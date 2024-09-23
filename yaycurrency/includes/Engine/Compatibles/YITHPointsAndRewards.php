<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://yithemes.com/themes/plugins/yith-woocommerce-points-and-rewards/

class YITHPointsAndRewards {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {
		if ( ! defined( 'YITH_YWPAR_VERSION' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'yay_currency_converted_coupon_price', array( $this, 'converted_coupon_price' ), 10, 4 );

		add_filter( 'ywpar_get_point_earned_price', array( $this, 'prevent_convert_points_by_price' ), 10, 3 );
		add_filter( 'ywpar_calculate_product_discount', array( $this, 'custom_price_value_of_points' ), 10, 3 );
		add_filter( 'ywpar_rewards_conversion_rate', array( $this, 'set_rewards_conversion_rate' ), 10, 1 );
		add_filter( 'ywpar_rewards_percentual_conversion_rate', array( $this, 'set_rewards_percentual_conversion_rate' ), 10, 1 );
		add_filter( 'ywpar_conversion_points_rate', array( $this, 'set_conversion_points_rate' ), 10, 1 );
		add_filter( 'ywpar_calculate_rewards_discount_max_discount', array( $this, 'custom_rewards_discount_max_discount' ), 10, 3 );
		add_filter( 'woocommerce_available_variation', array( $this, 'format_variation_price_discount_fixed_conversion' ), 11, 3 );
	}

	public function converted_coupon_price( $converted_coupon_price, $coupon, $price, $apply_currency ) {
		if ( \YITH_WC_Points_Rewards_Redemption()->check_coupon_is_ywpar( $coupon ) ) {
			// Fix for change currency after apply points
			$conversion_rate_method = \YITH_WC_Points_Rewards()->get_option( 'conversion_rate_method' );
			if ( 'percentage' === $conversion_rate_method ) {
				$percentual_conversion_rate = get_option( 'ywpar_rewards_percentual_conversion_rate' );
				$cart_total                 = WC()->cart->subtotal;
				$point                      = WC()->session->get( 'ywpar_coupon_code_points' );
				$percent                    = ( $point / reset( $percentual_conversion_rate )['points'] ) * reset( $percentual_conversion_rate )['discount'];
				$converted_coupon_price     = $cart_total * $percent / 100;
			}
		}
		return $converted_coupon_price;
	}

	public function prevent_convert_points_by_price( $price, $currency, $data ) {
		$price = $data->get_data()['price'];
		return $price;
	}

	public function custom_price_value_of_points( $discount, $product_id, $not_formatted_discount ) {
		$product_type       = wc_get_product( $product_id )->get_type();
		$converted_discount = YayCurrencyHelper::calculate_price_by_currency( $not_formatted_discount, false, $this->apply_currency );

		if ( 'variation' === $product_type || 'subscription_variation' === $product_type ) {
			return $converted_discount;
		}

		$discount = YayCurrencyHelper::format_price( $converted_discount );
		return $discount;
	}

	public function set_rewards_conversion_rate( $conversion ) {
		$rewards_conversion_rate = get_option( 'ywpar_rewards_conversion_rate' );
		$conversion              = reset( $rewards_conversion_rate );
		return $conversion;
	}

	public function set_rewards_percentual_conversion_rate( $conversion ) {
		$percentual_conversion_rate = get_option( 'ywpar_rewards_percentual_conversion_rate' );
		$conversion                 = reset( $percentual_conversion_rate );
		return $conversion;
	}

	public function set_conversion_points_rate( $conversion ) {
		$earn_points_conversion_rate = get_option( 'ywpar_earn_points_conversion_rate' );
		$conversion                  = reset( $earn_points_conversion_rate );
		return $conversion;
	}

	public function custom_rewards_discount_max_discount( $max_discount, $data, $conversion ) {
		$type = $data->get_conversion_method();
		if ( 'fixed' === $type ) {
			$converted_max_discount = YayCurrencyHelper::calculate_price_by_currency( $max_discount, false, $this->apply_currency );
			return $converted_max_discount;
		}
		return $max_discount;
	}

	public function format_variation_price_discount_fixed_conversion( $args, $product, $variation ) {

		if ( isset( $args['variation_price_discount_fixed_conversion'] ) ) {
			$args['variation_price_discount_fixed_conversion'] = YayCurrencyHelper::format_price( $args['variation_price_discount_fixed_conversion'] );
		}

		return $args;
	}
}
