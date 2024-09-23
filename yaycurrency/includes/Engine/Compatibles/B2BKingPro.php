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
		add_filter( 'yay_currency_get_price_default_in_checkout_page', array( $this, 'custom_price_default_in_checkout_page' ), 10, 2 );
		add_filter( 'yay_currency_get_price_by_currency', array( $this, 'custom_price_by_currency' ), 10, 3 );
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
		$b2b_price = $this->get_price_b2b_account_login( $product );
		if ( $b2b_price ) {
			$per_cent          = $this->get_percent_by_b2b_price( $b2b_price, $product );
			$b2b_price_convert = YayCurrencyHelper::calculate_price_by_currency( $b2b_price * $per_cent, false, $apply_currency );
			return $b2b_price_convert / $per_cent;
		}

		return $price;

	}
}
