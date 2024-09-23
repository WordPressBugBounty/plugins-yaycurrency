<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://wordpress.org/plugins/express-checkout-paypal-payment-gateway-for-woocommerce/

class PaymentGatewayForPayPalWooCommerce {

	use SingletonTrait;

	private $apply_currency = array();
	private $is_dis_checkout_diff_currency;

	public function __construct() {

		if ( ! defined( 'EH_PAYPAL_VERSION' ) ) {
			return;
		}

		$this->apply_currency                = YayCurrencyHelper::detect_current_currency();
		$this->is_dis_checkout_diff_currency = YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency );

		if ( $this->is_dis_checkout_diff_currency ) {
			// Keep original Price
			add_filter( 'yay_currency_is_original_default_currency', array( $this, 'is_original_default_currency' ), 20, 2 );
			add_filter( 'yay_currency_woocommerce_currency_symbol', array( $this, 'get_currency_symbol' ), 999999, 3 );

		}

		add_filter( 'yay_currency_woocommerce_currency', array( $this, 'custom_currency_paypal_method' ), 10, 2 );

	}

	public function get_currency_symbol( $symbol, $currency, $apply_currency ) {

		if ( doing_action( 'woocommerce_email_order_details' ) ) {
			$symbol = YayCurrencyHelper::get_symbol_by_currency_code( Helper::default_currency_code() );
		}

		return $symbol;
	}

	public function is_original_default_currency( $flag, $apply_currency ) {

		if ( doing_action( 'woocommerce_api_eh_paypal_express_payment' ) ) {
			$flag = true;
		}

		// Paypal after checkout success
		if ( isset( $_REQUEST['c'] ) && isset( $_REQUEST['express'] ) && isset( $_REQUEST['token'] ) && isset( $_REQUEST['PayerID'] ) ) {
			$flag = true;
		}

		return $flag;
	}

	public function custom_currency_paypal_method( $currency, $is_dis_checkout_diff_currency ) {

		if ( $is_dis_checkout_diff_currency ) {
			$currency = Helper::default_currency_code();
		}

		return $currency;

	}
}
