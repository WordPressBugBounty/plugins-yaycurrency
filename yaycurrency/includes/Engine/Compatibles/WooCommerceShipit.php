<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: http://markup.fi

class WooCommerceShipit {

	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! defined( 'WC_SHIPIT_VERSION' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'yay_currency_get_shipping_cost', array( $this, 'yay_currency_get_shipping_cost' ), 10, 3 );

		add_filter( 'yay_currency_detect_action_args', array( $this, 'yay_currency_detect_action_args' ), 10, 1 );

	}

	protected function get_tax_display_mode() {
		if ( WC()->cart ) {
			if ( is_callable( [ WC()->cart, 'get_tax_price_display_mode' ] ) ) {
				return WC()->cart->get_tax_price_display_mode();
			}

			return WC()->cart->tax_display_cart;
		}

		return false;
	}

	public function yay_currency_get_shipping_cost( $shipping_cost, $method, $apply_currency ) {
		$shipping_method_id = $method->method_id;
		if ( 'shipit_simple' !== $shipping_method_id ) {
			return $shipping_cost;
		}

		$data = get_option( 'woocommerce_' . $shipping_method_id . '_' . $method->instance_id . '_settings' );

		$free_shipping_requires  = isset( $data['free_shipping_requires'] ) && ! empty( $data['free_shipping_requires'] ) ? $data['free_shipping_requires'] : false;
		$free_shipping_threshold = isset( $data['free_shipping_threshold'] ) && ! empty( $data['free_shipping_threshold'] ) ? $data['free_shipping_threshold'] : false;

		if ( ! $free_shipping_requires || ! $free_shipping_threshold ) {
			return $shipping_cost;
		}

		// Free shipping
		$is_free_shipping_min_amount_reached = false;
		$is_free_shipping_coupon_applied     = false;

		// Check if min amount is reached
		if ( ! empty( $free_shipping_threshold ) ) {
				$total = WC()->cart->get_displayed_subtotal();
			if ( 'incl' === $this->get_tax_display_mode() ) {
				$total = $total - ( WC()->cart->get_cart_discount_total() + WC()->cart->get_cart_discount_tax_total() );
			} else {
				$total = $total - WC()->cart->get_cart_discount_total();
			}

				$limit = floatval( str_replace( ',', '.', trim( strval( $free_shipping_threshold ) ) ) );
				$limit = YayCurrencyHelper::calculate_price_by_currency( $limit, false, $this->apply_currency );
			if ( round( $total, 2 ) >= round( $limit, 2 ) ) {
				$is_free_shipping_min_amount_reached = true;
			}
		}

		// Check if a coupon that grants a free shipping has been applied
		foreach ( WC()->cart->get_coupons() as $coupon ) {
			$is_free_shipping_coupon_applied = $coupon->get_free_shipping();
		}

		// Determine eligibility for free shipping
		switch ( $free_shipping_requires ) {
			case 'min_amount':
				if ( $is_free_shipping_min_amount_reached ) {
					return 0;
				}
				break;
			case 'coupon':
				if ( $is_free_shipping_coupon_applied ) {
					return 0;
				}
				break;
			case 'either':
				if ( $is_free_shipping_min_amount_reached || $is_free_shipping_coupon_applied ) {
					return 0;
				}
				break;
			case 'both':
				if ( $is_free_shipping_min_amount_reached && $is_free_shipping_coupon_applied ) {
					return 0;
				}
		}

		$shipping_cost_original       = isset( $data['cost'] ) ? $data['cost'] : $method->get_cost();
		$shipping_cost_original       = trim( $shipping_cost_original );
		$shipping_cost_original       = (float) ( preg_match( '/,\d{1,2}$/', $shipping_cost_original )
		? str_replace( [ '.', ',' ], [ '', '.' ], $shipping_cost_original )
		: str_replace( ',', '', $shipping_cost_original ) );
		$will_not_round_shipping_cost = apply_filters( 'yay_currency_will_not_round_shipping_cost', false );
		$shipping_cost                = YayCurrencyHelper::calculate_price_by_currency( $shipping_cost_original, $will_not_round_shipping_cost, $this->apply_currency );
		return $shipping_cost;
	}

	public function yay_currency_detect_action_args( $action_args ) {
		$ajax_args   = array( 'eeco_update_shipping_methods' );
		$action_args = array_unique( array_merge( $action_args, $ajax_args ) );
		return $action_args;
	}
}
