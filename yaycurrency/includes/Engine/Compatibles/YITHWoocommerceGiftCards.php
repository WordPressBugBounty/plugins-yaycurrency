<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://wordpress.org/plugins/yith-woocommerce-gift-cards/

class YITHWoocommerceGiftCards {
	use SingletonTrait;

	private $apply_currency = array();
	public function __construct() {

		if ( ! defined( 'YITH_YWGC_VERSION' ) || apply_filters( 'yay_currency_disable_convert_ywgc_amount', false ) ) {
			return;
		}
		$this->apply_currency = YayCurrencyHelper::detect_current_currency();
		add_filter( 'yith_ywgc_gift_card_amounts', array( $this, 'custom_gift_cards_price_in_product_page' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'reverse_gift_card_amount_before_add_to_cart' ), PHP_INT_MAX, 3 );
		add_filter( 'yay_currency_get_product_price_by_cart_item', array( $this, 'get_cart_item_price_3rd_plugin' ), 10, 3 );
	}

	public function custom_gift_cards_price_in_product_page( $amount ) {

		$converted_amount = array_map(
			function ( $amount_item ) {
				return YayCurrencyHelper::calculate_price_by_currency( $amount_item, false, $this->apply_currency );
			},
			$amount
		);

		return $converted_amount;
	}

	public function reverse_gift_card_amount_before_add_to_cart( $cart_item_data, $product_id, $variation_id ) {

		if ( isset( $cart_item_data['ywgc_amount'] ) ) {
			$cart_item_data['ywgc_amount'] = YayCurrencyHelper::reverse_calculate_price_by_currency( $cart_item_data['ywgc_amount'] );
		}

		return $cart_item_data;

	}

	public function get_cart_item_price_3rd_plugin( $product_price, $cart_item, $apply_currency ) {

		if ( isset( $cart_item['ywgc_amount'] ) ) {
			$product_price = YayCurrencyHelper::calculate_price_by_currency( $cart_item['ywgc_amount'], false, $apply_currency );
		}

		return $product_price;

	}
}
