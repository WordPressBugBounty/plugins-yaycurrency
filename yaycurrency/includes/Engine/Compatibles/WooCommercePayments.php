<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use WCPay\MultiCurrency\MultiCurrency;
defined( 'ABSPATH' ) || exit;

// Link plugin: https://woocommerce.com/products/woocommerce-payments/

class WooCommercePayments {

	use SingletonTrait;

	private $apply_currency   = array();
	private $default_currency = '';

	public function __construct() {
		if ( ! class_exists( 'WCPay\MultiCurrency\MultiCurrency' ) ) {
			return;
		}

		$this->apply_currency   = YayCurrencyHelper::detect_current_currency();
		$this->default_currency = Helper::default_currency_code();

		add_filter( 'yay_currency_woocommerce_currency', array( $this, 'paypal_payments_get_currency' ), 20, 2 );

		add_filter( MultiCurrency::FILTER_PREFIX . 'override_selected_currency', array( $this, 'override_selected_currency' ), 50 );

	}

	public function paypal_payments_get_currency( $currency, $is_dis_checkout_diff_currency ) {

		if ( $is_dis_checkout_diff_currency || YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $this->default_currency; // YayCurrency Lite fallback currency is default store currency.
		}

		return $currency;

	}

	public function override_selected_currency() {
		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $this->default_currency;
		}
		$currency_code = isset( $this->apply_currency['currency'] ) ? $this->apply_currency['currency'] : $this->default_currency;
		return $currency_code;
	}
}
