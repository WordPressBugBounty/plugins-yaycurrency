<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;

use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\Helper;
use LP_Settings_Courses;

defined( 'ABSPATH' ) || exit;

// link plugin : https://wordpress.org/plugins/learnpress/

class LearnPress {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! class_exists( '\LP_Admin_Assets' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'learn-press/course/price', array( $this, 'custom_course_price' ), 10, 2 );
		add_filter( 'learn-press/course/regular-price', array( $this, 'custom_course_regular_price' ), 10, 2 );
		add_filter( 'learn_press_currency_symbol', array( $this, 'learn_press_currency_symbol' ), 10, 2 );

		// Archive
		add_filter( 'learnPress/course/price', array( $this, 'archive_course_price' ), 10, 2 );
		add_filter( 'learnPress/course/regular-price', array( $this, 'archive_course_regular_price' ), 10, 2 );

		add_filter( 'learn-press/course/regular-price-html', array( $this, 'archive_course_regular_price_html' ), 10, 2 );
		add_filter( 'learn_press_course_price_html', array( $this, 'archive_course_price_html' ), 10, 3 );

		// Cart Item Subtotal
		add_filter( 'learn-press/cart/item-subtotal', array( $this, 'cart_course_item_subtotal' ), 10, 4 );

		// LearnPress - WooCommerce Payment Methods Integration
		if ( defined( 'LP_ADDON_WOO_PAYMENT_VER' ) ) {
			add_filter( 'learn-press/woo-course/price', array( $this, 'get_woo_course_price_custom' ), 10, 2 );
			add_filter( 'learn-press/woo-course/regular-price', array( $this, 'get_woo_course_price_custom' ), 10, 2 );
			add_filter( 'learn-press/woo-course/sale-price', array( $this, 'get_woo_course_price_custom' ), 10, 2 );
		}

	}

	protected function is_admin_dashboard() {
		return is_admin() && ! wp_doing_ajax();
	}

	// Get Course price from original currency
	protected function get_course_price( $course_id, $type = 'price' ) {
		$prices = array(
			'regular' => get_post_meta( $course_id, '_lp_regular_price', true ),
			'sale'    => get_post_meta( $course_id, '_lp_sale_price', true ),
		);

		if ( 'regular' === $type || 'sale' === $type ) {
			return ! empty( $prices[ $type ] ) && $prices[ $type ] > 0 ? $prices[ $type ] : 0;
		}

		// Return sale price if available, otherwise fallback to regular price
		return ( ! empty( $prices['sale'] ) && $prices['sale'] > 0 ) ? $prices['sale'] : ( ! empty( $prices['regular'] ) && $prices['regular'] > 0 ? $prices['regular'] : 0 );
	}

	public function custom_course_price( $price, $course_id ) {

		if ( self::is_admin_dashboard() || empty( $price ) || ! is_numeric( $price ) ) {
			return $price;
		}

		$price = self::get_course_price( $course_id );
		$price = apply_filters( 'yay_currency_convert_price', $price, $this->apply_currency );

		return $price;
	}

	public function custom_course_regular_price( $regular_price, $course_id ) {

		if ( self::is_admin_dashboard() || empty( $regular_price ) || ! is_numeric( $regular_price ) ) {
			return $regular_price;
		}

		$regular_price = self::get_course_price( $course_id, 'regular' );
		$regular_price = apply_filters( 'yay_currency_convert_price', $regular_price, $this->apply_currency );

		return $regular_price;

	}

	public function learn_press_currency_symbol( $currency_symbol, $currency ) {

		if ( self::is_admin_dashboard() ) {
			return $currency_symbol;
		}

		if ( isset( $this->apply_currency['symbol'] ) && ! is_admin() ) {
			$currency_symbol = $this->apply_currency['symbol'];
		}

		return $currency_symbol;
	}

	// Archive Course page

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

	protected function is_archive_course() {
		$archive_course_ajax = LP_Settings_Courses::is_ajax_load_courses() && isset( $_REQUEST['lp-load-ajax'] ) && 'load_content_via_ajax' === $_REQUEST['lp-load-ajax'];
		return is_post_type_archive( 'lp_course' ) || $archive_course_ajax;
	}

	public function archive_course_price( $price, $course_id ) {
		if ( empty( $price ) || ! is_numeric( $price ) ) {
			return $price;
		}

		if ( self::is_admin_dashboard() ) {
			return $price;
		}
		if ( self::is_archive_course() ) {
			$price = apply_filters( 'yay_currency_convert_price', $price, $this->apply_currency );
		}

		return $price;

	}

	public function archive_course_regular_price( $regular_price, $course_id ) {

		if ( empty( $regular_price ) || ! is_numeric( $regular_price ) ) {
			return $regular_price;
		}

		if ( self::is_admin_dashboard() ) {
			return $regular_price;
		}

		if ( self::is_archive_course() ) {
			$regular_price = apply_filters( 'yay_currency_convert_price', $regular_price, $this->apply_currency );
		}

		return $regular_price;
	}

	public function archive_course_regular_price_html( $price, $course ) {

		if ( self::is_admin_dashboard() ) {
			return $price;
		}

		if ( self::archive_course_rest_route() || self::is_archive_course() || self::is_single_course_page() ) {
			$course_id     = is_object( $course ) ? $course->get_id() : $course;
			$regular_price = self::get_course_price( $course_id, 'regular' );
			$regular_price = apply_filters( 'yay_currency_convert_price', $regular_price, $this->apply_currency );
			$price         = YayCurrencyHelper::format_price( $regular_price );
		}

		return $price;
	}

	private function is_single_course_page() {
		return isset( $GLOBALS['wp']->query_vars ) && isset( $GLOBALS['wp']->query_vars['lp_course'] );
	}

	public function archive_course_price_html( $price, $has_sale_price, $course_id ) {

		if ( self::is_admin_dashboard() ) {
			return $price;
		}

		if ( self::archive_course_rest_route() || self::is_archive_course() || self::is_single_course_page() ) {
			$course = learn_press_get_course( $course_id );
			if ( $course ) {
				$price_html = '';
				if ( $has_sale_price ) {
					$price_html .= sprintf( '<span class="origin-price">%s</span>', self::archive_course_regular_price_html( $price, $course ) );
				}

				$final_price = $course->get_price();

				$format_price = YayCurrencyHelper::format_price( $final_price );

				if ( self::is_single_course_page() ) {
					$format_price = '<span class="price">' . $format_price . '</span>';
				}

				$price = $price_html . $format_price;

			}
		}

		return $price;
	}

	public function cart_course_item_subtotal( $course_subtotal, $course, $quantity, $cart ) {
		if ( ! function_exists( 'learn_press_format_price' ) ) {
			return $course_subtotal;
		}
		$course_price    = apply_filters( 'yay_currency_convert_price', $course->get_price(), $this->apply_currency );
		$course_subtotal = $course_price * $quantity;
		$course_subtotal = learn_press_format_price( $course_subtotal, true );
		return $course_subtotal;
	}
	// LearnPress - WooCommerce Payment Methods Integration

	public function get_woo_course_price_custom( $price, $course ) {
		if ( apply_filters( 'YayCurrency/LearnPress/StoreCurrency/CoursePrice/IsDefault', false ) || YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $price;
		}
		$price = apply_filters( 'yay_currency_convert_price', $price, $this->apply_currency );
		return $price;
	}
}
