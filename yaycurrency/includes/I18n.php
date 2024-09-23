<?php

namespace Yay_Currency;

defined( 'ABSPATH' ) || exit;

class I18n {

	public static function loadPluginTextdomain() {
		if ( function_exists( 'determine_locale' ) ) {
			$locale = determine_locale();
		} else {
			$locale = is_admin() ? get_user_locale() : get_locale();
		}
		unload_textdomain( 'yay-currency' );
		load_textdomain( 'yay-currency', YAY_CURRENCY_PLUGIN_DIR . '/languages/yay-currency-' . $locale . '.mo' );
		load_plugin_textdomain( 'yay-currency', false, YAY_CURRENCY_PLUGIN_DIR . '/languages/' );
	}
}
