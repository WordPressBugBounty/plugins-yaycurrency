<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;

class WooCommerceBookings {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! class_exists( 'WC_Bookings' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		// product price hooks
		add_filter( 'yay_currency_is_original_product_price', array( $this, 'is_original_product_price' ), 10, 3 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'woocommerce_get_price_html' ), 100, 2 );
		add_filter( 'yay_currency_caching_price_html', array( $this, 'yay_currency_caching_price_html' ), 100, 4 );

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'woocommerce_add_cart_item_data' ), PHP_INT_MAX, 3 );

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

		// Booking options
		add_filter( 'woocommerce_bookings_resource_additional_cost_string', array( $this, 'woocommerce_bookings_resource_additional_cost_string' ), 10, 2 );

		add_filter( 'woocommerce_bookings_calculated_booking_cost', array( $this, 'woocommerce_bookings_calculated_booking_cost' ), 10, 3 );
		add_filter( 'woocommerce_currency_symbol', array( $this, 'yay_currency_woocommerce_currency_symbol' ), 10, 2 );

		// Define filter get price default (when disable Checkout in different currency option)

		add_filter( 'yay_currency_get_product_price_default_by_cart_item', array( $this, 'get_product_price_default_by_cart_item' ), 10, 2 );
		add_filter( 'yay_currency_get_product_price_by_cart_item', array( $this, 'get_product_price_by_cart_item' ), 10, 3 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 20, 2 );

		// Checkout & Order

		add_filter( 'yay_currency_is_original_default_currency', array( $this, 'is_original_default_currency' ), 10, 3 );
		add_filter( 'yay_currency_woocommerce_currency', array( $this, 'convert_to_default_currency' ), 20, 2 );
		add_filter( 'yay_currency_use_default_default_currency_symbol', array( $this, 'use_default_default_currency_symbol' ), 20, 3 );
		add_filter( 'yay_currency_3rd_plugins_conditions', array( $this, 'detect_3rd_plugins_conditions' ), 20, 3 );
		add_filter( 'yay_currency_get_price_default_in_checkout_page', array( $this, 'get_price_fallback_in_checkout_page' ), 10, 3 );

	}

	public function is_original_product_price( $flag, $price, $product ) {
		if ( class_exists( 'WC_Bookings' ) && 'booking' === $product->get_type() && ( doing_filter( 'woocommerce_get_price_html' ) || doing_filter( 'yay_currency_caching_price_html' ) ) ) {
			$flag = true;
		}
		return $flag;
	}

	protected function get_booking_product_price_html( $price_html, $product, $apply_currency ) {
		if ( ! class_exists( 'WC_Bookings_Cost_Calculation' ) || Helper::default_currency_code() === $apply_currency['currency'] ) {
			return $price_html;
		}

		if ( 'booking' !== $product->get_type() ) {
			return $price_html;
		}

		$base_price = \WC_Bookings_Cost_Calculation::calculated_base_cost( $product );

		if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
			if ( function_exists( 'wc_get_price_excluding_tax' ) ) {
				$display_price = wc_get_price_including_tax(
					$product,
					array(
						'qty'   => 1,
						'price' => $base_price,
					)
				);
			} else {
				$display_price = $product->get_price_including_tax( 1, $base_price );
			}
		} elseif ( function_exists( 'wc_get_price_excluding_tax' ) ) {
				$display_price = wc_get_price_excluding_tax(
					$product,
					array(
						'qty'   => 1,
						'price' => $base_price,
					)
				);
		} else {
			$display_price = $product->get_price_excluding_tax( 1, $base_price );
		}

		$display_price_suffix  = YayCurrencyHelper::format_price( apply_filters( 'woocommerce_product_get_price', $display_price, $product ) ) . $product->get_price_suffix();
		$original_price_suffix = YayCurrencyHelper::format_price( $display_price ) . $product->get_price_suffix();
		$display_price         = YayCurrencyHelper::calculate_price_by_currency( $display_price, false, $this->apply_currency );
		if ( $original_price_suffix !== $display_price_suffix ) {
			$price_html = "<del>{$original_price_suffix}</del><ins>{$display_price_suffix}</ins>";
		} elseif ( $display_price ) {
			if ( $product->has_additional_costs() ) {
				/* translators: %s: method */
				$price_html = sprintf( __( 'From: %s', 'woocommerce-bookings' ), YayCurrencyHelper::format_price( $display_price ) ) . $product->get_price_suffix();
			} else {
				$price_html = YayCurrencyHelper::format_price( $display_price ) . $product->get_price_suffix();
			}
		} elseif ( ! $product->has_additional_costs() ) {
			$price_html = __( 'Free', 'woocommerce-bookings' );
		} else {
			$price_html = '';
		}

		return $price_html;
	}

	public function woocommerce_get_price_html( $price_html, $product ) {
		$price_html = self::get_booking_product_price_html( $price_html, $product, $this->apply_currency );
		return $price_html;
	}

	public function yay_currency_caching_price_html( $price_html, $product_price, $product, $apply_currency ) {
		$price_html = self::get_booking_product_price_html( $price_html, $product, $apply_currency );
		return $price_html;
	}

	public function woocommerce_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( isset( $cart_item_data['booking'] ) && ! empty( $cart_item_data['booking'] ) ) {
			$cart_item_data['apply_currency_added'] = $this->apply_currency;
		}
		return $cart_item_data;
	}

	public function product_addons_set_cart_contents( $cart_contents, $cart_item_key, $cart_item, $apply_currency ) {
		$cart_item_booking              = isset( $cart_item['booking'] ) && ! empty( $cart_item['booking'] ) ? $cart_item['booking'] : false;
		$cart_item_apply_currency_added = isset( $cart_item['apply_currency_added'] ) && ! empty( $cart_item['apply_currency_added'] ) ? $cart_item['apply_currency_added'] : false;
		$booking_cost                   = isset( $cart_item['booking']['_cost'] ) && ! empty( $cart_item['booking']['_cost'] ) ? $cart_item['booking']['_cost'] : false;
		if ( $cart_item_booking && $cart_item_apply_currency_added && $booking_cost ) {

			$rate_fee = YayCurrencyHelper::get_rate_fee( $cart_item_apply_currency_added );

			$default_booking_cost = $booking_cost / $rate_fee;
			$option_price         = SupportHelper::get_price_options_by_3rd_plugin( $cart_item['data'] );
			$option_price_default = SupportHelper::get_price_options_default_by_3rd_plugin( $cart_item['data'] );

			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_currency_booking_cost_default', $default_booking_cost + $option_price_default );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_currency_booking_cost_by_currency', YayCurrencyHelper::calculate_price_by_currency( $default_booking_cost, false, $apply_currency ) + $option_price );

		}
	}

	public function detect_checkout_default_via_rest_api( $apply_currency ) {

		$is_dis_checkout_diff_currency = YayCurrencyHelper::is_dis_checkout_diff_currency( $apply_currency );

		$rest_route = self::get_rest_route_via_rest_api();

		if ( ! $is_dis_checkout_diff_currency || ! $rest_route ) {
			return false;
		}

		if ( '/wc/store/v1/checkout' === $rest_route && isset( $_REQUEST['_locale'] ) ) {
			return true;
		}

		return false;

	}

	public static function get_rest_route_via_rest_api() {
		$query_vars = isset( $GLOBALS['wp']->query_vars ) && ! empty( $GLOBALS['wp']->query_vars ) ? $GLOBALS['wp']->query_vars : false;

		if ( ! $query_vars || ! isset( $query_vars['rest_route'] ) ) {
			return false;
		}

		$rest_route = ! empty( $query_vars['rest_route'] ) ? $query_vars['rest_route'] : false;

		if ( ! $rest_route ) {
			return false;
		}

		return $rest_route;
	}

	public function detect_cart_checkout_by_currency_via_rest_api() {
		$rest_route = self::get_rest_route_via_rest_api();

		if ( ! $rest_route ) {
			return false;
		}

		$rest_route_args = array( '/wc/store/v1/checkout', '/wc/store/v1/batch' );

		if ( in_array( $rest_route, $rest_route_args, true ) && isset( $_REQUEST['_locale'] ) ) {
			return true;
		}

		return false;

	}

	public function is_original_default_currency( $flag, $apply_currency ) {

		if ( self::detect_checkout_default_via_rest_api( $apply_currency ) ) {
			$flag = true;
		}

		return $flag;

	}

	public function convert_to_default_currency( $currency, $is_dis_checkout_diff_currency ) {

		if ( $is_dis_checkout_diff_currency && self::detect_checkout_default_via_rest_api( $this->apply_currency ) ) {
			$currency = Helper::default_currency_code();
		}

		return $currency;
	}

	public function use_default_default_currency_symbol( $flag, $is_dis_checkout_diff_currency, $apply_currency ) {

		if ( $is_dis_checkout_diff_currency && self::detect_checkout_default_via_rest_api( $apply_currency ) ) {
			$flag = true;
		}

		return $flag;
	}

	public function detect_3rd_plugins_conditions( $flag, $product, $apply_currency ) {

		if ( self::detect_cart_checkout_by_currency_via_rest_api() ) {
			$flag = true;
		}

		return $flag;

	}

	public function woocommerce_bookings_resource_additional_cost_string( $additional_cost_string, $booking_resource ) {
		if ( ! is_product() || ! is_singular( 'product' ) ) {
			return $additional_cost_string;
		}

		global $product;

		if ( 'booking' !== $product->get_type() ) {
			return $additional_cost_string;
		}

		$cost_plus_base  = $booking_resource->get_base_cost() + $product->get_block_cost() + $product->get_cost();
		$additional_cost = array();

		if ( $booking_resource->get_base_cost() && $product->get_block_cost() < $cost_plus_base ) {
			if ( '' !== $product->get_display_cost() ) {
				$cost_plus_base    = YayCurrencyHelper::calculate_price_by_currency( $cost_plus_base, false, $this->apply_currency );
				$additional_cost[] = '+' . wp_strip_all_tags( wc_price( $cost_plus_base ) );
			} else {
				$resource_base_cost = YayCurrencyHelper::calculate_price_by_currency( (float) $booking_resource->get_base_cost(), false, $this->apply_currency );
				$additional_cost[]  = '+' . wp_strip_all_tags( wc_price( $resource_base_cost ) );
			}
		}

		if ( $booking_resource->get_block_cost() && ! $product->get_display_cost() ) {
			$duration      = $product->get_duration();
			$duration_unit = $product->get_duration_unit();
			if ( 'minute' === $duration_unit ) {
				$duration_unit = _n( 'minute', 'minutes', $duration, 'woocommerce-bookings' );
			} elseif ( 'hour' === $duration_unit ) {
				$duration_unit = _n( 'hour', 'hours', $duration, 'woocommerce-bookings' );
			} elseif ( 'day' === $duration_unit ) {
				$duration_unit = _n( 'day', 'days', $duration, 'woocommerce-bookings' );
			} elseif ( 'month' === $duration_unit ) {
				$duration_unit = _n( 'month', 'months', $duration, 'woocommerce-bookings' );
			} else {
				$duration_unit = _n( 'block', 'blocks', $duration, 'woocommerce-bookings' );
			}

			// Check for singular display.
			if ( 1 === $duration ) {
				$duration_display = sprintf( '%s', $duration_unit );
			} else {
				// Plural.
				$duration_display = sprintf( '%d %s', $duration, $duration_unit );
			}
			$resource_block_cost = YayCurrencyHelper::calculate_price_by_currency( $booking_resource->get_block_cost(), false, $this->apply_currency );
			$duration_display    = apply_filters( 'woocommerce_bookings_resource_duration_display_string', $duration_display, $product );
			/* translators: %1$1s: booking cost, %2$2s: booking duration display */
			$additional_cost[] = sprintf( __( '+%1$1s per %2$2s', 'woocommerce-bookings' ), wp_strip_all_tags( wc_price( $resource_block_cost ) ), $duration_display );
		}

		if ( $additional_cost ) {
			$additional_cost_string = ' (' . implode( ', ', $additional_cost ) . ')';
		} else {
			$additional_cost_string = '';
		}

		return $additional_cost_string;
	}
	public function woocommerce_bookings_calculated_booking_cost( $booking_cost, $product, $data ) {
		$booking_cost = YayCurrencyHelper::calculate_price_by_currency( $booking_cost, false, $this->apply_currency );
		return $booking_cost;
	}

	public function yay_currency_woocommerce_currency_symbol( $currency_symbol, $apply_currency ) {
		if ( wp_doing_ajax() ) {
			if ( isset( $_REQUEST['action'] ) && 'wc_bookings_calculate_costs' === $_REQUEST['action'] ) {
				$currency_symbol = wp_kses_post( html_entity_decode( $this->apply_currency['symbol'] ) );
			}
		}
		return $currency_symbol;
	}

	public function get_price_default_in_checkout_page( $price, $product ) {
		$booking_cost_default = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_booking_cost_default' );
		if ( $booking_cost_default ) {
			return $booking_cost_default;
		}
		return $price;

	}

	public function get_product_price_default_by_cart_item( $price, $cart_item ) {
		$booking_cost_default = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_currency_booking_cost_default' );
		if ( $booking_cost_default ) {
			return $booking_cost_default;
		}
		return $price;
	}

	public function get_product_price_by_cart_item( $price, $cart_item, $apply_currency ) {
		$booking_cost_price = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_currency_booking_cost_by_currency' );
		if ( $booking_cost_price ) {
			return $booking_cost_price;
		}
		return $price;
	}

	public function get_price_with_options( $price, $product ) {
		$booking_cost = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_booking_cost_by_currency' );
		if ( $booking_cost ) {
			return $booking_cost;
		}
		return $price;
	}

	public function get_price_fallback_in_checkout_page( $product_price, $product, $fallback_currency ) {
		$booking_cost_default = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_booking_cost_default' );
		if ( $booking_cost_default ) {
			return $booking_cost_default;
		}
		return $product_price;
	}
}
