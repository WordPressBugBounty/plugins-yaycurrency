<?php

namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;
use SW_WAPF_PRO\Includes\Classes\Fields;

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
		} else {
			add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );
		}

		// Script Convert Wapf Price To Current Currency
		add_action( 'wp_footer', array( $this, 'convert_wapf_price_script' ), 999 );

		// Label Addon Price
		if ( $this->lite_version ) {
			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_fields_to_cart_item' ), 999, 3 );
			add_filter( 'raw_woocommerce_price', array( $this, 'convert_wapf_price_label' ), 999, 2 );
			// Change the Meta Label for Addon Field Prices
			add_filter( 'woocommerce_get_item_data', array( $this, 'display_fields_on_cart_and_checkout' ), 999, 2 );
			add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'create_order_line_item' ), 999, 4 );
		} else {
			add_filter( 'wapf/html/pricing_hint/amount', array( $this, 'convert_pricing_hint' ), 10, 3 );
		}

		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_product_price_with_options' ), 999, 2 );
		add_filter( 'YayCurrency/ApplyCurrency/ByCartItem/GetPriceOptions', array( $this, 'get_price_with_options_for_cart_item' ), 10, 5 );
		add_filter( 'YayCurrency/StoreCurrency/ByCartItem/GetPriceOptions', array( $this, 'get_default_price_with_options_for_cart_item' ), 10, 4 );

		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			add_filter( 'woocommerce_cart_subtotal', array( $this, 'recalculate_cart_subtotal_mini_cart' ), 10, 3 );
		}
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

			$options_total         = isset( $wapf_item_price['options_total'] ) ? $wapf_item_price['options_total'] : 0;
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
	public function convert_pricing_hint( $amount, $product, $type ) {
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
		return $price_options_by_current_currency ? $price_options_by_current_currency : $price;
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
}