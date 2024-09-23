<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;

use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class Cartflows {
	use SingletonTrait;

	private $cookie_name;

	public function __construct() {
		// Compatible with Cartflows plugin && betheme theme
		if ( class_exists( 'Cartflows_Checkout' ) || wp_doing_ajax() ) {
			$this->cookie_name = YayCurrencyHelper::get_cookie_name();
			add_filter( 'woocommerce_cart_product_subtotal', array( $this, 'custom_product_subtotal' ), 10, 4 );
			add_filter( 'woocommerce_cart_subtotal', array( $this, 'custom_cart_subtotal' ), 10, 3 );
			add_filter( 'woocommerce_cart_total', array( $this, 'custom_cart_total' ) );

		}

	}

	public function custom_product_subtotal( $product_subtotal, $product, $quantity, $cart ) {
		if ( is_checkout() ) {
			return $product_subtotal;
		}

		if ( isset( $_COOKIE[ $this->cookie_name ] ) ) {
			$currency_ID    = sanitize_key( $_COOKIE[ $this->cookie_name ] );
			$apply_currency = YayCurrencyHelper::get_currency_by_ID( $currency_ID );
			$reversed_price = YayCurrencyHelper::reverse_calculate_price_by_currency( $product->get_price() );
			$price          = YayCurrencyHelper::calculate_price_by_currency( $reversed_price * $quantity, false, $apply_currency );
			$price          = YayCurrencyHelper::format_price( $price );
			return $price;
		}

		return $product_subtotal;
	}

	public function custom_cart_subtotal( $price, $compound, $cart ) {
		if ( is_checkout() ) {
			return $price;
		}
		if ( isset( $_COOKIE[ $this->cookie_name ] ) ) {
			$currency_ID    = sanitize_key( $_COOKIE[ $this->cookie_name ] );
			$apply_currency = YayCurrencyHelper::get_currency_by_ID( $currency_ID );
			$reversed_price = YayCurrencyHelper::reverse_calculate_price_by_currency( WC()->cart->get_displayed_subtotal() );
			$price          = YayCurrencyHelper::calculate_price_by_currency( $reversed_price, false, $apply_currency );
			$price          = YayCurrencyHelper::format_price( $price );
			return $price;
		}
		return $price;
	}

	public function custom_cart_total( $price ) {
		if ( is_checkout() || is_cart() ) {
			return $price;
		}
		if ( isset( $_COOKIE[ $this->cookie_name ] ) ) {
			$currency_ID    = sanitize_key( $_COOKIE[ $this->cookie_name ] );
			$apply_currency = YayCurrencyHelper::get_currency_by_ID( $currency_ID );
			$price          = YayCurrencyHelper::calculate_price_by_currency( WC()->cart->total, false, $apply_currency );
			$price          = YayCurrencyHelper::format_price( $price );
			return $price;
		}
		return $price;
	}
}
