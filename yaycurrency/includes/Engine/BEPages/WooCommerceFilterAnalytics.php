<?php
namespace Yay_Currency\Engine\BEPages;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;


defined( 'ABSPATH' ) || exit;

class WooCommerceFilterAnalytics {

	use SingletonTrait;

	public $default_currency;
	private $converted_currency;
	public function __construct() {

		if ( Helper::convert_orders_to_base() ) {
			add_filter( 'woocommerce_currency_symbol', array( $this, 'change_existing_currency_symbol' ), 999, 2 );
		}

		add_filter( 'woocommerce_analytics_report_should_use_cache', array( $this, 'woocommerce_analytics_report_should_use_cache' ), 20, 2 );

		// convert coupons to default currency
		add_action( 'woocommerce_analytics_update_coupon', array( $this, 'convert_coupons' ), 20, 2 );

		// convert products to default currency
		add_action( 'woocommerce_analytics_update_product', array( $this, 'convert_products' ), 20, 2 );

		// convert tax to default currency
		add_action( 'woocommerce_analytics_update_tax', array( $this, 'convert_tax' ), 20, 2 );

		// convert order stats to default currency
		add_filter( 'woocommerce_analytics_update_order_stats_data', array( $this, 'convert_order_stats_data' ), 20, 2 );

	}


	public function change_existing_currency_symbol( $currency_symbol, $currency ) {

		if ( ! WC()->is_rest_api_request() ) {
			return $currency_symbol;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) && str_contains( sanitize_text_field( $_SERVER['REQUEST_URI'] ), 'wc-analytics' ) ) {
			$currency_symbol = YayCurrencyHelper::get_symbol_by_currency_code( Helper::default_currency_code() );
		}

		return $currency_symbol;
	}

	public function woocommerce_analytics_report_should_use_cache( $flag, $cache_key ) {
		$flag = false;
		return $flag;
	}

	public function convert_coupons( $coupon_id, $order_id ) {
		Helper::order_match_reverted( $order_id );
		Helper::revert_coupon_loop_to_default( $coupon_id, $order_id );
	}

	public function convert_products( $order_item_id, $order_id ) {
		Helper::order_match_reverted( $order_id );
		Helper::revert_product_loop_to_default( $order_item_id, $order_id );
	}

	public function convert_tax( $tax_rate_id, $order_id ) {
		Helper::order_match_reverted( $order_id );
		Helper::revert_tax_loop_to_default( $tax_rate_id, $order_id );
	}

	public function convert_order_stats_data( $order_data, $order ) {
		$order_id = $order->get_id();
		Helper::order_match_reverted( $order_id, $order );
		$rate = Helper::calculate_order_rate( $order_id );
		if ( $rate ) {
			$order_data['total_sales']    = $order_data['total_sales'] / $rate;
			$order_data['tax_total']      = $order_data['tax_total'] / $rate;
			$order_data['shipping_total'] = $order_data['shipping_total'] / $rate;
			$order_data['net_total']      = $order_data['net_total'] / $rate;
		}
		return $order_data;

	}
}
