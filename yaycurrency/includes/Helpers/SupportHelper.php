<?php
namespace Yay_Currency\Helpers;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;


class SupportHelper {

	use SingletonTrait;

	protected function __construct() {}

	public static function detect_php_version() {
		$version = phpversion();
		return $version;
	}

	public static function cart_item_maybe_prefix_key( $key, $prefix = '_' ) {
		return ( substr( $key, 0, strlen( $prefix ) ) !== $prefix ) ? $prefix . $key : $key;
	}

	public static function set_cart_item_objects_property( &$data, $key, $value ) {
		if ( self::detect_php_version() < 8.2 ) {
			$data->$key = $value;
		} else {
			$meta_key = self::cart_item_maybe_prefix_key( $key );
			$data->update_meta_data( $meta_key, $value, '' );
		}

	}

	public static function get_cart_item_objects_property( $data, $property ) {
		$value = ! empty( $default ) ? $default : '';
		if ( ! is_object( $data ) ) {
			return false;
		}

		if ( self::detect_php_version() < 8.2 ) {
			return isset( $data->$property ) ? $data->$property : false;
		} else {
			$prefixed_key = self::cart_item_maybe_prefix_key( $property );
			$value        = $data->get_meta( $prefixed_key, true );
			return ! empty( $value ) ? $value : false;
		}

	}

	public static function get_price_options_by_3rd_plugin( $product ) {
		$price_options = apply_filters( 'yay_currency_price_options', 0, $product );
		return $price_options;
	}

	public static function get_price_options_default_by_3rd_plugin( $product ) {
		$price_options = apply_filters( 'yay_currency_price_options_default', 0, $product );
		return $price_options;
	}

	public static function get_product_price( $product_id, $apply_currency = false ) {
		$_product      = wc_get_product( $product_id );
		$product_price = $_product->get_price( 'edit' );
		if ( $apply_currency ) {
			$product_price = YayCurrencyHelper::calculate_price_by_currency( $product_price, false, $apply_currency );
		}
		return $product_price;
	}

	public static function get_product_price_by_3rd_plugin( $product_price, $product, $apply_currency ) {
		$product_price = apply_filters( 'yay_currency_get_product_price_by_3rd_plugin', $product_price, $product, $apply_currency );
		return $product_price;
	}

	// GET PRICE SIGNUP FEE (WooCommerce Subscriptions plugin)
	public static function get_price_sign_up_fee_by_wc_subscriptions( $apply_currency, $product_obj ) {
		$sign_up_fee = 0;
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return $sign_up_fee;
		}
		if ( class_exists( 'WC_Subscriptions_Product' ) ) {
			$sign_up_fee = \WC_Subscriptions_Product::get_sign_up_fee( $product_obj );
			if ( $sign_up_fee > 0 ) {
				$sign_up_fee = YayCurrencyHelper::calculate_price_by_currency( $sign_up_fee, false, $apply_currency );
			}
		}
		return $sign_up_fee;
	}

	public static function calculate_product_price_by_cart_item( $cart_item, $apply_currency = false ) {
		$product_id    = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
		$_product      = wc_get_product( $product_id );
		$product_price = $_product->get_price( 'edit' );
		if ( $apply_currency ) {
			$product_price = YayCurrencyHelper::calculate_price_by_currency( $product_price, false, $apply_currency );
			$product_price = apply_filters( 'yay_currency_get_product_price_by_cart_item', $product_price, $cart_item, $apply_currency );
			$price_options = apply_filters( 'yay_currency_get_price_options_by_cart_item', 0, $cart_item, $product_id, $product_price, $apply_currency );
			return $price_options ? $product_price + $price_options : $product_price;
		}
		return $product_price;
	}

	// Calculate Cart Subtotal
	public static function calculate_cart_subtotal( $apply_currency ) {

		$cart_contents = WC()->cart->get_cart_contents();
		if ( ! $cart_contents ) {
			return 0;
		}

		$subtotal = 0;
		foreach ( $cart_contents  as $key => $cart_item ) {
			$product_price = self::calculate_product_price_by_cart_item( $cart_item, $apply_currency );
			$subtotal      = $subtotal + ( $product_price * $cart_item['quantity'] );
		}

		return $subtotal;
	}

	public static function get_product_price_default_by_cart_item( $cart_item ) {
		$product_id    = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
		$product_price = self::get_product_price( $product_id );
		$product_price = apply_filters( 'yay_currency_get_product_price_default_by_cart_item', $product_price, $cart_item );
		$price_options = apply_filters( 'yay_currency_get_price_options_default_by_cart_item', 0, $cart_item, $product_id, $product_price );
		return $price_options ? $product_price + $price_options : $product_price;
	}

	public static function get_cart_subtotal_default() {
		$subtotal      = 0;
		$cart_contents = WC()->cart->get_cart_contents();
		foreach ( $cart_contents  as $cart_item ) {
			$product_price = self::get_product_price_default_by_cart_item( $cart_item );
			$product_price = floatval( $product_price );
			if ( $product_price ) {
				$product_subtotal = $product_price * $cart_item['quantity'];
				$subtotal         = $subtotal + $product_subtotal;
			}
		}

		return $subtotal;
	}

	public static function get_cart_total_default( $apply_currency ) {
		$total = apply_filters( 'yay_currency_get_cart_total_default', 0, $apply_currency );
		return $total;
	}

	public static function calculate_discount_from() {
		if ( defined( 'WDR_VERSION' ) && class_exists( '\Wdr\App\Controllers\DiscountCalculator' ) ) {
			$calculate_discount_from = \Wdr\App\Controllers\DiscountCalculator::$config->getConfig( 'calculate_discount_from', 'sale_price' );
		} else {
			$calculate_discount_from = 'sale_price';
		}
		return $calculate_discount_from;
	}

	public static function woo_discount_rules_active() {
		return apply_filters( 'yay_currency_active_woo_discount_rules', false );
	}

	public static function get_original_price_apply_discount_pro( $product_id ) {
		$calculate_discount_from = self::calculate_discount_from();
		if ( 'sale_price' === $calculate_discount_from ) {
			$original_price = (float) get_post_meta( $product_id, '_sale_price', true );
		} else {
			$original_price = (float) get_post_meta( $product_id, '_regular_price', true );
		}
		return (float) $original_price;
	}

	public static function get_product_quantity_item_qty() {

		$total_quantity = 0;

		$cart_contents = WC()->cart->get_cart_contents();

		if ( $cart_contents ) {
			foreach ( $cart_contents as $cart_item ) {
				if ( $cart_item['quantity'] > 0 && $cart_item['data']->needs_shipping() ) {
					$total_quantity += $cart_item['quantity'];
				}
			}
		}

		return $total_quantity;
	}

	public static function get_shipping_flat_rate_fee_total_selected( $apply_currency = array(), $calculate_default = false, $calculate_tax = false ) {
		$shipping = WC()->session->get( 'shipping_for_package_0' );
		if ( ! $shipping || ! isset( $shipping['rates'] ) ) {
			return false;
		}
		$flag = false;
		foreach ( $shipping['rates'] as $method_id => $rate ) {
			if ( WC()->session->get( 'chosen_shipping_methods' )[0] === $method_id ) {
				if ( 'local_pickup' === $rate->method_id ) {
					$shipping = new \WC_Shipping_Local_Pickup( $rate->instance_id );
					if ( $calculate_tax && 'taxable' !== $shipping->tax_status ) {
						$flag = -1;
						break;
					}
				}

				if ( 'flat_rate' === $rate->method_id ) {

					$shipping = new \WC_Shipping_Flat_Rate( $rate->instance_id );

					if ( $calculate_tax && 'taxable' !== $shipping->tax_status ) {
						$flag = -1;
						break;
					}

					$cost = $shipping->get_option( 'cost' );

					if ( ! empty( $cost ) && ! is_numeric( $cost ) ) {
						if ( ! $calculate_default ) {
							$args = array(
								'qty'  => self::get_product_quantity_item_qty(),
								'cost' => apply_filters( 'yay_currency_get_cart_subtotal', 0, $apply_currency ),
							);
							$flag = self::evaluate_cost( $cost, $args );

						} else {
							$args = array(
								'qty'  => self::get_product_quantity_item_qty(),
								'cost' => apply_filters( 'yay_currency_get_cart_subtotal_default', 0 ),
							);
							$flag = self::evaluate_cost( $cost, $args, true );
						}

						break;
					}
				}
			}
		}
		return $flag;
	}

	public static function evaluate_cost( $sum, $args = array(), $calculate_default = false ) {
		// Add warning for subclasses.
		if ( ! is_array( $args ) || ! array_key_exists( 'qty', $args ) || ! array_key_exists( 'cost', $args ) ) {
			wc_doing_it_wrong( __FUNCTION__, '$args must contain `cost` and `qty` keys.', '4.0.1' );
		}

		include_once WC()->plugin_path() . '/includes/libraries/class-wc-eval-math.php';

		$locale   = localeconv();
		$decimals = array( wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point'], ',' );
		if ( $calculate_default ) {
			$sum = str_replace( '[yaycurrency-fee', '[yaycurrency-fee-default', $sum );
			$sum = str_replace( '[fee', '[yaycurrency-fee-default', $sum );
		} else {
			$sum = str_replace( '[yaycurrency-fee-default', '[yaycurrency-fee', $sum );
			$sum = str_replace( '[fee', '[yaycurrency-fee', $sum );
		}

		$sum = do_shortcode(
			str_replace(
				array(
					'[qty]',
					'[cost]',
				),
				array(
					$args['qty'],
					$args['cost'],
				),
				$sum
			)
		);

		// Remove whitespace from string.
		$sum = preg_replace( '/\s+/', '', $sum );

		// Remove locale from string.
		$sum = str_replace( $decimals, '.', $sum );

		// Trim invalid start/end characters.
		$sum = rtrim( ltrim( $sum, "\t\n\r\0\x0B+*/" ), "\t\n\r\0\x0B+-*/" );

		// Do the math.
		return $sum ? \WC_Eval_Math::evaluate( $sum ) : 0;
	}

	public static function get_filters_priority( $priority = 10 ) {
		// Compatible with B2B Wholesale Suite, Price by Country, B2BKing
		if ( class_exists( 'B2bwhs' ) || class_exists( 'CBP_Country_Based_Price' ) || class_exists( 'B2bkingcore' ) ) {
			$priority = 100000;
		}

		return apply_filters( 'yay_currency_filters_priority', $priority );
	}

	public static function get_fee_priority( $priority = 10 ) {
		// Payment Gateway Based Fees and Discounts for WooCommerce
		if ( class_exists( 'Alg_Woocommerce_Checkout_Fees' ) ) {
			$priority = PHP_INT_MAX;
		}

		return apply_filters( 'yay_currency_fee_priority', $priority );
	}

	public static function get_format_filters_priority( $priority = 10 ) {
		if ( class_exists( 'AG_Tyl_init' ) || class_exists( 'WC_Product_Price_Based_Country' ) ) {
			$priority = 9999;
		}

		return apply_filters( 'yay_currency_format_filters_priority', $priority );
	}

	public static function should_change_email_symbol( $flag = false ) {

		if ( defined( 'WCQP_VERSION' ) ) {
			$flag = true;
		}

		if ( class_exists( 'KCO' ) ) {
			$flag = true;
		}

		if ( function_exists( 'Mollie\WooCommerce\mollie_wc_plugin_autoload' ) || function_exists( 'grilabs_woocommerce_pos_init' ) ) {
			$flag = true;
		}

		return apply_filters( 'yay_currency_email_change_currency_symbol', $flag );
	}

	public static function detect_keep_old_currency_symbol( $flag, $is_dis_checkout_diff_currency, $apply_currency ) {

		if ( YayCurrencyHelper::detect_allow_hide_dropdown_currencies() ) {
			return true;
		}

		return apply_filters( 'yay_currency_use_default_default_currency_symbol', $flag, $is_dis_checkout_diff_currency, $apply_currency );

	}

	public static function detect_ignore_price_conversion( $flag, $price, $product ) {
		// Role Based Pricing for WooCommerce plugin & WooCommerce Bulk Discount plugin
		if ( class_exists( 'AF_C_S_P_Price' ) || class_exists( 'Woo_Bulk_Discount_Plugin_t4m' ) || class_exists( 'FP_Lottery' ) ) {
			$flag = true;
		}

		if ( defined( 'SUBSCRIPTIONS_FOR_WOOCOMMERCE_VERSION' ) ) {
			$flag = true;
		}

		return apply_filters( 'yay_currency_before_calculate_totals_ignore_price_conversion', $flag, $price, $product );
	}

	public static function detect_original_product_price( $flag, $price, $product ) {

		if ( empty( $price ) || ! is_numeric( $price ) || YayCurrencyHelper::is_wc_json_products() || class_exists( 'BM' ) ) {
			$flag = true;
		}

		if ( class_exists( 'WC_Bookings' ) && 'booking' === $product->get_type() && doing_filter( 'woocommerce_get_price_html' ) ) {
			$flag = true;
		}

		// WC Fields Factory plugin
		if ( class_exists( 'wcff' ) && doing_filter( 'woocommerce_get_cart_item_from_session' ) ) {
			$flag = true;
		}

		if ( doing_filter( 'woocommerce_before_calculate_totals' ) ) {
			$flag = self::detect_ignore_price_conversion( $flag, $price, $product );
		}

		return apply_filters( 'yay_currency_is_original_product_price', $flag, $price, $product );
	}

	public static function detect_used_other_currency_3rd_plugin( $order_id, $order ) {

		//FOX - Currency Switcher Professional for WooCommerce
		$order_rate = get_post_meta( $order_id, '_woocs_order_rate', true );
		//WooPayments
		$wcpay_default_currency = get_post_meta( $order_id, '_wcpay_multi_currency_order_default_currency', true );
		//CURCY - WooCommerce Multi Currency
		$wmc_order_info = get_post_meta( $order_id, 'wmc_order_info', true );

		if ( $order_rate || $wcpay_default_currency || $wmc_order_info ) {
			return true;
		}

		if ( Helper::check_custom_orders_table_usage_enabled() ) {
			if ( $order->get_meta( '_woocs_order_rate', true ) || $order->get_meta( '_wcpay_multi_currency_order_default_currency', true ) ) {
				return true;
			}
			if ( $order->get_meta( 'wmc_order_info', true ) ) {
				return true;
			}
		}

		return false;

	}

	public static function detect_deregister_script() {
		if ( class_exists( '\LP_Admin_Assets' ) ) {
			wp_deregister_script( 'vue-libs' );
		}
		if ( defined( 'STM_MOTORS_EXTENDS_PLUGIN_VERSION' ) ) {
			wp_deregister_script( 'vue.js' );
		}
		//EnvíaloSimple: Email Marketing y Newsletters plugin
		if ( defined( 'ES_PLUGIN_URL_BASE' ) ) {
			wp_deregister_script( 'es-vue-js' );
		}
		do_action( 'yay_currency_admin_deregister_script' );
	}

	public static function display_approximate_price_on_checkout() {
		return apply_filters( 'yay_currency_display_approximate_price_on_checkout', false );
	}
}
