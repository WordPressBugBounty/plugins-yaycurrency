<?php

namespace Yay_Currency\Engine;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore as OrdersStatsDataStore;

defined( 'ABSPATH' ) || exit;

class Ajax {

	use SingletonTrait;

	public $exchange_rate_api;
	private $converted_currencies = array();
	public function __construct() {

		// Fetch Analytics
		add_action( 'wp_ajax_yayCurrency_sync_orders_revert_to_base', array( $this, 'ajax_handle_sync_orders_revert_to_base' ) );

		add_action( 'wp_ajax_yayCurrency_get_cart_subtotal_default_blocks', array( $this, 'ajax_handle_get_cart_subtotal_blocks' ) );
		add_action( 'wp_ajax_nopriv_yayCurrency_get_cart_subtotal_default_blocks', array( $this, 'ajax_handle_get_cart_subtotal_blocks' ) );
	}

	// Update Order Product Loop
	protected function update_wc_order_product_loop( $order_id ) {
		global $wpdb;
		$product_item = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_order_product_lookup WHERE order_id = %d",
				$order_id
			)
		);
		if ( $product_item ) {
			foreach ( $product_item as $item ) {
				do_action( 'woocommerce_analytics_update_product', $item->order_item_id, $item->order_id );
			}
		}

	}

	// Update Order Coupon Loop
	protected function update_wc_order_coupon_loop( $order_id ) {
		global $wpdb;
		$coupon_item = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_order_coupon_lookup WHERE order_id = %d",
				$order_id,
			)
		);
		if ( $coupon_item ) {
			foreach ( $coupon_item as $item ) {
				do_action( 'woocommerce_analytics_update_coupon', $item->coupon_id, $item->order_id );
			}
		}

	}

	// Update Order Tax Loop
	protected function update_wc_order_tax_loop( $order_id ) {
		global $wpdb;
		$tax_item = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_order_tax_lookup WHERE order_id = %d",
				$order_id
			)
		);
		if ( $tax_item ) {
			foreach ( $tax_item as $item ) {
				do_action( 'woocommerce_analytics_update_tax', $item->tax_rate_id, $item->order_id );
			}
		}

	}

	public function ajax_handle_sync_orders_revert_to_base() {

		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;

		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-admin-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}

		if ( isset( $_POST['_yay_sync'] ) ) {
			$paged           = isset( $_POST['_paged'] ) && ! empty( $_POST['_paged'] ) ? intval( sanitize_text_field( $_POST['_paged'] ) ) : 1;
			$sync_currencies = isset( $_POST['_sync_currencies'] ) && ! empty( $_POST['_sync_currencies'] ) ? map_deep( wp_unslash( $_POST['_sync_currencies'] ), 'sanitize_text_field' ) : array();
			$data            = Helper::get_list_orders_not_revert_to_base( $sync_currencies, $paged );
			if ( isset( $data['results'] ) && $data['results'] ) {
				foreach ( $data['results'] as $order_id ) {
					$order = wc_get_order( $order_id );
					if ( ! $order ) {
						continue;
					}
					Helper::order_match_reverted( $order_id, $order );
					self::update_wc_order_product_loop( $order_id );
					self::update_wc_order_coupon_loop( $order_id );
					self::update_wc_order_tax_loop( $order_id );
					OrdersStatsDataStore::update( $order );
				}
			}

			$args = array(
				'orders' => $data['results'],
			);

			if ( isset( $data['orders'] ) && $data['orders'] ) {
				$args['paged'] = $paged + 1;
			} else {
				update_option( 'yay_currency_orders_synced_to_base', 'yes' );
			}

			wp_send_json_success( $args );
		}

		wp_send_json_error();
	}

	public function ajax_handle_get_cart_subtotal_blocks() {
		check_ajax_referer( 'yay-currency-nonce', 'nonce', true );
		$results                  = array();
		$default_currency         = Helper::default_currency_code();
		$cart_subtotal            = apply_filters( 'YayCurrency/StoreCurrency/GetCartSubtotal', 0 );
		$apply_currency           = YayCurrencyHelper::get_currency_by_currency_code( $default_currency );
		$cart_subtotal            = YayCurrencyHelper::format_price_currency( $cart_subtotal, $apply_currency );
		$currency_symbol          = YayCurrencyHelper::get_symbol_by_currency_code( $default_currency );
		$format                   = YayCurrencyHelper::format_currency_symbol( $apply_currency );
		$formatted_price          = sprintf( $format, '<span class="woocommerce-Price-currencySymbol">' . $currency_symbol . '</span>', $cart_subtotal );
		$cart_subtotal            = '<bdi>' . $formatted_price . '</bdi></span>';
		$results['cart_subtotal'] = $cart_subtotal;
		wp_send_json_success( $results );

	}
}
