<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;

class Barn2WooCommerceDiscountManager {
	use SingletonTrait;

	public function __construct() {
		if ( ! function_exists( 'Barn2\Plugin\Discount_Manager\wdm' ) ) {
			return;
		}

		add_filter( 'yay_currency_is_original_product_price', array( $this, 'yay_currency_is_original_product_price' ), 10, 3 );
	}
	public function yay_currency_is_original_product_price( $is_original_product_price, $price, $product ) {
		if ( doing_filter( 'woocommerce_get_price_html' ) ) {
			$changes = $product->get_changes();
			if ( is_array( $changes ) && isset( $changes['price'] ) && $price === $changes['price'] ) {
				$is_original_product_price = true;
			}
		}
		return $is_original_product_price;
	}
}
