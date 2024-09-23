<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class PaymentPluginsBraintreeForWooCommerce {

	use SingletonTrait;

	private $apply_currency = array();
	private $is_dis_checkout_diff_currency;

	public function __construct() {

		if ( ! function_exists( 'wc_braintree_get_currency' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'woocommerce_currency', array( $this, 'rechange_woocommerce_currency' ), 9999, 1 );

	}

	public function rechange_woocommerce_currency( $currency ) {

		if ( ! $this->apply_currency || ! isset( $this->apply_currency['currency'] ) || is_admin() || YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $currency;
		}

		if ( is_checkout() ) {
			$currency = $this->apply_currency['currency'];
		}

		return $currency;
	}
}
