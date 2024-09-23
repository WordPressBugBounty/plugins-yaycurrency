<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://woocommerce.com/products/product-add-ons/

class WooCommerceProductAddons {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! defined( 'WC_PRODUCT_ADDONS_VERSION' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

		add_filter( 'yay_currency_price_options', array( $this, 'get_price_options' ), 10, 2 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 10, 2 );

		add_filter( 'woocommerce_product_addons_option_price_raw', array( $this, 'custom_product_addons_option_price' ), 10, 2 );
		add_filter( 'woocommerce_product_addons_get_item_data', array( $this, 'custom_cart_item_addon_data' ), 10, 3 );
		// Place Order
		add_filter( 'woocommerce_product_addons_order_line_item_meta', array( $this, 'custom_order_line_item_meta' ), 10, 4 );
		add_filter( 'yay_currency_get_price_default_in_checkout_page', array( $this, 'get_price_default_in_checkout_page' ), 10, 2 );

		add_filter( 'yay_currency_get_product_price_by_3rd_plugin', array( $this, 'get_product_price_by_3rd_plugin' ), 10, 3 );
		add_filter( 'yay_currency_get_price_options_by_cart_item', array( $this, 'get_price_options_by_addons' ), 10, 5 );

	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {
		$addons = isset( $cart_item['addons'] ) ? $cart_item['addons'] : false;
		if ( $addons ) {
			$data_details                   = $this->calculate_price_options_by_cart_item( $cart_item, $apply_currency );
			$price_options_current_currency = $data_details['price_options_current_currency'];
			$price_options_default_currency = $data_details['price_options_default_currency'];

			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_addon_original_price_options', $price_options_default_currency );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_addon_price_options_by_currency', $price_options_current_currency );

			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_addon_set_options_price_default', $price_options_default_currency );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_addon_set_price_with_options_default', $data_details['product_price_with_option_default_currency'] );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_addon_set_options_price', $price_options_current_currency );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_addon_set_price_with_options', $data_details['product_price_with_option_current_currency'] );
		}
	}

	public function get_price_options_by_addons( $price_options, $cart_item, $product_id, $original_price, $apply_currency ) {
		$addons = isset( $cart_item['addons'] ) ? $cart_item['addons'] : false;
		if ( ! $addons ) {
			return $price_options;
		}

		$data_details = $this->calculate_price_options_by_cart_item( $cart_item, $this->apply_currency );

		return isset( $data_details['price_options_current_currency'] ) ? $data_details['price_options_current_currency'] : $price_options;
	}

	public function get_product_price_default_currency( $cart_item ) {
		$product_id    = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
		$_product      = wc_get_product( $product_id );
		$product_price = $_product->get_price( 'edit' );
		return (float) $product_price;
	}

	public function get_price_options( $price_options, $product ) {
		$options_price = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_addon_set_options_price' );
		if ( $options_price ) {
			return $options_price;
		}
		return $price_options;
	}

	public function get_price_with_options( $price, $product ) {
		$price_with_options = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_addon_set_price_with_options' );
		if ( $price_with_options ) {
			return $price_with_options;
		}
		return $price;
	}

	public function get_price_options_by_cart_item( $product_price, $cart_item ) {
		$addons        = isset( $cart_item['addons'] ) ? $cart_item['addons'] : false;
		$price_options = 0;
		if ( $addons ) {
			foreach ( $addons as $key => $addon ) {
				if ( isset( $addon['price_type'] ) ) {
					if ( 'percentage_based' !== $addon['price_type'] ) {
						$price_options += YayCurrencyHelper::calculate_price_by_currency( $addon['price'], false, $this->apply_currency );
					} else {
						$price_options += (float) $product_price * $addon['price'] / 100;
					}
				}
			}
		}
		return $price_options;
	}

	public function get_cart_subtotal_3rd_plugin( $subtotal, $apply_currency ) {
		$subtotal      = 0;
		$cart_contents = WC()->cart->get_cart_contents();
		foreach ( $cart_contents  as $key => $cart_item ) {
			$product_price    = SupportHelper::calculate_product_price_by_cart_item( $cart_item );
			$price_options    = $this->get_price_options_by_cart_item( $product_price, $cart_item );
			$product_subtotal = ( $product_price + $price_options ) * $cart_item['quantity'];
			$subtotal         = $subtotal + YayCurrencyHelper::calculate_price_by_currency( $product_subtotal, false, $apply_currency );
		}

		return $subtotal;
	}

	public function custom_product_addons_option_price( $price, $option ) {
		if ( 'percentage_based' !== $option['price_type'] ) {
			$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		}
		return $price;
	}

	public function custom_formatted_item_fee( $args_price_option, $apply_currency, $addon ) {
		$item_fee = isset( $args_price_option['price_options_current_currency'] ) ? $args_price_option['price_options_current_currency'] : (float) $addon['price'];

		$formatted_item_fee = YayCurrencyHelper::format_price( $item_fee );

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $apply_currency ) ) {
			$item_fee           = isset( $args_price_option['price_options_default_currency'] ) ? $args_price_option['price_options_default_currency'] : (float) $addon['price'];
			$formatted_item_fee = wc_price( $item_fee );
		}
		return $addon['value'] . ' (+ ' . $formatted_item_fee . ')';
	}

	public function custom_cart_item_addon_data( $addon_data, $addon, $cart_item ) {
		$addon_price = isset( $addon['price'] ) && ! empty( $addon['price'] ) ? $addon['price'] : false;
		if ( $addon_price ) {

			$args = $this->calculate_price_options_by_cart_item( $cart_item, $this->apply_currency, $addon['value'] );
			if ( ! $args ) {
				return $addon_data;
			}
			$cart_item_addon_data_value = $this->custom_formatted_item_fee( $args, $this->apply_currency, $addon );
			$addon_data['value']        = apply_filters( 'yay_currency_cart_item_addon_data', $cart_item_addon_data_value, $args, $cart_item, $addon, $this->apply_currency );
		}

		return $addon_data;

	}

	public function custom_order_line_item_meta( $meta_data, $addon, $item, $cart_item ) {

		$addon_price = isset( $addon['price'] ) && ! empty( $addon['price'] ) ? $addon['price'] : false;

		if ( ! $addon_price ) {
			return $meta_data;
		}

		$args = $this->calculate_price_options_by_cart_item( $cart_item, $this->apply_currency, $addon['value'] );

		if ( ! $args ) {
			return $meta_data;
		}

		$meta_data['value'] = $this->custom_formatted_item_fee( $args, $this->apply_currency, $addon );

		return $meta_data;

	}

	public function calculate_price_options_by_cart_item( $cart_item, $apply_currency, $addon_value = false ) {

		$price_options_default_currency = 0;
		$price_options_current_currency = 0;
		$product_price                  = $this->get_product_price_default_currency( $cart_item );
		$product_price_by_currency      = YayCurrencyHelper::calculate_price_by_currency( $product_price, false, $apply_currency );
		if ( ! $addon_value ) {
			foreach ( $cart_item['addons'] as $key => $addon ) {
				if ( isset( $addon['price_type'] ) ) {
					if ( 'percentage_based' !== $addon['price_type'] ) {
						$price_options_default_currency += (float) $addon['price'];
						$price_options_current_currency += YayCurrencyHelper::calculate_price_by_currency( $addon['price'], false, $apply_currency );
					} else {
						$price_options_default_currency += $product_price * ( $addon['price'] / 100 );
						$price_options_current_currency += $product_price_by_currency * ( $addon['price'] / 100 );
					}
				}
			}
		} else {
			$result = array_filter(
				$cart_item['addons'],
				function ( $option ) use ( $addon_value ) {
					if ( $option['value'] === $addon_value ) {
						return true;
					}
					return false;
				}
			);

			if ( $result ) {
				$addon = $result ? array_shift( $result ) : false;
				if ( 'percentage_based' !== $addon['price_type'] ) {
					$price_options_default_currency = (float) $addon['price'];
					$price_options_current_currency = YayCurrencyHelper::calculate_price_by_currency( $addon['price'], false, $apply_currency );
				} else {
					$price_options_default_currency = $product_price * ( $addon['price'] / 100 );
					$price_options_current_currency = $product_price_by_currency * ( $addon['price'] / 100 );
				}
			}
		}

		$data = array(
			'price_options_default_currency'             => $price_options_default_currency,
			'price_options_current_currency'             => $price_options_current_currency,
			'product_price_with_option_default_currency' => $product_price + $price_options_default_currency,
			'product_price_with_option_current_currency' => $product_price_by_currency + $price_options_current_currency,
		);
		return $data;
	}

	public function get_price_default_in_checkout_page( $price, $product ) {
		$price_with_options_default = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_addon_set_price_with_options_default' );
		if ( $price_with_options_default ) {
			return $price_with_options_default;
		}
		return $price;
	}

	public function get_product_price_by_3rd_plugin( $product_price, $product, $apply_currency ) {
		$price_with_options = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_addon_set_price_with_options' );
		if ( $price_with_options ) {
			return $price_with_options;
		}
		return $product_price;
	}
}
