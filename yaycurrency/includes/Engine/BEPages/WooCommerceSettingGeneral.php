<?php
namespace Yay_Currency\Engine\BEPages;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;


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
		$currencies = Helper::get_currencies_post_type();
		if ( $currencies ) {
			$this->currency_update = $value;
			$currency_update       = Helper::get_yay_currency_by_currency_code( $value );
			if ( ! $currency_update ) {
				Helper::create_new_currency( $value, true );
			} else {
				update_post_meta( $currency_update->ID, 'rate', '1' );
				update_post_meta(
					$currency_update->ID,
					'fee',
					array(
						'value' => '0',
						'type'  => get_post_meta(
							$currency_update->ID,
							'fee'
						)[0]['type'],
					)
				);
			}
			Helper::update_exchange_rate_currency( $currencies, $value );
			\WC_Cache_Helper::get_transient_version( 'product', true ); // Update product price (currency) after change value.
		}
		return $value;
	}

	public function update_currency_meta_option( $value, $option, $raw_value ) {
		if ( ! empty( $this->currency_update ) ) {
			$currency_update = Helper::get_yay_currency_by_currency_code( $this->currency_update );
			$option_name     = isset( $option['id'] ) && ! empty( $option['id'] ) ? $option['id'] : false;
			$currency_id     = isset( $currency_update->ID ) && $currency_update->ID ? $currency_update->ID : false;

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
