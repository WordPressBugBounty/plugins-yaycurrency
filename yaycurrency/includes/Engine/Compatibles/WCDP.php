<?php

namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class WCDP {
	use SingletonTrait;

	private $apply_currency = null;

	public function __construct() {
		if ( ! class_exists( 'WCDP' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();
		add_filter( 'wcdp_suggestion', array( $this, 'wcdp_suggestion_modify' ), 10, 2 );
	}

	public function wcdp_suggestion_modify( $suggestion, $product ) {
		if ( ! $suggestion ) {
			return $suggestion;
		}

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $suggestion;
		}

		return YayCurrencyHelper::calculate_price_by_currency( $suggestion, false, $this->apply_currency );
	}
}
