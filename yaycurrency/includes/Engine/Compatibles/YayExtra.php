<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;
use YayExtra\Helper\Utils;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://yaycommerce.com/yayextra-woocommerce-extra-product-options/

class YayExtra {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! defined( 'YAYE_VERSION' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

		add_filter( 'yay_currency_get_price_options_by_cart_item', array( $this, 'get_price_options_by_cart_item' ), 10, 5 );
		// Define filter get price default (when disable Checkout in different currency option)
		add_filter( 'yay_currency_get_price_default_in_checkout_page', array( $this, 'get_price_default_in_checkout_page' ), 10, 2 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 10, 2 );
		add_filter( 'yay_currency_get_product_price_by_3rd_plugin', array( $this, 'get_product_price_by_3rd_plugin' ), 10, 3 );
		if ( YayCurrencyHelper::enable_rounding_currency( $this->apply_currency ) ) {
			add_filter( 'yaye_option_cost_display_orders_and_emails', array( $this, 'yaye_option_cost_display_cart_checkout' ), 10, 5 );
			// Change Option Cost again with type is percentage
			add_filter( 'yaye_option_cost_display_cart_checkout', array( $this, 'yaye_option_cost_display_cart_checkout' ), 10, 5 );
			add_filter( 'yaye_option_cost_display_orders_and_emails', array( $this, 'yaye_option_cost_display_cart_checkout' ), 10, 5 );
		}

		// Elementor Pro. Link plugin: https://elementor.com/pro/
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			add_filter( 'woocommerce_cart_item_price', array( $this, 'woocommerce_cart_item_price' ), 10, 3 );
			add_filter( 'woocommerce_cart_subtotal', array( $this, 'woocommerce_cart_subtotal' ), 10, 3 );
		}

	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {

		if ( isset( $cart_item['yaye_total_option_cost'] ) && ! empty( $cart_item['yaye_total_option_cost'] ) && YayCurrencyHelper::enable_rounding_currency( $apply_currency ) ) {

			$product_price_default_currency = (float) $cart_item['yaye_product_price_original'];
			$product_price_by_currency      = YayCurrencyHelper::calculate_price_by_currency( $product_price_default_currency, false, $apply_currency );

			$addition_cost_details     = $this->calculate_option_again( $cart_item['yaye_custom_option'], $product_price_by_currency );
			$total_option_cost_default = Utils::cal_total_option_cost_on_cart_item_static( $cart_item['yaye_custom_option'], $product_price_default_currency );

			if ( $addition_cost_details && isset( $addition_cost_details['percent'] ) && $addition_cost_details['percent'] ) {
				$options_price = $addition_cost_details['cost'];
			} else {
				$options_price = YayCurrencyHelper::calculate_price_by_currency( $total_option_cost_default, false, $apply_currency );
			}

			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_extra_price_options_default', (float) $total_option_cost_default );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_extra_set_price_with_options_default', $product_price_default_currency + $total_option_cost_default );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_extra_price_options', (float) $options_price );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_extra_set_price_with_options', (float) $product_price_by_currency + $options_price );

		}
	}

	public function get_addition_cost_by_option_selected( $option_meta, $option_val, $product_price_by_currency ) {
		$cost    = 0;
		$percent = false;
		if ( isset( $option_meta['optionValues'] ) && ! empty( $option_meta['optionValues'] ) ) {
			foreach ( $option_meta['optionValues'] as $option_value ) {
				if ( $option_value['value'] !== $option_val ) {
					continue;
				}
				$additional_cost = $option_value['additionalCost'];
				if ( $additional_cost['isEnabled'] && ! empty( $additional_cost['value'] ) ) {
					if ( 'fixed' === $additional_cost['costType']['value'] ) { // fixed.
						$cost = floatval( $additional_cost['value'] );
					} else { // percentage.
						$percent = true;
						$cost    = $product_price_by_currency * ( $additional_cost['value'] / 100 );
					}
				}
			}
		}

		$addition_cost = array(
			'percent' => $percent,
			'cost'    => $cost,
		);
		return $addition_cost;
	}

	public function calculate_option_again( $option_field_data, $product_price_by_currency ) {
		$addition_cost = false;
		foreach ( $option_field_data as $option_set_id => $option ) {
			if ( ! empty( $option ) ) {
				foreach ( $option as $option_id => $option_args ) {
					$option_meta = false;
					if ( class_exists( '\YayExtra\Init\CustomPostType' ) ) {
						$option_meta = \YayExtra\Init\CustomPostType::get_option( (int) $option_set_id, $option_id );
					}

					if ( $option_meta ) {

						$option_args = isset( $option_args['option_value'] ) ? array_shift( $option_args['option_value'] ) : false;
						$option_val  = $option_args ? $option_args['option_val'] : false;
						if ( $option_val ) {
							$option_has_addtion_cost_list = array( 'checkbox', 'radio', 'button', 'button_multi', 'dropdown', 'swatches', 'swatches_multi' );
							if ( in_array( $option_meta['type']['value'], $option_has_addtion_cost_list, true ) ) {
								$addition_cost = $this->get_addition_cost_by_option_selected( $option_meta, $option_val, $product_price_by_currency );
							}
						}
					}
				}
			}
		}
		return $addition_cost;
	}

	public function get_price_options_by_cart_item( $price_options, $cart_item, $product_id, $original_price, $apply_currency ) {
		$extra_price_options = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_currency_extra_price_options' );
		if ( $extra_price_options ) {
			return $extra_price_options;
		}

		if ( isset( $cart_item['yaye_total_option_cost'] ) ) {
			$price_options = (float) YayCurrencyHelper::calculate_price_by_currency( $cart_item['yaye_total_option_cost'], false, $apply_currency );
		}

		return $price_options;
	}

	public function yaye_option_cost_display_cart_checkout( $option_cost, $option_cost_value, $cost_type, $product_price_original, $product_id ) {

		if ( 'percentage' === $cost_type ) {
			$flag = Helper::default_currency_code() === $this->apply_currency['currency'] || YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency );
			if ( $flag ) {
				$option_cost = $product_price_original * ( $option_cost_value / 100 );
				return $option_cost;
			}

			$product_price_currency = YayCurrencyHelper::calculate_price_by_currency( $product_price_original, false, $this->apply_currency );
			return $product_price_currency * ( $option_cost_value / 100 );

		}

		return $option_cost;
	}

	public function get_price_default_in_checkout_page( $price, $product ) {
		$price_with_options_default = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_extra_set_price_with_options_default' );
		if ( $price_with_options_default ) {
			return $price_with_options_default;
		}
		return $product->get_price( 'edit' );
	}

	public function get_price_with_options( $price, $product ) {
		$price_with_options = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_extra_set_price_with_options' );
		if ( $price_with_options ) {
			return $price_with_options;
		}
		return $product->get_price( 'edit' );
	}

	public function get_product_price_by_3rd_plugin( $product_price, $product, $apply_currency ) {
		$price_with_options = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_extra_set_price_with_options' );
		if ( $price_with_options ) {
			return $price_with_options;
		}
		return $product_price;
	}

	// Elementor Pro
	public function woocommerce_cart_item_price( $price, $cart_item, $cart_item_key ) {
		$ajax_flag = ( isset( $_REQUEST['action'] ) && 'elementor_menu_cart_fragments' === $_REQUEST['action'] ) || ( isset( $_REQUEST['wc-ajax'] ) && 'get_refreshed_fragments' === $_REQUEST['wc-ajax'] );
		if ( wp_doing_ajax() && $ajax_flag ) {
			$product_price = $cart_item['data']->get_price( 'edit' );
			$product_price = YayCurrencyHelper::calculate_price_by_currency( $product_price, false, $this->apply_currency );
			$product_price = apply_filters( 'yay_currency_get_product_price_by_cart_item', $product_price, $cart_item, $this->apply_currency );
			$price         = YayCurrencyHelper::format_price( $product_price );
		}
		return $price;
	}

	public function woocommerce_cart_subtotal( $cart_subtotal, $compound, $cart ) {
		$ajax_flag = ( isset( $_REQUEST['action'] ) && 'elementor_menu_cart_fragments' === $_REQUEST['action'] ) || ( isset( $_REQUEST['wc-ajax'] ) && 'get_refreshed_fragments' === $_REQUEST['wc-ajax'] );
		if ( wp_doing_ajax() && $ajax_flag ) {
			$subtotal      = apply_filters( 'yay_currency_get_cart_subtotal', 0, $this->apply_currency );
			$cart_subtotal = YayCurrencyHelper::format_price( $subtotal );
		}
		return $cart_subtotal;
	}
}
