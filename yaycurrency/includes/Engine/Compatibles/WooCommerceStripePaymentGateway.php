<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

class WooCommerceStripePaymentGateway {


	use SingletonTrait;

	private $apply_currency = array();
	private $is_dis_checkout_diff_currency;

	public function __construct() {

		if ( ! defined( 'WC_STRIPE_VERSION' ) ) {
			return;
		}

		$this->apply_currency                = YayCurrencyHelper::detect_current_currency();
		$this->is_dis_checkout_diff_currency = YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency );

		add_filter( 'wc_stripe_request_body', array( $this, 'custom_stripe_request_total_amount' ), 10, 2 );

		if ( $this->is_dis_checkout_diff_currency ) {
			// custom approximately price in cart
			add_filter( 'woocommerce_cart_product_price', array( $this, 'custom_woocommerce_cart_product_price' ), 10, 2 );

			add_filter( 'yay_currency_is_original_default_currency', array( $this, 'is_original_default_currency' ), 20, 2 );
			add_filter( 'yay_currency_is_original_with_3rd_plugin', array( $this, 'is_original_with_3rd_plugin' ), 10, 3 );
			add_filter( 'YayCurrency/Detect/ShippingCost/IsStoreCurrency', array( $this, 'is_original_shipping_cost' ), 10, 2 );
			add_filter( 'yay_currency_should_show_approximate_price', array( $this, 'should_show_approximate_price' ), 10, 1 );

			add_filter( 'yay_currency_use_default_default_currency_symbol', array( $this, 'default_default_currency_symbol' ), 20, 3 );
		}

	}

	public function custom_stripe_request_total_amount( $request, $api ) {
		if ( isset( $request['currency'] ) && isset( $request['metadata'] ) && isset( $request['metadata']['order_id'] ) ) {
			$array_zero_decimal_currencies = array(
				'BIF',
				'CLP',
				'DJF',
				'GNF',
				'JPY',
				'KMF',
				'KRW',
				'MGA',
				'PYG',
				'RWF',
				'UGX',
				'VND',
				'VUV',
				'XAF',
				'XOF',
				'XPF',
			);
			if ( in_array( strtoupper( $request['currency'] ), $array_zero_decimal_currencies ) ) {
				$order_id = $request['metadata']['order_id'];
				if ( ! empty( $order_id ) ) {
					$order = wc_get_order( $order_id );
					if ( ! $order ) {
						return $request;
					}
					$order_total       = YayCurrencyHelper::get_total_by_order( $order );
					$request['amount'] = floatval( $order_total );
				}
			}
		}
		return $request;
	}

	protected function has_stripe_express_checkout_enabled() {
		$stripe_settings = get_option( 'woocommerce_stripe_settings', [] );
		$show            = ! empty( $stripe_settings['payment_request'] ) && 'yes' === $stripe_settings['payment_request'];
		return $show;
	}

	public function should_show_approximate_price( $flag ) {
		// Detect stripe payment request doing
		if ( is_cart() ) {
			// Display approximate price on pages if stripe payment request is doing and skip approximate price on pages is not true
			if ( self::has_stripe_express_checkout_enabled() && ! apply_filters( 'yay_currency_should_skip_approximate_price', false ) ) {
				$flag = true;
			}
		}

		return $flag;
	}

	public function is_original_with_3rd_plugin( $flag, $price, $product ) {
		if ( self::has_stripe_express_checkout_enabled() ) {
			$flag = true;
		}
		return $flag;
	}

	public function is_original_default_currency( $flag, $apply_currency ) {

		if ( is_cart() && self::has_stripe_express_checkout_enabled() ) {
			$flag = true;
		}

		return $flag;
	}

	public function default_default_currency_symbol( $flag, $is_dis_checkout_diff_currency, $apply_currency ) {
		if ( is_cart() && self::has_stripe_express_checkout_enabled() ) {
			$flag = true;
		}
		return $flag;
	}

	public function is_original_shipping_cost( $flag, $apply_currency ) {
		if ( self::has_stripe_express_checkout_enabled() ) {
			$flag = true;
		}
		return $flag;
	}

	public function custom_woocommerce_cart_product_price( $product_price_html, $product ) {

		if ( is_cart() && self::has_stripe_express_checkout_enabled() && Helper::default_currency_code() !== $this->apply_currency['currency'] ) {
			$converted_approximately = SupportHelper::display_approximately_converted_price( $this->apply_currency );

			if ( ! $converted_approximately || apply_filters( 'yay_currency_should_skip_approximate_price', false ) ) {
				return $product_price_html;
			}

			$product_price = apply_filters( 'YayCurrency/StoreCurrency/GetPrice', $product->get_price( 'edit' ), $product );
			$price_args    = array(
				'qty'   => 1,
				'price' => $product_price,
			);

			$cart_product_item_price              = wc()->cart->display_prices_including_tax() ? wc_get_price_including_tax( $product, $price_args ) : wc_get_price_excluding_tax( $product, $price_args );
			$fallback_currency                    = YayCurrencyHelper::get_currency_by_currency_code( Helper::default_currency_code() );
			$fallback_currency_product_price_html = YayCurrencyHelper::get_formatted_total_by_convert_currency( $cart_product_item_price, $fallback_currency, $fallback_currency['currency'] );

			$convert_cart_product_item_price   = YayCurrencyHelper::calculate_price_by_currency( $cart_product_item_price, false, $this->apply_currency );
			$converted_cart_product_item_price = apply_filters( 'YayCurrency/ApplyCurrency/ThirdPlugins/GetProductPrice', $convert_cart_product_item_price, $product, $this->apply_currency );
			$converted_cart_product_item_price = YayCurrencyHelper::get_formatted_total_by_convert_currency( $converted_cart_product_item_price, $this->apply_currency, $this->apply_currency['currency'] );

			$cart_product_item_price_html = YayCurrencyHelper::converted_approximately_html( $converted_cart_product_item_price );
			$product_price_html           = $fallback_currency_product_price_html . $cart_product_item_price_html;

		}

		return $product_price_html;

	}
}
