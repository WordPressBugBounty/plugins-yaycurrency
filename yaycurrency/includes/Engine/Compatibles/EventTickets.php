<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin:

class EventTickets {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! class_exists( 'Tribe__Tickets__Main' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'tribe_format_amount', array( $this, 'tribe_format_amount' ), 10, 3 );

	}

	public function tribe_format_amount( $formatted, $amount, $currency ) {
		if ( doing_filter( 'tribe_currency_cost' ) ) {
			$amount    = YayCurrencyHelper::calculate_price_by_currency( $amount, false, $this->apply_currency );
			$formatted = YayCurrencyHelper::format_price_currency( $amount, $this->apply_currency );
		}

		return $formatted;
	}
}
