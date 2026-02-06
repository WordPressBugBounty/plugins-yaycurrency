<?php
namespace Yay_Currency\Engine\Register;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Engine\Register\ScriptName;

/**
 * Register Facade.
 *
 * @method static RegisterFacade get_instance()
 */
class RegisterFacade {
	use SingletonTrait;

	/** Hooks Initialization */
	protected function __construct() {
		add_filter( 'script_loader_tag', array( $this, 'add_entry_as_module' ), 10, 3 );
		add_action( 'init', array( $this, 'register_all_assets' ) );

		$is_prod = ! defined( 'YAY_CURRENCY_IS_DEVELOPMENT' ) || YAY_CURRENCY_IS_DEVELOPMENT !== true;
		if ( $is_prod && class_exists( '\Yay_Currency\Engine\Register\RegisterProd' ) ) {
			\Yay_Currency\Engine\Register\RegisterProd::get_instance();
		} elseif ( ! $is_prod && class_exists( '\Yay_Currency\Engine\Register\RegisterDev' ) ) {
			\Yay_Currency\Engine\Register\RegisterDev::get_instance();
		}
	}

	public function add_entry_as_module( $tag, $handle ) {
		if ( strpos( $handle, ScriptName::MODULE_PREFIX ) !== false ) {
			if ( strpos( $tag, 'type="' ) !== false ) {
				return preg_replace( '/\stype="\S+\s/', ' type="module" ', $tag, 1 );
			} else {
				return str_replace( ' src=', ' type="module" src=', $tag );
			}
		}
		return $tag;
	}

	public function register_all_assets() {
		wp_register_style(
			ScriptName::STYLE_SETTINGS,
			YAY_CURRENCY_PLUGIN_URL . 'assets/dist/style.css',
			array(
				'woocommerce_admin_styles',
				'wp-components',
			),
			YAY_CURRENCY_VERSION
		);
	}
}
