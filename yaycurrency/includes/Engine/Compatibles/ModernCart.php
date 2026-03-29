<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\YayCurrencyHelper;


use Yay_Currency\Utils\SingletonTrait;


defined( 'ABSPATH' ) || exit;

class ModernCart {


	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {
		if ( ! defined( 'MODERNCART_VER' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();
		add_filter( 'yay_currency_detect_action_args', array( $this, 'yay_currency_detect_action_args' ), 10, 1 );
		add_action( 'init', array( $this, 'init' ) );

	}
	public function yay_currency_detect_action_args( $action_args ) {
		$ajax_args   = array( 'moderncart_add_to_cart', 'moderncart_refresh_slide_out_cart', 'moderncart_refresh_floating_cart', 'moderncart_update_cart' );
		$action_args = array_unique( array_merge( $action_args, $ajax_args ) );
		return $action_args;
	}

	public function init() {

		if ( ! WC()->cart || ! WC()->session ) {
			return;
		}

		$current          = YayCurrencyHelper::get_current_currency();
		$current_currency = isset( $current['currency'] ) ? $current['currency'] : '';

		$stored = WC()->session->get( 'moderncart_currency' );

		if ( $stored !== $current_currency ) {

			WC()->session->set( 'moderncart_currency', $current_currency );

			// reset cache
			WC()->cart->set_totals( [] );

			// force recalc
			WC()->cart->calculate_totals();
		}

	}
}
