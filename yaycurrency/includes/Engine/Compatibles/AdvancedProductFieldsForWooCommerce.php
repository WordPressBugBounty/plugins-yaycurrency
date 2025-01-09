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
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'recalculate_pricing' ), 9 );

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
		add_filter( 'yay_currency_get_price_options_by_cart_item', array( $this, 'get_price_with_options_for_cart_item' ), 10, 5 );
		add_filter( 'yay_currency_get_price_options_default_by_cart_item', array( $this, 'get_default_price_with_options_for_cart_item' ), 10, 4 );

	}

	// CalCulate Total Wapf Price

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

	private function retrieve_option_data_lite( $cart_item, $apply_currency ) {

		$original_price             = $cart_item['data']->get_price( 'edit' );
		$currency_price             = YayCurrencyHelper::calculate_price_by_currency( $original_price, false, $apply_currency );
		$total_addon_price          = self::calculate_total_addon_price( $cart_item['wapf'] );
		$total_addon_price_currency = YayCurrencyHelper::calculate_price_by_currency( $total_addon_price, false, $apply_currency );

		return array(
			'options_total_default'       => $total_addon_price,
			'options_total_currency'      => $total_addon_price_currency,
			'currency_price'              => $currency_price,
			'original_price'              => $original_price,
			'price_with_options'          => $original_price + $total_addon_price,
			'price_with_options_currency' => $currency_price + $total_addon_price_currency,
		);

	}

	private function retrieve_option_data( $cart_item, $apply_currency ) {

		$quantity       = ! empty( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1;
		$product_id     = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
		$product        = wc_get_product( $product_id );
		$original_price = $product->get_price( 'edit' );
		$currency_price = YayCurrencyHelper::calculate_price_by_currency( $original_price, false, $apply_currency );

		$options_total_default = 0;
		$options_total         = 0;

		foreach ( $cart_item['wapf'] as $field ) {
			if ( ! empty( $field['values'] ) ) {
				foreach ( $field['values'] as $value ) {
					if ( 0 === $value['price'] || 'none' === $value['price_type'] ) {
						continue;
					}
					$v                             = isset( $value['slug'] ) ? $value['label'] : $field['raw'];
					$qty_based                     = ( isset( $field['clone_type'] ) && 'qty' === $field['clone_type'] ) || ! empty( $field['qty_based'] );
					$price                         = Fields::do_pricing( $qty_based, $value['price_type'], $value['price'], $original_price, $quantity, $v, $product_id, $cart_item['wapf'], $cart_item['wapf_field_groups'], isset( $cart_item['wapf_clone'] ) ? $cart_item['wapf_clone'] : 0, $options_total );
					$price_default_not_apply_fixed = false;
					if ( in_array( $value['price_type'], array( 'p', 'percent' ), true ) ) {
						$price = (float) ( $price / YayCurrencyHelper::get_rate_fee( $apply_currency ) );

						$price_default_not_apply_fixed = $original_price * ( $value['price'] / 100 );
						$price_default_not_apply_fixed = (float) $field['qty_based'] ? $price_default_not_apply_fixed : $price_default_not_apply_fixed / $quantity;
					}
					$options_total         = $options_total + $price;
					$options_total_default = $options_total_default + ( $price_default_not_apply_fixed ? $price_default_not_apply_fixed : $price );
				}
			}
		}

		$options_total_currency = YayCurrencyHelper::calculate_price_by_currency( $options_total, false, $apply_currency );

		$data = array(
			'options_total_default'       => $options_total_default,
			'options_total_currency'      => $options_total_currency,
			'currency_price'              => $currency_price,
			'original_price'              => $original_price,
			'price_with_options'          => $original_price + $options_total,
			'price_with_options_currency' => $currency_price + $options_total_currency,
		);

		return $data;
	}

	public function recalculate_pricing( $cart_obj ) {
		// get apply currency again --- apply for force payment
		$apply_currency = YayCurrencyHelper::get_current_currency( $this->apply_currency );

		foreach ( $cart_obj->get_cart() as $key => $item ) {

			$cart_item = WC()->cart->cart_contents[ $key ];
			if ( empty( $cart_item['wapf'] ) ) {
				continue;
			}

			if ( $this->lite_version ) {
				$wapf_data = self::retrieve_option_data_lite( $cart_item, $apply_currency );
			} else {
				$wapf_data = $this->retrieve_option_data( $cart_item, $apply_currency );
			}

			if ( ! empty( $wapf_data ) ) {
				SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'price_with_options_default', $wapf_data['price_with_options'] );
				SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'price_with_options_by_currency', $wapf_data['price_with_options_currency'] );
				SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'wapf_item_price_options_default', $wapf_data['options_total_default'] );
				SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'wapf_item_price_options', $wapf_data['options_total_currency'] );
				SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'wapf_item_base_price', $wapf_data['currency_price'] );
			}
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

	// Change the Meta Label for Addon Field Prices
	private function convert_add_on_label( $meta_value, $wapf, $pattern = false ) {
		$currency_applied = isset( $wapf['yay_currency_wapf_added'] ) && ! empty( $wapf['yay_currency_wapf_added'] ) ? $wapf['yay_currency_wapf_added'] : false;

		if ( $currency_applied ) {
			$currency_code = $currency_applied['currency'];
			$meta_value    = str_replace( $currency_code . ' ', '', $meta_value );
			$meta_value    = str_replace( ' ' . $currency_code, '', $meta_value );
			$meta_value    = str_replace( $currency_code, '', $meta_value );
		}
		$decimals = $currency_applied['decimalSeparator'];

		if ( ! $pattern ) {
			$pattern = '/([+-])([^\d\s]+)(\d+(' . preg_quote( $decimals, '/' ) . '\d{2})?)/';
			$index   = 3;

		} else {
			$pattern = '/([+-])(\d+(' . preg_quote( $decimals, '/' ) . '\d+)?)\s*([^\d\s\)]+)/';
			$index   = 2;
		}

		$add_on_label = preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $index ) {
				$newValue     = YayCurrencyHelper::calculate_price_by_currency( floatval( $matches[ $index ] ), false, $this->apply_currency );
				$format_price = preg_replace( '/<[^>]+>/', '', YayCurrencyHelper::format_price( $newValue ) );
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
}