<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin:

class FunnelKitCart {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( defined( 'FKCART_VERSION' ) ) {
			$this->apply_currency = YayCurrencyHelper::detect_current_currency();
			add_filter( 'woocommerce_cart_item_price', array( $this, 'woocommerce_cart_item_price' ), 99999, 3 );
			add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'woocommerce_cart_item_price' ), 99999, 3 );
			add_filter( 'woocommerce_cart_get_subtotal', array( $this, 'woocommerce_cart_get_subtotal' ), 99999 );
		}
	}

	public function woocommerce_cart_item_price( $price, $cart_item, $cart_item_key ) {
		if ( wp_doing_ajax() && ! is_cart() && ! is_checkout() ) {
			$price = $cart_item['data']->get_price( 'edit' );
			$price = YayCurrencyHelper::calculate_price_by_currency_html( $this->apply_currency, $price, $cart_item['quantity'] );
		}
		return $price;
	}

	public function woocommerce_cart_get_subtotal( $subtotal ) {
		if ( wp_doing_ajax() && ! is_cart() && ! is_checkout() ) {
			$subtotal = YayCurrencyHelper::calculate_price_by_currency( $subtotal, false, $this->apply_currency );
		}
		return $subtotal;
	}
}
