<?php

use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Registers all block assets so that they can be enqueued through Gutenberg in
 * the corresponding context.
 *
 * Passes translations to JavaScript.
 */
function yaycurrency_currency_switcher_register_block() {

	// automatically load dependencies and version
	$asset_file = plugin_dir_path( __FILE__ ) . 'build/index.asset.php';
	if ( file_exists( $asset_file ) ) {
		$asset_file = include $asset_file;
		wp_register_script(
			'yaycurrency-currency-switcher-block-editor-script',
			plugins_url( 'build/index.js', __FILE__ ),
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);
	}
	wp_localize_script(
		'yaycurrency-currency-switcher-block-editor-script',
		'yayCurrencyGutenberg',
		array(
			'nonce'                => wp_create_nonce( 'yay-currency-gutenberg-nonce' ),
			'yayCurrencyPluginURL' => YAY_CURRENCY_PLUGIN_URL,
		)
	);

	wp_register_style(
		'yaycurrency-currency-switcher-block-editor-style',
		plugins_url( 'style.css', __FILE__ ),
		array(),
		filemtime( plugin_dir_path( __FILE__ ) . 'style.css' )
	);

	register_block_type(
		'yay-currency/currency-switcher',
		array(
			'attributes'      => array(
				'currencyName'         => array(
					'type'    => 'string',
					'default' => 'United States dollar',
				),
				'currencySymbol'       => array(
					'type'    => 'string',
					'default' => '($)',
				),
				'hyphen'               => array(
					'type'    => 'string',
					'default' => ' - ',
				),
				'currencyCode'         => array(
					'type'    => 'string',
					'default' => 'USD',
				),
				'isShowFlag'           => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'isShowCurrencyName'   => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'isShowCurrencySymbol' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'isShowCurrencyCode'   => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'widgetSize'           => array(
					'type'    => 'string',
					'default' => 'small',
				),
			),
			'style'           => 'yaycurrency-currency-switcher-block-editor-style',
			'editor_script'   => 'yaycurrency-currency-switcher-block-editor-script',
			'render_callback' => 'yaycurrency_switcher_render_html',
		)
	);
}

function yaycurrency_switcher_render_html( $attributes ) {
	$is_show_flag            = $attributes['isShowFlag'];
	$is_show_currency_name   = $attributes['isShowCurrencyName'];
	$is_show_currency_symbol = $attributes['isShowCurrencySymbol'];
	$is_show_currency_code   = $attributes['isShowCurrencyCode'];
	$switcher_size           = $attributes['widgetSize'];

	require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/switcher/template.php';

}

add_action( 'init', 'yaycurrency_currency_switcher_register_block' );
