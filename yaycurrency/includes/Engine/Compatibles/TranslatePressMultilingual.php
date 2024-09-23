<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
defined( 'ABSPATH' ) || exit;

class TranslatePressMultilingual {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {
		if ( ! class_exists( 'TRP_Translate_Press' ) ) {
			return;
		}
		add_filter( 'yay_currency_woocommerce_currency_symbol', array( $this, 'custom_woocommerce_currency_symbol' ), 10, 3 );
	}

	public function convert_symbol_to_html_code( $currency, $currency_symbol ) {
		$symbols = array(
			'DKK' => '&#x6b;&#x72;&#x2e;',
			'ALL' => '&#x4c;',
			'AMD' => '&#x41;&#x4d;&#x44;',
			'AOA' => '&#x4b;&#x7a;',
			'AWG' => '&#x41;&#x66;&#x6c;&#x2e;',
			'BAM' => '&#x4b;&#x4d;',
			'BIF' => '&#x46;&#x72;',
			'BOB' => '&#x42;&#x73;&#x2e;',
			'BTN' => '&#x4e;&#x75;&#x2e;',
			'BWP' => '&#x50;',
			'BYR' => '&#x42;&#x72;',
			'BYN' => '&#x42;&#x72;',
			'CDF' => '&#x46;&#x72;',
			'DJF' => '&#x46;&#x72;',
			'EGP' => '&#x45;&#x47;&#x50;',
			'ERN' => '&#x4e;&#x66;&#x6b;',
			'ETB' => '&#x42;&#x72;',
			'GMD' => '&#x44;',
			'GNF' => '&#x46;&#x72;',
			'GTQ' => '&#x51;',
			'HNL' => '&#x4c;',
			'HRK' => '&#x6b;&#x6e;',
			'HTG' => '&#x47;',
			'IDR' => '&#x52;&#x70;',
			'ISK' => '&#x6b;&#x72;&#x2e;',
			'KES' => '&#x4b;&#x53;&#x68;',
			'KMF' => '&#x46;&#x72;',
			'LSL' => '&#x4c;',
			'MDL' => '&#x4d;&#x44;&#x4c;',
			'MGA' => '&#x41;&#x72;',
			'MMK' => '&#x4b;&#x73;',
			'MOP' => '&#x50;',
			'MRU' => '&#x55;&#x4d;',
			'MWK' => '&#x4d;&#x4b;',
			'MZN' => '&#x4d;&#x54;',
			'PGK' => '&#x4b;',
			'RON' => '&#x6c;&#x65;&#x69;',
			'RWF' => '&#x46;&#x72;',
			'SLL' => '&#x4c;&#x65;',
			'SOS' => '&#x53;&#x68;',
			'STN' => '&#x44;&#x62;',
			'SZL' => '&#x45;',
			'TZS' => '&#x53;&#x68;',
			'UGX' => '&#x55;&#x47;&#x58;',
			'UZS' => '&#x55;&#x5a;&#x53;',
			'VEF' => '&#x42;&#x73;&#x20;&#x46;',
			'VES' => '&#x42;&#x73;&#x2e;&#x53;',
			'VUV' => '&#x56;&#x74;',
			'WST' => '&#x54;',
			'XAF' => '&#x43;&#x46;&#x41;',
			'XOF' => '&#x43;&#x46;&#x41;',
			'XPF' => '&#x46;&#x72;',
			'ZMW' => '&#x5a;&#x4b;',
		);

		if ( in_array( $currency, array_keys( $symbols ) ) ) {
			return $symbols[ $currency ];
		}

		return $currency_symbol;
	}


	public function custom_woocommerce_currency_symbol( $currency_symbol, $currency, $apply_currency ) {
		if ( ! $apply_currency || YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $currency_symbol;
		}
		$current_currency = $apply_currency['currency'];
		return $this->convert_symbol_to_html_code( $current_currency, $currency_symbol );

	}
}
