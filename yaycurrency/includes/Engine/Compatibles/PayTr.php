<?php

namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://wordpress.org/plugins/paytr-sanal-pos-woocommerce-iframe-api/
class PayTr {
	use SingletonTrait;

	private $apply_currency = null;

	public function __construct() {
		if ( ! class_exists( 'PaytrCoreClass' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();
		add_filter( 'woocommerce_order_get_total', array( $this, 'order_get_total' ), 10, 2 );
	}
	public function order_get_total( $total, $order ) {
		if ( doing_action( 'woocommerce_receipt_paytr_payment_gateway_eft' ) ) {
			if ( 'TRY' !== $this->apply_currency['currency'] ) {
				$total          = YayCurrencyHelper::reverse_calculate_price_by_currency( $total, $this->apply_currency );
				$apply_currency = YayCurrencyHelper::get_currency_by_currency_code( 'TRY' );
				$total          = YayCurrencyHelper::round_price_by_currency( $total, $apply_currency );
			}
		}

		return $total;
	}
}
