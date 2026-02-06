<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\SupportHelper;
use Barn2\Plugin\WC_Wholesale_Pro\Controller\Wholesale_Price;
use Barn2\Plugin\WC_Wholesale_Pro\Util;
defined( 'ABSPATH' ) || exit;

class Barn2WooCommerceWholesalePro {

	use SingletonTrait;

	private $apply_currency  = array();
	private $currencies_data = array();
	private $wdm_plugin      = false;
	public function __construct() {

		if ( ! class_exists( 'Barn2\Plugin\WC_Wholesale_Pro\Controller\Wholesale_Price' ) ) {
			return;
		}

		$this->apply_currency          = YayCurrencyHelper::detect_current_currency();
		$is_dis_checkout_diff_currency = YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency );

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

		add_filter( 'wcwp_calculated_wholesale_price', array( $this, 'calculated_wholesale_price' ), 10, 3 );
		add_filter( 'wcwp_calculated_wholesale_sale_price', array( $this, 'calculated_wholesale_sale_price' ), 10, 3 );

		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'yay_get_price_with_options' ), 10, 2 );

		if ( $is_dis_checkout_diff_currency ) {
			$this->currencies_data = YayCurrencyHelper::get_current_and_fallback_currency( $this->apply_currency, YayCurrencyHelper::converted_currency() );
		}

		// Recalculate
		add_filter( 'YayCurrency/Barn2/GetProductPricing', array( $this, 'custom_get_product_price' ), 10, 2 );
		add_filter( 'YayCurrency/Barn2/GetCategoryPricing', array( $this, 'custom_get_category_product_price' ), 10, 2 );
		add_filter( 'YayCurrency/Barn2/GetGlobalPricing', array( $this, 'custom_get_global_product_price' ), 10, 2 );

		add_filter( 'YayCurrency/ApplyCurrency/ByCartItem/GetProductPrice', array( $this, 'get_product_price_by_cart_item' ), 10, 3 );

		if ( function_exists( 'Barn2\Plugin\Discount_Manager\wdm' ) ) {
			$this->wdm_plugin = true;
			add_filter( 'yay_currency_before_calculate_totals_ignore_price_conversion', array( $this, 'before_calculate_totals_ignore_price_conversion' ), 10, 3 );
			if ( class_exists( 'Barn2\Plugin\Discount_Manager\Integrations\Product_Options' ) ) {
				add_filter( 'YayCurrency/StoreCurrency/ByCartItem/GetProductPrice', array( $this, 'get_product_price_default_by_cart_item' ), 10, 2 );
				add_filter( 'woocommerce_cart_item_price', array( $this, 'display_discounted_price_in_cart' ), 999, 3 );

				if ( YayCurrencyHelper::is_dis_checkout_diff_currency( $this->apply_currency ) ) {
					add_filter( 'wdm_cart_total_discount_amount_output', array( $this, 'wdm_cart_total_discount_amount_output' ), 999, 2 );
				}
			}
		}

		if ( function_exists( 'Barn2\Plugin\WC_Wholesale_Pro\woocommerce_wholesale_pro' ) ) {
			add_filter( 'woocommerce_get_price_html', array( $this, 'woocommerce_get_price_html' ), 100, 2 );
		}
		// manual defined on WooCommerce Discount Manager - Barn2
		add_filter( 'wdm_wwp_discounted_price', array( $this, 'wdm_wwp_discounted_price' ), 10, 3 );
	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {
		$wholesale_price = $this->get_wholesale_price( $cart_item['data'] );
		if ( $wholesale_price ) {
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_wholesale_price_default', $wholesale_price );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_currency_wholesale_price_currency', YayCurrencyHelper::calculate_price_by_currency( $wholesale_price, false, $apply_currency ) );
		}
	}

	// Recalculate
	public function custom_get_product_price( $product, $role ) {
		if ( $role->get_product_pricing() !== 'yes' ) {
			return false;
		}

		$product_price = $product->get_meta( $role->get_name() );

		if ( ! is_numeric( $product_price ) ) {
			return false;
		}

		return (float) $product_price;
	}

	public function custom_get_category_product_price( $product, $role ) {
		$product_id = $product->get_id();
		$price      = (float) get_post_meta( $product_id, '_regular_price', true );
		if ( ! is_numeric( $price ) ) {
			return false;
		}

		$id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

		$categories = get_the_terms( $id, 'product_cat' );

		if ( ! $categories || empty( $categories ) ) {
			return false;
		}

		$discounts = array();

		// Grab our discounts
		foreach ( $categories as $category ) {
			$category_discount = get_term_meta( $category->term_id, $role->get_name(), true );

			if ( ! $category_discount ) {
				continue;
			}

			$discounts[ $category->name ] = $category_discount;
		}

		// Bail if none set
		if ( empty( $discounts ) ) {
			return false;
		}

		// Get the highest discount if we have multiple categories assigned
		$discount = max( $discounts );

		// Calculate the price
		$price = round( $price - ( $price * ( $discount / 100 ) ), 2 );

		return $price;
	}

	public function custom_get_global_product_price( $product, $role ) {

		$product_id = $product->get_id();
		$price      = (float) get_post_meta( $product_id, '_regular_price', true );

		if ( ! $role->get_global_discount() || ! is_numeric( $price ) ) {
			return false;
		}

		$discount = $role->get_global_discount();

		$price = round( $price - ( $price * ( $discount / 100 ) ), 2 );

		return $price;
	}

	public function calculate_product_price( $product, $role ) {

		$product_price = apply_filters( 'YayCurrency/Barn2/GetProductPricing', $product, $role );
		if ( is_numeric( $product_price ) ) {
			return $product_price;
		} else {
			$category_price = apply_filters( 'YayCurrency/Barn2/GetCategoryPricing', $product, $role );
			if ( is_numeric( $category_price ) ) {
				return $category_price;
			} else {
				$global_price = apply_filters( 'YayCurrency/Barn2/GetGlobalPricing', $product, $role );
				if ( is_numeric( $global_price ) ) {
					return $global_price;
				}
			}
		}

		return false;

	}

	public function yay_get_price_with_options( $price, $product ) {
		$wholesale_price_currency = SupportHelper::get_cart_item_objects_property( $product, 'yay_currency_wholesale_price_currency' );
		if ( $wholesale_price_currency ) {
			return $wholesale_price_currency;
		}
		return false;
	}

	public function get_product_price_by_cart_item( $price, $cart_item, $apply_currency ) {
		$wholesale_price_currency = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_currency_wholesale_price_currency' );
		if ( $wholesale_price_currency ) {
			$fallback_currency = isset( $this->currencies_data['fallback_currency'] ) ? $this->currencies_data['fallback_currency'] : false;
			if ( $fallback_currency && Helper::default_currency_code() !== $fallback_currency['currency'] ) {
				$wholesale_price_default = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_currency_wholesale_price_default' );
				if ( $wholesale_price_default ) {
					$price = YayCurrencyHelper::calculate_price_by_currency( $wholesale_price_default, false, $fallback_currency );
				}
			} else {
				return $wholesale_price_currency;
			}
		}

		return $price;
	}

	public function get_wholesale_price( $product ) {
		if ( ! is_user_logged_in() || Util::is_wholesale_login_page() ) {
			return false;
		}

		$wholesale_role = Util::get_current_user_wholesale_role_object();

		if ( ! $wholesale_role ) {
			return false;
		}

		$wholesale_price = new Wholesale_Price( $product, $wholesale_role );
		$product_price   = $wholesale_price->get_price();

		if ( ! $product_price ) {
			return false;
		}

		$sale_price = $wholesale_price->get_sale_price();

		// Avoid Transient Cache...
		$wholesale_price = get_transient( $wholesale_role->get_name() . '_' . $product->get_id() );
		if ( $wholesale_price ) {
			$calculate_product_price = $this->calculate_product_price( $product, $wholesale_role );
			if ( ! $calculate_product_price ) {
				$product_price = SupportHelper::get_product_price( $product->get_id() );
				return $product_price;
			}
			$calculate_product_sale_price = $this->calculate_product_sale_price( $product, $wholesale_role );
			return $calculate_product_sale_price ? $calculate_product_sale_price : $calculate_product_price;
		}

		return is_numeric( $sale_price ) ? $sale_price : $product_price;
	}

	public function calculate_product_sale_price( $product, $role ) {
		$sale_price = $this->get_product_sale_pricing( $product, $role );
		if ( ! is_numeric( $sale_price ) ) {
			if ( 'yes' === $role->get_apply_to_sale_price() ) {
				$original_sale_price = $product->get_sale_price( 'edit' );
				if ( is_numeric( $original_sale_price ) ) {
					$discount = $role->get_global_discount() ? $role->get_global_discount() : 0;
					$price    = (string) round( (float) $original_sale_price * ( 1 - ( $discount / 100 ) ), wc_get_price_decimals() );
					return $price;
				}
			}
		}
		return false;
	}

	public function calculated_wholesale_price( $price, $product, $role ) {

		if ( doing_filter( 'wcwp_calculated_wholesale_sale_price' ) || SupportHelper::detect_original_product_price( false, $price, $product ) ) {
			return $price;
		}

		// Avoid Transient Cache...
		$wholesale_price = get_transient( $role->get_name() . '_' . $product->get_id() );
		if ( $wholesale_price ) {
			$price = $this->calculate_product_price( $product, $role );
		}

		if ( ! $price ) {
			$price = SupportHelper::get_product_price( $product->get_id() );
		}

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $price;
		}

		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		return $price;

	}

	public function get_product_sale_pricing( $product, $role ) {
		if ( 'yes' !== $role->get_product_sale_pricing() ) {
			return '';
		}

		$product_sale_price = $product->get_meta( $role->get_name() . '_sale' );

		if ( ! is_numeric( $product_sale_price ) ) {
			return '';
		}

		return $product_sale_price;
	}

	public function calculated_wholesale_sale_price( $price, $product, $role ) {

		if ( SupportHelper::detect_original_product_price( false, $price, $product ) ) {
			return $price;
		}

		$sale_price    = $this->get_product_sale_pricing( $product, $role );
		$regular_price = $this->calculated_wholesale_price( $price, $product, $role );
		if ( ! is_numeric( $sale_price ) ) {

			if ( 'yes' === $role->get_apply_to_sale_price() ) {
				$original_sale_price = $product->get_sale_price( 'edit' );

				if ( is_numeric( $original_sale_price ) ) {
					$discount = $role->get_global_discount() ? $role->get_global_discount() : 0;
					$price    = (string) round( (float) $original_sale_price * ( 1 - ( $discount / 100 ) ), wc_get_price_decimals() );
					if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
						return $price;
					}
					$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
					return $price;
				}
			} else {
				$price = $regular_price;
			}
		}
		return $price;

	}

	public function before_calculate_totals_ignore_price_conversion( $flag, $price, $product ) {

		if ( $this->wdm_plugin ) {
			$flag = true;
		}
		return $flag;
	}

	private function check_discounted_price_exists( $cart_item ) {
		if ( ! function_exists( '\Barn2\Plugin\Discount_Manager\wdm' ) ) {
			return false;
		}

		$product_id  = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
		$discount_id = \Barn2\Plugin\Discount_Manager\wdm()->cache()->get_tracked_discount_id_by_product( $product_id );
		if ( $discount_id ) {
			return true;
		}
		return false;
	}

	public function get_product_price_default_by_cart_item( $price, $cart_item ) {
		if ( self::check_discounted_price_exists( $cart_item ) && isset( $cart_item['_wdm']['new_price'] ) ) {
			$price = $cart_item['_wdm']['new_price'];
		}
		return $price;
	}

	public function display_discounted_price_in_cart( $price, $cart_item, $cart_item_key ) {
		if ( self::check_discounted_price_exists( $cart_item ) ) {
			$original_price = apply_filters( 'yay_currency_convert_price', $cart_item['_wdm']['original_price'], $this->apply_currency );
			$sale_price     = apply_filters( 'yay_currency_convert_price', $cart_item['_wdm']['new_price'], $this->apply_currency );
			$price          = wc_format_sale_price( $original_price, $sale_price );
		}

		return $price;

	}

	public function wdm_cart_total_discount_amount_output( $output, $discount_total ) {

		if ( ! is_checkout() ) {
			return $output;
		}
		$converted_approximately = SupportHelper::display_approximately_converted_price( $this->apply_currency );
		if ( ! $converted_approximately ) {
			return $output;
		}
		$label                  = __( 'Total savings', 'woocommerce-discount-manager' );
		$convert_discount_total = YayCurrencyHelper::calculate_price_by_currency_html( $this->apply_currency, $discount_total );
		$converted_total_html   = YayCurrencyHelper::converted_approximately_html( $convert_discount_total, true );
		$html_content           = wc_price( $discount_total ) . $converted_total_html;
		$output                 = '<tr><th>' . $label . '</th><td data-title="' . $label . '">' . wp_kses_post( $html_content ) . '</td></tr>';
		return $output;
	}

	public function woocommerce_get_price_html( $price_html, $product ) {
		// Barn2 WooCommerce Quick View Pro
		if ( ! doing_filter( 'wc_quick_view_pro_quick_view_product_details' ) ) {
			return $price_html;
		}

		$price_handler = \Barn2\Plugin\WC_Wholesale_Pro\woocommerce_wholesale_pro()->get_service( 'price_handler' );
		$price_html    = $price_handler->get_price_html( $price_html, $product );
		return $price_html;
	}

	public function wdm_wwp_discounted_price( $discounted_price, $product, $role ) {
		$discounted_price = apply_filters( 'yay_currency_convert_price', $discounted_price, $this->apply_currency );
		return $discounted_price;
	}
}
