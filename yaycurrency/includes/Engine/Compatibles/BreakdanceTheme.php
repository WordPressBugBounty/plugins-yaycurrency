<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class BreakdanceTheme {

	use SingletonTrait;

	private $apply_currency = array();
	public function __construct() {

		if ( 'breakdance-zero' !== Helper::get_current_theme() ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'yay_currency_detect_action_args', array( $this, 'yay_currency_detect_action_args' ), 10, 1 );
		add_filter( 'woocommerce_gzd_cart_taxes', array( $this, 'woocommerce_gzd_cart_taxes' ), 999, 3 );
	}

	public function yay_currency_detect_action_args( $action_args ) {
		$ajax_args   = array( 'my_add_to_cart' );
		$action_args = array_unique( array_merge( $action_args, $ajax_args ) );
		return $action_args;
	}


	public function woocommerce_gzd_cart_taxes( $tax_array, $cart, $include_shipping_taxes ) {
		if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'my_add_to_cart' === $_REQUEST['action'] ) {
			foreach ( $tax_array as $key => $tax ) {
				$tax_array[ $key ]['amount']                = YayCurrencyHelper::calculate_price_by_currency( $tax_array[ $key ]['amount'], true, $this->apply_currency );
				$tax_array[ $key ]['tax']->amount           = $tax_array[ $key ]['amount'];
				$tax_array[ $key ]['tax']->formatted_amount = YayCurrencyHelper::format_price( $tax_array[ $key ]['amount'] );
			}
		}
		return $tax_array;
	}
}
