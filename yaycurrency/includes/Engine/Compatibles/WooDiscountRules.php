<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://www.flycart.org/products/wordpress/woocommerce-discount-rules

class WooDiscountRules {

	use SingletonTrait;

	private $apply_currency = array();
	private $cart_item_from;
	public function __construct() {

		if ( ! defined( 'WDR_VERSION' ) ) {
			return;
		}
		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'yay_currency_get_product_price_by_3rd_plugin', array( $this, 'get_product_price_by_3rd_plugin' ), 10, 3 );
		add_filter( 'yay_currency_get_product_price_by_cart_item', array( $this, 'get_cart_item_price_3rd_plugin' ), 10, 3 );

		add_filter( 'yay_currency_active_woo_discount_rules', array( $this, 'active_woo_discount_rules' ), 10, 1 );

		add_filter( 'advanced_woo_discount_rules_discounted_price_of_cart_item', array( $this, 'convert_price_apply_rules_discounted' ), 9999, 4 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_price_with_options' ), 10, 2 );

		add_filter( 'advanced_woo_discount_rules_converted_currency_value', array( $this, 'advanced_woo_discount_rules_converted_currency_value' ), 9999, 2 );
		add_filter( 'advanced_woo_discount_rules_bulk_table_ranges', array( $this, 'advanced_woo_discount_rules_bulk_table_ranges' ), 10, 3 );
		add_filter( 'advanced_woo_discount_rules_get_regular_price', array( $this, 'advanced_woo_discount_rules_get_price' ), 10, 2 );
		add_filter( 'advanced_woo_discount_rules_get_price', array( $this, 'advanced_woo_discount_rules_get_price' ), 10, 2 );
	}

	public function active_woo_discount_rules() {
		return true;
	}

	public function get_product_price_by_3rd_plugin( $product_price, $product, $apply_currency ) {

		if ( isset( $product->awdr_discount_price ) ) {
			if ( $product->awdr_discount_price >= 0 ) {
				$product_price = $product->awdr_discount_price;
			}
		}

		if ( isset( $product->awdr_product_discount_price_currency ) ) {
			return $product->awdr_product_discount_price_currency;
		}

		return $product_price;
	}

	public function get_cart_item_price_3rd_plugin( $product_price, $cart_item, $apply_currency ) {

		if ( isset( $cart_item['data']->awdr_product_discount_price_currency ) ) {
			$product_price = $cart_item['data']->awdr_product_discount_price_currency;
		}

		return $product_price;
	}

	public function is_total_discount_type( $calculated_cart_item_discount = array(), $cart_item_key = '', $discount_type = false ) {
		$total_discount_details = isset( $calculated_cart_item_discount['total_discount_details'] ) ? $calculated_cart_item_discount['total_discount_details'] : false;
		if ( $total_discount_details && isset( $total_discount_details[ $cart_item_key ] ) ) {
			$discount_details = array_shift( $total_discount_details[ $cart_item_key ] );
			if ( $discount_type ) {
				return isset( $discount_details[ $discount_type ] ) ? $discount_details[ $discount_type ] : false;
			} else {
				$bulk_discount = isset( $discount_details['bulk_discount'] ) && ! empty( $discount_details['bulk_discount'] ) ? $discount_details['bulk_discount'] : false;
				if ( $bulk_discount ) {
					return $bulk_discount;
				}

				$simple_discount = isset( $discount_details['simple_discount'] ) && ! empty( $discount_details['simple_discount'] ) ? $discount_details['simple_discount'] : false;
				if ( $simple_discount ) {
					return $simple_discount;
				}
				$set_discount = isset( $discount_details['set_discount'] ) && ! empty( $discount_details['set_discount'] ) ? $discount_details['set_discount'] : false;
				if ( $set_discount ) {
					$set_discount['total_discount_type_type'] = 'set_discount';
					return $set_discount;
				}
				return false;
			}
		}

		return false;
	}

	public function get_price_by_discount_type( $price, $product_fixed_price, $discount_type, $initial_price, $discount_value ) {
		if ( 'percentage' === $discount_type ) {
			$awdr_product_discount_price_currency = $product_fixed_price - ( $discount_value / 100 ) * floatval( $product_fixed_price );
			$awdr_product_price_default_currency  = $initial_price - ( $discount_value / 100 ) * floatval( $initial_price );
		}

		if ( 'flat' === $discount_type ) {
			$price                                = $initial_price - $discount_value;
			$awdr_product_discount_price_currency = $product_fixed_price - YayCurrencyHelper::calculate_price_by_currency( $discount_value, false, $this->apply_currency );
			$awdr_product_price_default_currency  = $price;
		}

		if ( 'fixed_price' === $discount_type ) {
			$price                                = $discount_value;
			$awdr_product_discount_price_currency = YayCurrencyHelper::calculate_price_by_currency( $discount_value, false, $this->apply_currency );
			$awdr_product_price_default_currency  = $price;
		}

		return array(
			'price'                          => $price,
			'discount_price_currency'        => $awdr_product_discount_price_currency,
			'product_price_default_currency' => $awdr_product_price_default_currency,
		);
	}

	// APPLY FOR BULK SET DISCOUNT
	public function getSetDiscountFromRanges( $ranges, $quantity, $ajax_price, $is_cart ) {
		$qualified_range   = array();
		$fully_matched     = array();
		$partially_matched = array();
		foreach ( $ranges as $key => $range ) {
			$max_quantity = ( isset( $range->from ) && ! empty( $range->from ) ) ? $range->from : 0;
			if ( $quantity === $max_quantity ) {
				$fully_matched[ $key ] = $max_quantity;
			} elseif ( $quantity >= $max_quantity ) {
				$partially_matched[ $key ] = $max_quantity;
			}
		}
		if ( empty( $fully_matched ) ) {
			if ( empty( $partially_matched ) ) {
				return array();
			}
			$qualified_range = $partially_matched;
			$matched_range   = array_keys( $qualified_range, max( $qualified_range ) );
			if ( $ajax_price || ! $is_cart ) {
				$matched_range_key         = isset( $matched_range[0] ) ? $matched_range[0] : null;
				$range                     = isset( $ranges->$matched_range_key ) ? $ranges->$matched_range_key : array();
				$bundle_recursive_quantity = ( isset( $range->from ) && ! empty( $range->from ) ) ? $range->from : 0;
				$recursive_step            = 0;
				if ( $bundle_recursive_quantity > 0 ) {
					$recursive_step = $quantity / $bundle_recursive_quantity;
				}
				if ( ! is_int( $recursive_step ) ) {
					return array();
				}
				$actual_quantity_to_be = $recursive_step * $bundle_recursive_quantity;
				if ( $actual_quantity_to_be !== $quantity ) {
					return array();
				}
			}
		} else {
			$qualified_range = $fully_matched;
			$matched_range   = array_keys( $qualified_range, min( $qualified_range ) );
		}
		if ( empty( $qualified_range ) ) {
			return array();
		}
		$matched_range_key = isset( $matched_range[0] ) ? $matched_range[0] : null;
		$range             = isset( $ranges->$matched_range_key ) ? $ranges->$matched_range_key : array();
		return $range;
	}


	public function selected_bundle_range( $cart_contents ) {
		if ( class_exists( '\Wdr\App\Controllers\ManageDiscount' ) && class_exists( 'WDRPro\App\Rules\Set' ) ) {
			$rules             = \Wdr\App\Controllers\ManageDiscount::$available_rules;
			$rule              = array_shift( $rules );
			$set_discount_data = \WDRPro\App\Rules\Set::getAdjustments( $rule );
			$quantity          = $rule->getProductCumulativeDiscountQuantity( $cart_contents );
			if ( $set_discount_data ) {
				$set_ranges            = ( isset( $set_discount_data->ranges ) && ! empty( $set_discount_data->ranges ) ) ? $set_discount_data->ranges : false;
				$selected_bundle_range = $this->getSetDiscountFromRanges( $set_ranges, $quantity, false, true );
				return $selected_bundle_range;
			}
		}

		return false;

	}


	public function calculate_price_with_set_discount_type( $price_fixed, $quantity, $discount_lines, $set_discount ) {
		$price_result = $price_fixed;
		if ( ! YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			$discounted_price = $discount_lines[0]['discounted_price'];
			if ( $discount_lines['non_applied']['quantity'] > 0 ) {
				$price_result = ( $price_fixed * $discount_lines['non_applied']['quantity'] + YayCurrencyHelper::calculate_price_by_currency( $discounted_price, false, $this->apply_currency ) * $discount_lines[0]['quantity'] ) / $quantity;
			} else {
				$price_result = YayCurrencyHelper::calculate_price_by_currency( $discounted_price, false, $this->apply_currency );
			}
		} else {
			$cart_contents          = WC()->cart->get_cart_contents();
			$selected_discount_info = $this->selected_bundle_range( $cart_contents );
			if ( $selected_discount_info ) {
				$discount_type = isset( $selected_discount_info->type ) ? $selected_discount_info->type : false;
				if ( $discount_type ) {
					if ( $discount_lines['non_applied']['quantity'] > 0 ) {
						$non_applied = $price_fixed * $discount_lines['non_applied']['quantity'];
						switch ( $discount_type ) {
							case 'fixed_set_price':
								$discounted_price = (float) $selected_discount_info->value / (float) $selected_discount_info->from;
								$price_result     = ( $non_applied + YayCurrencyHelper::calculate_price_by_currency( $discounted_price, false, $this->apply_currency ) * $discount_lines[0]['quantity'] ) / $quantity;
								break;
							case 'percentage':
								$discount_value = (float) $selected_discount_info->value;
								$applied_price  = ( $price_fixed - $price_fixed * ( $discount_value / 100 ) ) * $discount_lines[0]['quantity'];
								$price_result   = ( $non_applied + $applied_price ) / $quantity;
								break;
							default:
								$discount_value = (float) $selected_discount_info->value;
								$applied_price  = ( $price_fixed - YayCurrencyHelper::calculate_price_by_currency( $discount_value, false, $this->apply_currency ) ) * $discount_lines[0]['quantity'];
								$price_result   = ( $non_applied + $applied_price ) / $quantity;
								break;
						}
					} else {
						switch ( $discount_type ) {
							case 'fixed_set_price':
								$discounted_price = (float) $selected_discount_info->value / (float) $selected_discount_info->from;
								$price_result     = YayCurrencyHelper::calculate_price_by_currency( $discounted_price, false, $this->apply_currency );
								break;
							case 'percentage':
								$discount_value = (float) $selected_discount_info->value;
								$price_result   = $price_fixed - $price_fixed * ( $discount_value / 100 );
								break;
							default:
								$discount_value = (float) $selected_discount_info->value;
								$price_result   = $price_fixed - YayCurrencyHelper::calculate_price_by_currency( $discount_value, false, $this->apply_currency );
								break;
						}
					}
				}
			}
		}

		return $price_result > 0 ? $price_result : 0;
	}

	// Calculate Cart Discount Price wit Set Discount Type
	public function get_cart_discount_price_by_set_discount_type( $cart_object, $cart_item, $product_fixed_price ) {
		if ( isset( $cart_object->cart_contents ) ) {
			$cart_contents     = $cart_object->cart_contents;
			$quantity          = $cart_item['quantity'];
			$set_discount_info = $this->selected_bundle_range( $cart_contents );
			if ( $set_discount_info ) {
				if ( $set_discount_info ) {
					$cart_item_from = ! $this->cart_item_from ? (float) $set_discount_info->from : (float) $this->cart_item_from;
					if ( $cart_item_from > 0 ) {
						$cart_item_qty                 = $cart_item_from - $quantity;
						$product_price_not_apply_price = $product_fixed_price * $quantity;
						$discounted_price              = 0;
						$set_discount_type             = $set_discount_info->type;
						switch ( $set_discount_type ) {
							case 'fixed_set_price':
								$discounted_price = (float) $set_discount_info->value / (float) $set_discount_info->from;
								$discounted_price = YayCurrencyHelper::calculate_price_by_currency( $discounted_price, false, $this->apply_currency );
								break;
							case 'percentage':
								$discounted_price = $product_fixed_price - ( (float) $set_discount_info->value / 100 ) * $product_fixed_price;
								break;
							default:
								$discounted_price = YayCurrencyHelper::calculate_price_by_currency( (float) $set_discount_info->value, false, $this->apply_currency );
								$discounted_price = $discounted_price > $product_fixed_price ? $product_fixed_price : $product_fixed_price - $discounted_price;
								break;
						}
						if ( $cart_item_qty >= 0 ) {
							$cart_discount_price = $product_price_not_apply_price - $discounted_price * $cart_item['quantity'];
						} elseif ( $cart_item_qty < 0 ) {
							$get_qty_again       = $cart_item['quantity'] - $cart_item_from;
							$discount_price      = $discounted_price * $cart_item_from + ( $product_fixed_price * $get_qty_again );
							$cart_discount_price = $product_price_not_apply_price - $discount_price;
						}
						$this->cart_item_from = $cart_item_qty && $cart_item_qty > 0 ? $cart_item_qty : 0;
						return $cart_discount_price;
					}
				}
			}
		}

		return false;
	}

	public function convert_price_apply_rules_discounted( $price, $cart_item, $cart_object, $calculated_cart_item_discount ) {
		if ( ! empty( $cart_item['data'] ) ) {
			$product_obj                      = $cart_item['data'];
			$product_obj->awdr_discount_price = $calculated_cart_item_discount['discounted_price'];

			$product_id    = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
			$key           = $cart_item['key'];
			$initial_price = $calculated_cart_item_discount['initial_price'];

			$price_options = SupportHelper::get_price_options_by_3rd_plugin( $product_obj );

			$product_price_apply_currency = YayCurrencyHelper::calculate_price_by_currency( $initial_price, false, $this->apply_currency );

			$product_fixed_price = $product_price_apply_currency + $price_options;

			$discount = $this->is_total_discount_type( $calculated_cart_item_discount, $key );

			if ( $discount ) {
				$discount_type  = $discount['discount_type'];
				$discount_value = $discount['discount_value'];

				$price_by_discount_type = $this->get_price_by_discount_type( $price, $product_fixed_price, $discount_type, $initial_price, $discount_value );
				$price                  = $price_by_discount_type['price'];
				$product_obj->awdr_product_discount_price_currency = $price_by_discount_type['discount_price_currency'];
				$product_obj->awdr_product_price_default_currency  = $price_by_discount_type['product_price_default_currency'];
				//Support a few plugin like Extra, Add-ons
				$product_obj->awdr_product_get_discount_type  = $discount_type;
				$product_obj->awdr_product_get_quantity       = $cart_item['quantity'];
				$product_obj->awdr_product_get_discount_value = $discount_value;

				// DISCOUNT TYPE SET DISCOUNT
				$set_discount_discounted_price = isset( $calculated_cart_item_discount['discounted_price'] ) && ! empty( $calculated_cart_item_discount['discounted_price'] ) ? $calculated_cart_item_discount['discounted_price'] : false;
				if ( isset( $discount['total_discount_type_type'] ) && $set_discount_discounted_price ) {
					$discount_price_fixed                              = $this->calculate_price_with_set_discount_type( $product_fixed_price, $cart_item['quantity'], $calculated_cart_item_discount['discount_lines'], $discount );
					$product_obj->awdr_product_discount_price_currency = $discount_price_fixed;
					$product_obj->awdr_discount_price                  = $set_discount_discounted_price;
					$price = $set_discount_discounted_price;
				}
			}

			$cart_discount_details = isset( $calculated_cart_item_discount['cart_discount_details'] ) && ! empty( $calculated_cart_item_discount['cart_discount_details'] ) ? $calculated_cart_item_discount['cart_discount_details'] : false;
			if ( $cart_discount_details ) {
				$cart_discount_details                             = array_shift( $cart_discount_details );
				$cart_discount_price                               = $product_fixed_price ? $product_fixed_price : $product_price_apply_currency;
				$discount_type                                     = $cart_discount_details['cart_discount_type'];
				$discount_value                                    = $cart_discount_details['cart_discount_price'];
				$product_obj->awdr_product_discount_price_currency = $cart_discount_price;
				if ( 'flat' === $discount_type ) {
					$original_price      = SupportHelper::get_original_price_apply_discount_pro( $product_id );
					$cart_discount_value = (float) $cart_discount_details['cart_discount'];
					if ( $original_price - $cart_discount_value > 0 ) {
						$cart_discount_price = YayCurrencyHelper::calculate_price_by_currency( $cart_discount_value, false, $this->apply_currency ) * $cart_item['quantity'];
					} else {
						$cart_discount_price = $product_fixed_price * $cart_item['quantity'];
					}
				}
				if ( 'fixed_price' === $discount_type ) {
					$cart_discount_price_with_value = YayCurrencyHelper::calculate_price_by_currency( $cart_discount_details['cart_discount'], false, $this->apply_currency );
					$cart_discount_price            = ( $product_fixed_price - $cart_discount_price_with_value ) * $cart_item['quantity'];
				}

				if ( 'percentage' === $discount_type ) {
					$cart_discount_value = (float) $cart_discount_details['cart_discount'];
					$cart_discount_price = ( $product_fixed_price * ( $cart_discount_value / 100 ) ) * $cart_item['quantity'];
				}

				$product_obj->cart_discount_type  = $discount_type;
				$product_obj->cart_discount_price = $cart_discount_price;

				$cart_discount_price_by_set_discount = $this->get_cart_discount_price_by_set_discount_type( $cart_object, $cart_item, $product_fixed_price );
				if ( $cart_discount_price_by_set_discount ) {
					$product_obj->cart_discount_price = $cart_discount_price_by_set_discount;
				}
			}

			if ( ! isset( $discount['total_discount_type_type'] ) && ! empty( $calculated_cart_item_discount['discounted_price'] ) ) {
				$product_obj->awdr_discount_price = $calculated_cart_item_discount['discounted_price'];

				//YayExtra
				if ( defined( 'YAYE_VERSION' ) && isset( $cart_item['yaye_total_option_cost'] ) ) {
					$product_obj->awdr_discount_price = $product_obj->awdr_product_discount_price_currency;
				}
			}
		}
		return $price;
	}

	// GET PRICE WITH OPTIONS
	public function get_price_with_options( $price, $product ) {
		if ( isset( $product->awdr_discount_price ) ) {
			if ( $product->awdr_discount_price >= 0 ) {
				$price = $product->awdr_discount_price;
			}
		}
		return $price;
	}

	// CONVERTED CURRENCY VALUE
	public function advanced_woo_discount_rules_converted_currency_value( $price, $type ) {
		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $price;
		}

		if ( 'flat' === $type && $price < YayCurrencyHelper::get_rate_fee( $this->apply_currency ) ) {
			$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		}

		if ( 'flat' !== $type ) {
			$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		}

		return $price;
	}

	// Convert price with Bulk Table Ranges
	public function advanced_woo_discount_rules_bulk_table_ranges( $response_ranges, $list_rules, $product ) {
		if ( count( $response_ranges ) && ! empty( $list_rules ) && ! empty( $product ) ) {
			foreach ( $response_ranges as &$range ) {
				if ( isset( $range['discount_type'] ) ) {
					if ( 'flat' === $range['discount_type'] || 'fixed_price' === $range['discount_type'] ) {
						$range['discount_value'] = YayCurrencyHelper::calculate_price_by_currency( $range['discount_value'], false, $this->apply_currency );
					}
				}
			}
		}

		return $response_ranges;
	}

	public function advanced_woo_discount_rules_get_price( $price, $product ) {

		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $price;
		}

		return $price;

	}
}
