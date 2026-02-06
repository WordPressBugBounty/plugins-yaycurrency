<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://wordpress.org/plugins/woocommerce-product-addon/

class PPOM {
	use SingletonTrait;

	private $apply_currency = array();
	private $is_dis_checkout_diff_currency;

	public function __construct() {

		if ( ! class_exists( '\NM_PersonalizedProduct' ) ) {
			return;
		}

		$this->apply_currency                = YayCurrencyHelper::detect_current_currency();
		$this->is_dis_checkout_diff_currency = YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency );

		add_filter( 'ppom_option_price', array( $this, 'ppom_option_price' ), 10 );
		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'product_price_3rd_with_condition' ), 10, 2 );
		add_filter( 'YayCurrency/StoreCurrency/GetPrice', array( $this, 'get_price_default_in_checkout_page' ), 10, 2 );
		add_filter( 'yay_currency_is_cart_fees_original', '__return_true' );

		// Revert Fee in Order Details
		if ( $this->is_dis_checkout_diff_currency ) {
			add_action( 'woocommerce_checkout_create_order_fee_item', array( $this, 'woocommerce_checkout_create_order_fee_item' ), 99, 4 );
		}

		add_filter( 'woocommerce_coupon_validate_minimum_amount', array( $this, 'custom_coupon_minimum_amount' ), 10, 3 );
		add_filter( 'woocommerce_coupon_validate_maximum_amount', array( $this, 'custom_coupon_maximum_amount' ), 10, 3 );
	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {
		if ( isset( $cart_item['ppom'] ) ) {
			$product_price         = SupportHelper::calculate_product_price_by_cart_item( $cart_item, $apply_currency );
			$product_price_default = YayCurrencyHelper::reverse_calculate_price_by_currency( $product_price, $apply_currency );

			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_ppom_product_price', $product_price );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_ppom_product_price_default', $product_price_default );
		}
	}

	public function product_price_3rd_with_condition( $price, $product ) {
		$yay_ppom_product_price = SupportHelper::get_cart_item_objects_property( $product, 'yay_ppom_product_price' );
		if ( $yay_ppom_product_price ) {
			return $yay_ppom_product_price;
		}
		return $price;
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

		if ( SupportHelper::detect_original_product_price( false, $total_price, $cart_item['data'] ) ) {
			return $total_price;
		}

		$total_price = YayCurrencyHelper::reverse_calculate_price_by_currency( $total_price );
		return $total_price;
	}

	public function ppom_price_option_meta( $option_meta, $field_meta, $field_price, $option, $qty ) {

		$option_price = isset( $option['price'] ) ? $option['price'] : ( isset( $option['raw_price'] ) ? $option['raw_price'] : ( isset( $option_meta['price'] ) ? $option_meta['price'] : '' ) );
		$field_title  = isset( $field_meta['title'] ) ? stripslashes( $field_meta['title'] ) : '';
		$label_price  = "{$field_title} - " . wc_price( $option_price );

		if ( SupportHelper::detect_original_product_price( false, $option_price, array() ) ) {
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
		//fixed by Joey
		if ( $this->is_dis_checkout_diff_currency && doing_action( 'woocommerce_api_' . \strtolower( 'Eh_PayPal_Express_Payment' ) ) ) {
			return $option_price;
		}
		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $option_price;
		}
		return YayCurrencyHelper::calculate_price_by_currency( $option_price, false, $this->apply_currency );
	}

	public function get_price_default_in_checkout_page( $price, $product ) {
		$yay_ppom_product_price_default = SupportHelper::get_cart_item_objects_property( $product, 'yay_ppom_product_price_default' );
		if ( $yay_ppom_product_price_default ) {
			return $yay_ppom_product_price_default;
		}
		return $price;
	}

	protected function get_ppom_product_price( $product ) {
		$product_price = $this->is_dis_checkout_diff_currency ? SupportHelper::get_cart_item_objects_property( $product, 'yay_ppom_product_price_default' ) : SupportHelper::get_cart_item_objects_property( $product, 'yay_ppom_product_price' );
		return $product_price;
	}

	public function woocommerce_checkout_create_order_fee_item( $item, $fee_key, $fee, $order ) {

		//fixed by Joey
		if ( ! doing_action( 'woocommerce_api_' . \strtolower( 'Eh_PayPal_Express_Payment' ) ) ) {
			return;
		}

		$item_total     = apply_filters( 'yay_currency_revert_price', $fee->total, $this->apply_currency );
		$item_total_tax = apply_filters( 'yay_currency_revert_price', $fee->tax, $this->apply_currency );

		$taxes = $item->get_taxes();
		$item->set_total( $item_total );
		$item->set_total_tax( $item_total_tax );

		if ( $fee->tax_data ) {
			foreach ( $fee->tax_data as $rateId => $tax ) {
				$fee->tax_data[ $rateId ] = apply_filters( 'yay_currency_revert_price', $tax, $this->apply_currency );
			}
			$taxes['total'] = $fee->tax_data;
			$item->set_taxes( $taxes );
		}

		$item->save();
	}

	public function custom_coupon_minimum_amount( $flag, $coupon, $subtotal ) {
		$cart_contents = WC()->cart->get_cart_contents();
		if ( empty( $cart_contents ) ) {
			return $flag;
		}

		foreach ( $cart_contents as $key => $value ) {
			$yay_ppom_product_price = $this->get_ppom_product_price( $value['data'] );
			$coupon_minimum_amount  = $coupon->get_minimum_amount();
			if ( $yay_ppom_product_price && $coupon_minimum_amount > 0 && (float) $yay_ppom_product_price >= (float) $coupon_minimum_amount ) {
				$flag = false;
				break;
			}
		}

		return $flag;
	}

	public function custom_coupon_maximum_amount( $flag, $coupon, $subtotal ) {
		$cart_contents = WC()->cart->get_cart_contents();
		if ( empty( $cart_contents ) ) {
			return $flag;
		}

		foreach ( $cart_contents as $key => $value ) {
			$yay_ppom_product_price = $this->get_ppom_product_price( $value['data'] );
			$coupon_maximum_amount  = $coupon->get_maximum_amount();
			if ( $yay_ppom_product_price && $coupon_maximum_amount > 0 && (float) $yay_ppom_product_price <= (float) $coupon_maximum_amount ) {
				$flag = false;
				break;
			}
		}
		return $flag;
	}
}
