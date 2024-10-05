<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class WPCFrequentlyBoughtTogetherForWooCommerce {

	use SingletonTrait;

	private $converted_currency = array();
	private $apply_currency     = array();

	public function __construct() {

		if ( ! defined( 'WOOBT_VERSION' ) ) {
			return;
		}

		add_filter( 'yay_currency_detect_action_args', array( $this, 'yay_currency_detect_action_args' ), 10, 1 );

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

		add_filter( 'yay_currency_is_original_product_price', array( $this, 'is_original_product_price' ), 10, 3 );

		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'custom_mini_cart_price' ), 999 );

	}

	public function custom_mini_cart_price() {
		WC()->cart->calculate_totals();
	}

	public function yay_currency_detect_action_args( $action_args ) {
		$ajax_args   = array( 'woobt_add_all_to_cart' );
		$action_args = array_unique( array_merge( $action_args, $ajax_args ) );
		return $action_args;
	}

	public function product_addons_set_cart_contents( $cart_contents, $cart_item_key, $cart_item, $apply_currency ) {
		if ( isset( $cart_item['woobt_parent_id'], $cart_item['woobt_price_item'] ) && ( '100%' !== $cart_item['woobt_price_item'] ) && ( '' !== $cart_item['woobt_price_item'] ) ) {
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_woobt_product', true );
		}
	}

	public function is_original_product_price( $flag, $price, $product ) {
		$woobt_product = SupportHelper::get_cart_item_objects_property( $product, 'yay_woobt_product' );
		if ( $woobt_product ) {
			$flag = true;
		}
		return $flag;
	}
}
