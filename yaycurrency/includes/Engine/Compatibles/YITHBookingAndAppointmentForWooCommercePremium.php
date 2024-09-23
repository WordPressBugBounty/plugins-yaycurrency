<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Helpers\YayCurrencyHelper;
defined( 'ABSPATH' ) || exit;

// Link plugin: https://yithemes.com/themes/plugins/yith-woocommerce-booking/

class YITHBookingAndAppointmentForWooCommercePremium {
	use SingletonTrait;


	private $apply_currency = array();

	public function __construct() {
		if ( ! defined( 'YITH_WCBK_VERSION' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'yay_currency_is_original_product_price', array( $this, 'is_original_product_price' ), 10, 3 );
		add_filter( 'yay_currency_get_product_price_default_by_cart_item', array( $this, 'get_product_price_default_by_cart_item' ), 10, 2 );
		add_filter( 'yay_currency_get_product_price_by_cart_item', array( $this, 'get_product_price_by_cart_item' ), 10, 3 );

		add_filter( 'yith_wcbk_booking_product_get_price', array( $this, 'convert_currency_price' ), 10, 2 );
		add_filter( 'yith_wcbk_get_price_to_display', array( $this, 'convert_currency_price' ), 10, 2 ); // For totals.
		add_filter( 'yith_wcbk_booking_product_get_deposit', array( $this, 'convert_currency_price' ), 10, 2 ); // For bookings purchased with deposit.

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'woocommerce_add_cart_item_data' ), PHP_INT_MAX, 3 );

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

	}

	public function is_original_product_price( $flag, $price, $product ) {
		$yay_currency_product_is_yith_booking = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_product_is_yith_booking' );
		if ( $yay_currency_product_is_yith_booking ) {
			$flag = true;
		}
		return $flag;
	}

	public function get_product_price_default_by_cart_item( $price, $cart_item ) {
		$yith_booking_default_price = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_currency_yith_booking_default_price' );
		if ( $yith_booking_default_price ) {
			return $yith_booking_default_price;
		}
		return $price;
	}

	public function get_product_price_by_cart_item( $price, $cart_item, $apply_currency ) {
		$yith_booking_current_price = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_currency_yith_booking_current_price' );
		if ( $yith_booking_current_price ) {
			return $yith_booking_current_price;
		}
		return $price;
	}

	public function convert_currency_price( $price, $product = false ) {

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $price;
		}

		$product = $product instanceof \WC_Product ? $product : false;

		if ( ! apply_filters( 'yith_wcbk_process_multi_currency_price_for_product', true, $product ) ) {
			return $price;
		}

		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		return $price;
	}

	public function woocommerce_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( isset( $cart_item_data['yith_booking_data'] ) && ! empty( $cart_item_data['yith_booking_data'] ) ) {
			$cart_item_data['apply_currency_added'] = $this->apply_currency;
		}
		return $cart_item_data;
	}

	public function product_addons_set_cart_contents( $cart_contents, $cart_item_key, $cart_item, $apply_currency ) {
		if ( isset( $cart_item['apply_currency_added'] ) && ! empty( $cart_item['apply_currency_added'] ) ) {
			$default_price = $cart_item['data']->get_price( 'edit' );
			$current_price = YayCurrencyHelper::calculate_price_by_currency( $default_price, false, $this->apply_currency );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_currency_product_is_yith_booking', 'yes' );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_currency_yith_booking_current_price', $current_price );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_currency_yith_booking_default_price', $default_price );
		}
	}
}
