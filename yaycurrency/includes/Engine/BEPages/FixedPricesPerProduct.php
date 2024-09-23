<?php

namespace Yay_Currency\Engine\BEPages;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

class FixedPricesPerProduct {

	use SingletonTrait;

	private $currencies = null;

	protected function __construct() {
		if ( 1 !== intval( get_option( 'isShowRecommendations', '1' ) ) ) {
			return;
		}
		$this->currencies = array(
			array(
				'currency' => 'USD',
			),
			array(
				'currency' => 'GBP',
			),
			array(
				'currency' => 'EUR',
			),
			array(
				'currency' => 'INR',
			),
		);
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'custom_fixed_prices_input_single_product' ) );
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'custom_fixed_prices_input_variable_product' ), 10, 3 );

	}

	public function custom_fixed_prices_input_single_product() {
		global $product_object;

		if ( in_array( $product_object->get_type(), array( 'simple', 'subscription' ) ) ) {

			echo '<div class="yay-currency-product-custom-fixed-prices-simple">';
			echo '<div class="yay-currency-fixed-price-checkbox-wrapper">';
			woocommerce_wp_checkbox(
				array(
					'id'       => 'fixed_price_simple_checkbox',
					'label'    => 'Fixed price for each currency',
					'desc_tip' => 'true',
				)
			);
			echo '</div>';
			echo '<i class="checkbox-sub-text">You can manually set fixed price for each currency. Leave blank to get the rate automatically.</i>';
			echo '<div class="yay-currency-fixed-prices-input-wrapper">';
			echo '<div class="yay-currency-modal-wrapper">';
			echo '<div class="yay-currency-locked-modal">';
			echo '<svg width="32" height="32" xmlns="http://www.w3.org/2000/svg"><path d="M16 21.915v2.594a.5.5 0 0 0 1 0v-2.594a1.5 1.5 0 1 0-1 0zM9 14v-3.5a7.5 7.5 0 1 1 15 0V14c1.66.005 3 1.35 3 3.01v9.98A3.002 3.002 0 0 1 23.991 30H9.01A3.008 3.008 0 0 1 6 26.99v-9.98A3.002 3.002 0 0 1 9 14zm3 0v-3.5C12 8.01 14.015 6 16.5 6c2.48 0 4.5 2.015 4.5 4.5V14h-9z" fill="#c2cdd4" fill-rule="evenodd"/></svg>';
			echo '<p>This feature is available in YayCurrency Pro version</p>';
			echo '<a href="https://yaycommerce.com/yaycurrency-woocommerce-multi-currency-switcher/" target="_blank" class="button button-primary">Unlock this feature</a>';
			echo '</div>';
			echo '</div>';
			echo '<div class="yay-currency-inputs">';
			foreach ( array_slice( $this->currencies, 0, 3 ) as $currency ) {

				echo '<div class="yay-currency-fixed-prices-input">';
				woocommerce_wp_text_input(
					array(
						'id'                => "regular_price_{$currency['currency']}",
						'placeholder'       => 'Auto',
						'label'             => 'Regular Price (' . $currency['currency'] . ')',
						'desc_tip'          => 'true',
						'custom_attributes' => array( 'disabled' => 'disabled' ),
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'                => "sale_price_{$currency['currency']}",
						'placeholder'       => 'Auto',
						'label'             => 'Sale Price (' . $currency['currency'] . ')',
						'desc_tip'          => 'true',
						'custom_attributes' => array( 'disabled' => 'disabled' ),
					)
				);
				echo '</div>';
			}
			echo '</div>';
			echo '</div>';
			echo '</div>';
		}

	}

	public function custom_fixed_prices_input_variable_product( $index, $variation_data, $variation ) {
		echo '<div class="yay-currency-product-custom-fixed-prices-variable">';
		echo '<div class="yay-currency-fixed-price-checkbox-wrapper">';
		woocommerce_wp_checkbox(
			array(
				'id'       => 'fixed_price_variable_checkbox',
				'label'    => 'Fixed price for each currency',
				'desc_tip' => 'true',
			)
		);
		echo '</div>';
		echo '<i class="checkbox-sub-text">You can manually set fixed price for each currency. Leave blank to get the rate automatically.</i>';
		echo "<div class='yay-currency-fixed-prices-input-wrapper'>";
		echo "<div class='yay-currency-modal-wrapper'>";
		echo '<div class="yay-currency-locked-modal">';
		echo '<svg width="32" height="32" xmlns="http://www.w3.org/2000/svg"><path d="M16 21.915v2.594a.5.5 0 0 0 1 0v-2.594a1.5 1.5 0 1 0-1 0zM9 14v-3.5a7.5 7.5 0 1 1 15 0V14c1.66.005 3 1.35 3 3.01v9.98A3.002 3.002 0 0 1 23.991 30H9.01A3.008 3.008 0 0 1 6 26.99v-9.98A3.002 3.002 0 0 1 9 14zm3 0v-3.5C12 8.01 14.015 6 16.5 6c2.48 0 4.5 2.015 4.5 4.5V14h-9z" fill="#c2cdd4" fill-rule="evenodd"/></svg>';
		echo '<p>This feature is available in YayCurrency Pro version</p>';
		echo '<a href="https://yaycommerce.com/yaycurrency-woocommerce-multi-currency-switcher/" target="_blank" class="button button-primary">Unlock this feature</a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="yay-currency-inputs">';
		foreach ( $this->currencies as $currency ) {
			echo '<div class="yay-currency-fixed-prices-input">';

			woocommerce_wp_text_input(
				array(
					'id'                => "regular_price_{$currency['currency']}",
					'placeholder'       => 'Auto',
					'label'             => 'Regular Price (' . $currency['currency'] . ')',
					'desc_tip'          => 'true',
					'wrapper_class'     => 'form-row form-row-first',
					'custom_attributes' => array( 'disabled' => 'disabled' ),
				)
			);
			woocommerce_wp_text_input(
				array(
					'id'                => "sale_price_{$currency['currency']}",
					'placeholder'       => 'Auto',
					'label'             => 'Sale Price (' . $currency['currency'] . ')',
					'desc_tip'          => 'true',
					'wrapper_class'     => 'form-row form-row-last',
					'custom_attributes' => array( 'disabled' => 'disabled' ),
				)
			);
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';
		echo '</div>';

	}
}
