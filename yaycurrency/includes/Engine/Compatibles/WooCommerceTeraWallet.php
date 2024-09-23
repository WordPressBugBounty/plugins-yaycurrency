<?php

namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://wordpress.org/plugins/woo-wallet/

class WooCommerceTeraWallet {
	use SingletonTrait;

	private $converted_currency = array();
	private $apply_currency     = array();

	public function __construct() {
		if ( class_exists( 'WooWallet' ) ) {
			$this->converted_currency = YayCurrencyHelper::converted_currency();
			$this->apply_currency     = YayCurrencyHelper::detect_current_currency();

			if ( is_admin() && wp_doing_ajax() ) {
				if ( isset( $_REQUEST['action'] ) && 'draw_wallet_transaction_details_table' === $_REQUEST['action'] ) {
					add_filter( 'woocommerce_currency_symbol', array( $this, 'change_existing_currency_symbol' ), 10, 2 );
				}
			}

			add_filter( 'woo_wallet_current_balance', array( $this, 'woo_wallet_current_balance' ), 10, 2 );
			add_filter( 'woo_wallet_amount', array( $this, 'woo_wallet_amount' ), 10, 2 );
			add_filter( 'woo_wallet_rechargeable_amount', array( $this, 'woo_wallet_rechargeable_amount' ) ); // When user add amount Wallet Topup

		}
	}

	public function change_existing_currency_symbol( $currency_symbol, $currency ) {

		if ( ! $this->apply_currency ) {
			return $currency_symbol;
		}

		if ( isset( $this->apply_currency['currency'] ) ) {
			return wp_kses_post( html_entity_decode( $this->apply_currency['symbol'] ) );
		}

		return $currency_symbol;

	}

	public function woo_wallet_current_balance( $wallet_balance, $user_id ) {
		if ( $user_id ) {
			$wallet_balance = 0;
			foreach ( $this->converted_currency as $key => $currency ) {
				$credit_amount = array_sum(
					wp_list_pluck(
						get_wallet_transactions(
							array(
								'user_id' => $user_id,
								'where'   => array(
									array(
										'key'   => 'type',
										'value' => 'credit',
									),
									array(
										'key'   => 'currency',
										'value' => $currency['currency'],
									),
								),
							)
						),
						'amount'
					)
				);
				$debit_amount  = array_sum(
					wp_list_pluck(
						get_wallet_transactions(
							array(
								'user_id' => $user_id,
								'where'   => array(
									array(
										'key'   => 'type',
										'value' => 'debit',
									),
									array(
										'key'   => 'currency',
										'value' => $currency['currency'],
									),
								),
							)
						),
						'amount'
					)
				);
				$balance       = $credit_amount - $debit_amount;
				$rate_fee      = YayCurrencyHelper::get_rate_fee( $currency );
				if ( 0 !== $rate_fee ) {
					$wallet_balance += ( $balance / $rate_fee );
				} else {
					$wallet_balance += $balance;
				}
			}
			if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
				return $wallet_balance;
			}
			if ( ! is_admin() ) {
				$wallet_balance = YayCurrencyHelper::calculate_price_by_currency( $wallet_balance, false, $this->apply_currency );
			}
		}

		return $wallet_balance;
	}

	public function woo_wallet_amount( $amount, $currency ) {
		$default_currency = Helper::default_currency_code();
		if ( is_admin() && ! wp_doing_ajax() ) {
			if ( $currency !== $default_currency ) {
				$currency_data = YayCurrencyHelper::get_currency_by_currency_code( $currency, $this->converted_currency );
				$amount        = $amount / YayCurrencyHelper::get_rate_fee( $currency_data );
			}
		} else {
			if ( $currency !== $default_currency ) {
				$currency_data = YayCurrencyHelper::get_currency_by_currency_code( $currency, $this->converted_currency );
				$amount        = $amount / YayCurrencyHelper::get_rate_fee( $currency_data );
			}
			if ( $this->apply_currency['currency'] !== $default_currency ) {
				$amount = YayCurrencyHelper::calculate_price_by_currency( $amount, false, $this->apply_currency );
			}
		}

		return $amount;
	}

	public function woo_wallet_rechargeable_amount( $amount ) {

		if ( Helper::default_currency_code() !== $this->apply_currency['currency'] ) {
			$amount = $amount / YayCurrencyHelper::get_rate_fee( $this->apply_currency );
		}
		return $amount;
	}
}
