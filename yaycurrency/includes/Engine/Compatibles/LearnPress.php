<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// link plugin : https://thimpress.com/learnpress/

class LearnPress {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( class_exists( '\LP_Admin_Assets' ) ) {
			$this->apply_currency = YayCurrencyHelper::detect_current_currency();
			add_filter( 'learn-press/course/price', array( $this, 'custom_course_price' ), 10, 2 );
			add_filter( 'learn-press/course/regular-price', array( $this, 'custom_course_regular_price' ), 10, 2 );
			add_filter( 'learn_press_currency_symbol', array( $this, 'learn_press_currency_symbol' ), 10, 2 );
		}
	}

	public function learn_press_currency_symbol( $currency_symbol, $currency ) {
		if ( isset( $this->apply_currency['symbol'] ) ) {
			$currency_symbol = $this->apply_currency['symbol'];
		}
		return $currency_symbol;
	}

	protected function archive_course_rest_route() {

		if ( isset( $GLOBALS['wp']->query_vars ) && isset( $GLOBALS['wp']->query_vars['course-item'] ) ) {
			return true;
		}

		$rest_route = Helper::get_rest_route_via_rest_api();

		if ( $rest_route && str_contains( $rest_route, '/lp/v1/' ) ) {
			return true;
		}

		return false;
	}

	public function custom_course_price( $price, $course_id ) {
		if ( self::archive_course_rest_route() ) {
			return $price;
		}
		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		return $price;
	}

	public function custom_course_regular_price( $price, $course_id ) {

		if ( empty( $price ) || ! is_numeric( $price ) ) {
			return $price;
		}

		if ( self::archive_course_rest_route() ) {
			$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		}

		return $price;
	}
}
