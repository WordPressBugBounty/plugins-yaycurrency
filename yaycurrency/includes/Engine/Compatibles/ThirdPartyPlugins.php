<?php

namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

use Yay_Currency\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class ThirdPartyPlugins {
	use SingletonTrait;

	public function __construct() {

		// ======= Cache Plugins ============

		// WP Grid Builder Caching. Link plugin: https://www.wpgridbuilder.com
		if ( class_exists( 'WP_Grid_Builder_Caching\Includes\Plugin' ) ) {
			add_filter( 'wp_grid_builder_caching/bypass', array( $this, 'bypass_grid_builder_caching' ), 10, 2 );
		}

		// Revolut Gateway for WooCommerce. Link plugin: https://wordpress.org/plugins/revolut-gateway-for-woocommerce/
		if ( defined( 'WC_GATEWAY_REVOLUT_VERSION' ) ) {
			add_filter( 'yay_currency_woocommerce_currency', array( $this, 'yay_revolut_gateway_convert_currency' ), 999, 2 );
		}

		// ======= Payment Plugins ============

		// AG WooCommerce Tyl By NatWest Payment Gateway. Link plugin: https://weareag.co.uk/product/tyl-by-natwest-for-woocommerce/
		if ( class_exists( 'AG_Tyl_init' ) ) {
			add_filter( 'yay_currency_woocommerce_currency', array( $this, 'yay_agtyl_convert_to_default_currency' ), 999, 2 );
			add_filter( 'woocommerce_order_get_total', array( $this, 'yay_agtyl_convert_woocommerce_order_get_total' ), 9999, 2 );
		}

		// Tabby Payment.
		if ( class_exists( '\WC_Tabby' ) ) {
			add_filter( 'tabby_checkout_tabby_currency', array( $this, 'yay_tabby_get_woocommerce_currency' ), 10, 1 );
		}

		// WooCommerce Pay For Payment. Link plugin: https://cs.wordpress.org/plugins/woocommerce-pay-for-payment/
		if ( function_exists( 'pay4payment_plugin_init' ) ) {
			add_filter( 'woocommerce_pay4pay_charges_fixed', array( $this, 'pay4pay_custom_fee' ) );
			add_filter( 'woocommerce_pay4pay_charges_minimum', array( $this, 'pay4pay_custom_fee' ) );
			add_filter( 'woocommerce_pay4pay_charges_maximum', array( $this, 'pay4pay_custom_fee' ) );
		}

		// Rapyd Payment Gateway. Link plugin : https://woocommerce.com/document/rapyd-payments-plugin-for-woocommerce/

		if ( class_exists( 'WC_Rapyd_Payment_Gateway' ) ) {
			add_filter( 'woocommerce_order_get_total', array( $this, 'wc_rapyd_custom_get_order_total' ), 10, 2 );
		}

		// ======= Shipping Plugins ============

		// Advanced Flat Rate Shipping For WooCommerce Pro. Link plugin: https://woo.com/document/advanced-flat-rate-shipping-method-for-woocommerce/
		if ( defined( 'AFRSM_PRO_PLUGIN_VERSION' ) ) {
			add_action( 'advance_flat_rate_shipping_new_total', array( $this, 'yay_afrsm_custom_new_total' ), 10, 1 );
		}

		// Flexible Shipping. Link plugin: https://wordpress.org/plugins/flexible-shipping/
		if ( defined( 'FLEXIBLE_SHIPPING_VERSION' ) ) {
			add_filter( 'flexible_shipping_value_in_currency', array( $this, 'custom_flexible_shipping_fee' ), 1 );
		}

		// Shipmondo. Link plugin: https://wordpress.org/plugins/pakkelabels-for-woocommerce/
		if ( function_exists( 'shipmondo_init' ) ) {
			add_filter( 'woocommerce_shipping_shipmondo_instance_option', array( $this, 'shipmondo_shipping_instance_option' ), 20, 3 );
		}

		// WooCommerce Table Rate Shipping. Link plugin: https://woocommerce.com/products/table-rate-shipping/
		if ( class_exists( 'WC_Table_Rate_Shipping ' ) ) {
			add_filter( 'woocommerce_table_rate_package_row_base_price', array( $this, 'yay_wc_table_rate_shipping_plugin_row_base_price' ), 10, 3 );
		}

		// ======= Recalculate Price Plugins ============

		// YayPricing. Link plugin: https://wordpress.org/plugins/yaypricing/
		if ( defined( 'YAYDP_VERSION' ) ) {
			add_filter( 'YayCurrency/ApplyCurrency/ByCartItem/GetProductPrice', array( $this, 'yay_pricing_get_product_price_by_cart_item' ), 30, 3 );
			add_filter( 'YayCurrency/StoreCurrency/ByCartItem/GetProductPrice', array( $this, 'yay_pricing_get_product_price_default_by_cart_item' ), 10, 2 );
		}

		// WC Price History. Link plugin: https://github.com/kkarpieszuk/wc-price-history
		if ( defined( 'WC_PRICE_HISTORY_VERSION' ) ) {
			add_filter( 'wc_price_history_lowest_price_html_raw_value_taxed', array( $this, 'convert_wc_price_history_lowest_price' ), 10, 2 );
			add_filter( 'wc_price_history_variations_add_history_lowest_price', array( $this, 'convert_wc_price_history_variations_add_history_lowest_price' ), 10, 4 );
		}
		// BACKEND REPORT

		// Users Insights. Link plugin: https://usersinsights.com/
		if ( class_exists( 'USIN_Manager' ) ) {
			add_filter( 'usin_users_raw_data', array( $this, 'yay_insights_filter_raw_db_data' ), PHP_INT_MAX );
		}
	}

	// ======= Cache Plugins ============

	// WP Grid Builder Caching.
	public function bypass_grid_builder_caching( $is_bypass, $attrs ) {
		return true;
	}

	// ======= Payment Plugins ============

	// Revolut Gateway for WooCommerce.
	public function yay_revolut_gateway_convert_currency( $currency, $is_dis_checkout_diff_currency ) {
		if ( $is_dis_checkout_diff_currency ) {
			$revolut_action_args = array( 'wc_revolut_create_order', 'wc_revolut_process_payment_result' );
			if ( wp_doing_ajax() && isset( $_REQUEST['wc-ajax'] ) && in_array( $_REQUEST['wc-ajax'], $revolut_action_args, true ) ) {
				$currency = Helper::default_currency_code();
			}
		}

		return $currency;
	}

	// AG WooCommerce Tyl By NatWest Payment Gateway.
	public function yay_agtyl_convert_to_default_currency( $currency, $is_dis_checkout_diff_currency ) {
		if ( $is_dis_checkout_diff_currency || doing_action( 'woocommerce_receipt_ag_tyl_checkout' ) ) {
			$currency = Helper::default_currency_code();
		}

		return $currency;
	}

	public function yay_agtyl_convert_woocommerce_order_get_total( $order_total, $order ) {

		if ( 'ag_tyl_checkout' === $order->get_payment_method() && doing_action( 'woocommerce_receipt_ag_tyl_checkout' ) ) {
			$order_id       = $order->get_id();
			$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order_id );

			if ( ! $order_currency ) {
				return $order_total;
			}

			// Convert to Order Total Default Currency
			$order_total = $order_total / YayCurrencyHelper::get_rate_fee( $order_currency );
		}

		return $order_total;

	}

	// Tabby Payment.
	public function yay_tabby_get_woocommerce_currency( $currency ) {
		$apply_currency = YayCurrencyHelper::detect_current_currency();
		if ( isset( $apply_currency['currency'] ) ) {
			return $apply_currency['currency'];
		}
		return $currency;
	}

	// WooCommerce Pay For Payment.
	public function pay4pay_custom_fee( $fee ) {
		$apply_currency = YayCurrencyHelper::detect_current_currency();
		if ( is_checkout() && ( 0 === intval( get_option( 'yay_currency_checkout_different_currency', 0 ) ) || 0 === intval( $apply_currency['status'] ) ) ) {
			return $fee;
		}

		$fee = YayCurrencyHelper::calculate_price_by_currency( $fee, true, $apply_currency );

		return $fee;

	}

	// Rapyd Payment Gateway.
	public function wc_rapyd_custom_get_order_total( $total, $order ) {
		$get_total = YayCurrencyHelper::get_total_by_order( $order );
		if ( ! empty( $get_total ) ) {
			return $get_total;
		}
		return $total;
	}

	// ======= Shipping Plugins ============

	// Advanced Flat Rate Shipping For WooCommerce Pro.
	public function yay_afrsm_custom_new_total( $new_total ) {
		$apply_currency = YayCurrencyHelper::detect_current_currency();
		$rate_fee       = YayCurrencyHelper::get_rate_fee( $apply_currency );
		if ( $rate_fee > 0 ) {
			$new_total = $new_total / $rate_fee;
		}
		return $new_total;
	}

	// Flexible Shipping.
	public function custom_flexible_shipping_fee( $fee ) {
		$apply_currency = YayCurrencyHelper::detect_current_currency();
		$fee            = YayCurrencyHelper::calculate_price_by_currency( $fee, true, $apply_currency );
		return $fee;
	}

	//Shipmondo.
	public function shipmondo_shipping_instance_option( $value, $key, $shipping ) {

		if ( is_admin() || ! in_array( $key, array( 'free_shipping_total' ) ) ) {
			return $value;
		}

		$apply_currency = YayCurrencyHelper::detect_current_currency();

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $apply_currency ) ) {
			return $value;
		}

		$value = YayCurrencyHelper::calculate_price_by_currency( $value, false, $apply_currency );
		return $value;

	}

	// WooCommerce Table Rate Shipping.
	public function yay_wc_table_rate_shipping_plugin_row_base_price( $row_base_price, $_product, $qty ) {
		$row_base_price = $_product->get_data()['price'] * $qty;
		return $row_base_price;
	}

	// ======= Recalculate Price Plugins ============

	// YayPricing.
	public function yay_pricing_get_product_price_by_cart_item( $price, $cart_item, $apply_currency ) {

		if ( isset( $cart_item['modifiers'] ) && ! empty( $cart_item['modifiers'] ) ) {
			if ( isset( $cart_item['yaydp_custom_data']['price'] ) && ! empty( $cart_item['yaydp_custom_data']['price'] ) ) {
				$price = YayCurrencyHelper::calculate_price_by_currency( $cart_item['yaydp_custom_data']['price'], false, $apply_currency );
			}
		}

		return $price;
	}

	public function yay_pricing_get_product_price_default_by_cart_item( $price, $cart_item ) {

		if ( isset( $cart_item['modifiers'] ) && ! empty( $cart_item['modifiers'] ) ) {
			if ( isset( $cart_item['yaydp_custom_data']['price'] ) && ! empty( $cart_item['yaydp_custom_data']['price'] ) ) {
				$price = $cart_item['yaydp_custom_data']['price'];
			}
		}

		return $price;
	}

	// WC Price History.
	public function convert_wc_price_history_lowest_price( $lowest_price, $product ) {
		if ( is_admin() ) {
			return $lowest_price;
		}
		return apply_filters( 'yay_currency_convert_price', $lowest_price );
	}

	public function convert_wc_price_history_variations_add_history_lowest_price( $lowest_price, $variation_attributes, $product_variable, $variation ) {
		return apply_filters( 'yay_currency_convert_price', $lowest_price );
	}

	// Users Insights.
	protected function yay_insights_get_order_total_by_user_id( $user_id = false ) {
		$order_total = 0;
		if ( $user_id ) {
			global $wpdb;
			if ( Helper::check_custom_orders_table_usage_enabled() ) {
				$user_orders = $wpdb->get_results(
					$wpdb->prepare( "SELECT posts.id AS order_id, posts.customer_id AS user_id FROM {$wpdb->prefix}wc_orders AS posts WHERE posts.customer_id = %s AND posts.type = 'shop_order' AND posts.status IN ( 'wc-completed','wc-processing' )", $user_id )
				);
			} else {
				$user_orders = $wpdb->get_results(
					$wpdb->prepare( "SELECT meta.post_id AS order_id, meta.meta_value AS user_id FROM {$wpdb->prefix}posts AS posts  LEFT JOIN {$wpdb->prefix}postmeta AS meta ON posts.ID = meta.post_id WHERE meta.meta_key = '_customer_user' AND meta.meta_value = %s AND posts.post_type = 'shop_order' AND posts.post_status IN ( 'wc-completed','wc-processing' )", $user_id )
				);
			}
			if ( $user_orders ) {
				foreach ( $user_orders as $value ) {
					$order = wc_get_order( $value->order_id );
					if ( $order ) {
						$order_user_total = $order->get_total();
						if ( Helper::default_currency_code() !== $order->get_currency() ) {
							$order_rate = Helper::get_yay_currency_order_rate( $value->order_id, $order );
							if ( $order_rate ) {
								$order_user_total = $order_user_total / $order_rate;
							}
						}
						$order_total += $order_user_total;
					}
				}
			}
		}
		return YayCurrencyHelper::format_price_currency( $order_total );
	}

	public function yay_insights_filter_raw_db_data( $data ) {
		if ( $data ) {
			foreach ( $data as $key => $value ) {
				if ( isset( $data[ $key ]->lifetime_value ) ) {
					$data[ $key ]->lifetime_value = self::yay_insights_get_order_total_by_user_id( $value->ID );
				}
			}
		}
		return $data;
	}
}
