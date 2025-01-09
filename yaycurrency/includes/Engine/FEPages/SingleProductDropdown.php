<?php
namespace Yay_Currency\Engine\FEPages;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class SingleProductDropdown {

	use SingletonTrait;

	public $apply_currencies = array();

	public $all_currencies = array();

	protected function __construct() {

		$is_show_on_single_product_page = get_option( 'yay_currency_show_single_product_page', 1 );

		if ( $is_show_on_single_product_page ) {
			$switcherPositionOnSingleProductPage = get_option( 'yay_currency_switcher_position_on_single_product_page', 'before_description' );
			if ( 'after_description' === $switcherPositionOnSingleProductPage ) {
				add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'dropdown_price_in_different_currency' ) );
			} else {
				add_action( 'woocommerce_single_product_summary', array( $this, 'dropdown_price_in_different_currency' ) );
			}
		}
	}

	public function dropdown_price_in_different_currency() {
		$is_show_flag            = get_option( 'yay_currency_show_flag_in_switcher', 1 );
		$is_show_currency_name   = get_option( 'yay_currency_show_currency_name_in_switcher', 1 );
		$is_show_currency_symbol = get_option( 'yay_currency_show_currency_symbol_in_switcher', 1 );
		$is_show_currency_code   = get_option( 'yay_currency_show_currency_code_in_switcher', 1 );
		$switcher_size           = get_option( 'yay_currency_switcher_size', 'medium' );

		require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/switcher/template.php';

	}
}
