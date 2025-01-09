<?php

namespace Yay_Currency\Engine;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore as OrdersStatsDataStore;

defined( 'ABSPATH' ) || exit;

class Ajax {

	use SingletonTrait;

	public $exchange_rate_api;
	private $converted_currencies = array();
	public function __construct() {
		add_action( 'wp_ajax_yayCurrency_get_all_data', array( $this, 'get_all_data' ) );
		add_action( 'wp_ajax_yayCurrency_set_all_data', array( $this, 'set_all_data' ) );
		add_action( 'wp_ajax_yayCurrency_update_exchange_rate', array( $this, 'update_exchange_rate' ) );
		add_action( 'wp_ajax_yayCurrency_delete_currency', array( $this, 'delete_currency' ) );

		// Fetch Analytics
		add_action( 'wp_ajax_yayCurrency_sync_orders_revert_to_base', array( $this, 'ajax_handle_sync_orders_revert_to_base' ) );

		add_action( 'wp_ajax_yayCurrency_get_cart_subtotal_default_blocks', array( $this, 'ajax_handle_get_cart_subtotal_blocks' ) );
		add_action( 'wp_ajax_nopriv_yayCurrency_get_cart_subtotal_default_blocks', array( $this, 'ajax_handle_get_cart_subtotal_blocks' ) );
	}


	public function get_currency_manage_tab_data( $woo_current_settings ) {
		$post_type_args = array(
			'numberposts' => -1,
			'post_type'   => 'yay-currency-manage',
			'post_status' => 'publish',
			'order'       => 'ASC',
			'orderby'     => 'menu_order',
		);

		$currencies = get_posts( $post_type_args );

		if ( $currencies ) {
			foreach ( $currencies as $currency ) {
				$currency_meta = get_post_meta( $currency->ID, '', false );
				if ( ! $currency_meta ) {
					continue;
				}
				$converted_currency = array(
					'ID'                => $currency->ID,
					'currency'          => $currency->post_title,
					'currencySymbol'    => html_entity_decode( get_woocommerce_currency_symbol( $currency->post_title ) ),
					'currencyPosition'  => $currency_meta['currency_position'][0],
					'thousandSeparator' => $currency_meta['thousand_separator'][0],
					'decimalSeparator'  => isset( $currency_meta['decimal_separator'] ) ? $currency_meta['decimal_separator'][0] : '.',
					'numberDecimal'     => isset( $currency_meta['number_decimal'] ) ? $currency_meta['number_decimal'][0] : '0',
					'rate'              =>
						array(
							'type'  => isset( $currency_meta['rate_type'] ) ? $currency_meta['rate_type'][0] : 'auto',
							'value' => isset( $currency_meta['rate'] ) ? $currency_meta['rate'][0] : '1',
						),
					'fee'               => isset( $currency_meta['fee'] ) ? maybe_unserialize( $currency_meta['fee'][0] ) : array(
						'value' => '0',
						'type'  => 'fixed',
					),
					'status'            => isset( $currency_meta['status'] ) ? $currency_meta['status'][0] : '1',
					'paymentMethods'    => isset( $currency_meta['payment_methods'] ) ? maybe_unserialize( $currency_meta['payment_methods'][0] ) : array( 'all' ),
					'countries'         => isset( $currency_meta['countries'] ) ? maybe_unserialize( $currency_meta['countries'][0] ) : array( 'default' ),
					'default'           => Helper::default_currency_code() === $currency->post_title ? true : false,
					'isLoading'         => false,
					'roundingType'      => isset( $currency_meta['rounding_type'] ) ? $currency_meta['rounding_type'][0] : 'disabled',
					'roundingValue'     => isset( $currency_meta['rounding_value'] ) ? $currency_meta['rounding_value'][0] : 1,
					'subtractAmount'    => isset( $currency_meta['subtract_amount'] ) ? $currency_meta['subtract_amount'][0] : 0,
				);
				array_push( $this->converted_currencies, $converted_currency );
			}
		} else {
			$default_currency = array(
				'currency'          => $woo_current_settings['currentCurrency'],
				'currencySymbol'    => html_entity_decode( get_woocommerce_currency_symbol( $woo_current_settings['currentCurrency'] ) ),
				'currencyPosition'  => $woo_current_settings['currencyPosition'],
				'thousandSeparator' => $woo_current_settings['thousandSeparator'],
				'decimalSeparator'  => $woo_current_settings['decimalSeparator'],
				'numberDecimal'     => $woo_current_settings['numberDecimals'],
				'rate'              => array(
					'type'  => 'auto',
					'value' => '1',
				),
				'fee'               => array(
					'value' => '0',
					'type'  => 'fixed',
				),
				'status'            => '1',
				'paymentMethods'    => array( 'all' ),
				'countries'         => array( 'default' ),
				'default'           => true,
				'isLoading'         => false,
				'roundingType'      => 'disabled',
				'roundingValue'     => 1,
				'subtractAmount'    => 0,
			);
			array_push( $this->converted_currencies, $default_currency );
		}
		$is_checkout_different_currency           = get_option( 'yay_currency_checkout_different_currency', 0 );
		$is_show_on_single_product_page           = get_option( 'yay_currency_show_single_product_page', 1 );
		$switcher_position_on_single_product_page = get_option( 'yay_currency_switcher_position_on_single_product_page', 'before_description' );
		$is_show_flag_in_switcher                 = get_option( 'yay_currency_show_flag_in_switcher', 1 );
		$is_show_currency_name_in_switcher        = get_option( 'yay_currency_show_currency_name_in_switcher', 1 );
		$is_show_currency_symbol_in_switcher      = get_option( 'yay_currency_show_currency_symbol_in_switcher', 1 );
		$is_show_currency_code_in_switcher        = get_option( 'yay_currency_show_currency_code_in_switcher', 1 );
		$switcher_size                            = get_option( 'yay_currency_switcher_size', 'medium' );
		$is_wpml_compatible                       = get_option( 'yay_currency_wpml_compatible', 0 );
		$is_polylang_compatible                   = get_option( 'yay_currency_polylang_compatible', 0 );
		$paymentMethodsOptions                    = array();
		$installed_payment_methods                = WC()->payment_gateways->get_available_payment_gateways();
		foreach ( $installed_payment_methods as $key => $value ) {
			$paymentMethodsOptions[ $key ] = $value->title;
		}
		return array(
			'isCheckoutDifferentCurrency'         => $is_checkout_different_currency,
			'isShowOnSingleProductPage'           => $is_show_on_single_product_page,
			'switcherPositionOnSingleProductPage' => $switcher_position_on_single_product_page,
			'isShowFlagInSwitcher'                => $is_show_flag_in_switcher,
			'isShowCurrencyNameInSwitcher'        => $is_show_currency_name_in_switcher,
			'isShowCurrencySymbolInSwitcher'      => $is_show_currency_symbol_in_switcher,
			'isShowCurrencyCodeInSwitcher'        => $is_show_currency_code_in_switcher,
			'switcherSize'                        => $switcher_size,
			'isWPMLCompatible'                    => $is_wpml_compatible,
			'isPolylangCompatible'                => $is_polylang_compatible,
			'isShowRecommendations'               => get_option( 'isShowRecommendations', '1' ),
			'currencies'                          => $this->converted_currencies,
			'paymentMethods'                      => $paymentMethodsOptions,
		);
	}


	public function get_all_data() {
		check_ajax_referer( 'yay-currency-nonce', 'nonce', true );
		$woo_current_settings     = Helper::get_woo_current_settings();
		$currency_manage_tab_data = $this->get_currency_manage_tab_data( $woo_current_settings );
		wp_send_json(
			apply_filters(
				'yay_currency_wpml_polylang_compatible',
				array(
					'list_countries'           => WC()->countries->countries,
					'woo_current_settings'     => $woo_current_settings,
					'currency_manage_tab_data' => $currency_manage_tab_data,
				)
			)
		);
	}

	public function set_all_data() {

		check_ajax_referer( 'yay-currency-nonce', 'nonce', true );

		if ( isset( $_POST['data'] ) ) {
			// delete cache yay currencies list
			Helper::delete_yay_currencies_transient();

			$all_currencies_settings_data = Helper::sanitize( $_POST );
			$this->set_currency_manage_settings( $all_currencies_settings_data['currencies'] );
			$this->set_checkout_options_settings( $all_currencies_settings_data );
			$this->set_display_options_settings( $all_currencies_settings_data );
			$this->set_advance_options_settings( $all_currencies_settings_data );

			if ( class_exists( 'WC_Cache_Helper' ) ) {
				\WC_Cache_Helper::get_transient_version( 'product', true ); // Update product price (currency) after change value.
			}
		}
		$this->get_all_data();
	}

	public function update_exchange_rate() {
		check_ajax_referer( 'yay-currency-nonce', 'nonce', true );
		if ( isset( $_POST['data'] ) ) {
			$currency_object = Helper::sanitize( $_POST );
			$exchange_rate   = array();
			try {
				if ( 'all' === $currency_object['type'] ) {
					$currencies       = $currency_object['currencies'];
					$default_currency = Helper::default_currency_code();
					foreach ( $currencies as $currency ) {
						if ( $default_currency !== $currency ) {
							if ( '' === $currency ) {
								array_push( $exchange_rate, 'N/A' );
							} else {
								$currency_params_template = array(
									'$src'  => $default_currency,
									'$dest' => $currency,
								);
								$json_data                = Helper::get_exchange_rates( $currency_params_template );
								if ( 200 !== $json_data['response']['code'] ) {
									array_push( $exchange_rate, 'N/A' );
									continue;
								}
								$decoded_json_data = json_decode( $json_data['body'] );
								if ( isset( $decoded_json_data->chart->result[0]->meta->regularMarketPrice ) ) {
									array_push( $exchange_rate, $decoded_json_data->chart->result[0]->meta->regularMarketPrice );
								} elseif ( isset( $decoded_json_data->chart->result[0]->indicators->quote[0]->close ) ) {
									array_push( $exchange_rate, $decoded_json_data->chart->result[0]->indicators->quote[0]->close[0] );
								} else {
									array_push( $exchange_rate, $decoded_json_data->chart->result[0]->meta->previousClose );
								}
							}
						} else {
							array_push( $exchange_rate, 1 );
						}
					}
					wp_send_json_success(
						array(
							'success'      => true,
							'exchangeRate' => $exchange_rate,
						)
					);
				}
				$currency_params_template = array(
					'$src'  => $currency_object['srcCurrency'],
					'$dest' => $currency_object['destCurrency'],
				);
				$json_data                = Helper::get_exchange_rates( $currency_params_template );
				if ( 200 !== $json_data['response']['code'] ) {
					wp_send_json_error();
				}
				$decoded_json_data = json_decode( $json_data['body'] );
				if ( isset( $decoded_json_data->chart->result[0]->meta->regularMarketPrice ) ) {
					$exchange_rate = $decoded_json_data->chart->result[0]->meta->regularMarketPrice;
				} elseif ( isset( $decoded_json_data->chart->result[0]->indicators->quote[0]->close ) ) {
					$exchange_rate = $decoded_json_data->chart->result[0]->indicators->quote[0]->close[0];
				} else {
					$exchange_rate = $decoded_json_data->chart->result[0]->meta->previousClose;
				}
				wp_send_json_success(
					array(
						'exchangeRate' => $exchange_rate,
					)
				);
			} catch ( \Exception $e ) {
				wp_send_json_error( $e );
			}
		}
	}


	public function set_currency_manage_settings( $currencies ) {
		// $currencies_array = Helper::sanitize_array( $currencies );
		foreach ( $currencies as $key => $currency ) {
			if ( isset( $currency['ID'] ) ) {
				$update_currency = array(
					'ID'         => $currency['ID'],
					'post_title' => $currency['currency'],
					'menu_order' => $key,
				);
				wp_update_post( $update_currency );
				Helper::update_post_meta_currency( $currency['ID'], $currency );
			} else {
				$new_currency    = array(
					'post_title'  => $currency['currency'],
					'post_type'   => Helper::get_post_type(),
					'post_status' => 'publish',
					'menu_order'  => $key,
				);
				$new_currency_ID = wp_insert_post( $new_currency );
				if ( ! is_wp_error( $new_currency_ID ) ) {
					Helper::update_post_meta_currency( $new_currency_ID, $currency );
				}
			}
		}
	}

	public function set_checkout_options_settings( $all_currencies_settings_data ) {
		$currencies_array               = Helper::sanitize_array( $all_currencies_settings_data['currencies'] );
		$is_checkout_different_currency = sanitize_text_field( $all_currencies_settings_data['isCheckoutDifferentCurrency'] ) === '1' ? 1 : 0;
		update_option( 'yay_currency_checkout_different_currency', $is_checkout_different_currency );
		foreach ( $currencies_array as $currency ) {
			if ( isset( $currency['ID'] ) ) {
				update_post_meta( $currency['ID'], 'status', '1' === $currency['status'] ? 1 : 0 );
				update_post_meta( $currency['ID'], 'payment_methods', $currency['paymentMethods'] );
			}
		}
	}

	public function set_display_options_settings( $all_currencies_settings_data ) {
		$is_show_on_single_product_page           = sanitize_text_field( $all_currencies_settings_data['isShowOnSingleProductPage'] ) === '1' ? 1 : 0;
		$switcher_position_on_single_product_page = sanitize_text_field( $all_currencies_settings_data['switcherPositionOnSingleProductPage'] );
		$is_show_flag_in_switcher                 = sanitize_text_field( $all_currencies_settings_data['isShowFlagInSwitcher'] ) === '1' ? 1 : 0;
		$is_show_currency_name_in_switcher        = sanitize_text_field( $all_currencies_settings_data['isShowCurrencyNameInSwitcher'] ) === '1' ? 1 : 0;
		$is_show_currency_symbol_in_switcher      = sanitize_text_field( $all_currencies_settings_data['isShowCurrencySymbolInSwitcher'] ) === '1' ? 1 : 0;
		$is_show_currency_code_in_switcher        = sanitize_text_field( $all_currencies_settings_data['isShowCurrencyCodeInSwitcher'] ) === '1' ? 1 : 0;
		$switcher_size                            = sanitize_text_field( $all_currencies_settings_data['switcherSize'] );
		update_option( 'yay_currency_show_single_product_page', $is_show_on_single_product_page );
		update_option( 'yay_currency_switcher_position_on_single_product_page', $switcher_position_on_single_product_page );
		update_option( 'yay_currency_show_flag_in_switcher', $is_show_flag_in_switcher );
		update_option( 'yay_currency_show_currency_name_in_switcher', $is_show_currency_name_in_switcher );
		update_option( 'yay_currency_show_currency_symbol_in_switcher', $is_show_currency_symbol_in_switcher );
		update_option( 'yay_currency_show_currency_code_in_switcher', $is_show_currency_code_in_switcher );
		update_option( 'yay_currency_switcher_size', $switcher_size );
	}

	public function set_advance_options_settings( $all_currencies_settings_data ) {
		$is_show_recommen_dations = sanitize_text_field( $all_currencies_settings_data['isShowRecommendations'] ) === '1' ? 1 : 0;
		update_option( 'isShowRecommendations', $is_show_recommen_dations );
	}

	public function delete_currency() {
		check_ajax_referer( 'yay-currency-nonce', 'nonce', true );
		if ( isset( $_POST['data'] ) ) {
			$currency_ID = isset( $_POST['data']['ID'] ) ? sanitize_text_field( $_POST['data']['ID'] ) : false;
			if ( ! $currency_ID ) {
				$currency_code = isset( $_POST['data']['currency'] ) ? sanitize_text_field( $_POST['data']['currency'] ) : false;
				$currency_data = YayCurrencyHelper::get_currency_by_currency_code( $currency_code );
				$currency_ID   = $currency_data ? $currency_data['ID'] : false;
			}

			$is_deleted = $currency_ID ? wp_delete_post( $currency_ID ) : false;

			// delete cache yay currencies list
			Helper::delete_yay_currencies_transient();

			wp_send_json(
				array(
					'status' => $is_deleted,
				)
			);
		}
	}


	// Update Order Product Loop
	protected function update_wc_order_product_loop( $order_id ) {
		global $wpdb;
		$product_item = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_order_product_lookup WHERE order_id = %d",
				$order_id
			)
		);
		if ( $product_item ) {
			foreach ( $product_item as $item ) {
				do_action( 'woocommerce_analytics_update_product', $item->order_item_id, $item->order_id );
			}
		}

	}

	// Update Order Coupon Loop
	protected function update_wc_order_coupon_loop( $order_id ) {
		global $wpdb;
		$coupon_item = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_order_coupon_lookup WHERE order_id = %d",
				$order_id,
			)
		);
		if ( $coupon_item ) {
			foreach ( $coupon_item as $item ) {
				do_action( 'woocommerce_analytics_update_coupon', $item->coupon_id, $item->order_id );
			}
		}

	}

	// Update Order Tax Loop
	protected function update_wc_order_tax_loop( $order_id ) {
		global $wpdb;
		$tax_item = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_order_tax_lookup WHERE order_id = %d",
				$order_id
			)
		);
		if ( $tax_item ) {
			foreach ( $tax_item as $item ) {
				do_action( 'woocommerce_analytics_update_tax', $item->tax_rate_id, $item->order_id );
			}
		}

	}

	public function ajax_handle_sync_orders_revert_to_base() {

		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : false;

		if ( ! $nonce || ! wp_verify_nonce( sanitize_key( $nonce ), 'yay-currency-admin-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce invalid', 'yay-currency' ) ) );
		}

		if ( isset( $_POST['_yay_sync'] ) ) {
			$paged           = isset( $_POST['_paged'] ) && ! empty( $_POST['_paged'] ) ? intval( sanitize_text_field( $_POST['_paged'] ) ) : 1;
			$sync_currencies = isset( $_POST['_sync_currencies'] ) && ! empty( $_POST['_sync_currencies'] ) ? map_deep( wp_unslash( $_POST['_sync_currencies'] ), 'sanitize_text_field' ) : array();
			$data            = Helper::get_list_orders_not_revert_to_base( $sync_currencies, $paged );
			if ( isset( $data['results'] ) && $data['results'] ) {
				foreach ( $data['results'] as $order_id ) {
					$order = wc_get_order( $order_id );
					if ( ! $order ) {
						continue;
					}
					Helper::order_match_reverted( $order_id, $order );
					self::update_wc_order_product_loop( $order_id );
					self::update_wc_order_coupon_loop( $order_id );
					self::update_wc_order_tax_loop( $order_id );
					OrdersStatsDataStore::update( $order );
				}
			}

			$args = array(
				'orders' => $data['results'],
			);

			if ( isset( $data['orders'] ) && $data['orders'] ) {
				$args['paged'] = $paged + 1;
			} else {
				update_option( 'yay_currency_orders_synced_to_base', 'yes' );
			}

			wp_send_json_success( $args );
		}

		wp_send_json_error();
	}

	public function ajax_handle_get_cart_subtotal_blocks() {
		check_ajax_referer( 'yay-currency-nonce', 'nonce', true );
		$results                  = array();
		$default_currency         = Helper::default_currency_code();
		$cart_subtotal            = apply_filters( 'yay_currency_get_cart_subtotal_default', 0 );
		$apply_currency           = YayCurrencyHelper::get_currency_by_currency_code( $default_currency );
		$cart_subtotal            = YayCurrencyHelper::format_price_currency( $cart_subtotal, $apply_currency );
		$currency_symbol          = YayCurrencyHelper::get_symbol_by_currency_code( $default_currency );
		$format                   = YayCurrencyHelper::format_currency_symbol( $apply_currency );
		$formatted_price          = sprintf( $format, '<span class="woocommerce-Price-currencySymbol">' . $currency_symbol . '</span>', $cart_subtotal );
		$cart_subtotal            = '<bdi>' . $formatted_price . '</bdi></span>';
		$results['cart_subtotal'] = $cart_subtotal;
		wp_send_json_success( $results );

	}
}
