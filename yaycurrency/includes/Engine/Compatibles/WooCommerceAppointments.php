<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class WooCommerceAppointments {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! class_exists( 'WC_Appointments' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();
		// fields
		add_filter( 'woocommerce_appointments_staff_additional_cost_string', array( $this, 'adjust_staff_additional_cost_string' ), 10, 2 );
		// cost
		add_filter( 'appointment_form_calculated_appointment_cost', array( $this, 'adjust_appointment_cost' ), 10, 3 );
		// addon price
		add_filter( 'woocommerce_product_addons_option_price_raw', array( $this, 'adjust_product_addons_option_price_raw' ), 10, 2 );
	}

	public function adjust_staff_additional_cost_string( $additional_cost_string, $staff_member ) {

		$additional_cost = [];

		if ( $staff_member->get_base_cost() ) {
			$base_cost         = (float) $staff_member->get_base_cost();
			$staff_cost        = apply_filters( 'yay_currency_convert_price', $base_cost, $this->apply_currency );
			$formatted_cost    = YayCurrencyHelper::format_price( $staff_cost, $this->apply_currency );
			$additional_cost[] = ' + ' . wp_strip_all_tags( $formatted_cost );
		}

		if ( $additional_cost ) {
				$additional_cost_string = implode( ', ', $additional_cost );
		} else {
				$additional_cost_string = '';
		}

		return $additional_cost_string;
	}

	public function adjust_appointment_cost( $appointment_cost, $product, $posted ) {
		$product_cost          = (float) $product->get_price();
		$original_product_cost = 0 >= $product_cost ? 0 : $product_cost;

		// Product price with no currency changes.
		if ( $product_cost ) {
			$original_product_cost = (float) apply_filters( 'yay_currency_revert_price', $product_cost, $this->apply_currency );
		}

		// Extras price with no currency.
		$original_extra_cost = $appointment_cost - $product_cost;

		// Make sure extras are present.
		if ( ! $original_extra_cost ) {
			$original_extra_cost = 0;
		}
		// Appointment cost with no currency.
		$original_appointment_cost = $original_product_cost + $original_extra_cost;

		// Appointment cost with currency applied.
		$currency_appointment_cost = apply_filters( 'yay_currency_convert_price', $original_appointment_cost, $this->apply_currency );

		// Make sure appointment cost cannot be negative with applied currency.
		if ( 0 > $currency_appointment_cost ) {
			$currency_appointment_cost = 0;
		}

		// Don't discount the price when adding an appointment to the cart.
		if ( doing_action( 'woocommerce_add_cart_item_data' ) ) {
			$currency_appointment_cost = $original_appointment_cost;
		}

		// Make sure price is numeric.
		$currency_appointment_cost = is_numeric( $currency_appointment_cost ) ? $currency_appointment_cost : 0;

		return (float) $currency_appointment_cost;
	}

	public function adjust_product_addons_option_price_raw( $option_price, $option ) {
		if ( ! YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			$option_price = apply_filters( 'yay_currency_convert_price', $option_price, $this->apply_currency );
		}
		return $option_price;
	}
}
