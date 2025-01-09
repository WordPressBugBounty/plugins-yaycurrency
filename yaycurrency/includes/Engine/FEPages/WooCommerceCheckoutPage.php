<?php

namespace Yay_Currency\Engine\FEPages;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;
class WooCommerceCheckoutPage {
	use SingletonTrait;

	private $fee_cost = 0;

	public function __construct() {

		add_filter( 'yay_currency_checkout_converted_cart_subtotal', array( $this, 'checkout_converted_cart_subtotal' ), 10, 2 );
		add_filter( 'yay_currency_checkout_converted_discount_price', array( $this, 'checkout_converted_discount_price' ), 10, 3 );
		add_filter( 'yay_currency_checkout_converted_tax_amount', array( $this, 'checkout_converted_tax_amount' ), 10, 3 );
		add_filter( 'yay_currency_checkout_converted_cart_total', array( $this, 'checkout_converted_cart_total' ), 10, 3 );

		add_filter( 'yay_currency_checkout_converted_shipping_method_full_label', array( $this, 'checkout_converted_shipping_method_full_label' ), 10, 5 );
		add_filter( 'yay_currency_checkout_converted_cart_coupon_totals_html', array( $this, 'checkout_converted_cart_coupon_totals_html' ), 10, 4 );

		// Convert to Default
		add_filter( 'yay_currency_get_cart_total_default', array( $this, 'get_cart_total_default' ), 10, 2 );
		add_filter( 'yay_currency_get_discount_total_default', array( $this, 'get_discount_total_default' ), 10, 2 );
		add_filter( 'yay_currency_get_shipping_total_default', array( $this, 'get_shipping_total_default' ), 10, 2 );
		add_filter( 'yay_currency_get_fee_total_default', array( $this, 'get_fee_total_default' ), 10, 2 );
		add_filter( 'yay_currency_get_total_tax_default', array( $this, 'get_total_tax_default' ), 10, 2 );

		// Blocks
		add_filter( 'yay_currency_is_checkout_blocks', array( $this, 'yay_currency_is_checkout_blocks' ), 10, 2 );
	}

	public function checkout_converted_cart_subtotal( $converted_subtotal, $apply_currency ) {
		$cart_subtotal      = apply_filters( 'yay_currency_get_cart_subtotal', 0, $apply_currency );
		$converted_subtotal = YayCurrencyHelper::format_price( $cart_subtotal );
		return $converted_subtotal;
	}

	public function checkout_converted_discount_price( $formatted_discount_price, $coupon, $apply_currency ) {
		$discount_type   = $coupon->get_discount_type();
		$discount_amount = (float) $coupon->get_amount();
		$cart_subtotal   = apply_filters( 'yay_currency_get_cart_subtotal', 0, $apply_currency );
		if ( 'percent' !== $discount_type ) {
			if ( 'fixed_product' === $discount_type ) {
				$discount_totals          = WC()->cart->get_coupon_discount_totals();
				$discount_amount          = $discount_totals[ $coupon->get_code() ];
				$formatted_discount_price = YayCurrencyHelper::format_price( $discount_amount );
			}
		} else {
			$discount_price           = ( $cart_subtotal * $discount_amount ) / 100;
			$formatted_discount_price = YayCurrencyHelper::format_price( $discount_price );
		}
		return $formatted_discount_price;
	}

	public function checkout_converted_tax_amount( $formatted_converted_tax_amount, $tax_info, $apply_currency ) {
		$tax_rate_id    = $tax_info->tax_rate_id;
		$tax_rate       = \WC_Tax::_get_tax_rate( $tax_rate_id );
		$tax_class      = isset( $tax_rate['tax_rate_class'] ) && ! empty( $tax_rate['tax_rate_class'] ) ? $tax_rate['tax_rate_class'] : 'standard-rate';
		$shipping_total = $this->get_shipping_total_selected( $apply_currency );
		$taxes_in_cart  = $this->get_info_taxes_include_in_cart( $apply_currency, $shipping_total );
		$total_tax      = isset( $taxes_in_cart['taxes'][ $tax_class ] ) ? $taxes_in_cart['taxes'][ $tax_class ]['subtotal'] : 0;
		if ( $total_tax ) {
			$formatted_converted_tax_amount = YayCurrencyHelper::format_price( $total_tax );
		}

		return $formatted_converted_tax_amount;
	}

	public function checkout_converted_cart_total( $converted_total, $total_price, $apply_currency ) {
		$cart_subtotal = apply_filters( 'yay_currency_get_cart_subtotal', 0, $apply_currency );

		$shipping_total  = $this->get_shipping_total_selected( $apply_currency );
		$taxes_in_cart   = $this->get_info_taxes_include_in_cart( $apply_currency, $shipping_total );
		$total_tax_fees  = $this->get_total_fees( $apply_currency, true );
		$cart_total      = ( $cart_subtotal + $shipping_total + $total_tax_fees + $taxes_in_cart['total_tax'] ) - $taxes_in_cart['total_coupon'];
		$converted_total = YayCurrencyHelper::format_price( $cart_total );
		return $converted_total;
	}

	// CALCULATE TAX IN CART

	public function get_total_fees( $apply_currency, $calculate_include_total = false, $calculate_default = false ) {
		$total_fees = 0;
		foreach ( WC()->cart->get_fees() as $fee ) {
			if ( $fee->taxable || $calculate_include_total ) {
				$total_fees += ! $calculate_default ? YayCurrencyHelper::calculate_price_by_currency( $fee->amount, true, $apply_currency ) : (float) $fee->amount / YayCurrencyHelper::get_rate_fee( $apply_currency );
			}
		}
		return $total_fees;
	}

	public function get_total_coupon_default( $cart_subtotal = 0 ) {
		$total_coupon_applies = 0;
		if ( WC()->cart->applied_coupons ) {
			$applied_coupons = WC()->cart->applied_coupons;
			foreach ( $applied_coupons  as $coupon_code ) {
				$coupon          = new \WC_Coupon( $coupon_code );
				$discount_type   = $coupon->get_discount_type();
				$coupon_data     = $coupon->get_data();
				$discount_amount = (float) $coupon_data['amount'];

				if ( 'percent' !== $discount_type ) {
					if ( 'fixed_product' === $discount_type ) {
						$discount_totals = WC()->cart->get_coupon_discount_totals();
						$discount_amount = $discount_totals[ $coupon_code ];
					}
					$total_coupon_applies += $discount_amount;
				} else {
					$total_coupon_applies += ( $cart_subtotal * $discount_amount ) / 100;
				}
			}
		}
		return $total_coupon_applies;
	}

	public function get_shipping_total_selected( $apply_currency, $calculate_default = false, $calculate_tax = false ) {
		$shipping_total = WC()->cart->shipping_total;
		if ( $calculate_default ) {
			$shipping_total = $shipping_total / YayCurrencyHelper::get_rate_fee( $apply_currency );
		} else {
			$shipping_total = YayCurrencyHelper::calculate_price_by_currency( $shipping_total, true, $apply_currency );
		}

		$shipping_flat_fee_total = SupportHelper::get_shipping_flat_rate_fee_total_selected( $apply_currency, $calculate_default, $calculate_tax );

		if ( $shipping_flat_fee_total ) {
			$shipping_total = -1 === $shipping_flat_fee_total ? 0 : $shipping_flat_fee_total;
		}

		return $shipping_total;

	}

	public function get_tax_details_by_product( $_product ) {
		$tax_rates   = \WC_Tax::get_rates( $_product->get_tax_class() );
		$rateId      = key( $tax_rates );
		$tax_rate    = \WC_Tax::_get_tax_rate( $rateId );
		$tax_class   = isset( $tax_rate['tax_rate_class'] ) && ! empty( $tax_rate['tax_rate_class'] ) ? $tax_rate['tax_rate_class'] : false;
		$tax_details = array(
			'tax_status'   => $_product->get_tax_status(),
			'tax_class'    => $tax_class ? $tax_class : 'standard-rate',
			'tax_rate_id'  => $rateId,
			'tax_value'    => \WC_Tax::get_rate_percent_value( $rateId ),
			'tax_rate'     => $tax_rate,
			'tax_code'     => \WC_Tax::get_rate_code( $rateId ),
			'tax_compound' => \WC_Tax::is_compound( $rateId ),
		);
		return $tax_details;
	}

	public function get_taxes_in_cart( $apply_currency, $cart_contents, $calculate_default = false ) {
		$taxes    = array();
		$standard = false;
		foreach ( $cart_contents  as $key => $cart_item ) {
			$product_id  = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
			$_product    = wc_get_product( $product_id );
			$tax_details = $this->get_tax_details_by_product( $_product );
			$tax_class   = $tax_details['tax_class'];
			$products    = array();
			if ( 'standard-rate' === $tax_class ) {
				$standard = true;
			}
			$quantity = $cart_item['quantity'];
			$subtotal = 0;
			switch ( $tax_details['tax_status'] ) {
				case 'taxable':
					$product_price = ! $calculate_default ? SupportHelper::calculate_product_price_by_cart_item( $cart_item, $apply_currency ) : SupportHelper::get_product_price_default_by_cart_item( $cart_item );
					$subtotal      = $subtotal + $product_price * $quantity;
					break;
				default:
					break;
			}
			if ( isset( $taxes[ $tax_class ] ) ) {
				$merge_subtotal                = $taxes[ $tax_class ]['subtotal'] + $subtotal;
				$quantity_product              = $taxes[ $tax_class ]['quantity_product'] + $quantity;
				$merge_products                = $taxes[ $tax_class ]['products'];
				$merge_products[ $product_id ] = array(
					'product_id' => $product_id,
					'quantity'   => $quantity,
					'subtotal'   => $subtotal,
				);
			}
			if ( 'none' !== $tax_details['tax_status'] && 0 < $tax_details['tax_value'] ) {
				$products[ $product_id ] = array(
					'product_id' => $product_id,
					'quantity'   => $quantity,
					'subtotal'   => $subtotal,
				);
				$taxes[ $tax_class ]     = array(
					'tax_info'         => $tax_details,
					'tax_rate'         => $tax_details['tax_rate'],
					'tax_class'        => $tax_class,
					'subtotal'         => isset( $merge_subtotal ) ? $merge_subtotal : $subtotal,
					'products'         => isset( $merge_products ) ? $merge_products : $products,
					'quantity_product' => isset( $quantity_product ) ? $quantity_product : $quantity,
				);
			}
		}
		$all_rates_class = \WC_Tax::get_tax_classes();
		if ( ! $all_rates_class ) {
			$tax_apply = 'standard-rate';
		} else {
			$tax_apply = ! $standard ? sanitize_title( array_shift( $all_rates_class ) ) : 'standard-rate';
		}
		return array(
			'taxes'     => $taxes,
			'tax_apply' => $tax_apply,
		);
	}

	public function calculate_coupon_by_product_in_cart( $apply_currency, $tax_value, $calculate_default = false ) {
		$product_subtotal = $tax_value['subtotal'];
		$quantity_product = $tax_value['quantity_product'];

		$total_coupon_applies = 0;
		$applied_coupons      = WC()->cart->applied_coupons;
		if ( $applied_coupons ) {
			foreach ( $applied_coupons  as $coupon_code ) {
				$coupon          = new \WC_Coupon( $coupon_code );
				$discount_type   = $coupon->get_discount_type();
				$discount_amount = (float) $coupon->get_data()['amount'];

				if ( 'percent' !== $discount_type ) {

					if ( ! $calculate_default ) {
						$discount_amount = YayCurrencyHelper::calculate_price_by_currency( $discount_amount, true, $apply_currency );
					}

					if ( 'fixed_product' === $discount_type ) {
						$discount_amount *= $quantity_product;
					}

					$total_coupon_applies += $discount_amount;

				} else {
					$total_coupon_applies += ( $product_subtotal * $discount_amount ) / 100;
				}
			}
		}
		return $total_coupon_applies;
	}

	public function get_info_taxes_include_in_cart( $apply_currency, $shipping_total, $calculate_default = false ) {
		$cart_contents = WC()->cart->get_cart_contents();
		$taxes_info    = $this->get_taxes_in_cart( $apply_currency, $cart_contents, $calculate_default );
		if ( isset( $taxes_info['taxes'] ) ) {
			$taxes                = $taxes_info['taxes'];
			$tax_apply            = $taxes_info['tax_apply'];
			$total_tax            = 0;
			$apply_coupon         = WC()->cart->applied_coupons;
			$total_coupon_applies = 0;
			$total_tax_fees       = $this->get_total_fees( $apply_currency );
			foreach ( $taxes as $tax_key => $tax_value ) {
				$tax_amount           = $tax_value['tax_info']['tax_value'];
				$total_coupon_applies = $apply_coupon ? $this->calculate_coupon_by_product_in_cart( $apply_currency, $tax_value, $calculate_default ) : 0;

				if ( $tax_apply === $tax_key ) {
					$tax_rate_shipping = isset( $tax_value['tax_info']['tax_rate']['tax_rate_shipping'] ) ? (int) $tax_value['tax_info']['tax_rate']['tax_rate_shipping'] : false;
					if ( $tax_value['subtotal'] < $total_coupon_applies ) {
						$total_by_tax = $tax_rate_shipping ? $shipping_total * $tax_amount / 100 : 0;
					} elseif ( $tax_rate_shipping ) {
							$total_by_tax = ( $tax_value['subtotal'] - $total_coupon_applies + $shipping_total + $total_tax_fees ) * $tax_amount / 100;
					} else {
						$total_by_tax = ( $tax_value['subtotal'] - $total_coupon_applies + $total_tax_fees ) * $tax_amount / 100;
					}
				} else {
					$total_by_tax = ( $tax_value['subtotal'] - $total_coupon_applies + $total_tax_fees ) * $tax_amount / 100;
				}
				$total_tax                     = $total_tax + $total_by_tax;
				$taxes[ $tax_key ]['subtotal'] = $total_by_tax;
			}

			return array(
				'taxes'        => $taxes,
				'tax_apply'    => $tax_apply,
				'total_tax'    => $total_tax,
				'total_coupon' => $total_coupon_applies,
			);
		}

		return false;

	}

	public function checkout_converted_shipping_method_full_label( $label, $method, $shipping_fee, $fallback_currency, $apply_currency ) {
		$method_label                             = $method->label;
		$formatted_fallback_currency_shipping_fee = YayCurrencyHelper::calculate_price_by_currency_html( $fallback_currency, $shipping_fee );
		$converted_approximately                  = apply_filters( 'yay_currency_checkout_converted_approximately', true, $apply_currency );
		if ( ! $converted_approximately ) {
			return $method_label . ': ' . $formatted_fallback_currency_shipping_fee;
		}

		$converted_shipping_fee = YayCurrencyHelper::calculate_price_by_currency( $shipping_fee, true, $apply_currency );
		if ( 'flat_rate' === $method->method_id ) {
			$shipping = new \WC_Shipping_Flat_Rate( $method->instance_id );
			$cost     = $shipping->get_option( 'cost' );
			if ( ! empty( $cost ) && ! is_numeric( $cost ) ) {
				$converted_shipping_fee = SupportHelper::evaluate_cost(
					$cost,
					array(
						'qty'  => SupportHelper::get_product_quantity_item_qty(),
						'cost' => apply_filters( 'yay_currency_get_cart_subtotal', 0, $apply_currency ),
					)
				);
				if ( ! strpos( $cost, 'fee' ) ) {
					$converted_shipping_fee = YayCurrencyHelper::calculate_price_by_currency( $converted_shipping_fee, true, $apply_currency );
				}
			}
		}

		$formatted_shipping_fee = YayCurrencyHelper::format_price( $converted_shipping_fee );
		//  Display approximate price only on the checkout page
		if ( SupportHelper::display_approximate_price_on_checkout() ) {
			return $method_label . ': ' . $formatted_shipping_fee;
		}
		$formatted_shipping_fee_html = YayCurrencyHelper::converted_approximately_html( $formatted_shipping_fee );
		$label                       = $method_label . ': ' . $formatted_fallback_currency_shipping_fee . $formatted_shipping_fee_html;
		return $label;
	}

	public function checkout_converted_cart_coupon_totals_html( $coupon_html, $coupon, $fallback_currency, $apply_currency ) {

		$discount_totals         = WC()->cart->get_coupon_discount_totals();
		$discount_price          = $discount_totals[ $coupon->get_code() ];
		$discount_amount_html    = YayCurrencyHelper::calculate_price_by_currency_html( $fallback_currency, $discount_price );
		$converted_approximately = apply_filters( 'yay_currency_checkout_converted_approximately', true, $apply_currency );
		if ( ! $converted_approximately ) {
			return '-' . $discount_amount_html;
		}
		$converted_discount_price = YayCurrencyHelper::calculate_price_by_currency( $discount_price, true, $apply_currency );
		$formatted_discount_price = YayCurrencyHelper::format_price( $converted_discount_price );
		if ( YayCurrencyHelper::enable_rounding_currency( $apply_currency ) ) {
			$formatted_discount_price = apply_filters( 'yay_currency_checkout_converted_discount_price', $formatted_discount_price, $coupon, $apply_currency );
		}
		//  Display approximate price only on the checkout page
		if ( SupportHelper::display_approximate_price_on_checkout() ) {
			return '-' . $formatted_discount_price;
		}

		$formatted_discount_price_html = YayCurrencyHelper::converted_approximately_html( $formatted_discount_price );
		$custom_coupon_html            = '-' . $discount_amount_html . $formatted_discount_price_html . substr( $coupon_html, strpos( $coupon_html, '<a' ) ) . '';
		return $custom_coupon_html;
	}

	public function get_cart_total_default( $cart_total, $apply_currency ) {
		$cart_subtotal_default = apply_filters( 'yay_currency_get_cart_subtotal_default', 0 );
		$shipping_total        = $this->get_shipping_total_selected( $apply_currency, true );
		$total_coupon_applies  = $this->get_total_coupon_default( $cart_subtotal_default );
		$total_fees            = $this->get_total_fees( $apply_currency, true, true );

		$total_tax  = apply_filters( 'yay_currency_get_total_tax_default', 0, $apply_currency );
		$cart_total = ( $cart_subtotal_default - $total_coupon_applies ) + $total_tax + $shipping_total + $total_fees;
		return $cart_total;
	}

	public function get_discount_total_default( $discount_total, $apply_currency ) {
		$cart_subtotal_default = apply_filters( 'yay_currency_get_cart_subtotal_default', 0 );
		$discount_total        = $this->get_total_coupon_default( $cart_subtotal_default );

		return $discount_total;
	}

	public function get_shipping_total_default( $shipping_total, $apply_currency ) {
		$shipping_total = $this->get_shipping_total_selected( $apply_currency, true );
		return $shipping_total;
	}

	public function get_fee_total_default( $fee_total, $apply_currency ) {
		$fee_total = $this->get_total_fees( $apply_currency, true, true );
		return $fee_total;
	}

	public function get_total_tax_default( $total_tax, $apply_currency ) {
		if ( 'yes' === get_option( 'woocommerce_calc_taxes' ) ) {
			$shipping_total = $this->get_shipping_total_selected( $apply_currency, true, true );
			$taxes_in_cart  = $this->get_info_taxes_include_in_cart( $apply_currency, $shipping_total, true );
			$total_tax      = $taxes_in_cart ? $taxes_in_cart['total_tax'] : 0;
		}
		return $total_tax ? $total_tax : 0;
	}

	// Blocks
	protected function detect_rest_api_doing() {

		if ( ! WC()->is_rest_api_request() ) {
			return false;
		}

		$rest_route = Helper::get_rest_route_via_rest_api();

		if ( $rest_route && in_array( $rest_route, array( '/wc/store/v1/batch', '/wc/store/v1/checkout' ), true ) && isset( $_REQUEST['_locale'] ) ) {
			return true;
		}

		return false;
	}

	protected function detect_set_default_apply_currency_on_checkout_blocks() {

		if ( ! self::detect_rest_api_doing() ) {
			return false;
		}

		if ( ! Helper::detect_checkout_blocks_page() ) {
			return false;
		}

		if ( ! (int) get_option( 'yay_currency_checkout_different_currency', 0 ) ) {
			return true;
		}

		return false;

	}

	public function yay_currency_is_checkout_blocks( $flag ) {
		if ( self::detect_set_default_apply_currency_on_checkout_blocks() ) {
			return true;
		}
		return $flag;
	}
}
