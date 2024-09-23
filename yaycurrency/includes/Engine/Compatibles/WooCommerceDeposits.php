<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// link plugin : https://woocommerce.com/products/woocommerce-deposits/

class WooCommerceDeposits {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( class_exists( '\Webtomizer\WCDP\WC_Deposits' ) ) {
			$this->apply_currency = YayCurrencyHelper::detect_current_currency();
			add_filter( 'woocommerce_deposits_fixed_deposit_amount', array( $this, 'custom_woocommerce_deposits_amount' ), 10, 2 );
			add_filter( 'wc_deposits_product_slider_args', array( $this, 'wc_deposits_product_slider_args' ), 10, 2 );
			add_filter( 'wc_deposits_cart_item_deposit_data', array( $this, 'wc_deposits_cart_item_deposit_data' ), 10, 2 );
		}
	}

	public function custom_woocommerce_deposits_amount( $amount, $product ) {
		$amount = YayCurrencyHelper::calculate_price_by_currency( $amount, true, $this->apply_currency );
		return $amount;
	}

	public function wc_deposits_cart_item_deposit_data( $deposit_meta, $cart_item ) {
		$deposit_meta['deposit']   = YayCurrencyHelper::calculate_price_by_currency( $deposit_meta['deposit'], true, $this->apply_currency );
		$deposit_meta['remaining'] = YayCurrencyHelper::calculate_price_by_currency( $deposit_meta['remaining'], true, $this->apply_currency );
		$deposit_meta['total']     = YayCurrencyHelper::calculate_price_by_currency( $deposit_meta['total'], true, $this->apply_currency );
		if ( null !== $deposit_meta['payment_schedule'] && count( $deposit_meta['payment_schedule'] ) > 0 ) {
			foreach ( $deposit_meta['payment_schedule'] as $key => $payment_schedule ) {
				if ( ! empty( $payment_schedule['amount'] ) ) {
					$deposit_meta['payment_schedule'][ $key ]['amount'] = YayCurrencyHelper::calculate_price_by_currency( $deposit_meta['payment_schedule'][ $key ]['amount'], true, $this->apply_currency );
				}
			}
		}
		return $deposit_meta;
	}

	public function wc_deposits_product_slider_args( $args, $product_id ) {
		if ( ! empty( $args['payment_plans'] ) ) {
			foreach ( $args['payment_plans'] as $key => $payment_plan ) {
				$args['payment_plans'][ $key ]['plan_total']     = YayCurrencyHelper::calculate_price_by_currency( $payment_plan['plan_total'], true, $this->apply_currency );
				$args['payment_plans'][ $key ]['deposit_amount'] = YayCurrencyHelper::calculate_price_by_currency( $payment_plan['deposit_amount'], true, $this->apply_currency );
				foreach ( $payment_plan['details']['payment-plan'] as $detail_key => $detail_payment_plan ) {
					$args['payment_plans'][ $key ]['details']['payment-plan'][ $detail_key ]['line_amount'] = YayCurrencyHelper::calculate_price_by_currency( $detail_payment_plan['line_amount'], true, $this->apply_currency );
					$args['payment_plans'][ $key ]['details']['payment-plan'][ $detail_key ]['line_tax']    = YayCurrencyHelper::calculate_price_by_currency( $detail_payment_plan['line_tax'], true, $this->apply_currency );
				}
			}
		}
		return $args;
	}
}
