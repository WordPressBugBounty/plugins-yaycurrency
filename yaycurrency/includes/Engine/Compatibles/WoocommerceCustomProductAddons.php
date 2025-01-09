<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;

use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;

class WoocommerceCustomProductAddons {
	use SingletonTrait;


	private $apply_currency;

	public function __construct() {

		if ( ! defined( 'WCPA_VERSION' ) ) {
			return;
		}
		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'remove_action_hook_woocommerce_cart_loaded_from_session' ), 9 );

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

		add_filter( 'yay_currency_is_original_product_price', array( $this, 'is_original_product_price' ), 10, 3 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'product_price_3rd_with_condition' ), 10, 2 );
		add_filter( 'yay_currency_get_price_default_in_checkout_page', array( $this, 'get_price_default_in_checkout_page' ), 10, 2 );

	}

	public function remove_action_hook_woocommerce_cart_loaded_from_session() {
		if ( class_exists( 'Acowebs\WCPA\Cart' ) ) {
			remove_action( 'woocommerce_cart_loaded_from_session', array( 'Acowebs\WCPA\Cart', 'before_calculate_totals_session' ), 10 );
		}
	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {
		if ( isset( $cart_item['wcpaIgnore'] ) ) {
			$product_price         = $cart_item['data']->get_price();
			$product_price_default = $cart_item['data']->get_price( 'default' );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_wcpa_product_price', $product_price );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_wcpa_product_price_default', $product_price_default );
		}
	}

	public function is_original_product_price( $flag, $price, $product ) {
		if ( doing_action( 'woocommerce_add_to_cart' ) ) {
			return true;
		}
		return $flag;
	}

	public function product_price_3rd_with_condition( $price, $product ) {
		$yay_wcpa_product_price = SupportHelper::get_cart_item_objects_property( $product, 'yay_wcpa_product_price' );
		if ( $yay_wcpa_product_price ) {
			return $yay_wcpa_product_price;
		}
		return $price;
	}

	public function get_price_default_in_checkout_page( $price, $product ) {
		$yay_wcpa_product_price_default = SupportHelper::get_cart_item_objects_property( $product, 'yay_wcpa_product_price_default' );
		if ( $yay_wcpa_product_price_default ) {
			return $yay_wcpa_product_price_default;
		}
		return $price;
	}
}
