<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;

use Automattic\WooCommerce\Utilities\NumberUtil;

use WeDevs\Dokan\Models\AdminDashboardStats;
use WeDevs\Dokan\REST\AdminDashboardStatsController;
use WeDevs\Dokan\Analytics\Reports\OrderType;
use WeDevs\Dokan\Commission\OrderCommission;
// PRO only
use WeDevs\Dokan\Utilities\ReportUtil;

defined( 'ABSPATH' ) || exit;

class Dokan {
	use SingletonTrait;

	private $default_currency       = '';
	private $converted_currency     = array();
	private $apply_currency         = array();
	private $apply_default_currency = array();
	private $order_stats_columns    = array();

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

		$this->order_stats_columns = array(
			'vendor_earning',
			'vendor_gateway_fee',
			'vendor_shipping_fee',
			'vendor_discount',
			'vendor_shipping_tax',
			'vendor_order_tax',
			'admin_earning',
			'admin_commission',
			'admin_gateway_fee',
			'admin_shipping_fee',
			'admin_discount',
			'admin_shipping_tax',
			'admin_order_tax',
			'admin_subsidy',
			'amount',
		);

		// =======START LITE & PRO =======
		add_filter( 'yay_currency_woocommerce_currency_symbol', array( $this, 'resolve_dokan_currency_symbol' ), 10, 3 );

		// Disable Decimal Separator, Thousand Separator, Number Decimal - Default Currency Format
		add_filter( 'YayCurrency/DisableDecimalSeparator', array( $this, 'should_disable_format_settings' ), 10, 1 );
		add_filter( 'YayCurrency/DisableThousandSeparator', array( $this, 'should_disable_format_settings' ), 10, 1 );
		add_filter( 'YayCurrency/DisableNumberDecimal', array( $this, 'should_disable_format_settings' ), 10, 1 );

		// Filter Price Format
		add_filter( 'yay_currency_get_price_format', array( $this, 'filter_price_format' ), 10, 1 );
		add_filter( 'woocommerce_price_format', array( $this, 'filter_dokan_price_format' ), 9999, 2 );

		// Keep Original Price on Products Dashboard
		add_filter( 'yay_currency_is_original_product_price', array( $this, 'maybe_use_original_product_price' ), 20, 3 );

		// ===Dashboard Menu====

		// Balance Overview
		add_filter( 'dokan_get_formatted_seller_balance', array( $this, 'get_formatted_dokan_seller_balance' ), 10, 2 );

		// Total Vendor Earning Performance Indicator
		add_filter( 'woocommerce_rest_performance_indicators_data_value', array( $this, 'filter_dokan_revenue_performance_indicators' ), 10, 5 );

		// ===Withdraw Menu====

		// Your Balance
		add_filter( 'dokan_get_seller_balance', array( $this, 'filter_dokan_seller_balance' ), 10, 2 );

		// ======= START BACKEND =======

		// Filter Dokan REST API responses: Orders, Products, Withdrawals, Admin Dashboard, Report Logs, Admin Earnings, etc.
		add_filter( 'rest_request_after_callbacks', array( $this, 'filter_dokan_rest_response' ), 10, 3 );

		// Daily Sales Chart
		add_filter( 'dokan_rest_admin_dashboard_sales_chart_data', array( $this, 'filter_admin_dashboard_sales_chart_data' ), 10, 1 );

		// Filter Admin Dashboard Total Commissions
		add_filter( 'dokan_rest_admin_dashboard_all_time_stats_data', array( $this, 'filter_admin_dashboard_total_commissions' ), 10, 2 );

		// Top Performing Vendors - Dashboard menu
		add_filter( 'dokan_rest_admin_dashboard_top_performing_vendors_data', array( $this, 'admin_dashboard_top_performing_vendors_data' ), 10, 2 );

		// Format Admin Commission column table
		add_filter( 'dokan_manage_shop_order_custom_columns_admin_commission', array( $this, 'format_dokan_admin_commission_column' ), 10, 2 );

		// ======= END BACKEND =======

		// =======END LITE & PRO =======

		// ======= START PRO ONLY =======

		// Report: Statement summary - Total balance, Total debit, Total credit, First entry date
		add_filter( 'dokan_report_statement_summary_results', array( $this, 'filter_dokan_report_statement_summary_results' ), 10, 3 );

		// Report: Statement data table - Processed Entry & Processed Entries (Open Balance, Debit, Credit, Balance)
		add_filter( 'dokan_report_statement_processed_entry', array( $this, 'filter_dokan_report_statement_processed_entry' ), 10, 4 );
		add_filter( 'dokan_report_statement_processed_entries', array( $this, 'filter_dokan_report_statement_processed_entries' ), 10, 2 );

		// START BACKEND PRO ONLY =======
		// Report: Stats - Commission earned
		add_filter( 'dokan_admin_report_stats_commission_earned', array( $this, 'filter_dokan_admin_report_stats_commission_earned' ), 10, 4 );
		// Report: Stats - Overview chart data
		add_filter( 'dokan_admin_report_stats_overview_chart_data', array( $this, 'filter_dokan_admin_report_stats_overview_chart_data' ), 10, 4 );
		// Report: Admin Earnings tab
		// Summary
		add_filter( 'dokan_admin_report_earnings_summary', array( $this, 'filter_dokan_admin_report_earnings_summary' ), 10, 3 );

		// END BACKEND PRO ONLY =======

		// Dokan REST API: Subscription Order
		add_filter( 'dokan_rest_prepare_vendor_subscription_order', array( $this, 'custom_dokan_rest_prepare_vendor_subscription_order' ), 10, 3 );
	}

	protected function is_seller_dashboard() {
		if ( function_exists( 'dokan_is_seller_dashboard' ) && dokan_is_seller_dashboard() ) {
			return true;
		}
		return false;
	}

	protected function is_dokan_rest_request( $route = '' ) {
		return SupportHelper::is_rest_route_request( 'dokan/v1', $route );
	}

	protected function is_dokan_request( $route = '' ) {
		return $this->is_seller_dashboard() || $this->is_dokan_rest_request( $route );
	}

	protected function resolve_dokan_order_currency() {
		$order_currency = array();
		// Is Dokan Order Edit Page
		if ( $this->is_dokan_request( 'orders' ) && isset( $_REQUEST['order_id'] ) ) {
			$order_id = sanitize_key( $_REQUEST['order_id'] );
			$order    = wc_get_order( $order_id );
			if ( ! $order ) {
				return $this->apply_default_currency;
			}
			$order_currency = YayCurrencyHelper::get_currency_by_currency_code( $order->get_currency(), $this->converted_currency );
		}
		return $order_currency;
	}

	public function resolve_dokan_currency_symbol( $currency_symbol = '', $currency = '', $product = null ) {

		$order_currency = $this->resolve_dokan_order_currency();

		if ( ! empty( $order_currency ) && isset( $order_currency['symbol'] ) ) {
			return $order_currency['symbol'];
		}

		// Is Seller Dashboard Page or REST Request then apply default currency symbol
		if ( $this->is_dokan_request() ) {
			return $this->apply_default_currency['symbol'];
		}

		// Otherwise, return the original currency symbol
		return $currency_symbol;
	}

	// Disable Decimal Separator, Thousand Separator, Number Decimal - Default Currency Format
	public function should_disable_format_settings( $disable = false ) {
		if ( ! $this->is_dokan_request() ) {
			return $disable;
		}
		return true;
	}

	// Keep Original Price on Products Dashboard
	public function maybe_use_original_product_price( $should_use_original_price = false, $price = 0, $product = null ) {
		if ( $this->is_dokan_request( 'products' ) || SupportHelper::is_rest_route_request( 'dokan/v3', 'products/init/fields' ) ) {
			$should_use_original_price = true;
		}
		return $should_use_original_price;
	}

	// Filter Price Format
	public function filter_price_format( $args = array() ) {

		if ( ! $this->is_dokan_request() ) {
			return $args;
		}

		$apply_currency = array();
		$order_currency = $this->resolve_dokan_order_currency();

		if ( ! empty( $order_currency ) ) {
			$apply_currency = $order_currency;
		} else {
			$apply_currency = $this->apply_default_currency;
		}

		if ( empty( $args ) || empty( $apply_currency ) ) {
			return $args;
		}

		$args['currency']           = $apply_currency['currency'];
		$args['thousand_separator'] = $apply_currency['thousandSeparator'];
		$args['decimal_separator']  = $apply_currency['decimalSeparator'];
		$args['decimals']           = $apply_currency['numberDecimal'];
		$args['price_format']       = YayCurrencyHelper::format_currency_symbol( $apply_currency );

		return $args;
	}

	// Filter Price Format
	public function filter_dokan_price_format( $format = '', $currency_position = '' ) {

		if ( ! $this->is_dokan_request() ) {
			return $format;
		}

		$format = YayCurrencyHelper::format_currency_symbol( $this->apply_default_currency );

		return $format;
	}

	protected function convert_order_value( $value = 0, $order_id = 0, $keep_default = false ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_currency() || $this->default_currency === $order->get_currency() ) {
			return $value;
		}

		$order_rate_fee = Helper::get_yay_currency_order_rate( $order_id, $order );
		$original_value = floatval( $value / $order_rate_fee );
		if ( $keep_default ) {
			return $original_value;
		}
		return YayCurrencyHelper::calculate_price_by_currency( $original_value, false, $this->apply_currency );
	}

	protected function get_order_id_by_dokan_order_id( $trn_id ) {
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

	protected function get_seller_balances( $seller_id = 0, $on_date = '', $type = 'debit' ) {
		global $wpdb;

		switch ( $type ) {
			case 'debit':
				$balances = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dokan_vendor_balance WHERE vendor_id = %d AND DATE(balance_date) <= %s AND trn_type = %s", $seller_id, $on_date, 'dokan_orders' )
				);
				break;
			case 'credit':
				$balances = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dokan_vendor_balance WHERE vendor_id = %d AND DATE(balance_date) <= %s AND trn_type = %s AND status = %s", $seller_id, $on_date, 'dokan_refund', 'approved' )
				);
				break;
			default: // earning
				$balances = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dokan_vendor_balance WHERE vendor_id = %d AND DATE(balance_date) <= %s", $seller_id, $on_date ) );
				break;
		}

		// Filter by order status for credit type
		if ( 'credit' !== $type ) {
			$status   = dokan_withdraw_get_active_order_status();
			$balances = array_filter(
				$balances,
				function ( $value ) use ( $status ) {
					return in_array( $value->status, $status, true );
				},
				ARRAY_FILTER_USE_BOTH
			);
		}

		return $balances;
	}

	public function calculate_seller_earnings( $seller_id = 0, $balance_date = '', $only_get_price_default = false ) {
		$earning         = 0;
		$seller_balances = $this->get_seller_balances( $seller_id, $balance_date, 'earning' );

		foreach ( $seller_balances as $row_balance ) {
			$order_id      = isset( $row_balance->trn_id ) ? $row_balance->trn_id : 0;
			$debit         = isset( $row_balance->debit ) ? $row_balance->debit : 0;
			$balance_debit = $this->convert_order_value( $debit, $order_id, $only_get_price_default );
			$balance_debit = apply_filters( 'yay_currency_dokan_seller_balance_debit', $balance_debit, $order_id, $seller_id, $balance_date, $only_get_price_default );
			$earning      += $balance_debit;
		}
		$earning = (float) NumberUtil::round( $earning, wc_get_rounding_precision() );
		return $earning;
	}

	public function calculate_seller_withdrawals( $seller_id = 0, $balance_date = '' ) {
		global $wpdb;

		$result   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(credit) as withdraw
		   FROM {$wpdb->prefix}dokan_vendor_balance WHERE vendor_id = %d AND DATE(balance_date) <= %s",
				$seller_id,
				$balance_date
			)
		);
		$withdraw = isset( $result->withdraw ) ? $result->withdraw : 0;

		$withdraw = apply_filters( 'yay_currency_dokan_seller_withdrawals', $withdraw, $seller_id, $balance_date );
		$withdraw = (float) NumberUtil::round( $withdraw, wc_get_rounding_precision() );

		return $withdraw;

	}

	// ===Dashboard Menu====

	// Balance Overview
	public function get_formatted_dokan_seller_balance( $earning = 0, $seller_id = 0 ) {
		$on_date  = dokan_current_datetime()->format( 'Y-m-d H:i:s' );
		$earning  = $this->calculate_seller_earnings( $seller_id, $on_date, true );
		$withdraw = $this->calculate_seller_withdrawals( $seller_id, $on_date );

		$balance                    = $earning - $withdraw;
		$balance                    = $balance < 0 ? 0 : $balance;
		$balance_convert            = YayCurrencyHelper::calculate_price_by_currency( $balance, false, $this->apply_currency );
		$formatted_balance_currency = YayCurrencyHelper::formatted_price_by_currency( $balance_convert, $this->apply_currency );

		if ( $this->default_currency !== $this->apply_currency['currency'] ) {
			$formatted_balance_currency = YayCurrencyHelper::formatted_price_by_currency( $balance, $this->apply_default_currency );
			if ( apply_filters( 'yay_dokan_approximately_price', true ) ) {
				$balance_convert_currency    = YayCurrencyHelper::formatted_price_by_currency( $balance_convert, $this->apply_currency );
				$formatted_balance_currency .= YayCurrencyHelper::converted_approximately_html( $balance_convert_currency );
			}
		}

		return $formatted_balance_currency;
	}

	//===Start Total Vendor Earning Performance Indicator===
	protected function filter_results_by_order_type( $results ) {
		$order_type = new OrderType();
		$types      = array_map( 'intval', $order_type->get_vendor_order_types() );
		return array_filter(
			$results,
			function ( $value ) use ( $types ) {
				return in_array( intval( $value->order_type ), $types, true );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	protected function get_seller_order_stats_by_balance_date_range( $seller_id = 0, $args = array(), $is_admin_dashboard = false ) {
		if ( empty( $args['date_start'] ) || empty( $args['date_start'] ) ) {
			return array();
		}
		global $wpdb;

		if ( $seller_id && ! user_can( $seller_id, 'manage_options' ) || $is_admin_dashboard ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ds.* FROM {$wpdb->prefix}dokan_order_stats AS ds
					INNER JOIN {$wpdb->prefix}dokan_vendor_balance AS vb
					ON ds.order_id = vb.trn_id WHERE ds.vendor_id = %d AND vb.balance_date BETWEEN %s AND %s",
					$seller_id,
					$args['date_start'],
					$args['date_end'],
				)
			);
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ds.* FROM {$wpdb->prefix}dokan_order_stats AS ds
					INNER JOIN {$wpdb->prefix}dokan_vendor_balance AS vb
					ON ds.order_id = vb.trn_id WHERE vb.balance_date BETWEEN %s AND %s",
					$args['date_start'],
					$args['date_end'],
				)
			);
		}

		$results = $this->filter_results_by_order_type( $results );

		return $results;
	}

	/**
	 * Calculate seller statistics for the given date range.
	 * All balance-related fields are converted from the order currency to the
	 * current display currency before being aggregated. Returns total values for
	 * every supported field and average values for selected metrics.
	 *
	 * @param int   $seller_id Seller ID.
	 * @param array $args      Query arguments.
	 * @return array
	*/
	protected function calculate_seller_order_stats( $seller_id = 0, $args = array(), $is_admin_dashboard = false ) {
		$results = $this->get_seller_order_stats_by_balance_date_range( $seller_id, $args, $is_admin_dashboard );
		$totals  = array_fill_keys( $this->order_stats_columns, 0 );

		foreach ( $results as $result ) {
			$order_id = isset( $result->order_id ) ? $result->order_id : 0;
			foreach ( $this->order_stats_columns as $column ) {
				if ( ! isset( $result->{$column} ) ) {
					continue;
				}
				$totals[ $column ] += $this->convert_order_value( $result->{$column}, $order_id, true );
			}
		}

		$order_count = max( count( $results ), 1 );
		$return      = array();

		foreach ( $totals as $column => $value ) {
			$return[ 'total_' . $column ] = $value;
			// Only create averages for the necessary fields.
			if ( in_array( $column, array( 'vendor_earning', 'admin_commission' ), true ) ) {
				$return[ 'avg_' . $column ] = floatval( $value / $order_count );
			}
		}

		return $return;
	}

	/**
	 * Build date arguments for seller statistics from performance query params.
	 *
	 * @param array $query_args Performance indicator query arguments.
	 * @return array
	*/
	protected function get_performance_indicator_date_args( $query_args = array() ) {

		$args   = array();
		$before = isset( $query_args['before'] ) ? $query_args['before'] : '';
		$after  = isset( $query_args['after'] ) ? $query_args['after'] : '';

		if ( empty( $before ) || empty( $after ) ) {
			return $args;
		}

		$end_date           = new \DateTimeImmutable( $before, wp_timezone() );
		$start_date         = new \DateTimeImmutable( $after, wp_timezone() );
		$args['date_start'] = $start_date->format( 'Y-m-d H:i:s' );
		$args['date_end']   = $end_date->format( 'Y-m-d H:i:s' );

		return $args;
	}

	/**
	 * Update performance values for totals or interval subtotals.
	 *
	 * @param array|object $target  Target array or object to update.
	 * @param array        $results Calculated performance values.
	 * @param array        $fields  Fields to update.
	 * @return void
	*/
	protected function update_performance_values( &$target, $results, $fields ) {
		foreach ( $fields as $field ) {
			if ( is_array( $target ) ) {
				if ( isset( $target[ $field ] ) ) {
					$target[ $field ] = $results[ $field ];
				}
			} elseif ( isset( $target->{$field} ) ) {
				$target->{$field} = $results[ $field ];
			}
		}
	}

	public function filter_dokan_revenue_performance_indicators( $value = array(), $stat = '', $report = '', $chart = '', $query_args = array() ) {
		$field_map = array(
			'revenue/total_vendor_earning'   => array(
				'total_vendor_earning',
				'avg_vendor_earning',
			),
			'revenue/total_vendor_discount'  => array( 'total_vendor_discount' ),
			'revenue/total_admin_commission' => array(
				'total_admin_commission',
				'avg_admin_commission',
			),
			'revenue/total_admin_discount'   => array( 'total_admin_discount' ),
		);

		$metric = 'revenue' === $report && ! empty( $chart ) ? "revenue/{$chart}" : $stat;

		if ( empty( $field_map[ $metric ] ) ) {
			return $value;
		}

		$fields    = $field_map[ $metric ];
		$vendor_id = dokan_get_current_user_id();
		$args      = $this->get_performance_indicator_date_args( $query_args );

		if ( ! empty( $value['totals'] ) ) {
			$results = $this->calculate_seller_order_stats( $vendor_id, $args );
			$this->update_performance_values( $value['totals'], $results, $fields );
		}

		if ( empty( $value['intervals'] ) ) {
			return $value;
		}

		foreach ( $value['intervals'] as $key => $interval ) {
			$results = $this->calculate_seller_order_stats(
				$vendor_id,
				[
					'date_start' => ! empty( $interval['date_start'] ) ? $interval['date_start'] : '',
					'date_end'   => ! empty( $interval['date_end'] ) ? $interval['date_end'] : '',
				]
			);
			$this->update_performance_values( $value['intervals'][ $key ]['subtotals'], $results, $fields );
		}

		return $value;
	}

	//===End Total Vendor Earning Performance Indicator===

	// ===Withdraw Menu====

	// Your Balance
	public function filter_dokan_seller_balance( $earning = 0, $seller_id = 0 ) {
		$on_date = dokan_current_datetime();
		if ( $this->is_dokan_request( 'vendor/reports/statement' ) ) {
			$start_date = dokan_current_datetime()->modify( 'first day of this month' )->format( 'Y-m-d' );
			if ( isset( $_GET['start_date'] ) ) {
				$start_date = sanitize_text_field( wp_unslash( $_GET['start_date'] ) );
			}
			$on_date = $start_date && strtotime( $start_date ) ? dokan_current_datetime()->modify( $start_date ) : dokan_current_datetime();
		}
		$on_date = $on_date->format( 'Y-m-d H:i:s' );

		$earning        = $this->calculate_seller_earnings( $seller_id, $on_date, true );
		$withdraw       = $this->calculate_seller_withdrawals( $seller_id, $on_date );
		$seller_balance = $earning - $withdraw;
		return $seller_balance < 0 ? 0 : $seller_balance;
	}

	// =======START BACKEND =======
	public function get_vendor_store_opening_balance( $vendor_id = 0, $trn_date = array(), $opening_balance = false ) {
		$balance = 0;
		if ( empty( $trn_date['from'] ) || empty( $trn_date['to'] ) ) {
			return $balance;
		}
		global $wpdb;
		if ( $opening_balance ) {
			$start_date = ( new \DateTimeImmutable( $trn_date['from'], wp_timezone() ) )->modify( '-1 day' )->setTime( 0, 0, 0 )->getTimestamp();
			$end_date   = ( new \DateTimeImmutable( $trn_date['from'], wp_timezone() ) )->modify( '-1 day' )->setTime( 23, 59, 59 )->getTimestamp();
		} else {
			$start_date = ( new \DateTimeImmutable( $trn_date['from'], wp_timezone() ) )->getTimestamp();
			$end_date   = ( new \DateTimeImmutable( $trn_date['to'], wp_timezone() ) )->getTimestamp();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT drw.* FROM {$wpdb->prefix}dokan_reverse_withdrawal AS drw
				WHERE drw.vendor_id = %d AND drw.trn_date BETWEEN %s AND %s",
				$vendor_id,
				$start_date,
				$end_date,
			)
		);
		foreach ( $results as $result ) {
			$order_id = isset( $result->trn_id ) ? $result->trn_id : 0;
			$debit    = 'order_commission' === $result->trn_type ? $this->convert_order_value( $result->debit, $order_id, true ) : $result->debit;
			$credit   = 'order_commission' === $result->trn_type ? $this->convert_order_value( $result->credit, $order_id, true ) : $result->credit;
			$balance += (float) ( $debit - $credit );
		}
		return $balance;
	}

	protected function get_vendor_store_balance_summary( $vendor_id = 0 ) {
		global $wpdb;
		$results       = $wpdb->get_results( $wpdb->prepare( "SELECT drw.* FROM {$wpdb->prefix}dokan_reverse_withdrawal AS drw WHERE drw.vendor_id = %d", $vendor_id ) );
		$total_debit   = 0;
		$total_credit  = 0;
		$total_balance = 0;
		foreach ( $results as $result ) {
			$order_id       = isset( $result->trn_id ) ? $result->trn_id : 0;
			$debit          = 'order_commission' === $result->trn_type ? $this->convert_order_value( $result->debit, $order_id, true ) : $result->debit;
			$credit         = 'order_commission' === $result->trn_type ? $this->convert_order_value( $result->credit, $order_id, true ) : $result->credit;
			$total_debit   += (float) $debit;
			$total_credit  += (float) $credit;
			$total_balance += (float) ( $debit - $credit );
		}
		return [
			'total_debit'   => $total_debit,
			'total_credit'  => $total_credit,
			'total_balance' => $total_balance,
		];
	}

	// Filter Dokan REST API responses
	public function filter_dokan_rest_response( $response = null, $handler = null, $request = null ) {
		$rest_route = $request->get_route();
		if ( ! $response instanceof \WP_REST_Response || ! $request instanceof \WP_REST_Request || false === strpos( $rest_route, '/dokan/v1/' ) ) {
			return $response;
		}
		// Lite
		$this->filter_dokan_lite_rest_route_response( $rest_route, $response );

		// PRO
		$this->filter_dokan_pro_rest_route_response( $rest_route, $response );

		return $response;
	}

	public function filter_orders_table_response( $rest_route = '', $response = null ) {
		// Add New Reverse Withdrawal filter for Orders table - Admin Dashboard
		if ( '/dokan/v1/orders' === $rest_route && isset( $_REQUEST['seller_id'] ) ) {
			$data = $response->get_data();
			foreach ( $data as $key => $value ) {
				if ( $this->default_currency !== $value['currency'] ) {
					$order_id = isset( $value['id'] ) ? $value['id'] : 0;
					$earning  = $this->convert_order_value( isset( $value['earning'] ) ? $value['earning'] : 0, $order_id, true );
					$total    = $this->convert_order_value( isset( $value['total'] ) ? $value['total'] : 0, $order_id, true );

					$data[ $key ]['earning'] = $earning;
					$data[ $key ]['total']   = $total;
				}
			}
			$response->set_data( $data );
		}
	}
	public function filter_reverse_withdrawal_response( $rest_route = '', $response = null ) {
		$prefix = '/dokan/v1/reverse-withdrawal/';
		if ( false === strpos( $rest_route, $prefix ) ) {
			return;
		}
		$data = $response->get_data();
		// Reverse Withdrawal Stores Balance: Total Debit, Total Credit, Total Balance
		if ( $prefix . 'stores-balance' === $rest_route ) {
			foreach ( $data as $key => $value ) {
				$vendor_id               = isset( $value['vendor_id'] ) ? $value['vendor_id'] : 0;
				$balance_summary         = $this->get_vendor_store_balance_summary( $vendor_id );
				$data[ $key ]['debit']   = $balance_summary['total_debit'];
				$data[ $key ]['credit']  = $balance_summary['total_credit'];
				$data[ $key ]['balance'] = $balance_summary['total_balance'];
			}

			$response->set_data( $data );
		}

		// Reverse Withdrawal Transactions table
		if ( $prefix . 'transactions' === $rest_route ) {
			$should_set_data    = false;
			$commission_balance = 0;

			foreach ( $data as $key => $value ) {
				$order_id            = isset( $value['trn_id'] ) ? $value['trn_id'] : 0;
				$is_order_commission = ! empty( $value['trn_type_raw'] ) && 'order_commission' === $value['trn_type_raw'];
				if ( 'opening_balance' === $value['trn_type_raw'] ) {
					$should_set_data = true;
					if ( isset( $_REQUEST['trn_date']['from'] ) && isset( $_REQUEST['trn_date']['to'] ) ) {
						$start_date              = sanitize_text_field( wp_unslash( $_REQUEST['trn_date']['from'] ) );
						$end_date                = sanitize_text_field( wp_unslash( $_REQUEST['trn_date']['to'] ) );
						$data[ $key ]['balance'] = $this->get_vendor_store_opening_balance(
							$value['vendor_id'],
							[
								'from' => $start_date,
								'to'   => $end_date,
							],
							true
						);
					}
					continue;
				}
				if ( ! is_numeric( $value['debit'] ) && ! is_numeric( $value['credit'] ) ) {
					continue;
				}
				$debit               = $is_order_commission ? $this->convert_order_value( $value['debit'], $order_id, true ) : $value['debit'];
				$credit              = $is_order_commission ? $this->convert_order_value( $value['credit'], $order_id, true ) : $value['credit'];
				$commission_balance += (float) ( $debit - $credit );
				if ( $is_order_commission ) {
					$should_set_data         = true;
					$data[ $key ]['debit']   = $debit;
					$data[ $key ]['credit']  = $credit;
					$data[ $key ]['balance'] = $commission_balance;
				}
			}
			if ( $should_set_data ) {
				$response->set_data( $data );
			}
		}

		// Reverse Withdrawal Vendor Due Status: Reverse Pay Balance, Pay now button
		// if ( $prefix . 'vendor-due-status' === $rest_route ) {}
	}

	public function filter_dokan_lite_rest_route_response( $rest_route = '', $response = null ) {
		$this->filter_orders_table_response( $rest_route, $response );
		$this->filter_reverse_withdrawal_response( $rest_route, $response );
	}

	public function filter_admin_dashboard_sales_chart_data( $data = array(), $date_range = array() ) {
		if ( ! isset( $data['intervals'] ) ) {
			return $data;
		}

		foreach ( $data['intervals'] as $key => $interval ) {
			$date = ! empty( $interval['date'] ) ? $interval['date'] : '';
			if ( empty( $date ) ) {
				continue;
			}
			$datetime    = new \DateTimeImmutable( $date, wp_timezone() );
			$start_date  = $datetime->setTime( 0, 0, 0 );
			$end_date    = $datetime->setTime( 23, 59, 59 );
			$order_stats = $this->calculate_seller_order_stats(
				dokan_get_current_user_id(),
				[
					'date_start' => $start_date->format( 'Y-m-d H:i:s' ),
					'date_end'   => $end_date->format( 'Y-m-d H:i:s' ),
				]
			);

			$data['intervals'][ $key ]['commissions'] = isset( $order_stats['total_admin_commission'] ) ? (float) $order_stats['total_admin_commission'] : 0;
		}

		return $data;
	}

	public function filter_admin_dashboard_total_commissions( $data = array() ) {
		if ( empty( $data['total_commissions'] ) || empty( $data['total_commissions']['count'] ) ) {
			return $data;
		}
		global $wpdb;
		$total_commissions = 0;
		$results           = $wpdb->get_results( "SELECT ds.* FROM {$wpdb->prefix}dokan_order_stats AS ds" );
		foreach ( $results as $result ) {
			$total_commissions += $this->convert_order_value( $result->admin_commission, $result->order_id, true );
		}

		$data['total_commissions']['count'] = $total_commissions;
		return $data;
	}

	public function admin_dashboard_top_performing_vendors_data( $data = array(), $date = '' ) {
		if ( empty( $date ) ) {
			return $data;
		}
		$result = [];

		$dashboard_stats = new AdminDashboardStatsController();
		$date_range      = $dashboard_stats->parse_date_range( $date );
		$vendors         = AdminDashboardStats::get_top_performing_vendors( $date_range['current_month_start'], $date_range['current_month_end'], 5 );

		// If vendors found, then populate the result array
		if ( ! empty( $vendors ) ) {
			$rank = 0;
			foreach ( $vendors as $vendor ) {
				$vendor_info = dokan()->vendor->get( $vendor['vendor_id'] );
				if ( ! $vendor_info->get_id() ) {
					continue;
				}
				$seller_order_stats = $this->calculate_seller_order_stats(
					$vendor['vendor_id'],
					[
						'date_start' => $date_range['current_month_start'],
						'date_end'   => $date_range['current_month_end'],
					],
					true
				);

				$result[] = [
					'rank'             => ++$rank,
					'vendor_name'      => $vendor_info->get_shop_name(),
					'total_earning'    => isset( $seller_order_stats['total_vendor_earning'] ) ? (float) $seller_order_stats['total_vendor_earning'] : $vendor->total_earning,
					'total_orders'     => (int) $vendor['total_orders'],
					'total_commission' => isset( $seller_order_stats['total_admin_commission'] ) ? (float) $seller_order_stats['total_admin_commission'] : $vendor->total_commission,
				];
			}
		}
		return $result;
	}

	protected function get_order_admin_commission( \WC_Order $order ) {
		try {
			$order_commission = dokan_get_container()->get( OrderCommission::class );
			$order_commission->set_order( $order );
			$order_commission->get();
			return max( 0, $order_commission->get_admin_net_commission() + $order_commission->get_total_admin_fees() );
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	public function format_dokan_admin_commission_column( $output = '', $order = null ) {
		if ( ! $order instanceof \WC_Order || '--' === $output || $this->default_currency === $order->get_currency() ) {
			return $output;
		}
		$order_currency           = $this->apply_default_currency;
		$order_currency['symbol'] = get_woocommerce_currency_symbol( $order->get_currency() );
		return YayCurrencyHelper::formatted_price_by_currency( $this->get_order_admin_commission( $order ), $order_currency );
	}

	// ======= END BACKEND ======= //

	// ======= PRO only =======

	// Report: Statement summary - Total balance, Total debit, Total credit, First entry date
	public function filter_dokan_report_statement_summary_results( $results, $status, $statement_data ) {
		global $wpdb;
		$results  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT dokan_vendor.*
				FROM {$wpdb->prefix}dokan_vendor_balance as dokan_vendor
				WHERE vendor_id = %d
				AND DATE(balance_date) >= %s
				AND DATE(balance_date) <= %s
				",
				$statement_data->get_vendor_id(),
				$statement_data->get_start_date(),
				$statement_data->get_end_date()
			)
		);
		$statuses = dokan_withdraw_get_active_order_status();
		$results  = array_filter(
			$results,
			function ( $value ) use ( $statuses ) {
				return ( 'dokan_orders' === $value->trn_type && in_array( $value->status, $statuses ) ) || in_array( $value->trn_type, array( 'dokan_withdraw', 'dokan_refund', 'gateway_fee' ) );
			},
			ARRAY_FILTER_USE_BOTH
		);

		$total_debit      = 0;
		$total_credit     = 0;
		$first_entry_date = dokan_current_datetime()->format( 'Y-m-d H:i:s' );
		foreach ( $results as $result ) {
			$total_debit     += $this->convert_order_value( $result->debit, $result->trn_id, true );
			$total_credit    += $this->convert_order_value( $result->credit, $result->trn_id, true );
			$first_entry_date = $result->balance_date < $first_entry_date ? $result->balance_date : $first_entry_date;
		}

		return (object) array(
			'total_items'      => count( $results ),
			'total_debit'      => $total_debit,
			'total_credit'     => $total_credit,
			'first_entry_date' => $first_entry_date,
		);
	}

	// Statement data table
	public function filter_dokan_report_statement_processed_entry( $processed_entry, $entry, $balance, $type_data ) {
		if ( ! empty( $entry->trn_id ) ) {
			$debit  = $this->convert_order_value( $entry->debit, $entry->trn_id, true );
			$credit = $this->convert_order_value( $entry->credit, $entry->trn_id, true );

			$processed_entry['debit']   = $debit;
			$processed_entry['credit']  = $credit;
			$processed_entry['balance'] = $debit - $credit;
		}

		return $processed_entry;
	}

	// Statement data table
	public function filter_dokan_report_statement_processed_entries( $processed_entries = array(), $statement_data = null ) {
		if ( ! empty( $processed_entries ) && ! empty( $statement_data ) ) {
			$balance = $statement_data->get_opening_balance();
			foreach ( $processed_entries as $key => $entry ) {
				if ( empty( $entry['trn_type'] ) || 'opening_balance' === $entry['trn_type'] ) {
					continue;
				}
				$balance                             += ( floatval( $entry['debit'] ) - floatval( $entry['credit'] ) );
				$processed_entries[ $key ]['balance'] = $balance;
			}
		}

		return $processed_entries;
	}

	// === Start Backend Pro Only ===

	// Report: Stats - Commission earned
	public function filter_dokan_admin_report_stats_commission_earned( $commission = 0, $start_date = '', $end_date = '', $seller_id = 0 ) {

		$results = $this->get_seller_order_stats_by_balance_date_range(
			$seller_id,
			[
				'date_start' => $start_date,
				'date_end'   => $end_date,
			]
		);

		$total_commission = 0;
		foreach ( $results as $result ) {
			$total_commission += $this->convert_order_value( $result->admin_commission, $result->order_id, true );
		}

		return $total_commission ? $total_commission : $commission;
	}

	// Report: Stats - Overview chart data
	public function filter_dokan_admin_report_stats_overview_chart_data( $data = [], $start_date = '', $end_date = '', $seller_id = 0 ) {
		foreach ( $data as $key => $value ) {
			$date = ! empty( $value['date'] ) ? $value['date'] : '';
			if ( empty( $date ) || ! $value['commissions'] ) {
				continue;
			}
			$datetime                    = new \DateTimeImmutable( $date, wp_timezone() );
			$start_date                  = $datetime->setTime( 0, 0, 0 );
			$end_date                    = $datetime->setTime( 23, 59, 59 );
			$data[ $key ]['commissions'] = $this->filter_dokan_admin_report_stats_commission_earned( $value['commissions'], $start_date->format( 'Y-m-d H:i:s' ), $end_date->format( 'Y-m-d H:i:s' ), $seller_id );
		}
		return $data;
	}

	// Report: Admin Earnings tab

	// Summary --- Admin Earnings
	public function filter_dokan_admin_report_earnings_summary( $summary = [], $full_summary = [], $request = [] ) {
		global $wpdb;
		// Get the order statuses to exclude from the report.
		$exclude_order_statuses = ReportUtil::get_exclude_order_statuses();

		$stats_results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT dos.`order_id`, dos.`order_type`,wos.status as order_status, dos.admin_earning as earnings, dos.admin_commission + CASE WHEN dos.order_type IN (%d, %d, %d, %d) THEN dos.admin_earning ELSE 0 END AS net_earning, 
dos.admin_commission AS commission, CASE WHEN dos.order_type IN (%d, %d) THEN dos.admin_earning ELSE 0 END AS subscription_revenue, CASE WHEN dos.order_type IN (%d, %d) THEN dos.admin_earning ELSE 0 END AS other_revenue
FROM wp_dokan_order_stats dos JOIN wp_wc_order_stats wos ON dos.order_id = wos.order_id',
				OrderType::DOKAN_SUBSCRIPTION_ORDER,
				OrderType::DOKAN_SUBSCRIPTION_REFUND_ORDER,
				OrderType::DOKAN_ADVERTISEMENT_PRODUCT_ORDER,
				OrderType::DOKAN_ADVERTISEMENT_REFUND_ORDER,
				OrderType::DOKAN_SUBSCRIPTION_ORDER,
				OrderType::DOKAN_SUBSCRIPTION_REFUND_ORDER,
				OrderType::DOKAN_ADVERTISEMENT_PRODUCT_ORDER,
				OrderType::DOKAN_ADVERTISEMENT_REFUND_ORDER
			)
		);
		$columns       = array( 'earnings', 'net_earning', 'commission', 'subscription_revenue', 'other_revenue' );
		$totals        = array_fill_keys( $columns, 0 );

		foreach ( $stats_results as $stat ) {
			if ( in_array( $stat->order_status, $exclude_order_statuses ) || empty( $stat->order_id ) ) {
				continue;
			}
			foreach ( $columns as $column ) {
				$totals[ $column ] += $this->convert_order_value( $stat->$column, $stat->order_id, true );
			}
		}

		return [
			'total_earnings'       => $totals['earnings'],
			'net_earning'          => $totals['net_earning'],
			'commission'           => $totals['commission'],
			'subscription_revenue' => $totals['subscription_revenue'],
			'other_revenue'        => $totals['other_revenue'],
		];
	}

	// Filter PRO REST Route Response
	public function filter_dokan_pro_rest_route_response( $rest_route = '', $response = null ) {
		// Admin: All Logs reports (tab) & Earning Overview reports ( Admin Earnings tab)
		if ( '/dokan/v1/admin/report-logs' === $rest_route || '/dokan/v1/admin/report-earnings' === $rest_route ) {
			$data = $response->get_data();
			foreach ( $data as $key => $value ) {
				$order_id = isset( $value['order_id'] ) ? $value['order_id'] : 0;
				foreach ( $this->order_stats_columns as $column ) {
					if ( ! isset( $value[ $column ] ) ) {
						continue;
					}
					$data[ $key ][ $column ] = $this->convert_order_value( $value[ $column ], $order_id, true );
				}
			}

			$response->set_data( $data );
		}

	}

	// === End Backend Pro Only ===

	public function custom_dokan_rest_prepare_vendor_subscription_order( $response = null, $item = null, $request = null ) {
		$request_data  = $item->get_data();
		$response_data = $response->get_data();

		if ( isset( $request_data['currency'] ) && ! empty( $response_data['total'] ) ) {
			$current_currency_apply = YayCurrencyHelper::detect_current_currency();
			if ( ! $current_currency_apply ) {
				return $response;
			}
			$order_currency_apply = YayCurrencyHelper::get_currency_by_currency_code( $request_data['currency'] );
			if ( ! $order_currency_apply ) {
				return $response;
			}
			if ( $current_currency_apply['currency'] === $order_currency_apply['currency'] ) {
				return $response;
			}

			$response_total        = floatval( $response_data['total'] );
			$default_currency_code = Helper::default_currency_code();
			if ( $order_currency_apply['currency'] !== $default_currency_code ) {
				$response_total = YayCurrencyHelper::reverse_calculate_price_by_currency( $response_total, $order_currency_apply );
			}
			if ( $current_currency_apply['currency'] !== $default_currency_code ) {
				$response_total = YayCurrencyHelper::calculate_price_by_currency( $response_total, false, $current_currency_apply );
			}

			$response_data['total'] = $response_total;
			$response->set_data( $response_data );
		};

		return $response;
	}
}
