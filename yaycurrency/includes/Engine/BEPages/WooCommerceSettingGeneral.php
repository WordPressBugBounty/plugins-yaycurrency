<?php
namespace Yay_Currency\Engine\BEPages;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\RateHelper;


defined( 'ABSPATH' ) || exit;

class WooCommerceSettingGeneral {
	use SingletonTrait;

	private $currency_update;
	public function __construct() {

		add_filter( 'woocommerce_admin_settings_sanitize_option_woocommerce_currency', array( $this, 'update_currency_option' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_woocommerce_currency_pos', array( $this, 'update_currency_meta_option' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_woocommerce_price_thousand_sep', array( $this, 'update_currency_meta_option' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_woocommerce_price_decimal_sep', array( $this, 'update_currency_meta_option' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_woocommerce_price_num_decimals', array( $this, 'update_currency_meta_option' ), 10, 3 );

	}

	// Update Currency when save WooCommerce Setting General
	public function update_currency_option( $value, $option, $raw_value ) {
		$currencies            = Helper::get_currencies_post_type();
		$this->currency_update = $value;
		$default_currency_code = Helper::default_currency_code();
		if ( $currencies && $default_currency_code !== $value ) {
			RateHelper::sync_currency_exchange_rates( $value, $default_currency_code, true );
			if ( class_exists( 'WC_Cache_Helper' ) ) {
				\WC_Cache_Helper::get_transient_version( 'product', true ); // Update product price (currency) after change value.
			}
		}
		return $value;
	}

	public function update_currency_meta_option( $value, $option, $raw_value ) {
		$currency_update = Helper::get_yay_currency_by_currency_code( $this->currency_update );

		if ( $currency_update ) {
			$option_name = isset( $option['id'] ) && ! empty( $option['id'] ) ? $option['id'] : false;
			$currency_id = isset( $currency_update->ID ) && $currency_update->ID ? $currency_update->ID : false;

			if ( $currency_id && $option_name ) {
				$option_key = false;
				switch ( $option_name ) {
					case 'woocommerce_currency_pos':
						$option_key = 'currency_position';
						break;
					case 'woocommerce_price_thousand_sep':
						$option_key = 'thousand_separator';
						break;
					case 'woocommerce_price_decimal_sep':
						$option_key = 'decimal_separator';
						break;
					case 'woocommerce_price_num_decimals':
						$option_key = 'number_decimal';
						break;
					default:
						break;
				}
				if ( $option_key ) {
					update_post_meta( $currency_id, $option_key, $value );
				}
			}
		}

		return $value;
	}
}
