<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://pluginrepublic.com/wordpress-plugins/woocommerce-product-add-ons-ultimate/

class WooCommerceProductAddOnsUltimate {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! defined( 'PEWC_PLUGIN_VERSION' ) ) {
			return;
		}
		$this->apply_currency = YayCurrencyHelper::detect_current_currency();
		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );
		add_filter( 'pewc_after_add_cart_item_data', array( $this, 'pewc_after_add_cart_item_data' ), 10, 1 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 10, 2 );
		add_filter( 'yay_currency_get_price_default_in_checkout_page', array( $this, 'get_price_default_in_checkout_page' ), 10, 2 );

		add_filter( 'pewc_filter_field_price', array( $this, 'pewc_yay_currency_convert_price' ), 10, 3 );
		add_filter( 'pewc_filter_option_price', array( $this, 'pewc_yay_currency_convert_price' ), 10, 3 );

	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {
		$product_extras = isset( $cart_item['product_extras'] ) ? $cart_item['product_extras'] : false;
		if ( $product_extras && isset( $product_extras['yay_currency'] ) ) {
			$product_id                = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
			$price_with_extras_default = SupportHelper::get_product_price( $product_id );
			$price_with_extras         = $product_extras['yay_currency'] === $apply_currency['currency'] ? $product_extras['price_with_extras'] : YayCurrencyHelper::calculate_price_by_currency( $price_with_extras_default, false, $apply_currency );

			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_product_price_with_extras_by_currency', $price_with_extras );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_product_price_with_extras_by_default', $price_with_extras_default );
		}
	}

	public function pewc_after_add_cart_item_data( $cart_item_data ) {
		$product_extras = isset( $cart_item_data['product_extras'] ) && ! empty( $cart_item_data['product_extras'] ) ? $cart_item_data['product_extras'] : false;
		if ( $product_extras ) {
			$cart_item_data['product_extras']['yay_currency'] = $this->apply_currency['currency'];
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

	public function pewc_yay_currency_convert_price( $option_price, $item, $product ) {

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $option_price;
		}

		$option_price = YayCurrencyHelper::calculate_price_by_currency( $option_price, false, $this->apply_currency );

		return $option_price;

	}
}
