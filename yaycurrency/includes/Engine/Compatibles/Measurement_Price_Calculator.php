<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://woocommerce.com/products/measurement-price-calculator/

class Measurement_Price_Calculator {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! class_exists( 'WC_Measurement_Price_Calculator' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'woocommerce_cart_item_price', array( $this, 'custom_cart_item_price_mini_cart' ), 10000, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'reverse_gift_card_amount_before_add_to_cart' ), PHP_INT_MAX, 3 );
	}

	public function custom_cart_item_price_mini_cart( $price, $cart_item, $cart_item_key ) {
		if ( isset( $cart_item['custom_price'] ) && ! empty( $cart_item['custom_price'] ) ) {
			$custom_price  = $cart_item['custom_price'];
			$convert_price = YayCurrencyHelper::calculate_price_by_currency( $custom_price, false, $this->apply_currency );
			$price         = YayCurrencyHelper::format_price( $convert_price );
		}

		return $price;

	}

	public function reverse_gift_card_amount_before_add_to_cart( $cart_item_data, $product_id, $variation_id ) {

		if ( isset( $cart_item_data['custom_price'] ) ) {
			$cart_item_data['custom_price'] = YayCurrencyHelper::reverse_calculate_price_by_currency( $cart_item_data['custom_price'] );
		}
		if ( isset( $cart_item_data['extra_pack'] ) ) {
			$cart_item_data['extra_pack'] = YayCurrencyHelper::reverse_calculate_price_by_currency( $cart_item_data['extra_pack'] );
		}

		return $cart_item_data;

	}
}
