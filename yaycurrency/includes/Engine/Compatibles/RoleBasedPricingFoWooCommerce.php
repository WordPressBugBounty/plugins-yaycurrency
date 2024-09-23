<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class RoleBasedPricingFoWooCommerce {
	use SingletonTrait;

	private $apply_currency = array();
	private $afc_sp_price;
	public function __construct() {

		if ( ! class_exists( 'AF_C_S_P_Price' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();
		$this->afc_sp_price   = new \AF_C_S_P_Price();
		add_filter( 'woocommerce_get_price_html', array( $this, 'custom_woocommerce_get_price_html' ), 200, 2 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'custom_woocommerce_cart_item_price' ), 20, 3 );
	}

	public function custom_woocommerce_get_price_html( $price_html, $product ) {

		if ( Helper::default_currency_code() === $this->apply_currency['currency'] ) {
			return $price_html;
		}

		$user      = false;
		$user_role = 'guest';

		if ( $this->afc_sp_price->is_product_price_hidden( $product, $user, $user_role ) ) {
			$cps_price_text = get_option( 'csp_price_text' );
			return $cps_price_text;
		}

		$has_price = $this->afc_sp_price->have_price_of_product( $product, $user, $user_role );

		if ( ! $has_price ) {
			return $price_html;
		}

		$replace_original = $this->afc_sp_price->is_replace_price( $product, $user, $user_role );

		switch ( $product->get_type() ) {
			case 'simple':
			case 'variation':
				$price_html = $this->get_product_price_html( $product, $replace_original );
				break;
			case 'variable':
				$price_html = $this->get_variable_product_price_html( $product, $replace_original );
				break;
			case 'grouped':
				$price_html = $this->get_grouped_product_price_html( $product, $replace_original );
				break;
			default:
				break;
		}

		return $price_html;
	}

	public function get_product_price_html( $product, $replace_original ) {

		$tax_display_mode = get_option( 'woocommerce_tax_display_shop' );

		$role_based_price = $this->afc_sp_price->get_price_of_product( $product );
		$price            = ! is_bool( $role_based_price ) ? floatval( $role_based_price ) : $role_based_price;

		if ( ! empty( $price ) ) {
			$active_price = 'incl' === $tax_display_mode ? wc_get_price_including_tax(
				$product,
				array(
					'qty'   => 1,
					'price' => $price,
				)
			) : wc_get_price_excluding_tax(
				$product,
				array(
					'qty'   => 1,
					'price' => $price,
				)
			);
		} else {
			$active_price = 'incl' === $tax_display_mode ? wc_get_price_including_tax( $product ) : wc_get_price_excluding_tax( $product );
		}

		$args          = array( 'price' => $product->get_regular_price() );
		$regular_price = 'incl' === $tax_display_mode ? wc_get_price_including_tax( $product, $args ) : wc_get_price_excluding_tax( $product, $args );
		$active_price  = YayCurrencyHelper::calculate_price_by_currency( $active_price, false, $this->apply_currency );

		if ( $active_price >= $regular_price ) {
			return YayCurrencyHelper::format_price( $active_price ) . $product->get_price_suffix();
		}

		if ( $replace_original ) {
			return YayCurrencyHelper::format_price( $active_price ) . $product->get_price_suffix();
		}

		return YayCurrencyHelper::format_sale_price( $regular_price, $active_price ) . $product->get_price_suffix();
	}

	public function get_variable_product_price_html( $product, $replace_original ) {
		$tax_display_mode = get_option( 'woocommerce_tax_display_shop' );
		$child_prices     = array();
		$children         = array_map( 'wc_get_product', $product->get_visible_children() );

		foreach ( $children as $child ) {
			if ( '' !== $child->get_price() ) {
				// Get the discounted price.
				$role_based_price = $this->afc_sp_price->get_price_of_product( $child );
				$price            = ! is_bool( $role_based_price ) ? floatval( $role_based_price ) : $role_based_price;
				if ( ! empty( $price ) ) {
					$child_prices[] = 'incl' === $tax_display_mode ? wc_get_price_including_tax(
						$child,
						array(
							'qty'   => 1,
							'price' => $price,
						)
					) : wc_get_price_excluding_tax(
						$child,
						array(
							'qty'   => 1,
							'price' => $price,
						)
					);
				} else {
					$child_prices[] = 'incl' === $tax_display_mode ? wc_get_price_including_tax( $child ) : wc_get_price_excluding_tax( $child );
				}

				$args = array( 'price' => $child->get_regular_price() );

				$child_prices_regular[] = 'incl' === $tax_display_mode ? wc_get_price_including_tax( $child, $args ) : wc_get_price_excluding_tax( $child, $args );
			}
		}

		if ( ! empty( $child_prices ) ) {
			$min_price         = min( $child_prices );
			$max_price         = max( $child_prices );
			$max_price_regular = max( $child_prices_regular );
		} else {
			$min_price         = '';
			$max_price         = '';
			$max_price_regular = '';
		}

		$min_price = YayCurrencyHelper::calculate_price_by_currency( $min_price, false, $this->apply_currency );
		$max_price = YayCurrencyHelper::calculate_price_by_currency( $max_price, false, $this->apply_currency );

		if ( $min_price === $max_price ) {

			if ( $replace_original ) {
				return YayCurrencyHelper::format_price( $max_price ) . $product->get_price_suffix();
			}

			$max_price_regular = YayCurrencyHelper::calculate_price_by_currency( $max_price_regular, false, $this->apply_currency );
			return YayCurrencyHelper::format_sale_price( $max_price_regular, $max_price ) . $product->get_price_suffix();

		}

		return wc_format_price_range( $min_price, $max_price );
	}

	public function get_grouped_product_price_html( $product, $replace_original ) {
		$price            = '';
		$tax_display_mode = get_option( 'woocommerce_tax_display_shop' );
		$child_prices     = array();
		$children         = array_filter( array_map( 'wc_get_product', $product->get_children() ), 'wc_products_array_filter_visible_grouped' );

		foreach ( $children as $child ) {
			if ( '' !== $child->get_price() ) {

				$role_based_price = $this->afc_sp_price->get_price_of_product( $child );
				$price            = ! is_bool( $role_based_price ) ? floatval( $role_based_price ) : $role_based_price;
				if ( ! empty( $price ) ) {
					$child_prices[] = 'incl' === $tax_display_mode ? wc_get_price_including_tax(
						$child,
						array(
							'qty'   => 1,
							'price' => $price,
						)
					) : wc_get_price_excluding_tax(
						$child,
						array(
							'qty'   => 1,
							'price' => $price,
						)
					);
				} else {
					$child_prices[] = 'incl' === $tax_display_mode ? wc_get_price_including_tax( $child ) : wc_get_price_excluding_tax( $child );
				}

				$max_price_regular = empty( $max_price_regular ) || $child->get_regular_price() > $max_price_regular ? $child->get_regular_price() : $max_price_regular;
			}
		}

		if ( ! empty( $child_prices ) ) {
			$min_price = min( $child_prices );
			$max_price = max( $child_prices );
		} else {
			$min_price = '';
			$max_price = '';
		}

		if ( '' !== $min_price ) {
			$min_price = YayCurrencyHelper::calculate_price_by_currency( $min_price, false, $this->apply_currency );
			$max_price = YayCurrencyHelper::calculate_price_by_currency( $max_price, false, $this->apply_currency );
			if ( $min_price !== $max_price ) {
				$price = wc_format_price_range( $min_price, $max_price );
			} elseif ( $replace_original ) {

					$price = YayCurrencyHelper::format_price( $min_price );
			} else {
				$max_price_regular = YayCurrencyHelper::calculate_price_by_currency( $max_price_regular, false, $this->apply_currency );
				$price             = YayCurrencyHelper::format_sale_price( $max_price_regular, $max_price );
			}
		}

		return $price . $product->get_price_suffix();
	}

	public function custom_woocommerce_cart_item_price( $price, $cart_item, $cart_item_key ) {

		if ( 0 !== intval( $cart_item['variation_id'] ) ) {
			$product_id = $cart_item['variation_id'];
		} else {
			$product_id = $cart_item['product_id'];
		}
		$user       = wp_get_current_user();
		$user_roles = (array) $user->roles;
		$_product   = wc_get_product( $product_id );
		if ( empty( $user_roles ) ) {
			$user_role        = 'guest';
			$role_based_price = $this->afc_sp_price->get_price_of_product( $_product, $user, $user_role, $cart_item['quantity'] );
			$new_price        = ! is_bool( $role_based_price ) ? floatval( $role_based_price ) : $role_based_price;
		} else {
			foreach ( $user_roles as $user_role ) {
				$role_based_price = $this->afc_sp_price->get_price_of_product( $_product, $user, $user_role, $cart_item['quantity'] );
				$new_price        = ! is_bool( $role_based_price ) ? floatval( $role_based_price ) : $role_based_price;
				if ( $price ) {
					break;
				}
			}
		}

		if ( ! empty( $new_price ) ) {
			$new_price = YayCurrencyHelper::calculate_price_by_currency( $new_price, false, $this->apply_currency );
			return YayCurrencyHelper::format_price( $new_price );
		}

		return $price;
	}
}
