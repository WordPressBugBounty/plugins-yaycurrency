<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://woocommerce.com/products/woocommerce-paypal-payments/

class WooCommercePayPalPayments {

	use SingletonTrait;

	private $apply_currency   = array();
	private $default_currency = '';
	private $is_dis_checkout_diff_currency;
	public function __construct() {

		if ( ! class_exists( 'WooCommerce\PayPalCommerce\PPCP' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		if ( ! $this->apply_currency ) {
			return;
		}
		$this->default_currency              = Helper::default_currency_code();
		$this->is_dis_checkout_diff_currency = YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency );

		add_filter( 'woocommerce_currency', array( $this, 'woocommerce_currency' ), 999 );
		add_filter( 'woocommerce_paypal_args', array( $this, 'custom_request_paypal' ), 10, 2 );

		if ( $this->is_dis_checkout_diff_currency ) {

			add_filter( 'yay_currency_disable_fallback_checkout_conditions', array( $this, 'disable_fallback_checkout_conditions' ), 10, 1 );

			add_filter( 'yay_currency_woocommerce_currency', array( $this, 'custom_currency_paypal_method' ), 10, 2 );
			add_filter( 'yay_currency_is_original_default_currency', array( $this, 'is_original_default_currency' ), 20, 2 );

			add_filter( 'yay_currency_localize_args', array( $this, 'add_localize_args' ), 10, 1 );

			if ( isset( $_COOKIE['ppc_paypal_cart_or_product_page'] ) ) {
				add_filter( 'woocommerce_cart_get_total', array( $this, 'convert_to_default' ), 999, 1 );
			}
		}

	}

	public function woocommerce_currency( $currency ) {

		if ( is_admin() && ! wp_doing_ajax() ) {
			return $currency;
		}

		if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'woocommerce_load_variations' === $_REQUEST['action'] ) {
			return $currency;
		}

		if ( ! $this->is_dis_checkout_diff_currency && isset( $this->apply_currency['currency'] ) ) {
			$currency = $this->apply_currency['currency'];
		}

		return $currency;
	}

	public function disable_fallback_checkout_conditions( $flag ) {
		$flag = false;
		if ( wp_doing_ajax() && isset( $_COOKIE['ppc_paypal_checkout_page'] ) && isset( $_REQUEST['wc-ajax'] ) ) {
			$wc_ajax_conditions = array( 'get_refreshed_fragments', 'wc_stripe_get_cart_details' );
			$flag               = in_array( $_REQUEST['wc-ajax'], $wc_ajax_conditions );
		}
		return $flag;
	}

	public function custom_currency_paypal_method( $currency, $is_dis_checkout_diff_currency ) {

		if ( $is_dis_checkout_diff_currency ) {
			$currency = $this->default_currency;
		}

		return $currency;

	}

	public function is_calculate_total_default_currency() {
		$flag = false;

		if ( wp_doing_ajax() ) {
			$args_ajax = array( 'ppc-create-order', 'ppc-save-checkout-form' );
			if ( isset( $_REQUEST['wc-ajax'] ) && in_array( $_REQUEST['wc-ajax'], $args_ajax ) ) {
				return true;
			}
		}

		return $flag;
	}

	public function is_original_default_currency( $flag, $apply_currency ) {

		if ( $this->is_calculate_total_default_currency() ) {
			$flag = true;
		}

		return $flag;
	}

	public function custom_request_paypal( $args, $order ) {
		if ( $this->is_dis_checkout_diff_currency ) {
			$currency_code = $this->default_currency;
		} else {
			$currency_code = isset( $this->apply_currency['currency'] ) ? $this->apply_currency['currency'] : $this->default_currency;
		}
		$args['currency_code'] = $currency_code;
		return $args;
	}

	public function add_localize_args( $localize_args ) {
		if ( ! isset( $localize_args['ppc_paypal'] ) ) {
			$localize_args['ppc_paypal'] = 'yes';
		}
		if ( ! isset( $localize_args['product_page'] ) ) {
			$localize_args['product_page'] = is_product() || is_singular( 'product' );
		}
		return $localize_args;
	}

	public function convert_to_default( $real_value ) {
		if ( $this->is_calculate_total_default_currency() ) {
			$real_value = floatval( $real_value / YayCurrencyHelper::get_rate_fee( $this->apply_currency ) );
		}
		return $real_value;
	}
}
