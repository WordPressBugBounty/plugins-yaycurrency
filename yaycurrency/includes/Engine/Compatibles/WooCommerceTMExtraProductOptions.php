<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\SupportHelper;
use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://codecanyon.net/item/woocommerce-extra-product-options/7908619

class WooCommerceTMExtraProductOptions {
	use SingletonTrait;

	private $apply_currency                = array();
	private $is_dis_checkout_diff_currency = false;
	private $default_currency_code;

	public function __construct() {

		if ( ! defined( 'THEMECOMPLETE_EPO_PLUGIN_FILE' ) ) {
			return;
		}
		$this->apply_currency                = YayCurrencyHelper::detect_current_currency();
		$this->is_dis_checkout_diff_currency = YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency );
		$this->default_currency_code         = Helper::default_currency_code();

		add_filter( 'yay_currency_get_price_default_in_checkout_page', array( $this, 'get_price_default_in_checkout_page' ), 10, 2 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 10, 2 );
		add_filter( 'yay_currency_price_options', array( $this, 'get_price_options' ), 10, 2 );
		add_filter( 'yay_currency_get_price_options_by_cart_item', array( $this, 'get_price_options_by_cart_item' ), 10, 5 );
		add_filter( 'yay_currency_get_product_price_by_3rd_plugin', array( $this, 'get_product_price_by_3rd_plugin' ), 10, 3 );
		// Convert Price from WooCommerce TM Extra Product Options plugin
		add_filter( 'wc_epo_option_price_correction', array( $this, 'wc_epo_option_price_correction' ), 10, 2 );
		add_filter( 'wc_epo_get_current_currency_price', array( $this, 'wc_epo_get_current_currency_price' ), 10, 6 );
		add_filter( 'wc_epo_convert_to_currency', array( $this, 'wc_epo_convert_to_currency' ), 10, 4 );
		add_filter( 'wc_epo_get_currency_price', array( $this, 'wc_epo_get_currency_price' ), 10, 7 );
		add_filter( 'wc_epo_price_on_cart', array( $this, 'wc_epo_price_on_cart' ), 10, 2 );

		add_filter( 'wc_epo_adjust_cart_item', array( $this, 'wc_epo_adjust_cart_item' ), 9999, 1 );

	}

	public function get_price_options_by_cart_item( $price_options, $cart_item, $product_id, $original_price, $apply_currency ) {
		$_product = $cart_item['data'];
		if ( isset( $_product->tm_epo_set_options_price ) ) {
			$price_options = (float) $_product->tm_epo_set_options_price;
		}

		return $price_options;
	}

	public function get_price_default_in_checkout_page( $price, $product ) {
		if ( isset( $product->tm_epo_set_product_price_with_options_default ) ) {
			$price = $product->tm_epo_set_product_price_with_options_default;
		}
		return $price;
	}

	public function get_price_with_options( $price, $product ) {
		if ( isset( $product->tm_epo_set_product_price_with_options ) ) {
			$price = $product->tm_epo_set_product_price_with_options;
		}
		return $price;
	}

	public function get_price_options( $price_options, $product ) {
		if ( isset( $product->tm_epo_set_options_price ) ) {
			$price_options = $product->tm_epo_set_options_price;
		}
		return $price_options;
	}

	public function get_product_price_by_3rd_plugin( $product_price, $product, $apply_currency ) {

		if ( isset( $product->tm_epo_set_product_price_with_options ) ) {
			$product_price = $product->tm_epo_set_product_price_with_options;
		}

		return $product_price;
	}

	public function wc_epo_adjust_cart_item( $cart_item ) {

		$tmcartepo = isset( $cart_item['tmcartepo'] ) && ! empty( $cart_item['tmcartepo'] ) ? $cart_item['tmcartepo'] : false;
		if ( $tmcartepo ) {
			$product_price             = SupportHelper::calculate_product_price_by_cart_item( $cart_item );
			$product_price_by_currency = YayCurrencyHelper::calculate_price_by_currency( $product_price, false, $this->apply_currency );
			foreach ( $tmcartepo as $k => $epo ) {
				$tmdata                          = isset( $cart_item['tmdata'] ) && ! empty( $cart_item['tmdata'] ) ? $cart_item['tmdata'] : false;
				$_price_type                     = THEMECOMPLETE_EPO()->get_saved_element_price_type( $epo );
				$_key                            = $epo['key'];
				$discount_price                  = isset( $epo['element']['rules'][ $_key ] ) ? $epo['element']['rules'][ $_key ][0] : 0;
				$discount_price_default_currency = $discount_price;

				if ( 'percent' === $_price_type && $tmdata ) {

					$discount_price_by_currency            = $product_price_by_currency * ( $discount_price / 100 );
					$cart_item['tmcartepo'][ $k ]['price'] = $discount_price_by_currency;

					$discount_price_default_currency = $product_price * ( $discount_price_default_currency / 100 );

				}
				$cart_item['tmcartepo'][ $k ]['price_per_currency'] = array(
					$this->default_currency_code => $discount_price_default_currency,
				);
			}
		}

		return $cart_item;

	}

	public function get_original_price_options_by_cart_item( $cart_item ) {
		$tmcartepo = isset( $cart_item['tmcartepo'] ) && ! empty( $cart_item['tmcartepo'] ) ? array_shift( $cart_item['tmcartepo'] ) : false;
		if ( $tmcartepo ) {
			$product_price  = SupportHelper::calculate_product_price_by_cart_item( $cart_item );
			$_price_type    = THEMECOMPLETE_EPO()->get_saved_element_price_type( $tmcartepo );
			$_key           = $tmcartepo['key'];
			$discount_price = $tmcartepo['element']['rules'][ $_key ][0];
			if ( 'percent' === $_price_type ) {
				$discount_price = $product_price * ( $discount_price / 100 );
			}
			$cart_item['discount_price_default_currency']       = $discount_price;
			$cart_item['data']->discount_price_default_currency = $discount_price;
		}
		return $cart_item;
	}

	public function get_price_options_convert( $cart_item ) {
		$product_price                     = SupportHelper::calculate_product_price_by_cart_item( $cart_item );
		$product_price_by_currency         = YayCurrencyHelper::calculate_price_by_currency( $product_price, false, $this->apply_currency );
		$price_convert_options             = 0;
		$price_convert_options_by_currency = 0;
		$is_percent_options                = false;

		foreach ( $cart_item['tmcartepo'] as $k => $epo ) {
			$_price_type = THEMECOMPLETE_EPO()->get_saved_element_price_type( $epo );

			$_key           = $epo['key'];
			$discount_price = $epo['element']['rules'][ $_key ][0];

			if ( 'percent' === $_price_type ) {
				$is_percent_options                 = true;
				$price_convert_options             += $product_price * ( $discount_price / 100 );
				$price_convert_options_by_currency += $product_price_by_currency * ( $discount_price / 100 );

			} else {
				$price_convert_options             += $discount_price;
				$price_convert_options_by_currency += YayCurrencyHelper::calculate_price_by_currency( $discount_price, false, $this->apply_currency );
			}
		}
		return array(
			'product_price'             => $product_price,
			'product_price_currency'    => $product_price_by_currency,
			'price_options'             => $price_convert_options,
			'price_options_by_currency' => $price_convert_options_by_currency,
			'is_percent_options'        => $is_percent_options,
		);
	}

	public function wc_epo_option_price_correction( $price, $cart_item ) {

		if ( ! empty( $cart_item['tm_epo_set_product_price_with_options'] ) ) {

			$tmcartepo = isset( $cart_item['tmcartepo'] ) && ! empty( $cart_item['tmcartepo'] ) ? $cart_item['tmcartepo'] : false;

			if ( $tmcartepo ) {
				$discount_details               = $this->get_original_price_options_by_cart_item( $cart_item );
				$options_price                  = (float) $cart_item['tm_epo_options_prices'];
				$options_price_default_currency = isset( $discount_details['discount_price_default_currency'] ) ? (float) $discount_details['discount_price_default_currency'] : $options_price / YayCurrencyHelper::get_rate_fee( $this->apply_currency );

				$product_obj          = $cart_item['data'];
				$data_convert_options = $this->get_price_options_convert( $cart_item );

				if ( $data_convert_options['is_percent_options'] ) {
					$options_price = $data_convert_options['price_options_by_currency'];
				} else {
					$price_options = isset( $data_convert_options['price_options'] ) && ! empty( $data_convert_options['price_options'] ) ? $data_convert_options['price_options'] : 0;
					$options_price = YayCurrencyHelper::calculate_price_by_currency( $price_options, true, $this->apply_currency );
				}

				$product_obj->tm_epo_set_options_price                      = $options_price;
				$product_obj->tm_epo_set_product_price_with_options         = $data_convert_options['product_price_currency'] + $options_price;
				$product_obj->tm_epo_set_options_price_default_currency     = isset( $discount_details->discount_price_default_currency ) ? (float) $discount_details->discount_price_default_currency : 0;
				$product_obj->tm_epo_set_product_price_with_options_default = (float) $cart_item['tm_epo_product_original_price'] + $options_price_default_currency;
			}
		}

		return $price;

	}

	public function wc_epo_get_current_currency_price( $price = '', $type = '', $currencies = null, $currency = false, $product_price = false, $tc_added_in_currency = false ) {
		$types = array( '', 'math', 'fixedcurrenttotal' );
		if ( $currency ) {
			return $price;
		}
		if ( in_array( $type, $types, true ) ) {
			if ( is_checkout() && ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) ) {
				return $price;
			}
			// edit order
			if ( is_admin() && isset( $_GET['post'] ) ) {
				$post_id = (int) sanitize_key( $_GET['post'] );
				if ( Helper::check_custom_orders_table_usage_enabled() ) {
					if ( 'shop_order' === OrderUtil::get_order_type( $post_id ) ) {
						return $price;
					}
				} elseif ( 'shop_order' === get_post_type( $post_id ) ) {
						return $price;
				}
			}
			$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		}
		return $price;
	}

	//apply with case is percent
	public function wc_epo_convert_to_currency( $cpf_product_price = '', $tc_added_in_currency = false, $current_currency = false, $force = false ) {
		if ( ! $tc_added_in_currency || ! $current_currency || $tc_added_in_currency === $current_currency ) {
			return $cpf_product_price;
		}

		if ( $tc_added_in_currency === $this->default_currency_code && $current_currency === $this->default_currency_code ) {
			return $cpf_product_price;
		} elseif ( $tc_added_in_currency === $this->default_currency_code && $current_currency !== $this->default_currency_code ) {
			$price = YayCurrencyHelper::calculate_price_by_currency( $cpf_product_price, false, $this->apply_currency );
		} else {
			$apply_currency = YayCurrencyHelper::get_currency_by_currency_code( $tc_added_in_currency );
			$price          = $cpf_product_price / YayCurrencyHelper::get_rate_fee( $apply_currency );
		}
		return $price;
	}

	public function wc_epo_get_currency_price( $price = '', $currency = false, $price_type = '', $current_currency = false, $price_per_currencies = null, $key = null, $attribute = null ) {
		if ( ! $currency ) {
			return $this->wc_epo_get_current_currency_price( $price, $price_type, $currency );
		}

		$current_currency = class_exists( 'WCPay\MultiCurrency\MultiCurrency' ) ? $this->apply_currency['currency'] : $current_currency;
		if ( $current_currency && $current_currency === $currency && $current_currency === $this->default_currency_code ) {
			return $price;
		}

		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		return $price;

	}


	public function wc_epo_price_on_cart( $price, $cart_item ) {

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			$price = (float) ( $price / YayCurrencyHelper::get_rate_fee( $this->apply_currency ) );
			if ( isset( $cart_item['data']->discount_price_default_currency ) ) {
				$price = (float) $cart_item['data']->discount_price_default_currency;
			}
		}

		return $price;

	}
}
