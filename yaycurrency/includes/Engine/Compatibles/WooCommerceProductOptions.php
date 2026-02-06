<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class WooCommerceProductOptions {
	use SingletonTrait;

	public function __construct() {
		if ( ! function_exists( 'Barn2\Plugin\WC_Product_Options\wpo' ) ) {
			return;
		}
		add_filter( 'wc_product_options_cart_price', [ $this, 'convert_cart_price' ], 10, 3 );
		add_filter( 'wc_product_options_choice_label_price', [ $this, 'convert_price' ], 10, 1 );
		add_action( 'wc_product_options_after_cart_item_calculation', [ $this, 'convert_cart_item_price' ], 10, 2 );
	}

	/**
	 * Convert price for YayCurrency.
	 *
	 * @param string|float $price
	 * @param WC_Product $product
	 * @param array $price_data
	 * @return string|float
	 */
	public function convert_cart_price( $price, $product, $price_data ) {
		if ( ! in_array( $price_data['type'], [ 'percentage_inc', 'percentage_dec' ], true ) ) {
			if ( function_exists( 'Yay_Currency\\plugin_init' ) &&
				class_exists( 'Yay_Currency\Helpers\YayCurrencyHelper' ) &&
				method_exists( 'Yay_Currency\Helpers\YayCurrencyHelper', 'calculate_price_by_currency' ) &&
				method_exists( 'Yay_Currency\Helpers\YayCurrencyHelper', 'disable_fallback_option_in_checkout_page' ) &&
				method_exists( 'Yay_Currency\Helpers\YayCurrencyHelper', 'detect_current_currency' )
			) {
				$apply_currency = \Yay_Currency\Helpers\YayCurrencyHelper::detect_current_currency();
				if ( ! empty( $apply_currency ) ) {
					if ( \Yay_Currency\Helpers\YayCurrencyHelper::disable_fallback_option_in_checkout_page( $apply_currency ) ) {
						return $price;
					}
					return \Yay_Currency\Helpers\YayCurrencyHelper::calculate_price_by_currency( $price, false, $apply_currency );
				}
			}
		}

		return $price;
	}

	/**
	 * Convert price.
	 *
	 * @param string|float $price
	 * @return string|float
	 */
	public function convert_price( $price ) {
		if ( function_exists( 'Yay_Currency\\plugin_init' ) &&
			class_exists( 'Yay_Currency\Helpers\YayCurrencyHelper' ) &&
			method_exists( 'Yay_Currency\Helpers\YayCurrencyHelper', 'calculate_price_by_currency' ) &&
			method_exists( 'Yay_Currency\Helpers\YayCurrencyHelper', 'disable_fallback_option_in_checkout_page' ) &&
			method_exists( 'Yay_Currency\Helpers\YayCurrencyHelper', 'detect_current_currency' )
		) {
			$apply_currency = \Yay_Currency\Helpers\YayCurrencyHelper::detect_current_currency();
			if ( ! empty( $apply_currency ) ) {
				if ( \Yay_Currency\Helpers\YayCurrencyHelper::disable_fallback_option_in_checkout_page( $apply_currency ) ) {
					return $price;
				}
				return \Yay_Currency\Helpers\YayCurrencyHelper::calculate_price_by_currency( $price, false, $apply_currency );
			}
		}
		return $price;
	}

	/**
	 * Convert cart item price.
	 *
	 * @param array $cart
	 * @param array $cart_item
	 * @return void
	 */
	public function convert_cart_item_price( $cart, $cart_item ) {
		$product = $cart_item['data'];
		$price   = $cart_item['data']->get_price( 'original' );

		$apply_currency = \Yay_Currency\Helpers\YayCurrencyHelper::detect_current_currency();
		if ( \Yay_Currency\Helpers\YayCurrencyHelper::disable_fallback_option_in_checkout_page( $apply_currency ) ) {
			$product->set_price( $this->convert_price( $price ) );
		} else {
			$product->set_price( apply_filters( 'yay_currency_revert_price', $price ) );
		}
	}
}
