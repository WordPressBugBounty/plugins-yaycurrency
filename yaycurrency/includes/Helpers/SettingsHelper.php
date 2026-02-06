<?php

namespace Yay_Currency\Helpers;

use Yay_Currency\Utils\SingletonTrait;

class SettingsHelper {
	use SingletonTrait;

	// GET DATA PAYMENT METHODS
	public static function get_data_payment_methods() {
		$paymentMethodsOptions     = array();
		$installed_payment_methods = WC()->payment_gateways->get_available_payment_gateways();
		foreach ( $installed_payment_methods as $key => $value ) {
			$title                         = isset( $value->title ) && ! empty( $value->title ) ? $value->title : ( isset( $value->method_title ) && ! empty( $value->method_title ) ? $value->method_title : $key );
			$title                         = wp_strip_all_tags( $title );
			$paymentMethodsOptions[ $key ] = $title;
		}

		return $paymentMethodsOptions;
	}

	public static function get_settings() {
		$converted_currencies = array();
		$currencies           = Helper::get_currencies_post_type();
		$default_currency     = Helper::get_default_currency();

		if ( $currencies ) {
			$converted_currencies = Helper::converted_currencies( $currencies );
			// Add default currency if it doesn't exist in the list
			if ( ! Helper::check_currency_code_exists( $currencies, Helper::default_currency_code() ) ) {
				array_push( $converted_currencies, $default_currency );
			}
		} else {
			array_push( $converted_currencies, $default_currency );
		}

		$settings = array(
			'manage_currency'       => $converted_currencies,
			'checkout_options'      => array(
				'isCheckoutDifferentCurrency' => get_option( 'yay_currency_checkout_different_currency', 0 ),
			),
			'display_options'       => array(
				'isShowOnSingleProductPage'           => get_option( 'yay_currency_show_single_product_page', 1 ),
				'switcherPositionOnSingleProductPage' => get_option( 'yay_currency_switcher_position_on_single_product_page', 'before_description' ),
				'isShowFlagInSwitcher'                => get_option( 'yay_currency_show_flag_in_switcher', 1 ),
				'isShowCurrencyNameInSwitcher'        => get_option( 'yay_currency_show_currency_name_in_switcher', 1 ),
				'isShowCurrencySymbolInSwitcher'      => get_option( 'yay_currency_show_currency_symbol_in_switcher', 1 ),
				'isShowCurrencyCodeInSwitcher'        => get_option( 'yay_currency_show_currency_code_in_switcher', 1 ),
				'switcherSize'                        => get_option( 'yay_currency_switcher_size', 'medium' ),
				'currencyUnitType'                    => get_option( 'yay_currency_currency_unit_type', 'symbol' ),
			),
			'isShowRecommendations' => get_option( 'isShowRecommendations', '1' ),
		);

		$settings = apply_filters( 'YayCurrency/Data/GetGeneralSettings', $settings );

		return $settings;
	}
}
