<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://yithemes.com/themes/plugins/yith-woocommerce-product-add-ons/

class YITHWooCommerceAddOnsExtraPremiumOptions {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! defined( 'YITH_WAPO' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_action( 'init', array( $this, 'init' ) );

		add_filter( 'yay_currency_is_original_product_price', array( $this, 'yay_currency_is_original_product_price' ), 10, 3 );

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

		// Custom Option Price Attribute
		add_filter( 'yith_wapo_addon_select_option_args', array( $this, 'yith_wapo_addon_select_option_args' ), 10, 2 );

		// Custom Option Price lable
		// add_filter( 'yith_wapo_option_price', array( $this, 'custom_yith_wapo_option_price' ), 10, 1 );
		// add_filter( 'yith_wapo_option_price_sale', array( $this, 'custom_yith_wapo_option_price_sale' ), 10, 1 );

		add_filter( 'yith_wapo_get_addon_price', array( $this, 'custom_yith_wapo_get_addon_price' ), 10, 5 );
		add_filter( 'yith_wapo_get_addon_sale_price', array( $this, 'custom_yith_wapo_get_addon_price' ), 10, 5 );

		add_filter( 'yay_currency_get_price_default_in_checkout_page', array( $this, 'get_price_default_in_checkout_page' ), 10, 2 );
		add_filter( 'yay_currency_get_price_options_by_cart_item', array( $this, 'get_price_options_by_cart_item' ), 10, 5 );
		add_filter( 'yay_currency_get_cart_subtotal_3rd_plugin', array( $this, 'get_cart_subtotal_3rd_plugin' ), 10, 2 );

		add_filter( 'yith_wapo_addon_prices_on_cart', array( $this, 'yith_wapo_addon_prices_on_cart' ), 10, 1 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 10, 2 );

		add_filter( 'yay_currency_price_options', array( $this, 'get_price_options' ), 10, 2 );
		add_filter( 'yay_currency_price_options_default', array( $this, 'get_price_options_default' ), 10, 2 );
	}

	public function init() {
		if ( class_exists( 'YITH_WAPO_Cart' ) ) {
			remove_filter( 'woocommerce_get_cart_item_from_session', array( \YITH_WAPO_Cart::get_instance(), 'get_cart_item_from_session' ), 100, 2 );
		}
	}

	public function yay_currency_is_original_product_price( $flag, $price, $product ) {
		if ( empty( $price ) || ! $price || ! is_numeric( $price ) ) {
			$product_price_with_options = SupportHelper::get_cart_item_objects_property( $product, 'yay_yith_wapo_set_product_price_with_options' );
			if ( $product_price_with_options ) {
				$flag = false;
			}
		}
		return $flag;
	}

	public function yith_wapo_addon_select_option_args( $args, $addon ) {

		if ( Helper::default_currency_code() === $this->apply_currency['currency'] ) {
			return $args;
		}

		$price_type = isset( $args['price_type'] ) ? $args['price_type'] : false;

		if ( $price_type && 'fixed' === $args['price_type'] ) {
			$args['price'] = YayCurrencyHelper::calculate_price_by_currency( $args['price'], true, $this->apply_currency );
			if ( ! empty( $args['price_sale'] ) ) {
				$args['price_sale'] = YayCurrencyHelper::calculate_price_by_currency( $args['price_sale'], true, $this->apply_currency );
			}
		}

		return $args;
	}

	public function custom_yith_wapo_option_price( $option_price ) {
		$option_price = YayCurrencyHelper::calculate_price_by_currency( $option_price, true, $this->apply_currency );
		return $option_price;
	}

	public function custom_yith_wapo_option_price_sale( $option_price_sale ) {
		if ( ! empty( $option_price_sale ) ) {
			$option_price_sale = YayCurrencyHelper::calculate_price_by_currency( $option_price_sale, true, $this->apply_currency );
		}
		return $option_price_sale;
	}

	public function custom_yith_wapo_get_addon_price( $price, $flag, $price_method, $price_type, $product ) {
		if ( ! empty( $price ) && ( is_product() || is_singular( 'product' ) ) ) {
			$price = YayCurrencyHelper::calculate_price_by_currency( $price, true, $this->apply_currency );
		}
		return $price;
	}

	public function product_addons_set_cart_contents( $cart_contents, $cart_item_key, $cart_item, $apply_currency ) {
		if ( isset( $cart_item['yith_wapo_options'] ) && ! empty( $cart_item['yith_wapo_options'] ) ) {

			$yith_wapo_total_options_price = isset( $cart_item['yith_wapo_total_options_price'] ) && ! empty( $cart_item['yith_wapo_total_options_price'] ) ? $cart_item['yith_wapo_total_options_price'] : false;

			if ( ! $yith_wapo_total_options_price ) {
				return;
			}

			$first_free_options_count = 0;
			$currency_rate            = YayCurrencyHelper::get_rate_fee( $this->apply_currency );
			$product_id               = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
			$product_price            = SupportHelper::get_product_price( $product_id, $this->apply_currency );

			$addon_id_check = '';
			$price_options  = 0;
			$option_price   = 0;

			foreach ( $cart_item['yith_wapo_options'] as $index => $option ) {
				foreach ( $option as $key => $value ) {
					if ( $key && '' !== $value ) {
						$values = \YITH_WAPO_Premium::get_instance()->split_addon_and_option_ids( $key, $value );

						$addon_id  = $values['addon_id'];
						$option_id = $values['option_id'];

						if ( $addon_id !== $addon_id_check ) {
							$first_free_options_count = 0;
							$addon_id_check           = $addon_id;
						}

						$info = yith_wapo_get_option_info( $addon_id, $option_id );

						if ( ! apply_filters( 'yith_wapo_show_options_grouped_in_cart', true ) && 'addon_title' === $option_id ) {
							$info['addon_type'] = 'hidden';
						}

						if ( 'percentage' === $info['price_type'] ) {
							$option_percentage      = floatval( $info['price'] );
							$option_percentage_sale = floatval( $info['price_sale'] );
							$option_price           = ( $product_price / 100 ) * $option_percentage;
							$option_price_sale      = ( $product_price / 100 ) * $option_percentage_sale;
						} elseif ( 'multiplied' === $info['price_type'] ) {
							$option_price      = floatval( $info['price'] ) * (float) $value * (float) $currency_rate;
							$option_price_sale = floatval( $info['price_sale'] ) * (float) $value * (float) $currency_rate;
						} elseif ( 'characters' === $info['price_type'] ) {
							$remove_spaces     = apply_filters( 'yith_wapo_remove_spaces', false );
							$value             = $remove_spaces ? str_replace( ' ', '', $value ) : $value;
							$option_price      = floatval( $info['price'] ) * strlen( $value ) * (float) $currency_rate;
							$option_price_sale = floatval( $info['price_sale'] ) * strlen( $value ) * (float) $currency_rate;
						} else {
							$option_price      = floatval( $info['price'] ) * (float) $currency_rate;
							$option_price_sale = floatval( $info['price_sale'] ) * (float) $currency_rate;
						}

						// First X free options check.
						if ( 'yes' === $info['addon_first_options_selected'] && $first_free_options_count < $info['addon_first_free_options'] ) {
							$option_price = 0;
							++$first_free_options_count;
						} else {
							$option_price = $option_price_sale > 0 ? $option_price_sale : $option_price;
						}

						if ( in_array(
							$info['addon_type'],
							array(
								'checkbox',
								'color',
								'label',
								'radio',
								'select',
							),
							true
						) ) {
							$value = ! empty( $info['label'] ) ? $info['label'] : ( isset( $info['tooltip'] ) ? $info['tooltip'] : '' );
						} elseif ( 'product' === $info['addon_type'] ) {
							$option_product_info = explode( '-', $value );
							$option_product_id   = isset( $option_product_info[1] ) ? $option_product_info[1] : '';
							$option_product_qty  = isset( $cart_item['yith_wapo_qty_options'][ $key ] ) ? $cart_item['yith_wapo_qty_options'][ $key ] : 1;
							$option_product      = wc_get_product( $option_product_id );

							if ( $option_product && $option_product instanceof \WC_Product ) {
								// product prices.
								$product_price = $option_product->get_price();
								if ( 'product' === $info['price_method'] ) {
									$option_price = $product_price;
								} elseif ( 'discount' === $info['price_method'] ) {
									$option_discount_value = floatval( $info['price'] );
									if ( 'percentage' === $info['price_type'] ) {
										$option_price = $product_price - ( ( $product_price / 100 ) * $option_discount_value );
									} else {
										$option_price = $product_price - $option_discount_value;
									}
								}

								$option_price = $option_price * $option_product_qty;
							}
						} elseif ( 'number' === $info['addon_type'] ) {
							if ( 'value_x_product' === $info['price_method'] ) {
								$option_price = $value * $product_price;
							} elseif ( 'multiplied' === $info['price_type'] ) {
									$option_price = $value * $info['price'];
							}
						}

						if ( 'free' === $info['price_method'] ) {
							$option_price = 0;
						}

						$option_price = '' !== $option_price ? $option_price : 0;
						$option_price = \YITH_WAPO_Premium::get_instance()->calculate_price_depending_on_tax( $option_price );
					}
					// get total price options
					$price_options = $price_options + $option_price;
				}
			}

			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_wapo_price_options', (float) $price_options / YayCurrencyHelper::get_rate_fee( $this->apply_currency ) );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_yith_wapo_set_options_price', $price_options );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_yith_wapo_set_product_price_with_options_default', (float) SupportHelper::get_product_price( $product_id ) + (float) $price_options / YayCurrencyHelper::get_rate_fee( $this->apply_currency ) );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_yith_wapo_set_product_price_with_options', $product_price + $price_options );

			if ( $cart_item['yith_wapo_total_options_price'] ) {
				$price_option_3rd_default_currency = $cart_item['yith_wapo_total_options_price'];
				SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_yith_wapo_price_option_3rd_default_currency', $price_option_3rd_default_currency );
				SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_yith_wapo_price_option_3rd_current_currency', YayCurrencyHelper::calculate_price_by_currency( $price_option_3rd_default_currency, true, $this->apply_currency ) );
			}
		}
	}

	public function get_price_options_by_cart_item( $price_options, $cart_item, $product_id, $original_price, $apply_currency ) {
		$wapo_price_options = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_wapo_price_options' );
		if ( $wapo_price_options ) {
			return $wapo_price_options;
		}
		return $price_options;
	}

	public function get_price_default_in_checkout_page( $price, $product ) {
		$product_price_with_options_default = SupportHelper::get_cart_item_objects_property( $product, 'yay_yith_wapo_set_product_price_with_options_default' );
		if ( $product_price_with_options_default ) {
			return $product_price_with_options_default;
		}
		return $price;
	}

	public function get_cart_subtotal_3rd_plugin( $subtotal, $apply_currency ) {
		$subtotal      = 0;
		$cart_contents = WC()->cart->get_cart_contents();
		foreach ( $cart_contents  as $key => $cart_item ) {
			$product_price      = SupportHelper::calculate_product_price_by_cart_item( $cart_item );
			$wapo_price_options = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_wapo_price_options' );
			$price_options      = $wapo_price_options ? $wapo_price_options : 0;
			$product_subtotal   = ( $product_price + $price_options ) * $cart_item['quantity'];
			$subtotal           = $subtotal + YayCurrencyHelper::calculate_price_by_currency( $product_subtotal, false, $apply_currency );
		}

		return $subtotal;
	}

	public function yith_wapo_addon_prices_on_cart( $option_price ) {
		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $option_price;
		}
		$option_price = YayCurrencyHelper::calculate_price_by_currency( $option_price, false, $this->apply_currency );
		return $option_price;
	}

	public function get_price_with_options( $price, $product ) {
		$product_price_with_options = SupportHelper::get_cart_item_objects_property( $product, 'yay_yith_wapo_set_product_price_with_options' );
		if ( $product_price_with_options ) {
			return $product_price_with_options;
		}
		return $price;
	}

	public function get_price_options( $price_options, $product ) {
		$price_option_3rd_current_currency = SupportHelper::get_cart_item_objects_property( $product, 'yay_yith_wapo_price_option_3rd_current_currency' );
		if ( $price_option_3rd_current_currency ) {
			return $price_option_3rd_current_currency;
		}
		return $price_options;
	}

	public function get_price_options_default( $price_options_default, $product ) {
		$price_option_3rd_default_currency = SupportHelper::get_cart_item_objects_property( $product, 'yay_yith_wapo_price_option_3rd_default_currency' );
		if ( $price_option_3rd_default_currency ) {
			return $price_option_3rd_default_currency;
		}
		return $price_options_default;
	}
}
