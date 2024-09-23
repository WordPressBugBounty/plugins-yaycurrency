<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://woocommerce.com/products/gift-cards/

class WoocommerceGiftCards {
	use SingletonTrait;

	private $apply_currency                = array();
	private $is_dis_checkout_diff_currency = false;
	public function __construct() {

		if ( ! class_exists( 'WC_Gift_Cards' ) ) {
			return;
		}

		$this->apply_currency                = YayCurrencyHelper::detect_current_currency();
		$this->is_dis_checkout_diff_currency = YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency );

		add_filter( 'woocommerce_gc_account_session_timeout_minutes', array( $this, 'woocommerce_gc_account_session_timeout_minutes' ), 10, 1 );
		add_filter( 'woocommerce_gc_gift_card_amount', array( $this, 'woocommerce_order_custom_gift_card_amount' ), 10, 3 );

		add_filter( 'woocommerce_order_item_display_meta_value', array( $this, 'order_item_display_meta_value' ), 9, 3 );

		add_action( 'current_screen', array( $this, 'get_current_screen' ) );
		add_filter( 'formatted_woocommerce_price', array( $this, 'formatted_woocommerce_price' ), 10, 6 );

		// Convert To Default Currency Symbol when Send Mail
		add_filter( 'yay_currency_woocommerce_currency_symbol', array( $this, 'yay_currency_woocommerce_currency_symbol' ), 9999, 3 );

		add_filter( 'woocommerce_gc_gift_cards_array', array( $this, 'woocommerce_gc_gift_cards_array' ), 10, 1 ); // Custom Hook
		add_filter( 'woocommerce_gc_gift_card_usage_data', array( $this, 'woocommerce_gc_gift_card_usage_data' ), 10, 3 ); // Custom Hook
		add_filter( 'woocommerce_gc_gift_card_debit_amount', array( $this, 'woocommerce_gc_gift_card_debit_amount' ), 10, 2 );// Custom Hook
		add_filter( 'woocommerce_gc_gift_card_amount_check_valid_balance', array( $this, 'woocommerce_gc_gift_card_amount_check_valid_balance' ), 10, 1 );// Custom Hook
	}

	public function woocommerce_gc_gift_card_debit_amount( $amount, $order ) {
		if ( ! $this->is_dis_checkout_diff_currency ) {
			$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order->get_id() );
			$amount         = $amount / YayCurrencyHelper::get_rate_fee( $order_currency );
		}

		return $amount;
	}

	public function woocommerce_gc_gift_card_amount_check_valid_balance( $amount ) {
		if ( ! $this->is_dis_checkout_diff_currency ) {
			$amount = $amount / YayCurrencyHelper::get_rate_fee( $this->apply_currency );
		}

		return $amount;
	}

	public function yay_currency_woocommerce_currency_symbol( $currency_symbol, $currency, $apply_currency ) {
		if ( doing_action( 'woocommerce_account_giftcards_endpoint' ) && $this->is_dis_checkout_diff_currency ) {
			$currency_symbol = wp_kses_post( html_entity_decode( $this->apply_currency['symbol'] ) );
		}
		return $currency_symbol;
	}

	public function formatted_woocommerce_price( $price_format, $price, $decimals, $decimal_separator, $thousand_separator, $original_price ) {

		if ( doing_action( 'woocommerce_email_gift_card_html' ) || doing_action( 'woocommerce_gc_send_gift_card_to_customer_notification' ) || doing_action( 'woocommerce_gc_send_gift_card_to_customer' ) || doing_action( 'woocommerce_gc_force_send_gift_card_to_customer_notification ' ) || doing_action( 'woocommerce_gc_schedule_send_gift_card_to_customer_notification' ) ) {
			$order_id = isset( $_REQUEST['post_ID'] ) ? intval( sanitize_text_field( $_REQUEST['post_ID'] ) ) : false;
			if ( $order_id ) {
				$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order_id );
				$price          = YayCurrencyHelper::calculate_price_by_currency( $price, false, $order_currency );
				$price_format   = number_format( $price, $order_currency['numberDecimal'], $order_currency['decimalSeparator'], $order_currency['thousandSeparator'] );
				return $price_format;
			}
		}

		if ( doing_action( 'woocommerce_gc_send_gift_card_to_customer_notification' ) || doing_action( 'woocommerce_gc_send_gift_card_to_customer' ) ) {
			if ( wp_doing_ajax() && isset( $_REQUEST['wc-ajax'] ) && 'checkout' === $_REQUEST['wc-ajax'] ) {
				if ( ! $this->is_dis_checkout_diff_currency ) {
					$price        = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
					$price_format = number_format( $price, $this->apply_currency['numberDecimal'], $this->apply_currency['decimalSeparator'], $this->apply_currency['thousandSeparator'] );
					return $price_format;
				}
			}
		}

		if ( doing_action( 'woocommerce_admin_order_items_after_fees' ) ) {
			$order_id = isset( $_REQUEST['post_ID'] ) ? intval( sanitize_text_field( $_REQUEST['post_ID'] ) ) : false;
			// Maybe cache the object in prop?
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$giftcards = $order->get_items( 'gift_card' );
				if ( $giftcards ) {
					$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order_id );
					foreach ( $giftcards as $id => $giftcard_order_item ) {
						$giftcard = $giftcard_order_item->get_giftcard();

						if ( $giftcard && $giftcard_order_item['amount'] !== $price ) {
							$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $order_currency );
						}
						$price_format = number_format( $price, $order_currency['numberDecimal'], $order_currency['decimalSeparator'], $order_currency['thousandSeparator'] );

					}
				}
			}
		}

		if ( doing_action( 'woocommerce_account_giftcards_endpoint' ) ) {
			$price        = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
			$price_format = number_format( $price, $this->apply_currency['numberDecimal'], $this->apply_currency['decimalSeparator'], $this->apply_currency['thousandSeparator'] );
		}

		return $price_format;
	}

	public function get_current_screen() {
		$screen   = get_current_screen();
		$order_id = false;
		switch ( $screen->id ) {
			case 'shop_order':
				$order_id = isset( $_GET['post'] ) ? sanitize_key( $_GET['post'] ) : false;
				break;
			case 'woocommerce_page_wc-orders':
				$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : false;
				if ( 'edit' === $action ) {
					$order_id = isset( $_GET['id'] ) ? sanitize_key( $_GET['id'] ) : false;
				}
				break;
			default:
				break;
		}

		if ( $order_id ) {
			$order_currency = YayCurrencyHelper::get_order_currency_by_order_id( $order_id );
			if ( $order_currency ) {
				$this->apply_currency = $order_currency;
				add_filter( 'woocommerce_currency_symbol', array( $this, 'change_existing_currency_symbol' ), 10, 2 );
			}
		}

	}

	public function woocommerce_gc_account_session_timeout_minutes( $timeout ) {
		$timeout = 0;
		return $timeout;
	}

	public function woocommerce_order_custom_gift_card_amount( $amount, $product, $order ) {

		if ( $this->is_dis_checkout_diff_currency ) {
			return $amount;
		}

		return $amount / YayCurrencyHelper::get_rate_fee( $this->apply_currency );
	}

	public function woocommerce_gc_gift_cards_array( $args ) {
		if ( $this->is_dis_checkout_diff_currency || YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $args;
		}

		$totals  = isset( $args['totals'] ) && $args['totals'] ? $args['totals'] : false;
		$balance = isset( $args['balance'] ) && $args['balance'] ? $args['balance'] : false;
		if ( $totals ) {
			$available_total = isset( $args['totals']['available_total'] ) && $args['totals']['available_total'] ? $args['totals']['available_total'] : false;
			$pending_total   = isset( $args['totals']['pending_total'] ) && $args['totals']['pending_total'] ? $args['totals']['pending_total'] : false;

			if ( ( $available_total && $pending_total ) || $balance === $available_total ) {
				$available_total                   = YayCurrencyHelper::calculate_price_by_currency( $available_total, false, $this->apply_currency );
				$args['totals']['available_total'] = $available_total > (float) $totals['cart_total'] ? (float) $totals['cart_total'] : $available_total;
			}

			if ( $pending_total ) {
				$args['totals']['pending_total'] = YayCurrencyHelper::calculate_price_by_currency( $pending_total, false, $this->apply_currency );
			}
		}

		if ( $balance ) {
			$args['balance'] = YayCurrencyHelper::calculate_price_by_currency( $balance, false, $this->apply_currency );
		}

		return $args;
	}

	public function woocommerce_gc_gift_card_usage_data( $usage_data, $balance, $covered_balance ) {
		if ( $this->is_dis_checkout_diff_currency || (float) $balance === (float) $covered_balance ) {
			return $usage_data;
		}

		$giftcards = isset( $usage_data['giftcards'] ) ? $usage_data['giftcards'] : false;
		if ( $giftcards ) {
			foreach ( $giftcards as $key => $gift_card ) {
				if ( isset( $gift_card['amount'] ) ) {
					$amount                                    = $gift_card['amount'];
					$usage_data['giftcards'][ $key ]['amount'] = YayCurrencyHelper::calculate_price_by_currency( $amount, false, $this->apply_currency );
				}
			}
		}
		$total_amount = isset( $usage_data['total_amount'] ) ? $usage_data['total_amount'] : false;
		if ( $total_amount ) {
			$total_amount = YayCurrencyHelper::calculate_price_by_currency( $total_amount, false, $this->apply_currency );
			if ( $total_amount > (float) $balance ) {
				$usage_data['total_amount'] = (float) $balance;
			} else {
				$usage_data['total_amount'] = $total_amount;
			}
		}
		return $usage_data;
	}

	public function order_item_display_meta_value( $display_value, $meta = null, $order_item = null ) {

		if ( is_null( $meta ) ) {
			return $display_value;
		}
		if ( 'wc_gc_giftcard_amount' === $meta->key ) {
			$display_value = YayCurrencyHelper::calculate_price_by_currency( $display_value, false, $this->apply_currency );
		}

		return $display_value;
	}

	public function change_existing_currency_symbol( $currency_symbol, $currency ) {
		return Helper::change_existing_currency_symbol( $this->apply_currency, $currency_symbol );
	}
}
