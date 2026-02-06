<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use WeDevs\Dokan\Cache;
use Automattic\WooCommerce\Utilities\NumberUtil;

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

		$this->default_currency       = Helper::default_currency_code();
		$this->converted_currency     = YayCurrencyHelper::converted_currency();
		$this->apply_currency         = YayCurrencyHelper::detect_current_currency();
		$this->apply_default_currency = YayCurrencyHelper::get_default_apply_currency( $this->converted_currency );

		if ( ! $this->apply_currency || ! $this->apply_default_currency ) {
			return;
		}

		// Caching
		add_action( 'yay_currency_allow_detect_caching', array( $this, 'allow_detect_caching' ), 10, 1 );
		add_filter( 'yay_currency_localize_args', array( $this, 'add_localize_args' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );

		// CUSTOM PRICE FORMAT TO DEFAULT CURRENCY
		add_filter( 'yay_currency_woocommerce_currency_symbol', array( $this, 'custom_currency_symbol' ), 10, 3 );
		add_filter( 'yay_currency_get_price_format', array( $this, 'get_price_format' ), 10, 1 );
		add_filter( 'woocommerce_price_format', array( $this, 'change_price_format' ), 9999, 2 );
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

		add_filter( 'YayCurrency/Admin/ReportQuery/GetCurrencyCode', array( $this, 'custom_report_query_by_currency' ), 10, 1 );

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
		add_action( 'wp_ajax_yay_dokan_custom_approved_withdraw_request', array( $this, 'custom_approved_withdraw_request' ) );
		add_action( 'wp_ajax_nopriv_yay_dokan_custom_approved_withdraw_request', array( $this, 'custom_approved_withdraw_request' ) );
		add_action( 'wp_ajax_yay_dokan_custom_cancelled_withdraw_request', array( $this, 'custom_cancelled_withdraw_request' ) );
		add_action( 'wp_ajax_nopriv_yay_dokan_custom_cancelled_withdraw_request', array( $this, 'custom_cancelled_withdraw_request' ) );
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

		// Custom WholeSale
		add_filter( 'dokan_product_wholesale_price_html', array( $this, 'dokan_product_wholesale_price_html' ), 10, 1 ); // Custom WhoSale Price HTML

		add_filter( 'dokan_rest_prepare_withdraw_object', array( $this, 'dokan_rest_prepare_withdraw_object' ), 10, 3 );

	}

	public function allow_detect_caching( $flag ) {
		if ( self::detect_dokan_pages( 'dashboard' ) ) {
			$flag = false;
		}
		return $flag;
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

			$suffix = defined( 'YAY_CURRENCY_SCRIPT_DEBUG' ) ? '' : '.min';

			wp_enqueue_script( 'yay-currency-dokan-admin-script', YAY_CURRENCY_PLUGIN_URL . 'src/compatibles/dokan/yay-dokan-admin' . $suffix . '.js', array(), YAY_CURRENCY_VERSION, true );
			wp_localize_script(
				'yay-currency-dokan-admin-script',
				'yay_dokan_admin_data',
				$data_localize_script
			);
		}
	}

	public function add_localize_args( $localize_args ) {
		if ( ! isset( $localize_args['shortCode'] ) ) {
			$localize_args['shortCode'] = do_shortcode( '[yaycurrency-menu-item-switcher]' );
		}
		return $localize_args;
	}

	public function frontend_enqueue_scripts() {
		$withdraw_limit             = function_exists( 'dokan_get_option' ) ? dokan_get_option( 'withdraw_limit', 'dokan_withdraw', 0 ) : 0;
		$withdraw_limit_convert     = YayCurrencyHelper::calculate_price_by_currency( $withdraw_limit, false, $this->apply_currency );
		$withdraw_limit_by_currency = YayCurrencyHelper::formatted_price_by_currency( $withdraw_limit_convert, $this->apply_currency );
		$show_approximately_price   = apply_filters( 'yay_dokan_approximately_price', true );
		if ( $withdraw_limit && $this->default_currency !== $this->apply_currency['currency'] && $show_approximately_price ) {
			$withdraw_limit_by_currency = YayCurrencyHelper::formatted_price_by_currency( $withdraw_limit, $this->apply_default_currency ) . YayCurrencyHelper::converted_approximately_html( $withdraw_limit_by_currency );
		}

		$data_localize_script = array(
			'ajax_url'                => admin_url( 'admin-ajax.php' ),
			'nonce'                   => wp_create_nonce( 'yay-currency-dokan-nonce' ),
			'seller_id'               => is_user_logged_in() ? get_current_user_id() : 0,
			'withdraw_limit_currency' => $withdraw_limit_by_currency,
		);

		if ( self::detect_dokan_pages( 'withdraw-requests' ) ) {
			$data_localize_script['withdraw_approved_requests_page']  = isset( $_GET['type'] ) && 'approved' === $_GET['type'] ? 'yes' : 'no';
			$data_localize_script['withdraw_cancelled_requests_page'] = isset( $_GET['type'] ) && 'cancelled' === $_GET['type'] ? 'yes' : 'no';
		}

		if ( $show_approximately_price ) {
			$data_localize_script['approximately_price'] = 'yes';
		}

		if ( self::detect_dokan_pages( 'dashboard' ) ) {
			$data_localize_script['dashboard_page'] = 'yes';
		}

		if ( $this->default_currency !== $this->apply_currency['currency'] ) {
			$data_localize_script['default_symbol']          = get_woocommerce_currency_symbol( $this->default_currency );
			$data_localize_script['yay_dokan_regular_price'] = '<strong class="yay-dokan-price-wrapper yay-dokan-regular-price-wrapper"></strong>';
			$data_localize_script['yay_dokan_sale_price']    = '<strong class="yay-dokan-price-wrapper yay-dokan-sale-price-wrapper"></strong>';
		}

		if ( class_exists( 'Dokan_Pro' ) ) {
			$data_localize_script['dokan_pro'] = true;
			if ( self::detect_dokan_pages( 'reports' ) && isset( $_REQUEST['chart'] ) && 'sales_statement' === $_REQUEST['chart'] ) {
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

		if ( self::detect_dokan_pages( 'withdraw' ) ) {
			$last_withdraw = dokan()->withdraw->get_withdraw_requests( dokan_get_current_user_id(), 1, 1 );
			$last_withdraw = isset( $last_withdraw[0] ) ? $last_withdraw[0] : false;
			if ( $last_withdraw ) {
				$amount                    = $last_withdraw->get_amount();
				$withdraw_date             = $last_withdraw->get_date();
				$rate_fee_by_last_withdraw = self::get_rate_fee_by_dokan_vendor_balance_by_withdraw( dokan_get_current_user_id(), floatval( $amount ) );

				if ( $rate_fee_by_last_withdraw ) {
					$amount = floatval( $amount / $rate_fee_by_last_withdraw );
					$amount = apply_filters( 'YayCurrency/Dokan/Withdraw/Amount', $amount, $withdraw_date, dokan_get_current_user_id() );
				}

				$last_payment_withdraw_currency = YayCurrencyHelper::formatted_price_by_currency( $amount, $this->apply_default_currency );

				if ( $this->default_currency !== $this->apply_currency['currency'] ) {
					$last_payment_by_currency = YayCurrencyHelper::calculate_price_by_currency( $amount, false, $this->apply_currency );
					$last_payment_by_currency = YayCurrencyHelper::formatted_price_by_currency( $last_payment_by_currency, $this->apply_currency );
					if ( $show_approximately_price ) {
						$last_payment_withdraw_currency = $last_payment_withdraw_currency . YayCurrencyHelper::converted_approximately_html( $last_payment_by_currency );
					}
				}

				$last_withdraw_date        = '<strong><em>' . dokan_format_date( $withdraw_date ) . '</em></strong>';
				$last_withdraw_method_used = '<strong>' . dokan_withdraw_get_method_title( $last_withdraw->get_method() ) . '</strong>';

				$payment_details = '<strong>' . $last_payment_withdraw_currency . '</strong> on ' . $last_withdraw_date . ' to ' . $last_withdraw_method_used;

				$data_localize_script['last_payment_details'] = '<strong>' . esc_html__( 'Last Payment', 'dokan-lite' ) . '</strong><br>' . $payment_details;
			}
		}

		if ( self::detect_dokan_pages( 'coupons' ) && $this->default_currency !== $this->apply_currency['currency'] ) {
			$data_localize_script['yay_dokan_coupon_area']   = true;
			$data_localize_script['yay_dokan_coupon_amount'] = '<strong class="yay-dokan-coupon-amount-wrapper"></strong>';
		}

		$suffix = defined( 'YAY_CURRENCY_SCRIPT_DEBUG' ) ? '' : '.min';

		wp_enqueue_script( 'yay-currency-dokan-script', YAY_CURRENCY_PLUGIN_URL . 'src/compatibles/dokan/yay-dokan' . $suffix . '.js', array(), YAY_CURRENCY_VERSION, true );
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
		$order_currency = false;
		if ( self::detect_dokan_pages( 'orders' ) && isset( $_REQUEST['order_id'] ) ) {
			$order_id = sanitize_key( $_REQUEST['order_id'] );
			$order    = wc_get_order( $order_id );
			if ( ! $order ) {
				return $this->apply_default_currency;
			}
			$order_currency = YayCurrencyHelper::get_currency_by_currency_code( $order->get_currency(), $this->converted_currency );
		}
		return $order_currency;
	}

	protected function detect_dokan_pages( $type = 'dashboard' ) {
		$pagename = isset( $GLOBALS['wp']->query_vars['pagename'] ) ? $GLOBALS['wp']->query_vars['pagename'] : false;
		if ( ! $pagename ) {
			return false;
		}
		$flag = false;
		switch ( $type ) {
			case 'dashboard':
				$dashboard_page_id = dokan_get_option( 'dashboard', 'dokan_pages' );
				$flag              = $pagename && ( in_array( $pagename, array( 'dashboard', 'vendor-dashboard' ) ) || is_page( $dashboard_page_id ) );
				break;
			default:
				$flag = self::detect_dokan_pages( 'dashboard' ) && isset( $GLOBALS['wp']->query_vars[ $type ] );
				break;
		}
		return $flag;
	}

	public function custom_currency_symbol( $symbol, $currency, $apply_currency ) {

		if ( ! self::detect_dokan_pages( 'dashboard' ) || ( class_exists( 'Dokan_Pro' ) && doing_action( 'dokan_dashboard_right_widgets' ) ) ) {
			return $symbol;
		}

		$order_currency = $this->get_order_currency_by_dokan_order_details();

		if ( $order_currency && isset( $order_currency['symbol'] ) ) {
			return $order_currency['symbol'];
		}

		if ( ! self::detect_dokan_pages( 'reports' ) ) {
			$symbol = $this->apply_default_currency['symbol'];
		}

		return $symbol;
	}

	public function get_price_format( $args ) {

		if ( ! self::detect_dokan_pages( 'dashboard' ) ) {
			return $args;
		}

		$apply_currency = array();
		$order_currency = $this->get_order_currency_by_dokan_order_details();

		if ( $order_currency ) {
			$apply_currency = $order_currency;
		} elseif ( ! self::detect_dokan_pages( 'reports' ) ) {
			$apply_currency = $this->apply_default_currency;
		}

		if ( empty( $args ) || ! $apply_currency ) {
			return $args;
		}

		$args['currency']           = $apply_currency['currency'];
		$args['thousand_separator'] = $apply_currency['thousandSeparator'];
		$args['decimal_separator']  = $apply_currency['decimalSeparator'];
		$args['decimals']           = $apply_currency['numberDecimal'];
		$args['price_format']       = YayCurrencyHelper::format_currency_symbol( $apply_currency );

		return $args;
	}

	public function change_price_format( $format, $currency_position ) {

		if ( ! self::detect_dokan_pages( 'dashboard' ) ) {
			return $format;
		}

		if ( self::detect_dokan_pages( 'withdraw' ) || self::detect_dokan_pages( 'withdraw-requests' ) ) {
			$apply_currency = $this->apply_default_currency;
			$format         = YayCurrencyHelper::format_currency_symbol( $apply_currency );
		}

		return $format;
	}

	public function is_original_product_price( $flag, $price, $product ) {
		if ( self::detect_dokan_pages( 'products' ) ) {
			$flag = true;
		}
		return $flag;
	}

	protected function convert_value_from_order( $value, $order_id, $keep_default = false ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return $value;
		}
		$order_rate_fee = Helper::get_yay_currency_order_rate( $order_id, $order );
		$original_value = floatval( $value / $order_rate_fee );
		return $keep_default ? $original_value : YayCurrencyHelper::calculate_price_by_currency( $original_value, false, $this->apply_currency );
	}

	// GET
	public function get_dokan_vendor_balance_data( $seller_id, $amount ) {
		global $wpdb;
		$dokan_vendor_balance = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dokan_vendor_balance WHERE vendor_id = %d AND trn_type ='dokan_withdraw' AND credit =%f AND status='approved'",
				$seller_id,
				$amount
			)
		);
		if ( $dokan_vendor_balance ) {
			$orders = self::get_dokan_orders_by_seller_id( $seller_id );
			return $orders;
		}
		return false;
	}
	public function get_rate_fee_by_dokan_vendor_balance_by_withdraw( $seller_id, $amount ) {
		if ( ! apply_filters( 'YayCurrency/Dokan/RevertToDefault', false ) ) {
			return 1;
		}
		global $wpdb;
		$dokan_vendor_balance = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dokan_vendor_balance WHERE vendor_id = %d AND trn_type ='dokan_withdraw' AND credit =%f AND status='approved'",
				$seller_id,
				$amount
			)
		);
		$trn_id               = isset( $dokan_vendor_balance->trn_id ) ? $dokan_vendor_balance->trn_id : false;
		$order_id             = self::get_order_id_by_trn_id( $trn_id );
		$order                = wc_get_order( $order_id );
		return $order ? Helper::get_yay_currency_order_rate( $order_id, $order ) : 1;
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
			$order = wc_get_order( $balance->trn_id );
			if ( ! $order ) {
				continue;
			}
			$balance_debit = self::convert_value_from_order( $balance->debit, $balance->trn_id, $only_get_price_default );
			$balance_debit = apply_filters( 'YayCurrency/Dokan/Seller/Balance/Debit', $balance_debit, $order, $seller_id, $only_get_price_default );

			$earning += $balance_debit;
		}
		$earning = (float) NumberUtil::round( $earning, wc_get_rounding_precision() );
		return $earning;
	}
	// GET CREDIT BY SELLER_ID : WITHDRAW AUTO IS DEFAULT CURRENCY
	protected function get_order_id_by_trn_id( $trn_id ) {
		global $wpdb;
		$result   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
            FROM {$wpdb->prefix}dokan_orders
            WHERE id = %d",
				$trn_id
			)
		);
		$order_id = $result && isset( $result->order_id ) ? $result->order_id : false;
		return $order_id;
	}

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

			$order_id = self::get_order_id_by_trn_id( $value->trn_id );
			$order    = wc_get_order( $order_id );
			$rate_fee = 1;

			if ( $order ) {
				$rate_fee = Helper::get_yay_currency_order_rate( $order_id, $order );
				$rate_fee = apply_filters( 'YayCurrency/Dokan/Withdraw/ExchangeRate', $rate_fee, $order, $value->balance_date, $seller_id );
			}

			if ( 'dokan_refund' === $value->trn_type ) {
				$withdraw -= (float) $value->credit / $rate_fee;
			} else {
				$withdraw += (float) $value->credit / $rate_fee;
			}
		}

		$withdraw = apply_filters( 'YayCurrency/Dokan/Seller/Withdraw', $withdraw, $results, $seller_id );
		$withdraw = (float) NumberUtil::round( $withdraw, wc_get_rounding_precision() );

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

	protected function get_debit_balance( $seller_id, $balance_date ) {
		$debit    = 0;
		$balances = $this->get_balance_by_seller_id( $seller_id, $balance_date, 'debit' );
		foreach ( $balances as $balance ) {
			$balance_debit = self::convert_value_from_order( $balance->debit, $balance->trn_id, true );
			$debit        += $balance_debit;
		}
		return $debit;
	}

	protected function get_credit_balance( $seller_id, $balance_date ) {
		$credit   = 0;
		$balances = $this->get_balance_by_seller_id( $seller_id, $balance_date, 'credit' );
		foreach ( $balances as $balance ) {
			$order_id       = self::get_order_id_by_trn_id( $balance->trn_id );
			$balance_credit = self::convert_value_from_order( $balance->credit, $order_id, true );
			$credit        += $balance_credit;
		}
		return $credit;
	}

	// Custom Net Sales by apply currency ---(Dashboard)
	public function custom_dokan_seller_total_sales( $net_sales, $seller_id ) {
		$dokan_orders = $this->get_dokan_orders_by_seller_id( $seller_id );
		$net_sales    = 0;
		foreach ( $dokan_orders as $dokan_order ) {
			$net_amount = self::convert_value_from_order( $dokan_order->net_amount, $dokan_order->order_id, true );
			$net_sales += $net_amount;
		}

		return $net_sales;
	}

	// Custom Earning by apply currency ---(Dashboard)
	public function custom_dokan_get_seller_earnings( $earning, $seller_id ) {
		$on_date        = dokan_current_datetime()->format( 'Y-m-d H:i:s' );
		$debit_balance  = self::get_debit_balance( $seller_id, $on_date );
		$credit_balance = self::get_credit_balance( $seller_id, $on_date );
		$earning        = floatval( $debit_balance - $credit_balance );
		$earning        = $earning < 0 ? 0 : $earning;

		return YayCurrencyHelper::formatted_price_by_currency( $earning, $this->apply_default_currency );

	}

	public function custom_dokan_get_formatted_seller_balance( $earning, $seller_id ) {
		$on_date = dokan_current_datetime()->format( 'Y-m-d H:i:s' );

		$withdraw = $this->get_withdraw_by_seller_id( $seller_id, $on_date );
		$earning  = $this->get_earning_by_seller_id( $seller_id, $on_date, true );

		$balance          = $earning - $withdraw;
		$balance          = $balance < 0 ? 0 : $balance;
		$balance_convert  = YayCurrencyHelper::calculate_price_by_currency( $balance, false, $this->apply_currency );
		$balance_currency = YayCurrencyHelper::formatted_price_by_currency( $balance_convert, $this->apply_currency );

		if ( $this->default_currency !== $this->apply_currency['currency'] ) {
			$balance_currency = YayCurrencyHelper::formatted_price_by_currency( $balance, $this->apply_default_currency );
			if ( apply_filters( 'yay_dokan_approximately_price', true ) ) {
				$balance_convert_currency = YayCurrencyHelper::formatted_price_by_currency( $balance_convert, $this->apply_currency );
				$balance_currency        .= YayCurrencyHelper::converted_approximately_html( $balance_convert_currency );
			}
		}

		return $balance_currency;
	}

	public function custom_dokan_get_seller_balance( $earning, $seller_id ) {
		if ( self::detect_dokan_pages( 'reports' ) ) {
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

		$earning        = $this->get_earning_by_seller_id( $seller_id, $on_date, true );
		$withdraw       = $this->get_withdraw_by_seller_id( $seller_id, $on_date, true );
		$seller_balance = $earning - $withdraw;
		return $seller_balance < 0 ? 0 : $seller_balance;
	}

	public function custom_dokan_reports_get_order_report_query( $query ) {

		if ( ! self::detect_dokan_pages( 'reports' ) ) {
			$query['select'] = 'SELECT meta__order_total.*, post_date';
			if ( isset( $query['group_by'] ) ) {
				unset( $query['group_by'] );
			}
		} elseif ( 'SELECT SUM( meta__order_total.meta_value) as total_sales,COUNT(DISTINCT posts.ID) as total_orders, posts.post_date as post_date' === $query['select'] ) {
				$query['select'] = 'SELECT meta__order_total.*, post_date';
			if ( isset( $query['group_by'] ) ) {
				unset( $query['group_by'] );
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
		foreach ( $rows as $value ) {
			$date        = gmdate( 'Y-m-d', strtotime( $value->post_date ) );
			$total_sales = self::convert_value_from_order( (float) $value->meta_value, $value->post_id, true );
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
				$total_sales    += self::convert_value_from_order( (float) $value->sales, $value->order_id, true );
				$total_shipping += self::convert_value_from_order( (float) $value->shipping, $value->order_id, true );
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
			$total_refund += self::convert_value_from_order( (float) $value->refund_amount, $value->order_id, true );
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
				$total_coupons += self::convert_value_from_order( (float) $value->meta_value, $order_id, true );
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
		if ( ! self::detect_dokan_pages( 'reports' ) ) {
			return $this->custom_report_chart( $rows, $data );
		} elseif ( isset( $data['_order_total'] ) && isset( $data['ID'] ) ) {
			if ( isset( $data['post_date'] ) ) {
				return $this->custom_report_chart( $rows, $data );
			}
		}

		return $rows;
	}

	public function custom_reports_top_earners_order_items( $order_items, $start_date, $end_date ) {
		if ( self::detect_dokan_pages( 'reports' ) ) {
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

				$order_id = ! isset( $item->order_id ) ? $item->order_id : false;

				if ( ! $order_id || ! in_array( $item->order_status, array_values( $withdraw_order_status ) ) ) {
					continue;
				}

				$line_total    = self::convert_value_from_order( (float) $item->line_total, $order_id );
				$total_earning = self::convert_value_from_order( (float) $item->total_earning, $order_id );
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
			$order_total = self::convert_value_from_order( (float) $value->order_total, $value->order_id, true );
			$net_amount  = self::convert_value_from_order( (float) $value->net_amount, $value->order_id, true );
			$earning     = $order_total - $net_amount;
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

		$this_month_order_total_html   = YayCurrencyHelper::formatted_price_by_currency( $this_month_order_total, $this->apply_default_currency );
		$last_month_order_total_html   = YayCurrencyHelper::formatted_price_by_currency( $last_month_order_total, $this->apply_default_currency );
		$this_month_earning_total_html = YayCurrencyHelper::formatted_price_by_currency( $this_month_earning_total, $this->apply_default_currency );
		$last_month_earning_total_html = YayCurrencyHelper::formatted_price_by_currency( $last_month_earning_total, $this->apply_default_currency );

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

			$this_period_order_total_html   = YayCurrencyHelper::formatted_price_by_currency( $this_period_order_total, $this->apply_default_currency );
			$this_period_total_orders_html  = YayCurrencyHelper::formatted_price_by_currency( $this_period_total_orders, $this->apply_default_currency );
			$this_period_earning_total_html = YayCurrencyHelper::formatted_price_by_currency( $this_period_earning_total, $this->apply_default_currency );

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
		$order          = wc_get_order( $order_id );
		$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order_id, $this->converted_currency );

		if ( ! $order_currency ) {
			wp_send_json_error( array( 'message' => __( 'Order doesn\'t exist', 'yay-currency' ) ) );
		}

		$order_total = $order->get_total();
		if ( 'refunded' === $order->get_status() ) {
			$order_total = YayCurrencyHelper::formatted_sale_price_by_currency( $order_total, 0, $order_currency );
		} else {
			$order_total = YayCurrencyHelper::formatted_price_by_currency( $order_total, $order_currency );
		}

		if ( function_exists( 'dokan' ) && method_exists( dokan()->commission, 'get_earning_by_order' ) ) {
			$earning = dokan()->commission->get_earning_by_order( $order );
		} else {
			$seller_id = isset( $_POST['seller_id'] ) ? intval( sanitize_text_field( $_POST['seller_id'] ) ) : false;
			$result    = $this->get_order_info_from_order_table( $order_id, $seller_id );
			$earning   = isset( $result ) ? $result->net_amount : 0;
		}

		wp_send_json_success(
			array(
				'earning'     => YayCurrencyHelper::formatted_price_by_currency( $earning, $order_currency ),
				'order_total' => $order_total,
			)
		);
	}

	// CALCULATE AGAIN WITH DOKAN PRO

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
				'price_html' => YayCurrencyHelper::converted_approximately_html( YayCurrencyHelper::formatted_price_by_currency( $price, $this->apply_currency ) ),
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
			foreach ( $statements as $statement ) {

				$order_id   = self::get_order_id_by_trn_id( $statement->trn_id );
				$order_id   = $order_id ? $order_id : $statement->trn_id;
				$order_rate = 1;
				$order      = wc_get_order( $order_id );
				if ( $order ) {
					$order_rate = Helper::get_yay_currency_order_rate( $order_id, $order );
					$order_rate = apply_filters( 'YayCurrency/Dokan/Statement/ExchangeRate', $order_rate, $order, $statement->trn_date, $seller_id );
				}
				$debit  = $statement->debit / $order_rate;
				$credit = $statement->credit / $order_rate;

				$total_debit                += $debit;
				$total_credit               += $credit;
				$balance                    += abs( $debit - $credit );
				$debit                       = YayCurrencyHelper::calculate_price_by_currency( $debit, false, $this->apply_currency );
				$credit                      = YayCurrencyHelper::calculate_price_by_currency( $credit, false, $this->apply_currency );
				$results[ $index ]['debit']  = YayCurrencyHelper::formatted_price_by_currency( $debit, $this->apply_currency );
				$results[ $index ]['credit'] = YayCurrencyHelper::formatted_price_by_currency( $credit, $this->apply_currency );

				++$index;
			}
			$total_debit  = YayCurrencyHelper::calculate_price_by_currency( $total_debit, false, $this->apply_currency );
			$total_credit = YayCurrencyHelper::calculate_price_by_currency( $total_credit, false, $this->apply_currency );
			$balance      = YayCurrencyHelper::calculate_price_by_currency( $balance, false, $this->apply_currency );
			wp_send_json_success(
				array(
					'statements'    => $results,
					'total_debit'   => YayCurrencyHelper::formatted_price_by_currency( $total_debit, $this->apply_currency ),
					'total_credit'  => YayCurrencyHelper::formatted_price_by_currency( $total_credit, $this->apply_currency ),
					'total_balance' => YayCurrencyHelper::formatted_price_by_currency( $balance, $this->apply_currency ),
				)
			);
		}

		wp_send_json_error();
	}

	public function custom_approved_withdraw_request() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;
		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-dokan-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}
		$seller_id = isset( $_POST['seller_id'] ) ? intval( sanitize_text_field( $_POST['seller_id'] ) ) : false;
		if ( $seller_id ) {
			$results = dokan()->withdraw->get_withdraw_requests( $seller_id, 1, 100 );
			$html    = '';

			foreach ( $results as $key => $row ) {
				$amount     = $row->get_amount();
				$charge     = $row->get_charge();
				$receivable = $row->get_receivable_amount();

				$rate_fee_withdraw = 1;
				$order_id          = self::get_order_id_by_trn_id( $row->get_id() );
				$order             = wc_get_order( $order_id );
				if ( $order ) {
					$rate_fee_withdraw = Helper::get_yay_currency_order_rate( $order_id, $order );
				} else {
					$rate_fee_withdraw = self::get_rate_fee_by_dokan_vendor_balance_by_withdraw( $seller_id, floatval( $amount ) );
				}

				if ( $rate_fee_withdraw ) {
					$amount     = floatval( $amount / $rate_fee_withdraw );
					$receivable = floatval( $receivable / $rate_fee_withdraw );
					$charge     = floatval( $charge / $rate_fee_withdraw );
				}

				$amount_withdraw_currency = YayCurrencyHelper::formatted_price_by_currency( $amount, $this->apply_default_currency );
				$receivable_currency      = YayCurrencyHelper::formatted_price_by_currency( $receivable, $this->apply_default_currency );
				$charge_withdraw_currency = YayCurrencyHelper::formatted_price_by_currency( $charge, $this->apply_default_currency );
				$html                    .= ' <tr>
				<td>' . wp_kses_post( $amount_withdraw_currency ) . '</td>
				<td>' . esc_html( dokan_withdraw_get_method_title( $row->get_method(), $row ) ) . '</td>
				<td>' . wp_kses_post( $charge_withdraw_currency ) . '</td>
				<td>' . wp_kses_post( $receivable_currency ) . '</td>
				<td>' . esc_html( dokan_format_date( $row->get_date() ) ) . '</td>
			</tr>';

			}

			wp_send_json_success(
				array(
					'html' => $html,
				)
			);

		}

		wp_send_json_error();
	}

	public function custom_cancelled_withdraw_request() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;
		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-dokan-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}
		$seller_id = isset( $_POST['seller_id'] ) ? intval( sanitize_text_field( $_POST['seller_id'] ) ) : false;
		if ( $seller_id ) {
			$results = dokan()->withdraw->get_withdraw_requests( $seller_id, 2, 100 );
			$html    = '';
			foreach ( $results as $key => $row ) {
				$amount            = $row->get_amount();
				$charge            = $row->get_charge();
				$receivable        = $row->get_receivable_amount();
				$rate_fee_withdraw = self::get_rate_fee_by_dokan_vendor_balance_by_withdraw( $seller_id, floatval( $amount ) );
				if ( $rate_fee_withdraw ) {
					$amount     = floatval( $amount / $rate_fee_withdraw );
					$receivable = floatval( $receivable / $rate_fee_withdraw );
					$charge     = floatval( $charge / $rate_fee_withdraw );
				}

				$amount_withdraw_currency = YayCurrencyHelper::formatted_price_by_currency( $amount, $this->apply_default_currency );
				$receivable_currency      = YayCurrencyHelper::formatted_price_by_currency( $receivable, $this->apply_default_currency );
				$charge_withdraw_currency = YayCurrencyHelper::formatted_price_by_currency( $charge, $this->apply_default_currency );
				$html                    .= ' <tr>
				<td>' . wp_kses_post( $amount_withdraw_currency ) . '</td>
				<td>' . esc_html( dokan_withdraw_get_method_title( $row->get_method(), $row ) ) . '</td>
				<td>' . wp_kses_post( $charge_withdraw_currency ) . '</td>
				<td>' . wp_kses_post( $receivable_currency ) . '</td>
				<td>' . esc_html( dokan_format_date( $row->get_date() ) ) . '</td>
				<td>' . wp_kses_post( $row->get_note() ) . '</td>
			</tr>';

			}

			wp_send_json_success(
				array(
					'html' => $html,
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
					'order_total'        => YayCurrencyHelper::formatted_price_by_currency( $value['order_total'], $order_currency ),
					'vendor_earning'     => YayCurrencyHelper::formatted_price_by_currency( $value['vendor_earning'], $order_currency ),
					'commission'         => YayCurrencyHelper::formatted_price_by_currency( $value['commission'], $order_currency ),
					'dokan_gateway_fee'  => YayCurrencyHelper::formatted_price_by_currency( $value['dokan_gateway_fee'], $order_currency ),
					'shipping_total'     => YayCurrencyHelper::formatted_price_by_currency( $value['shipping_total'], $order_currency ),
					'shipping_total_tax' => YayCurrencyHelper::formatted_price_by_currency( $value['shipping_total_tax'], $order_currency ),
					'tax_total'          => YayCurrencyHelper::formatted_price_by_currency( $value['tax_total'], $order_currency ),
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

			$data[ $order_id ] = YayCurrencyHelper::formatted_price_by_currency( $refund->refund_amount, $order_currency );
		}

		wp_send_json_success(
			array(
				'refunds' => $data,
			)
		);
	}

	public function custom_get_overview_data( $data, $group_by, $start_date, $end_date, $seller_id ) {
		$start_date        = ! empty( $start_date ) ? sanitize_text_field( $start_date ) : '';
		$end_date          = ! empty( $end_date ) ? sanitize_text_field( $end_date ) : '';
		$all_data_by_month = $this->get_all_data_by_month( $start_date, $end_date, $seller_id );
		$data              = $this->get_data_earning_order_total_by_month( $all_data_by_month );
		return $data;
	}

	public function dokan_product_wholesale_price_html( $html ) {
		if ( ! doing_action( 'woocommerce_before_add_to_cart_button' ) || ! isset( $GLOBALS['post']->ID ) ) {
			return $html;
		}
		$wholesale = get_post_meta( $GLOBALS['post']->ID, '_dokan_wholesale_meta', true );
		if ( ! $wholesale ) {
			return $html;
		}
		$wholesale_price = ! empty( $wholesale['price'] ) ? $wholesale['price'] : false;
		if ( ! $wholesale_price ) {
			return $html;
		}
		$wholesale_price    = YayCurrencyHelper::calculate_price_by_currency( $wholesale_price, false, $this->apply_currency );
		$wholesale_quantity = ! empty( $wholesale['quantity'] ) ? $wholesale['quantity'] : '';
		$html               = sprintf( '%s: <strong>%s</strong> ( %s: <strong>%s</strong> )', __( 'Wholesale Price', 'dokan' ), wc_price( $wholesale_price ), __( 'Minimum Quantity', 'dokan' ), $wholesale_quantity );

		return $html;
	}

	public function dokan_rest_prepare_withdraw_object( $response, $withdraw, $request ) {
		if ( ! apply_filters( 'YayCurrency/Dokan/RevertToDefault', false ) ) {
			return $response;
		}
		$data      = $response->get_data();
		$seller_id = $withdraw->get_user_id();
		if ( ! $seller_id || ! isset( $data['amount'] ) ) {
			return $response;
		}

		$dokan_vendor_data = self::get_dokan_vendor_balance_data( $seller_id, floatval( $data['amount'] ) );

		if ( ! $dokan_vendor_data ) {
			return $response;
		}

		$dokan_vendor_data = array_shift( $dokan_vendor_data );
		$order_id          = $dokan_vendor_data->order_id;

		$order = wc_get_order( $order_id );

		$rate_fee_withdraw = Helper::get_yay_currency_order_rate( $order_id, $order );

		if ( ! $rate_fee_withdraw ) {
			return $response;
		}

		$data['amount']     = floatval( $data['amount'] / $rate_fee_withdraw );
		$data['receivable'] = floatval( $data['receivable'] / $rate_fee_withdraw );
		$data['charge']     = floatval( $data['charge'] / $rate_fee_withdraw );

		$response->set_data( $data );

		return $response;
	}
}
