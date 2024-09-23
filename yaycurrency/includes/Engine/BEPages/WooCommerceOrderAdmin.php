<?php

namespace Yay_Currency\Engine\BEPages;

use Yay_Currency\Utils\SingletonTrait;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class WooCommerceOrderAdmin {
	use SingletonTrait;

	private $apply_currency = array();
	private $screen_id      = false;

	public function __construct() {
		add_action( 'current_screen', array( $this, 'get_current_screen' ) );
	}

	public function get_current_screen() {
		$screen = get_current_screen();

		if ( ! isset( $screen->id ) ) {
			return;
		}

		global $pagenow;

		if ( ( 'shop_order' === $screen->id && 'post-new.php' === $pagenow ) || ( 'woocommerce_page_wc-orders' === $screen->id && isset( $_REQUEST['action'] ) && 'new' === $_REQUEST['action'] ) ) {
			$this->screen_id = $screen->id;
			add_action( 'add_meta_boxes', array( $this, 'add_shop_order_meta_boxes' ) );
		}

		// Add Order Info meta boxes
		if ( ( 'shop_order' === $screen->id && 'post.php' === $pagenow ) || ( 'woocommerce_page_wc-orders' === $screen->id && isset( $_REQUEST['action'] ) && 'edit' === $_REQUEST['action'] ) ) {
			$this->screen_id = $screen->id;
			add_action( 'add_meta_boxes', array( $this, 'add_order_info_meta_boxes' ) );
		}

		// Save the Meta field value
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_custom_metabox_manual_order' ), 10, 2 );
		add_action( 'woocommerce_new_order', array( $this, 'handle_manual_new_order_on_admin' ), 10, 2 );

		add_filter( 'woocommerce_analytics_customers_select_query', array( $this, 'customers_select_query' ), 10, 2 );
	}

	public function add_shop_order_meta_boxes() {
		if ( ! $this->screen_id ) {
			return;
		}
		add_meta_box(
			'currency_code_meta_box',
			__( 'Convert to currency', 'yay-currency' ),
			array( $this, 'add_list_currencies_meta_boxes' ),
			$this->screen_id,
			'side',
			'high'
		);
	}

	public function add_list_currencies_meta_boxes( $post ) {

		require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/metaboxes/currencies.php';
	}

	public function save_custom_metabox_manual_order( $order_id, $post ) {

		// check if nonce is set with manual order.
		if ( ! isset( $_POST['yay_currency_manual_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['yay_currency_manual_order_nonce'] ), 'yay-currency-manual-order-nonce' ) || ! isset( $_POST['yay_currency_code'] ) ) {
			return;
		}

		$order         = wc_get_order( $order_id );
		$currency_code = sanitize_text_field( $_POST['yay_currency_code'] );
		$order->set_currency( $currency_code );
		$order->save();
	}

	public function handle_manual_new_order_on_admin( $order_id, $order ) {

		$currency_code = $order->get_currency();

		if ( Helper::default_currency_code() !== $currency_code ) {

			$apply_currency = YayCurrencyHelper::get_currency_by_currency_code( $currency_code );
			$rate_fee       = YayCurrencyHelper::get_rate_fee( $apply_currency );

			self::custom_manual_order_line_items( $order, $apply_currency );
			self::custom_manual_order_fee_lines( $order, $apply_currency );
			self::custom_manual_order_shipping_lines( $order, $apply_currency );
			self::custom_manual_order_tax_lines( $order, $apply_currency );
			self::custom_manual_order_coupon_lines( $order, $apply_currency );

			self::custom_manual_order_totals( $order, $apply_currency );
			$order->set_currency( $currency_code );
			if ( Helper::check_custom_orders_table_usage_enabled() ) {
				$order->add_meta_data( 'yay_currency_order_rate', $rate_fee );
			} else {
				update_post_meta( $order_id, 'yay_currency_order_rate', $rate_fee );
			}
			$order->save();
		}

	}

	protected function custom_manual_order_line_items( $order, $apply_currency ) {
		$line_items = $order->get_items( 'line_item' );

		foreach ( $line_items as $item ) {
			$item_subtotal     = YayCurrencyHelper::calculate_price_by_currency( $item['line_subtotal'], false, $apply_currency );
			$item_subtotal_tax = YayCurrencyHelper::calculate_price_by_currency( $item['line_subtotal_tax'], false, $apply_currency );
			$item_total        = YayCurrencyHelper::calculate_price_by_currency( $item['line_total'], false, $apply_currency );
			$item_total_tax    = YayCurrencyHelper::calculate_price_by_currency( $item['line_tax'], false, $apply_currency );

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

	protected function custom_manual_order_fee_lines( $order, $apply_currency ) {
		$fee_items = $order->get_items( 'fee' );
		foreach ( $fee_items as $fee ) {
			$fee_data       = $fee->get_data();
			$item_total     = YayCurrencyHelper::calculate_price_by_currency( $fee_data['total'], true, $apply_currency );
			$item_total_tax = YayCurrencyHelper::calculate_price_by_currency( $fee_data['total_tax'], true, $apply_currency );
			$taxes          = $fee->get_taxes();
			$fee->set_total( $item_total );
			$fee->set_total_tax( $item_total_tax );

			if ( isset( $taxes['total'] ) ) {
				foreach ( $taxes['total'] as $rateId => $tax ) {
					$taxes['total'][ $rateId ] = YayCurrencyHelper::calculate_price_by_currency( $tax, true, $apply_currency );
				}

				$fee->set_taxes( $taxes );
			}

			$fee->save();

		}
	}

	protected function custom_manual_order_shipping_lines( $order, $apply_currency ) {
		$shipping_items = $order->get_items( 'shipping' );
		foreach ( $shipping_items as $shipping ) {
			// custom shipping total tax
			$shipping_taxes = $shipping->get_taxes();
			if ( isset( $shipping_taxes['total'] ) && $shipping_taxes['total'] ) {
				foreach ( $shipping_taxes['total'] as $rateId => $shipping_tax ) {
					$shipping_taxes['total'][ $rateId ] = YayCurrencyHelper::calculate_price_by_currency( $shipping_tax, true, $apply_currency );
				}
				$shipping->set_taxes( $shipping_taxes );
			}
			// custom shipping total
			$shipping_data  = $shipping->get_data();
			$shipping_total = YayCurrencyHelper::calculate_price_by_currency( $shipping_data['total'], true, $apply_currency );
			$shipping->set_total( $shipping_total );
			$shipping->save();
		}
	}

	protected function custom_manual_order_tax_lines( $order, $apply_currency ) {
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax ) {
			$tax_data           = $tax->get_data();
			$tax_total          = YayCurrencyHelper::calculate_price_by_currency( $tax_data['tax_total'], true, $apply_currency );
			$shipping_tax_total = YayCurrencyHelper::calculate_price_by_currency( $tax_data['shipping_tax_total'], true, $apply_currency );
			$tax->set_tax_total( $tax_total );
			$tax->set_shipping_tax_total( $shipping_tax_total );
			$tax->save();
		}
	}

	protected function custom_manual_order_coupon_lines( $order, $apply_currency ) {
		$coupon_items = $order->get_items( 'coupon' );
		foreach ( $coupon_items as $coupon ) {
			$discount     = YayCurrencyHelper::calculate_price_by_currency( $coupon->get_discount(), true, $apply_currency );
			$discount_tax = YayCurrencyHelper::calculate_price_by_currency( $coupon->get_discount_tax(), true, $apply_currency );
			$coupon->set_discount( $discount );
			$coupon->set_discount_tax( $discount_tax );
			$coupon->save();
		}
	}

	protected function custom_manual_order_totals( $order, $apply_currency ) {
		$shipping_total = YayCurrencyHelper::calculate_price_by_currency( $order->get_shipping_total(), true, $apply_currency );
		$discount_total = YayCurrencyHelper::calculate_price_by_currency( $order->get_discount_total(), true, $apply_currency );
		$discount_tax   = YayCurrencyHelper::calculate_price_by_currency( $order->get_discount_tax(), true, $apply_currency );
		$shipping_tax   = YayCurrencyHelper::calculate_price_by_currency( $order->get_shipping_tax(), true, $apply_currency );

		$order_total = YayCurrencyHelper::calculate_price_by_currency( $order->get_total(), true, $apply_currency );

		$order->set_shipping_total( $shipping_total );
		$order->set_shipping_tax( $shipping_tax );
		$order->set_discount_total( $discount_total );
		$order->set_discount_tax( $discount_tax );
		$order->set_total( $order_total );
	}

	// Order Info
	public function add_order_info_meta_boxes() {
		if ( ! $this->screen_id ) {
			return;
		}
		add_meta_box(
			'currency_code_meta_box',
			__( 'YayCurrency Order Info', 'yay-currency' ),
			array( $this, 'add_order_info_data_meta_boxes' ),
			$this->screen_id,
			'side',
			'high'
		);
	}

	public function add_order_info_data_meta_boxes( $post ) {
		if ( ! $post || ! is_object( $post ) ) {
			return;
		}
		require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/metaboxes/order-info.php';
	}

	public function customers_select_query( $results, $args ) {
		global $wpdb;
		$data = isset( $results->data ) ? $results->data : false;
		if ( $data ) {
			foreach ( $data as $key => $value ) {
				if ( $value['user_id'] ) {
					$response = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT user_id, order_id, parent_id, total_sales
							FROM {$wpdb->prefix}wc_customer_lookup as customer_loop
							LEFT JOIN {$wpdb->prefix}wc_order_stats as order_stats  ON customer_loop.customer_id = order_stats.customer_id AND ( order_stats.status NOT IN ( 'wc-auto-draft','wc-trash','wc-pending','wc-cancelled','wc-failed','wc-checkout-draft' ) )
						WHERE 1=1 AND customer_loop.user_id = %d",
							$value['user_id']
						)
					);

					$total_spend     = 0;
					$total_parent_id = 0;

					foreach ( $response as $value ) {
						if ( isset( $value->total_sales ) && 0 !== $value->total_sales ) {
							$order_id = isset( $value->order_id ) ? $value->order_id : 0;
							$order    = wc_get_order( $order_id );
							if ( ! $order ) {
								continue;
							}
							$total_parent_id += isset( $value->parent_id ) && 0 === intval( $value->parent_id ) ? 1 : 0;
							$order_rate       = Helper::default_currency_code() === $order->get_currency() ? 1 : Helper::get_yay_currency_order_rate( $order_id, $order );
							$total_spend     += $order_rate ? $value->total_sales : $value->total_sales / $order_rate;
						}
					}
					if ( $total_spend ) {
						$results->data[ $key ]['total_spend'] = $total_spend;
						if ( $total_parent_id ) {
							$results->data[ $key ]['avg_order_value'] = $total_spend / $total_parent_id;
						}
					}
				}
			}
		}
		return $results;
	}
}
