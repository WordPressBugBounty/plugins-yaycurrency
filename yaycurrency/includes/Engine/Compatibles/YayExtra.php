<?php
namespace Yay_Currency\Engine\Compatibles;

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

		add_filter( 'YayCurrency/ApplyCurrency/GetPriceOptions', array( $this, 'get_price_options' ), 10, 2 );

		// Define filter get price default (when disable Checkout in different currency option)
		add_filter( 'YayCurrency/StoreCurrency/GetPrice', array( $this, 'get_price_default_in_checkout_page' ), 10, 2 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 10, 2 );

		// Calculate Product Subtotal & Cart Subtotal
		add_filter( 'yay_currency_extra_get_product_subtotal', array( $this, 'yay_get_product_subtotal' ), 10, 3 );

		// Woo Discount Rules PRO
		if ( SupportHelper::woo_discount_rules_active() ) {
			add_filter( 'advanced_woo_discount_extra_get_price_options', array( $this, 'advanced_woo_discount_extra_get_price_options' ), 10, 3 );
			add_filter( 'advanced_woo_discount_rules_cart_strikeout_price_html', array( $this, 'advanced_woo_discount_rules_cart_strikeout_price_html' ), 10, 4 );
		}

		// Change Option Cost again with type is percentage
		add_filter( 'yaye_option_cost_display_cart_checkout', array( $this, 'yaye_option_cost_display_cart_checkout' ), 10, 5 );
		add_filter( 'yaye_option_cost_display_orders_and_emails', array( $this, 'yaye_option_cost_display_cart_checkout' ), 10, 5 );

		add_filter( 'YayCurrency/ApplyCurrency/ByCartItem/GetPriceOptions', array( $this, 'get_price_options_by_cart_item' ), 10, 5 );
		add_filter( 'YayCurrency/StoreCurrency/ByCartItem/GetPriceOptions', array( $this, 'get_price_options_default_by_cart_item' ), 10, 4 );
	}

	public function product_addons_set_cart_contents( $cart_contents, $cart_item_key, $value, $apply_currency ) {
		if ( isset( $value['yaye_total_option_cost'] ) && ! empty( $value['yaye_total_option_cost'] ) ) {
			$product_obj                    = $value['data'];
			$product_price_default_currency = (float) $value['yaye_product_price_original'];
			$product_price_by_currency      = YayCurrencyHelper::calculate_price_by_currency( $product_price_default_currency, false, $apply_currency );

			$addition_cost_details     = $this->calculate_option_again( $value['yaye_custom_option'], $product_price_by_currency );
			$total_option_cost_default = Utils::cal_total_option_cost_on_cart_item_static( $value['yaye_custom_option'], $product_price_default_currency );

			if ( $addition_cost_details && isset( $addition_cost_details['percent'] ) && $addition_cost_details['percent'] ) {
				$options_price = $addition_cost_details['cost'];
			} else {
				$options_price = YayCurrencyHelper::calculate_price_by_currency( $total_option_cost_default, false, $apply_currency );
			}

			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_currency_extra_price_options_default', (float) $total_option_cost_default );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_currency_extra_set_price_with_options_default', $product_price_default_currency + $total_option_cost_default );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_currency_extra_price_options', (float) $options_price );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_currency_extra_set_price_with_options', (float) $product_price_by_currency + $options_price );
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
		$addition_cost                = false;
		$option_has_addtion_cost_list = array( 'checkbox', 'radio', 'button', 'button_multi', 'dropdown', 'swatches', 'swatches_multi' );
		foreach ( $option_field_data as $option_set_id => $option ) {
			if ( ! empty( $option ) ) {
				foreach ( $option as $option_id => $option_args ) {
					$option_meta = false;
					if ( class_exists( '\YayExtra\Init\CustomPostType' ) ) {
						$option_meta = \YayExtra\Init\CustomPostType::get_option( (int) $option_set_id, $option_id );
					}

					if ( $option_meta && is_array( $option_meta ) && in_array( $option_meta['type']['value'], $option_has_addtion_cost_list, true ) ) {
						$option_args = isset( $option_args['option_value'] ) && is_array( $option_args['option_value'] ) ? array_shift( $option_args['option_value'] ) : false;
						$option_val  = $option_args ? $option_args['option_val'] : false;
						if ( $option_val ) {
							$addition_cost = $this->get_addition_cost_by_option_selected( $option_meta, $option_val, $product_price_by_currency );
						}
					}
				}
			}
		}
		return $addition_cost;
	}

	public function get_price_options( $price_options, $product ) {
		$extra_price_options = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_extra_price_options' );
		if ( $extra_price_options ) {
			return $extra_price_options;
		}
		return $price_options;
	}


	public function get_price_default_in_checkout_page( $price, $product ) {
		$price_with_options_default = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_extra_set_price_with_options_default' );
		if ( $price_with_options_default ) {
			return $price_with_options_default;
		}
		$price = SupportHelper::get_product_price( $product->get_id() );
		return $price;
	}

	public function get_price_with_options( $price, $product ) {
		$price_with_options = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_extra_set_price_with_options' );
		if ( $price_with_options ) {
			return $price_with_options;
		}

		$price = SupportHelper::get_product_price( $product->get_id(), $this->apply_currency );
		return $price;

	}

	public function get_price_options_by_cart_item( $price_options, $cart_item, $product_id, $original_price, $apply_currency ) {
		$extra_price_options = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_currency_extra_price_options' );
		if ( $extra_price_options ) {
			return $extra_price_options;
		}

		return $price_options;
	}

	public function get_price_options_default_by_cart_item( $price_options, $cart_item, $product_id, $original_price ) {
		$extra_price_options_default = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_currency_extra_price_options_default' );
		if ( $extra_price_options_default ) {
			return $extra_price_options_default;
		}

		return $price_options;
	}

	public function yay_get_product_subtotal( $subtotal, $product_obj, $quantity ) {
		$extra_price_with_options = SupportHelper::get_cart_item_objects_property( $product_obj, 'yay_currency_extra_set_price_with_options' );
		if ( $extra_price_with_options ) {
			return $extra_price_with_options * $quantity;
		}
		return $subtotal;
	}

	// Compatible with Woo Discount Rules PRO
	public function advanced_woo_discount_extra_get_price_options( $cart_item, $product_obj, $apply_currency ) {
		$option_cost = isset( $cart_item['yaye_total_option_cost'] ) && ! empty( $cart_item['yaye_total_option_cost'] ) ? $cart_item['yaye_total_option_cost'] : 0;
		if ( $option_cost ) {
			$product_id    = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
			$initial_price = SupportHelper::get_original_price_apply_discount_pro( $product_id );
			if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $apply_currency ) ) {
				$price_options = $option_cost;
			} else {
				$price_options = YayCurrencyHelper::calculate_price_by_currency( $option_cost, false, $apply_currency );
				$initial_price = YayCurrencyHelper::calculate_price_by_currency( $initial_price, false, $apply_currency );
			}
			return array(
				'price_options'                  => $price_options,
				'initial_price'                  => $initial_price,
				'regular_discount_price_options' => $initial_price + $price_options,
			);
		}
		return false;
	}

	public function advanced_woo_discount_rules_cart_strikeout_price_html( $new_item_price_html, $item_price, $cart_item, $cart_item_key ) {
		if ( isset( $cart_item['yaye_total_option_cost'] ) ) {
			$product_obj = $cart_item['data'];
			if ( isset( $product_obj->awdr_discount_price ) ) {
				$regular_discount_price = $product_obj->awdr_discount_regular_discount_price_options;
				$sale_discount_price    = $product_obj->awdr_discount_price;
				$new_item_price_html    = '<div class="awdr_cart_strikeout_line">' . wc_format_sale_price( $regular_discount_price, $sale_discount_price ) . '</div>';
			}
		}
		return $new_item_price_html;
	}

	public function yaye_option_cost_display_cart_checkout( $option_cost, $option_cost_value, $cost_type, $product_price_original, $product_id ) {
		$flag = 1 === floatval( $this->apply_currency['rate'] ) || YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency );

		if ( 'percentage' === $cost_type ) {
			if ( $flag ) {
				$option_cost = $product_price_original * ( $option_cost_value / 100 );
				return $option_cost;
			}
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return $option_cost;
			}

			$price_original          = YayCurrencyHelper::calculate_price_by_currency( $product_price_original, false, $this->apply_currency );
			$calculate_price_percent = $price_original * ( $option_cost_value / 100 );
			if ( $calculate_price_percent !== $option_cost ) {
				return $calculate_price_percent;
			}
		}

		return $option_cost;
	}
}
