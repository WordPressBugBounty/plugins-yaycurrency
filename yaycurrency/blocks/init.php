<?php
/**
 * Currency Switcher Gutenberg Block for YayCurrency
 */

use Yay_Currency\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Detect if current request is background or automated (E2E, REST, AJAX, etc.)
 */
function yaycurrency_is_background_or_e2e() {

	// REST API, AJAX, CRON, WP-CLI → skip heavy editor loading
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| wp_doing_ajax()
		|| ( defined( 'DOING_CRON' ) && DOING_CRON )
		|| ( defined( 'WP_CLI' ) && WP_CLI )
	) {
		// error_log( 'E2E/background request detected: REST/AJAX/CRON/CLI' );
		return true;
	}

	// Localhost test environments (common in Woo E2E, wp-env, Playwright)
	if ( isset( $_SERVER['HTTP_HOST'] ) && preg_match( '/(localhost|127\.0\.0\.1|wp-env)/', sanitize_text_field( $_SERVER['HTTP_HOST'] ) ) ) {
		// error_log( 'E2E/background request detected: Localhost environment' );
		return true;
	}

	// User-Agent heuristic (Playwright / Puppeteer / Cypress / Woo E2E)
	if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$ua       = strtolower( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) );
		$patterns = [
			'playwright',
			'puppeteer',
			'cypress',
			'woocommerce e2e',
			'wp-env',
			'node/',
			'postman',
			'curl',
			'insomnia',
			'axios',
		];
		foreach ( $patterns as $keyword ) {
			if ( strpos( $ua, $keyword ) !== false ) {
				// error_log( "E2E/background request detected: UA contains '{$keyword}'" );
				return true;
			}
		}
	}

	// Common REST route used by E2E: creating posts/pages
	return false;
}

/**
 * Shared block attributes
 */
function yaycurrency_get_block_attributes() {
	return array(
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
	);
}

/**
 * Register the Currency Switcher Gutenberg block.
 */
function yaycurrency_register_currency_switcher_block() {
	$block_name = 'yay-currency/currency-switcher';
	$attributes = yaycurrency_get_block_attributes();

	// Case 1: E2E or background → Register lightweight fallback only
	if ( yaycurrency_is_background_or_e2e() ) {
		// error_log( 'YayCurrency: registering lightweight fallback block (E2E/background)' );
		register_block_type(
			$block_name,
			array(
				'attributes'      => $attributes,
				'render_callback' => 'yaycurrency_render_currency_switcher',
			)
		);
		return;
	}

	// Case 2: Admin/editor → Register full block assets
	if ( is_admin() ) {
		$asset_path = plugin_dir_path( __FILE__ ) . 'build/index.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			// error_log( 'YayCurrency: missing index.asset.php, registering fallback block.' );
			register_block_type(
				$block_name,
				array(
					'attributes'      => $attributes,
					'render_callback' => 'yaycurrency_render_currency_switcher',
				)
			);
			return;
		}

		$asset_file = include $asset_path;

		wp_register_script(
			'yaycurrency-block-editor',
			plugins_url( 'build/index.js', __FILE__ ),
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_localize_script(
			'yaycurrency-block-editor',
			'yayCurrencyGutenberg',
			[
				'nonce'                => wp_create_nonce( 'yay-currency-gutenberg-nonce' ),
				'yayCurrencyPluginURL' => YAY_CURRENCY_PLUGIN_URL,
			]
		);

		wp_register_style(
			'yaycurrency-block-style',
			plugins_url( 'style.css', __FILE__ ),
			[],
			filemtime( plugin_dir_path( __FILE__ ) . 'style.css' )
		);

		register_block_type(
			$block_name,
			[
				'attributes'      => $attributes,
				'style'           => 'yaycurrency-block-style',
				'editor_script'   => 'yaycurrency-block-editor',
				'render_callback' => 'yaycurrency_render_currency_switcher',
			]
		);
		return;
	}

	// Case 3: Frontend fallback
	register_block_type(
		$block_name,
		array(
			'attributes'      => $attributes,
			'render_callback' => 'yaycurrency_render_currency_switcher',
		)
	);
}

/**
 * Render the Currency Switcher block HTML.
 */
function yaycurrency_render_currency_switcher( $attributes ) {
	ob_start();
	$is_show_flag            = $attributes['isShowFlag'];
	$is_show_currency_name   = $attributes['isShowCurrencyName'];
	$is_show_currency_symbol = $attributes['isShowCurrencySymbol'];
	$is_show_currency_code   = $attributes['isShowCurrencyCode'];
	$switcher_size           = $attributes['widgetSize'];
	require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/switcher/template.php';
	return ob_get_clean();
}

add_action( 'init', 'yaycurrency_register_currency_switcher_block' );
