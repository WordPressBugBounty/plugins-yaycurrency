<?php

namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://www.studiowombat.com/plugin/advanced-product-fields-for-woocommerce/
class AdvancedProductFieldsForWooCommerce {

	use SingletonTrait;

	private $apply_currency = null;
	private $lite_version   = false;
	private $pro_version    = false;

	public function __construct() {
		$this->lite_version = class_exists( '\SW_WAPF\WAPF' ) && ! class_exists( '\SW_WAPF_PRO\WAPF' );
		$this->pro_version  = class_exists( '\SW_WAPF_PRO\WAPF' ) && ! class_exists( '\SW_WAPF\WAPF' );

		if ( ! $this->lite_version && ! $this->pro_version ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		// CalCulate Total Wapf Price

		if ( $this->lite_version ) {
			add_action( 'woocommerce_before_calculate_totals', array( $this, 'recalculate_pricing' ), 9 );
			// WAPF Lite uses get_price() (already converted) as base then set_price().
			// Reset to default-currency base+options so YayCurrency converts only once.
			add_action( 'woocommerce_before_calculate_totals', array( $this, 'reset_lite_cart_item_price_to_default' ), 11 );
		} else {
			add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );
			// Like WOOCS/Aelia: force cart calc base back to store currency so options aren't converted twice.
			add_filter( 'wapf/pricing/cart_item_base', array( $this, 'get_cart_item_base_in_default_currency' ), 20, 4 );
			add_filter( 'wapf/pricing/cart_item_base_for_formulas', array( $this, 'get_cart_item_base_in_default_currency' ), 10, 4 );
		}

		// Script Convert Wapf Price To Current Currency
		// add_action( 'wp_footer', array( $this, 'convert_wapf_price_script' ), 999 );

		// Label Addon Price
		if ( $this->lite_version ) {
			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_fields_to_cart_item' ), 999, 3 );
			add_filter( 'raw_woocommerce_price', array( $this, 'convert_wapf_price_label' ), 999, 2 );
			// Change the Meta Label for Addon Field Prices
			add_filter( 'woocommerce_get_item_data', array( $this, 'display_fields_on_cart_and_checkout' ), 999, 2 );
			add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'create_order_line_item' ), 999, 4 );
		} else {
			// Option tip text (Select label): amount → format_pricing_hint
			add_filter( 'wapf/html/pricing_hint/amount', array( $this, 'convert_pricing_hint' ), 10, 5 );
			// data-wapf-price + data-wapf-label (Select/radio/checkbox options)
			add_filter( 'wapf/html/option_attributes', array( $this, 'convert_option_attributes' ), 10, 4 );
			// data-wapf-price on field-level inputs (text, number, true-false, …)
			add_filter( 'wapf/html/field_attributes', array( $this, 'convert_field_attributes' ), 10, 4 );
			// Variable product: keep WAPF apf_base in current currency (like WOOCS/Aelia).
			add_filter( 'woocommerce_available_variation', array( $this, 'set_variant_base_price' ), 10, 3 );
		}

		add_filter( 'YayCurrency/StoreCurrency/GetPrice', array( $this, 'get_price_default_in_checkout_page' ), 10, 2 );
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_product_price_with_options' ), 999, 2 );
		add_filter( 'YayCurrency/ApplyCurrency/ByCartItem/GetPriceOptions', array( $this, 'get_price_with_options_for_cart_item' ), 10, 5 );
		add_filter( 'YayCurrency/StoreCurrency/ByCartItem/GetPriceOptions', array( $this, 'get_default_price_with_options_for_cart_item' ), 10, 4 );

		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			add_filter( 'woocommerce_cart_subtotal', array( $this, 'recalculate_cart_subtotal_mini_cart' ), 10, 3 );
		}

		// Product totals block: convert data-product-price (base used by WAPF JS).
		add_filter( 'wapf/html/product_totals', array( $this, 'convert_product_totals_html' ), 10, 2 );
	}

	// CalCulate Total Wapf Price

	public function product_addons_set_cart_contents( $cart_contents, $cart_item_key, $cart_item, $apply_currency ) {

		// get apply currency again --- apply for force payment
		$apply_currency = YayCurrencyHelper::get_current_currency( $this->apply_currency );

		if ( isset( $cart_item['wapf_item_price'] ) && ! empty( $cart_item['wapf_item_price'] ) ) {
			$wapf_item_price = $cart_item['wapf_item_price'];
			$product_id      = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
			$original_price  = wc_get_product( $product_id )->get_price( 'edit' );
			$currency_price  = apply_filters( 'yay_currency_convert_price', $original_price, $apply_currency );

			$options_total = 0;
			if ( isset( $wapf_item_price['options_total'] ) ) {
				if ( $wapf_item_price['options_total'] < 0 ) {
					$options_total = apply_filters( 'yay_currency_revert_price', $wapf_item_price['options_total'], $apply_currency );
				} else {
					$options_total = $wapf_item_price['options_total'];
				}
			}

			$options_total_convert = apply_filters( 'yay_currency_convert_price', $options_total, $apply_currency );

			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'price_with_options_default', $original_price + $options_total );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'price_with_options_by_currency', $currency_price + $options_total_convert );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'wapf_item_price_options_default', $options_total );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'wapf_item_price_options', $options_total_convert );

		}
	}

	private function calculate_total_addon_price( $cart_item_wapf = array() ) {
		$total_addon_price = 0;
		foreach ( $cart_item_wapf as $field ) {
			if ( ! empty( $field['price'] ) ) {
				foreach ( $field['price'] as $value ) {
					if ( 0 === $value['value'] || 'none' === $value['type'] ) {
						continue;
					}
					$total_addon_price += $value['value'];

				}
			}
		}
		return $total_addon_price;
	}

	public function recalculate_pricing( $cart_obj ) {
		// get apply currency again --- apply for force payment
		$apply_currency = YayCurrencyHelper::get_current_currency( $this->apply_currency );

		foreach ( $cart_obj->get_cart() as $key => $item ) {

			$cart_item = WC()->cart->cart_contents[ $key ];

			if ( empty( $cart_item['wapf'] ) ) {
				continue;
			}

			$product_id     = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
			$original_price = wc_get_product( $product_id )->get_price( 'edit' );

			$currency_price = apply_filters( 'yay_currency_convert_price', $original_price, $apply_currency );

			$total_addon_price          = self::calculate_total_addon_price( $cart_item['wapf'] );
			$total_addon_price_currency = apply_filters( 'yay_currency_convert_price', $total_addon_price, $apply_currency );

			SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'price_with_options_default', $original_price + $total_addon_price );
			SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'price_with_options_by_currency', $currency_price + $total_addon_price_currency );
			SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'wapf_item_price_options_default', $total_addon_price );
			SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'wapf_item_price_options', $total_addon_price_currency );

		}
	}

	// Script Convert Wapf Price To Current Currency

	public function convert_wapf_price_script() {
		if ( is_product() || is_singular( 'product' ) ) {
			if ( $this->pro_version ) {
				$format = YayCurrencyHelper::format_currency_position( $this->apply_currency['currencyPosition'] );
				echo "<script>wapf_config.display_options.format='" . esc_js( $format ) . "';wapf_config.display_options.symbol = '" . esc_js( $this->apply_currency['symbol'] ) . "';</script>";
			}
			?>
			<script>
				var yay_currency_rate = <?php echo esc_js( YayCurrencyHelper::get_rate_fee( $this->apply_currency ) ); ?>;
				var wapf_lite_version = "<?php echo $this->lite_version ? 'yes' : 'no'; ?>";
				if('yes' === wapf_lite_version ) {
					jQuery(document).ready(function ($) {
						$('.wapf-input').each(function() {
							const $input = $(this);
							const isSelect = $input.prop('tagName').toLowerCase() === 'select';
							// Process select elements
							if (isSelect) {
								$input.find('option').each(function() {
									const $option = $(this);
									const price = parseFloat($option.data('wapf-price'));

									if (!isNaN(price)) {
										const updatedPrice = price * yay_currency_rate;
										$option.data('wapf-price', updatedPrice).attr('data-wapf-price', updatedPrice);
									}
								});
							} 
							// Process non-select elements
							else {
								const price = parseFloat($input.data('wapf-price'));
								if (!isNaN(price)) {
									const updatedPrice = price * yay_currency_rate;
									$input.data('wapf-price', updatedPrice).attr('data-wapf-price', updatedPrice);
								}
							}
							// Trigger the change event on the input element
							$input.trigger('change');
						});
					});
				} else {
					WAPF.Filter.add('wapf/pricing/base',function(price, data) {
						price = parseFloat(price/yay_currency_rate);
						return price;
					});
					jQuery(document).on('wapf/pricing',function(e,productTotal,optionsTotal,total,$parent){
						var activeElement = jQuery(e.target.activeElement);
			
						var type = '';
						if(activeElement.is('input') || activeElement.is('textarea')) {
							type = activeElement.data('wapf-pricetype');
						}
						if(activeElement.is('select')) {
							type = activeElement.find(':selected').data('wapf-pricetype');
						}
						var convert_product_total = productTotal*yay_currency_rate;

						var convert_total_options = optionsTotal*yay_currency_rate;
						var convert_grand_total = convert_product_total + convert_total_options;
	
						jQuery('.wapf-product-total').html(WAPF.Util.formatMoney(convert_product_total,window.wapf_config.display_options));
						jQuery('.wapf-options-total').html(WAPF.Util.formatMoney(convert_total_options,window.wapf_config.display_options));
						jQuery('.wapf-grand-total').html(WAPF.Util.formatMoney(convert_grand_total,window.wapf_config.display_options));
					});
					// convert in dropdown,...
					WAPF.Filter.add('wapf/fx/hint', function(price) {
						return price*yay_currency_rate;
					});
				}
					
			</script>
			<?php
		}
	}
	// Label Addon Price
	// Lite version

	public function add_fields_to_cart_item( $cart_item_data, $product_id, $variation_id ) {
		if ( isset( $cart_item_data['wapf'] ) ) {
			$cart_item_data['wapf']['yay_currency_wapf_added'] = $this->apply_currency;
		}
		return $cart_item_data;
	}

	public function convert_wapf_price_label( $price, $original_price ) {
		if ( doing_action( 'woocommerce_before_add_to_cart_button' ) ) {
			$price = YayCurrencyHelper::calculate_price_by_currency( $price, true, $this->apply_currency );
		}
		return $price;
	}
	// Pro version
	public function convert_pricing_hint( $amount, $product, $type, $for_page = 'shop', $field = null ) {
		$types = array( 'p', 'percent' );
		if ( in_array( $type, $types, true ) ) {
			return $amount;
		}
		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			return $amount;
		}
		$amount = YayCurrencyHelper::calculate_price_by_currency( $amount, false, $this->apply_currency );
		return $amount;
	}

	public function convert_option_attributes( $attributes, $field, $product, $option ) {
		if ( $this->should_skip_currency_convert() ) {
			return $attributes;
		}

		$type = $option['pricing_type'] ?? 'none';
		if ( empty( $type ) || 'none' === $type ) {
			return $attributes;
		}

		// data-wapf-price (addon_value_for_calculations has no currency filter in current WAPF Pro).
		if ( isset( $attributes['data-wapf-price'] ) && 'fx' !== $type && is_numeric( $attributes['data-wapf-price'] ) ) {
			$attributes['data-wapf-price'] = YayCurrencyHelper::calculate_price_by_currency(
				(float) $attributes['data-wapf-price'],
				false,
				$this->apply_currency
			);
		}

		// data-wapf-label: append converted pricing hint (FX rebuilds label+hint in JS — keep bare label).
		if ( 'fx' !== $type && ! empty( $attributes['data-wapf-label'] ) && class_exists( '\SW_WAPF_PRO\Includes\Classes\Html' ) ) {
			$hint = \SW_WAPF_PRO\Includes\Classes\Html::frontend_option_pricing_hint( $option, $field, $product );
			$hint = html_entity_decode( wp_strip_all_tags( (string) $hint ), ENT_QUOTES, get_bloginfo( 'charset' ) );
			if ( '' !== $hint ) {
				$attributes['data-wapf-label'] = trim( $attributes['data-wapf-label'] . ' ' . $hint );
			}
		}

		return $attributes;
	}

	public function convert_field_attributes( $attributes, $field, $product, $field_group_id ) {
		if ( $this->should_skip_currency_convert() ) {
			return $attributes;
		}
		if ( empty( $attributes['data-wapf-price'] ) || empty( $attributes['data-wapf-pricetype'] ) ) {
			return $attributes;
		}
		if ( 'fx' === $attributes['data-wapf-pricetype'] || ! is_numeric( $attributes['data-wapf-price'] ) ) {
			return $attributes;
		}

		$attributes['data-wapf-price'] = YayCurrencyHelper::calculate_price_by_currency(
			(float) $attributes['data-wapf-price'],
			false,
			$this->apply_currency
		);

		return $attributes;
	}

	/**
	 * Like WOOCS/Aelia: set variation apf_base for WAPF JS pricing.
	 * YayCurrency converts option prices, so apf_base must be in the same (current) currency.
	 */
	public function set_variant_base_price( $variant_data, $product, $variation ) {
		if ( $this->should_skip_currency_convert() ) {
			return $variant_data;
		}

		if ( ! in_array( $product->get_type(), array( 'variable', 'variable-subscription' ), true ) ) {
			return $variant_data;
		}

		$original_price = $this->get_original_product_price( $variation );
		$currency_price = YayCurrencyHelper::calculate_price_by_currency( $original_price, false, $this->apply_currency );

		$variant_data['apf_base'] = $currency_price;

		return $variant_data;
	}

	private function get_original_product_price( $product, $for_page = 'shop' ) {
		$the_product = wc_get_product( $product->get_id() );
		$tax_display = get_option( 'woocommerce_tax_display_' . $for_page );
		$args        = array(
			'qty'   => 1,
			'price' => $the_product->get_price( 'edit' ),
		);

		return 'incl' === $tax_display
			? wc_get_price_including_tax( $product, $args )
			: wc_get_price_excluding_tax( $product, $args );
	}


	private function should_skip_currency_convert() {
		if ( empty( $this->apply_currency['currency'] ) ) {
			return true;
		}
		if ( Helper::default_currency_code() === $this->apply_currency['currency'] ) {
			return true;
		}
		return YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency );
	}

	private function convert_add_on_label( $meta_value, $wapf, $pattern = false ) {
		$currency_applied = isset( $wapf['yay_currency_wapf_added'] ) && ! empty( $wapf['yay_currency_wapf_added'] ) ? $wapf['yay_currency_wapf_added'] : false;

		if ( $currency_applied ) {
			$currency_code = $currency_applied['currency'];
			$meta_value    = str_replace( $currency_code . ' ', '', $meta_value );
			$meta_value    = str_replace( ' ' . $currency_code, '', $meta_value );
			$meta_value    = str_replace( $currency_code, '', $meta_value );
		}
		$decimals           = $currency_applied['decimalSeparator'];
		$thousand_separator = $currency_applied['thousandSeparator'];

		if ( ! $pattern ) {
			// Pattern to match numbers with any combination of . and , as separators
			$pattern = '/([+-])([^\d\s]+)((?:\d{1,3}(?:[.,]\d{3})*(?:[.,]\d+)?|\d+(?:[.,]\d+)?))/';
			$index   = 3;
		} else {
			// Pattern to match numbers with any combination of . and , as separators
			// $pattern = '/([+-])((?:\d{1,3}(?:[.,]\d{3})*(?:[.,]\d+)?|\d+(?:[.,]\d+)?))\s*([^\d\s\)]+)/';
			$pattern = '/([+-])(\d+(' . preg_quote( $decimals, '/' ) . '\d+)?)\s*([^\d\s\)]+)/';
			$index   = 2;
		}

		$add_on_label = preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $index, $decimals, $thousand_separator ) {
				$number = $matches[ $index ];

				// If the number contains both . and , we need to determine which is which
				if ( strpos( $number, '.' ) !== false && strpos( $number, ',' ) !== false ) {
					// Count occurrences of each separator
					$dot_count   = substr_count( $number, '.' );
					$comma_count = substr_count( $number, ',' );

					// The one that appears more times is likely the thousand separator
					if ( $dot_count > $comma_count ) {
						$number = str_replace( '.', '', $number ); // Remove thousand separator
						$number = str_replace( ',', '.', $number ); // Convert decimal separator
					} else {
						$number = str_replace( ',', '', $number ); // Remove thousand separator
					}
				} elseif ( '.' === $decimals ) {
						$number = str_replace( ',', '', $number ); // Remove thousand separator
				} else {
					$number = str_replace( '.', '', $number ); // Remove thousand separator
					$number = str_replace( ',', '.', $number ); // Convert decimal separator

				}

				$newValue = YayCurrencyHelper::calculate_price_by_currency( floatval( $number ), false, $this->apply_currency );
				// $format_price = preg_replace( '/<[^>]+>/', '', YayCurrencyHelper::format_price( $newValue ) );
				$format_price = preg_replace( '/<[^>]+>/', '', YayCurrencyHelper::format_price( $newValue, $this->apply_currency ) );
				return $matches[1] . $format_price;
			},
			$meta_value
		);

		return $add_on_label === $meta_value ? false : $add_on_label;
	}

	private function get_addon_price_meta_value( $meta_value, $wapf ) {
		if ( ! $wapf || empty( $wapf ) ) {
			return $meta_value;
		}
		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			$default_currency     = Helper::default_currency_code();
			$this->apply_currency = YayCurrencyHelper::get_currency_by_currency_code( $default_currency );
		}

		$add_on_label = self::convert_add_on_label( $meta_value, $wapf );
		if ( ! $add_on_label ) {
			$add_on_label = self::convert_add_on_label( $meta_value, $wapf, true );
		}

		return $add_on_label ? $add_on_label : $meta_value;
	}

	public function display_fields_on_cart_and_checkout( $item_data, $cart_item ) {
		$wapf = isset( $cart_item['wapf'] ) && ! empty( $cart_item['wapf'] ) ? $cart_item['wapf'] : false;
		if ( ! $wapf ) {
			return $item_data;
		}

		if ( ( is_cart() && get_option( 'wapf_settings_show_in_cart', 'yes' ) === 'yes' ) || ( is_checkout() && get_option( 'wapf_settings_show_in_checkout', 'yes' ) === 'yes' ) ) {
			foreach ( $cart_item['wapf'] as $key => $field ) {

				$field_value_cart = isset( $field['value_cart'] ) ? $field['value_cart'] : false;

				if ( ! $field_value_cart || ! isset( $item_data[ $key ] ) ) {
					continue;
				}

				$item_data[ $key ]['value'] = self::get_addon_price_meta_value( $field_value_cart, $wapf );

			}
		}
		return $item_data;
	}

	public function create_order_line_item( $item, $cart_item_key, $values, $order ) {
		$wapf = isset( $values['wapf'] ) && ! empty( $values['wapf'] ) ? $values['wapf'] : false;
		if ( ! $wapf ) {
			return;
		}

		$fields_meta = array();
		foreach ( $values['wapf'] as $field ) {
			$field_value = isset( $field['value'] ) ? $field['value'] : false;
			if ( $field_value ) {
				$field_value = self::get_addon_price_meta_value( $field_value, $wapf );
				// Fetch the meta data if it exists
				$existing_meta_data = $item->get_meta( $field['label'], true );
				// Check if the meta data exists
				if ( $existing_meta_data ) {
					// Update the existing meta data
					$item->update_meta_data( $field['label'], $field_value );
				} else {
					// Add the meta data if it doesn’t exist
					$item->add_meta_data( $field['label'], $field_value );
				}
				$fields_meta[ $field['id'] ] = [
					'id'    => $field['id'],
					'label' => $field['label'],
					'value' => $field_value,
					'raw'   => $field['raw'],
				];
			}
		}

		if ( ! empty( $fields_meta ) ) {
			// Fetch the meta data if it exists
			$existing_wapf_meta = $item->get_meta( '_wapf_meta', true );
			// Check if the meta data exists
			if ( $existing_wapf_meta ) {
				// Update the existing meta data
				$item->update_meta_data( '_wapf_meta', $fields_meta );
			} else {
				// Add the meta data if it doesn’t exist
				$item->add_meta_data( '_wapf_meta', $fields_meta );
			}
			$item->save();
		}
	}

	public function get_product_price_with_options( $price, $product ) {
		$price_options_by_current_currency = SupportHelper::get_cart_item_objects_property( $product, 'price_with_options_by_currency' );
		if ( false !== $price_options_by_current_currency && '' !== $price_options_by_current_currency ) {
			return (float) $price_options_by_current_currency;
		}
		return $price;
	}

	public function get_price_with_options_for_cart_item( $price_options, $cart_item, $product_id, $original_price, $apply_currency ) {
		$wapf_item_price_options = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'wapf_item_price_options' );
		return $wapf_item_price_options ? $wapf_item_price_options : $price_options;
	}

	public function get_default_price_with_options_for_cart_item( $price_options, $cart_item, $product_id, $original_price ) {
		$wapf_item_price_options_default = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'wapf_item_price_options_default' );
		return $wapf_item_price_options_default ? (float) $wapf_item_price_options_default : $price_options;
	}

	public function recalculate_cart_subtotal_mini_cart( $cart_subtotal, $compound, $cart ) {
		// Check if this is being called from the mini cart
		if ( ! wp_doing_ajax() || ! isset( $_REQUEST['wc-ajax'] ) || 'get_refreshed_fragments' !== $_REQUEST['wc-ajax'] ) {
			return $cart_subtotal;
		}

		$cart_contents = WC()->cart->get_cart_contents();
		if ( count( $cart_contents ) > 0 ) {
			$subtotal      = $this->calculate_cart_subtotal( $cart_contents );
			$cart_subtotal = YayCurrencyHelper::calculate_custom_price_by_currency_html( $this->apply_currency, $subtotal );
		}
		return $cart_subtotal;
	}

	private function calculate_cart_subtotal( $cart_contents ) {
		$subtotal = 0;
		foreach ( $cart_contents  as $key => $cart_item ) {
			$product_obj                    = $cart_item['data'];
			$price_with_options_by_currency = SupportHelper::get_cart_item_objects_property( $product_obj, 'price_with_options_by_currency' );
			if ( $price_with_options_by_currency ) {
				$subtotal += $price_with_options_by_currency * $cart_item['quantity'];
			} else {
				$subtotal += $product_obj->get_price() * $cart_item['quantity'];
			}
		}
		return $subtotal;
	}

	/**
	 * Convert WAPF product totals base price (data-product-price) to current YayCurrency.
	 * Lite has no option attribute filters, so also convert data-wapf-price once via JS.
	 *
	 * @param string      $totals_html Product totals HTML.
	 * @param \WC_Product $product     Product object.
	 * @return string
	 */
	public function convert_product_totals_html( $totals_html, $product ) {
		if ( $this->should_skip_currency_convert() ) {
			return $totals_html;
		}

		if ( ! $product instanceof \WC_Product ) {
			return $totals_html;
		}

		$original_price = $this->get_original_product_price( $product );
		$currency_price = YayCurrencyHelper::calculate_price_by_currency( $original_price, false, $this->apply_currency );

		$totals_html = preg_replace(
			'/data-product-price="[^"]*"/',
			'data-product-price="' . esc_attr( $currency_price ) . '"',
			$totals_html,
			1
		);

		if ( $this->lite_version ) {
			$totals_html .= $this->get_lite_option_prices_script();
		}

		return $totals_html;
	}

	/**
	 * Lite WAPF has no PHP filters for option data-wapf-price — convert once on the product page.
	 *
	 * @return string
	 */
	private function get_lite_option_prices_script() {
		static $printed = false;
		if ( $printed ) {
			return '';
		}
		$printed = true;

		$rate = (float) YayCurrencyHelper::get_rate_fee( $this->apply_currency );
		if ( $rate <= 0 || 1.0 === $rate ) {
			return '';
		}

		ob_start();
		?>
		<script>
		(function ($) {
			var yayCurrencyRate = <?php echo esc_js( $rate ); ?>;
			function convertWapfOptionPrices() {
				$('.wapf-input').each(function () {
					var $input = $(this);
					if ($input.prop('tagName').toLowerCase() === 'select') {
						$input.find('option').each(function () {
							var $option = $(this);
							var price = parseFloat($option.attr('data-wapf-price'));
							if (!isNaN(price) && !$option.data('yay-converted')) {
								var converted = price * yayCurrencyRate;
								$option.attr('data-wapf-price', converted).data('wapf-price', converted).data('yay-converted', true);
							}
						});
					} else {
						var price = parseFloat($input.attr('data-wapf-price'));
						if (!isNaN(price) && !$input.data('yay-converted')) {
							var converted = price * yayCurrencyRate;
							$input.attr('data-wapf-price', converted).data('wapf-price', converted).data('yay-converted', true);
						}
					}
				});
			}
			$(convertWapfOptionPrices);
		})(jQuery);
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Lite WAPF sets cart price via get_price() (already converted) + addon.
	 * Restore default-currency total so YayCurrency's get_price pipeline converts once only.
	 *
	 * @param \WC_Cart $cart_obj Cart object.
	 */
	public function reset_lite_cart_item_price_to_default( $cart_obj ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart_obj->get_cart() as $key => $item ) {
			if ( empty( $item['wapf'] ) ) {
				continue;
			}

			$default_price = SupportHelper::get_cart_item_objects_property( $item['data'], 'price_with_options_default' );
			if ( false === $default_price || '' === $default_price ) {
				continue;
			}

			$item['data']->set_price( (float) $default_price );
		}
	}

	/**
	 * Pro: WAPF reads $product->get_price() which YayCurrency already converted.
	 * Return store-currency catalog price so options_total stays in default currency (avoid double convert on order totals).
	 *
	 * @param float       $price     Current base price.
	 * @param \WC_Product $product   Product.
	 * @param int         $quantity  Quantity.
	 * @param array       $cart_item Cart item.
	 * @return float
	 */
	public function get_cart_item_base_in_default_currency( $price, $product, $quantity = 1, $cart_item = array() ) {
		if ( $this->should_skip_currency_convert() ) {
			return $price;
		}

		$product_id = 0;
		if ( ! empty( $cart_item['variation_id'] ) ) {
			$product_id = (int) $cart_item['variation_id'];
		} elseif ( ! empty( $cart_item['product_id'] ) ) {
			$product_id = (int) $cart_item['product_id'];
		} elseif ( $product instanceof \WC_Product ) {
			$product_id = (int) $product->get_id();
		}

		if ( ! $product_id ) {
			return $price;
		}

		$catalog_product = wc_get_product( $product_id );
		if ( ! $catalog_product ) {
			return $price;
		}

		return (float) $catalog_product->get_price( 'edit' );
	}

	public function get_price_default_in_checkout_page( $price, $product ) {
		$price_with_options_default = SupportHelper::get_cart_item_objects_property( $product, 'price_with_options_default' );
		if ( false !== $price_with_options_default && '' !== $price_with_options_default ) {
			return (float) $price_with_options_default;
		}
		return $price;
	}
}