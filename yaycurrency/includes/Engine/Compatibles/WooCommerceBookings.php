<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

class WooCommerceBookings {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( class_exists( 'WC_Bookings' ) ) {
			$this->apply_currency = YayCurrencyHelper::detect_current_currency();

			add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data_booking_cost' ), 40 );

			// add_filter( 'woocommerce_product_get_display_cost', array( $this, 'woocommerce_product_get_display_cost' ), 10, 2 );
			add_filter( 'woocommerce_bookings_calculated_booking_cost', array( $this, 'woocommerce_bookings_calculated_booking_cost' ), 10, 3 );
			add_filter( 'woocommerce_currency_symbol', array( $this, 'yay_currency_woocommerce_currency_symbol' ), 10, 2 );
			add_filter( 'woocommerce_get_price_html', array( $this, 'woocommerce_get_price_html' ), 20, 2 );
		}
	}

	public function product_addons_set_cart_contents( $cart_contents, $cart_item_key, $cart_item, $apply_currency ) {
		if ( isset( $cart_item['booking'] ) && ! empty( $cart_item['booking'] ) ) {
			$currency_code        = isset( $cart_item['yay_currency_code'] ) ? $cart_item['yay_currency_code'] : Helper::default_currency_code();
			$apply_currency_added = YayCurrencyHelper::get_currency_by_currency_code( $currency_code );
			$booking_cost         = isset( $cart_item['booking']['_cost'] ) ? $cart_item['booking']['_cost'] : 0;
			$default_booking_cost = $booking_cost / YayCurrencyHelper::get_rate_fee( $apply_currency_added );
			$option_price         = SupportHelper::get_price_options_by_3rd_plugin( $cart_item['data'] );
			$option_price_default = SupportHelper::get_price_options_default_by_3rd_plugin( $cart_item['data'] );

			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_currency_booking_cost_default', $default_booking_cost + $option_price_default );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_currency_booking_cost_by_currency', YayCurrencyHelper::calculate_price_by_currency( $default_booking_cost, false, $apply_currency ) + $option_price );
		}
	}

	public function add_cart_item_data_booking_cost( $cart_item_data ) {
		if ( isset( $cart_item_data['booking'] ) ) {
			$cart_item_data['yay_currency_code'] = $this->apply_currency['currency'];
		}

		return $cart_item_data;
	}

	public function woocommerce_product_get_display_cost( $price, $product ) {
		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		return $price;
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

	public function woocommerce_get_price_html( $price_html, $product ) {
		if ( $product && is_a( $product, 'WC_Product_Booking' ) && Helper::default_currency_code() !== $this->apply_currency['currency'] ) {
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
			$display_price              = YayCurrencyHelper::calculate_price_by_currency( $display_price, false, $this->apply_currency );
			$display_price_suffix_comp  = wc_price( apply_filters( 'woocommerce_product_get_price', $display_price, $product ) ) . $product->get_price_suffix();
			$original_price_suffix_comp = wc_price( $display_price ) . $product->get_price_suffix();

			$original_price_suffix = wc_price( $display_price ) . $product->get_price_suffix();
			$display_price         = apply_filters( 'woocommerce_product_get_price', $display_price, $product );
			$display_price_suffix  = wc_price( $display_price ) . $product->get_price_suffix();

			if ( $original_price_suffix_comp !== $display_price_suffix_comp ) {
				$price_html = "<del>{$original_price_suffix}</del><ins>{$display_price_suffix}</ins>";
			} elseif ( $display_price ) {
				if ( $product->has_additional_costs() || $product->get_display_cost() ) {
					$price_html = __( 'From: ', 'woocommerce-bookings' ) . wc_price( $display_price ) . $product->get_price_suffix();
				} else {
					$price_html = wc_price( $display_price ) . $product->get_price_suffix();
				}
			} elseif ( ! $product->has_additional_costs() ) {
				$price_html = __( 'Free', 'woocommerce-bookings' );
			} else {
				$price_html = '';
			}
		}

		return $price_html;
	}
}
