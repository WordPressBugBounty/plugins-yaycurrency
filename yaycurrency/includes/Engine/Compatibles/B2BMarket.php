<?php

namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://marketpress.com/shop/plugins/woocommerce/b2b-market/

class B2BMarket {

	use SingletonTrait;

	private $apply_currency = array();

	public function __construct() {

		if ( ! class_exists( 'BM' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

		add_filter( 'bm_filter_rrp_price', array( $this, 'bm_filter_rrp_price' ), 10, 2 );
		add_filter( 'bm_filter_listable_bulk_prices', array( $this, 'bm_filter_listable_bulk_prices' ), 10, 1 );
		add_filter( 'bm_filter_bulk_price_dynamic_generate_first_row', array( $this, 'bm_filter_bulk_price_dynamic_generate_first_row' ), 10, 5 );
		add_filter( 'bm_filter_cheapest_bulk_price', array( $this, 'bm_filter_cheapest_bulk_price' ), 10, 1 );

		add_filter( 'bm_filter_bulk_discount_string', array( $this, 'bm_filter_bulk_discount_string' ), 10, 5 );
		add_filter( 'bm_filter_bulk_price_discount_value', array( $this, 'bm_filter_bulk_price_discount_value' ), 10, 3 );

		add_filter( 'bm_filter_get_price', array( $this, 'bm_filter_get_price' ), 10, 2 );

		// Convert Regular and Sale Price --- get discount in Cart page
		$product_price_regular_sale_hooks = array( 'woocommerce_product_get_regular_price', 'woocommerce_product_variation_get_regular_price', 'woocommerce_variation_prices_regular_price', 'woocommerce_product_get_sale_price', 'woocommerce_product_variation_get_sale_price', 'woocommerce_variation_prices_sale_price', 'woocommerce_variation_prices_price' );
		foreach ( $product_price_regular_sale_hooks as $product_price_hook ) {
			add_filter( $product_price_hook, array( $this, 'convert_product_get_regular_sale_price' ), 10, 2 );
		}

		add_filter( 'woocommerce_product_get_price', array( $this, 'convert_product_get_price' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'convert_product_get_price' ), 10, 2 );

		add_filter( 'bm_filter_update_price_response', array( $this, 'custom_update_price_response' ), 10, 3 );

		add_filter( 'yay_currency_get_product_price_by_cart_item', array( $this, 'get_product_price_by_cart_item' ), 10, 3 );

	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $apply_currency ) ) {
			$product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
			$product    = wc_get_product( $product_id );
			$quantity   = $cart_item['quantity'];

			$current_price_by_currency = $this->calculate_current_price_by_currency( $product, $quantity, $apply_currency );
			if ( $current_price_by_currency ) {
				SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_b2b_price_by_currency', $current_price_by_currency );
			}

			$current_price = $this->calculate_current_price_by_currency( $product, $quantity );
			if ( $current_price ) {
				SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_b2b_price_default', $current_price );
			}
		}

	}

	public function calculate_group_prices( $group_prices, $price, $product_id, $parent_id, $apply_currency = false ) {
		$prices = array();

		// calculate group prices and add them to $prices.
		if ( ! empty( $group_prices ) ) {
			foreach ( $group_prices as $price_entries ) {
				if ( is_array( $price_entries ) ) {
					foreach ( $price_entries as $price_data ) {
						// no price skip entry.
						if ( empty( $price_data['group_price'] ) ) {
							continue;
						}

						$type     = empty( $price_data['group_price_type'] ) ? 'fix' : $price_data['group_price_type'];
						$category = empty( $price_data['group_price_category'] ) ? 0 : \BM_Helper::get_translated_object_ids( $price_data['group_price_category'], 'category' );
						// check for category restriction before calculating.
						if ( $category > 0 ) {
							// if it's a variation we need to check for the parent id.
							if ( $parent_id > 0 ) {
								if ( ! has_term( $category, 'product_cat', $parent_id ) && ! \BM_Price::product_in_descendant_category( $category, $parent_id ) ) {
									continue;
								}
							} elseif ( ! has_term( $category, 'product_cat', $product_id ) && ! \BM_Price::product_in_descendant_category( $category, $product_id ) ) {
									continue;
							}
						}

						// ensure price is float.
						$group_price = floatval( $price_data['group_price'] );
						$group_price = $apply_currency ? YayCurrencyHelper::calculate_price_by_currency( $group_price, false, $apply_currency ) : $group_price;
						// check type, calculate price and add them to prices.
						if ( $group_price > 0 ) {
							switch ( $type ) {
								case 'fix':
									$prices[] = $group_price;
									break;
								case 'discount':
									$group_price = $price - $group_price;
									if ( $group_price > 0 ) {
										$prices[] = $group_price;
									}
									break;
								case 'discount-percent':
									if ( is_numeric( $group_price ) && is_numeric( $price ) ) {
										$group_price = $price - ( $group_price * $price / 100 );
										if ( $group_price > 0 ) {
											$prices[] = $group_price;
										}
									}
									break;
							}
						}
					}
				}
			}
		}

		return $prices;
	}

	public function calculate_bulk_prices( $prices, $bulk_prices, $price, $product_id, $parent_id, $qty, $apply_currency = false ) {
		if ( ! empty( $bulk_prices ) ) {
			foreach ( $bulk_prices as $price_entries ) {
				if ( is_array( $price_entries ) ) {
					foreach ( $price_entries as $price_data ) {
						// no price skip.
						if ( empty( $price_data['bulk_price'] ) || empty( $price_data['bulk_price_from'] ) ) {
							continue;
						}
						$type     = empty( $price_data['bulk_price_type'] ) ? 'fix' : $price_data['bulk_price_type'];
						$to       = 0 === intval( $price_data['bulk_price_to'] ) ? INF : intval( $price_data['bulk_price_to'] );
						$category = empty( $price_data['bulk_price_category'] ) ? 0 : \BM_Helper::get_translated_object_ids( $price_data['bulk_price_category'], 'category' );

						// check for category restriction before calculating.
						if ( $category > 0 ) {
							// if it's a variation we need to check for the parent id.
							if ( $parent_id > 0 ) {
								if ( ! has_term( $category, 'product_cat', $parent_id ) && ! \BM_Price::product_in_descendant_category( $category, $parent_id ) ) {
									continue;
								}
							} elseif ( ! has_term( $category, 'product_cat', $product_id ) && ! \BM_Price::product_in_descendant_category( $category, $product_id ) ) {
									continue;
							}
						}

						$bulk_price = floatval( $price_data['bulk_price'] );
						$bulk_price = $apply_currency ? YayCurrencyHelper::calculate_price_by_currency( $bulk_price, false, $apply_currency ) : $bulk_price;
						$from       = intval( $price_data['bulk_price_from'] );

						if ( $bulk_price > 0 ) {
							switch ( $type ) {
								case 'fix':
									// add to prices if matched qty.
									if ( ( $qty >= $from ) && ( $qty <= $to ) ) {
										$prices[] = $bulk_price;
									}
									break;

								case 'discount':
									$bulk_price = $price - $bulk_price;
									// add to prices if matched qty.
									if ( ( $qty >= $from ) && ( $qty <= $to ) && $bulk_price > 0 ) {
										$prices[] = $bulk_price;
									}
									break;

								case 'discount-percent':
									$bulk_price = $price - ( $bulk_price * $price / 100 );
									// add to prices if matched qty.
									if ( ( $qty >= $from ) && ( $qty <= $to ) && $bulk_price > 0 ) {
										$prices[] = $bulk_price;
									}
									break;
							}
						}
					}
				}
			}
		}

		return $prices;

	}

	public function get_current_price_by_apply_currency( $price, $product, $qty, $apply_currency = false ) {

		$product_id = $product->get_id();
		$group_id   = \BM_Conditionals::get_validated_customer_group();

		if ( empty( $group_id ) ) {
			return $price;
		}

		// Force and return product price if filter is set.
		if ( apply_filters( 'bm_force_product_price', false, $product_id, $group_id ) ) {
			return $price;
		}

		// filter for manipulating prices.
		if ( apply_filters( 'bm_use_regular_for_group_price', false ) ) {
			$regular = get_post_meta( $product_id, '_regular_price', true );
			$regular = $apply_currency ? YayCurrencyHelper::calculate_price_by_currency( $regular, false, $apply_currency ) : $regular;
			$price   = $regular;
		}

		// check if it's a variation.
		$parent_id = 'variation' === $product->get_type() ? $product->get_parent_id() : 0;

		// Save group slug and product data to reduce requests.
		$group_slug          = \BM_Helper::get_group_slug( $group_id );
		$fallback_group_id   = get_option( 'bm_fallback_customer_group' );
		$fallback_group_slug = \BM_Helper::get_group_slug( $fallback_group_id );

		// get group prices from product and customer group.
		$group_prices = apply_filters(
			'bm_group_prices',
			array(
				'global'        => get_post_meta( $fallback_group_id, 'bm_group_prices', true ),
				'group'         => get_post_meta( $group_id, 'bm_group_prices', true ),
				'product'       => get_post_meta( $product_id, 'bm_' . $group_slug . '_group_prices', true ),
				'all_customers' => get_post_meta( $product_id, 'bm_' . $fallback_group_slug . '_group_prices', true ),
			)
		);

		$prices = $this->calculate_group_prices( $group_prices, $price, $product_id, $parent_id, $apply_currency );

		// calculate bulk prices and add them to $prices.
		$bulk_prices = apply_filters(
			'bm_bulk_prices',
			array(
				'global'        => get_post_meta( $fallback_group_id, 'bm_bulk_prices', true ),
				'group'         => get_post_meta( $group_id, 'bm_bulk_prices', true ),
				'product'       => get_post_meta( $product_id, 'bm_' . $group_slug . '_bulk_prices', true ),
				'all_customers' => get_post_meta( $product_id, 'bm_' . $fallback_group_slug . '_bulk_prices', true ),
			)
		);

		$prices = $this->calculate_bulk_prices( $prices, $bulk_prices, $price, $product_id, $parent_id, $qty, $apply_currency );

		// If not apply Discount
		if ( ! $prices ) {
			return false;
		}

		// add the original price.
		$prices[] = $price;

		// get the cheapest price from array.
		$price = min( $prices );

		return $price;

	}

	public function calculate_current_price_by_currency( $product, $quantity, $apply_currency = false ) {
		$current_price = false;
		if ( $product->get_price() > 0 ) {
			// setup prices to compare.
			$product_price = $product->get_price( 'edit' );
			if ( $apply_currency ) {
				$product_price = YayCurrencyHelper::calculate_price_by_currency( $product_price, false, $apply_currency );
			}
			$current_price = $this->get_current_price_by_apply_currency( $product_price, $product, $quantity, $apply_currency );
		}
		return $current_price;
	}

	public function get_product_price_fixed_3rd_plugin( $fixed_product_price, $product, $apply_currency ) {
		$b2b_price_by_currency = SupportHelper::get_cart_item_objects_property( $product, 'yay_b2b_price_by_currency' );
		if ( $b2b_price_by_currency ) {
			return $b2b_price_by_currency;
		}
		return $fixed_product_price;
	}

	public function get_product_price_by_cart_item( $price, $cart_item, $apply_currency ) {
		$b2b_price_by_currency = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_b2b_price_by_currency' );
		if ( $b2b_price_by_currency ) {
			return $b2b_price_by_currency;
		}
		return $price;
	}

	public function bm_filter_bulk_discount_string( $result_string, $cheapest_bulk_price, $cheapest_bulk_price_qty, $bulk_price_message, $product ) {

		if ( ! class_exists( 'BM_Tax' ) ) {
			return $result_string;
		}

		$cheapest_bulk_price = \BM_Tax::get_tax_price( $product, $cheapest_bulk_price );
		if ( $cheapest_bulk_price > 0 ) {
			$converted_price    = YayCurrencyHelper::calculate_price_by_currency( $cheapest_bulk_price, false, $this->apply_currency );
			$converted_price    = YayCurrencyHelper::format_price( $converted_price );
			$bulk_price_message = str_replace( array( '[bulk_qty]', '[bulk_price]' ), array( $cheapest_bulk_price_qty, $converted_price ), get_option( 'bm_bulk_price_discount_message', 'From [bulk_qty]x only [bulk_price] each.' ) );
			$result_string      = '<span class="bm-cheapest-bulk" style="float:left;width:100%;margin-bottom:10px;"><b>' . $bulk_price_message . '</b></span></br>';
		}

		return $result_string;
	}

	public function bm_filter_bulk_price_discount_value( $price, $product, $discount_type ) {
		if ( 'discount-percent' === $discount_type ) {
			return $price;
		}

		$converted_price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		return $converted_price;

	}

	public function bm_filter_rrp_price( $rrp_price, $product_id ) {
		$converted_price = YayCurrencyHelper::calculate_price_by_currency( $rrp_price, false, $this->apply_currency );
		return $converted_price;
	}

	public function bm_filter_listable_bulk_prices( $bulk_prices ) {
		if ( is_array( $bulk_prices ) ) {
			foreach ( $bulk_prices as $key => $table_row ) {
				if ( isset( $table_row['price'] ) ) {
					$price                        = $table_row['price'];
					$bulk_prices[ $key ]['price'] = $price;
				}
			}
		}
		return $bulk_prices;
	}

	public function bm_filter_bulk_price_dynamic_generate_first_row( $temp_price, $price, $product, $group_id, $quantity ) {
		$converted_price = YayCurrencyHelper::calculate_price_by_currency( $temp_price, false, $this->apply_currency );
		return $converted_price;
	}

	public function bm_filter_cheapest_bulk_price( $cheapest_bulk_price ) {
		if ( is_array( $cheapest_bulk_price ) ) {
			$price                  = $cheapest_bulk_price[0];
			$price                  = $price / YayCurrencyHelper::get_rate_fee( $this->apply_currency );
			$cheapest_bulk_price[0] = $price;
		}
		return $cheapest_bulk_price;
	}

	public function bm_filter_get_price( $price, $product ) {

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $price;
		}

		$price = YayCurrencyHelper::calculate_price_by_currency( $product->get_price( 'edit' ), false, $this->apply_currency );

		return $price;

	}

	public function detect_product_is_not_set_price( $price ) {

		if ( empty( $price ) || ! is_numeric( $price ) || YayCurrencyHelper::is_wc_json_products() ) {
			return true;
		}

		return false;
	}

	public function get_product_price_default( $product_id ) {
		$sale_price    = (float) get_post_meta( $product_id, '_sale_price', true );
		$regular_price = (float) get_post_meta( $product_id, '_regular_price', true );

		return ! empty( $sale_price ) ? $sale_price : $regular_price;
	}

	public function convert_product_get_regular_sale_price( $price, $product ) {

		if ( $this->detect_product_is_not_set_price( $price ) ) {
			return $price;
		}

		$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );

		return $price;
	}

	public function convert_product_get_price( $price, $product ) {

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			$b2b_price_default = SupportHelper::get_cart_item_objects_property( $product, 'yay_b2b_price_default' );
			if ( $b2b_price_default ) {
				return $b2b_price_default;
			}
			return $this->get_product_price_default( $product->get_id() );
		}

		return $price;
	}

	public function custom_update_price_response( $response, $product, $cheapest_price ) {
		$nonce = ! empty( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'update-price-nonce' ) ) {
			return $response;
		}
		$qty                     = isset( $_POST['qty'] ) && ! empty( $_POST['qty'] ) ? sanitize_text_field( wp_unslash( $_POST['qty'] ) ) : 1;
		$response['price']       = YayCurrencyHelper::format_price( $cheapest_price );
		$price_value             = number_format( $cheapest_price, $this->apply_currency['numberDecimal'], $this->apply_currency['decimalSeparator'], $this->apply_currency['thousandSeparator'] );
		$response['price_value'] = $price_value;
		if ( ! empty( $response['totals'] ) ) {
			$cheapest_price     = round( $cheapest_price, isset( $this->apply_currency['numberDecimal'] ) ? $this->apply_currency['numberDecimal'] : 0 );
			$response['totals'] = YayCurrencyHelper::format_price( $cheapest_price * $qty );
		}
		return $response;
	}
}
