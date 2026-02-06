<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;


class FunnelKitPlugins {
	use SingletonTrait;

	private $allow_action = array();
	public function __construct() {
		// FunnelKit Funnel Builder Pro,  Link plugin: https://funnelkit.com/wordpress-funnel-builder/
		if ( defined( 'WFFN_PRO_BUILD_VERSION' ) ) {
			array_push( $this->allow_action, 'wfocu_front_charge' );
		}
		//FunnelKit Payment Gateway for Stripe WooCommerce. Link plugin: https://www.funnelkit.com/
		if ( class_exists( 'FKWCS_Gateway_Stripe' ) ) {
			array_push( $this->allow_action, 'wfocu_front_handle_fkwcs_stripe_payments' );
		}

		if ( $this->allow_action ) {
			add_filter( 'yay_currency_is_original_product_price', array( $this, 'yay_currency_is_original_product_price' ), 10, 3 );
		}
	}

	public function yay_currency_is_original_product_price( $is_original_product_price, $price, $product ) {
		if ( wp_doing_ajax() && isset( $_REQUEST['wc-ajax'] ) ) {

			if ( 'wfocu_front_handle_paypal_payments' === $_REQUEST['wc-ajax'] ) {
				return true;
			}

			if ( ! in_array( $_REQUEST['wc-ajax'], $this->allow_action, true ) ) {
				return $is_original_product_price;
			}

			$changes = $product->get_changes();

			if ( is_array( $changes ) && isset( $changes['price'] ) && $price === $changes['price'] ) {
				$is_original_product_price = true;
			}
		}

		return $is_original_product_price;
	}
}
