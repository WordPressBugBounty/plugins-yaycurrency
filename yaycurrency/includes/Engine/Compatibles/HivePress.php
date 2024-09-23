<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;

use HivePress\Helpers as hp;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://hivepress.io/

class HivePress {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! function_exists( 'hivepress' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'yay_currency_is_original_product_price', array( $this, 'is_original_product_price' ), 10, 3 );

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

		add_filter( 'yay_currency_get_price_default_in_checkout_page', array( $this, 'get_price_default_in_checkout_page' ), 10, 2 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 10, 2 );

		add_filter( 'hivepress/v1/fields/currency/display_value', array( $this, 'custom_hivepress_price_by_currency_type' ), 10, 2 );
		//customize
		add_filter( 'hivepress_item_extra_price', array( $this, 'hivepress_item_extra_price' ), 10, 1 );

		//HivePress Marketplace
		add_filter( 'formatted_woocommerce_price', array( $this, 'formatted_woocommerce_price' ), 10, 6 );

		add_filter( 'yay_currency_get_price_options_by_cart_item', array( $this, 'get_price_options_by_cart_item' ), 10, 5 );
		add_filter( 'yay_currency_get_price_options_default_by_cart_item', array( $this, 'get_price_options_default_by_cart_item' ), 10, 4 );

		add_filter( 'hivepress/v1/forms/booking_make', array( $this, 'alter_booking_make_form' ), PHP_INT_MAX, 2 );
	}

	public function hivepress_item_extra_price( $price_extra ) {
		$price_extra = YayCurrencyHelper::calculate_price_by_currency( $price_extra, false, $this->apply_currency );
		return $price_extra;
	}

	public function is_original_product_price( $flag, $price, $product ) {
		if ( class_exists( '\HivePress\Controllers\Marketplace' ) ) {
			if ( doing_action( 'woocommerce_before_calculate_totals' ) ) {
				return true;
			}
		}
		return $flag;
	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {

		$hp_price_change = isset( $cart_item['hp_price_change'] ) ? $cart_item['hp_price_change'] : false;
		if ( $hp_price_change ) {
			$hp_price_change_by_currency = YayCurrencyHelper::calculate_price_by_currency( $hp_price_change, false, $apply_currency );

			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_hp_price_change_by_currency', $hp_price_change_by_currency );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_hp_price_change_by_default', $hp_price_change );

			$product_id            = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
			$product_price_default = SupportHelper::get_product_price( $product_id );
			$product               = wc_get_product( $product_id );

			$product_price = YayCurrencyHelper::calculate_price_by_currency( $product_price_default, false, $apply_currency );

			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_hp_product_price_by_currency', $product_price + $hp_price_change_by_currency );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_hp_product_price_by_default', $hp_price_change );

		}

	}

	public function get_price_options_by_cart_item( $price_options, $cart_item, $product_id, $original_price, $apply_currency ) {
		$hp_price_change = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_currency_hp_price_change_by_currency' );
		if ( $hp_price_change ) {
			return $hp_price_change;
		}
		return $price_options;
	}

	public function get_price_options_default_by_cart_item( $price_options, $cart_item, $product_id, $original_price ) {
		$hp_price_change_default = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_currency_hp_price_change_by_default' );
		if ( $hp_price_change_default ) {
			return $hp_price_change_default;
		}
		return $price_options;
	}

	public function get_price_default_in_checkout_page( $price, $product ) {
		$hp_product_price_default = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_hp_product_price_by_default' );
		if ( $hp_product_price_default ) {
			return $hp_product_price_default;
		}
		return $price;
	}

	public function get_price_with_options( $price, $product ) {
		$hp_price_change = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_hp_product_price_by_currency' );
		if ( $hp_price_change ) {
			return $hp_price_change;
		}
		return $price;
	}

	public function custom_hivepress_price_by_currency_type( $price, $data ) {
		if ( class_exists( '\HivePress\Controllers\Marketplace' ) ) {
			$rest_route = Helper::get_rest_route_via_rest_api();
			if ( ( $rest_route && str_contains( $rest_route, 'hivepress/v1/listings/' ) ) ) {
				return $price;
			}
		}
		$price = YayCurrencyHelper::calculate_price_by_currency( $data->get_value(), false, $this->apply_currency );
		return YayCurrencyHelper::format_price( $price );
	}

	public function formatted_woocommerce_price( $price_format, $price, $decimals, $decimal_separator, $thousand_separator, $original_price ) {

		if ( doing_filter( 'hivepress/v1/forms/listing_buy' ) || ( doing_filter( 'template_include' ) && isset( $_REQUEST['_extras'] ) && ! empty( $_REQUEST['_extras'] ) ) ) {
			$price        = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
			$price_format = number_format( $price, $this->apply_currency['numberDecimal'], $this->apply_currency['decimalSeparator'], $this->apply_currency['thousandSeparator'] );
		}

		return $price_format;
	}

	protected function get_price_options() {
		$options = [];
		if ( get_option( 'hp_booking_enable_quantity' ) ) {
			$options = [
				''             => esc_html__( 'per place per day', 'hivepress-bookings' ),
				'per_quantity' => esc_html__( 'per place', 'hivepress-bookings' ),
				'per_item'     => esc_html__( 'per day', 'hivepress-bookings' ),
			];
		} else {
			$options[''] = esc_html__( 'per day', 'hivepress-bookings' );
		}
		$options['per_order'] = esc_html__( 'per booking', 'hivepress-bookings' );
		return $options;
	}

	public function alter_booking_make_form( $form_args, $form ) {
		$booking = $form->get_model();
		if ( ! $booking || Helper::default_currency_code() === $this->apply_currency['currency'] ) {
			return $form_args;
		}

		// Get listing.
		$listing = $booking->get_listing();
		if ( ! $listing ) {
			return $form_args;
		}
		// Get listing price extras.
		$listing_price_extras = $listing->get_price_extras();
		if ( ! get_option( 'hp_listing_allow_price_extras' ) || ! $listing_price_extras ) {
			return $form_args;
		}
		// Override price extras with current currency
		foreach ( $listing_price_extras as $index => $item ) {
			$extra_price = YayCurrencyHelper::calculate_price_by_currency( $item['price'], false, $this->apply_currency );
			$extra_label = sprintf(
				/* translators: 1: extra name, 2: extra price. */
				_x( '%1$s (%2$s %3$s)', 'price extra format', 'hivepress-bookings' ),
				$item['name'],
				YayCurrencyHelper::format_price( $extra_price ),
				hp\get_array_value( self::get_price_options(), hp\get_array_value( $item, 'type' ) )
			);
			$form_args['fields']['_extras']['options'][ $index ]['label'] = $extra_label;
		}

		return $form_args;
	}
}
