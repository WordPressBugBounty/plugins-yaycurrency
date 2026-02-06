<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Utils\SingletonTrait;

use Yay_Currency\Helpers\YayCurrencyHelper;


defined( 'ABSPATH' ) || exit;

class FunnelKitAutomations {
	use SingletonTrait;

	public function __construct() {

		if ( ! defined( 'BWFAN_BWF_VERSION' ) ) {
			return;
		}

		$transient_args = array( 'bwfan_dashboard_report_lite', 'bwfan_dashboard_report_pro' );
		// Ignore cache
		foreach ( $transient_args as $transient ) {
			add_filter( "transient_{$transient}", array( $this, 'yay_currency_ignore_transient_cache' ), 10, 2 );
		}

		add_filter( 'yay_currency_woocommerce_currency_symbol', array( $this, 'custom_currency_symbol' ), 10, 3 );

		// Admin
		add_filter( 'YayCurrency/Admin/GetLocalizeArgs', array( $this, 'admin_localize_args' ), 10, 1 );
		add_action( 'wp_ajax_yay_bwf_admin_recalculate_revenue', array( $this, 'ajax_recalculate_revenue' ) );
		add_action( 'wp_ajax_nopriv_yay_bwf_admin_recalculate_revenue', array( $this, 'ajax_recalculate_revenue' ) );
		add_filter( 'bwfan_get_price_format_cart', array( $this, 'bwfan_get_price_format_cart' ), 10, 2 );
		add_filter( 'bwfan_get_contacts', array( $this, 'bwfan_get_contacts' ), 10, 1 );

	}

	public function yay_currency_ignore_transient_cache( $value, $transient ) {
		return false;
	}

	public function custom_currency_symbol( $symbol, $currency, $apply_currency ) {
		$query_vars = isset( $GLOBALS['wp']->query_vars ) && ! empty( $GLOBALS['wp']->query_vars ) ? $GLOBALS['wp']->query_vars : false;
		$rest_route = $query_vars && ! empty( $query_vars['rest_route'] ) ? $query_vars['rest_route'] : false;

		if ( ! $rest_route ) {
			return $symbol;
		}

		$args = array( '/autonami-app/dashboard', '/autonami-app/carts/recovered', '/autonami-app/carts/recoverable', '/autonami-app/carts/lost' );

		if ( in_array( $rest_route, $args ) ) {
			$symbol = YayCurrencyHelper::get_symbol_by_currency_code( $currency );
		}

		return $symbol;
	}

	public function bwfan_get_price_format_cart( $format, $currency_code ) {
		$apply_currency = \Yay_Currency\Helpers\YayCurrencyHelper::get_currency_by_currency_code( $currency_code );

		$format = YayCurrencyHelper::format_currency_symbol( $apply_currency );

		return $format;
	}

	protected function get_total_order_value_by_customer_id( $customer_id ) {
		global $wpdb;
		$total_order_value = 0;
		$results           = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT posts.ID as order_id FROM {$wpdb->posts} AS posts 
                WHERE posts.post_type = 'shop_order' AND posts.ID in (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_woofunnel_cid' AND meta_value = %s ) ",
				$customer_id
			)
		);
		foreach ( $results as $result ) {
			$order_id = isset( $result->order_id ) ? $result->order_id : false;
			$order    = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			$order_rate         = Helper::get_yay_currency_order_rate( $order_id, $order );
			$order_value        = floatval( $order->get_total() / $order_rate );
			$total_order_value += $order_value;
		}

		return $total_order_value;
	}

	protected function convert_price_to_default_currency( $price, $apply_default_currency ) {
		$price           = YayCurrencyHelper::format_price_currency( $price, $apply_default_currency );
		$format          = YayCurrencyHelper::format_currency_symbol( $apply_default_currency );
		$formatted_price = sprintf( $format, '<span class="woocommerce-Price-currencySymbol">' . $apply_default_currency['symbol'] . '</span>', $price );
		$return          = '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted_price . '</bdi></span>';
		return $return;
	}

	public function bwfan_get_contacts( $results ) {
		$contacts = isset( $results['contacts'] ) && ! empty( $results['contacts'] ) ? $results['contacts'] : false;
		if ( $contacts ) {
			$default_apply_currency = YayCurrencyHelper::get_currency_by_currency_code( Helper::default_currency_code() );
			foreach ( $contacts as $key => $contact ) {
				if ( ! isset( $contact['id'] ) ) {
					continue;
				}
				$total_order_value                                = self::convert_price_to_default_currency( self::get_total_order_value_by_customer_id( $contact['id'] ), $default_apply_currency );
				$results['contacts'][ $key ]['total_order_value'] = $total_order_value;
			}
		}
		return $results;
	}

	public function admin_localize_args( $localize_args ) {
		$localize_args['funnel_kit_automation']                     = 'yes';
		$localize_args['fkit_automation_customer_contact_purchase'] = isset( $_GET['page'] ) && isset( $_GET['path'] ) && 'autonami' === $_GET['page'] && '/contact/3/purchase' === $_GET['path'] ? 'yes' : 'no';
		$localize_args['fkit_automation_customer_contact_page']     = isset( $_GET['page'] ) && isset( $_GET['path'] ) && 'autonami' === $_GET['page'] && '/contacts' === $_GET['path'] ? 'yes' : 'no';
		$localize_args['fkit_automation_bwf_analytics_area']        = '.bwf-c-s-tab-cont[data-tab="purchase"] .bwf-analytics-card';
		$localize_args['fkit_automation_bwf_purchase_tab']          = '.bwf-c-s-menu_item a';
		$localize_args['fkit_automation_menu_page']                 = '#toplevel_page_autonami';
		$localize_args['fkit_default_symbol']                       = YayCurrencyHelper::get_symbol_by_currency_code( Helper::default_currency_code() );
		return $localize_args;
	}

	public function ajax_recalculate_revenue() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;

		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-admin-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}

		$orderID = isset( $_POST['orderID'] ) ? sanitize_text_field( $_POST['orderID'] ) : 0;
		if ( ! $orderID ) {
			wp_send_json_error();
		}

		$order                = wc_get_order( $orderID );
		$order_total          = $order->get_total();
		$currency_code        = $order->get_currency();
		$order_apply_currency = YayCurrencyHelper::get_currency_by_currency_code( $currency_code );
		$format_order_total   = YayCurrencyHelper::calculate_custom_price_by_currency_html( $order_apply_currency, $order_total );
		wp_send_json_success(
			array(
				'fkit_revenue' => $format_order_total,
			)
		);
	}
}
