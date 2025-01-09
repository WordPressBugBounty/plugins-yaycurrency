<?php
namespace Yay_Currency\Helpers;

use Yay_Currency\Utils\SingletonTrait;
class YayCurrencyHelper {

	use SingletonTrait;

	private static $COOKIE_NAME     = 'yay_currency_widget';
	private static $COOKIE_SWITCHER = 'yay_currency_do_change_switcher';

	protected function __construct() {}


	public static function get_cookie_name( $type = 'default' ) {

		if ( 'switcher' === $type ) {
			return self::$COOKIE_SWITCHER;
		}

		return self::$COOKIE_NAME;

	}

	public static function get_symbol_by_currency_code( $currency_code = '' ) {
		$default_currency_code = Helper::default_currency_code();
		$currency_code         = ! empty( $currency_code ) ? $currency_code : $default_currency_code;
		$all_symbols           = get_woocommerce_currency_symbols();
		if ( isset( $all_symbols[ $currency_code ] ) ) {
			$currency_symbol = $all_symbols[ $currency_code ];
		} else {
			$currency_symbol = get_woocommerce_currency_symbol( $default_currency_code );
		}

		return $currency_symbol;
	}

	public static function checkout_in_fallback_currency( $apply_currency = array() ) {

		if ( $apply_currency && isset( $apply_currency['status'] ) ) {
			$is_checkout_different_currency = (int) get_option( 'yay_currency_checkout_different_currency', 0 );
			$status                         = (int) $apply_currency['status'];
			if ( $is_checkout_different_currency && ! $status ) {
				return true;
			}
		}

		return false;
	}

	public static function is_dis_checkout_diff_currency( $apply_currency = array() ) {

		$is_checkout_different_currency = (int) get_option( 'yay_currency_checkout_different_currency', 0 );
		if ( ! $is_checkout_different_currency ) {
			return true;
		}

		if ( isset( $apply_currency['status'] ) ) {
			if ( ! (int) $apply_currency['status'] ) {
				return true;
			}
		}

		return false;

	}

	public static function disable_fallback_option_in_checkout_page( $apply_currency = array() ) {
		$is_dis_checkout_diff_currency = self::is_dis_checkout_diff_currency( $apply_currency );
		$checkout_blocks               = apply_filters( 'yay_currency_is_checkout_blocks', false ); // checkout use gutenberg blocks
		$is_checkout_page              = is_checkout() || $checkout_blocks || apply_filters( 'yay_currency_disable_fallback_checkout_conditions', false );
		$order_received_page           = $is_checkout_page && is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' );
		return $is_dis_checkout_diff_currency && ( $is_checkout_page || $order_received_page );
	}

	public static function set_cookies( $apply_currency = array() ) {
		if ( ! $apply_currency || ! isset( $apply_currency['ID'] ) || headers_sent() ) {
			return;
		}

		$cookie_value = $apply_currency['ID'];

		if ( isset( $_COOKIE[ self::$COOKIE_NAME ] ) ) {
			$currency_id = intval( sanitize_key( $_COOKIE[ self::$COOKIE_NAME ] ) );
			if ( $currency_id === $cookie_value ) {
				return;
			}
		}

		setcookie( self::$COOKIE_NAME, $cookie_value, time() + ( 86400 * 30 ), '/' );

		$_COOKIE[ self::$COOKIE_NAME ] = $cookie_value;

	}

	public static function set_cookie( $cookie_name, $cookie_value ) {
		setcookie( $cookie_name, $cookie_value, time() + ( 86400 * 30 ), '/' );
	}

	public static function delete_cookie( $cookie_name ) {
		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			unset( $_COOKIE[ $cookie_name ] );
			setcookie( $cookie_name, '', -1, '/' );
		}
	}

	public static function get_id_selected_currency() {
		$current_currency_id = 0;
		if ( isset( $_COOKIE[ self::$COOKIE_NAME ] ) ) {
			$current_currency_id = sanitize_key( $_COOKIE[ self::$COOKIE_NAME ] );
		}

		if ( isset( $_REQUEST['yay-currency-nonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['yay-currency-nonce'] ), 'yay-currency-check-nonce' ) ) {
			$current_currency_id = isset( $_POST['currency'] ) ? sanitize_key( wp_unslash( $_POST['currency'] ) ) : $current_currency_id;
		}

		return intval( $current_currency_id );
	}

	public static function converted_currency( $currencies = false ) {
		$yay_list_currencies = $currencies ? $currencies : Helper::get_currencies_post_type();
		$converted_currency  = array();
		if ( $yay_list_currencies ) {
			foreach ( $yay_list_currencies as $currency ) {
				$currency_meta = get_post_meta( $currency->ID, '', false );
				if ( ! $currency_meta ) {
					continue;
				}
				$apply_currency = self::get_apply_currency_meta_data( $currency, $currency_meta );
				array_push( $converted_currency, $apply_currency );
			}
		}
		return $converted_currency;
	}

	// WooCommerce Product Price Filter hooks

	public static function get_woocommerce_product_price_hooks() {
		$product_price_hooks = array(
			'woocommerce_product_get_price',
			'woocommerce_product_get_regular_price',
			'woocommerce_product_get_sale_price',

			'woocommerce_product_variation_get_price',
			'woocommerce_product_variation_get_regular_price',
			'woocommerce_product_variation_get_sale_price',

			'woocommerce_variation_prices_price',
			'woocommerce_variation_prices_regular_price',
			'woocommerce_variation_prices_sale_price',
		);
		return apply_filters( 'yay_currency_get_product_price_hooks', $product_price_hooks );
	}

	// GET CURRENT CURRENCY & APPLY CURRENCY
	public static function get_currency_by_currency_code( $currency_code = '', $converted_currency = false ) {
		$currency_code      = ! empty( $currency_code ) ? $currency_code : Helper::default_currency_code();
		$converted_currency = $converted_currency ? $converted_currency : self::converted_currency();
		foreach ( $converted_currency as $currency ) {
			if ( $currency['currency'] === $currency_code ) {
				return $currency;
			}
		}
		return false;
	}

	public static function get_apply_currency_meta_data( $currency, $currency_meta = array() ) {
		return array(
			'ID'                   => $currency->ID,
			'currency'             => $currency->post_title,
			'currencyPosition'     => $currency_meta['currency_position'][0],
			'currencyCodePosition' => isset( $currency_meta['currency_code_position'] ) && ! empty( $currency_meta['currency_code_position'][0] ) ? $currency_meta['currency_code_position'][0] : 'not_display',
			'thousandSeparator'    => isset( $currency_meta['thousand_separator'][0] ) ? $currency_meta['thousand_separator'][0] : Helper::default_thousand_separator(),
			'decimalSeparator'     => isset( $currency_meta['decimal_separator'][0] ) ? $currency_meta['decimal_separator'][0] : Helper::default_decimal_separator(),
			'numberDecimal'        => isset( $currency_meta['number_decimal'][0] ) ? $currency_meta['number_decimal'][0] : Helper::default_price_num_decimals(),
			'roundingType'         => isset( $currency_meta['rounding_type'] ) ? $currency_meta['rounding_type'][0] : 'disabled',
			'roundingValue'        => isset( $currency_meta['rounding_value'] ) ? $currency_meta['rounding_value'][0] : 1,
			'subtractAmount'       => isset( $currency_meta['subtract_amount'] ) ? $currency_meta['subtract_amount'][0] : 0,
			'rate'                 => isset( $currency_meta['rate'] ) ? $currency_meta['rate'][0] : array(
				'type'  => 'auto',
				'value' => '1',
			),
			'fee'                  => isset( $currency_meta['fee'] ) ? maybe_unserialize( $currency_meta['fee'][0] ) : array(
				'value' => '0',
				'type'  => 'fixed',
			),
			'status'               => isset( $currency_meta['status'] ) ? $currency_meta['status'][0] : '1',
			'paymentMethods'       => isset( $currency_meta['payment_methods'] ) ? maybe_unserialize( $currency_meta['payment_methods'][0] ) : array( 'all' ),
			'countries'            => isset( $currency_meta['countries'] ) ? maybe_unserialize( $currency_meta['countries'][0] ) : array( 'default' ),
			'symbol'               => self::get_symbol_by_currency_code( $currency->post_title ),
		);

	}

	public static function get_currency_by_ID( $currency_id = 0 ) {
		$currency      = get_post( $currency_id );
		$currency_meta = get_post_meta( $currency_id, '', false );
		if ( ! $currency || ! $currency_meta || Helper::get_post_type() !== $currency->post_type ) {
			return self::get_currency_by_currency_code();
		}
		$apply_currency = self::get_apply_currency_meta_data( $currency, $currency_meta );
		return $apply_currency;
	}

	public static function set_cookie_currency_switcher( $selected_currency_id, $do_set_cookie = false ) {
		$cookie_switcher_name = self::get_cookie_name( 'switcher' );
		if ( $do_set_cookie ) {
			self::set_cookie( $cookie_switcher_name, $selected_currency_id );
		}
		$_COOKIE[ $cookie_switcher_name ] = $selected_currency_id;
	}

	public static function get_currency_change_switcher( $apply_currency = array() ) {
		if ( isset( $_REQUEST['yay-currency-nonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['yay-currency-nonce'] ), 'yay-currency-check-nonce' ) ) {

			if ( isset( $_POST['currency'] ) ) {
				$selected_currency_id = sanitize_text_field( $_POST['currency'] );
				$apply_currency       = self::get_currency_by_ID( $selected_currency_id );
				self::set_cookie_currency_switcher( $selected_currency_id );
			}

			if ( isset( $_POST['yay_currency'] ) && isset( $_POST['yay_currency_current_url'] ) ) {
				$selected_currency_id = sanitize_text_field( $_POST['yay_currency'] );
				$current_url          = sanitize_text_field( $_POST['yay_currency_current_url'] );
				self::set_cookie_currency_switcher( $selected_currency_id );
				do_action( 'yay_currency_redirect_to_url', $current_url, $selected_currency_id );
			}
		}

		// CHANGE CURRENCY ON URL --- ?yay-currency=EUR
		$currency_param_name = apply_filters( 'yay_currency_param_name', 'yay-currency' );
		if ( Helper::use_yay_currency_params() && isset( $_REQUEST[ $currency_param_name ] ) && ! empty( $_REQUEST[ $currency_param_name ] ) ) {
			$currency_code          = sanitize_text_field( $_REQUEST[ $currency_param_name ] );
			$apply_currency_by_code = self::get_currency_by_currency_code( strtoupper( $currency_code ) );
			$apply_currency         = $apply_currency_by_code ? $apply_currency_by_code : $apply_currency;
			self::set_cookie_currency_switcher( $apply_currency['ID'] );
		}

		$apply_currency = apply_filters( 'yay_currency_apply_currency', $apply_currency );
		return $apply_currency;
	}

	public static function get_default_apply_currency_not_exists_post_type() {
		$default_apply_currency = Helper::get_default_currency();
		if ( $default_apply_currency ) {
			$default_apply_currency['rate']   = 1;
			$default_apply_currency['symbol'] = self::get_symbol_by_currency_code( Helper::default_currency_code() );
		}
		return $default_apply_currency;
	}

	public static function get_default_apply_currency( $converted_currency = array() ) {
		if ( $converted_currency ) {
			$default_currency_code  = Helper::default_currency_code();
			$default_apply_currency = reset( $converted_currency );
			if ( isset( $default_apply_currency['currency'] ) && $default_currency_code === $default_apply_currency['currency'] ) {
				return $default_apply_currency;
			} else {
				$found_key              = array_search( $default_currency_code, array_column( $converted_currency, 'currency' ) );
				$default_apply_currency = isset( $converted_currency[ $found_key ] ) ? $converted_currency[ $found_key ] : $default_apply_currency;
			}
		} else {
			$default_apply_currency = self::get_default_apply_currency_not_exists_post_type();
		}

		return $default_apply_currency;
	}

	public static function get_apply_currency( $converted_currency = array() ) {
		$converted_currency = $converted_currency ? $converted_currency : self::converted_currency();
		$apply_currency     = self::get_default_apply_currency( $converted_currency );

		if ( isset( $_COOKIE[ self::$COOKIE_NAME ] ) ) {
			$currency_id    = sanitize_key( $_COOKIE[ self::$COOKIE_NAME ] );
			$apply_currency = self::get_currency_by_ID( $currency_id );
		}

		$apply_currency = self::get_currency_change_switcher( $apply_currency );

		return $apply_currency;
	}

	public static function detect_current_currency() {
		$currency_id    = self::get_id_selected_currency();
		$apply_currency = $currency_id ? self::get_currency_by_ID( $currency_id ) : self::get_apply_currency();
		return $apply_currency;
	}

	public static function list_ajax_actions_allow() {
		$action_args = array( 'woosq_quickview', 'pvtfw_woocommerce_ajax_add_to_cart', 'woocommerce_get_refreshed_fragments', 'loadmore' );
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			array_push( $action_args, 'elementor_menu_cart_fragments' );
		}

		if ( function_exists( 'Barn2\Plugin\WC_Product_Table\wpt' ) ) {
			array_push( $action_args, 'wcpt_load_products' );
		}

		$current_theme = Helper::get_current_theme();
		switch ( $current_theme ) {
			case 'kapee':
				$action_args = array_unique( array_merge( $action_args, array( 'kapee_update_cart_widget_quantity', 'kapee_ajax_add_to_cart' ) ) );
				break;
			case 'betheme':
				$action_args = array_unique( array_merge( $action_args, array( 'mfnrefreshcart', 'mfnproductquickview' ) ) );
				break;
			case 'flatsome':
				array_push( $action_args, 'flatsome_quickview' );
				break;
			case 'salient':
				array_push( $action_args, 'nectar_woo_get_product' );
				break;
			default:
				break;
		}
		return apply_filters( 'yay_currency_detect_action_args', $action_args );
	}

	public static function list_wc_ajax_actions_allow() {
		$wc_ajax_args = array( 'xoo_wsc_update_item_quantity', 'get_refreshed_fragments' );
		return apply_filters( 'yay_currency_detect_wc_ajax_args', $wc_ajax_args );
	}

	public static function is_reload_permitted() {

		if ( apply_filters( 'yay_currency_detect_block_convert_price', false ) ) {
			return false;
		}

		$admin_rest_route = Helper::get_rest_route_via_rest_api();
		if ( ! is_admin() && ( ! $admin_rest_route || '/yaycurrency/v1/settings' !== $admin_rest_route ) ) {
			return true;
		}

		if ( wp_doing_ajax() ) {
			// Ajax actions
			$action_args = self::list_ajax_actions_allow();
			if ( isset( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], $action_args, true ) ) {
				return true;
			}
			// WC Ajax actions
			$wc_ajax_args = self::list_wc_ajax_actions_allow();
			if ( isset( $_REQUEST['wc-ajax'] ) && in_array( $_REQUEST['wc-ajax'], $wc_ajax_args, true ) ) {
				return true;
			}
		}

		return apply_filters( 'yay_currency_detect_reload_with_ajax', false );

	}

	public static function get_current_currency( $apply_currency = array() ) {
		$apply_currency = apply_filters( 'yay_currency_detect_current_currency', $apply_currency );
		return $apply_currency ? $apply_currency : self::detect_current_currency();
	}

	public static function detect_allow_hide_dropdown_currencies() {

		if ( is_checkout() && ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) ) {
			return true;
		}

		// detect manual order with payment link
		if ( isset( $_REQUEST['pay_for_order'] ) && isset( $_REQUEST['key'] ) ) {
			return true;
		}
		return false;
	}

	public static function format_price( $price = 0 ) {
		$formatted_price = wc_price( $price );
		$apply_currency  = self::detect_current_currency();
		if ( $apply_currency ) {
			$formatted_price = wc_price(
				$price,
				self::get_apply_currency_format_info( $apply_currency )
			);
		}
		return $formatted_price;
	}

	public static function format_sale_price( $regular_price, $sale_price ) {
		$formatted_price = '<del aria-hidden="true">' . ( is_numeric( $regular_price ) ? wc_price( $regular_price ) : $regular_price ) . '</del> <ins>' . ( is_numeric( $sale_price ) ? wc_price( $sale_price ) : $sale_price ) . '</ins>';
		$apply_currency  = self::detect_current_currency();
		if ( $apply_currency ) {
			$format          = self::get_apply_currency_format_info( $apply_currency );
			$regular_price   = wc_price( $regular_price, $format );
			$sale_price      = wc_price( $sale_price, $format );
			$formatted_price = '<del aria-hidden="true">' . ( is_numeric( $regular_price ) ? $regular_price : $regular_price ) . '</del> <ins>' . ( is_numeric( $sale_price ) ? $sale_price : $sale_price ) . '</ins>';
		}
		return $formatted_price;
	}

	public static function get_apply_currency_format_info( $apply_currency = array() ) {
		$currency_code              = $apply_currency['currency'];
		$format                     = self::format_currency_position( $apply_currency['currencyPosition'] );
		$apply_currency_format_info = array(
			'ex_tax_label'       => false,
			'currency'           => $currency_code,
			'decimal_separator'  => $apply_currency['decimalSeparator'],
			'thousand_separator' => $apply_currency['thousandSeparator'],
			'decimals'           => Helper::default_currency_code() === $currency_code ? Helper::default_price_num_decimals() : $apply_currency['numberDecimal'],
			'price_format'       => $format,
		);
		return $apply_currency_format_info;

	}

	public static function format_currency_position( $currency_position = 'left' ) {
		$format = '%1$s%2$s';
		switch ( $currency_position ) {
			case 'left':
				$format = '%1$s%2$s';
				break;
			case 'right':
				$format = '%2$s%1$s';
				break;
			case 'left_space':
				$format = '%1$s&nbsp;%2$s';
				break;
			case 'right_space':
				$format = '%2$s&nbsp;%1$s';
				break;
		}
		return $format;
	}

	public static function format_currency_code_position( $format_currency_position = '', $currency_info = array() ) {
		$format = $format_currency_position;

		if ( isset( $currency_info['currencyCodePosition'] ) ) {

			switch ( $currency_info['currencyCodePosition'] ) {
				case 'left':
					$format = $currency_info['currency'] . $format_currency_position;
					break;
				case 'right':
					$format = $format_currency_position . $currency_info['currency'];
					break;
				case 'left_space':
					$format = $currency_info['currency'] . ' ' . $format_currency_position;
					break;
				case 'right_space':
					$format = $format_currency_position . ' ' . $currency_info['currency'];
					break;
				case 'not_display':
					$format = $format_currency_position;
					break;
			}
		}

		return $format;

	}

	public static function get_symbol_by_currency( $currency_name = '', $converted_currency = array() ) {

		foreach ( $converted_currency as $key => $currency ) {
			if ( $currency['currency'] === $currency_name ) {
				return $currency['symbol'];
			}
		}

		return '';
	}

	public static function get_rate_fee( $currency = array() ) {
		if ( 'percentage' === $currency['fee']['type'] ) {
			$rate_after_fee = (float) $currency['rate'] + ( (float) $currency['rate'] * ( (float) $currency['fee']['value'] / 100 ) );
		} else {
			$rate_after_fee = (float) $currency['rate'] + (float) $currency['fee']['value'];
		}
		return $rate_after_fee;
	}

	public static function get_rate_fee_from_currency_not_exists_in_list( $currency_code ) {
		$rate_fee      = false;
		$exchange_data = Helper::get_exchange_rates(
			array(
				'$src'  => Helper::default_currency_code(),
				'$dest' => $currency_code,
			)
		);
		if ( 200 === $exchange_data['response']['code'] ) {
			$decoded_exchange_data = json_decode( $exchange_data['body'] );
			$exchange_result       = isset( $decoded_exchange_data->chart->result[0] ) ? $decoded_exchange_data->chart->result[0] : false;
			if ( $exchange_result ) {
				$rate_fee = isset( $exchange_result->indicators->quote[0]->close ) ? $exchange_result->indicators->quote[0]->close[0] : ( isset( $exchange_result->meta->previousClose ) ? $exchange_result->meta->previousClose : false );
			}
		}
		return $rate_fee;
	}

	public static function enable_rounding_currency( $apply_currency = array() ) {
		return $apply_currency && 'disabled' !== $apply_currency['roundingType'];
	}

	public static function round_price_by_currency( $price = 0, $apply_currency = array() ) {

		if ( ! $price || $price <= 0 ) {
			return $price;
		}

		if ( isset( $apply_currency['roundingType'] ) && 'disabled' !== $apply_currency['roundingType'] ) {
			$rounding_value  = isset( $apply_currency['roundingValue'] ) ? floatval( $apply_currency['roundingValue'] ) : 1;
			$subtract_amount = isset( $apply_currency['subtractAmount'] ) ? floatval( $apply_currency['subtractAmount'] ) : 0;
			switch ( $apply_currency['roundingType'] ) {
				case 'up':
					$price = ceil( $price / $rounding_value ) * $rounding_value - $subtract_amount;
					break;
				case 'down':
					$price = floor( $price / $rounding_value ) * $rounding_value - $subtract_amount;
					break;
				case 'nearest':
					$price = round( $price / $rounding_value ) * $rounding_value - $subtract_amount;
					break;
				default:
					break;
			}
		} elseif ( apply_filters( 'yay_currency_round_product_price', true ) ) {
			$round_type     = apply_filters( 'yay_currency_round_type', PHP_ROUND_HALF_UP );
			$number_decimal = isset( $apply_currency['numberDecimal'] ) ? $apply_currency['numberDecimal'] : get_option( 'woocommerce_price_num_decimals' );
			$price          = round( $price, $number_decimal, $round_type );
		}

		return $price;
	}

	public static function calculate_price_by_currency( $price = 0, $exclude = false, $apply_currency = array() ) {
		if ( ! empty( $apply_currency ) ) {
			$rate_after_fee = self::get_rate_fee( $apply_currency );
			$price          = (float) $price * $rate_after_fee;

			if ( ! $exclude ) {
				$price = self::round_price_by_currency( $price, $apply_currency );
			}

			if ( apply_filters( 'yay_currency_should_format_product_price', false, $price, $apply_currency ) ) {
				$price = number_format( $price, $apply_currency['numberDecimal'], $apply_currency['decimalSeparator'], $apply_currency['thousandSeparator'] );
			}
		}
		return $price;
	}

	public static function calculate_price_by_currency_html( $currency = array(), $original_price = 0, $quantity = 1 ) {
		$rate_after_fee  = self::get_rate_fee( $currency );
		$price           = $original_price * $rate_after_fee;
		$price           = self::round_price_by_currency( $price, $currency );
		$format          = self::format_currency_position( $currency['currencyPosition'] );
		$formatted_price = wc_price(
			$price * $quantity,
			array(
				'currency'           => $currency['currency'],
				'decimal_separator'  => $currency['decimalSeparator'],
				'thousand_separator' => $currency['thousandSeparator'],
				'decimals'           => Helper::default_currency_code() === $currency['currency'] ? Helper::default_price_num_decimals() : $currency['numberDecimal'],
				'price_format'       => $format,
			)
		);
		return $formatted_price;

	}

	public static function calculate_custom_price_by_currency_html( $apply_currency = array(), $price = 0 ) {
		$price           = self::round_price_by_currency( $price, $apply_currency );
		$formatted_price = self::get_formatted_total_by_convert_currency( $price, $apply_currency, $apply_currency['currency'] );
		return $formatted_price;
	}

	public static function converted_approximately_html( $price_html = '', $class_name = 'yay-currency-checkout-converted-approximately' ) {
		$html = " <span class='" . esc_attr( $class_name ) . "'>(~$price_html)</span>";
		return $html;
	}

	public static function reverse_calculate_price_by_currency( $price = 0, $apply_currency = array() ) {
		$apply_currency = $apply_currency ? $apply_currency : self::detect_current_currency();
		$rate_fee       = self::get_rate_fee( $apply_currency );
		return $rate_fee ? (float) ( $price / $rate_fee ) : $price;
	}

	public static function is_current_fallback_currency( $currencies_data = array() ) {
		if ( $currencies_data && $currencies_data['current_currency']['currency'] === $currencies_data['fallback_currency']['currency'] ) {
			return true;
		}
		return false;
	}

	public static function get_current_and_fallback_currency( $apply_currency = array(), $converted_currency = array() ) {
		return array(
			'current_currency'  => $apply_currency ? $apply_currency : self::detect_current_currency(),
			'fallback_currency' => self::get_currency_by_currency_code( '', $converted_currency ),
		);
	}

	public static function format_currency_symbol( $currency_info = array() ) {
		$format_currency_position = isset( $currency_info['currencyPosition'] ) ? self::format_currency_position( $currency_info['currencyPosition'] ) : '';
		$format                   = self::format_currency_code_position( $format_currency_position, $currency_info );
		return $format;
	}

	public static function get_formatted_total_by_convert_currency( $price = 0, $convert_currency = array(), $yay_currency = '', $ex_tax_label = false ) {
		$format              = self::format_currency_symbol( $convert_currency );
		$thousand_separator  = get_option( 'woocommerce_price_thousand_sep' ) ? get_option( 'woocommerce_price_thousand_sep' ) : '.';
		$decimal_separator   = get_option( 'woocommerce_price_decimal_sep' ) ? get_option( 'woocommerce_price_decimal_sep' ) : '.';
		$is_default_currency = Helper::default_currency_code() === $yay_currency;

		$args = array(
			'ex_tax_label'       => $ex_tax_label,
			'currency'           => $yay_currency,
			'decimal_separator'  => $is_default_currency ? $decimal_separator : $convert_currency['decimalSeparator'],
			'thousand_separator' => $is_default_currency ? $thousand_separator : $convert_currency['thousandSeparator'],
			'decimals'           => $is_default_currency ? Helper::default_price_num_decimals() : $convert_currency['numberDecimal'],
			'price_format'       => $format,
		);

		$formatted_total = wc_price( $price, $args );
		return $formatted_total;

	}

	// ORDER CURRENCY

	public static function get_total_by_order( $order ) {
		if ( Helper::check_custom_orders_table_usage_enabled() ) {
			$order_total = $order->get_total();
		} else {
			$order_id    = $order->get_id();
			$order_total = get_post_meta( $order_id, '_order_total', true );
		}
		return $order_total;
	}

	public static function get_currency_code_by_order( $order ) {
		if ( Helper::check_custom_orders_table_usage_enabled() ) {
			$order_currency_code = ! empty( $order->get_currency() ) ? $order->get_currency() : Helper::default_currency_code();
		} else {
			$order_id            = $order->get_id();
			$order_currency_code = get_post_meta( $order_id, '_order_currency', true ) ? get_post_meta( $order_id, '_order_currency', true ) : Helper::default_currency_code();
		}
		return $order_currency_code;
	}

	public static function get_rate_fee_by_order( $order ) {
		$currency_code  = $order->get_currency();
		$order_currency = self::get_currency_by_currency_code( $currency_code );
		if ( ! $order_currency ) {
			$order_rate = self::get_rate_fee_from_currency_not_exists_in_list( $currency_code );
		} else {
			$order_rate = self::get_rate_fee( $order_currency );
		}
		return $order_rate;
	}

	public static function get_order_currency_by_order_id( $order_id, $converted_currency = array() ) {
		$converted_currency = $converted_currency ? $converted_currency : self::converted_currency();
		$order              = wc_get_order( $order_id );
		if ( ! $order ) {
			return $converted_currency ? reset( $converted_currency ) : false;
		}

		$currency_code  = self::get_currency_code_by_order( $order );
		$order_currency = self::get_currency_by_currency_code( $currency_code, $converted_currency );

		return $order_currency ? $order_currency : reset( $converted_currency );

	}

	public static function format_price_currency( $price, $apply_currency = false ) {
		if ( ! $apply_currency ) {
			$apply_currency = self::get_default_apply_currency( self::converted_currency() );
		}

		$price = number_format( $price, $apply_currency['numberDecimal'], $apply_currency['decimalSeparator'], $apply_currency['thousandSeparator'] );
		return $price;
	}

	// PAYMENT
	public static function filter_payment_methods_by_currency( $currency = array(), $available_gateways = array() ) {
		if ( ! $currency || array( 'all' ) === $currency['paymentMethods'] ) {
			return $available_gateways;
		}
		$allowed_payment_methods = $currency['paymentMethods'];
		$filtered                = array_filter(
			$available_gateways,
			function ( $key ) use ( $allowed_payment_methods ) {
				return in_array( $key, $allowed_payment_methods );
			},
			ARRAY_FILTER_USE_KEY
		);
		$available_gateways      = $filtered;
		return $available_gateways;
	}

	// return price default (wp-json/wc/v3/products?consumer_key=&consumer_secret=&per_page=&page=
	public static function is_wc_json_products() {
		return isset( $_REQUEST['consumer_key'] ) && isset( $_REQUEST['consumer_secret'] );
	}
}
