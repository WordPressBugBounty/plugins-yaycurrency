<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class WoodmartTheme {
	use SingletonTrait;

	private $apply_currency = array();
	private $is_dis_checkout_diff_currency;

	public function __construct() {

		if ( 'woodmart' === wp_get_theme()->template || 'WoodMart Theme/woodmart' === wp_get_theme()->template ) {

			$this->apply_currency                = YayCurrencyHelper::detect_current_currency();
			$this->is_dis_checkout_diff_currency = YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency );

			add_filter( 'yay_currency_get_price_with_conditions', array( $this, 'yay_currency_get_price_with_conditions' ), 10, 3 );

			add_filter( 'yay_currency_detect_action_args', array( $this, 'yay_currency_detect_action_args' ), 10, 1 );

			if ( ! $this->is_dis_checkout_diff_currency ) {
				add_filter( 'yay_currency_get_price_by_currency', array( $this, 'get_round_price_by_currency' ), 10, 3 );
				add_filter( 'woocommerce_cart_subtotal', array( $this, 'woocommerce_cart_subtotal' ), 9999, 3 );
				add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'woodmart_cart_data' ), 9999 );
			}

			add_action( 'woodmart_shipping_progress_bar_amount', array( $this, 'woodmart_yay_currency_convert_price_limit' ), PHP_INT_MAX, 1 );

			if ( class_exists( 'JEMTR_Table_Rate_Shipping_Method' ) ) {
				add_filter( 'woocommerce_cart_get_cart_contents_total', array( $this, 'woocommerce_cart_get_cart_contents_total' ), PHP_INT_MAX, 1 );
				add_filter( 'woocommerce_cart_get_cart_contents_tax', array( $this, 'woocommerce_cart_get_cart_contents_tax' ), PHP_INT_MAX, 1 );
			}
		}

	}

	public function yay_currency_get_price_with_conditions( $price, $product, $apply_currency ) {
		if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'woodmart_ajax_search' === $_REQUEST['action'] ) {
			return false;
		}
		return $price;
	}

	public function woodmart_yay_currency_convert_price_limit( $limit ) {
		$limit = YayCurrencyHelper::calculate_price_by_currency( $limit, false, $this->apply_currency );
		return $limit;
	}

	public function woocommerce_cart_get_cart_contents_total( $total ) {
		if ( Helper::is_method_executed( 'JEMTR_Table_Rate_Shipping_Method', 'calculate_shipping' ) ) {
			$total = $total / YayCurrencyHelper::get_rate_fee( $this->apply_currency );
		}
		return $total;
	}

	public function woocommerce_cart_get_cart_contents_tax( $total_tax ) {
		if ( Helper::is_method_executed( 'JEMTR_Table_Rate_Shipping_Method', 'calculate_shipping' ) ) {
			$total_tax = $total_tax / YayCurrencyHelper::get_rate_fee( $this->apply_currency );
		}
		return $total_tax;
	}

	public function yay_currency_detect_action_args( $action_args ) {
		$woodmart_action = array( 'woodmart_quick_view', 'woodmart_ajax_search', 'woodmart_quick_shop', 'woodmart_update_frequently_bought_price', 'woodmart_ajax_add_to_cart', 'woodmart_get_products_tab_shortcode' );
		$action_args     = array_unique( array_merge( $action_args, $woodmart_action ) );
		return $action_args;
	}


	public function get_round_price( $price ) {
		if ( function_exists( 'round_price_product' ) ) {
			// Return rounded price
			return ceil( $price );
		}

		return $price;
	}

	public function get_round_price_by_currency( $price, $product, $apply_currency ) {
		return $this->get_round_price( $price );
	}

	public function woocommerce_cart_subtotal( $cart_subtotal, $compound, $cart ) {
		WC()->cart->calculate_totals();
		return $cart_subtotal;
	}

	public function woodmart_cart_data( $args ) {
		ob_start();
		woodmart_cart_count();
		$count = ob_get_clean();

		ob_start();
		woodmart_cart_subtotal();
		$subtotal = ob_get_clean();

		if ( apply_filters( 'woodmart_update_fragments_fix', true ) ) {
			$args['span.wd-cart-number_wd']   = $count;
			$args['span.wd-cart-subtotal_wd'] = $subtotal;
		} else {
			$args['span.wd-cart-number']   = $count;
			$args['span.wd-cart-subtotal'] = $subtotal;
		}

		return $args;
	}
}
