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
		$price_options = apply_filters( 'YayCurrency/ApplyCurrency/GetPriceOptions', 0, $product );
		return $price_options;
	}

	public static function get_price_options_default_by_3rd_plugin( $product ) {
		$price_options = apply_filters( 'YayCurrency/StoreCurrency/GetPriceOptions', 0, $product );
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
		$product_price = apply_filters( 'YayCurrency/ApplyCurrency/ThirdPlugins/GetProductPrice', $product_price, $product, $apply_currency );
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
			$product_price = apply_filters( 'YayCurrency/ApplyCurrency/ByCartItem/GetProductPrice', $product_price, $cart_item, $apply_currency );
			$price_options = apply_filters( 'YayCurrency/ApplyCurrency/ByCartItem/GetPriceOptions', 0, $cart_item, $product_id, $product_price, $apply_currency );
			return $price_options ? $product_price + $price_options : $product_price;
		}
		return $product_price;
	}

	public static function get_total_shipping_fee_flat_rate_method( $shipping_fee, $method, $data ) {
		$apply_currency               = isset( $data['apply_currency'] ) ? $data['apply_currency'] : false;
		$will_not_round_shipping_cost = apply_filters( 'yay_currency_will_not_round_shipping_cost', false );
		if ( ! $apply_currency ) {
			return $shipping_fee;
		}
		$has_costs   = false;
		$is_fallback = isset( $data['is_fallback'] ) ? $data['is_fallback'] : false;
		$shipping    = new \WC_Shipping_Flat_Rate( $method->instance_id );
		$cost        = $shipping->get_option( 'cost' );
		if ( ! empty( $cost ) ) {
			if ( ! is_numeric( $cost ) ) {
				$has_costs    = preg_match( '/\[fee(\s|\])/i', $cost ) ? true : false;
				$args         = array(
					'qty'  => self::get_product_quantity_item_qty(),
					'cost' => apply_filters( 'YayCurrency/ApplyCurrency/GetCartSubtotalWithShipping', 0, $apply_currency ),
				);
				$shipping_fee = self::evaluate_cost( $cost, $args, false, $is_fallback );
				if ( is_numeric( $shipping_fee ) && ! strpos( $cost, 'fee' ) ) {
					$shipping_fee = YayCurrencyHelper::calculate_price_by_currency( $shipping_fee, true, $apply_currency );
				}
				$shipping_fee = apply_filters( 'YayCurrency/InclTax/GetShippingFee', $shipping_fee, $method, $apply_currency );
			} else {
				$shipping_fee = YayCurrencyHelper::calculate_price_by_currency( $cost, true, $apply_currency );
			}
		} else {
			$shipping_fee = 0;
		}

		$shipping_classes = WC()->shipping->get_shipping_classes();
		if ( empty( $shipping_classes ) ) {
			return $shipping_fee;
		}

		$package                = array();
		$cart_shipping_packages = WC()->cart->get_shipping_packages();
		if ( $cart_shipping_packages && is_array( $cart_shipping_packages ) ) {
			$package = array_shift( $cart_shipping_packages );
		}
		$product_shipping_classes = $shipping->find_shipping_classes( $package );
		$rate_class_cost          = 0;
		$shipping_classes_cost    = 0;
		foreach ( $product_shipping_classes as $shipping_class => $products ) {
			$class_has_fee_costs = false;
			$shipping_class_term = get_term_by( 'slug', $shipping_class, 'product_shipping_class' );
			$class_cost_string   = $shipping_class_term && $shipping_class_term->term_id ? $shipping->get_option( 'class_cost_' . $shipping_class_term->term_id, $shipping->get_option( 'class_cost_' . $shipping_class, '' ) ) : $shipping->get_option( 'no_class_cost', '' );
			if ( '' === $class_cost_string ) {
				continue;
			}
			if ( ! empty( $class_cost_string ) && ! is_numeric( $class_cost_string ) ) {
				$class_has_fee_costs = preg_match( '/\[fee(\s|\])/i', $class_cost_string ) ? true : false;
				//calculate class cost
				$subtotal = array_sum( wp_list_pluck( $products, 'line_total' ) );

				$class_cost = self::evaluate_cost(
					$class_cost_string,
					array(
						'qty'  => array_sum( wp_list_pluck( $products, 'quantity' ) ),
						'cost' => YayCurrencyHelper::calculate_price_by_currency( $subtotal, false, $apply_currency ),
					)
				);

			} else {
				if ( is_numeric( $cost ) ) {
					if ( $class_has_fee_costs ) {
						$class_cost_string = YayCurrencyHelper::calculate_price_by_currency( $class_cost_string, $will_not_round_shipping_cost, $apply_currency );
					}
				}

				$class_cost = $class_cost_string;
			}

			if ( is_numeric( $class_cost ) ) {
				if ( ! $class_has_fee_costs ) {
					$rate_class_cost += YayCurrencyHelper::calculate_price_by_currency( $class_cost, $will_not_round_shipping_cost, $apply_currency );
				} elseif ( 'class' === $shipping->type ) {
					$rate_class_cost += $class_cost;
				} else {
					$shipping_classes_cost = $class_cost > $shipping_classes_cost ? $class_cost : $shipping_classes_cost;
				}
			}

			if ( 'order' === $shipping->type && $shipping_classes_cost ) {
				$rate_class_cost += $shipping_classes_cost;
			}
		}
		if ( $has_costs ) {
			if ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_cart' ) ) {
				$tax_class_cost = \WC_Tax::calc_shipping_tax( $rate_class_cost, \WC_Tax::get_shipping_tax_rates() );
				if ( $tax_class_cost && is_array( $tax_class_cost ) ) {
					$rate_class_cost += array_shift( $tax_class_cost );
				}
			}

			if ( ! is_numeric( $cost ) ) {
				$cost = self::evaluate_cost(
					$cost,
					array(
						'qty'  => self::get_product_quantity_item_qty(),
						'cost' => apply_filters( 'YayCurrency/ApplyCurrency/GetCartSubtotalWithShipping', 0, $apply_currency ),
					)
				);
			} else {
				$cost = YayCurrencyHelper::calculate_price_by_currency( $cost, $will_not_round_shipping_cost, $apply_currency );
			}
			$shipping_fee = $rate_class_cost + $cost;
		} else {
			$shipping_fee += $rate_class_cost;
		}
		return $shipping_fee;

	}

	public static function detect_shipping_methods_ignore() {
		$shipping_methods_args = array( 'alids', 'betrs_shipping', 'printful_shipping', 'easyship', 'printful_shipping_STANDARD', 'BookVAULT Shipping' );
		$special_methods_args  = array( 'per_product', 'tree_table_rate' );

		return array(
			'shipping_methods' => apply_filters( 'YayCurrency/Detect/Ignore/ShippingMethods', $shipping_methods_args ),
			'special_methods'  => apply_filters( 'YayCurrency/Detect/Ignore/SpecialMethods', $special_methods_args ),
		);
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

	public static function get_cart_subtotal_with_shipping( $apply_currency = array() ) {
		$subtotal      = 0;
		$cart_contents = WC()->cart->get_cart_contents();
		foreach ( $cart_contents  as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : false;
			if ( $product && $product->needs_shipping() ) {
				$product_price = self::calculate_product_price_by_cart_item( $cart_item, $apply_currency );
				$subtotal      = $subtotal + ( $product_price * $cart_item['quantity'] );
			}
		}
		return $subtotal;
	}

	public static function get_product_price_default_by_cart_item( $cart_item ) {
		$product_id    = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
		$product_price = self::get_product_price( $product_id );
		$product_price = apply_filters( 'YayCurrency/StoreCurrency/ByCartItem/GetProductPrice', $product_price, $cart_item );
		$price_options = apply_filters( 'YayCurrency/StoreCurrency/ByCartItem/GetPriceOptions', 0, $cart_item, $product_id, $product_price );
		return $price_options ? $product_price + $price_options : $product_price;
	}

	public static function get_cart_subtotal_default() {
		$subtotal = 0;
		if ( ! WC()->cart ) {
			return $subtotal;
		}
		$cart_contents = WC()->cart->get_cart_contents();
		if ( ! $cart_contents ) {
			return $subtotal;
		}
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

	public static function calculate_discount_from() {
		if ( defined( 'WDR_VERSION' ) && class_exists( '\Wdr\App\Controllers\DiscountCalculator' ) ) {
			$calculate_discount_from = \Wdr\App\Controllers\DiscountCalculator::$config->getConfig( 'calculate_discount_from', 'sale_price' );
		} else {
			$calculate_discount_from = 'sale_price';
		}
		return $calculate_discount_from;
	}

	public static function woo_discount_rules_active() {
		return apply_filters( 'YayCurrency/WooDiscountRules/Active', false );
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

		if ( ! WC()->cart ) {
			return $total_quantity;
		}
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

	public static function get_total_coupons( $cart_subtotal = 0, $apply_currency = false ) {
		$total_coupon_applies = 0;
		if ( ! WC()->cart ) {
			return $total_coupon_applies;
		}
		$applied_coupons = WC()->cart->applied_coupons;
		if ( $applied_coupons ) {
			foreach ( $applied_coupons  as $coupon_code ) {
				$coupon          = new \WC_Coupon( $coupon_code );
				$discount_type   = $coupon->get_discount_type();
				$coupon_data     = $coupon->get_data();
				$discount_amount = (float) $coupon_data['amount'];

				if ( 'percent' !== $discount_type ) {

					if ( 'fixed_product' === $discount_type ) {
						$discount_amount *= self::get_product_quantity_item_qty( true );
					}

					if ( apply_filters( 'YayCurrency/InclTax/Enable', false ) ) {
						$discount_totals     = WC()->cart->get_coupon_discount_totals();
						$discount_tax_totals = WC()->cart->get_coupon_discount_tax_totals();
						$discount_totals     = wc_array_merge_recursive_numeric( $discount_totals, $discount_tax_totals );
						$discount_amount     = $discount_totals[ $coupon->get_code() ];
					}

					if ( apply_filters( 'YayCurrency/ExclTax/Enable', false ) ) {
						$tax_rate_percent = apply_filters( 'YayCurrency/Tax/InCart/GetRatePercent', false );
						if ( $tax_rate_percent ) {
							$discount_amount = $discount_amount / ( 1 + $tax_rate_percent );
						}
					}

					if ( $apply_currency ) {
						$discount_amount = YayCurrencyHelper::calculate_price_by_currency( $discount_amount, true, $apply_currency );
					}

					$total_coupon_applies += $discount_amount;
				} else {
					$total_coupon_applies += ( $cart_subtotal * $discount_amount ) / 100;
				}
			}
		}
		return $total_coupon_applies;
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
								'cost' => apply_filters( 'YayCurrency/ApplyCurrency/GetCartSubtotal', 0, $apply_currency ),
							);
							$flag = self::evaluate_cost( $cost, $args );

						} else {
							$args = array(
								'qty'  => self::get_product_quantity_item_qty(),
								'cost' => apply_filters( 'YayCurrency/StoreCurrency/GetCartSubtotal', 0 ),
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

		YayCurrencyHelper::$evaluate_line_subtotal = $args['cost'];

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

		return apply_filters( 'yay_currency_product_prices_filters_priority', $priority );
	}

	public static function get_fee_priority( $priority = 10 ) {
		// Payment Gateway Based Fees and Discounts for WooCommerce
		if ( class_exists( 'Alg_Woocommerce_Checkout_Fees' ) ) {
			$priority = PHP_INT_MAX;
		}

		if ( class_exists( '\Packetery\Module\Plugin' ) ) {
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

	public static function detect_original_format_order_item_totals( $flag, $total_rows, $order, $tax_display ) {
		if ( isset( $_GET['action'] ) && 'generate_wpo_wcpdf' === $_GET['action'] ) {
			$flag = true;
		}
		return apply_filters( 'yay_currency_is_original_format_order_item_totals', $flag, $total_rows, $order, $tax_display );
	}

	public static function detect_keep_old_currency_symbol( $flag, $is_dis_checkout_diff_currency, $apply_currency ) {

		if ( YayCurrencyHelper::detect_allow_hide_dropdown_currencies() ) {
			return true;
		}

		if ( ! YayCurrencyHelper::should_run_for_request() ) {
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

		// Custom Product Boxes: https://wisdmlabs.com/assorted-bundles-woocommerce-custom-product-boxes-plugin/
		if ( class_exists( 'Custom_Product_Boxes' ) ) {
			$flag = true;
		}

		return apply_filters( 'yay_currency_before_calculate_totals_ignore_price_conversion', $flag, $price, $product );
	}

	public static function detect_original_product_price( $flag, $price, $product ) {

		if ( ! YayCurrencyHelper::should_run_for_request() ) {
			return true;
		}

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

	public static function display_approximately_converted_price( $apply_currency ) {
		return apply_filters( 'yay_currency_checkout_converted_approximately', true, $apply_currency );
	}

	public static function display_approximate_price_checkout_only() {
		return apply_filters( 'yay_currency_display_approximate_price_checkout_only', false );
	}

	// WooCommerce Block support

	public static function detect_wc_store_rest_api_doing() {
		if ( ! WC()->is_rest_api_request() ) {
			return false;
		}

		$rest_route = Helper::get_rest_route_via_rest_api();

		return $rest_route && strpos( $rest_route, '/wc/store/' ) === 0;
	}

	public static function is_checkout_blocks() {

		// Return false if not rest api request
		if ( ! self::detect_wc_store_rest_api_doing() ) {
			return false;
		}

		// Return true if force country by checkout blocks page
		if ( 'checkout' === self::get_wc_blocks_page_context() ) {
			return true;
		}

		return apply_filters( 'YayCurrency/WooBlocks/IsCheckout', false );

	}

	public static function get_wc_blocks_page_context() {
		$page_context = '';

		if ( ! self::detect_wc_store_rest_api_doing() ) {
			return $page_context;
		}

		if ( isset( $_SERVER['HTTP_YAYCURRENCY_WC_BLOCKS_CONTEXT'] ) ) {
			$page_context = sanitize_text_field( $_SERVER['HTTP_YAYCURRENCY_WC_BLOCKS_CONTEXT'] );
		}

		return $page_context;

	}
}
