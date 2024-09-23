<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://wordpress.org/plugins/tour-booking-manager/

class TravelBooking {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! class_exists( '\TTBM_Woocommerce_Plugin' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'yay_currency_detect_action_args', array( $this, 'yay_currency_detect_action_args' ), 10, 1 );
		add_filter( 'raw_woocommerce_price', array( $this, 'raw_woocommerce_price' ), 10, 2 );
		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 10, 2 );
	}

	public function yay_currency_detect_action_args( $action_args ) {
		$ajax_args   = array( 'get_ttbm_ticket' );
		$action_args = array_unique( array_merge( $action_args, $ajax_args ) );
		return $action_args;
	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {
		$ttbm_ticket_info = isset( $cart_item['ttbm_ticket_info'] ) ? $cart_item['ttbm_ticket_info'] : false;
		if ( $ttbm_ticket_info ) {
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_product_ttbm_booking', 'yes' );
		}
	}

	public function raw_woocommerce_price( $price, $original_price ) {
		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $price;
		}
		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		return $price;
	}

	public function get_price_with_options( $price, $product ) {
		$price_with_options = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_product_ttbm_booking' );
		if ( $price_with_options ) {
			return $product->get_price( 'edit' );
		}
		return $price;
	}
}
