<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://codecanyon.net/item/b2bking-the-ultimate-woocommerce-b2b-plugin/26689576

class B2BKingPro {

	use SingletonTrait;

	private $apply_currency = array();
	public function __construct() {

		if ( ! function_exists( 'b2bking' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();
		add_filter( 'YayCurrency/StoreCurrency/GetPrice', array( $this, 'custom_price_default_in_checkout_page' ), 10, 2 );
		add_filter( 'yay_currency_get_price_by_currency', array( $this, 'custom_price_by_currency' ), 10, 3 );

		add_filter( 'b2bking_currency_converted_price', array( $this, 'custom_b2bking_currency_converted_price' ), 10, 1 );

		add_filter( 'b2bking_disable_dynamic_rules', '__return_true' );
		add_filter( 'b2bking_disable_dynamic_rules_in_cart', '__return_true' );
		add_filter( 'b2bking_disable_dynamic_rule_discount_sale_setting', '__return_true' );
	}

	public function get_price_b2b_account_login( $product ) {
		if ( is_user_logged_in() ) {
			$user_id      = get_current_user_id();
			$account_type = get_user_meta( $user_id, 'b2bking_account_type', true );
			if ( 'subaccount' === $account_type ) {
				$parent_user_id = get_user_meta( $user_id, 'b2bking_account_parent', true );
				$user_id        = $parent_user_id;
			}
			$is_b2b_user          = get_user_meta( $user_id, 'b2bking_b2buser', true );
			$currentusergroupidnr = b2bking()->get_user_group( $user_id );
			if ( 'yes' === $is_b2b_user ) {
				// Search if there is a specific price set for the user's group
				$b2b_price     = b2bking()->tofloat( get_post_meta( $product->get_id(), 'b2bking_regular_product_price_group_' . $currentusergroupidnr, true ) );
				$b2b_saleprice = b2bking()->tofloat( get_post_meta( $product->get_id(), 'b2bking_sale_product_price_group_' . $currentusergroupidnr, true ) );
				return ! empty( $b2b_saleprice ) ? $b2b_saleprice : $b2b_price;
			}
		}
		return false;
	}

	public function get_percent_by_b2b_price( $b2b_price, $product ) {
		if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
			$price_by_tax =
			wc_get_price_including_tax(
				$product,
				array(
					'qty'   => 1,
					'price' => $b2b_price,
				)
			);
		} else {
			$price_by_tax =
			wc_get_price_excluding_tax(
				$product,
				array(
					'qty'   => 1,
					'price' => $b2b_price,
				)
			);
		}
		$percent = $price_by_tax / $b2b_price;
		return $percent;
	}

	public function custom_price_default_in_checkout_page( $price, $product ) {
		$b2b_price = $this->get_price_b2b_account_login( $product );
		if ( $b2b_price ) {
			$per_cent = $this->get_percent_by_b2b_price( $b2b_price, $product );
			$price    = $b2b_price * $per_cent;
		}
		return $price;
	}

	public function custom_price_by_currency( $price, $product, $apply_currency ) {
		// Keep B2BKing tiered pricing in cart untouched.
		if ( $this->is_b2bking_tiered_pricing_in_cart( $product ) ) {
			return $price;
		}

		$b2b_price = $this->get_price_b2b_account_login( $product );
		if ( $b2b_price ) {
			$per_cent          = $this->get_percent_by_b2b_price( $b2b_price, $product );
			$b2b_price_convert = YayCurrencyHelper::calculate_price_by_currency( $b2b_price * $per_cent, false, $apply_currency );
			return $b2b_price_convert / $per_cent;
		}

		return $price;

	}

	private function is_b2bking_tiered_pricing_in_cart( $product ) {
		if ( ! is_object( WC()->cart ) || ! is_user_logged_in() ) {
			return false;
		}

		$user_id      = get_current_user_id();
		$account_type = get_user_meta( $user_id, 'b2bking_account_type', true );
		if ( 'subaccount' === $account_type ) {
			$parent_user_id = get_user_meta( $user_id, 'b2bking_account_parent', true );
			$user_id        = $parent_user_id;
		}

		$currentusergroupidnr = apply_filters( 'b2bking_b2b_group_for_pricing', b2bking()->get_user_group( $user_id ), $user_id, $product->get_id() );
		$price_tiers          = get_post_meta( $product->get_id(), 'b2bking_product_pricetiers_group_' . $currentusergroupidnr, true );

		$grregprice    = get_post_meta( $product->get_id(), 'b2bking_regular_product_price_group_' . $currentusergroupidnr, true );
		$grsaleprice   = get_post_meta( $product->get_id(), 'b2bking_sale_product_price_group_' . $currentusergroupidnr, true );
		$grpriceexists = 'no';
		if ( ! empty( $grregprice ) && b2bking()->tofloat( $grregprice ) !== 0 ) {
			$grpriceexists = 'yes';
		}
		if ( ! empty( $grsaleprice ) && b2bking()->tofloat( $grsaleprice ) !== 0 ) {
			$grpriceexists = 'yes';
		}

		if ( empty( $price_tiers ) && 'no' === $grpriceexists ) {
			$price_tiers = get_post_meta( $product->get_id(), 'b2bking_product_pricetiers_group_b2c', true );
		}

		$price_tiers = b2bking()->convert_price_tiers( $price_tiers, $product );
		if ( empty( $price_tiers ) ) {
			return false;
		}

		$product_id = $product->get_id();
		$quantity   = 0;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( $product_id === $cart_item['product_id'] || $product_id === $cart_item['variation_id'] ) {
				$quantity += apply_filters( 'b2bking_cart_item_quantity_tiers', $cart_item['quantity'], $cart_item['variation_id'], $cart_item['product_id'] );
			}
		}

		if ( apply_filters( 'b2bking_tiered_pricing_uses_total_cart_qty', false ) ) {
			$quantity = 0;
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$quantity += apply_filters( 'b2bking_tiered_pricing_count_total_qty', $cart_item['quantity'], $cart_item );
			}
		}

		if ( 0 === $quantity ) {
			return false;
		}

		$price_tiers = explode( ';', $price_tiers );
		foreach ( $price_tiers as $tier ) {
			$tier_values = explode( ':', $tier );
			if ( count( $tier_values ) > 1 && ! empty( $tier_values[0] ) && (float) $tier_values[0] <= (float) $quantity ) {
				return true;
			}
		}

		return false;
	}

	public function custom_b2bking_currency_converted_price( $price ) {
		return YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
	}
}
