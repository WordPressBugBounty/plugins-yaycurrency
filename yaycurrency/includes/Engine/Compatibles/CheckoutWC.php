<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin:  https://www.CheckoutWC.com

class CheckoutWC {
	use SingletonTrait;

	public function __construct() {

		if ( ! defined( 'CFW_VERSION' ) ) {
			return;
		}

		add_filter( 'YayCurrency/ConvertPrice/NotPermitted', array( $this, 'is_not_permitted_convert_price' ), 10, 1 );

		add_filter( 'yay_currency_get_acr_report', array( $this, 'reconvert_tracked_carts' ), 10, 1 );
		add_filter( 'yay_currency_get_tracked_carts', array( $this, 'reconvert_tracked_carts' ), 10, 1 );

	}

	public function is_not_permitted_convert_price( $flag ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : false;
		if ( $request_uri && ( strpos( $request_uri, 'wp-json/checkoutwc/v1/acr/' ) !== false ) ) {
			$flag = true;
		}
		return $flag;
	}

	protected function get_currency_info_on_cart( $cart_contents ) {
		foreach ( $cart_contents as $key => $cart_item ) {
			if ( isset( $cart_item['yay_currency_code'] ) && ! empty( $cart_item['yay_currency_code'] ) ) {
				$currency_code = $cart_item['yay_currency_code'];
				$currency_rate = $cart_item['yay_currency_rate'];
				break;
			}
		}
		return array(
			'currency_code' => isset( $currency_code ) ? $currency_code : Helper::default_currency_code(),
			'currency_rate' => isset( $currency_rate ) ? $currency_rate : false,
		);
	}

	public function reconvert_tracked_carts( $tracked_carts ) {
		foreach ( $tracked_carts as $key => $value ) {
			if ( isset( $value->cart ) ) {
				$cart_contents = json_decode( $value->cart, true );
				$currency_info = self::get_currency_info_on_cart( $cart_contents );
				if ( Helper::default_currency_code() !== $currency_info['currency_code'] ) {
					if ( $currency_info['currency_rate'] ) {
						$rate_fee = $currency_info['currency_rate'];
					} else {
						$apply_currency = YayCurrencyHelper::get_currency_by_currency_code( $currency_info['currency_code'] );
						$rate_fee       = YayCurrencyHelper::get_rate_fee( $apply_currency );
					}
					$subtotal                        = floatval( $value->subtotal ) / $rate_fee;
					$tracked_carts[ $key ]->subtotal = $subtotal;
				}
			}
		}
		return $tracked_carts;
	}
}
