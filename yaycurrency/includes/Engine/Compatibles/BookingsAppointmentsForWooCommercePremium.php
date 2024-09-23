<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;


defined( 'ABSPATH' ) || exit;

// Link plugin: https://www.pluginhive.com/product/woocommerce-booking-and-appointments/

class BookingsAppointmentsForWooCommercePremium {
	use SingletonTrait;

	private $apply_currency = array();
	protected $offer_controller;

	public function __construct() {

		if ( ! defined( 'PH_BOOKINGS_PLUGIN_VERSION' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'ph_bookings_get_client_currency', array( $this, 'ph_bookings_get_client_currency' ), 99, 1 );
		add_filter( 'phive_booking_cost', array( $this, 'phive_booking_cost' ), 99, 4 );

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data_booking_infos_with_cart_item' ), 10, 3 );
		add_action( 'woocommerce_get_cart_item_from_session', array( $this, 'woocommerce_get_cart_item_from_session' ), 20, 3 );
	}

	public function ph_bookings_get_client_currency( $currency ) {
		if ( ! $this->apply_currency || ! isset( $this->apply_currency['currency'] ) ) {
			return $currency;
		}
		return $this->apply_currency['currency'];
	}

	public function phive_booking_cost( $asset_applied_price, $product, $customer_choosen_values, $booking_data ) {
		if ( ! $this->apply_currency || ! isset( $this->apply_currency['currency'] ) ) {
			return $asset_applied_price;
		}
		return YayCurrencyHelper::calculate_price_by_currency( $asset_applied_price, false, $this->apply_currency );
	}

	public function add_cart_item_data_booking_infos_with_cart_item( $cart_item_data, $product_id, $variation_id ) {
		if ( isset( $cart_item_data['phive_booked_price'] ) ) {
			$cart_item_data['phive_booked_price_yay_currency_added'] = $this->apply_currency;
		}
		return $cart_item_data;
	}

	public function woocommerce_get_cart_item_from_session( $cart_item ) {
		if ( isset( $cart_item['phive_booked_price'] ) ) {
			$booked_price                          = isset( $cart_item['phive_booked_price'] ) && ! empty( $cart_item['phive_booked_price'] ) ? $cart_item['phive_booked_price'] : false;
			$phive_booked_price_yay_currency_added = isset( $cart_item['phive_booked_price_yay_currency_added'] ) && ! empty( $cart_item['phive_booked_price_yay_currency_added'] ) ? $cart_item['phive_booked_price_yay_currency_added'] : false;
			if ( $booked_price && $phive_booked_price_yay_currency_added ) {
				$booked_price_default = $booked_price / YayCurrencyHelper::get_rate_fee( $phive_booked_price_yay_currency_added );
				if ( $this->apply_currency['currency'] !== $phive_booked_price_yay_currency_added['currency'] ) {
					$booked_price = YayCurrencyHelper::calculate_price_by_currency( $booked_price_default, false, $this->apply_currency );
				}
			}
			$cart_item['data']->set_price( $booked_price );
		}
		return $cart_item;
	}
}
