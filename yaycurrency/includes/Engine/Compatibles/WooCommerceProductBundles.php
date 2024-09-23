<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://woocommerce.com/products/product-bundles/

class WooCommerceProductBundles {
	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! class_exists( 'WC_Bundles' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'woocommerce_cart_item_subtotal' ), 20, 3 );
		add_filter( 'woocommerce_checkout_item_subtotal', array( $this, 'woocommerce_cart_item_subtotal' ), 20, 3 );

		add_filter( 'woocommerce_bundle_container_cart_item', array( $this, 'woocommerce_bundle_container_cart_item' ), 20, 2 );
		add_filter( 'woocommerce_bundled_cart_item', array( $this, 'woocommerce_bundled_cart_item' ), 20, 2 );
		add_filter( 'yay_currency_is_change_format_order_line_subtotal', array( $this, 'is_change_format_order_line_subtotal' ), 10, 4 );
	}

	public function get_container_cart_item_subtotal( $subtotal, $cart_item, $cart_item_key, $wc_pb_display ) {
		if ( ! class_exists( 'WC_Product_Bundle' ) || ! class_exists( 'WC_PB_Product_Prices' ) ) {
			return $subtotal;
		}
		$aggregate_subtotals = \WC_Product_Bundle::group_mode_has( $cart_item['data']->get_group_mode(), 'aggregated_subtotals' );

		if ( $aggregate_subtotals ) {

			$calc_type                             = ! $wc_pb_display->display_cart_prices_including_tax() ? 'excl_tax' : 'incl_tax';
			$bundle_price                          = \WC_PB_Product_Prices::get_product_price(
				$cart_item['data'],
				array(
					'price' => $cart_item['data']->get_price(),
					'calc'  => $calc_type,
					'qty'   => $cart_item['quantity'],
				)
			);
			$bundled_cart_items                    = wc_pb_get_bundled_cart_items( $cart_item, WC()->cart->cart_contents );
			$bundled_items_price                   = 0.0;
			$bundle_subtotal_cart_item_by_currency = 0;
			foreach ( $bundled_cart_items as $bundled_cart_item ) {

				$bundled_item_id        = $bundled_cart_item['bundled_item_id'];
				$bundled_item_raw_price = $bundled_cart_item['data']->get_price();

				if ( class_exists( 'WC_Subscriptions_Product' ) && WC_PB()->compatibility->is_subscription( $bundled_cart_item['data'] ) && ! WC_PB()->compatibility->is_subscription( $cart_item['data'] ) ) {

					$bundled_item = $cart_item['data']->get_bundled_item( $bundled_item_id );

					if ( $bundled_item ) {
						$bundled_item_raw_recurring_fee = $bundled_cart_item['data']->get_price();
						$bundled_item_raw_sign_up_fee   = (float) \WC_Subscriptions_Product::get_sign_up_fee( $bundled_cart_item['data'] );
						$bundled_item_raw_price         = $bundled_item->get_up_front_subscription_price( $bundled_item_raw_recurring_fee, $bundled_item_raw_sign_up_fee, $bundled_cart_item['data'] );
					}
				}

				$bundled_item_price                     = \WC_PB_Product_Prices::get_product_price(
					$bundled_cart_item['data'],
					array(
						'price' => $bundled_item_raw_price,
						'calc'  => $calc_type,
						'qty'   => $bundled_cart_item['quantity'],
					)
				);
				$bundle_subtotal_cart_item_by_currency += isset( $bundled_cart_item['product_bunlde_discount_by_currency'] ) ? $bundled_cart_item['product_bunlde_discount_by_currency'] * $bundled_cart_item['quantity'] : 0;
				$bundled_items_price                   += wc_format_decimal( (float) $bundled_item_price, wc_pb_price_num_decimals() );
			}
			$product_subtotal = (float) $bundle_price + $bundled_items_price;
			$subtotal         = $wc_pb_display->format_subtotal( $cart_item['data'], $product_subtotal );
			if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) && 1 !== floatval( $this->apply_currency['rate'] ) ) {
				$bundle_subtotal_cart_item_by_currency += isset( $cart_item['product_bunle_container_by_currency'] ) ? $cart_item['product_bunle_container_by_currency'] : 0;
				$converted_subtotal                     = YayCurrencyHelper::calculate_custom_price_by_currency_html( $this->apply_currency, $bundle_subtotal_cart_item_by_currency );
				$converted_subtotal_html                = YayCurrencyHelper::converted_approximately_html( $converted_subtotal );
				$subtotal                              .= $converted_subtotal_html;
			}
		} elseif ( empty( $cart_item['line_subtotal'] ) ) {
			$hide_container_zero_subtotal = \WC_Product_Bundle::group_mode_has( $cart_item['data']->get_group_mode(), 'component_multiselect' );
			$subtotal                     = $hide_container_zero_subtotal ? '' : $subtotal;
		}

		return $subtotal;
	}

	public function woocommerce_cart_item_subtotal( $subtotal, $cart_item, $cart_item_key ) {
		if ( class_exists( 'WC_Bundles' ) && class_exists( 'WC_PB_Display' ) ) {
			$wc_pb_display = \WC_PB_Display::instance();
			if ( wc_pb_is_bundled_cart_item( $cart_item ) ) {
				$subtotal = $wc_pb_display->get_child_cart_item_subtotal( $subtotal, $cart_item, $cart_item_key );
			} elseif ( wc_pb_is_bundle_container_cart_item( $cart_item ) ) {
				$subtotal = $this->get_container_cart_item_subtotal( $subtotal, $cart_item, $cart_item_key, $wc_pb_display );
			}
		}
		return $subtotal;
	}

	public function woocommerce_bundle_container_cart_item( $cart_item, $bundle ) {

		if ( isset( $cart_item['stamp'] ) ) {
			$product_bunle_container_id  = isset( $cart_item['variation_id'] ) && ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
			$product_bunle_container_obj = $cart_item['data'];

			$price_original = SupportHelper::get_product_price( $product_bunle_container_id );

			$product_bunle_container_price = SupportHelper::get_product_price( $product_bunle_container_id, $this->apply_currency );

			$product_bunle_container_obj->product_bunle_container_by_currency = $product_bunle_container_price;
			$product_bunle_container_obj->product_bunle_container_by_default  = $price_original;
			$product_bunle_container_obj->is_product_bunle_container          = true;
			$cart_item['product_bunle_container_by_currency']                 = $product_bunle_container_price;
			$cart_item['product_bunle_container_by_default']                  = $price_original;
			$cart_item['is_product_bunle_container']                          = true;
		}

		return $cart_item;
	}

	public function woocommerce_bundled_cart_item( $cart_item, $bundle ) {
		$bundle_stamp_by_cart_item = isset( $cart_item['stamp'] ) ? $cart_item['stamp'] : false;
		if ( $bundle_stamp_by_cart_item ) {
			$product_obj                      = $cart_item['data'];
			$variation_id                     = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
			$product_id                       = $cart_item['product_id'];
			$filtered_product_bundle_selected = array_filter(
				$bundle_stamp_by_cart_item,
				function ( $bundle_item ) use ( $variation_id, $product_id ) {
					if ( ! isset( $bundle_item['variation_id'] ) && ! $variation_id ) {
						if ( $bundle_item['product_id'] === $product_id ) {
							return true;
						}
					} elseif ( $bundle_item['product_id'] === $product_id && isset( $bundle_item['variation_id'] ) && intval( $bundle_item['variation_id'] ) === $variation_id ) {
							return true;
					}

					return false;
				}
			);
			if ( $filtered_product_bundle_selected ) {
				if ( ! empty( $cart_item['line_subtotal'] ) ) {
					$product_bundle_settings          = array_shift( $filtered_product_bundle_selected );
					$product_bundle_id                = isset( $product_bundle_settings['variation_id'] ) ? intval( $product_bundle_settings['variation_id'] ) : $product_bundle_settings['product_id'];
					$price_original                   = SupportHelper::get_product_price( $product_bundle_id );
					$product_bundle_price             = SupportHelper::get_product_price( $product_bundle_id, $this->apply_currency );
					$discount_value                   = isset( $product_bundle_settings['discount'] ) && ! empty( $product_bundle_settings['discount'] ) ? $product_bundle_settings['discount'] : false;
					$product_bunlde_discount          = $discount_value ? $product_bundle_price - ( $discount_value / 100 ) * $product_bundle_price : $product_bundle_price;
					$product_bundle_original_discount = $discount_value ? $price_original - ( $discount_value / 100 ) * $price_original : $price_original;
				} else {
					$product_bunlde_discount          = 0;
					$product_bundle_original_discount = 0;
				}

				$product_obj->product_bunlde_discount_by_currency = $product_bunlde_discount;
				$product_obj->product_bunlde_discount_by_default  = $product_bundle_original_discount;
				$cart_item['product_bunlde_discount_by_currency'] = $product_bunlde_discount;
				$cart_item['product_bunlde_discount_by_default']  = $product_bundle_original_discount;
			}
		}

		return $cart_item;
	}

	public function is_change_format_order_line_subtotal( $flag, $subtotal, $item, $order ) {

		if ( function_exists( 'wc_pb_is_bundle_container_order_item' ) && wc_pb_is_bundle_container_order_item( $item ) ) {
			$flag = false;
		}

		return $flag;

	}
}
