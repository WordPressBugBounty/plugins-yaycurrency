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

		add_filter( 'wc_epo_enabled_currencies', [ $this, 'wc_epo_enabled_currencies' ], 10, 1 );
		add_filter( 'wc_epo_replace_math_currency', [ $this, 'wc_epo_replace_math_currency' ], 10, 1 );
		add_filter( 'wc_epo_adjust_cart_item', array( $this, 'wc_epo_adjust_cart_item' ), 9999, 1 );

		add_filter( 'YayCurrency/StoreCurrency/GetPrice', array( $this, 'get_price_default_in_checkout_page' ), 10, 2 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 10, 2 );
		add_filter( 'YayCurrency/ApplyCurrency/GetPriceOptions', array( $this, 'get_price_options' ), 10, 2 );
		add_filter( 'YayCurrency/ApplyCurrency/ByCartItem/GetPriceOptions', array( $this, 'get_price_options_by_cart_item' ), 10, 5 );
		add_filter( 'YayCurrency/ApplyCurrency/ThirdPlugins/GetProductPrice', array( $this, 'get_product_price_by_3rd_plugin' ), 10, 3 );
		// Convert Price from WooCommerce TM Extra Product Options plugin
		add_filter( 'wc_epo_option_price_correction', array( $this, 'wc_epo_option_price_correction' ), 10, 2 );
		add_filter( 'wc_epo_get_current_currency_price', array( $this, 'wc_epo_get_current_currency_price' ), 10, 6 );
		add_filter( 'wc_epo_convert_to_currency', array( $this, 'wc_epo_convert_to_currency' ), 10, 4 );
		add_filter( 'wc_epo_get_currency_price', array( $this, 'wc_epo_get_currency_price' ), 10, 7 );
		add_filter( 'wc_epo_price_on_cart', array( $this, 'wc_epo_price_on_cart' ), 10, 2 );

	}

	public function wc_epo_enabled_currencies( $currencies = [] ) {
		$all_currencies = Helper::get_currencies_post_type();
		if ( ! $all_currencies ) {
			return $currencies;
		}
		$enabled_currencies = array();
		foreach ( $all_currencies as $value ) {
			array_push( $enabled_currencies, $value->post_title );
		}
		return $enabled_currencies;
	}

	public function wc_epo_replace_math_currency( $formula = '' ) {
		$formula = preg_replace_callback(
			'/\{(\d+)\}/u',
			function ( $matches ) {
				// We have null in currency so that we get the current currency.
				return $this->wc_epo_get_current_currency_price( $matches[1], '', null, null );
			},
			$formula
		);
		return $formula;
	}

	// set price options in cart item on cart page
	public function wc_epo_adjust_cart_item( $cart_item ) {
		$tmcartepo = isset( $cart_item['tmcartepo'] ) && ! empty( $cart_item['tmcartepo'] ) ? $cart_item['tmcartepo'] : false;
		if ( $tmcartepo ) {
			foreach ( $tmcartepo as $k => $epo ) {
				if ( ! isset( $epo['key'] ) ) {
					continue;
				}
				if ( isset( $cart_item['data']->tm_epo_set_options_price ) ) {
					$cart_item['tmcartepo'][ $k ]['price'] = $cart_item['data']->tm_epo_set_options_price;
				}
			}
		}

		return $cart_item;
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

	public function get_price_options_convert( $cart_item ) {
		$apply_currency            = YayCurrencyHelper::get_current_currency( $this->apply_currency );
		$product_id                = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
		$product_price             = SupportHelper::get_product_price( $product_id );
		$product_price_by_currency = YayCurrencyHelper::calculate_price_by_currency( $product_price, false, $apply_currency );

		$price_convert_options             = 0;
		$price_convert_options_by_currency = 0;
		$is_percent_options                = false;
		$element_object                    = self::get_element_object_by_product_id( $product_id );

		foreach ( $cart_item['tmcartepo'] as $k => $epo ) {
			if ( ! isset( $epo['key'] ) || ! isset( $element_object[ $epo['section'] ] ) ) {
				continue;
			}

			$price_per_currency            = isset( $epo['price_per_currency'] ) ? $epo['price_per_currency'] : array();
			$current_currency_code         = $apply_currency['currency'];
			$price_option_default_currency = isset( $price_per_currency[ $this->default_currency_code ] ) ? $price_per_currency[ $this->default_currency_code ] : 0;
			$price_option_current_currency = isset( $price_per_currency[ $current_currency_code ] ) && ! empty( $price_per_currency[ $current_currency_code ] ) ? $price_per_currency[ $current_currency_code ] : $price_option_default_currency;

			if ( 'builder' === $epo['mode'] ) {
				$key                           = $epo['key'];
				$_price_type                   = THEMECOMPLETE_EPO()->get_saved_element_price_type( $epo );
				$price_per_currencies          = isset( $element_object[ $epo['section'] ]['price_per_currencies'] ) ? $element_object[ $epo['section'] ]['price_per_currencies'] : [];
				$price_option_default_currency = isset( $price_per_currencies[ $this->default_currency_code ][ $key ][0] ) && ! empty( $price_per_currencies[ $this->default_currency_code ][ $key ][0] ) ? $price_per_currencies[ $this->default_currency_code ][ $key ][0] : false;
				if ( $price_option_default_currency ) {
					$price_option_current_currency = isset( $price_per_currencies[ $current_currency_code ][ $key ][0] ) && ! empty( $price_per_currencies[ $current_currency_code ][ $key ][0] ) ? $price_per_currencies[ $current_currency_code ][ $key ][0] : false;
				}
			}
			if ( 'percent' === $_price_type ) {
				$is_percent_options                 = true;
				$price_convert_options             += $product_price * ( $price_option_default_currency / 100 );
				$price_convert_options_by_currency += $product_price_by_currency * ( ( $price_option_current_currency ? $price_option_current_currency : $price_option_default_currency ) / 100 );

			} else {
				$price_convert_options             += $price_option_default_currency;
				$price_option_apply                 = $price_option_current_currency ? $price_option_current_currency : YayCurrencyHelper::calculate_price_by_currency( $price_option_default_currency, false, $apply_currency );
				$price_convert_options_by_currency += $price_option_apply;
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

	protected function get_element_object_by_product_id( $product_id ) {

		$element_object = array();
		if ( function_exists( 'THEMECOMPLETE_EPO_Cart' ) ) {
			$populate_arrays = THEMECOMPLETE_EPO_Cart()->populate_arrays( $product_id, false, false, '' );
			if ( $populate_arrays ) {
				$global_prices = $populate_arrays['global_prices'];
				$pl            = [ 'before', 'after' ];
				foreach ( $pl as $where ) {
					foreach ( $global_prices[ $where ] as $priorities ) {
						foreach ( $priorities as $field ) {
							foreach ( $field['sections'] as $section_id => $section ) {
								if ( isset( $section['elements'] ) ) {
									foreach ( $section['elements'] as $element ) {
										$element_object[ $element['uniqid'] ] = $element;
									}
								}
							}
						}
					}
				}
			}
		}
		return $element_object;
	}

	public function wc_epo_option_price_correction( $price, $cart_item ) {

		if ( ! empty( $cart_item['tm_epo_set_product_price_with_options'] ) ) {

			$tmcartepo = isset( $cart_item['tmcartepo'] ) && ! empty( $cart_item['tmcartepo'] ) ? $cart_item['tmcartepo'] : false;

			if ( $tmcartepo && isset( $tmcartepo[0]['key'] ) ) {
				$options_price         = (float) $cart_item['tm_epo_options_prices'];
				$product_obj           = $cart_item['data'];
				$data_convert_options  = $this->get_price_options_convert( $cart_item );
				$price_options_default = isset( $data_convert_options['price_options'] ) && ! empty( $data_convert_options['price_options'] ) ? $data_convert_options['price_options'] : 0;
				if ( $data_convert_options['is_percent_options'] ) {
					$options_price = $data_convert_options['price_options_by_currency'];
				} else {
					$options_price = isset( $data_convert_options['price_options_by_currency'] ) && ! empty( $data_convert_options['price_options_by_currency'] ) ? $data_convert_options['price_options_by_currency'] : YayCurrencyHelper::calculate_price_by_currency( $price_options_default, true, $this->apply_currency );
				}

				$product_obj->tm_epo_set_options_price                      = $options_price;
				$product_obj->tm_epo_set_product_price_with_options         = $data_convert_options['product_price_currency'] + $options_price;
				$product_obj->tm_epo_set_options_price_default_currency     = $price_options_default;
				$product_obj->tm_epo_set_product_price_with_options_default = $data_convert_options['product_price'] + $price_options_default;

			}
		}

		return $price;

	}

	public function wc_epo_get_current_currency_price( $price = '', $type = '', $currencies = null, $currency = false, $product_price = false, $tc_added_in_currency = false ) {
		if ( $currency === $tc_added_in_currency ) {
			return $price;
		}
		if ( is_array( $type ) ) {
			$type = '';
		}
		// checkout page
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
		// Check if the price should be processed only once.
		if ( 'math' === $type ) {
			// Replaces any number between curly braces with the current currency.
			$price = preg_replace_callback(
				'/\{(\d+)\}/u',
				function ( $matches ) use ( $currency ) {
					return apply_filters( 'wc_epo_get_currency_price', $matches[1], $currency, '' );
				},
				$price
			);
		} elseif ( in_array( (string) $type, [ '', 'fixedcurrenttotal', 'word', 'wordnon', 'char', 'step', 'intervalstep', 'charnofirst', 'charnospaces', 'fixednon', 'charnon', 'charnonnospaces', 'fee', 'stepfee', 'subscriptionfee' ], true ) ) {
			if ( is_array( $currencies ) && isset( $currencies[ $this->apply_currency['currency'] ] ) ) {
				$price = $currencies[ $this->apply_currency['currency'] ];
			} else {
				$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
			}
		} elseif ( false !== $product_price && false !== $tc_added_in_currency && (string) 'percent' === $type ) {
			if ( is_array( $currencies ) && isset( $currencies[ $this->apply_currency['currency'] ] ) ) {
				$product_price = $currencies[ $this->apply_currency['currency'] ];
			} else {
				$product_price = YayCurrencyHelper::calculate_price_by_currency( $product_price, false, $this->apply_currency );
			}
			$price = floatval( $product_price ) * ( floatval( $price ) / 100 );
		}

		return $price;
	}

	//apply with case is percent
	public function wc_epo_convert_to_currency( $cpf_product_price = '', $tc_added_in_currency = false, $current_currency = false, $force = false ) {

		if ( ! $tc_added_in_currency || ! $current_currency || $tc_added_in_currency === $this->default_currency_code ) {
			return $cpf_product_price;
		}

		$apply_currency = YayCurrencyHelper::get_currency_by_currency_code( $tc_added_in_currency );
		$price          = $cpf_product_price / YayCurrencyHelper::get_rate_fee( $apply_currency );

		return $price;
	}

	public function wc_epo_get_currency_price( $price = '', $currency = false, $price_type = '', $current_currency = false, $price_per_currencies = null, $key = null, $attribute = null ) {

		if ( ! $currency ) {
			return $this->wc_epo_get_current_currency_price( $price, $price_type, null, $currency );
		}

		$current_currency = class_exists( 'WCPay\MultiCurrency\MultiCurrency' ) ? $this->apply_currency['currency'] : $current_currency;
		if ( $current_currency && $current_currency === $currency && $current_currency === $this->default_currency_code ) {
			return $price;
		}

		if ( $current_currency && $current_currency === $currency ) {
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
