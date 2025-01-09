<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://wordpress.org/plugins/woo-product-bundle/
class WPCProductBundles {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! defined( 'WOOSB_VERSION' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 9999, 4 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 10, 2 );

	}


	public function init() {
		if ( class_exists( 'WPCleverWoosb' ) ) {
			$WPCleverWoosb = \WPCleverWoosb::instance();
			remove_filter( 'woocommerce_get_price_html', array( $WPCleverWoosb, 'get_price_html' ), 99, 2 );
		}
	}

	public function product_addons_set_cart_contents( $cart_contents, $cart_item_key, $cart_item, $apply_currency ) {
		$product_id          = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
		$product             = wc_get_product( $product_id );
		$woosb_product_price = $product->get_price( 'edit' );
		$woosb_product_price = YayCurrencyHelper::calculate_price_by_currency( $woosb_product_price, false, $this->apply_currency );
		if ( isset( $cart_item['woosb_key'] ) && isset( $cart_item['woosb_parent_key'] ) ) {
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_currency_woosb_bundle_product', $woosb_product_price );
		}
		if ( isset( $cart_item['woosb_ids'], $cart_item['woosb_keys'] ) ) {
			WC()->cart->cart_contents[ $cart_item_key ]['woosb_price'] = $woosb_product_price;
		}
	}

	public function get_price_with_options( $price, $product ) {
		$woosb_bundle_product_price = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_woosb_bundle_product' );
		if ( $woosb_bundle_product_price ) {
			return $woosb_bundle_product_price;
		}
		return $price;
	}
}
