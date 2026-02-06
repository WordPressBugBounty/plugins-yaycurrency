<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://pluginrepublic.com/wordpress-plugins/woocommerce-product-add-ons-ultimate/

class WooCommerceProductAddOnsUltimate {
	use SingletonTrait;

	private $apply_currency = array();
	private $default_currency;
	public function __construct() {

		if ( ! defined( 'PEWC_PLUGIN_VERSION' ) ) {
			return;
		}
		$this->apply_currency   = YayCurrencyHelper::detect_current_currency();
		$this->default_currency = Helper::default_currency_code();
		add_filter( 'YayCurrency/ThirdPlugins/Rounding/Enable', '__return_true' );
		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );
		add_filter( 'pewc_after_add_cart_item_data', array( $this, 'pewc_after_add_cart_item_data' ), 10, 1 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 10, 2 );
		add_filter( 'YayCurrency/StoreCurrency/GetPrice', array( $this, 'get_price_default_in_checkout_page' ), 10, 2 );

		add_filter( 'YayCurrency/ApplyCurrency/ByCartItem/GetProductPrice', array( $this, 'get_product_price_by_cart_item' ), 10, 3 );
		add_filter( 'YayCurrency/ApplyCurrency/ThirdPlugins/GetProductPrice', array( $this, 'get_product_price_by_3rd_plugin' ), 10, 3 );

		add_filter( 'pewc_filter_field_price', array( $this, 'pewc_yay_currency_convert_price' ), 10, 3 );
		add_filter( 'pewc_filter_option_price', array( $this, 'pewc_yay_currency_convert_price' ), 10, 3 );

		add_filter( 'pewc_filter_item_value_in_cart', array( $this, 'pewc_filter_item_value_in_cart' ), 10, 2 );
		add_filter( 'pewc_end_get_item_data', array( $this, 'pewc_end_get_item_data' ), 10, 3 );

		add_filter( 'yay_currency_is_original_product_price', array( $this, 'is_original_product_price' ), 10, 3 );
		add_filter( 'pewc_price_with_extras_before_calc_totals', array( $this, 'pewc_price_with_extras_before_calc_totals' ), 10, 2 );
	}

	public function pewc_price_with_extras_before_calc_totals( $price, $cart_item ) {
		$currency_code_when_add_to_cart = $cart_item['product_extras']['yay_currency'] ?? '';
		if ( ! empty( $currency_code_when_add_to_cart ) ) {
			$current_currency = YayCurrencyHelper::get_current_currency();
			if ( $current_currency['currency'] !== $currency_code_when_add_to_cart ) {
				$currency_when_add_to_cart = YayCurrencyHelper::get_currency_by_currency_code( $currency_code_when_add_to_cart );
				$base_price                = YayCurrencyHelper::reverse_calculate_price_by_currency( $cart_item['product_extras']['price_with_extras'], $currency_when_add_to_cart );

				$price = YayCurrencyHelper::calculate_price_by_currency( $base_price, false, $current_currency );

			}
		}
		return $price;
	}

	public function is_original_product_price( $flag, $price, $product ) {
		$changes = $product->get_changes();
		if ( is_array( $changes ) && isset( $changes['price'] ) && $price === $changes['price'] ) {
			return true;
		}
		return $flag;
	}

	public function pewc_filter_item_value_in_cart( $value, $item ) {

		if ( ! $item['price'] || ! $item['price'] ) {
			return $value;
		}

		return $value . '-yay_currency_flag';
	}

	public function pewc_end_get_item_data( $other_data, $cart_item, $groups ) {
		$yay_currency = isset( $cart_item['product_extras'] ) && isset( $cart_item['product_extras']['yay_currency'] ) ? $cart_item['product_extras']['yay_currency'] : false;
		if ( ! $yay_currency || ! isset( $this->apply_currency ) ) {
			return $other_data;
		}
		$current_currency = $this->apply_currency;
		$extra_price      = $yay_currency['extra_price_default'];
		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $current_currency ) ) {
			$current_currency = YayCurrencyHelper::get_currency_by_currency_code( $this->default_currency );
		}
		foreach ( $other_data as $other_key => $other_value ) {
			$display = isset( $other_value['display'] ) && ! empty( $other_value['display'] ) ? $other_value['display'] : false;
			if ( strpos( $display, '-yay_currency_flag' ) !== false ) {
				$display = isset( $other_value['value'] ) && ! empty( $other_value['value'] ) ? $other_value['value'] : str_replace( '-yay_currency_flag', '', $display );
				$display = preg_replace_callback(
					'/\((.)(.*?)\)/',
					function () use ( $extra_price, $current_currency ) {
						$newValue     = YayCurrencyHelper::calculate_price_by_currency( floatval( $extra_price ), false, $current_currency );
						$format_price = preg_replace( '/<[^>]+>/', '', YayCurrencyHelper::format_price( $newValue, $current_currency ) );
						return ' (' . $format_price . ')';
					},
					$display
				);

				$other_data[ $other_key ]['display'] = $display;
			}
		}
		return $other_data;
	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {
		$product_extras = isset( $cart_item['product_extras'] ) ? $cart_item['product_extras'] : false;
		if ( $product_extras && isset( $product_extras['price_with_extras'] ) ) {
			$yay_currency = isset( $product_extras['yay_currency'] ) && ! empty( $product_extras['yay_currency'] ) ? $product_extras['yay_currency'] : false;
			if ( $yay_currency && isset( $yay_currency['currency_code'] ) ) {
				$product_id                = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
				$price_with_extras         = $product_extras['price_with_extras'];
				$price_default             = wc_get_product( $product_id )->get_price( 'edit' );
				$extra_price_default       = $yay_currency['extra_price_default'];
				$price_with_extras_default = $price_default + $extra_price_default;
				$current_currency          = isset( $this->apply_currency['currency'] ) ? $this->apply_currency['currency'] : $this->default_currency;
				if ( $yay_currency['currency_code'] !== $current_currency ) {
					$extra_price       = apply_filters( 'yay_currency_convert_price', $extra_price_default, $this->apply_currency );
					$product_price     = apply_filters( 'yay_currency_convert_price', $price_default, $this->apply_currency );
					$price_with_extras = $extra_price + $product_price;
				}
				SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_product_price_with_extras_by_currency', $price_with_extras );
				SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_product_price_with_extras_by_default', $price_with_extras_default );
			}
		}
	}

	public function pewc_after_add_cart_item_data( $cart_item_data ) {
		$product_extras = isset( $cart_item_data['product_extras'] ) && ! empty( $cart_item_data['product_extras'] ) ? $cart_item_data['product_extras'] : false;

		if ( $product_extras ) {
			$price_with_extras = isset( $product_extras ['price_with_extras'] ) && ! empty( $product_extras ['price_with_extras'] ) ? $product_extras ['price_with_extras'] : false;
			$original_price    = isset( $product_extras ['original_price'] ) && ! empty( $product_extras ['original_price'] ) ? $product_extras ['original_price'] : false;
			if ( $price_with_extras && $original_price ) {
				$extra_price = $price_with_extras - $original_price;
				if ( $this->default_currency === $this->apply_currency['currency'] ) {
					$extra_price_default = $extra_price;
				} else {
					$extra_price_default = apply_filters( 'yay_currency_revert_price', $extra_price, $this->apply_currency );
				}
				$cart_item_data['product_extras']['yay_currency'] = array(
					'currency_code'       => $this->apply_currency['currency'],
					'extra_price_default' => number_format( $extra_price_default ),
				);
			}
		}
		return $cart_item_data;
	}

	public function get_price_with_options( $price, $product ) {
		$product_price_with_extras = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_product_price_with_extras_by_currency' );
		if ( $product_price_with_extras ) {
			return $product_price_with_extras;
		}
		return $price;
	}

	public function get_price_default_in_checkout_page( $price, $product ) {
		$product_price_with_extras_default = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_product_price_with_extras_by_default' );
		if ( $product_price_with_extras_default ) {
			return $product_price_with_extras_default;
		}
		return $price;
	}

	public function get_product_price_by_cart_item( $price, $cart_item, $apply_currency ) {
		$product_price_with_extras = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_currency_product_price_with_extras_by_currency' );
		if ( $product_price_with_extras ) {
			return $product_price_with_extras;
		}
		return $price;
	}

	public function get_product_price_by_3rd_plugin( $product_price, $product, $apply_currency ) {
		$product_price_with_extras = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_product_price_with_extras_by_currency' );
		if ( $product_price_with_extras ) {
			return $product_price_with_extras;
		}
		return $product_price;
	}

	public function pewc_yay_currency_convert_price( $option_price, $item, $product ) {

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $option_price;
		}

		$option_price = apply_filters( 'yay_currency_convert_price', $option_price, $this->apply_currency );

		return $option_price;

	}
}
