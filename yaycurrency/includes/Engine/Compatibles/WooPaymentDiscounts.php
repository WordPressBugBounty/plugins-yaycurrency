<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class WooPaymentDiscounts {
	use SingletonTrait;

	private $apply_currency = array();
	private $is_dis_checkout_diff_currency;

	public function __construct() {
		if ( class_exists( '\Woo_Payment_Discounts' ) ) {
			$this->apply_currency                = YayCurrencyHelper::detect_current_currency();
			$this->is_dis_checkout_diff_currency = YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency );
			add_filter( 'yay_currency_get_fee_amount_after_calculate', array( $this, 'yay_currency_get_fee_amount_after_calculate' ), 10, 2 );
		}
	}

	public function yay_currency_get_fee_amount_after_calculate( $amount, $fee ) {
		$cart = WC()->cart;
		// Gets the settings.
		$gateways = get_option( 'woo_payment_discounts_setting' );
		$gateways = maybe_unserialize( $gateways );
		if ( isset( $gateways[ WC()->session->chosen_payment_method ] ) ) {
			$value = $gateways[ WC()->session->chosen_payment_method ]['amount'];
			$type  = $gateways[ WC()->session->chosen_payment_method ]['type'];

			if ( 'percentage' === $type && apply_filters( 'woo_payment_discounts_apply_discount', 0 < $value, $cart ) ) {
				$payment_gateways = WC()->payment_gateways->payment_gateways();
				$gateway          = $payment_gateways[ WC()->session->chosen_payment_method ];
				if ( $fee->name === $this->discount_name( $value, $gateway ) ) {
					return $fee->amount;
				}
			}
		}
		return $amount;
	}

	protected function discount_name( $value, $gateway ) {
		if ( strstr( $value, '%' ) ) {
			/* translators: %1$s: Gateway title, %2$s: Discount value */
			return sprintf( __( 'Discount for %1$s (%2$s off)', 'woo-payment-discounts' ), esc_attr( $gateway->title ), $value );
		}
		/* translators: %1$s: Gateway title, %2$s: Discount value */
		return sprintf( __( 'Discount for %s', 'woo-payment-discounts' ), esc_attr( $gateway->title ) );
	}
}
