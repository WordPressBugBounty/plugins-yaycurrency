<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use LearnPress\Models\CourseModel;

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

			// Archive
			add_filter( 'learnPress/course/price', array( $this, 'archiveCoursePrice' ), 10, 2 );
			add_filter( 'learnPress/course/regular-price', array( $this, 'archiveCourseRegularPrice' ), 10, 2 );

			add_filter( 'learn-press/course/regular-price-html', array( $this, 'archive_course_regular_price_html' ), 10, 2 );
			add_filter( 'learn_press_course_price_html', array( $this, 'archive_course_price_html' ), 10, 3 );

		}
	}

	public function learn_press_currency_symbol( $currency_symbol, $currency ) {
		if ( isset( $this->apply_currency['symbol'] ) && ! is_admin() ) {
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
		if ( apply_filters( 'yay_currency_learn_press_default_course_price', false ) ) {
			return $price;
		}
		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		return $price;
	}

	public function custom_course_regular_price( $price, $course_id ) {
		if ( empty( $price ) || ! is_numeric( $price ) ) {
			return $price;
		}
		$course = CourseModel::find( $course_id, true );
		if ( $course && ! $course->get_sale_price() ) {
			return $price;
		}

		if ( apply_filters( 'yay_currency_learn_press_default_course_price', false ) ) {
			return $price;
		}

		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );

		return $price;
	}

	public function archiveCoursePrice( $price, $course_id ) {
		if ( empty( $price ) || ! is_numeric( $price ) ) {
			return $price;
		}
		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		return $price;
	}

	public function archiveCourseRegularPrice( $price, $course_id ) {
		if ( empty( $price ) || ! is_numeric( $price ) ) {
			return $price;
		}
		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		return $price;
	}

	public function archive_course_regular_price_html( $price, $course ) {
		if ( isset( $GLOBALS['wp']->query_vars ) && isset( $GLOBALS['wp']->query_vars['lp_course'] ) ) {
			return $price;
		}
		if ( self::archive_course_rest_route() ) {
			$regular_price = $course->get_regular_price();
			$regular_price = YayCurrencyHelper::calculate_price_by_currency( $regular_price, false, $this->apply_currency );
			$price         = YayCurrencyHelper::format_price( $regular_price );
		}
		return $price;
	}

	public function archive_course_price_html( $price, $has_sale_price, $course_id ) {
		if ( isset( $GLOBALS['wp']->query_vars ) && isset( $GLOBALS['wp']->query_vars['lp_course'] ) ) {
			return $price;
		}
		if ( self::archive_course_rest_route() ) {
			$course = CourseModel::find( $course_id, true );
			if ( $course ) {
				$price_html = '';
				if ( $has_sale_price ) {
					$price_html .= sprintf( '<span class="origin-price">%s</span>', self::archive_course_regular_price_html( $price, $course ) );
				}
				$regular_price = $course->get_price();
				$price         = $price_html . YayCurrencyHelper::format_price( $regular_price );
			}
		}
		return $price;
	}
}
