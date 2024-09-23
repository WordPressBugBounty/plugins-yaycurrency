<?php
namespace Yay_Currency\Engine\FEPages;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class Shortcodes {

	use SingletonTrait;

	protected function __construct() {

		//Dropdown Shortcode
		add_shortcode( 'yaycurrency-switcher', array( $this, 'currency_dropdown_shortcode' ) );

		//Menu Shortcode
		add_shortcode( 'yaycurrency-menu-item-switcher', array( $this, 'menu_item_switcher_shortcode' ) );

		// Convert Price HTML By Currency
		add_shortcode( 'yaycurrency-price-html', array( $this, 'yay_convert_price_html' ) );
		add_shortcode( 'yaycurrency-product-price-html', array( $this, 'generate_product_price_html' ) ); // Support for Single Product pages and Product Archive Loop.

		// Fee custom
		add_shortcode( 'yaycurrency-fee', array( $this, 'yay_currency_calculate_fee' ) );
		add_shortcode( 'yaycurrency-fee-default', array( $this, 'yay_currency_calculate_fee_default' ) );

		// Currency convertor
		add_shortcode( 'yaycurrency-converter', array( $this, 'yay_currency_converter' ) );

	}

	public function currency_dropdown_shortcode( $atts, $content = null ) {
		$atts = shortcode_atts(
			array(
				'switcher_size' => null,
				'show_flag'     => null,
				'show_name'     => null,
				'show_symbol'   => null,
				'show_code'     => null,
				'device'        => 'all',
			),
			$atts
		);
		ob_start();

		if ( YayCurrencyHelper::detect_allow_hide_dropdown_currencies() ) {
			return '';
		}
		$is_show_flag            = get_option( 'yay_currency_show_flag_in_switcher', 1 );
		$is_show_currency_name   = get_option( 'yay_currency_show_currency_name_in_switcher', 1 );
		$is_show_currency_symbol = get_option( 'yay_currency_show_currency_symbol_in_switcher', 1 );
		$is_show_currency_code   = get_option( 'yay_currency_show_currency_code_in_switcher', 1 );
		$switcher_size           = get_option( 'yay_currency_switcher_size', 'medium' );
		if ( ! empty( $atts['switcher_size'] ) || ! empty( $atts['show_flag'] ) || ! empty( $atts['show_name'] ) || ! empty( $atts['show_symbol'] ) || ! empty( ! $atts['show_code'] ) ) {
			switch ( $atts['device'] ) {
				case 'mobile':
					$device_allow = wp_is_mobile();
					break;
				case 'desktop':
					$device_allow = ! wp_is_mobile();
					break;
				default:
					$device_allow = true;
					break;
			}

			if ( ! $device_allow ) {
				$content = ob_get_clean();
				return $content;
			} else {
				$switcher_size           = ! $atts['switcher_size'] ? $switcher_size : ( ! in_array( $atts['switcher_size'], array( 'medium', 'small' ) ) ? 'medium' : $atts['switcher_size'] );
				$is_show_flag            = ! $atts['show_flag'] ? $is_show_flag : ( 'yes' === $atts['show_flag'] || '1' === $atts['show_flag'] ? 1 : 0 );
				$is_show_currency_name   = ! $atts['show_name'] ? $is_show_currency_name : ( 'yes' === $atts['show_name'] || '1' === $atts['show_name'] ? 1 : 0 );
				$is_show_currency_symbol = ! $atts['show_symbol'] ? $is_show_currency_symbol : ( 'yes' === $atts['show_symbol'] || '1' === $atts['show_symbol'] ? 1 : 0 );
				$is_show_currency_code   = ! $atts['show_code'] ? $is_show_currency_code : ( 'yes' === $atts['show_code'] || '1' === $atts['show_code'] ? 1 : 0 );
			}
		}
		require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/switcher/template.php';
		$content = ob_get_clean();
		return $content;

	}


	public function menu_item_switcher_shortcode( $content = null ) {
		if ( YayCurrencyHelper::detect_allow_hide_dropdown_currencies() ) {
			return '';
		}
		$is_show_flag            = get_option( 'yay_currency_show_flag_in_menu_item', 1 );
		$is_show_currency_name   = get_option( 'yay_currency_show_currency_name_in_menu_item', 1 );
		$is_show_currency_symbol = get_option( 'yay_currency_show_currency_symbol_in_menu_item', 1 );
		$is_show_currency_code   = get_option( 'yay_currency_show_currency_code_in_menu_item', 1 );
		$switcher_size           = get_option( 'yay_currency_menu_item_size', 'small' );

		ob_start();
		require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/switcher/template.php';
		$content = ob_get_clean();
		return $content;

	}

	public function yay_convert_price_html( $atts, $content = null ) {
		$atts = shortcode_atts(
			array(
				'price' => '',
			),
			$atts
		);

		ob_start();
		$price          = apply_filters( 'yaycurrency_get_price', $atts['price'] );
		$apply_currency = YayCurrencyHelper::detect_current_currency();
		$price_html     = YayCurrencyHelper::calculate_price_by_currency_html( $apply_currency, $price );
		$price_html     = apply_filters( 'yaycurrency_get_price_html', $price_html, $apply_currency );
		echo wp_kses_post( $price_html );
		return ob_get_clean();
	}

	public function generate_product_price_html( $atts, $content = null ) {

		$atts = shortcode_atts(
			array(
				'product_id' => '',
			),
			$atts
		);

		ob_start();
		if ( ! empty( $atts['product_id'] ) ) {
			$product = wc_get_product( $atts['product_id'] );
		} else {
			global $product, $post;
			if ( ! $product || ! is_object( $product ) ) {
				if ( $post && isset( $post->post_type ) && 'product' === $post->post_type ) {
					$product = wc_get_product( $post->ID );
				}
			}
		}
		$price_html = '';
		if ( $product && is_object( $product ) ) {
			$price_html = $product->get_price_html();
		}
		echo wp_kses_post( $price_html );
		return ob_get_clean();
	}

	public function yay_currency_calculate_fee( $atts ) {
		$atts = shortcode_atts(
			array(
				'percent' => '',
				'min_fee' => '',
				'max_fee' => '',
			),
			$atts,
			'yaycurrency-fee'
		);

		$apply_currency = YayCurrencyHelper::detect_current_currency();

		$atts['min_fee'] = YayCurrencyHelper::calculate_price_by_currency( $atts['min_fee'], true, $apply_currency );
		$atts['max_fee'] = YayCurrencyHelper::calculate_price_by_currency( $atts['max_fee'], true, $apply_currency );

		$cart_subtotal  = apply_filters( 'yay_currency_get_cart_subtotal', 0, $apply_currency );
		$calculated_fee = $this->get_fee_cost_by_shortcode( $cart_subtotal, $atts );

		return $calculated_fee;
	}

	public function yay_currency_calculate_fee_default( $atts ) {
		$atts = shortcode_atts(
			array(
				'percent' => '',
				'min_fee' => '',
				'max_fee' => '',
			),
			$atts,
			'yaycurrency-fee-default'
		);

		$cart_subtotal  = apply_filters( 'yay_currency_get_cart_subtotal_default', 0 );
		$calculated_fee = $this->get_fee_cost_by_shortcode( $cart_subtotal, $atts );

		return $calculated_fee;
	}

	public function get_fee_cost_by_shortcode( $cart_subtotal, $atts ) {
		$calculated_fee = 0;

		if ( $atts['percent'] ) {
			$calculated_fee = $cart_subtotal * ( floatval( $atts['percent'] ) / 100 );
		}

		if ( $atts['min_fee'] && $calculated_fee < $atts['min_fee'] ) {
			$calculated_fee = $atts['min_fee'];
		}

		if ( $atts['max_fee'] && $calculated_fee > $atts['max_fee'] ) {
			$calculated_fee = $atts['max_fee'];
		}

		return $calculated_fee;
	}

	public function yay_currency_converter( $atts, $content = null ) {
		$atts = shortcode_atts(
			array(
				'heading'      => '',
				'amount_text'  => __( 'Amount', 'yay-currency' ),
				'from_text'    => __( 'From', 'yay-currency' ),
				'to_text'      => __( 'To', 'yay-currency' ),
				'hide_heading' => '',
			),
			$atts
		);
		ob_start();
		require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/shortcodes/converter.php';
		$content = ob_get_clean();
		return $content;
	}
}
