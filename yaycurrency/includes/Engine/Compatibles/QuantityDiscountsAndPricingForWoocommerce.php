<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://woocommerce.com/products/quantity-discounts-pricing-for-woocommerce/

class QuantityDiscountsAndPricingForWoocommerce {

	use SingletonTrait;

	private $apply_currency = array();
	private $cart_item_from;
	public function __construct() {

		if ( ! class_exists( 'PlugfyQDP_Main_Class_Alpha' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		// Avoid Recalculate again
		remove_action( 'woocommerce_after_cart_item_quantity_update', 'plgfyqdp_alter_price_on_quantity_change' );

		add_filter( 'yay_currency_is_original_product_price', array( $this, 'is_original_product_price' ), 10, 3 );

		add_filter( 'woocommerce_cart_item_price', array( $this, 'custom_woocommerce_cart_item_price' ), PHP_INT_MAX, 3 );

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'subscription_get_price_renew' ), 10, 2 );

	}

	public function is_original_product_price( $flag, $price, $product ) {
		if ( doing_action( 'woocommerce_before_calculate_totals' ) || doing_action( 'woocommerce_after_cart_item_quantity_update' ) ) {
			return true;
		}
		return $flag;
	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {

		if ( isset( $cart_item['new_price'] ) && isset( $cart_item['old_price'] ) && isset( $cart_item['plugify_discount'] ) && ! doing_action( 'woocommerce_after_cart_item_quantity_update' ) ) {
			$old_price = YayCurrencyHelper::calculate_price_by_currency( $cart_item['old_price'], false, $this->apply_currency );
			$new_price = YayCurrencyHelper::calculate_price_by_currency( $cart_item['new_price'], false, $this->apply_currency );

			SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'yay_currency_plgfyqdp_old_price', $old_price );
			SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'yay_currency_plgfyqdp_new_price', $new_price );

		}

	}

	public function custom_woocommerce_cart_item_price( $price, $cart_item, $cart_item_key ) {

		if ( ! function_exists( 'plgfyspi_get_all_gnrl_settttings_onfrnt' ) ) {
			return $price;
		}

		$all_gnrl_plgfyqdp_set = plgfyspi_get_all_gnrl_settttings_onfrnt();
		if ( $all_gnrl_plgfyqdp_set && count( WC()->cart->get_applied_coupons() ) > 0 && isset( $all_gnrl_plgfyqdp_set['plgfqdp_coupon_settings'] ) ) {
			if ( 'plgfqdp_aply_nly_cpn' === $all_gnrl_plgfyqdp_set['plgfqdp_coupon_settings'] ) {
				return $price;
			}
		}

		$product = $cart_item['data'];
		if ( is_cart() ) {

			if ( isset( $cart_item['plugify_discount'] ) && 'valid' === $cart_item['plugify_discount'] ) {
				if ( 'incl' === get_option( 'woocommerce_tax_display_cart' ) && 'yes' === get_option( 'woocommerce_calc_taxes' ) ) {
					if ( wc_get_price_including_tax( $product ) !== $cart_item['old_price_with_tax'] ) {
						$old_price_with_tax = YayCurrencyHelper::calculate_price_by_currency( $cart_item['old_price_with_tax'], false, $this->apply_currency );
						return '<strike>' . wc_price( $old_price_with_tax ) . '</strike> ' . wc_price( wc_get_price_including_tax( $product ) );
					}
				} elseif ( $cart_item['old_price'] !== $cart_item['new_price'] ) {
					$old_price = YayCurrencyHelper::calculate_price_by_currency( $cart_item['old_price'], false, $this->apply_currency );
					$new_price = YayCurrencyHelper::calculate_price_by_currency( $cart_item['new_price'], false, $this->apply_currency );
					return '<strike>' . wc_price( $old_price ) . '</strike> ' . wc_price( $new_price );
				}
			}
		}

		if ( isset( $cart_item['plugify_discount'] ) && 'valid' === $cart_item['plugify_discount'] ) {
			if ( 'incl' === get_option( 'woocommerce_tax_display_cart' ) && 'yes' === get_option( 'woocommerce_calc_taxes' ) ) {
				return wc_price( wc_get_price_including_tax( $product ) );
			} else {
				$new_price = YayCurrencyHelper::calculate_price_by_currency( $cart_item['new_price'], false, $this->apply_currency );
				return wc_price( $new_price );
			}
		}

		return $price;
	}

	public function subscription_get_price_renew( $price, $product ) {

		$yay_check_discount = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_plgfyqdp_new_price' );

		if ( $yay_check_discount ) {
			return $yay_check_discount;
		}

		return $price;
	}
}
