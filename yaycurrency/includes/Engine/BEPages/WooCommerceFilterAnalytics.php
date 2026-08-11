<?php
namespace Yay_Currency\Engine\BEPages;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Automattic\WooCommerce\Utilities\OrderUtil;


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

		// update order stats currency
		add_action( 'woocommerce_analytics_update_order_stats', array( $this, 'update_order_stats_currency' ), 20, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'woocommerce_admin_enqueue_scripts' ) );
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

	/**
	 * Get number of items sold among all orders.
	 *
	 * @param WC_Order|WC_Order_Refund $order WC_Order or WC_Order_Refund object.
	 * @return int
	 */
	protected static function get_num_items_sold( $order ) {
		$num_items  = 0;
		$line_items = $order->get_items( \Automattic\WooCommerce\Enums\OrderItemType::LINE_ITEM );
		foreach ( $line_items as $line_item ) {
			$num_items += $line_item->get_quantity();
		}
		return $num_items;
	}

	/**
	 * Whether this refund is a single lump-sum refund for the full order (e.g. status set to refunded without line items).
	 *
	 * @param WC_Order_Refund|OrderRefund|Order $refund        Refund order.
	 * @param WC_Order|Order                    $parent_order Parent order (not a refund).
	 * @return bool
	 */
	protected static function should_split_full_refund_using_parent_order( $refund, $parent_order ) {
		// The parent must be the original order, not another refund.
		if ( ! $parent_order instanceof \WC_Order || 'shop_order_refund' === $parent_order->get_type() ) {
			return false;
		}

		if ( self::get_num_items_sold( $refund ) > 0 ) {
			return false;
		}

		$parent_refunds = $parent_order->get_refunds();
		if ( 1 !== count( $parent_refunds ) ) {
			return false;
		}

		$refund_total = wc_format_decimal( abs( (float) $refund->get_total() ) );
		$order_total  = wc_format_decimal( (float) $parent_order->get_total() );

		return $refund_total === $order_total;
	}

	/**
	 * Update order stats currency
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function update_order_stats_currency( $order_id = 0 ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || 'shop_order_refund' !== $order->get_type() || Helper::default_currency_code() === $order->get_currency() ) {
			return;
		}

		$parent_order = wc_get_order( $order->get_parent_id() );

		if ( $parent_order && ! $parent_order instanceof \WC_Order_Refund ) {
			$refund_type               = $order->get_meta( '_refund_type' );
			$uses_new_full_refund_data = OrderUtil::uses_new_full_refund_data();
			$use_parent_refund_amounts = $uses_new_full_refund_data && ( 'full' === $refund_type || self::should_split_full_refund_using_parent_order( $order, $parent_order ) );
			if ( $use_parent_refund_amounts ) {
				global $wpdb;
				$order_rate = Helper::get_yay_currency_order_rate( $order->get_parent_id(), $parent_order );
				if ( $order_rate ) {
					$tax_total      = -1 * floatval( $parent_order->get_total_tax() / $order_rate );
					$order_total    = -1 * floatval( $parent_order->get_total() / $order_rate );
					$shipping_total = -1 * floatval( $parent_order->get_shipping_total() / $order_rate );
					$wpdb->update(
						$wpdb->prefix . 'wc_order_stats',
						array(
							'tax_total'      => $tax_total,
							'shipping_total' => $shipping_total,
							'net_total'      => $order_total - $tax_total - $shipping_total,
						),
						array( 'order_id' => $order_id ),
						array( '%f', '%f', '%f' ),
						array( '%d' )
					);
				}
			}
		}

	}

	public function woocommerce_admin_enqueue_scripts( $hook_suffix ) {
		if ( 'woocommerce_page_wc-admin' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_script( 'yay-currency-woocommerce-admin-script', YAY_CURRENCY_PLUGIN_URL . 'src/admin/script.js', array( 'jquery' ), YAY_CURRENCY_VERSION, true );
		wp_localize_script(
			'yay-currency-woocommerce-admin-script',
			'yayCurrencyWooCommerceAdmin',
			array(
				'sync_orders'     => array(
					'reverted'      => apply_filters( 'yay_currency_convert_all_orders_to_base', get_option( 'yay_currency_orders_synced_to_base', 'no' ) ),
					'notice_title'  => __( 'YayCurrency database update', 'yay-currency' ),
					'notice_desc'   => __( 'Recommended: You can force a database update for past orders so that the revenue recorded in different currencies will be recorded in your default currency. This action will convert the sales based on the current exchange rate.', 'yay-currency' ),
					'notice_button' => __( 'Convert all orders', 'yay-currency' ),
				),
				'sync_currencies' => $this->get_sync_currencies(),
				'nonce'           => wp_create_nonce( 'yay-currency-woocommerce-admin-nonce' ),
			)
		);
	}

	protected function get_sync_currencies() {
		$default_currency = Helper::default_currency_code();
		$currencies       = Helper::woo_list_currencies();
		unset( $currencies[ $default_currency ] );
		return apply_filters( 'yay_currency_sync_currencies', array_keys( $currencies ) );
	}
}
