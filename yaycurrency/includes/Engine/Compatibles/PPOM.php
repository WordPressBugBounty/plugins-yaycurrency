<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://wordpress.org/plugins/woocommerce-product-addon/

class PPOM {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! class_exists( '\NM_PersonalizedProduct' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'ppom_option_price', array( $this, 'ppom_option_price' ), 10 );
		add_filter( 'ppom_meta_fields', array( $this, 'ppom_meta_fields' ), 10, 2 );
		add_filter( 'ppom_price_option_meta', array( $this, 'ppom_price_option_meta' ), 10, 5 );
		add_filter( 'ppom_cart_fixed_fee', array( $this, 'ppom_cart_fixed_fee' ) );
		add_filter( 'ppom_cart_line_total', array( $this, 'ppom_cart_line_total' ), 10, 3 );

	}

	public function ppom_meta_fields( $meta_fields, $meta ) {
		foreach ( $meta_fields as $key => $meta_field ) {
			if ( ! empty( $meta_field['price'] ) ) {
				$meta_fields[ $key ]['price'] = YayCurrencyHelper::calculate_price_by_currency( $meta_field['price'], false, $this->apply_currency );
			}
		}
		return $meta_fields;
	}

	public function ppom_cart_line_total( $total_price, $cart_item, $values ) {

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $total_price;
		}

		if ( apply_filters( 'yay_currency_is_original_product_price', false, $total_price, $cart_item['data'] ) ) {
			return $total_price;
		}

		$total_price = YayCurrencyHelper::reverse_calculate_price_by_currency( $total_price );
		return $total_price;
	}

	public function ppom_price_option_meta( $option_meta, $field_meta, $field_price, $option, $qty ) {

		$option_price = isset( $option['price'] ) ? $option['price'] : ( isset( $option['raw_price'] ) ? $option['raw_price'] : ( isset( $option_meta['price'] ) ? $option_meta['price'] : '' ) );
		$field_title  = isset( $field_meta['title'] ) ? stripslashes( $field_meta['title'] ) : '';
		$label_price  = "{$field_title} - " . wc_price( $option_price );
		if ( apply_filters( 'yay_currency_is_original_product_price', false, $option_price, array() ) ) {
			$option_meta['price'] = (float) $option_price / YayCurrencyHelper::get_rate_fee( $this->apply_currency );
		} else {
			$option_meta['price'] = $option_price;
		}

		$option_meta['label_price'] = $label_price;

		return $option_meta;
	}

	public function ppom_cart_fixed_fee( $fee_price ) {
		return YayCurrencyHelper::calculate_price_by_currency( $fee_price, false, $this->apply_currency );
	}

	public function ppom_option_price( $option_price ) {
		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $option_price;
		}
		return YayCurrencyHelper::calculate_price_by_currency( $option_price, false, $this->apply_currency );
	}
}
