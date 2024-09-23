<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use WeDevs\Dokan\Cache;

// Dokan Pro
use WeDevs\DokanPro\REST\LogsController;

defined( 'ABSPATH' ) || exit;

class Dokan {
	use SingletonTrait;

	private $default_currency;
	private $converted_currency = array();
	private $apply_currency     = array();
	private $apply_default_currency;

	public function __construct() {

		if ( ! class_exists( 'WeDevs_Dokan' ) ) {
			return;
		}

		$this->default_currency = Helper::default_currency_code();

		$this->converted_currency     = YayCurrencyHelper::converted_currency();
		$this->apply_currency         = YayCurrencyHelper::detect_current_currency();
		$this->apply_default_currency = YayCurrencyHelper::get_default_apply_currency( $this->converted_currency );

		if ( ! $this->apply_currency || ! $this->apply_default_currency ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );

		// CUSTOM PRICE FORMAT TO DEFAULT CURRENCY
		add_filter( 'yay_currency_woocommerce_currency_symbol', array( $this, 'custom_currency_symbol' ), 10, 3 );
		add_filter( 'yay_currency_custom_thousand_separator', array( $this, 'custom_thousand_separator' ), 10, 2 );
		add_filter( 'yay_currency_custom_decimal_separator', array( $this, 'custom_decimal_separator' ), 10, 2 );
		add_filter( 'yay_currency_custom_number_decimal', array( $this, 'custom_number_decimal' ), 10, 2 );
		add_filter( 'yay_currency_custom_price_format', array( $this, 'custom_price_format' ), 10, 2 );

		// KEEP PRICE ON PRODUCTS DASHBOARD
		add_filter( 'yay_currency_is_original_product_price', array( $this, 'is_original_product_price' ), 20, 3 );

		add_filter( 'dokan_seller_total_sales', array( $this, 'custom_dokan_seller_total_sales' ), 10, 2 );

		add_filter( 'dokan_get_formatted_seller_earnings', array( $this, 'custom_dokan_get_seller_earnings' ), 10, 2 );

		add_filter( 'dokan_get_formatted_seller_balance', array( $this, 'custom_dokan_get_formatted_seller_balance' ), 10, 2 );

		add_filter( 'dokan_get_seller_balance', array( $this, 'custom_dokan_get_seller_balance' ), 10, 2 );

		//REPORT : Dashboard Sales this Month Area

		add_filter( 'dokan_reports_get_order_report_query', array( $this, 'custom_dokan_reports_get_order_report_query' ), 10, 1 );
		add_filter( 'dokan_reports_get_order_report_data', array( $this, 'custom_dokan_reports_get_order_report_data' ), 10, 2 );

		add_filter( 'woocommerce_reports_top_earners_order_items', array( $this, 'custom_reports_top_earners_order_items' ), 10, 3 );

		add_filter( 'dokan-seller-dashboard-reports-left-sidebar', array( $this, 'custom_reports_net_sales' ), 10, 1 );

		add_filter( 'yay_currency_report_query_by_currency', array( $this, 'custom_report_query_by_currency' ), 10, 1 );

		/********** FRONTEND AJAX **********/

		// ORDERS DASHBOARD - LITE & PRO
		add_action( 'wp_ajax_yay_custom_earning_from_order_table', array( $this, 'custom_earning_from_order' ) );
		add_action( 'wp_ajax_nopriv_yay_custom_earning_from_order_table', array( $this, 'custom_earning_from_order' ) );

		// PRODUCTS, COUPON DASHBOARD - LITE & PRO
		add_action( 'wp_ajax_yay_dokan_custom_approximately_price', array( $this, 'custom_yay_dokan_approximately_price' ) );
		add_action( 'wp_ajax_nopriv_yay_dokan_custom_approximately_price', array( $this, 'custom_yay_dokan_approximately_price' ) );

		// REPORT DASHBOARD -- PRO
		add_action( 'wp_ajax_yay_dokan_custom_reports_statement', array( $this, 'custom_yay_dokan_reports_statement' ) );
		add_action( 'wp_ajax_nopriv_yay_dokan_custom_reports_statement', array( $this, 'custom_yay_dokan_reports_statement' ) );

		/********** BACKEND AJAX **********/

		// DASHBOARD -- LITE & PRO
		add_action( 'wp_ajax_yay_dokan_admin_custom_dashboard', array( $this, 'custom_admin_dokan_dashboard' ) );

		// REPORT -- PRO
		add_action( 'wp_ajax_yay_dokan_admin_custom_reports', array( $this, 'custom_yay_dokan_admin_reports' ) );
		add_action( 'wp_ajax_yay_dokan_admin_reports_by_year', array( $this, 'custom_yay_dokan_admin_reports_by_year' ) );
		add_action( 'wp_ajax_yay_dokan_admin_custom_reports_logs', array( $this, 'custom_yay_dokan_admin_custom_reports_logs' ) );

		// REFUNDS -- PRO
		add_action( 'wp_ajax_yay_dokan_admin_custom_refund_request', array( $this, 'custom_yay_dokan_admin_refund_request' ) );

		add_filter( 'dokan_get_overview_data', array( $this, 'custom_get_overview_data' ), 10, 5 ); // Custom Hook

	}

	// GET FORMAT PRICE WITH DEFAULT CURRENCY  APPLY FOR FRONTEND
	public function convert_price_to_default_currency( $price ) {
		$price           = YayCurrencyHelper::format_price_currency( $price, $this->apply_default_currency );
		$currency_symbol = YayCurrencyHelper::get_symbol_by_currency_code( $this->default_currency );
		$format          = YayCurrencyHelper::format_currency_symbol( $this->apply_default_currency );
		$formatted_price = sprintf( $format, '<span class="woocommerce-Price-currencySymbol">' . $currency_symbol . '</span>', $price );
		$return          = '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted_price . '</bdi></span>';
		return $return;
	}

	// GET FORMAT PRICE WITH DEFAULT CURRENCY  APPLY FOR FRONTEND
	public function custom_formatted_price_by_currency( $price, $current_currency = false ) {
		$apply_currency  = $current_currency ? $current_currency : $this->apply_currency;
		$price           = YayCurrencyHelper::format_price_currency( $price, $apply_currency );
		$currency_symbol = $apply_currency['symbol'];
		$format          = YayCurrencyHelper::format_currency_symbol( $apply_currency );
		$formatted_price = sprintf( $format, '<span class="woocommerce-Price-currencySymbol">' . $currency_symbol . '</span>', $price );
		$return          = '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted_price . '</bdi></span><span>';
		return $return;
	}

	public function custom_format_sale_price_by_currency( $regular_price, $sale_price, $current_currency = false ) {
		$apply_currency  = $current_currency ? $current_currency : $this->apply_currency;
		$regular_price   = $this->custom_formatted_price_by_currency( $regular_price, $apply_currency );
		$sale_price      = $this->custom_formatted_price_by_currency( $sale_price, $apply_currency );
		$formatted_price = '<del aria-hidden="true">' . $regular_price . '</del> <ins>' . $sale_price . '</ins>';
		return $formatted_price;
	}

	// GET FORMAT PRICE WITH DEFAULT CURRENCY  APPLY FOR ADMIN
	public function get_format_price_by_default_currency( $price, $apply_default_currency = false ) {
		// GET DEFAULT CURRENCY
		$formatted_price = wc_price(
			$price,
			YayCurrencyHelper::get_apply_currency_format_info( $apply_default_currency )
		);
		return $formatted_price;
	}

	public function admin_enqueue_scripts( $page ) {
		if ( 'toplevel_page_dokan' === $page ) {
			$data_localize_script = array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'yay-currency-dokan-admin-nonce' ),
				'admin_url' => admin_url(),
			);
			if ( class_exists( 'Dokan_Pro' ) ) {
				$data_localize_script['dokan_pro'] = true;
			}
			wp_enqueue_script( 'yay-currency-dokan-admin-script', YAY_CURRENCY_PLUGIN_URL . 'src/compatibles/dokan/yay-dokan-admin.js', array(), YAY_CURRENCY_VERSION, true );
			wp_localize_script(
				'yay-currency-dokan-admin-script',
				'yay_dokan_admin_data',
				$data_localize_script
			);
		}
	}

	public function frontend_enqueue_scripts() {
		global $wp;
		$withdraw_limit             = function_exists( 'dokan_get_option' ) ? dokan_get_option( 'withdraw_limit', 'dokan_withdraw', 0 ) : 0;
		$withdraw_limit_convert     = YayCurrencyHelper::calculate_price_by_currency( $withdraw_limit, false, $this->apply_currency );
		$withdraw_limit_by_currency = $this->custom_formatted_price_by_currency( $withdraw_limit_convert, $this->apply_currency );
		$show_approximately_price   = apply_filters( 'yay_dokan_approximately_price', true );
		if ( $withdraw_limit && $this->default_currency !== $this->apply_currency['currency'] && $show_approximately_price ) {
			$withdraw_limit_by_currency = $this->convert_price_to_default_currency( $withdraw_limit ) . YayCurrencyHelper::converted_approximately_html( $withdraw_limit_by_currency );
		}

		$data_localize_script = array(
			'ajax_url'                => admin_url( 'admin-ajax.php' ),
			'nonce'                   => wp_create_nonce( 'yay-currency-dokan-nonce' ),
			'seller_id'               => is_user_logged_in() ? get_current_user_id() : 0,
			'withdraw_limit_currency' => $withdraw_limit_by_currency,
			'withdraw_page'           => isset( $wp->query_vars['withdraw'] ) ? 'yes' : 'no',
		);

		if ( $show_approximately_price ) {
			$data_localize_script['approximately_price'] = 'yes';
		}

		if ( $this->default_currency !== $this->apply_currency['currency'] ) {
			$data_localize_script['default_symbol']          = get_woocommerce_currency_symbol( $this->default_currency );
			$data_localize_script['yay_dokan_regular_price'] = '<strong class="yay-dokan-price-wrapper yay-dokan-regular-price-wrapper"></strong>';
			$data_localize_script['yay_dokan_sale_price']    = '<strong class="yay-dokan-price-wrapper yay-dokan-sale-price-wrapper"></strong>';
		}

		if ( class_exists( 'Dokan_Pro' ) ) {
			$data_localize_script['dokan_pro'] = true;
			if ( isset( $wp->query_vars['reports'] ) && isset( $_REQUEST['chart'] ) && 'sales_statement' === $_REQUEST['chart'] ) {
				$start_date = dokan_current_datetime()->modify( 'first day of this month' )->format( 'Y-m-d' );
				$end_date   = dokan_current_datetime()->format( 'Y-m-d' );

				if ( isset( $_GET['dokan_report_filter'] ) && isset( $_GET['dokan_report_filter_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['dokan_report_filter_nonce'] ) ), 'dokan_report_filter' ) && isset( $_GET['start_date_alt'] ) && isset( $_GET['end_date_alt'] ) ) {
					$start_date = dokan_current_datetime()
						->modify( sanitize_text_field( wp_unslash( $_GET['start_date_alt'] ) ) )
						->format( 'Y-m-d' );
					$end_date   = dokan_current_datetime()
						->modify( sanitize_text_field( wp_unslash( $_GET['end_date_alt'] ) ) )
						->format( 'Y-m-d' );
				}
				$data_localize_script['yay_dokan_report_statement_page'] = true;
				$data_localize_script['yay_dokan_report_statement_from'] = $start_date;
				$data_localize_script['yay_dokan_report_statement_to']   = $end_date;
				$vendor          = dokan()->vendor->get( dokan_get_current_user_id() );
				$opening_balance = $vendor->get_balance( false, gmdate( 'Y-m-d', strtotime( $start_date . ' -1 days' ) ) );
				$data_localize_script['yay_dokan_report_statement_opening_balance'] = $opening_balance ? 'yes' : 'no';

			}
		}

		if ( isset( $wp->query_vars['withdraw'] ) ) {
			$last_withdraw = dokan()->withdraw->get_withdraw_requests( dokan_get_current_user_id(), 1, 1 );
			if ( ! empty( $last_withdraw ) ) {
				$amount                         = isset( $last_withdraw[0]->amount ) ? $last_withdraw[0]->amount : $last_withdraw[0]->get_data()['amount'];
				$last_payment_withdraw_currency = $this->convert_price_to_default_currency( $amount );
				if ( $this->default_currency !== $this->apply_currency['currency'] ) {
					$last_payment_by_currency = YayCurrencyHelper::calculate_price_by_currency( $amount, false, $this->apply_currency );
					$last_payment_by_currency = $this->custom_formatted_price_by_currency( $last_payment_by_currency, $this->apply_currency );
					if ( $show_approximately_price ) {
						$last_payment_withdraw_currency = $last_payment_withdraw_currency . YayCurrencyHelper::converted_approximately_html( $last_payment_by_currency );
					}
				}

				$date                      = isset( $last_withdraw[0]->date ) ? $last_withdraw[0]->date : $last_withdraw[0]->get_data()['date'];
				$method                    = isset( $last_withdraw[0]->method ) ? $last_withdraw[0]->method : $last_withdraw[0]->get_data()['method'];
				$last_withdraw_date        = '<strong><em>' . dokan_format_date( $date ) . '</em></strong>';
				$last_withdraw_method_used = '<strong>' . dokan_withdraw_get_method_title( $method ) . '</strong>';

				$payment_details = '<strong>' . $last_payment_withdraw_currency . '</strong> on ' . $last_withdraw_date . ' to ' . $last_withdraw_method_used;

				$data_localize_script['last_payment_details'] = '<strong>' . esc_html__( 'Last Payment', 'dokan-lite' ) . '</strong><br>' . $payment_details;
			}
		}

		if ( isset( $wp->query_vars['coupons'] ) && $this->default_currency !== $this->apply_currency['currency'] ) {
			$data_localize_script['yay_dokan_coupon_area']   = true;
			$data_localize_script['yay_dokan_coupon_amount'] = '<strong class="yay-dokan-coupon-amount-wrapper"></strong>';
		}

		wp_enqueue_script( 'yay-currency-dokan-script', YAY_CURRENCY_PLUGIN_URL . 'src/compatibles/dokan/yay-dokan.js', array(), YAY_CURRENCY_VERSION, true );
		wp_localize_script(
			'yay-currency-dokan-script',
			'yay_dokan_data',
			$data_localize_script
		);

		wp_enqueue_style(
			'yay-currency-dokan-frontend-style',
			YAY_CURRENCY_PLUGIN_URL . 'src/compatibles/dokan/yay-dokan.css',
			array(),
			YAY_CURRENCY_VERSION
		);
	}

	public function get_order_currency_by_dokan_order_details() {
		global $wp;
		$order_currency = false;
		if ( isset( $wp->query_vars['orders'] ) && isset( $_REQUEST['order_id'] ) ) {
			$order_id       = sanitize_key( $_REQUEST['order_id'] );
			$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order_id, $this->converted_currency );
		}
		return $order_currency;
	}

	public function is_dokan_special_pages( $wp ) {
		$pages = array( 'reports' );

		$flag = false;
		foreach ( $pages as $page ) {
			if ( isset( $wp->query_vars[ $page ] ) ) {
				$flag = true;
				break;
			}
		}

		return $flag;

	}

	public function custom_currency_symbol( $symbol, $currency, $apply_currency ) {
		global $wp;

		if ( class_exists( 'Dokan_Pro' ) && doing_action( 'dokan_dashboard_right_widgets' ) ) {
			return $symbol;
		}

		$order_currency = $this->get_order_currency_by_dokan_order_details();

		if ( $order_currency && isset( $order_currency['currency'] ) ) {
			$symbol = YayCurrencyHelper::get_symbol_by_currency_code( $order_currency['currency'] );
			return $symbol;
		}

		if ( isset( $wp->query_vars['pagename'] ) && 'dashboard' === $wp->query_vars['pagename'] ) {

			if ( ! $this->is_dokan_special_pages( $wp ) ) {
				$symbol = YayCurrencyHelper::get_symbol_by_currency_code( $this->default_currency );
			}
		}

		return $symbol;
	}

	public function custom_thousand_separator( $thousand_separator, $apply_currency ) {
		global $wp;

		if ( isset( $wp->query_vars['pagename'] ) && 'dashboard' === $wp->query_vars['pagename'] ) {
			if ( ! $this->is_dokan_special_pages( $wp ) ) {
				$thousand_separator = isset( $this->apply_default_currency['thousandSeparator'] ) ? $this->apply_default_currency['thousandSeparator'] : $thousand_separator;
			}
		}

		return $thousand_separator;

	}

	public function custom_decimal_separator( $decimal_separator, $apply_currency ) {
		global $wp;

		if ( isset( $wp->query_vars['pagename'] ) && 'dashboard' === $wp->query_vars['pagename'] ) {
			if ( ! $this->is_dokan_special_pages( $wp ) ) {
				$decimal_separator = isset( $this->apply_default_currency['decimalSeparator'] ) ? $this->apply_default_currency['decimalSeparator'] : $decimal_separator;
			}
		}

		return $decimal_separator;

	}

	public function custom_number_decimal( $number_decimal, $apply_currency ) {
		global $wp;

		if ( isset( $wp->query_vars['pagename'] ) && 'dashboard' === $wp->query_vars['pagename'] ) {
			if ( ! $this->is_dokan_special_pages( $wp ) ) {
				$number_decimal = isset( $this->apply_default_currency['numberDecimal'] ) ? $this->apply_default_currency['numberDecimal'] : $number_decimal;
			}
		}

		return $number_decimal;

	}

	public function custom_price_format( $format, $apply_currency ) {
		global $wp;

		$order_currency = $this->get_order_currency_by_dokan_order_details();
		if ( $order_currency ) {
			$format = YayCurrencyHelper::format_currency_symbol( $order_currency );
			return $format;
		}

		if ( isset( $wp->query_vars['pagename'] ) && 'dashboard' === $wp->query_vars['pagename'] ) {
			if ( ! $this->is_dokan_special_pages( $wp ) ) {
				$format = YayCurrencyHelper::format_currency_symbol( $this->apply_default_currency );
			}
		}

		return $format;
	}

	public function is_original_product_price( $flag, $price, $product ) {
		global $wp;
		if ( isset( $wp->query_vars['products'] ) ) {
			$flag = true;
		}

		return $flag;
	}

	public function calculate_price_apply_currency_from_order_currency( $price, $order_currency, $only_get_price_default = false ) {

		if ( $this->default_currency !== $order_currency['currency'] ) {
			$rate_fee = YayCurrencyHelper::get_rate_fee( $order_currency );
			$price    = $price / $rate_fee;
		}

		return $only_get_price_default ? $price : YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
	}

	// GET ALL ORDERS BY SELLER ID
	public function get_dokan_orders_by_seller_id( $seller_id = 0 ) {
		global $wpdb;
		$dokan_orders = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dokan_orders WHERE seller_id = %d AND order_status IN('wc-completed', 'wc-processing', 'wc-on-hold')", $seller_id )
		);
		return $dokan_orders;
	}

	public function filter_by_order_status( $data, $order_status = array() ) {
		$data = array_filter(
			$data,
			function ( $value, $key ) use ( $order_status ) {
				return in_array( $value->status, $order_status );
			},
			ARRAY_FILTER_USE_BOTH
		);
		return $data;
	}

	// GET EARNING BY SELLER_ID
	public function get_earning_by_seller_id( $seller_id, $balance_date, $only_get_price_default = false ) {
		$earning  = 0;
		$balances = $this->get_balance_by_seller_id( $seller_id, $balance_date, 'earning' );
		foreach ( $balances as $balance ) {
			$balance_debit = $this->convert_value_by_order_id( $balance->debit, $balance->trn_id, $only_get_price_default );
			if ( -1 === $balance_debit ) {
				continue;
			}
			$earning += $balance_debit;
		}
		return $earning;
	}
	// GET CREDIT BY SELLER_ID : WITHDRAW AUTO IS DEFAULT CURRENCY
	public function get_withdraw_by_seller_id( $seller_id, $balance_date ) {
		global $wpdb;
		$withdraw = 0;
		$results  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT dok_vendor.*
            FROM {$wpdb->prefix}dokan_vendor_balance as dok_vendor
            WHERE vendor_id = %d AND DATE(balance_date) <= %s and credit > %d",
				$seller_id,
				$balance_date,
				0
			)
		);
		foreach ( $results as $value ) {
			if ( 'dokan_refund' === $value->trn_type ) {
				$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $value->trn_id, $this->converted_currency );
				if ( ! $order_currency ) {
					continue;
				}
				$withdraw -= (float) $value->credit / YayCurrencyHelper::get_rate_fee( $order_currency );
			} else {
				$withdraw += $value->credit;
			}
		}
		return $withdraw;

	}

	public function get_balance_by_seller_id( $seller_id, $on_date, $type = 'debit' ) {
		global $wpdb;
		$status = dokan_withdraw_get_active_order_status();
		switch ( $type ) {
			case 'debit':
				$results  = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dokan_vendor_balance WHERE vendor_id = %d AND DATE(balance_date) <= %s AND trn_type = %s", $seller_id, $on_date, 'dokan_orders' )
				);
				$balances = $this->filter_by_order_status( $results, $status );
				break;
			case 'credit':
				$balances = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dokan_vendor_balance WHERE vendor_id = %d AND DATE(balance_date) <= %s AND trn_type = %s AND status = %s", $seller_id, $on_date, 'dokan_refund', 'approved' )
				);
				break;
			default: // earning
				$results  = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dokan_vendor_balance WHERE vendor_id = %d AND DATE(balance_date) <= %s", $seller_id, $on_date )
				);
				$balances = $this->filter_by_order_status( $results, $status );
				break;
		}

		return $balances;
	}

	public function convert_value_by_order_id( $value = 0, $order_id = 0, $only_get_price_default = false ) {
		$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order_id, $this->converted_currency );
		if ( ! $order_currency ) {
			return -1;
		}
		return $this->calculate_price_apply_currency_from_order_currency( $value, $order_currency, $only_get_price_default );
	}

	public function get_debit_balance( $seller_id, $balance_date ) {
		$debit    = 0;
		$balances = $this->get_balance_by_seller_id( $seller_id, $balance_date, 'debit' );
		foreach ( $balances as $balance ) {
			$balance_debit = $this->convert_value_by_order_id( $balance->debit, $balance->trn_id, true );
			if ( -1 === $balance_debit ) {
				continue;
			}
			$debit += $balance_debit;
		}
		return $debit;
	}

	public function get_credit_balance( $seller_id, $balance_date ) {
		$credit   = 0;
		$balances = $this->get_balance_by_seller_id( $seller_id, $balance_date, 'credit' );
		foreach ( $balances as $balance ) {
			$balance_credit = $this->convert_value_by_order_id( $balance->credit, $balance->trn_id, true );
			if ( -1 === $balance_credit ) {
				continue;
			}
			$credit += $balance_credit;
		}
		return $credit;
	}

	// Custom Net Sales by apply currency ---(Dashboard)
	public function custom_dokan_seller_total_sales( $net_sales, $seller_id ) {
		$dokan_orders = $this->get_dokan_orders_by_seller_id( $seller_id );
		$net_sales    = 0;
		foreach ( $dokan_orders as $dokan_order ) {
			if ( ! isset( $dokan_order->net_amount ) || ! isset( $dokan_order->order_id ) ) {
				return;
			}
			$net_amount = $this->convert_value_by_order_id( $dokan_order->net_amount, $dokan_order->order_id, true );
			if ( -1 === intval( $net_amount ) || empty( $net_amount ) || ! $net_amount ) {
				continue;
			}
			$net_sales += $net_amount;
		}

		return $net_sales;
	}

	// Custom Earning by apply currency ---(Dashboard)
	public function custom_dokan_get_seller_earnings( $earning, $seller_id ) {
		$on_date        = dokan_current_datetime()->format( 'Y-m-d H:i:s' );
		$debit_balance  = $this->get_debit_balance( $seller_id, $on_date );
		$credit_balance = $this->get_credit_balance( $seller_id, $on_date );

		$earning = floatval( $debit_balance - $credit_balance );

		return $this->custom_formatted_price_by_currency( $earning, $this->apply_default_currency );

	}

	public function custom_dokan_get_formatted_seller_balance( $earning, $seller_id ) {
		$on_date          = dokan_current_datetime()->format( 'Y-m-d H:i:s' );
		$withdraw         = $this->get_withdraw_by_seller_id( $seller_id, $on_date );
		$earning          = $this->get_earning_by_seller_id( $seller_id, $on_date, true );
		$balance          = $earning - $withdraw;
		$balance_convert  = YayCurrencyHelper::calculate_price_by_currency( $balance, false, $this->apply_currency );
		$balance_currency = $this->custom_formatted_price_by_currency( $balance_convert, $this->apply_currency );

		if ( $this->default_currency !== $this->apply_currency['currency'] ) {
			$balance_currency = $this->convert_price_to_default_currency( $balance );
			if ( apply_filters( 'yay_dokan_approximately_price', true ) ) {
				$balance_convert_currency = $this->custom_formatted_price_by_currency( $balance_convert, $this->apply_currency );
				$balance_currency        .= YayCurrencyHelper::converted_approximately_html( $balance_convert_currency );
			}
		}

		return $balance_currency;
	}

	public function custom_dokan_get_seller_balance( $earning, $seller_id ) {
		global $wp;
		if ( isset( $wp->query_vars['reports'] ) ) {
			$start_date = dokan_current_datetime()->modify( 'first day of this month' )->format( 'Y-m-d' );
			if ( isset( $_GET['dokan_report_filter'] ) && isset( $_GET['dokan_report_filter_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['dokan_report_filter_nonce'] ) ), 'dokan_report_filter' ) && isset( $_GET['start_date_alt'] ) && isset( $_GET['end_date_alt'] ) ) {
				$start_date = dokan_current_datetime()
					->modify( sanitize_text_field( wp_unslash( $_GET['start_date_alt'] ) ) )
					->format( 'Y-m-d' );
			}
			$on_date = gmdate( 'Y-m-d H:i:s', strtotime( $start_date . ' -1 days' ) );
		} else {
			$on_date = dokan_current_datetime();
			$on_date = $on_date->format( 'Y-m-d H:i:s' );
		}

		$earning  = $this->get_earning_by_seller_id( $seller_id, $on_date, true );
		$withdraw = $this->get_withdraw_by_seller_id( $seller_id, $on_date, true );

		$seller_balance = $earning - $withdraw;
		return $seller_balance;
	}

	public function custom_dokan_reports_get_order_report_query( $query ) {
		global $wp;
		if ( ! isset( $wp->query_vars['reports'] ) && isset( $wp->query_vars['pagename'] ) && 'dashboard' === $wp->query_vars['pagename'] ) {
			$query['select'] = 'SELECT meta__order_total.*, post_date';
			if ( isset( $query['group_by'] ) ) {
				unset( $query['group_by'] );
			}
		}

		if ( isset( $wp->query_vars['reports'] ) ) {

			if ( 'SELECT SUM( meta__order_total.meta_value) as total_sales,COUNT(DISTINCT posts.ID) as total_orders, posts.post_date as post_date' === $query['select'] ) {
				$query['select'] = 'SELECT meta__order_total.*, post_date';
				if ( isset( $query['group_by'] ) ) {
					unset( $query['group_by'] );
				}
			}
		}

		// Delete transient ( Avoid cache)
		$cache_key    = 'wc_report_' . md5( 'get_results' . implode( ' ', $query ) );
		$current_user = dokan_get_current_user_id();
		Cache::delete_transient( $cache_key, "report_data_seller_{$current_user}" );

		// Custom again Query
		return $query;
	}

	public function custom_report_chart( $rows, $data ) {
		$query = array();
		foreach ( $rows as $key => $value ) {

			$date        = gmdate( 'Y-m-d', strtotime( $value->post_date ) );
			$total_sales = $this->convert_value_by_order_id( (float) $value->meta_value, $value->post_id, true );

			if ( -1 === $total_sales ) {
				continue;
			}

			if ( ! isset( $query[ $date ] ) ) {

				$query[ $date ] = (object) array(
					'total_sales'  => $total_sales,
					'total_orders' => 1,
					'post_date'    => $value->post_date,
				);
			} else {
				$query[ $date ]->total_sales  = $query[ $date ]->total_sales + $total_sales;
				$query[ $date ]->total_orders = $query[ $date ]->total_orders + 1;
			}
		}
		$query = (object) $query;
		return $query;
	}

	public function custom_report_by_sales_shipping( $seller_id, $start_date, $end_date ) {

		$rows           = $this->get_data_report_total_sales_total_shipping( $seller_id, $start_date, $end_date );
		$total_sales    = 0;
		$total_shipping = 0;
		$total_orders   = 0;
		if ( $rows ) {
			foreach ( $rows as $key => $value ) {

				$order_total_sales    = $this->convert_value_by_order_id( (float) $value->sales, $value->order_id, true );
				$order_total_shipping = $this->convert_value_by_order_id( (float) $value->shipping, $value->order_id, true );
				if ( -1 === $order_total_sales && -1 === $order_total_shipping ) {
					continue;
				}

				$total_sales    += $order_total_sales;
				$total_shipping += $order_total_shipping;
				++$total_orders;

			}
		}

		$data = (object) array(
			'total_sales'    => $total_sales,
			'total_shipping' => $total_shipping,
			'total_orders'   => $total_orders,
		);
		return $data;
	}

	public function custom_report_total_refund( $seller_id, $start_date, $end_date ) {
		global $wpdb;
		$total_refund = 0;
		$results      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT dr.order_id, dr.refund_amount FROM {$wpdb->posts} AS posts
					INNER JOIN $wpdb->dokan_refund AS dr ON posts.ID = dr.order_id
					WHERE posts.post_type = %s AND posts.post_status != %s
						AND dr.status = %d AND seller_id = %d AND DATE(post_date) >= %s AND DATE(post_date) <= %s",
				'shop_order',
				'trash',
				1,
				$seller_id,
				$start_date,
				$end_date
			)
		);

		foreach ( $results as $value ) {
			$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $value->order_id, $this->converted_currency );
			if ( ! $order_currency ) {
				continue;
			}
			$total_refund += $this->calculate_price_apply_currency_from_order_currency( (float) $value->refund_amount, $order_currency, true );
		}
		return $total_refund;
	}

	public function custom_report_by_coupons( $seller_id, $start_date, $end_date ) {
		$rows          = $this->get_data_report_total_coupons( $seller_id, $start_date, $end_date );
		$total_coupons = 0;
		if ( $rows ) {
			foreach ( $rows as $value ) {
				$order_id       = wc_get_order_id_by_order_item_id( $value->order_item_id );
				$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order_id, $this->converted_currency );
				if ( ! $order_currency ) {
					continue;
				}
				$total_coupons += $this->calculate_price_apply_currency_from_order_currency( (float) $value->meta_value, $order_currency, true );
			}
		}
		return $total_coupons;
	}

	public function get_data_report_total_sales_total_shipping( $seller_id = false, $start_date = '', $end_date = '' ) {
		global $wpdb;
		$data_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta__order_total.meta_value as sales,meta__order_shipping.meta_value as shipping, posts.ID as order_id FROM {$wpdb->prefix}posts AS posts LEFT JOIN {$wpdb->prefix}dokan_orders AS do ON posts.ID = do.order_id LEFT JOIN {$wpdb->prefix}postmeta AS meta__order_total ON posts.ID = meta__order_total.post_id LEFT JOIN {$wpdb->prefix}postmeta AS meta__order_shipping ON posts.ID = meta__order_shipping.post_id 
				WHERE   posts.post_type     = 'shop_order'
				AND     posts.post_status   != 'trash'
				AND     do.seller_id = %d
				AND     do.order_status IN ('wc-completed','wc-processing','wc-on-hold','wc-refunded')
				AND     do.order_status NOT IN ('wc-cancelled','wc-failed')
				
					AND     DATE(post_date) >= %s
					AND     DATE(post_date) <= %s
				 AND meta__order_total.meta_key = '_order_total' AND meta__order_shipping.meta_key = '_order_shipping'",
				$seller_id,
				$start_date,
				$end_date
			)
		);
		return $data_rows;
	}

	public function get_data_report_total_coupons( $seller_id = false, $start_date = '', $end_date = '' ) {
		global $wpdb;
		$data_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT order_item_meta_discount_amount.order_item_id, order_item_meta_discount_amount.meta_value FROM {$wpdb->prefix}posts AS posts LEFT JOIN {$wpdb->prefix}dokan_orders AS do ON posts.ID = do.order_id LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS order_items ON posts.ID = order_items.order_id LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta_discount_amount ON order_items.order_item_id = order_item_meta_discount_amount.order_item_id 
				WHERE   posts.post_type     = 'shop_order'
				AND     posts.post_status   != 'trash'
				AND     do.seller_id = %d
				AND     do.order_status IN ('wc-completed','wc-processing','wc-on-hold','wc-refunded')
				AND     do.order_status NOT IN ('wc-cancelled','wc-failed')
				
					AND     DATE(post_date) >= %s
					AND     DATE(post_date) <= %s
				 AND order_items.order_item_type = 'coupon' AND order_item_meta_discount_amount.meta_key = 'discount_amount' AND order_item_type = 'coupon'",
				$seller_id,
				$start_date,
				$end_date
			)
		);
		return $data_rows;
	}

	public function custom_dokan_reports_get_order_report_data( $rows, $data ) {
		global $wp;
		if ( ! isset( $wp->query_vars['reports'] ) && isset( $wp->query_vars['pagename'] ) && 'dashboard' === $wp->query_vars['pagename'] ) {
			return $this->custom_report_chart( $rows, $data );
		}

		if ( isset( $wp->query_vars['reports'] ) ) {

			if ( isset( $data['_order_total'] ) && isset( $data['ID'] ) ) {
				if ( isset( $data['post_date'] ) ) {
					return $this->custom_report_chart( $rows, $data );
				}
			}
		}

		return $rows;
	}

	public function custom_reports_top_earners_order_items( $order_items, $start_date, $end_date ) {
		global $wp;

		if ( isset( $wp->query_vars['reports'] ) ) {
			global $wpdb;
			$seller_id             = dokan_get_current_user_id();
			$withdraw_order_status = dokan_get_option( 'withdraw_order_status', 'dokan_withdraw', array( 'wc-completed' ) );
			$withdraw_order_status = apply_filters( 'woocommerce_reports_order_statuses', $withdraw_order_status );
			$order_items           = $wpdb->get_results(
				$wpdb->prepare(
					" SELECT order_items.order_id, order_item_meta_2.meta_value as product_id, order_item_meta.meta_value as line_total,do.net_amount as total_earning, do.order_status
					FROM {$wpdb->prefix}woocommerce_order_items as order_items
					LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
					LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_2 ON order_items.order_item_id = order_item_meta_2.order_item_id
					LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
					LEFT JOIN {$wpdb->prefix}dokan_orders AS do ON posts.ID = do.order_id
					WHERE   posts.post_type     = 'shop_order'
					AND     posts.post_status   != 'trash'
					AND     do.seller_id = %s
					AND     post_date > %s
					AND     post_date < %s
					AND     order_items.order_item_type = 'line_item'
					AND     order_item_meta.meta_key = '_line_total'
					AND     order_item_meta_2.meta_key = '_product_id'
					
					",
					$seller_id,
					$start_date->format( 'Y-m-d' ),
					$end_date->format( 'Y-m-d' )
				)
			);
			$args_order_items      = array();
			foreach ( $order_items as $item ) {

				if ( ! in_array( $item->order_status, array_values( $withdraw_order_status ) ) ) {
					continue;
				}

				$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $item->order_id, $this->converted_currency );
				if ( ! $order_currency ) {
					continue;
				}

				$line_total    = $this->calculate_price_apply_currency_from_order_currency( (float) $item->line_total, $order_currency );
				$total_earning = $this->calculate_price_apply_currency_from_order_currency( (float) $item->total_earning, $order_currency );
				if ( ! isset( $args_order_items[ $item->product_id ] ) ) {
						$args_order_items[ $item->product_id ] = (object) array(
							'product_id'    => $item->product_id,
							'line_total'    => $line_total,
							'total_earning' => $total_earning,
						);
				} else {
					$args_order_items[ $item->product_id ]->line_total    = $args_order_items[ $item->product_id ]->line_total + $line_total;
					$args_order_items[ $item->product_id ]->total_earning = $args_order_items[ $item->product_id ]->total_earning + $total_earning;
				}
			}

			return $args_order_items;
		}

		return $order_items;
	}

	public function custom_reports_net_sales( $data ) {
		$seller_id  = dokan_get_current_user_id();
		$start_date = dokan_current_datetime()->modify( 'first day of this month' )->format( 'Y-m-d' );
		$end_date   = dokan_current_datetime()->format( 'Y-m-d' );

		// TOP SELL BY DAY
		if ( isset( $_POST['dokan_report_filter'] ) && isset( $_POST['dokan_report_filter_daily_sales_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dokan_report_filter_daily_sales_nonce'] ) ), 'dokan_report_filter_daily_sales' ) && isset( $_POST['start_date_alt'] ) && isset( $_POST['end_date_alt'] ) ) {
			$start_date = dokan_current_datetime()
					->modify( sanitize_text_field( wp_unslash( $_POST['start_date_alt'] ) ) )
					->format( 'Y-m-d' );
			$end_date   = dokan_current_datetime()
					->modify( sanitize_text_field( wp_unslash( $_POST['end_date_alt'] ) ) )
					->format( 'Y-m-d' );
		}

		// TOP SELLING
		if ( isset( $_POST['dokan_report_filter_top_seller'] ) && isset( $_POST['dokan_report_filter_top_seller_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dokan_report_filter_top_seller_nonce'] ) ), 'dokan_report_filter_top_seller' ) && isset( $_POST['start_date_alt'] ) && isset( $_POST['end_date_alt'] ) ) {
			$start_date = dokan_current_datetime()
					->modify( sanitize_text_field( wp_unslash( $_POST['start_date_alt'] ) ) )
					->format( 'Y-m-d' );
			$end_date   = dokan_current_datetime()
					->modify( sanitize_text_field( wp_unslash( $_POST['end_date_alt'] ) ) )
					->format( 'Y-m-d' );
		}
		$order_totals   = $this->custom_report_by_sales_shipping( $seller_id, $start_date, $end_date );
		$total_sales    = $order_totals->total_sales;
		$total_shipping = $order_totals->total_shipping;
		$num_of_days    = (int) gmdate( 'd' );
		$average_sales  = $total_sales / $num_of_days;
		$total_refunded = $this->custom_report_total_refund( $seller_id, $start_date, $end_date );
		$total_coupons  = $this->custom_report_by_coupons( $seller_id, $start_date, $end_date );

		$data['sales_in_this_period']['title']     = '<strong>' . wc_price( $total_sales ) . '</strong> ' . __( 'sales in this period', 'dokan' );
		$data['net_sales_in_this_period']['title'] = '<strong>' . wc_price( $total_sales - $total_refunded ) . '</strong> ' . __( 'net sales', 'dokan' );
		$data['average_daily_sales']['title']      = '<strong>' . wc_price( $average_sales ) . '</strong> ' . __( 'average daily sales', 'dokan' );
		$data['charged_for_shipping']['title']     = '<strong>' . wc_price( $total_shipping ) . '</strong> ' . __( 'charged for shipping', 'dokan' );
		$data['worth_of_coupons_used']['title']    = '<strong>' . wc_price( $total_coupons ) . '</strong> ' . __( 'worth of coupons used', 'dokan' );

		return $data;
	}

	public function custom_report_query_by_currency( $currency ) {
		if ( ! is_admin() ) {
			$currency = isset( $this->apply_currency['currency'] ) && ! empty( $this->apply_currency['currency'] ) ? $this->apply_currency['currency'] : $this->default_currency;
		}
		return $currency;
	}

	// ADMIN AJAX

	public function get_all_data_by_month( $start_date, $end_date, $seller_id = false ) {
		global $wpdb;
		if ( ! $seller_id ) {
			$data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT do.net_amount, do.order_total, p.ID as order_id, p.post_date as order_date
			FROM {$wpdb->prefix}dokan_orders do LEFT JOIN $wpdb->posts p ON do.order_id = p.ID
			AND DATE(p.post_date) >= %s AND DATE(p.post_date) <= %s
			WHERE
				seller_id != 0 AND
				p.post_status != 'trash' AND
				do.order_status IN ('wc-on-hold', 'wc-completed', 'wc-processing')
				 AND DATE(p.post_date) >= %s AND DATE(p.post_date) <= %s",
					$start_date,
					$end_date,
					$start_date,
					$end_date
				)
			);
		} else {
			$data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT do.net_amount, do.order_total, p.ID as order_id, p.post_date as order_date
			FROM {$wpdb->prefix}dokan_orders do LEFT JOIN $wpdb->posts p ON do.order_id = p.ID
			AND DATE(p.post_date) >= %s AND DATE(p.post_date) <= %s
			WHERE
				seller_id = %d AND
				p.post_status != 'trash' AND
				do.order_status IN ('wc-on-hold', 'wc-completed', 'wc-processing')
				 AND DATE(p.post_date) >= %s AND DATE(p.post_date) <= %s",
					$start_date,
					$end_date,
					$seller_id,
					$start_date,
					$end_date
				)
			);
		}

		return $data;
	}

	public function get_data_earning_order_total_by_month( $all_data_by_month ) {
		$data = array();
		foreach ( $all_data_by_month as $value ) {
			$date        = gmdate( 'Y-m-d', strtotime( $value->order_date ) );
			$order_total = $this->convert_value_by_order_id( (float) $value->order_total, $value->order_id, true );
			$net_amount  = $this->convert_value_by_order_id( (float) $value->net_amount, $value->order_id, true );
			if ( -1 === $order_total && -1 === $net_amount ) {
				continue;
			}
			$earning = $order_total - $net_amount;
			if ( ! isset( $data[ $date ] ) ) {
				$data[ $date ] = (object) array(
					'earning'      => $earning,
					'order_total'  => $order_total,
					'total_orders' => 1,
					'order_date'   => $value->order_date,
				);
			} else {
				$data[ $date ]->earning      = $data[ $date ]->earning + $earning;
				$data[ $date ]->order_total  = $data[ $date ]->order_total + $order_total;
				$data[ $date ]->total_orders = $data[ $date ]->total_orders + 1;
			}
		}
		return $data;
	}

	public function get_this_month_report_data() {
		$data                = array();
		$now                 = dokan_current_datetime();
		$start_date          = $now->modify( 'first day of this month' )->format( 'Y-m-d' );
		$end_date            = $now->format( 'Y-m-d' );
		$all_data_this_month = $this->get_all_data_by_month( $start_date, $end_date );
		if ( ! $all_data_this_month ) {
			return $data;
		}
		$data = $this->get_data_earning_order_total_by_month( $all_data_this_month );
		return $data;
	}

	public function get_last_month_report_data() {
		$now                 = dokan_current_datetime();
		$start_date          = $now->modify( 'first day of previous month' )->format( 'Y-m-d' );
		$end_date            = $now->modify( 'last day of previous month' )->format( 'Y-m-d' );
		$all_data_last_month = $this->get_all_data_by_month( $start_date, $end_date );
		$data                = array();
		if ( ! $all_data_last_month ) {
			return $data;
		}
		$data = $this->get_data_earning_order_total_by_month( $all_data_last_month );
		return $data;
	}

	public function get_all_data_reports( $from = false, $to = false, $seller_id = false ) {
		// THIS MONTH
		$this_month_report_data   = $this->get_this_month_report_data();
		$this_month_order_total   = 0;
		$this_month_earning_total = 0;
		$this_month_total_orders  = 0;

		if ( $this_month_report_data ) {
			foreach ( $this_month_report_data as $row ) {
				$this_month_order_total   += $row->order_total;
				$this_month_earning_total += $row->earning;
				$this_month_total_orders  += $row->total_orders;
			}
		}
		// LAST MONTH
		$last_month_report_data   = $this->get_last_month_report_data();
		$last_month_order_total   = 0;
		$last_month_earning_total = 0;
		$last_month_total_orders  = 0;

		if ( $last_month_report_data ) {
			foreach ( $last_month_report_data as $row ) {
				$last_month_order_total   += $row->order_total;
				$last_month_earning_total += $row->earning;
				$last_month_total_orders  += $row->total_orders;
			}
		}

		$this_month_order_total_html   = $this->get_format_price_by_default_currency( $this_month_order_total, $this->apply_default_currency );
		$last_month_order_total_html   = $this->get_format_price_by_default_currency( $last_month_order_total, $this->apply_default_currency );
		$this_month_earning_total_html = $this->get_format_price_by_default_currency( $this_month_earning_total, $this->apply_default_currency );
		$last_month_earning_total_html = $this->get_format_price_by_default_currency( $last_month_earning_total, $this->apply_default_currency );

		if ( $from && $to ) {
			$date             = dokan_prepare_date_query( $from, $to );
			$this_period_data = $this->get_all_data_by_month( $from, $to, $seller_id );
			if ( $this_period_data ) {
				$this_period_data = $this->get_data_earning_order_total_by_month( $this_period_data );
			}
			$last_period_data = $this->get_all_data_by_month( $date['last_from_full_date'], $date['last_to_full_date'], $seller_id );
			if ( $last_period_data ) {
				$last_period_data = $this->get_data_earning_order_total_by_month( $last_period_data );
			}

			$this_period_order_total   = 0;
			$this_period_earning_total = 0;
			$this_period_total_orders  = 0;
			$last_period_order_total   = 0;
			$last_period_earning_total = 0;
			$last_period_total_orders  = 0;

			if ( $this_period_data ) {
				foreach ( $this_period_data as $row ) {
					$this_period_order_total   += $row->order_total;
					$this_period_earning_total += $row->earning;
					$this_period_total_orders  += $row->total_orders;
				}
			}

			if ( $last_period_data ) {
				foreach ( $last_period_data as $row ) {
					$last_period_order_total   += $row->order_total;
					$last_period_earning_total += $row->earning;
					$last_period_total_orders  += $row->total_orders;
				}
			}

			$this_period_order_total_html   = $this->get_format_price_by_default_currency( $this_period_order_total, $this->apply_default_currency );
			$this_period_total_orders_html  = $this->get_format_price_by_default_currency( $this_period_total_orders, $this->apply_default_currency );
			$this_period_earning_total_html = $this->get_format_price_by_default_currency( $this_period_earning_total, $this->apply_default_currency );

			$sale_percentage    = dokan_get_percentage_of( $this_period_order_total, $last_period_order_total );
			$earning_percentage = dokan_get_percentage_of( $this_period_earning_total, $last_period_earning_total );
			$order_percentage   = dokan_get_percentage_of( $this_period_total_orders, $last_period_total_orders );
		} else {
			$sale_percentage    = dokan_get_percentage_of( $this_month_order_total, $last_month_order_total );
			$earning_percentage = dokan_get_percentage_of( $this_month_earning_total, $last_month_earning_total );
			$order_percentage   = dokan_get_percentage_of( $this_month_total_orders, $last_month_total_orders );
		}

		$sales = array(
			'sales'   => array(
				'this_month'  => $this_month_order_total_html,
				'last_month'  => $last_month_order_total_html,
				'this_period' => $from && $to ? $this_period_order_total_html : null,
				'class'       => $sale_percentage['class'],
				'parcent'     => $sale_percentage['parcent'],
			),
			'orders'  => array(
				'this_month'  => $this_month_total_orders,
				'last_month'  => $last_month_total_orders,
				'this_period' => $from && $to ? $this_period_total_orders_html : null,
				'class'       => $order_percentage['class'],
				'parcent'     => $order_percentage['parcent'],
			),
			'earning' => array(
				'this_month'  => $this_month_earning_total_html,
				'last_month'  => $last_month_earning_total_html,
				'this_period' => $from && $to ? $this_period_earning_total_html : null,
				'class'       => $earning_percentage['class'],
				'parcent'     => $earning_percentage['parcent'],
			),
		);
		$data  = array(
			'products' => dokan_get_product_count( $from, $to, $seller_id ),
			'withdraw' => dokan_get_withdraw_count(),
			'vendors'  => dokan_get_seller_count( $from, $to ),
			'sales'    => $sales['sales'],
			'orders'   => $sales['orders'],
			'earning'  => $sales['earning'],
		);
		return $data;
	}

	public function custom_admin_dokan_dashboard() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;

		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-dokan-admin-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}

		$data = $this->get_all_data_reports();

		wp_send_json_success(
			array(
				'report_data' => $data,
			)
		);

	}

	// FRONTEND AJAX
	public function get_order_info_from_order_table( $order_id, $seller_id ) {
		global $wpdb;
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `net_amount`, `order_total`, `order_status` FROM {$wpdb->dokan_orders} WHERE `order_id` = %d and `seller_id` = %d",
				$order_id,
				$seller_id
			)
		);
		return $result;
	}

	public function custom_earning_from_order() {

		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;

		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-dokan-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? intval( sanitize_text_field( $_POST['order_id'] ) ) : false;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Order doesn\'t exist', 'yay-currency' ) ) );
		}

		$seller_id      = isset( $_POST['seller_id'] ) ? intval( sanitize_text_field( $_POST['seller_id'] ) ) : false;
		$result         = $this->get_order_info_from_order_table( $order_id, $seller_id );
		$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order_id, $this->converted_currency );

		if ( ! $result || ! $order_currency ) {
			wp_send_json_error( array( 'message' => __( 'Order doesn\'t exist', 'yay-currency' ) ) );
		}

		$order_total = $result->order_total;
		if ( 'wc-refunded' === $result->order_status ) {
			$order_total = $this->custom_format_sale_price_by_currency( $order_total, 0, $order_currency );
		} else {
			$order_total = $this->custom_formatted_price_by_currency( $order_total, $order_currency );
		}

		wp_send_json_success(
			array(
				'earning'     => $this->custom_formatted_price_by_currency( $result->net_amount, $order_currency ),
				'order_total' => $order_total,
			)
		);

		/*
		$earning     = $this->convert_value_by_order_id( $result->net_amount, $order_id );
		$order_total = $this->convert_value_by_order_id( $result->order_total, $order_id );


		if ( 'wc-refunded' === $result->order_status ) {
			$order_total = YayCurrencyHelper::format_sale_price( $order_total, 0 );
		} else {
			$order_total = YayCurrencyHelper::format_price( $order_total );
		}
		wp_send_json_success(
			array(
				'earning'     => YayCurrencyHelper::format_price( $earning ),
				'order_total' => $order_total,
			)
		);
		*/
	}

	// CALCULATE AGAIN WITH DOKAN PRO

	public function custom_order_shipping_totals( $order, $order_currency ) {
		$shipping_totals     = array();
		$line_items_shipping = $order->get_items( 'shipping' );
		if ( $line_items_shipping ) {
			$check_item = current( $line_items_shipping );
			$tax_data   = maybe_unserialize( isset( $check_item['taxes'] ) ? $check_item['taxes'] : '' );

			if ( wc_tax_enabled() ) {
				$order_taxes  = $order->get_taxes();
				$legacy_order = ! empty( $order_taxes ) && empty( $tax_data ) && ! is_array( $tax_data );
			} else {
				$legacy_order = false;
				$order_taxes  = false;
			}

			foreach ( $line_items_shipping as $item_id => $item ) {
				$line_cost = isset( $item['cost'] ) ? YayCurrencyHelper::format_price( $this->calculate_price_apply_currency_from_order_currency( $item['cost'], $order_currency ) ) : '';
				$refunded  = $order->get_total_refunded_for_item( $item_id, 'shipping' );
				if ( $refunded ) {
					$refunded_html = YayCurrencyHelper::format_price( $this->calculate_price_apply_currency_from_order_currency( $refunded, $order_currency ) );
					$line_cost     = $line_cost . '<small class="refunded">-' . $refunded_html . '</small>';
				}

				$shipping_totals[ $item_id ]['line_cost'] = $line_cost;

				if ( empty( $legacy_order ) && wc_tax_enabled() ) {
					$shipping_taxes = isset( $item['taxes'] ) ? $item['taxes'] : '';
					$tax_data       = maybe_unserialize( $shipping_taxes );

					foreach ( $order_taxes as $tax_item ) {

						$tax_item_id    = $tax_item['rate_id'];
						$tax_item_total = isset( $tax_data['total'][ $tax_item_id ] ) ? $tax_data['total'][ $tax_item_id ] : '';
						$tax_item_total = ! empty( $tax_item_total ) ? YayCurrencyHelper::format_price( $this->calculate_price_apply_currency_from_order_currency( wc_round_tax_total( $tax_item_total ), $order_currency ) ) : '&ndash;';

						$refunded = $order->get_tax_refunded_for_item( $item_id, $tax_item_id, 'shipping' );
						if ( $refunded ) {
							$refunded_html  = YayCurrencyHelper::format_price( $this->calculate_price_apply_currency_from_order_currency( $refunded, $order_currency ) );
							$tax_item_total = $tax_item_total . '<small class="refunded">-' . $refunded_html . '</small>';
						}
						$shipping_totals[ $item_id ]['line_tax'] = $tax_item_total;

					}
				}
			}
		}
		return $shipping_totals;
	}

	public function custom_order_fee_totals( $order, $order_currency ) {
		$line_items_fee = $order->get_items( 'fee' );
		$fee_totals     = array();
		if ( $line_items_fee ) {
			$check_item = current( $line_items_fee );
			$tax_data   = maybe_unserialize( isset( $check_item['line_tax_data'] ) ? $check_item['line_tax_data'] : '' );
			if ( wc_tax_enabled() ) {
				$order_taxes  = $order->get_taxes();
				$legacy_order = ! empty( $order_taxes ) && empty( $tax_data ) && ! is_array( $tax_data );
			} else {
				$legacy_order = false;
				$order_taxes  = false;
			}

			foreach ( $line_items_fee as $item_id => $item ) {
				$line_total = isset( $item['line_total'] ) ? YayCurrencyHelper::format_price( $this->calculate_price_apply_currency_from_order_currency( wc_round_tax_total( $item['line_total'] ), $order_currency ) ) : '';
				$refunded   = $order->get_total_refunded_for_item( $item_id, 'fee' );
				if ( $refunded ) {
					$refunded_html = YayCurrencyHelper::format_price( $this->calculate_price_apply_currency_from_order_currency( $refunded, $order_currency ) );
					$line_total    = $line_total . '<small class="refunded">-' . $refunded_html . '</small>';
				}
				$fee_totals[ $item_id ]['line_cost'] = $line_total;
				if ( empty( $legacy_order ) && wc_tax_enabled() ) {
					$line_tax_data = isset( $item['line_tax_data'] ) ? $item['line_tax_data'] : '';
					$tax_data      = maybe_unserialize( $line_tax_data );

					foreach ( $order_taxes as $tax_item ) {
						$tax_item_id    = $tax_item['rate_id'];
						$tax_item_total = isset( $tax_data['total'][ $tax_item_id ] ) ? $tax_data['total'][ $tax_item_id ] : '';
						$tax_item_total = ! empty( $tax_item_total ) ? YayCurrencyHelper::format_price( $this->calculate_price_apply_currency_from_order_currency( wc_round_tax_total( $tax_item_total ), $order_currency ) ) : '&ndash;';

						$refunded = $order->get_tax_refunded_for_item( $item_id, $tax_item_id, 'fee' );
						if ( $refunded ) {
							$refunded_html  = YayCurrencyHelper::format_price( $this->calculate_price_apply_currency_from_order_currency( $refunded, $order_currency ) );
							$tax_item_total = $tax_item_total . '<small class="refunded">-' . $refunded_html . '</small>';
						}
						$fee_totals[ $item_id ]['line_tax'] = $tax_item_total;
					}
				}
			}
		}

		return $fee_totals;
	}

	public function custom_table_order_totals( $order, $order_currency ) {
		$discount        = $this->calculate_price_apply_currency_from_order_currency( $order->get_total_discount(), $order_currency );
		$shipping        = $this->calculate_price_apply_currency_from_order_currency( $order->get_total_shipping(), $order_currency );
		$order_totals    = array();
		$order_totals[0] = YayCurrencyHelper::format_price( $discount );
		$order_totals[1] = YayCurrencyHelper::format_price( $shipping );
		if ( wc_tax_enabled() ) {
			$id = 2;
			foreach ( $order->get_tax_totals() as $code => $tax_item ) {
				$tax_amount          = $this->calculate_price_apply_currency_from_order_currency( $tax_item->amount, $order_currency );
				$order_totals[ $id ] = YayCurrencyHelper::format_price( $tax_amount );
				++$id;
			}
		}
		return $order_totals;
	}

	public function custom_order_refund_totals( $order, $order_currency ) {
		$refunds       = $order->get_refunds();
		$refund_totals = array();
		if ( $refunds ) {
			foreach ( $refunds as $refund ) {
				$refund_amount                                  = dokan_replace_func( 'get_refund_amount', 'get_amount', $refund );
				$refund_amount                                  = $this->calculate_price_apply_currency_from_order_currency( $refund_amount, $order_currency );
				$order_refund_id                                = dokan_get_prop( $refund, 'id' );
				$refund_totals[ $order_refund_id ]['line_cost'] = '-' . YayCurrencyHelper::format_price( $refund_amount );
			}
			$refund_totals = array(
				'totals'         => $refund_totals,
				'total_refunded' => '-' . YayCurrencyHelper::format_price( $this->calculate_price_apply_currency_from_order_currency( $order->get_total_refunded(), $order_currency ) ),
			);

		}
		return $refund_totals;
	}

	public function is_dokan_edit_order_page() {
		global $wp;
		$flag = false;
		if ( isset( $wp->query_vars['orders'] ) && ( isset( $wp->query_vars['pagename'] ) ) && 'dashboard' === $wp->query_vars['pagename'] ) {
			$flag = true;
		}
		return $flag;
	}

	public function custom_yay_dokan_approximately_price() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;
		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-dokan-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}
		$price = isset( $_POST['_price'] ) ? (float) sanitize_text_field( $_POST['_price'] ) : 0;
		if ( ! $price ) {
			wp_send_json_error();
		}
		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		wp_send_json_success(
			array(
				'price_html' => YayCurrencyHelper::converted_approximately_html( $this->custom_formatted_price_by_currency( $price, $this->apply_currency ) ),
			)
		);
	}

	public function custom_yay_dokan_reports_statement() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;
		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-dokan-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}

		$start_date      = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : false;
		$end_date        = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : false;
		$seller_id       = isset( $_POST['seller_id'] ) ? intval( sanitize_text_field( $_POST['seller_id'] ) ) : false;
		$opening_balance = isset( $_POST['opening_balance'] ) && 'yes' === $_POST['opening_balance'] ? true : false;
		if ( $start_date && $end_date && $seller_id ) {
			global $wpdb;

			$statements   = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT * from {$wpdb->prefix}dokan_vendor_balance WHERE vendor_id = %d AND DATE(balance_date) >= %s AND DATE(balance_date) <= %s AND ( ( trn_type = 'dokan_orders' AND status IN ('wc-refunded', 'wc-completed') ) OR trn_type IN ( 'dokan_withdraw', 'dokan_refund' ) ) ORDER BY balance_date
					",
					$seller_id,
					$start_date,
					$end_date
				)
			);
			$total_debit  = 0;
			$total_credit = 0;
			$balance      = 0;
			$results      = array();
			$index        = $opening_balance ? 1 : 0;
			foreach ( $statements as $key => $statement ) {
				$statement_type = $statement->trn_type;
				if ( in_array( $statement_type, array( 'dokan_orders', 'dokan_refund' ) ) ) {
					$statement_debit  = $this->convert_value_by_order_id( $statement->debit, $statement->trn_id, true );
					$statement_credit = $this->convert_value_by_order_id( $statement->credit, $statement->trn_id, true );
					if ( -1 === $statement_debit && -1 === $statement_credit ) {
						continue;
					}
					$debit  = $statement_debit;
					$credit = $statement_credit;
				} else {
					$debit  = $statement->debit;
					$credit = $statement->credit;
				}
				$total_debit                += $debit;
				$total_credit               += $credit;
				$balance                    += abs( $debit - $credit );
				$debit                       = YayCurrencyHelper::calculate_price_by_currency( $debit, false, $this->apply_currency );
				$credit                      = YayCurrencyHelper::calculate_price_by_currency( $credit, false, $this->apply_currency );
				$results[ $index ]['debit']  = $this->custom_formatted_price_by_currency( $debit, $this->apply_currency );
				$results[ $index ]['credit'] = $this->custom_formatted_price_by_currency( $credit, $this->apply_currency );

				++$index;
			}
			$total_debit  = YayCurrencyHelper::calculate_price_by_currency( $total_debit, false, $this->apply_currency );
			$total_credit = YayCurrencyHelper::calculate_price_by_currency( $total_credit, false, $this->apply_currency );
			$balance      = YayCurrencyHelper::calculate_price_by_currency( $balance, false, $this->apply_currency );
			wp_send_json_success(
				array(
					'statements'    => $results,
					'total_debit'   => $this->custom_formatted_price_by_currency( $total_debit, $this->apply_currency ),
					'total_credit'  => $this->custom_formatted_price_by_currency( $total_credit, $this->apply_currency ),
					'total_balance' => $this->custom_formatted_price_by_currency( $balance, $this->apply_currency ),
				)
			);
		}

		wp_send_json_error();
	}

	// DOKAN PRO
	public function get_store_id_by_name( $store_name ) {
		global $wpdb;
		$vendor = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id as seller_id
            FROM {$wpdb->prefix}usermeta
            WHERE meta_key = %s AND meta_value = %s",
				'dokan_store_name',
				$store_name
			)
		);

		return isset( $vendor->seller_id ) ? $vendor->seller_id : false;
	}

	public function custom_yay_dokan_admin_reports() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;
		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-dokan-admin-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}
		$from      = isset( $_POST['from'] ) ? sanitize_text_field( $_POST['from'] ) : false;
		$to        = isset( $_POST['to'] ) ? sanitize_text_field( $_POST['to'] ) : false;
		$seller_id = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : false;
		if ( $seller_id ) {
			$seller_id = $this->get_store_id_by_name( $seller_id );
		}
		$data = $this->get_all_data_reports( $from, $to, $seller_id );

		wp_send_json_success(
			array(
				'report_data' => $data,
			)
		);
	}

	public function custom_yay_dokan_admin_reports_by_year() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;
		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-dokan-admin-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}

		$year = isset( $_POST['_year'] ) ? sanitize_text_field( $_POST['_year'] ) : false;
		if ( ! $year ) {
			wp_send_json_error( array( 'message' => __( 'Year invalid', 'yay-currency' ) ) );
		}
		$from      = $year . '-01-01';
		$to        = $year . '-12-31';
		$seller_id = isset( $_POST['seller_id'] ) ? (int) sanitize_text_field( $_POST['seller_id'] ) : false;
		$data      = $this->get_all_data_reports( $from, $to, $seller_id );
		wp_send_json_success(
			array(
				'report_data' => $data,
			)
		);
	}

	public function get_all_refund_by_status( $order_ids = array(), $status = 0 ) {
		global $wpdb;

		$refunds = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT order_id, refund_amount FROM {$wpdb->prefix}dokan_refund WHERE status=%d",
				$status
			)
		);
		$refunds = array_filter(
			$refunds,
			function ( $value ) use ( $order_ids ) {
				return in_array( $value->order_id, $order_ids );
			}
		);
		return $refunds;
	}

	public function custom_yay_dokan_admin_custom_reports_logs() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;

		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-dokan-admin-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}

		$report_args = isset( $_POST['reportArgs'] ) ? array_map( 'sanitize_text_field', $_POST['reportArgs'] ) : array();

		if ( $report_args ) {
			$vendor_id                = isset( $report_args['vendor_id'] ) && ! empty( $report_args['vendor_id'] ) ? array_map( 'intval', explode( ',', $report_args['vendor_id'] ) ) : array();
			$order_id                 = isset( $report_args['order_id'] ) && ! empty( $report_args['order_id'] ) ? array_map( 'intval', explode( ',', $report_args['order_id'] ) ) : array();
			$page                     = isset( $report_args['page'] ) && ! empty( $report_args['page'] ) ? intval( $report_args['page'] ) : 1;
			$report_args['vendor_id'] = $vendor_id;
			$report_args['order_id']  = $order_id;
			$report_args['page']      = $page;
			$report_args['return']    = 'ids';
		}

		$logs     = new LogsController();
		$orderIds = dokan_pro()->reports->get_logs( $report_args );
		$results  = $logs->prepare_logs_data( $orderIds, array() );
		$data     = array();

		if ( $results ) {
			foreach ( $results as $value ) {
				$order_id       = intval( $value['order_id'] );
				$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order_id, $this->converted_currency );

				if ( ! $order_currency ) {
					continue;
				}

				$data[ $order_id ] = array(
					'order_total'        => $this->custom_formatted_price_by_currency( $value['order_total'], $order_currency ),
					'vendor_earning'     => $this->custom_formatted_price_by_currency( $value['vendor_earning'], $order_currency ),
					'commission'         => $this->custom_formatted_price_by_currency( $value['commission'], $order_currency ),
					'dokan_gateway_fee'  => $this->custom_formatted_price_by_currency( $value['dokan_gateway_fee'], $order_currency ),
					'shipping_total'     => $this->custom_formatted_price_by_currency( $value['shipping_total'], $order_currency ),
					'shipping_total_tax' => $this->custom_formatted_price_by_currency( $value['shipping_total_tax'], $order_currency ),
					'tax_total'          => $this->custom_formatted_price_by_currency( $value['tax_total'], $order_currency ),
				);

			}
		}

		wp_send_json_success(
			array(
				'reports_logs' => $data,
			)
		);
	}

	public function custom_yay_dokan_admin_refund_request() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;

		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-dokan-admin-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}

		$status    = isset( $_POST['status'] ) ? (int) sanitize_text_field( $_POST['status'] ) : 0;
		$order_ids = isset( $_POST['orderIds'] ) ? array_map( 'sanitize_text_field', $_POST['orderIds'] ) : array();

		$refunds = $this->get_all_refund_by_status( $order_ids, $status );

		if ( ! $refunds ) {
			wp_send_json_error( array( 'message' => __( 'No request found', 'yay-currency' ) ) );
		}

		$data = array();

		foreach ( $refunds as $refund ) {
			$order_id       = $refund->order_id;
			$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order_id, $this->converted_currency );
			if ( ! $order_currency ) {
				continue;
			}

			$data[ $order_id ] = $this->custom_formatted_price_by_currency( $refund->refund_amount, $order_currency );
		}

		wp_send_json_success(
			array(
				'refunds' => $data,
			)
		);
	}

	public function get_all_orders_by_date( $start_date, $end_date, $seller_id = 0 ) {

		global $wpdb;
		if ( ! $seller_id || empty( $seller_id ) ) {
			$data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT do.order_id,do.seller_id,do.order_total,do.net_amount,p.date_created_gmt as order_date FROM {$wpdb->prefix}dokan_orders do
					LEFT JOIN {$wpdb->prefix}wc_orders as p ON do.order_id = p.id
					WHERE seller_id != 0 AND p.status != 'trash' AND do.order_status IN ('wc-on-hold', 'wc-completed', 'wc-processing') AND DATE(p.date_created_gmt) >= %s AND DATE(p.date_created_gmt) <= %s",
					$start_date,
					$end_date
				)
			);
		} else {
			$data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT do.order_id,do.seller_id,do.order_total,do.net_amount,p.date_created_gmt as order_date FROM {$wpdb->prefix}dokan_orders do
					LEFT JOIN {$wpdb->prefix}wc_orders as p ON do.order_id = p.id
					WHERE seller_id = %d AND p.status != 'trash' AND do.order_status IN ('wc-on-hold', 'wc-completed', 'wc-processing') AND DATE(p.date_created_gmt) >= %s AND DATE(p.date_created_gmt) <= %s",
					$seller_id,
					$start_date,
					$end_date
				)
			);
		}

		return $data;

	}

	public function custom_get_overview_data( $data, $group_by, $start_date, $end_date, $seller_id ) {
		$start_date        = ! empty( $start_date ) ? sanitize_text_field( $start_date ) : '';
		$end_date          = ! empty( $end_date ) ? sanitize_text_field( $end_date ) : '';
		$all_data_by_month = $this->get_all_data_by_month( $start_date, $end_date, $seller_id );
		$data              = $this->get_data_earning_order_total_by_month( $all_data_by_month );
		return $data;
	}
}
