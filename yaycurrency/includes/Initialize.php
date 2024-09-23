<?php
namespace Yay_Currency;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Utils\SingletonTrait;

/**
 * Yay_Currency Plugin Initializer
 */
class Initialize {

	use SingletonTrait;

	/**
	 * The Constructor that load the engine classes
	 */
	protected function __construct() {
		// Engine
		Helper::get_instance_classes( array( '\Yay_Currency', 'Engine' ), Helper::engine_classes() );
		// BEPages
		Helper::get_instance_classes( array( '\Yay_Currency', 'Engine', 'BEPages' ), Helper::backend_classes() );
		// Appearance
		Helper::get_instance_classes( array( '\Yay_Currency', 'Engine', 'Appearance' ), Helper::appearance_classes() );
		// FEPages
		Helper::get_instance_classes( array( '\Yay_Currency', 'Engine', 'FEPages' ), Helper::frontend_classes() );
		// COMPATIBLES : THEMES, CACHES, PLUGINS
		Helper::get_instance_classes( array( '\Yay_Currency', 'Engine', 'Compatibles' ), Helper::compatible_classes() );

	}
}
