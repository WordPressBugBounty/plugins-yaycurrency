<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\SupportHelper;

use TierPricingTable\PriceManager;
use TierPricingTable\PricingRule;


defined( 'ABSPATH' ) || exit;

// Tiered Pricing Table For WooCommerce. Link plugin: https://woocommerce.com/products/tiered-pricing-table-for-woocommerce/

class TieredPricingTableForWooCommerce {
	use SingletonTrait;

	public function __construct() {
		if ( ! class_exists( 'TierPricingTable\TierPricingTablePlugin' ) ) {
			return;
		}

		add_filter( 'yay_currency_before_calculate_totals_ignore_price_conversion', array( $this, 'before_calculate_totals_ignore_price_conversion' ), 10, 3 );

		add_filter( 'tiered_pricing_table/price/product_price_rules', array( $this, 'custom_tier_pricing_product_price_rules' ), 10, 3 );

		add_filter( 'tiered_pricing_table/price/price_by_rules', array( $this, 'custom_table_price_by_rules' ), 10, 6 );

		add_filter( 'yay_currency_get_approximately_formatted_price', array( $this, 'get_approximately_formatted_price' ), 10, 4 );

		add_filter( 'tiered_pricing_table/cart/product_cart_price', array( $this, 'custom_product_cart_price' ), 10, 4 );

	}

	public function before_calculate_totals_ignore_price_conversion( $flag, $price, $product ) {
		$flag = true;
		return $flag;
	}

	public function custom_table_price_by_rules( $product_price, $quantity, $product_id, $context, $place, PricingRule $pricingRule ) {

		if ( $pricingRule->isPercentage() ) {
			return $product_price;
		}

		if ( $product_price && 'view' === $context ) {
			return apply_filters( 'yay_currency_convert_price', $product_price );
		}

		return $product_price;
	}

	public function custom_product_cart_price( $price, $cartItem, $cartItemKey, $totalQuantity ) {
		$price = PriceManager::getPriceByRules(
			$totalQuantity,
			$cartItem['data']->get_id(),
			'edit',
			'cart',
			false
		);
		return $price;
	}

	public function custom_tier_pricing_product_price_rules( $rules, $product_id, $type ) {
		if ( 'fixed' === $type ) {
			$converted_rules = array_map(
				function ( $rule ) {
					return apply_filters( 'yay_currency_convert_price', $rule );
				},
				$rules
			);
			return $converted_rules;
		}
		return $rules;
	}

	protected function getLowestPrice( $pricingRule, $product, $approximately_price_data, $html = true ) {
		$pricingRules = $pricingRule->getRules();
		if ( $pricingRule->isPercentage() ) {
			$lowest = \TierPricingTable\PriceManager::getProductPriceWithPercentageDiscount(
				$product,
				(float) array_pop( $pricingRules )
			);
		} else {
			$lowest = array_pop( $pricingRules );
		}

		$lowest = wc_get_price_to_display(
			$product,
			array(
				'price' => $lowest,
			)
		);

		$lowest = self::convert_to_approximately_price( $lowest, $approximately_price_data );

		if ( $html ) {
			$params        = $approximately_price_data['params'];
			$lowestHtml    = SupportHelper::get_formatted_price( $lowest, $params );
			$lowest_prefix = get_option( 'tier_pricing_table_lowest_prefix', __( 'From', 'tier-pricing-table' ) );
			return $lowest_prefix . ' ' . $lowestHtml;
		}

		return $lowest;
	}

	protected function getRange( $pricingRule, $product, $approximately_price_data ) {
		$params = $approximately_price_data['params'];

		$pricingRules = $pricingRule->getRules();
		$lowest       = (float) array_pop( $pricingRules );

		$highest = wc_get_price_to_display(
			$product,
			array(
				'price' => $product->get_price(),
			)
		);

		if ( $pricingRule->isPercentage() ) {
			$lowest = \TierPricingTable\PriceManager::getProductPriceWithPercentageDiscount( $product, $lowest );
		}

		$lowest = wc_get_price_to_display(
			$product,
			array(
				'price' => $lowest,
			)
		);

		$highest     = self::convert_to_approximately_price( $highest, $approximately_price_data );
		$highestHtml = SupportHelper::get_formatted_price( $highest, $params );

		$lowest     = self::convert_to_approximately_price( $lowest, $approximately_price_data );
		$lowestHtml = SupportHelper::get_formatted_price( $lowest, $params );

		$range = $lowestHtml . ' - ' . $highestHtml;

		if ( $lowest !== $highest ) {
			return $range;
		}

		return $lowestHtml;

	}

	protected function convert_to_approximately_price( $price, $approximately_price_data ) {
		$price = apply_filters( 'yay_currency_revert_price', $price );
		return apply_filters( 'yay_currency_convert_price', $price, $approximately_price_data['approximately_apply_currency'] );
	}

	public function get_approximately_formatted_price( $formatted_price, $product, $approximately_price_data, $apply_currency ) {
		if ( ! class_exists( 'TierPricingTable\PriceManager' ) || ! isset( $approximately_price_data['approximately_apply_currency'] ) ) {
			return $formatted_price;
		}
		$displayPriceType = get_option( 'tier_pricing_table_tiered_price_at_catalog_type', 'range' );
		if ( $product instanceof \WC_Product_Variable ) {
			// With taxes
			$maxPrice = self::convert_to_approximately_price( (float) $product->get_variation_price( 'max', true ), $approximately_price_data );
			$min      = self::convert_to_approximately_price( (float) $product->get_variation_price( 'min', true ), $approximately_price_data );

			$minPrices = array( $min );

			foreach ( $product->get_available_variations() as $variation ) {
				$pricingRule = \TierPricingTable\PriceManager::getPricingRule( (int) $variation['variation_id'] );
				if ( ! empty( $pricingRule->getRules() ) ) {
					$lowest_price = $this->getLowestPrice( $pricingRule, wc_get_product( $variation['variation_id'] ), $approximately_price_data, false );
					$minPrices[]  = $lowest_price;
				}
			}

			// If product has more than 1 min price - that means that some variation has a tiered pricing rule.
			if ( ! empty( $minPrices ) && count( $minPrices ) > 1 ) {
				$minPrice = SupportHelper::get_formatted_price( min( $minPrices ), $approximately_price_data['params'] );
				if ( 'range' === $displayPriceType ) {

					if ( min( $minPrices ) === $maxPrice ) {
						return null;
					}

					$maxPrice = SupportHelper::get_formatted_price( $maxPrice, $approximately_price_data['params'] );
					return $minPrice . ' - ' . $maxPrice;
				} else {
					$lowest_prefix = get_option( 'tier_pricing_table_lowest_prefix', __( 'From', 'tier-pricing-table' ) );
					return $lowest_prefix . ' ' . $minPrice;
				}
			}
		} else {
			$pricingRule = \TierPricingTable\PriceManager::getPricingRule( $product->get_id() );

			if ( ! empty( $pricingRule->getRules() ) ) {
				if ( 'range' === $displayPriceType ) {
					return $this->getRange( $pricingRule, $product, $approximately_price_data );
				} else {
					return $this->getLowestPrice( $pricingRule, $product, $approximately_price_data );
				}
			}
		}
		return $formatted_price;
	}
}
