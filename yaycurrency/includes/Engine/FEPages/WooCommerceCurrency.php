<?php

namespace Yay_Currency\Engine\FEPages;

use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Helpers\Helper;

use Yay_Currency\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;
class WooCommerceCurrency {
	use SingletonTrait;

	private $default_currency;
	private $converted_currency            = array();
	private $apply_currency                = null;
	private $currencies_data               = array();
	private $is_dis_checkout_diff_currency = false;

	public function __construct() {
		add_action( 'init', array( $this, 'yay_currency_init' ) );
	}

	public function yay_currency_init() {

		if ( YayCurrencyHelper::is_reload_permitted() ) {
			$this->default_currency   = Helper::default_currency_code();
			$this->converted_currency = YayCurrencyHelper::converted_currency();
			$this->apply_currency     = YayCurrencyHelper::get_apply_currency( $this->converted_currency );

			YayCurrencyHelper::set_cookies( $this->apply_currency );

			$this->currencies_data               = YayCurrencyHelper::get_current_and_fallback_currency( $this->apply_currency, $this->converted_currency );
			$this->is_dis_checkout_diff_currency = YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency );

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			$price_priority      = SupportHelper::get_filters_priority();
			$product_price_hooks = YayCurrencyHelper::get_woocommerce_product_price_hooks();

			foreach ( $product_price_hooks as $price_hook ) {
				add_filter( $price_hook, array( $this, 'custom_raw_price' ), $price_priority, 2 );
			}

			add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'custom_variation_price_hash' ), $price_priority, 1 );

			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'conditional_payment_gateways' ), 10, 1 );

			add_action( 'woocommerce_before_mini_cart', array( $this, 'custom_mini_cart_price' ), 10 );

			// Pass currency code into cart_contents ( Support for 3rd plugins: Abandoned Cart,...)
			add_filter( 'woocommerce_get_cart_contents', array( $this, 'custom_woocommerce_get_cart_contents' ), 10, 1 );

			if ( YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency ) ) {
				add_action( 'woocommerce_before_checkout_form', array( $this, 'add_notice_checkout_payment_methods' ), 1000 );
				add_filter( 'woocommerce_cart_product_subtotal', array( $this, 'custom_checkout_product_subtotal' ), 10, 4 );
				add_filter( 'woocommerce_cart_subtotal', array( $this, 'custom_checkout_order_subtotal' ), 10, 3 );
				add_filter( 'woocommerce_cart_totals_coupon_html', array( $this, 'custom_discount_coupon' ), 10, 3 );
				add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'custom_shipping_fee' ), 10, 2 );
				add_filter( 'woocommerce_cart_totals_fee_html', array( $this, 'custom_cart_totals_fee_html' ), 10, 2 );
				add_filter( 'woocommerce_cart_tax_totals', array( $this, 'custom_total_tax' ), 10, 2 );
				add_filter( 'woocommerce_cart_totals_taxes_total_html', array( $this, 'custom_cart_totals_taxes' ), 10, 1 );
				add_filter( 'woocommerce_cart_total', array( $this, 'custom_checkout_order_total' ) );
			} else {
				add_action( 'woocommerce_checkout_create_order', array( $this, 'custom_checkout_create_order' ), PHP_INT_MAX, 2 );
			}

			// Filter to Coupon Min/Max
			add_filter( 'woocommerce_coupon_get_amount', array( $this, 'change_coupon_amount' ), 10, 2 );
			add_filter( 'woocommerce_coupon_get_minimum_amount', array( $this, 'change_coupon_min_max_amount' ), 10, 2 );
			add_filter( 'woocommerce_coupon_get_maximum_amount', array( $this, 'change_coupon_min_max_amount' ), 10, 2 );

			// Custom price fees
			$fee_priority = SupportHelper::get_fee_priority();
			add_action( 'woocommerce_cart_calculate_fees', array( $this, 'recalculate_cart_fees' ), $fee_priority, 1 );

			// Shipping Methods
			add_filter( 'woocommerce_package_rates', array( $this, 'change_shipping_cost' ), 10, 2 );

			// Free shipping with minimum amount
			add_filter( 'woocommerce_shipping_free_shipping_instance_option', array( $this, 'custom_free_shipping_min_amount' ), 20, 3 );
			add_filter( 'woocommerce_shipping_free_shipping_option', array( $this, 'custom_free_shipping_min_amount' ), 20, 3 );

			// Order Details in My Account
			add_filter( 'woocommerce_order_formatted_line_subtotal', array( $this, 'change_format_order_line_subtotal' ), 10, 3 );// frontend
			add_filter( 'woocommerce_get_order_item_totals', array( $this, 'change_format_order_item_totals' ), 10, 3 );// frontend
			add_filter( 'woocommerce_order_subtotal_to_display', array( $this, 'get_formatted_order_subtotal' ), 10, 3 ); // frontend
			add_filter( 'woocommerce_order_shipping_to_display', array( $this, 'get_formatted_order_shipping' ), 10, 3 );
			add_filter( 'woocommerce_order_discount_to_display', array( $this, 'get_formatted_order_discount' ), 10, 2 );
			add_filter( 'woocommerce_get_formatted_order_total', array( $this, 'get_formatted_order_total' ), 10, 4 );

			// Price Format Currency
			$format_priority = SupportHelper::get_format_filters_priority();
			add_filter( 'woocommerce_currency', array( $this, 'change_woocommerce_currency' ), $format_priority, 1 );
			add_filter( 'woocommerce_currency_symbol', array( $this, 'change_existing_currency_symbol' ), $format_priority, 2 );
			add_filter( 'pre_option_woocommerce_currency_pos', array( $this, 'change_currency_position' ), $format_priority );
			add_filter( 'wc_get_price_thousand_separator', array( $this, 'change_thousand_separator' ), $format_priority );
			add_filter( 'wc_get_price_decimal_separator', array( $this, 'change_decimal_separator' ), $format_priority );
			add_filter( 'wc_get_price_decimals', array( $this, 'change_number_decimals' ), $format_priority );
		}

		add_filter( 'wc_price_args', array( $this, 'custom_wc_price_args' ), 99, 1 );

	}

	public function enqueue_scripts() {

		$suffix = defined( 'YAY_CURRENCY_SCRIPT_DEBUG' ) ? '' : '.min';

		$localize_args = array(
			'admin_url'               => admin_url( 'admin.php?page=wc-settings' ),
			'ajaxurl'                 => admin_url( 'admin-ajax.php' ),
			'nonce'                   => wp_create_nonce( 'yay-currency-nonce' ),
			'isShowOnMenu'            => get_option( 'yay_currency_show_menu', 0 ),
			'shortCode'               => do_shortcode( '[yaycurrency-menu-item-switcher]' ),
			'isPolylangCompatible'    => get_option( 'yay_currency_polylang_compatible', 0 ),
			'isDisplayFlagInSwitcher' => get_option( 'yay_currency_show_flag_in_switcher', 1 ),
			'yayCurrencyPluginURL'    => YAY_CURRENCY_PLUGIN_URL,
			'converted_currency'      => $this->converted_currency,
			'cart_page'               => function_exists( 'is_cart' ) ? is_cart() : '',
			'default_currency_code'   => $this->default_currency,
			'hide_dropdown_switcher'  => YayCurrencyHelper::detect_allow_hide_dropdown_currencies(),
			'cookie_name'             => YayCurrencyHelper::get_cookie_name(),
			'cookie_switcher_name'    => YayCurrencyHelper::get_cookie_name( 'switcher' ),
			'current_theme'           => Helper::get_current_theme(),
		);

		if ( $this->is_dis_checkout_diff_currency ) {
			$localize_args['checkout_page'] = function_exists( 'is_checkout' ) ? is_checkout() : '';
		}

		if ( YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency ) ) {
			$localize_args['cart_subtotal_default'] = apply_filters( 'yay_currency_get_cart_subtotal_default', 0 );
			$localize_args['checkout_notice_html']  = apply_filters( 'yay_currency_notice_checkout_payment_methods', '', $this->currencies_data, Helper::get_current_theme() );
		}

		if ( Helper::use_yay_currency_params() ) {
			$localize_args['yay_currency_use_params'] = 'yes';
			$param_name                               = apply_filters( 'yay_currency_param_name', 'yay-currency' );
			if ( isset( $_REQUEST[ $param_name ] ) ) {
				$localize_args['yay_currency_param__name'] = $param_name;
			}
		}

		wp_enqueue_style(
			'yay-currency-frontend-style',
			YAY_CURRENCY_PLUGIN_URL . 'src/styles.css',
			array(),
			YAY_CURRENCY_VERSION
		);

		wp_enqueue_script( 'yay-currency-callback', YAY_CURRENCY_PLUGIN_URL . 'src/callback' . $suffix . '.js', array( 'jquery' ), YAY_CURRENCY_VERSION, true );

		wp_enqueue_script( 'yay-currency-frontend-script', YAY_CURRENCY_PLUGIN_URL . 'src/script' . $suffix . '.js', array( 'jquery' ), YAY_CURRENCY_VERSION, true );

		wp_localize_script(
			'yay-currency-frontend-script',
			'yayCurrency',
			apply_filters( 'yay_currency_localize_args', $localize_args )
		);

		do_action( 'yay_currency_enqueue_scripts' );
	}

	public function custom_wc_price_args( $args ) {
		$args = apply_filters( 'yay_currency_get_price_format', $args );
		return $args;
	}

	public function is_original_default_currency() {
		$flag = apply_filters( 'yay_currency_is_original_default_currency', false, $this->apply_currency );
		return $flag;
	}

	public function custom_raw_price( $price, $product ) {

		$this->apply_currency = YayCurrencyHelper::get_current_currency( $this->apply_currency );

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) || $this->is_original_default_currency() ) {
			$price = apply_filters( 'yay_currency_get_price_default_in_checkout_page', $price, $product );
			return $price;
		}

		if ( SupportHelper::detect_original_product_price( false, $price, $product ) ) {
			return $price;
		}

		$conditions_3rd_plugin = apply_filters( 'yay_currency_3rd_plugins_conditions', false, $product, $this->apply_currency );

		// Fix for manual renewal subscription product and still keep old code works well
		if ( is_checkout() || is_cart() || wp_doing_ajax() || $conditions_3rd_plugin ) {

			$price_with_conditions = apply_filters( 'yay_currency_get_price_with_conditions', $price, $product, $this->apply_currency );
			if ( $price_with_conditions ) {
				return $price_with_conditions;
			}

			$price_exist_class_plugins = apply_filters( 'yay_currency_get_price_except_class_plugins', $price, $product, $this->apply_currency );
			if ( $price_exist_class_plugins ) {
				return $price_exist_class_plugins;
			}
		}

		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		$price = apply_filters( 'yay_currency_get_price_by_currency', $price, $product, $this->apply_currency );
		return $price;

	}

	public function custom_variation_price_hash( $price_hash ) {
		$cookie_name = YayCurrencyHelper::get_cookie_name();
		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			$price_hash[] = (int) sanitize_key( $_COOKIE[ $cookie_name ] );
		}
		return $price_hash;
	}

	public function conditional_payment_gateways( $available_gateways ) {

		if ( ! $this->apply_currency ) {
			return $available_gateways;
		}

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			$available_gateways = YayCurrencyHelper::filter_payment_methods_by_currency( $this->currencies_data['fallback_currency'], $available_gateways );
			return $available_gateways;
		}

		$available_gateways = YayCurrencyHelper::filter_payment_methods_by_currency( $this->apply_currency, $available_gateways );
		$available_gateways = apply_filters( 'yay_currency_available_gateways', $available_gateways, $this->apply_currency );
		return $available_gateways;

	}

	public function custom_mini_cart_price() {

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) || is_cart() || is_checkout() ) {
			return false;
		}
		WC()->cart->calculate_totals();

	}

	public function custom_woocommerce_get_cart_contents( $cart_contents ) {

		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			$cart_contents[ $cart_item_key ]['yay_currency_code'] = $this->apply_currency['currency'];
			$cart_contents[ $cart_item_key ]['yay_currency_rate'] = YayCurrencyHelper::get_rate_fee( $this->apply_currency );
			do_action( 'yay_currency_set_cart_contents', $cart_contents, $cart_item_key, $cart_item, $this->apply_currency );

		}

		return $cart_contents;

	}

	public function custom_checkout_create_order( $order, $data ) {

		if ( $this->default_currency !== $this->apply_currency['currency'] ) {

			$rate_fee = YayCurrencyHelper::get_rate_fee( $this->apply_currency );

			if ( Helper::check_custom_orders_table_usage_enabled() ) {
				$order->delete_meta_data( '_yay_currency_order_synced' );
				$order->update_meta_data( 'yay_currency_order_rate', $rate_fee );
				$order->save();
			} else {
				$order_id = $order->get_id();
				delete_post_meta( $order_id, '_yay_currency_order_synced' );
				update_post_meta( $order_id, 'yay_currency_order_rate', $rate_fee );
			}
		}

	}

	public function add_notice_checkout_payment_methods() {

		$notice_payment_methods = apply_filters( 'yay_currency_checkout_notice_payment_methods', true, $this->apply_currency );

		if ( YayCurrencyHelper::is_current_fallback_currency( $this->currencies_data ) || ! $notice_payment_methods ) {
			return;
		}

		do_action( 'yay_currency_before_notice_checkout_payment_methods' );
		echo wp_kses_post( apply_filters( 'yay_currency_notice_checkout_payment_methods', '', $this->currencies_data, Helper::get_current_theme() ) );
		do_action( 'yay_currency_after_notice_checkout_payment_methods' );

	}

	public function custom_checkout_product_subtotal( $product_subtotal, $product, $quantity, $cart ) {
		if ( is_checkout() ) {

			if ( YayCurrencyHelper::is_current_fallback_currency( $this->currencies_data ) ) {
				return $product_subtotal;
			}

			$product_price             = $product->get_price();
			$original_product_subtotal = YayCurrencyHelper::calculate_price_by_currency_html( $this->currencies_data['fallback_currency'], $product_price, $quantity );
			$converted_approximately   = apply_filters( 'yay_currency_checkout_converted_approximately', true, $this->apply_currency );
			if ( ! $converted_approximately ) {
				return $original_product_subtotal;
			}

			$converted_product_subtotal = YayCurrencyHelper::calculate_price_by_currency_html( $this->currencies_data['current_currency'], $product_price, $quantity );

			if ( class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscriptions_Product' ) && \WC_Subscriptions_Product::is_subscription( $product ) ) {
				$original_price = (float) $product->get_price( 'edit' );
				$original_price = apply_filters( 'yay_currency_checkout_get_original_price_wc_subscriptions', $original_price, $product );
				$sign_up_price  = SupportHelper::get_price_sign_up_fee_by_wc_subscriptions( $this->apply_currency, $product );

				if ( $sign_up_price ) {
					$sign_up_price = (float) $product->get_price();
					if ( $sign_up_price && $original_price !== $sign_up_price ) {
						return $converted_product_subtotal;
					}
				}
			}

			$product_price_3rd = apply_filters( 'yay_currency_get_product_price_by_3rd_plugin', false, $product, $this->apply_currency );

			if ( $product_price_3rd ) {
				$converted_product_subtotal = YayCurrencyHelper::format_price( $product_price_3rd * $quantity );
			}

			//  Display approximate price only on the checkout page
			if ( SupportHelper::display_approximate_price_on_checkout() ) {
				return $converted_product_subtotal;
			}

			$converted_product_subtotal_html = YayCurrencyHelper::converted_approximately_html( $converted_product_subtotal );
			$product_subtotal                = $original_product_subtotal . $converted_product_subtotal_html;

		}
		return $product_subtotal;
	}

	public function custom_checkout_order_subtotal( $cart_subtotal ) {
		if ( is_checkout() ) {

			if ( YayCurrencyHelper::is_current_fallback_currency( $this->currencies_data ) ) {
				return $cart_subtotal;
			}

			$subtotal_price          = apply_filters( 'yay_currency_checkout_get_subtotal_price', (float) WC()->cart->get_displayed_subtotal(), $this->apply_currency );
			$original_subtotal       = YayCurrencyHelper::calculate_price_by_currency_html( $this->currencies_data['fallback_currency'], $subtotal_price );
			$converted_approximately = apply_filters( 'yay_currency_checkout_converted_approximately', true, $this->apply_currency );
			if ( ! $converted_approximately ) {
				return $original_subtotal;
			}

			$converted_subtotal = YayCurrencyHelper::calculate_price_by_currency_html( $this->currencies_data['current_currency'], $subtotal_price );

			if ( YayCurrencyHelper::enable_rounding_currency( $this->apply_currency ) ) {
				$converted_subtotal = apply_filters( 'yay_currency_checkout_converted_cart_subtotal', $converted_subtotal, $this->apply_currency );
			}

			//  Display approximate price only on the checkout page
			if ( SupportHelper::display_approximate_price_on_checkout() ) {
				return $converted_subtotal;
			}

			$converted_product_subtotal_html = YayCurrencyHelper::converted_approximately_html( $converted_subtotal );
			$cart_subtotal                   = $original_subtotal . $converted_product_subtotal_html;

		}
		return $cart_subtotal;
	}

	public function custom_discount_coupon( $coupon_html, $coupon, $discount_amount_html ) {
		if ( is_checkout() ) {

			if ( YayCurrencyHelper::is_current_fallback_currency( $this->currencies_data ) ) {
				return $coupon_html;
			}

			$coupon_html = apply_filters( 'yay_currency_checkout_converted_cart_coupon_totals_html', $coupon_html, $coupon, $this->currencies_data['fallback_currency'], $this->apply_currency );
		}
		return $coupon_html;
	}

	public function custom_shipping_fee( $label, $method ) {
		if ( is_checkout() ) {

			if ( 'free_shipping' === $method->method_id ) {
				return $label;
			}

			$shipping_fee = (float) $method->cost;

			if ( YayCurrencyHelper::is_current_fallback_currency( $this->currencies_data ) ) {
				return $label;
			}

			$label = apply_filters( 'yay_currency_checkout_converted_shipping_method_full_label', $label, $method, $shipping_fee, $this->currencies_data['fallback_currency'], $this->apply_currency );
		}
		return $label;
	}

	public function custom_cart_totals_fee_html( $cart_totals_fee_html, $fee ) {
		if ( is_checkout() ) {
			$converted_approximately = apply_filters( 'yay_currency_checkout_converted_approximately', true, $this->apply_currency );

			if ( ! $converted_approximately || YayCurrencyHelper::is_current_fallback_currency( $this->currencies_data ) ) {
				return $cart_totals_fee_html;
			}

			$fee_amount              = $fee->amount;
			$fee_amount_html         = YayCurrencyHelper::calculate_price_by_currency_html( $this->currencies_data['fallback_currency'], $fee_amount );
			$convert_fee_amount      = YayCurrencyHelper::calculate_price_by_currency( $fee_amount, true, $this->currencies_data['current_currency'] );
			$convert_fee_amount_html = YayCurrencyHelper::format_price( $convert_fee_amount );
			//  Display approximate price only on the checkout page
			if ( SupportHelper::display_approximate_price_on_checkout() ) {
				return $convert_fee_amount_html;
			}
			$cart_totals_fee_html = $fee_amount_html . YayCurrencyHelper::converted_approximately_html( $convert_fee_amount_html );
		}
		return $cart_totals_fee_html;
	}

	public function custom_total_tax( $tax_display ) {
		if ( count( $tax_display ) > 0 && is_checkout() ) {

			if ( YayCurrencyHelper::is_current_fallback_currency( $this->currencies_data ) ) {
				return $tax_display;
			}
			$converted_approximately = apply_filters( 'yay_currency_checkout_converted_approximately', true, $this->apply_currency );
			foreach ( $tax_display as $tax_info ) {
				$tax_info->formatted_amount = YayCurrencyHelper::calculate_price_by_currency_html( $this->currencies_data['fallback_currency'], $tax_info->amount );

				if ( $converted_approximately ) {
					$converted_tax_amount           = YayCurrencyHelper::calculate_price_by_currency( $tax_info->amount, true, $this->apply_currency );
					$formatted_converted_tax_amount = YayCurrencyHelper::format_price( $converted_tax_amount );
					if ( YayCurrencyHelper::enable_rounding_currency( $this->apply_currency ) ) {
						$formatted_converted_tax_amount = apply_filters( 'yay_currency_checkout_converted_tax_amount', $formatted_converted_tax_amount, $tax_info, $this->apply_currency );
					}
					//  Display approximate price only on the checkout page
					if ( SupportHelper::display_approximate_price_on_checkout() ) {
						$tax_info->formatted_amount = $formatted_converted_tax_amount;
					} else {
						$formatted_converted_tax_amount_html = YayCurrencyHelper::converted_approximately_html( $formatted_converted_tax_amount );
						$tax_info->formatted_amount         .= $formatted_converted_tax_amount_html;
					}
				}
			}
		}
		return $tax_display;
	}

	public function custom_cart_totals_taxes( $taxes_total_html ) {
		if ( is_checkout() ) {
			if ( YayCurrencyHelper::is_current_fallback_currency( $this->currencies_data ) ) {
				return $taxes_total_html;
			}
			$converted_approximately = apply_filters( 'yay_currency_checkout_converted_approximately', true, $this->apply_currency );
			$taxes_total             = WC()->cart->get_taxes_total();
			$taxes_total_html        = YayCurrencyHelper::calculate_price_by_currency_html( $this->currencies_data['fallback_currency'], $taxes_total );
			$taxes_total_html        = apply_filters( 'yay_currency_get_taxes_total_html', $taxes_total_html, $taxes_total, $this->currencies_data['fallback_currency'] );
			if ( ! $converted_approximately ) {
				return $taxes_total_html;
			}
			$converted_taxes_total_html = YayCurrencyHelper::calculate_price_by_currency_html( $this->apply_currency, $taxes_total );
			if ( SupportHelper::display_approximate_price_on_checkout() ) {
				return $converted_taxes_total_html;
			}
			$converted_taxes_total_html = YayCurrencyHelper::converted_approximately_html( $converted_taxes_total_html, true );
			$taxes_total_html           = $taxes_total_html . $converted_taxes_total_html;
		}
		return $taxes_total_html;
	}

	public function custom_checkout_order_total( $cart_total ) {
		if ( is_checkout() ) {

			if ( YayCurrencyHelper::is_current_fallback_currency( $this->currencies_data ) ) {
				return $cart_total;
			}

			$total_price             = apply_filters( 'yay_currency_checkout_get_total_price', (float) WC()->cart->total );
			$original_total          = YayCurrencyHelper::calculate_price_by_currency_html( $this->currencies_data['fallback_currency'], $total_price );
			$converted_approximately = apply_filters( 'yay_currency_checkout_converted_approximately', true, $this->apply_currency );
			if ( ! $converted_approximately ) {
				return $original_total;
			}
			$converted_total = YayCurrencyHelper::calculate_price_by_currency_html( $this->apply_currency, $total_price );

			if ( YayCurrencyHelper::enable_rounding_currency( $this->apply_currency ) ) {
				$converted_total = apply_filters( 'yay_currency_checkout_converted_cart_total', $converted_total, $total_price, $this->apply_currency );
			}
			//  Display approximate price only on the checkout page
			if ( SupportHelper::display_approximate_price_on_checkout() ) {
				return $converted_total;
			}
			$converted_total_html = YayCurrencyHelper::converted_approximately_html( $converted_total );
			$cart_total           = $original_total . $converted_total_html;

		}
		return $cart_total;
	}

	// Coupon
	public function change_coupon_amount( $price, $coupon ) {
		$coupon_types = apply_filters( 'yay_currency_coupon_types', array( 'percent' ), $coupon );
		// Check coupon type is percent return default price
		if ( $coupon->is_type( $coupon_types ) || empty( $price ) || ! $price ) {
			return $price;
		}

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) || $this->is_original_default_currency() ) {
			return apply_filters( 'yay_currency_get_amount_coupon_price_fallback_currency', $price, $coupon, $this->currencies_data );
		}

		// Coupon type != 'percent' calculate price
		$converted_coupon_price = YayCurrencyHelper::calculate_price_by_currency( $price, true, $this->apply_currency );
		$converted_coupon_price = apply_filters( 'yay_currency_converted_coupon_price', $converted_coupon_price, $coupon, $price, $this->apply_currency );
		return $converted_coupon_price;
	}

	public function change_coupon_min_max_amount( $price, $coupon ) {

		if ( empty( $price ) || ! $price ) {
			return $price;
		}

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) || $this->is_original_default_currency() ) {
			return apply_filters( 'yay_currency_get_min_max_amount_coupon_price_fallback_currency', $price, $coupon, $this->currencies_data );
		}

		$converted_coupon_price = YayCurrencyHelper::calculate_price_by_currency( $price, true, $this->apply_currency );
		$converted_coupon_price = apply_filters( 'yay_currency_coupon_get_amount', $converted_coupon_price, $coupon, $this->apply_currency );

		return $converted_coupon_price;

	}

	public function recalculate_cart_fees( $cart ) {
		if ( ! apply_filters( 'yay_currency_is_cart_fees_original', true, $this->apply_currency ) ) {
			return;
		}

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) || $this->is_original_default_currency() ) {
			return;
		}

		foreach ( $cart->get_fees() as $fee ) {
			if ( ! isset( $fee->yay_currency_fee_converted ) || ! $fee->yay_currency_fee_converted ) {
				$amount                          = YayCurrencyHelper::calculate_price_by_currency( $fee->amount, true, $this->apply_currency );
				$amount                          = apply_filters( 'yay_currency_get_fee_amount_after_calculate', $amount, $fee );
				$fee->amount                     = $amount;
				$fee->yay_currency_fee_converted = true;
			}
		}

	}

	public function custom_free_shipping_min_amount( $option, $key, $method ) {

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) || $this->is_original_default_currency() ) {
			return $option;
		}

		if ( 'min_amount' !== $key ) {
			return $option;
		}

		if ( ! $option || empty( $option ) ) {
			return $option;
		}

		$converted_min_amount = YayCurrencyHelper::calculate_price_by_currency( $option, true, $this->apply_currency );

		return $converted_min_amount;

	}

	// Shipping
	public function change_shipping_cost( $methods, $package ) {

		if ( apply_filters( 'yay_currency_is_original_shipping_cost', false, $this->apply_currency ) ) {
			return $methods;
		}

		if ( count( array_filter( $methods ) ) ) {
			$shipping_methods_args = array( 'alids', 'betrs_shipping', 'printful_shipping', 'easyship', 'printful_shipping_STANDARD', 'BookVAULT Shipping' );
			if ( ! function_exists( 'b2bking' ) ) {
				array_push( $shipping_methods_args, 'shipmondo' );
			}
			foreach ( $methods as $key => $method ) {
				$method_id = $method->method_id;
				if ( in_array( $method_id, $shipping_methods_args, true ) ) {
					continue;
				}
				if ( 'flat_rate' === $method->method_id ) {
					$shipping = new \WC_Shipping_Flat_Rate( $method->instance_id );
					// Calculate the costs.
					$rate = array(
						'id'      => $method->id,
						'label'   => $method->label,
						'cost'    => 0,
						'package' => $package,
					);

					$has_fee_costs = false; // True when a cost is set. False if all costs are blank strings.
					$cost          = $shipping->get_option( 'cost' );

					if ( ! empty( $cost ) && ! is_numeric( $cost ) ) {
						$has_fee_costs         = true;
						$package_contents_cost = $package['contents_cost'];
						$calculate_default     = false;
						if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) || $this->is_original_default_currency() ) {
							$package_contents_cost = apply_filters( 'yay_currency_get_cart_subtotal_default', 0 );
							$calculate_default     = true;
						}

						$rate['cost'] = SupportHelper::evaluate_cost(
							$cost,
							array(
								'qty'  => $shipping->get_package_item_qty( $package ),
								'cost' => $package_contents_cost,
							),
							$calculate_default
						);
						if ( is_numeric( $rate['cost'] ) && ! strpos( $cost, 'fee' ) ) {
							if ( ! YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) && ! $this->is_original_default_currency() ) {
								$rate['cost'] = YayCurrencyHelper::calculate_price_by_currency( $rate['cost'], true, $this->apply_currency );
							}
						}
					}

					$shipping_classes = WC()->shipping->get_shipping_classes();

					if ( ! empty( $shipping_classes ) ) {
						$product_shipping_classes = $shipping->find_shipping_classes( $package );
						$shipping_classes_cost    = 0;

						foreach ( $product_shipping_classes as $shipping_class => $products ) {
							$shipping_class_term = get_term_by( 'slug', $shipping_class, 'product_shipping_class' );
							$class_cost_string   = $shipping_class_term && $shipping_class_term->term_id ? $shipping->get_option( 'class_cost_' . $shipping_class_term->term_id, $shipping->get_option( 'class_cost_' . $shipping_class, '' ) ) : $shipping->get_option( 'no_class_cost', '' );

							if ( '' === $class_cost_string ) {
								continue;
							}
							if ( ! empty( $class_cost_string ) && ! is_numeric( $class_cost_string ) ) {
								$has_fee_costs = true;
								$class_cost    = SupportHelper::evaluate_cost(
									$class_cost_string,
									array(
										'qty'  => array_sum( wp_list_pluck( $products, 'quantity' ) ),
										'cost' => array_sum( wp_list_pluck( $products, 'line_total' ) ),
									)
								);
								if ( ! empty( $cost ) && is_numeric( $cost ) ) {
									$class_cost += $cost;
								}
							} else {
								if ( ! empty( $cost ) && is_numeric( $cost ) ) {
									$cost += $class_cost_string;
								}
								$class_cost = $class_cost_string;
							}

							if ( is_numeric( $class_cost ) && ! strpos( $class_cost_string, 'fee' ) ) {
								if ( ! YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) && ! $this->is_original_default_currency() ) {
									$class_cost = YayCurrencyHelper::calculate_price_by_currency( $class_cost, true, $this->apply_currency );
								}
							}

							if ( 'class' === $shipping->type ) {
								$rate['cost'] += $class_cost;
							} else {
								$shipping_classes_cost = $class_cost > $shipping_classes_cost ? $class_cost : $shipping_classes_cost;
							}
						}

						if ( 'order' === $shipping->type && $shipping_classes_cost ) {
							$rate['cost'] += $shipping_classes_cost;
						}
					}

					if ( $has_fee_costs ) {
						$method->set_cost( $rate['cost'] );
					} else {
						if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) || $this->is_original_default_currency() ) {
							$rate['cost'] = apply_filters( 'yay_currency_get_original_shipping_cost', $cost, $this->apply_currency );
						} else {
							$rate['cost'] = YayCurrencyHelper::calculate_price_by_currency( $cost, true, $this->apply_currency );
						}
						$method->set_cost( $rate['cost'] );
					}
				} else {
					$special_shipping_methods = array( 'per_product', 'tree_table_rate' );
					if ( in_array( $method->method_id, $special_shipping_methods ) ) {
						if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) || $this->is_original_default_currency() ) {
							return $methods;
						}
						$method->cost = YayCurrencyHelper::calculate_price_by_currency( $method->cost, true, $this->apply_currency );
						return $methods;
					}

					if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) || $this->is_original_default_currency() ) {
						return $methods;
					}
					$data = get_option( 'woocommerce_' . $method->method_id . '_' . $method->instance_id . '_settings' );
					$data = apply_filters( 'yay_currency_get_data_info_from_shipping_method', $data, $method->method_id, $package['contents_cost'], $this->apply_currency );
					$method->set_cost( isset( $data['cost'] ) ? YayCurrencyHelper::calculate_price_by_currency( $data['cost'], true, $this->apply_currency ) : YayCurrencyHelper::calculate_price_by_currency( $method->get_cost(), true, $this->apply_currency ) );
				}

				// Set tax for shipping method
				if ( count( $method->get_taxes() ) ) {
					$apply_currency = $this->apply_currency;
					if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) || $this->is_original_default_currency() ) {
						return $methods;
					}
					$tax_new = array();
					foreach ( $method->get_taxes() as $key => $tax ) {
						$tax_currency = YayCurrencyHelper::calculate_price_by_currency( $tax, true, $apply_currency );
						if ( 'flat_rate' === $method->method_id && isset( $cost ) && ! is_numeric( $cost ) ) {
							$tax_calculate   = \WC_Tax::calc_shipping_tax( $rate['cost'], \WC_Tax::get_shipping_tax_rates() );
							$tax_new[ $key ] = is_array( $tax_calculate ) ? array_sum( $tax_calculate ) : $tax_currency;
						} else {
							$tax_new[ $key ] = $tax_currency;
						}
					}
					$method->set_taxes( $tax_new );
				}
			}
		}
		return $methods;
	}

	// Change currency when send mail start
	public function change_format_order_item_totals( $total_rows, $order, $tax_display ) {
		if ( apply_filters( 'yay_currency_is_original_format_order_item_totals', false, $total_rows, $order, $tax_display ) ) {
					return $total_rows;
		}

		$currency_code = YayCurrencyHelper::get_currency_code_by_order( $order );
		if ( ! empty( $currency_code ) ) {
			$convert_currency = YayCurrencyHelper::get_currency_by_currency_code( $currency_code, $this->converted_currency );
			// Fee
			$fees = $order->get_fees();
			if ( $fees ) {
				foreach ( $fees as $id => $fee ) {
					if ( apply_filters( 'woocommerce_get_order_item_totals_excl_free_fees', empty( $fee['line_total'] ) && empty( $fee['line_tax'] ), $id ) ) {
						continue;
					}
					$price_format                          = 'excl' === $tax_display ? $fee->get_total() : $fee->get_total() + $fee->get_total_tax();
					$total_rows[ 'fee_' . $fee->get_id() ] = array(
						'label' => $fee->get_name() . ':',
						'value' => YayCurrencyHelper::get_formatted_total_by_convert_currency( $price_format, $convert_currency, $currency_code ),
					);

				}
			}
			// Tax for tax exclusive prices.
			if ( 'excl' === $tax_display && wc_tax_enabled() ) {
				if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
					foreach ( $order->get_tax_totals() as $code => $tax ) {
						$formatted_tax_amount                  = YayCurrencyHelper::get_formatted_total_by_convert_currency( $tax->amount, $convert_currency, $currency_code );
						$total_rows[ sanitize_title( $code ) ] = array(
							'label' => $tax->label . ':',
							'value' => $formatted_tax_amount, // $tax->formatted_amount
						);
					}
				} else {
					$total_rows['tax'] = array(
						'label' => WC()->countries->tax_or_vat() . ':',
						'value' => YayCurrencyHelper::get_formatted_total_by_convert_currency( $order->get_total_tax(), $convert_currency, $currency_code ),
					);
				}
			}
			// Refund
			if ( method_exists( $order, 'get_refunds' ) ) {
				$refunds = $order->get_refunds();
				if ( $refunds ) {
					foreach ( $refunds as $id => $refund ) {
							$total_rows[ 'refund_' . $id ] = array(
								'label' => $refund->get_reason() ? $refund->get_reason() : __( 'Refund', 'woocommerce' ) . ':',
								'value' => YayCurrencyHelper::get_formatted_total_by_convert_currency( '-' . $refund->get_amount(), $convert_currency, $currency_code ),
							);
					}
				}
			}
		}
		return $total_rows;
	}

	public function change_format_order_line_subtotal( $subtotal, $item, $order ) {

		if ( ! apply_filters( 'yay_currency_is_change_format_order_line_subtotal', true, $subtotal, $item, $order ) ) {
					return $subtotal;
		}

		$currency_code = YayCurrencyHelper::get_currency_code_by_order( $order );
		if ( ! empty( $currency_code ) ) {
			$convert_currency = YayCurrencyHelper::get_currency_by_currency_code( $currency_code, $this->converted_currency );
			$tax_display      = get_option( 'woocommerce_tax_display_cart' );
			if ( 'excl' === $tax_display ) {
						$ex_tax_label = $order->get_prices_include_tax() ? 1 : 0;
						$subtotal     = YayCurrencyHelper::get_formatted_total_by_convert_currency( $order->get_line_subtotal( $item ), $convert_currency, $currency_code, $ex_tax_label );
			} else {
				$subtotal = YayCurrencyHelper::get_formatted_total_by_convert_currency( $order->get_line_subtotal( $item, true ), $convert_currency, $currency_code );
			}
		}
		return $subtotal;
	}

	protected function get_values_for_total( $field, $order ) {
		$items = array_map(
			function ( $item ) use ( $field ) {
				return wc_add_number_precision( $item[ $field ], false );
			},
			array_values( $order->get_items() )
		);
		return $items;
	}

	protected function round_line_tax( $value = 0, $in_cents = true ) {
		$round_at_subtotal = 'yes' === get_option( 'woocommerce_tax_round_at_subtotal' ) ? true : false;
		if ( ! $round_at_subtotal ) {
			$precision = $in_cents ? 0 : null;
			$value     = wc_round_tax_total( $value, $precision );
		}
		return $value;
	}

	public function get_formatted_order_subtotal( $subtotal, $compound, $order ) {
		$currency_code = YayCurrencyHelper::get_currency_code_by_order( $order );
		if ( ! empty( $currency_code ) ) {
			$convert_currency = YayCurrencyHelper::get_currency_by_currency_code( $currency_code, $this->converted_currency );
			$tax_display      = get_option( 'woocommerce_tax_display_cart' );
			$subtotal         = wc_remove_number_precision(
				$order->get_rounded_items_total( self::get_values_for_total( 'subtotal', $order ) )
			);

			if ( ! $compound ) {
				if ( 'incl' === $tax_display ) {
					$subtotal_taxes = 0;
					foreach ( $order->get_items() as $item ) {
						$subtotal_taxes += self::round_line_tax( $item->get_subtotal_tax(), false );
					}
					$subtotal += wc_round_tax_total( $subtotal_taxes );
				}
						$subtotal = YayCurrencyHelper::get_formatted_total_by_convert_currency( $subtotal, $convert_currency, $currency_code );
				if ( 'excl' === $tax_display && $order->get_prices_include_tax() && wc_tax_enabled() ) {
					$subtotal .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
				}
			} else {
				if ( 'incl' === $tax_display ) {
					return '';
				}
				// Add Shipping Costs.
				$subtotal += $order->get_shipping_total();
				// Remove non-compound taxes.
				foreach ( $order->get_taxes() as $tax ) {
					if ( $tax->is_compound() ) {
						continue;
					}
					$subtotal = $subtotal + $tax->get_tax_total() + $tax->get_shipping_tax_total();
				}
				// Remove discounts.
				$subtotal = $subtotal - $order->get_total_discount();
				$subtotal = YayCurrencyHelper::get_formatted_total_by_convert_currency( $subtotal, $convert_currency, $currency_code );
			}
		}
		return $subtotal;
	}

	public function get_formatted_order_shipping( $shipping, $order, $tax_display ) {
		$currency_code = YayCurrencyHelper::get_currency_code_by_order( $order );
		if ( ! empty( $currency_code ) ) {
			$convert_currency = YayCurrencyHelper::get_currency_by_currency_code( $currency_code, $this->converted_currency );
			$tax_display      = $tax_display ? $tax_display : get_option( 'woocommerce_tax_display_cart' );

			if ( 0 < abs( (float) $order->get_shipping_total() ) ) {
				if ( 'excl' === $tax_display ) {
					// Show shipping excluding tax.
					$shipping = YayCurrencyHelper::get_formatted_total_by_convert_currency( $order->get_shipping_total(), $convert_currency, $currency_code );
					if ( (float) $order->get_shipping_tax() > 0 && $order->get_prices_include_tax() ) {
						$shipping .= apply_filters( 'woocommerce_order_shipping_to_display_tax_label', '&nbsp;<small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>', $order, $tax_display );
					}
				} else {
					// Show shipping including tax.
					$shipping = YayCurrencyHelper::get_formatted_total_by_convert_currency( $order->get_shipping_total() + $order->get_shipping_tax(), $convert_currency, $currency_code );
					if ( (float) $order->get_shipping_tax() > 0 && ! $order->get_prices_include_tax() ) {
						$shipping .= apply_filters( 'woocommerce_order_shipping_to_display_tax_label', '&nbsp;<small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>', $order, $tax_display );
					}
				}
						/* translators: %s: method */
						$shipping .= apply_filters( 'woocommerce_order_shipping_to_display_shipped_via', '&nbsp;<small class="shipped_via">' . sprintf( __( 'via %s', 'woocommerce' ), $order->get_shipping_method() ) . '</small>', $order );
			} elseif ( $order->get_shipping_method() ) {
				return $shipping;
			} else {
				$shipping = __( 'Free!', 'woocommerce' );
			}
		}
		return $shipping;
	}

	public function get_formatted_order_discount( $discount, $order ) {
		$currency_code = YayCurrencyHelper::get_currency_code_by_order( $order );
		if ( ! empty( $currency_code ) ) {
			$tax_display      = get_option( 'woocommerce_tax_display_cart' );
			$convert_currency = YayCurrencyHelper::get_currency_by_currency_code( $currency_code, $this->converted_currency );
			$price_format     = $order->get_total_discount( 'excl' === $tax_display );
			$discount         = YayCurrencyHelper::get_formatted_total_by_convert_currency( $price_format, $convert_currency, $currency_code );
		}
		return $discount;
	}

	public function get_formatted_order_total( $formatted_total, $order, $tax_display, $display_refunded ) {

		if ( apply_filters( 'yay_currency_skip_order_total_formatting', false ) ) {
			return $formatted_total;
		}

		$total_refunded = $order->get_total_refunded();

		if ( $total_refunded && $display_refunded ) {
			return $formatted_total;
		}

		$currency_code = YayCurrencyHelper::get_currency_code_by_order( $order );

		if ( ! empty( $currency_code ) ) {
			$convert_currency = YayCurrencyHelper::get_currency_by_currency_code( $currency_code, $this->converted_currency );
			if ( ! $convert_currency ) {
				return $formatted_total;
			}

			$total = YayCurrencyHelper::get_total_by_order( $order );

			$formatted_total = YayCurrencyHelper::get_formatted_total_by_convert_currency( $total, $convert_currency, $currency_code );

			if ( wc_tax_enabled() && 'incl' === $tax_display ) {
				$formatted_tax = sprintf( '%s %s', YayCurrencyHelper::get_formatted_total_by_convert_currency( $order->get_total_tax(), $convert_currency, $currency_code ), WC()->countries->tax_or_vat() );
				/* translators: %s: taxes */
				$formatted_tax_string = ' <small class="includes_tax">' . sprintf( __( '(includes %s)', 'woocommerce' ), $formatted_tax ) . '</small>';
				$formatted_total      = $formatted_total . $formatted_tax_string;
			}
		}

		return $formatted_total;

	}

	public function change_woocommerce_currency( $currency ) {
		$apply_currency = YayCurrencyHelper::get_current_currency( $this->apply_currency );
		if ( ! $apply_currency || YayCurrencyHelper::disable_fallback_option_in_checkout_page( $apply_currency ) ) {
			$currency = apply_filters( 'yay_currency_woocommerce_currency', $currency, $this->is_dis_checkout_diff_currency );
			return $currency;
		}

		if ( isset( $apply_currency['currency'] ) ) {
			$currency = $apply_currency['currency'];
		}
		$currency = apply_filters( 'yay_currency_woocommerce_currency', $currency, $this->is_dis_checkout_diff_currency );
		return $currency;
	}

	public function change_existing_currency_symbol( $currency_symbol, $currency ) {
		$apply_currency          = YayCurrencyHelper::get_current_currency( $this->apply_currency );
		$default_currency_symbol = SupportHelper::detect_keep_old_currency_symbol( false, $this->is_dis_checkout_diff_currency, $this->apply_currency );

		if ( ! $apply_currency || $default_currency_symbol || YayCurrencyHelper::disable_fallback_option_in_checkout_page( $apply_currency ) || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
			$currency_symbol = apply_filters( 'yay_currency_woocommerce_currency_symbol', $currency_symbol, $currency, $apply_currency );
			return $currency_symbol;
		}

		if ( isset( $apply_currency['currency'] ) ) {
			$currency_symbol = wp_kses_post( html_entity_decode( $apply_currency['symbol'] ) );
			$currency_symbol = apply_filters( 'yay_currency_woocommerce_currency_symbol', $currency_symbol, $currency, $apply_currency );
		}

		return $currency_symbol;
	}

	public function change_currency_position() {
		$apply_currency = YayCurrencyHelper::get_current_currency( $this->apply_currency );
		return Helper::change_currency_position( $apply_currency );
	}

	public function change_thousand_separator() {
		$apply_currency = YayCurrencyHelper::get_current_currency( $this->apply_currency );
		return Helper::change_thousand_separator( $apply_currency );
	}

	public function change_decimal_separator() {
		$apply_currency = YayCurrencyHelper::get_current_currency( $this->apply_currency );
		return Helper::change_decimal_separator( $apply_currency );
	}

	public function change_number_decimals() {
		$apply_currency = YayCurrencyHelper::get_current_currency( $this->apply_currency );
		return Helper::change_number_decimals( $apply_currency );
	}
}
