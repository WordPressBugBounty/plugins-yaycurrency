<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://woocommerce.com/products/woocommerce-points-and-rewards/

class WooCommercePointsAndRewards {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! defined( 'WC_POINTS_REWARDS_VERSION' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'wc_points_rewards_user_points_balance', array( $this, 'recalculate_user_points_balance' ), 10, 2 );
		add_action( 'woocommerce_removed_coupon', array( $this, 'coupon_removed' ), 50 );
		// handle the apply discount AJAX submit on the cart page
		add_action( 'wp', array( $this, 'detect_currency_apply_discount_cart' ), 20 );
		add_action( 'init', array( $this, 'detect_currency_apply_discount_checkout' ), 20 );
		add_filter( 'yay_currency_converted_coupon_price', array( $this, 'converted_coupon_price' ), 10, 4 );
		// Fallback
		add_filter( 'yay_currency_get_amount_coupon_price_fallback_currency', array( $this, 'get_amount_coupon_price_fallback_currency' ), 10, 3 );

		add_filter( 'wc_points_rewards_my_points_events', array( $this, 'points_rewards_my_points_events' ), 20, 2 );
	}

	public function recalculate_user_points_balance( $points_balance, $user_id ) {

		global $wpdb;

		$recalculate_points_balance = 0;

		$points = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wc_points_rewards_user_points WHERE user_id = %d AND points_balance != 0", $user_id )
		);

		foreach ( $points as $point ) {

			if ( ! isset( $point->order_id ) ) {
				continue;
			}

			$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $point->order_id );

			if ( ! $order_currency ) {
				continue;
			}

			$original_balance            = isset( $point->points_balance ) ? floatval( $point->points_balance ) / YayCurrencyHelper::get_rate_fee( $order_currency ) : 0;
			$recalculate_points_balance += $original_balance;
		}
		$points_balance = $recalculate_points_balance ? $recalculate_points_balance : $points_balance;
		$points_balance = YayCurrencyHelper::calculate_price_by_currency( $points_balance, false, $this->apply_currency );

		return $points_balance;

	}

	public function coupon_removed( $coupon_code ) {
		$existing_discount = class_exists( 'WC_Points_Rewards_Discount' ) ? \WC_Points_Rewards_Discount::get_discount_code() : '';
		if ( ! empty( $existing_discount ) && $existing_discount === $coupon_code ) {
			WC()->session->set( 'yay_currency_wc_points_rewards_currency_code', null );
			WC()->session->set( 'yay_currency_wc_points_rewards_apply_discount', null );
		}

	}

	// RECALCULATE DISCOUNT ON CART

	public function detect_currency_apply_discount_cart() {
		// only apply on cart and from apply discount action
		if ( isset( $_REQUEST['wc_points_rewards_apply_discount'] ) && isset( $this->apply_currency['currency'] ) ) {
			$discount_code   = class_exists( 'WC_Points_Rewards_Discount' ) ? \WC_Points_Rewards_Discount::get_discount_code() : '';
			$discount_amount = class_exists( 'WC_Points_Rewards_Cart_Checkout' ) && ! empty( $discount_code ) ? \WC_Points_Rewards_Cart_Checkout::get_discount_for_redeeming_points( true, null, false, $discount_code ) : '';
			if ( ! empty( $discount_amount ) ) {
				WC()->session->set( 'yay_currency_wc_points_rewards_apply_discount', $discount_amount );
			}

			WC()->session->set( 'yay_currency_wc_points_rewards_currency_code', $this->apply_currency['currency'] );

		}

	}

	// RECALCULATE DISCOUNT ON CHECKOUT
	public static function get_discount_for_redeeming_points( $discount_amount ) {
		if ( ! class_exists( 'WC_Points_Rewards_Manager' ) ) {
			return false;
		}
		// get the value of the user's point balance
		$available_user_discount = \WC_Points_Rewards_Manager::get_users_points_value( get_current_user_id() );

		// no discount
		if ( $available_user_discount <= 0 ) {
			return false;
		}

		if ( $discount_amount ) {
			$requested_user_discount = \WC_Points_Rewards_Manager::calculate_points_value( $discount_amount );
			if ( $requested_user_discount > 0 && $requested_user_discount < $available_user_discount ) {
				$available_user_discount = $requested_user_discount;
			}
		}
		return $available_user_discount;
	}

	public function detect_currency_apply_discount_checkout() {

		if ( isset( $_REQUEST['action'] ) && 'wc_points_rewards_apply_discount' === $_REQUEST['action'] && isset( $this->apply_currency['currency'] ) ) {
			check_ajax_referer( 'apply-coupon', 'security' );
			$discount_amount = ! empty( $_POST['discount_amount'] ) ? intval( sanitize_text_field( $_POST['discount_amount'] ) ) : '';
			$discount_amount = self::get_discount_for_redeeming_points( $discount_amount );
			if ( $discount_amount ) {
				WC()->session->set( 'yay_currency_wc_points_rewards_apply_discount', $discount_amount );
				WC()->session->set( 'yay_currency_wc_points_rewards_currency_code', $this->apply_currency['currency'] );
			}
		}
	}

	public function convert_amount_coupon_price_to_default( $coupon_price, $coupon ) {
		$coupon_data = $coupon->get_data();
		if ( empty( $coupon_data ) ) {
			return false;
		}
		$coupon_code       = isset( $coupon_data['code'] ) ? $coupon_data['code'] : '';
		$existing_discount = class_exists( 'WC_Points_Rewards_Discount' ) ? \WC_Points_Rewards_Discount::get_discount_code() : '';
		if ( empty( $existing_discount ) || ! $existing_discount || $existing_discount !== $coupon_code ) {
			return false;
		}
		// convert to default currency
		$currency_code_apply     = WC()->session->get( 'yay_currency_wc_points_rewards_currency_code' );
		$currency_code_apply     = ! empty( $currency_code_apply ) ? $currency_code_apply : Helper::default_currency_code();
		$discount_apply_currency = YayCurrencyHelper::get_currency_by_currency_code( $currency_code_apply );

		if ( ! $discount_apply_currency ) {
			return false;
		}
		$rate_fee        = YayCurrencyHelper::get_rate_fee( $discount_apply_currency );
		$discount_amount = WC()->session->get( 'yay_currency_wc_points_rewards_apply_discount' );
		if ( empty( $discount_amount ) || ! $discount_amount ) {
			return false;
		}
		$coupon_price = floatval( $discount_amount / $rate_fee ); // convert to default currency
		return $coupon_price;
	}

	public function converted_coupon_price( $converted_coupon_price, $coupon, $price, $apply_currency ) {
		$coupon_price = $this->convert_amount_coupon_price_to_default( $converted_coupon_price, $coupon );
		if ( ! $coupon_price ) {
			return $converted_coupon_price;
		}
		$converted_coupon_price = YayCurrencyHelper::calculate_price_by_currency( $coupon_price, true, $this->apply_currency );
		return $converted_coupon_price;
	}

	public function get_amount_coupon_price_fallback_currency( $price, $coupon, $currencies_data ) {

		$coupon_price = $this->convert_amount_coupon_price_to_default( $price, $coupon );

		if ( ! $coupon_price ) {
			return $price;
		}

		return $coupon_price;

	}

	public function points_rewards_my_points_events( $events, $user_id ) {
		if ( $events ) {
			foreach ( $events as $key => $event ) {
				$order_currency = isset( $event->order_id ) ? YayCurrencyHelper::get_order_currency_by_order_id( $event->order_id ) : false;
				$points         = isset( $event->points ) ? floatval( $event->points ) : 0;
				if ( $order_currency ) {
					$rate_fee = YayCurrencyHelper::get_rate_fee( $order_currency );
					$points   = $rate_fee ? floatval( $points / $rate_fee ) : $points;
				}
				$converted_points = YayCurrencyHelper::calculate_price_by_currency( $points, true, $this->apply_currency );

				$events[ $key ]->points = class_exists( 'WC_Points_Rewards_Manager' ) ? \WC_Points_Rewards_Manager::round_the_points( $converted_points ) : $converted_points;
			}
		}
		return $events;
	}
}
