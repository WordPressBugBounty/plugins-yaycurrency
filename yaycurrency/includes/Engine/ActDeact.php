<?php
namespace Yay_Currency\Engine;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;

/**
 * Activate and deactive method of the plugin and relates.
 */
class ActDeact {

	use SingletonTrait;

	protected function __construct() {}

	public static function install_yaycurrency_admin_notice() {
		/* translators: %s: Woocommerce link */
		echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'YayCurrency is enabled but not effective. It requires %s in order to work', 'yay-currency' ), '<a href="' . esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ) . '">WooCommerce</a>' ) . '</strong></p></div>';
		return false;
	}

	public static function activate() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		// Create new Currency when empty
		$currencies = Helper::get_currencies_post_type();
		if ( ! $currencies ) {
			update_option( 'yay_currency_orders_synced_to_base', 'yes' );
			Helper::create_new_currency();
		}
	}

	public static function deactivate() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		do_action( 'yay_currency_deactivate' );
	}
}
