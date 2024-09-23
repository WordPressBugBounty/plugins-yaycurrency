<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class YITHWooCommerceSubscription {

	use SingletonTrait;

	private $apply_currency = array();
	private $is_checkout_different_currency;
	private $is_dis_checkout_diff_currency = false;

	public function __construct() {

		if ( ! defined( 'YITH_YWSBS_VERSION' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		$this->is_checkout_different_currency = get_option( 'yay_currency_checkout_different_currency', 0 );
		$this->is_dis_checkout_diff_currency  = YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency );

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

		add_filter( 'ywsbs_subscription_meta_on_cart', array( $this, 'custom_ywsbs_subscription_meta_on_cart' ), 10, 2 );
		add_filter( 'ywsbs_subscription_recurring_price', array( $this, 'custom_ywsbs_subscription_recurring_price' ), 10, 3 );
		add_filter( 'ywsbs_checkout_subscription_total_amount', array( $this, 'custom_ywsbs_checkout_subscription_total_amount' ), 10, 3 );
		add_filter( 'ywsbs_change_price_in_cart_html', array( $this, 'custom_ywsbs_change_price_in_cart_html' ), 10, 2 );
		add_filter( 'ywsbs_subscription_subtotal_html', array( $this, 'custom_ywsbs_subscription_subtotal_html' ), 10, 3 );

		add_filter( 'ywsbs_recurring_price_html', array( $this, 'custom_ywsbs_recurring_price_html' ), 10, 4 );
		add_filter( 'ywsbs_change_subtotal_product_price', array( $this, 'custom_ywsbs_change_subtotal_product_price' ), 10, 4 );

	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {
		$subscription_info = isset( $cart_item['ywsbs-subscription-info'] ) ? $cart_item['ywsbs-subscription-info'] : false;
		if ( $subscription_info ) {
			$cart_contents[ $key ]['data']->recurring_price_by_currency = YayCurrencyHelper::calculate_price_by_currency( $subscription_info['recurring_price_default'], false, $apply_currency );
			$cart_contents[ $key ]['data']->recurring_price_default     = $subscription_info['recurring_price_default'];
			$max_length = \YWSBS_Subscription_Helper::get_subscription_product_max_length( $cart_item['data'] );
			if ( ! empty( $max_length ) ) {
				$total_price = $subscription_info['recurring_price_default'] * $max_length;
				if ( ! empty( $subscription_info['price_is_per'] ) ) {
					$total_price = $total_price / $subscription_info['price_is_per'];
				}
				$cart_contents[ $key ]['data']->total_subscription_price = $total_price;
			}
		}
	}

	public function custom_ywsbs_subscription_meta_on_cart( $cart_item_subscription_data, $product ) {
		$cart_item_subscription_data['yay_currency_apply']      = $this->apply_currency['currency'];
		$recurring_price                                        = $cart_item_subscription_data['recurring_price'];
		$cart_item_subscription_data['recurring_price_default'] = $recurring_price / YayCurrencyHelper::get_rate_fee( $this->apply_currency );
		return $cart_item_subscription_data;
	}

	public function custom_ywsbs_subscription_recurring_price( $recurring_price, $product, $subscription_info ) {
		$default_currency   = Helper::default_currency_code();
		$yay_currency_apply = isset( $subscription_info['yay_currency_apply'] ) ? $subscription_info['yay_currency_apply'] : $default_currency;
		if ( $yay_currency_apply === $this->apply_currency['currency'] ) {
			return $recurring_price;
		}
		$product_price   = SupportHelper::get_product_price( $product->get_id() );
		$recurring_price = YayCurrencyHelper::calculate_price_by_currency( $product_price, false, $this->apply_currency );
		return $recurring_price;
	}

	public function custom_ywsbs_checkout_subscription_total_amount( $sbs_total_format, $product, $quantity ) {

		$sbs_total_format = '';
		$max_length       = \YWSBS_Subscription_Helper::get_subscription_product_max_length( $product );
		if ( $max_length && $max_length > 1 ) {
			$sbs_total_format         = get_option( 'ywsbs_total_subscription_length_text', esc_html_x( 'Subscription total for {{sub-time}}: {{sub-total}}', 'do not translate the text inside the brackets', 'yith-woocommerce-subscription' ) );
			$max_length_text          = \YWSBS_Subscription_Helper::get_subscription_max_length_formatted_for_price( $product );
			$total_subscription_price = isset( $product->total_subscription_price ) ? $product->total_subscription_price : false;
			if ( ! $total_subscription_price ) {
				return $sbs_total_format;
			}

			$sbs_total_format = str_replace( '{{sub-time}}', $max_length_text, $sbs_total_format );
			if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
				$converted_approximately = apply_filters( 'yay_currency_checkout_converted_approximately', true, $this->apply_currency );
				if ( $converted_approximately ) {
					$total_subscription_price_approximately = YayCurrencyHelper::calculate_price_by_currency( $total_subscription_price, false, $this->apply_currency );
					$total_subscription_price_approximately = YayCurrencyHelper::format_price( $total_subscription_price_approximately * $quantity );
					$total_subscription_price               = wc_price( $total_subscription_price * $quantity ) . YayCurrencyHelper::converted_approximately_html( $total_subscription_price_approximately );
				} else {
					$total_subscription_price = wc_price( $total_subscription_price * $quantity );
				}

				$sbs_total_format = str_replace( '{{sub-total}}', $total_subscription_price, $sbs_total_format );
			} else {
				$total_subscription_price = YayCurrencyHelper::calculate_price_by_currency( $total_subscription_price, false, $this->apply_currency );
				$total_subscription_price = wc_price( $total_subscription_price * $quantity );
				$sbs_total_format         = str_replace( '{{sub-total}}', $total_subscription_price, $sbs_total_format );
			}

			if ( ! wc_prices_include_tax() ) {
				$sbs_total_format .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
			}

			$sbs_total_format = '<div class="ywsbs-subscription-total">' . $sbs_total_format . '</div>';

		}

		return $sbs_total_format;
	}

	public function custom_ywsbs_change_price_in_cart_html( $cart_item_price, $cart_item_data ) {

		if ( isset( $cart_item_data->recurring_price_by_currency ) ) {
			return YayCurrencyHelper::reverse_calculate_price_by_currency( $cart_item_data->recurring_price_by_currency );
		}
		return $cart_item_price;
	}

	public function custom_ywsbs_subscription_subtotal_html( $price_html, $product, $cart_item ) {
		if ( isset( $product->recurring_price_by_currency ) ) {
			$price_html = wc_price(
				$product->recurring_price_by_currency * $cart_item['quantity'],
				YayCurrencyHelper::get_apply_currency_format_info( $this->apply_currency )
			);
		}

		return $price_html;
	}

	public function custom_ywsbs_recurring_price_html( $pri, $recurring_price, $recurring_period, $cart_item ) {
		if ( ! $this->is_dis_checkout_diff_currency ) {
			return $pri;
		}
		$product_id              = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
		$recurring_price_display = wc_get_product( $product_id )->get_price( 'edit' );
		$recurring_price_display = $recurring_price_display * $cart_item['quantity'];
		if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
			$recurring_tax = ' <small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>';
		} else {
			$recurring_tax = ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
		}
		if ( is_checkout() ) {
			$converted_approximately = apply_filters( 'yay_currency_checkout_converted_approximately', true, $this->apply_currency );
			$currencies_data         = YayCurrencyHelper::get_current_and_fallback_currency( $this->apply_currency );
			if ( ! $converted_approximately || YayCurrencyHelper::is_current_fallback_currency( $currencies_data ) ) {
				$recurring_total      = YayCurrencyHelper::calculate_price_by_currency( $recurring_price_display, false, $this->apply_currency );
				$recurring_total_html = YayCurrencyHelper::format_price( $recurring_total );
				$pri                  = $recurring_total_html . ' / ' . $recurring_period . ' ' . $recurring_tax;
				return $pri;
			}

			$recurring_total      = YayCurrencyHelper::calculate_price_by_currency_html( $this->apply_currency, $recurring_price_display );
			$recurring_total_html = wc_price( $recurring_price_display ) . YayCurrencyHelper::converted_approximately_html( $recurring_total );

			$pri = $recurring_total_html . ' / ' . $recurring_period . ' ' . $recurring_tax;
		} else {
			$recurring_total      = YayCurrencyHelper::calculate_price_by_currency( $recurring_price_display, false, $this->apply_currency );
			$recurring_total_html = YayCurrencyHelper::format_price( $recurring_total );
			$pri                  = $recurring_total_html . ' / ' . $recurring_period . ' ' . $recurring_tax;
		}
		return $pri;
	}

	public function custom_ywsbs_change_subtotal_product_price( $price_html, $product, $quantity, $cart_item ) {
		if ( is_checkout() ) {
			$converted_approximately = apply_filters( 'yay_currency_checkout_converted_approximately', true, $this->apply_currency );
			$currencies_data         = YayCurrencyHelper::get_current_and_fallback_currency( $this->apply_currency );
			if ( ! $converted_approximately || YayCurrencyHelper::is_current_fallback_currency( $currencies_data ) ) {
				return $price_html;
			}
			$subscription_info = false;
			if ( isset( $cart_item['ywsbs-subscription-info'] ) ) {
				$subscription_info = $cart_item['ywsbs-subscription-info'];
			}
			if ( ! $subscription_info ) {
				return $price_html;
			}
			$recurring_price_default = isset( $subscription_info['recurring_price_default'] ) ? $subscription_info['recurring_price_default'] : $subscription_info['recurring_price'];
			$recurring_price         = $this->is_dis_checkout_diff_currency ? $recurring_price_default : YayCurrencyHelper::calculate_price_by_currency( $recurring_price_default, false, $this->apply_currency );
			$price                   = wc_get_price_to_display(
				$product,
				array(
					'qty'   => $quantity,
					'price' => $recurring_price,
				)
			);
			$price_html              = '<div class="ywsbs-wrapper"><div class="ywsbs-price">';
			$price_html             .= wc_price( $price );

			if ( $this->is_dis_checkout_diff_currency ) {
				$price_converted_subtotal = YayCurrencyHelper::calculate_price_by_currency_html( $this->apply_currency, $price );
				$price_html              .= YayCurrencyHelper::converted_approximately_html( $price_converted_subtotal );
			}

			if ( ! isset( $subscription_info['sync'] ) || ! $subscription_info['sync'] ) {
				$price_html .= '<span class="price_time_opt"> / ' . \YWSBS_Subscription_Helper::get_subscription_period_for_price( $product, $subscription_info ) . '</span>';
			}
			$max_length  = \YWSBS_Subscription_Helper::get_subscription_max_length_formatted_for_price( $product, $subscription_info );
			$max_length  = ! empty( $max_length ) ? esc_html__( ' for ', 'yith-woocommerce-subscription' ) . $max_length : '';
			$price_html  = $price_html . '<span class="ywsbs-max-lenght">' . $max_length . '</span>';
			$price_html .= '</div>';
			$price_html .= '</div>';
		}
		return $price_html;

	}
}
