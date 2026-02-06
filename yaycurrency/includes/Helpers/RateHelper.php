<?php
namespace Yay_Currency\Helpers;

use Yay_Currency\Utils\SingletonTrait;
class RateHelper {

	use SingletonTrait;

	protected function __construct() {}

	// Get exchange rates from yahoo finance api
	public static function get_exchange_rates( $currency_params_template ) {
		$url_template = 'https://query1.finance.yahoo.com/v8/finance/chart/$src$dest=X?interval=1m';
		$url          = strtr( $url_template, $currency_params_template );
		$json_data    = wp_remote_get( $url );
		return $json_data;
	}

	// Get rate fee from currency not exists in list
	public static function get_rate_fee_from_currency_not_exists_in_list( $currency_code ) {
		$exchange_data = self::get_exchange_rates(
			array(
				'$src'  => Helper::default_currency_code(),
				'$dest' => $currency_code,
			)
		);

		// Return 1 on error or invalid response
		if ( is_wp_error( $exchange_data ) || ! isset( $exchange_data['response']['code'] ) || 200 !== $exchange_data['response']['code'] ) {
			return 1;
		}

		$data   = json_decode( wp_remote_retrieve_body( $exchange_data ) );
		$result = isset( $data->chart->result[0] ) ? $data->chart->result[0] : null;

		// Return 1 if no result is found
		if ( ! $result ) {
			return 1;
		}

		// Return close value or previous close, defaulting to 1
		return isset( $result->indicators->quote[0]->close[0] )
			? $result->indicators->quote[0]->close[0]
			: ( isset( $result->meta->previousClose ) ? $result->meta->previousClose : 1 );
	}

	// Sync currency exchange rates --- Update default currency option
	public static function sync_currency_exchange_rates( $currency_code, $default_currency_code, $is_wc_settings_page = false ) {
		$original_currency = Helper::get_yay_currency_by_currency_code( $currency_code );
		if ( ! $original_currency ) {
			Helper::create_new_currency( $currency_code, $is_wc_settings_page );
		} else {
			$original_currency_id = isset( $original_currency->ID ) ? $original_currency->ID : false;
			if ( $original_currency_id ) {
				update_post_meta( $original_currency_id, 'rate', '1' );
				update_post_meta( $original_currency_id, 'rate_type', 'auto' );
				update_post_meta(
					$original_currency_id,
					'fee',
					array(
						'value' => '0',
						'type'  => 'fixed',
					)
				);
			}
			// Sync currency settings
			if ( ! $is_wc_settings_page ) {
				Helper::sync_currency_settings( $original_currency_id, $is_wc_settings_page );
			}
		}

		$currencies = Helper::get_currencies_post_type();
		if ( $currencies ) {
			foreach ( $currencies as $key => $currency ) {
				$other_currency_code = isset( $currency->post_title ) && ! empty( $currency->post_title ) ? $currency->post_title : false;
				$currency_id         = isset( $currency->ID ) ? $currency->ID : false;

				if ( ! $other_currency_code || ! $currency_id ) {
					continue;
				}

				if ( ! $original_currency && $other_currency_code === $default_currency_code ) {
					wp_delete_post( $currency_id );
					unset( $currencies[ $key ] );
					continue;
				}

				if ( $other_currency_code && $other_currency_code !== $currency_code ) {

					update_post_meta( $currency_id, 'rate_type', 'auto' );
					$currency_object = array(
						'srcCurrency'  => $currency_code,
						'destCurrency' => $other_currency_code,
					);
					$exchange_rate   = self::get_exchange_rate_from_finance_api( $currency_object );
					if ( ! $exchange_rate ) {
						update_post_meta( $currency_id, 'rate', 'N/A' );
						continue;
					}
					update_post_meta( $currency_id, 'rate', self::format_scientific_notation( $exchange_rate ) );

				}
			}

			Helper::update_currency_menu_order( $currencies, $currency_code );
			// delete cache yay currencies list
			Helper::delete_yay_currencies_transient();
		}
	}

	// Get exchange rate from finance api
	public static function get_exchange_rate_from_finance_api( $currency_object ) {
		// $finance_api = isset( $currency_object['financeApi'] ) ? $currency_object['financeApi'] : get_option( 'yay_currency_finance_api', 'default' );
		$exchange_rate = self::get_exchange_rate_from_yahoo_api( $currency_object );
		return $exchange_rate;
	}

	// YAHOO API - GET EXCHANGE RATE
	public static function get_exchange_rate_from_yahoo_api( $currency_object ) {
		$currency_params_template = array(
			'$src'  => $currency_object['srcCurrency'],
			'$dest' => $currency_object['destCurrency'],
		);

		$json_data = self::get_exchange_rates( $currency_params_template );
		if ( is_wp_error( $json_data ) || ! isset( $json_data['response']['code'] ) || 200 !== $json_data['response']['code'] ) {
			// If error, try to get exchange rate from fallback
			$exchange_rate = self::get_exchange_rate_fallback( $currency_object );
			if ( $exchange_rate ) {
				return $exchange_rate;
			}
			return false;
		}

		$decoded_json_data = json_decode( $json_data['body'] );

		if ( ! isset( $decoded_json_data->chart->result[0] ) ) {
			return false;
		}

		$exchange_rate = 1;

		if ( isset( $decoded_json_data->chart->result[0]->meta->regularMarketPrice ) ) {
			$exchange_rate = $decoded_json_data->chart->result[0]->meta->regularMarketPrice;
		} elseif ( isset( $decoded_json_data->chart->result[0]->indicators->quote[0]->close ) ) {
			$exchange_rate = $decoded_json_data->chart->result[0]->indicators->quote[0]->close[0];
		} else {
			$exchange_rate = $decoded_json_data->chart->result[0]->meta->previousClose;
		}

		// If false or null, try to get exchange rate from fallback
		if ( ! $exchange_rate ) {
			$exchange_rate = self::get_exchange_rate_fallback( $currency_object );
		}

		return $exchange_rate;

	}

	// Format scientific notation to decimal
	public static function format_scientific_notation( $number, $precision = 20 ) {
		$numStr = (string) $number;

		// If not scientific notation then return
		if ( ! preg_match( '/e/i', $numStr ) ) {
			return $number;
		}

		// Fallback: use sprintf to convert to decimal
		$decimal = sprintf( "%.{$precision}f", (float) $numStr );

		// Remove trailing 0 and trailing dot
		return rtrim( rtrim( $decimal, '0' ), '.' );
	}

	// Get exchange rate from fallback, only use when yahoo finance api return false
	public static function get_exchange_rate_fallback( $currency_object ) {

		// If error, try to get exchange rate from exchangerate-api.com only use when yahoo finance api return false
		$exchange_rate = self::get_exchange_rate_from_exchangerate_api( strtoupper( $currency_object['srcCurrency'] ), strtoupper( $currency_object['destCurrency'] ) );
		if ( $exchange_rate ) {
			return $exchange_rate;
		}

		// If error, try to get exchange rate from fawazcurrency-api only use when exchangerate-api.com return false
		$exchange_rate = self::get_exchange_rate_fawazcurrency_api( strtolower( $currency_object['srcCurrency'] ), strtolower( $currency_object['destCurrency'] ) );
		if ( $exchange_rate ) {
			return $exchange_rate;
		}

		return false;
	}

	public static function get_exchange_rate_from_exchangerate_api( $from_currency, $to_currency ) {
		$url      = "https://api.exchangerate-api.com/v4/latest/{$from_currency}";
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['rates'][ $to_currency ] ) ) {
			return floatval( $data['rates'][ $to_currency ] );
		}

		return false;
	}

	// Get exchange rate from fawazcurrency-api
	public static function get_exchange_rate_fawazcurrency_api( $from, $to ) {

		$api_url = "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{$from}.json";

		try {
			$response = wp_remote_get(
				$api_url,
				array(
					'sslverify' => true,
					'timeout'   => 30,
					'headers'   => array(
						'Cache-Control' => 'no-cache',
					),
				)
			);

			if ( is_wp_error( $response ) || empty( wp_remote_retrieve_body( $response ) ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! isset( $data[ $from ] ) || ! isset( $data[ $from ][ $to ] ) ) {
				return false;
			}

			return $data[ $from ][ $to ];

		} catch ( \Exception $e ) {
			return false;
		}
	}
}
