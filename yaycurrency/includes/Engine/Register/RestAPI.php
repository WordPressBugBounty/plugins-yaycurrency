<?php
namespace Yay_Currency\Engine\Register;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;

use Yay_Currency\Helpers\RateHelper;


defined( 'ABSPATH' ) || exit;

class RestAPI {
	use SingletonTrait;

	protected function __construct() {

		add_action( 'rest_api_init', array( $this, 'yay_currency_endpoints' ) );

	}

	public function yay_currency_endpoints() {

		// POST /settings
		register_rest_route(
			'yaycurrency/v1',
			'/settings',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'exec_patch_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		// POST /exchange-rate
		register_rest_route(
			'yaycurrency/v1',
			'/exchange-rate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_exchange_rate' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		// POST /delete-currency
		register_rest_route(
			'yaycurrency/v1',
			'/delete-currency',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_currency' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		// POST /update currency settings
		register_rest_route(
			'yaycurrency/v1',
			'/update-currency-settings',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_currency_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			'yaycurrency/v1',
			'/mark-reviewed',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'mark_reviewed' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		do_action( 'yay_currency_rest_api_endpoints' );
	}

	public function permission_callback( $request ) {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
	}

	// POST METHOD
	private function set_currency_manage_settings( $list_currencies ) {
		foreach ( $list_currencies as $key => $currency ) {
			if ( isset( $currency['ID'] ) ) {
				$update_currency = array(
					'ID'         => $currency['ID'],
					'post_title' => $currency['currency'],
					'menu_order' => $key,
				);
				wp_update_post( $update_currency );
				Helper::update_post_meta_currency( $currency['ID'], $currency );
				// Sync currency settings
				if ( isset( $currency['currency'] ) && get_option( 'woocommerce_currency' ) === $currency['currency'] ) {
					Helper::sync_currency_settings( $currency['ID'] );
				}
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

	private function set_checkout_options_settings( $checkout_options ) {
		if ( ! empty( $checkout_options ) ) {
			if ( isset( $checkout_options['isCheckoutDifferentCurrency'] ) ) {
				update_option( 'yay_currency_checkout_different_currency', $checkout_options['isCheckoutDifferentCurrency'] );
			}

			do_action( 'YayCurrency/Data/SetCheckoutOptions', $checkout_options );
		}

	}

	private function set_display_options_settings( $display_options ) {

		if ( ! empty( $display_options ) ) {
			update_option( 'yay_currency_show_single_product_page', $display_options['isShowOnSingleProductPage'] );
			update_option( 'yay_currency_switcher_position_on_single_product_page', $display_options['switcherPositionOnSingleProductPage'] );
			update_option( 'yay_currency_show_flag_in_switcher', $display_options['isShowFlagInSwitcher'] );
			update_option( 'yay_currency_show_currency_name_in_switcher', $display_options['isShowCurrencyNameInSwitcher'] );
			update_option( 'yay_currency_show_currency_symbol_in_switcher', $display_options['isShowCurrencySymbolInSwitcher'] );
			update_option( 'yay_currency_show_currency_code_in_switcher', $display_options['isShowCurrencyCodeInSwitcher'] );
			update_option( 'yay_currency_switcher_size', $display_options['switcherSize'] );
			if ( isset( $display_options['currencyUnitType'] ) ) {
				update_option( 'yay_currency_currency_unit_type', $display_options['currencyUnitType'] );
			}
			do_action( 'YayCurrency/Data/SetDisplayOptions', $display_options );
		}

	}

	public function exec_patch_settings( $request ) {
		$params = $request->get_params();

		if ( $params ) {
			// delete cache yay currencies list
			Helper::delete_yay_currencies_transient();

			$manage_currency = isset( $params['manage_currency'] ) && ! empty( $params['manage_currency'] ) ? $params['manage_currency'] : array();
			self::set_currency_manage_settings( $manage_currency );

			$checkout_options = isset( $params['checkout_options'] ) && ! empty( $params['checkout_options'] ) ? $params['checkout_options'] : array();
			self::set_checkout_options_settings( $checkout_options );

			$display_options = isset( $params['display_options'] ) && ! empty( $params['display_options'] ) ? $params['display_options'] : array();
			self::set_display_options_settings( $display_options );

			if ( isset( $params['isShowRecommendations'] ) ) {
				update_option( 'isShowRecommendations', $params['isShowRecommendations'] );
			}

			foreach ( $manage_currency as $currency ) {
				if ( isset( $currency['ID'] ) && ! empty( $currency['ID'] ) ) {
					if ( isset( $currency['status'] ) ) {
						update_post_meta( $currency['ID'], 'status', $currency['status'] );
					}

					if ( isset( $currency['paymentMethods'] ) ) {
						$payment_methods = empty( $currency['paymentMethods'] ) ? array( 'all' ) : $currency['paymentMethods'];
						update_post_meta( $currency['ID'], 'payment_methods', $payment_methods );
					}
				}
			}

			if ( class_exists( 'WC_Cache_Helper' ) ) {
				\WC_Cache_Helper::get_transient_version( 'product', true ); // Update product price (currency) after change value.
			}
		}

		// Return the updated settings
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Settings saved!', 'yay-currency' ),
			)
		);
	}

	// UPDATE EXCHANGE RATE
	protected function get_exchange_rates_from_finance_api( $currency_object ) {
		$currencies       = $currency_object['currencies'];
		$default_currency = Helper::default_currency_code();
		$finance_api      = 'default'; // yahoo finance api

		return array_map(
			function ( $currency ) use ( $default_currency, $finance_api ) {
				// Return 1 for default currency
				if ( $default_currency === $currency ) {
					return 1;
				}

				// Return N/A for empty currency
				if ( empty( $currency ) ) {
					return 'N/A'; // return N/A if no currency
				}

				// Get exchange rate
				$exchange_rate = RateHelper::get_exchange_rate_from_finance_api(
					array(
						'srcCurrency'  => $default_currency,
						'destCurrency' => $currency,
						'financeApi'   => $finance_api,
					)
				);

				return $exchange_rate ? $exchange_rate : 'N/A'; // return N/A if no exchange rate
			},
			$currencies
		);
	}

	/**
	 * Callback for POST /exchange-rate endpoint
	 *
	 * @param WP_REST_Request $request The REST request object
	 * @return WP_REST_Response|WP_Error The response
	 */
	public function update_exchange_rate( $request ) {
		// Get the request data
		$data = $request->get_json_params();

		if ( ! $data ) {
			return new \WP_Error( 'missing_data', 'Missing data parameter', array( 'status' => 400 ) );
		}

		try {

			if ( 'all' === $data['type'] ) {
				// Fetch exchange rates for all currencies
				$exchange_rates = $this->get_exchange_rates_from_finance_api( $data );
				return rest_ensure_response(
					array(
						'success'      => true,
						'exchangeRate' => $exchange_rates,
					)
				);
			}

			// Fetch exchange rate for a single currency
			$exchange_rate = RateHelper::get_exchange_rate_from_finance_api( $data );

			if ( ! $exchange_rate ) {
				return new \WP_Error( 'no_exchange_rate', 'Failed to retrieve exchange rate', array( 'status' => 500 ) );
			}

			return rest_ensure_response(
				array(
					'exchangeRate' => array( $exchange_rate ),
				)
			);

		} catch ( \Exception $e ) {
			return new \WP_Error( 'error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	// DELETE CURRENCY
	/**
	 * Callback for POST /delete-currency endpoint
	 *
	 * @param WP_REST_Request $request The REST request object
	 * @return WP_REST_Response|WP_Error The response
	 */
	public function delete_currency( $request ) {
		// Get the request data
		$params = $request->get_json_params();

		if ( ! isset( $params['currencyId'] ) ) {
			return new \WP_Error( 'missing_data', 'Missing data parameter', array( 'status' => 400 ) );
		}

		// Delete the currency post
		$currency_id = $params['currencyId'];
		$is_deleted  = $currency_id ? wp_delete_post( $currency_id ) : false;

		if ( $is_deleted ) {
			// delete cache yay currencies list
			Helper::delete_yay_currencies_transient();
		}

		// Return response
		return rest_ensure_response( array( 'status' => true ) );
	}

	public function update_currency_settings( $request ) {
		$params = $request->get_json_params();

		if ( ! isset( $params['currency'] ) ) {
			return new \WP_Error( 'missing_data', 'Missing data parameter', array( 'status' => 400 ) );
		}

		$currency_code         = $params['currency'];
		$default_currency_code = Helper::default_currency_code();
		update_option( 'woocommerce_currency', $currency_code );
		RateHelper::sync_currency_exchange_rates( $currency_code, $default_currency_code, false );
		// Return response
		return rest_ensure_response( array( 'status' => true ) );
	}

	public function mark_reviewed() {
		update_option( 'yaycurrency_reviewed', true );

		return rest_ensure_response( true );
	}
}
