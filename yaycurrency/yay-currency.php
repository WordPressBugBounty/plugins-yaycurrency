<?php

/**
 * Plugin Name:       YayCurrency
 * Plugin URI:        https://yaycommerce.com/yaycurrency-woocommerce-multi-currency-switcher/
 * Description:       Provide multiple currencies for WooCommerce. Let your potential customers switch currency on the go.
 * Version:           3.3.5
 * Author:            YayCommerce
 * Author URI:        https://yaycommerce.com
 * Text Domain:       yay-currency
 * Domain Path:       /languages
 * Requires at least: 4.7
 * Tested up to: 7.0
 * Requires PHP: 5.4
 * WC requires at least: 5.0.0
 * WC tested up to: 11.0
 *
 * @package yaycommerce/yaycurrency
 */

namespace Yay_Currency;

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'Yay_Currency\\plugin_init' ) || class_exists( 'YayCurrencyPluginAdapter' ) ) {
	$fallback_file = plugin_dir_path( __FILE__ ) . 'includes/Fallback.php';
	if ( file_exists( $fallback_file ) ) {
		require_once $fallback_file;
	}
	add_action(
		'admin_init',
		function () {
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
	);
	return;
}

if ( ! defined( 'YAY_CURRENCY_FILE' ) ) {
	define( 'YAY_CURRENCY_FILE', __FILE__ );
}

if ( ! defined( 'YAY_CURRENCY_VERSION' ) ) {
	define( 'YAY_CURRENCY_VERSION', '3.3.5' );
}

if ( ! defined( 'YAY_CURRENCY_PLUGIN_URL' ) ) {
	define( 'YAY_CURRENCY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'YAY_CURRENCY_PLUGIN_DIR' ) ) {
	define( 'YAY_CURRENCY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'YAY_CURRENCY_BASE_NAME' ) ) {
	define( 'YAY_CURRENCY_BASE_NAME', plugin_basename( __FILE__ ) );
}



require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/YayCurrencyPluginAdapter.php';

add_action(
	'plugins_loaded',
	function () {
		\YayCurrencyScoped\YayCommerce\AdminShell\AdminShell::boot();
		\YayCurrencyScoped\YayCommerce\AdminShell\AdminShell::register_plugin(
			new \YayCurrencyPluginAdapter()
		);
	},
	5
);

if ( ! function_exists( 'Yay_Currency\\plugin_init' ) ) {

	function plugin_init() {
		if ( ! function_exists( 'WC' ) ) {
			add_action( 'admin_notices', array( \Yay_Currency\Engine\ActDeact::class, 'install_yaycurrency_admin_notice' ) );
			return;
		}
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				}
			}
		);
		Initialize::get_instance();
		I18n::loadPluginTextdomain();
		if ( function_exists( 'register_block_type' ) ) {
			require_once YAY_CURRENCY_PLUGIN_DIR . 'blocks/init.php';
		}
	}
}

if ( ! wp_installing() ) {
	add_action( 'plugins_loaded', 'Yay_Currency\\plugin_init' );
}

register_activation_hook( YAY_CURRENCY_FILE, array( \Yay_Currency\Engine\ActDeact::class, 'activate' ) );
register_deactivation_hook( YAY_CURRENCY_FILE, array( \Yay_Currency\Engine\ActDeact::class, 'deactivate' ) );
