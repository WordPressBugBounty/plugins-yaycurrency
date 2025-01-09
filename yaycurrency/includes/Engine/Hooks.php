<?php
namespace Yay_Currency\Engine;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;

class Hooks {
	use SingletonTrait;

	public function __construct() {
		// GET CURRENCY RATE
		add_filter( 'yay_currency_rate', array( $this, 'get_yay_currency_rate' ), 10, 1 );
		add_filter( 'yay_currency_order_rate', array( $this, 'get_yay_currency_order_rate' ), 10, 3 );

		// GET SHIPPING DATA
		add_filter( 'yay_currency_get_data_info_from_shipping_method', array( $this, 'get_data_info_from_shipping_method' ), 10, 4 );

		// DETECT BLOCK CONVERT PRICE
		add_filter( 'yay_currency_detect_block_convert_price', array( $this, 'detect_block_convert_price' ), 10, 1 );

		// ADD FILTER PRIORITY
		add_filter( 'yay_currency_filters_priority', array( $this, 'get_filters_priority' ), 9, 1 );

		// NOTICE HTML CHECKOUT PAYMENT METHODS
		add_filter( 'yay_currency_notice_checkout_payment_methods', array( $this, 'get_notice_checkout_payment_methods' ), 10, 3 );

		// GET PRICE FORMAT BY APPLY CURRENCY
		add_filter( 'yay_currency_get_price_format', array( $this, 'get_price_format_by_currency' ), 9999, 1 );

		add_filter( 'yay_currency_get_cart_item_price', array( $this, 'get_cart_item_price' ), 10, 3 );
		add_filter( 'yay_currency_get_cart_subtotal', array( $this, 'get_cart_subtotal' ), 10, 2 );
		add_filter( 'yay_currency_get_cart_subtotal_default', array( $this, 'calculate_cart_subtotal_default' ), 10, 1 );
		add_filter( 'yay_currency_get_discount_total', array( $this, 'calculate_discount_total' ), 10, 2 );
		add_filter( 'yay_currency_get_shipping_total', array( $this, 'calculate_shipping_total' ), 10, 3 );

		add_filter( 'yay_currency_get_fee_total', array( $this, 'calculate_fee_total' ), 10, 2 );
		add_filter( 'yay_currency_get_total_tax', array( $this, 'calculate_total_tax' ), 10, 2 );
		add_filter( 'yay_currency_get_cart_total', array( $this, 'calculate_cart_total' ), 10, 3 );

		// ADD FILTER GET PRICE WITH CONDITIONS
		add_filter( 'yay_currency_get_price_with_conditions', array( $this, 'get_price_with_conditions' ), 10, 3 );
		// ADD FILTER GET PRICE EXCEPT CLASS PLUGINS
		add_filter( 'yay_currency_get_price_except_class_plugins', array( $this, 'get_price_except_class_plugins' ), 10, 3 );

		add_filter( 'woocommerce_stripe_request_body', array( $this, 'custom_stripe_request_total_amount' ), 10, 2 );
		add_filter( 'yay_currency_stripe_request_amount', array( $this, 'custom_stripe_request_amount' ), 10, 3 );

		// Keep original fee
		add_filter( 'yay_currency_is_cart_fees_original', array( $this, 'is_cart_fees_original' ), 10, 2 );

		add_filter( 'yay_currency_is_original_format_order_item_totals', array( $this, 'is_original_format_order_item_totals' ), 10, 4 );

		// Action
		add_action( 'yay_currency_redirect_to_url', array( $this, 'yay_currency_redirect_to_url' ), 10, 2 );
		add_action( 'yay_currency_admin_enqueue_scripts', array( $this, 'yay_currency_admin_enqueue_scripts' ) );

		// RELATE WITH MANUAL ORDER
		add_action( 'yay_currency_handle_manual_order_line_items', array( $this, 'handle_manual_order_line_items' ), 10, 3 );
		add_action( 'yay_currency_handle_manual_order_fee_lines', array( $this, 'handle_manual_order_fee_lines' ), 10, 3 );
		add_action( 'yay_currency_handle_manual_order_shipping_lines', array( $this, 'handle_manual_order_shipping_lines' ), 10, 3 );
		add_action( 'yay_currency_handle_manual_order_tax_lines', array( $this, 'handle_manual_order_tax_lines' ), 10, 3 );
		add_action( 'yay_currency_handle_manual_order_coupon_lines', array( $this, 'handle_manual_order_coupon_lines' ), 10, 3 );
		add_action( 'yay_currency_handle_manual_order_totals', array( $this, 'handle_manual_order_totals' ), 10, 3 );
		add_action( 'yay_currency_handle_manual_set_order_data', array( $this, 'handle_manual_set_order_data' ), 10, 3 );

		// CALCULATE PRICE
		add_filter( 'yay_currency_convert_price', array( $this, 'convert_price_callback' ), 10, 2 );
		add_filter( 'yay_currency_revert_price', array( $this, 'revert_price_callback' ), 10, 2 );
	}

	public function get_yay_currency_rate( $rate = 1 ) {
		$apply_currency = YayCurrencyHelper::detect_current_currency();
		$rate           = YayCurrencyHelper::get_rate_fee( $apply_currency );
		return $rate;
	}

	public function get_yay_currency_order_rate( $order_id, $rate_fee, $converted_currency ) {
		$converted_currency = $converted_currency ? $converted_currency : YayCurrencyHelper::converted_currency();
		$order              = wc_get_order( $order_id );

		if ( ! $order ) {
			return 1;
		}

		$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order_id, $converted_currency );

		if ( ! $order_currency ) {
			$rate_fee = YayCurrencyHelper::get_rate_fee_from_currency_not_exists_in_list( $order->get_currency() );
			return $rate_fee ? $rate_fee : 1;
		}

		if ( Helper::check_custom_orders_table_usage_enabled() ) {
			$rate_fee = $order->get_meta( 'yay_currency_order_rate', true ) ? (float) $order->get_meta( 'yay_currency_order_rate', true ) : false;
		} else {
			$rate_fee = get_post_meta( $order_id, 'yay_currency_order_rate', true ) ? (float) get_post_meta( $order_id, 'yay_currency_order_rate', true ) : false;
		}

		return $rate_fee ? $rate_fee : YayCurrencyHelper::get_rate_fee( $order_currency );
	}


	public function get_data_info_from_shipping_method( $data, $method_id, $package_content_cost, $apply_currency ) {
		$methods = array( 'slovakparcelservice_pickupplace', 'slovakparcelservice_address' );
		if ( in_array( $method_id, $methods, true ) && isset( $data['free'] ) && ! empty( $data['free'] ) && is_numeric( $data['free'] ) ) {
			$free = YayCurrencyHelper::calculate_price_by_currency( $data['free'], true, $apply_currency );
			if ( $package_content_cost >= $free ) {
				$data['cost'] = 0;
			}
		}
		return $data;
	}

	public function detect_block_convert_price( $flag ) {
		// Detect Mobile App
		if ( isset( $_SERVER['HTTP_BAGGAGE'] ) ) {
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) && ! empty( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : false;
			if ( $user_agent && ( stripos( $user_agent, 'wc-ios' ) !== false || stripos( $user_agent, 'wc-android' ) !== false ) ) {
				$flag = true;
			}
		}
		return $flag;
	}

	public function get_filters_priority( $priority ) {

		// Compatible with B2B Wholesale Suite, Price by Country, B2BKing
		if ( class_exists( 'B2bwhs' ) || class_exists( 'CBP_Country_Based_Price' ) || class_exists( 'B2bkingcore' ) ) {
			$priority = 100000;
		}

		return $priority;

	}

	public function get_cart_item_price( $product_price, $cart_item, $apply_currency ) {
		$product_price = SupportHelper::calculate_product_price_by_cart_item( $cart_item, $apply_currency );
		return $product_price;
	}

	public function get_cart_subtotal( $subtotal, $apply_currency ) {
		$subtotal = apply_filters( 'yay_currency_get_cart_subtotal_3rd_plugin', $subtotal, $apply_currency );
		if ( $subtotal ) {
			return $subtotal;
		}
		$subtotal = SupportHelper::calculate_cart_subtotal( $apply_currency );
		return $subtotal;
	}

	public function calculate_cart_subtotal_default( $subtotal ) {
		$subtotal = SupportHelper::get_cart_subtotal_default();
		return $subtotal;
	}

	public function calculate_discount_total( $discount_total, $apply_currency = false ) {
		$cart_subtotal  = $apply_currency ? apply_filters( 'yay_currency_get_cart_subtotal', 0, $apply_currency ) : apply_filters( 'yay_currency_get_cart_subtotal_default', 0 );
		$discount_total = SupportHelper::get_total_coupons( $cart_subtotal, $apply_currency );
		return $discount_total;
	}

	public function calculate_shipping_total( $shipping_total, $apply_currency, $is_fallback = false ) {
		$calculate_default = $apply_currency ? false : true;
		$shipping_total    = SupportHelper::get_shipping_total_selected( $apply_currency, $calculate_default, false, $is_fallback );
		return $shipping_total;
	}

	public function calculate_fee_total( $fee_total, $apply_currency ) {
		$calculate_default = $apply_currency ? false : true;
		$fee_total         = SupportHelper::get_total_fees( $apply_currency, false, $calculate_default );
		return $fee_total;
	}

	public function calculate_total_tax( $total_tax, $apply_currency ) {
		$total_tax = SupportHelper::get_total_tax( $apply_currency );
		return $total_tax;
	}

	public function calculate_cart_total( $total, $apply_currency, $is_fallback ) {
		$total = SupportHelper::get_cart_total( $apply_currency, $is_fallback );
		return $total;
	}

	public function get_price_with_conditions( $price, $product, $apply_currency ) {
		// YayPricing

		$is_ydp_adjust_price = false;
		$calculate_price     = YayCurrencyHelper::calculate_price_by_currency( $price, false, $apply_currency );

		if ( class_exists( '\YayPricing\FrontEnd\ProductPricing' ) ) {
			$is_ydp_adjust_price = apply_filters( 'ydp_check_adjust_price', false );
		}

		if ( class_exists( '\YayPricing\FrontEnd\ProductPricing' ) && $is_ydp_adjust_price ) {
			return $calculate_price;
		}

		$price_3rd_plugin = apply_filters( 'yay_currency_product_price_3rd_with_condition', false, $product );

		return $price_3rd_plugin;

	}

	public function get_price_except_class_plugins( $price, $product, $apply_currency ) {
		if ( class_exists( '\BM_Conditionals' ) ) {
			$group_id = \BM_Conditionals::get_validated_customer_group();
			if ( false !== $group_id ) {
				return $price;
			}
		}
		$calculate_price      = YayCurrencyHelper::calculate_price_by_currency( $price, false, $apply_currency );
		$except_class_plugins = array(
			'WC_Measurement_Price_Calculator',
			'WCPA', // Woocommerce Custom Product Addons
			'\Acowebs\WCPA\Main', // Woocommerce Custom Product Addons
			'WoonpCore', // Name Your Price for WooCommerce
			'Webtomizer\\WCDP\\WC_Deposits', // WooCommerce Deposits
			'\WC_Product_Price_Based_Country', // Price Per Country
			'\JET_APB\Plugin', // Jet Appointments Booking
		);
		$except_class_plugins = apply_filters( 'yay_currency_except_class_plugin', $except_class_plugins );
		foreach ( $except_class_plugins as $class ) {
			if ( class_exists( $class ) ) {
				return $calculate_price;
			}
		}
		return false;
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

	public function is_cart_fees_original( $flag, $apply_currency ) {

		if ( class_exists( 'Woocommerce_Conditional_Product_Fees_For_Checkout_Pro' ) || class_exists( 'TaxamoClass' ) || class_exists( 'WooWallet' ) || function_exists( 'WholeSale_Discount_Based_on_CartTotal' ) ) {
			$flag = false;
		}

		return $flag;
	}

	public function is_original_format_order_item_totals( $flag, $total_rows, $order, $tax_display ) {
		if ( isset( $_GET['action'] ) && 'generate_wpo_wcpdf' === $_GET['action'] ) {
			$flag = true;
		}
		return $flag;
	}

	public function yay_currency_redirect_to_url( $current_url, $currency_id ) {
		$current_currency    = YayCurrencyHelper::get_currency_by_ID( $currency_id );
		$currency_param_name = apply_filters( 'yay_currency_param_name', 'yay-currency' );
		$current_url         = add_query_arg( array( $currency_param_name => $current_currency['currency'] ), $current_url );
		if ( wp_safe_redirect( $current_url ) ) {
			exit;
		}
	}

	public function get_price_format_by_currency( $args ) {

		if ( isset( $args['currency'] ) && ! empty( $args['currency'] ) ) {
			$apply_currency = YayCurrencyHelper::get_currency_by_currency_code( $args['currency'] );

			if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $apply_currency ) ) {
				return $args;
			}

			if ( ! $apply_currency || ! isset( $args['price_format'] ) || ! isset( $apply_currency['currencyPosition'] ) ) {
				return $args;
			}

			$args['price_format'] = YayCurrencyHelper::format_currency_position( $apply_currency['currencyPosition'] );

		}

		return $args;

	}

	public function get_notice_checkout_payment_methods( $notice_html, $currencies_data, $current_theme ) {
		if ( isset( $currencies_data['current_currency'] ) && isset( $currencies_data['fallback_currency'] ) ) {
			if ( Helper::default_currency_code() === $currencies_data['current_currency']['currency'] ) {
				return $notice_html;
			}
			$notice_html = '<div class="yay-currency-checkout-notice user yay-currency-with-' . esc_attr( $current_theme ) . '"><span>' . esc_html__( 'The current payment method for ', 'yay-currency' ) . '<strong>' . wp_kses_post( html_entity_decode( esc_html__( $currencies_data['current_currency']['currency'], 'yay-currency' ) ) ) . '</strong></span><span>' . esc_html__( ' is not supported in your location. ', 'yay-currency' ) . '</span><span>' . esc_html__( 'So your payment will be recorded in ', 'yay-currency' ) . '</span><strong>' . wp_kses_post( html_entity_decode( esc_html__( $currencies_data['fallback_currency']['currency'], 'yay-currency' ) ) ) . '.</strong></span></div>';
			if ( current_user_can( 'manage_options' ) ) {
				$notice_html .= "<div class='yay-currency-checkout-notice-admin yay-currency-with-" . esc_attr( $current_theme ) . "'><span>" . esc_html__( 'Are you the admin? You can change the checkout options for payment methods ', 'yay-currency' ) . '<a href=' . esc_url( admin_url( '/admin.php?page=yay_currency&tabID=1' ) ) . '>' . esc_html__( 'here', 'yay-currency' ) . '</a>.</span><br><span><i>' . esc_html__( '(Only logged in admin can see this.)', 'yay-currency' ) . '</i></span></div>';
			}
		}
		return apply_filters( 'yay_currency_checkout_notice_html', $notice_html, $currencies_data, $current_theme );
	}

	public function yay_currency_admin_enqueue_scripts() {

		$sync_notice_args = array(
			'reverted'      => get_option( 'yay_currency_orders_synced_to_base', 'no' ),
			'notice_title'  => __( 'YayCurrency database update', 'yay-currency' ),
			'notice_desc'   => __( 'Recommended: You can force a database update for past orders so that the revenue recorded in different currencies will be recorded in your default currency. This action will convert the sales based on the current exchange rate.', 'yay-currency' ),
			'notice_button' => __( 'Convert all orders', 'yay-currency' ),
		);
		$localize_args    = array(
			'sync_orders'     => $sync_notice_args,
			'sync_currencies' => Helper::get_sync_currencies(),
			'nonce'           => wp_create_nonce( 'yay-currency-admin-nonce' ),
		);
		wp_enqueue_script( 'yay-currency-admin-script', YAY_CURRENCY_PLUGIN_URL . 'src/admin/script.js', array( 'jquery' ), YAY_CURRENCY_VERSION, true );
		wp_localize_script(
			'yay-currency-admin-script',
			'yayCurrency_Admin',
			apply_filters( 'yay_currency_admin_localize_args', $localize_args )
		);
	}

	// Action Hook Manual Order

	public function handle_manual_order_line_items( $order, $apply_currency, $parent_rate_fee ) {
		$line_items = $order->get_items( 'line_item' );
		foreach ( $line_items as $item ) {
			$line_subtotal     = (float) ( $item['line_subtotal'] / $parent_rate_fee );
			$line_subtotal_tax = (float) ( $item['line_subtotal_tax'] / $parent_rate_fee );
			$line_total        = (float) ( $item['line_total'] / $parent_rate_fee );
			$line_tax          = (float) ( $item['line_tax'] / $parent_rate_fee );

			$item_subtotal     = YayCurrencyHelper::calculate_price_by_currency( $line_subtotal, false, $apply_currency );
			$item_subtotal_tax = YayCurrencyHelper::calculate_price_by_currency( $line_subtotal_tax, false, $apply_currency );
			$item_total        = YayCurrencyHelper::calculate_price_by_currency( $line_total, false, $apply_currency );
			$item_total_tax    = YayCurrencyHelper::calculate_price_by_currency( $line_tax, false, $apply_currency );

			$item->set_subtotal( $item_subtotal );
			$item->set_subtotal_tax( $item_subtotal_tax );
			$item->set_total( $item_total );
			$item->set_total_tax( $item_total_tax );

			$line_tax_data = $item['line_tax_data'];
			$has_tax       = false;

			if ( $line_tax_data ) {
				if ( isset( $line_tax_data['subtotal'] ) && $line_tax_data['subtotal'] ) {
					$rateId                               = key( $line_tax_data['subtotal'] );
					$line_tax_data['subtotal'][ $rateId ] = $item_subtotal_tax;
					$has_tax                              = true;
				}

				if ( isset( $line_tax_data['total'] ) && $line_tax_data['total'] ) {
					$rateId                            = key( $line_tax_data['total'] );
					$line_tax_data['total'][ $rateId ] = $item_total_tax;
					$has_tax                           = true;
				}

				if ( $has_tax ) {
					$item->set_taxes( $line_tax_data );
				}
			}

			$item->save();

		}

	}

	public function handle_manual_order_fee_lines( $order, $apply_currency, $parent_rate_fee ) {
		$fee_items = $order->get_items( 'fee' );
		foreach ( $fee_items as $fee ) {
			$fee_data = $fee->get_data();

			$fee_total     = (float) ( $fee_data['total'] / $parent_rate_fee );
			$fee_total_tax = (float) ( $fee_data['total_tax'] / $parent_rate_fee );

			$item_total     = YayCurrencyHelper::calculate_price_by_currency( $fee_total, true, $apply_currency );
			$item_total_tax = YayCurrencyHelper::calculate_price_by_currency( $fee_total_tax, true, $apply_currency );

			$taxes = $fee->get_taxes();
			$fee->set_total( $item_total );
			$fee->set_total_tax( $item_total_tax );

			if ( isset( $taxes['total'] ) ) {
				foreach ( $taxes['total'] as $rateId => $tax ) {

					$tax = ! empty( $tax ) ? floatval( $tax ) : false;
					if ( $tax ) {
						$tax_total                 = (float) ( $tax / $parent_rate_fee );
						$taxes['total'][ $rateId ] = YayCurrencyHelper::calculate_price_by_currency( $tax_total, true, $apply_currency );
					}
				}

				$fee->set_taxes( $taxes );
			}

			$fee->save();

		}

	}

	public function handle_manual_order_shipping_lines( $order, $apply_currency, $parent_rate_fee ) {
		$shipping_items = $order->get_items( 'shipping' );
		foreach ( $shipping_items as $shipping ) {
			// custom shipping total tax
			$shipping_taxes = $shipping->get_taxes();
			if ( isset( $shipping_taxes['total'] ) && $shipping_taxes['total'] ) {
				foreach ( $shipping_taxes['total'] as $rateId => $shipping_tax ) {
					$shipping_tax = ! empty( $shipping_tax ) ? floatval( $shipping_tax ) : false;
					if ( $shipping_tax ) {
						$shipping_tax_total                 = (float) ( $shipping_tax / $parent_rate_fee );
						$shipping_taxes['total'][ $rateId ] = YayCurrencyHelper::calculate_price_by_currency( $shipping_tax_total, true, $apply_currency );
					}
				}
				$shipping->set_taxes( $shipping_taxes );
			}
			// custom shipping total
			$shipping_data       = $shipping->get_data();
			$shipping_data_total = (float) ( $shipping_data['total'] / $parent_rate_fee );
			$shipping_total      = YayCurrencyHelper::calculate_price_by_currency( $shipping_data_total, true, $apply_currency );
			$shipping->set_total( $shipping_total );
			$shipping->save();
		}
	}

	public function handle_manual_order_tax_lines( $order, $apply_currency, $parent_rate_fee ) {
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax ) {
			$tax_data                = $tax->get_data();
			$tax_data_total          = (float) ( $tax_data['tax_total'] / $parent_rate_fee );
			$shipping_data_tax_total = (float) ( $tax_data['shipping_tax_total'] / $parent_rate_fee );
			$tax_total               = YayCurrencyHelper::calculate_price_by_currency( $tax_data_total, true, $apply_currency );
			$shipping_tax_total      = YayCurrencyHelper::calculate_price_by_currency( $shipping_data_tax_total, true, $apply_currency );
			$tax->set_tax_total( $tax_total );
			$tax->set_shipping_tax_total( $shipping_tax_total );
			$tax->save();
		}
	}

	public function handle_manual_order_coupon_lines( $order, $apply_currency, $parent_rate_fee ) {
		$coupon_items = $order->get_items( 'coupon' );
		foreach ( $coupon_items as $coupon ) {
			$coupon_total     = (float) ( $coupon->get_discount() / $parent_rate_fee );
			$coupon_tax_total = (float) ( $coupon->get_discount_tax() / $parent_rate_fee );

			$discount     = YayCurrencyHelper::calculate_price_by_currency( $coupon_total, true, $apply_currency );
			$discount_tax = YayCurrencyHelper::calculate_price_by_currency( $coupon_tax_total, true, $apply_currency );
			$coupon->set_discount( $discount );
			$coupon->set_discount_tax( $discount_tax );
			$coupon->save();
		}
	}

	public function handle_manual_order_totals( $order, $apply_currency, $parent_rate_fee ) {
		$order_shipping_total     = (float) ( $order->get_shipping_total() / $parent_rate_fee );
		$order_coupon_total       = (float) ( $order->get_discount_total() / $parent_rate_fee );
		$order_coupon_tax_total   = (float) ( $order->get_discount_tax() / $parent_rate_fee );
		$order_shipping_tax_total = (float) ( $order->get_shipping_tax() / $parent_rate_fee );

		$shipping_total = YayCurrencyHelper::calculate_price_by_currency( $order_shipping_total, true, $apply_currency );
		$discount_total = YayCurrencyHelper::calculate_price_by_currency( $order_coupon_total, true, $apply_currency );
		$discount_tax   = YayCurrencyHelper::calculate_price_by_currency( $order_coupon_tax_total, true, $apply_currency );
		$shipping_tax   = YayCurrencyHelper::calculate_price_by_currency( $order_shipping_tax_total, true, $apply_currency );

		$order_get_total = (float) ( $order->get_total() / $parent_rate_fee );
		$order_total     = YayCurrencyHelper::calculate_price_by_currency( $order_get_total, true, $apply_currency );

		$order->set_shipping_total( $shipping_total );
		$order->set_shipping_tax( $shipping_tax );
		$order->set_discount_total( $discount_total );
		$order->set_discount_tax( $discount_tax );
		$order->set_total( $order_total );
	}

	public function handle_manual_set_order_data( $order, $new_rate_fee, $order_currency_code ) {

		if ( $order_currency_code && ! empty( $order_currency_code ) ) {
			$order->set_currency( $order_currency_code );
		}

		if ( Helper::check_custom_orders_table_usage_enabled() ) {
			$order->update_meta_data( 'yay_currency_order_rate', $new_rate_fee );
		} else {
			$order_id = $order->get_id();
			update_post_meta( $order_id, 'yay_currency_order_rate', $new_rate_fee );
		}

		$order->save();

	}

	public function convert_price_callback( $price = 0, $apply_currency = array() ) {
		$apply_currency = $apply_currency ? $apply_currency : YayCurrencyHelper::detect_current_currency();
		$price          = YayCurrencyHelper::calculate_price_by_currency( $price, false, $apply_currency );
		return $price;
	}

	public function revert_price_callback( $price = 0, $apply_currency = array() ) {
		$price = YayCurrencyHelper::reverse_calculate_price_by_currency( $price, false, $apply_currency );
		return $price;
	}
}
