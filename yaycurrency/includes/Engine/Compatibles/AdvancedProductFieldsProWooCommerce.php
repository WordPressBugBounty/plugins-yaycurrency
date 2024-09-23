<?php

namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\SupportHelper;

use SW_WAPF_PRO\Includes\Classes\Fields;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://www.studiowombat.com/plugin/advanced-product-fields-for-woocommerce/
class AdvancedProductFieldsProWooCommerce {

	use SingletonTrait;

	private $apply_currency = null;

	public function __construct() {
		if ( ! class_exists( '\SW_WAPF_PRO\WAPF' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();
		add_filter( 'yay_currency_get_price_options_by_cart_item', array( $this, 'get_price_options_by_cart_item' ), 10, 5 );
		add_filter( 'yay_currency_get_cart_subtotal_3rd_plugin', array( $this, 'get_cart_subtotal_3rd_plugin' ), 10, 2 );

		// Define hooks get price
		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'yay_get_price_with_options' ), 10, 2 );

		add_filter( 'yay_wapf_get_cart_subtotal', array( $this, 'yay_get_cart_subtotal' ), 10, 2 );

		add_filter( 'wapf/html/pricing_hint/amount', array( $this, 'convert_pricing_hint' ), 10, 3 );
		add_action( 'wp_footer', array( $this, 'add_footer_script' ), 100 );
		add_action( 'wp_footer', array( $this, 'change_currency_info_on_frontend' ), 5555 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'recalculate_pricing' ), 9 );

	}

	public function get_price_options_by_cart_item( $price_options, $cart_item, $product_id, $original_price, $apply_currency ) {
		$wapf_item_price_options = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'wapf_item_price_options' );
		return $wapf_item_price_options ? $wapf_item_price_options : $price_options;
	}

	public function get_cart_subtotal_3rd_plugin( $subtotal, $apply_currency ) {
		$subtotal      = 0;
		$cart_contents = WC()->cart->get_cart_contents();
		foreach ( $cart_contents  as $key => $cart_item ) {
			$product_price           = SupportHelper::calculate_product_price_by_cart_item( $cart_item );
			$wapf_item_price_options = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'wapf_item_price_options' );
			$price_options           = $wapf_item_price_options ? (float) $wapf_item_price_options / YayCurrencyHelper::get_rate_fee( $apply_currency ) : 0;
			$product_subtotal        = ( $product_price + $price_options ) * $cart_item['quantity'];
			$subtotal                = $subtotal + YayCurrencyHelper::calculate_price_by_currency( $product_subtotal, false, $apply_currency );
		}

		return $subtotal;
	}

	public function yay_currency_calculate_rate_fee_again() {
		$rate_fee = YayCurrencyHelper::get_rate_fee( $this->apply_currency );
		if ( YayCurrencyHelper::disable_fallback_option_in_checkout_page( $this->apply_currency ) ) {
			$rate_fee = 1;
		}
		return $rate_fee;
	}

	public function yay_get_price_with_options( $price, $product ) {
		$price_options_by_current_currency = SupportHelper::get_cart_item_objects_property( $product, 'price_with_options_by_currency' );
		return $price_options_by_current_currency ? $price_options_by_current_currency : $price;
	}

	public function yay_get_cart_subtotal( $subtotal_price, $apply_currency ) {
		$subtotal      = 0;
		$cart_contents = WC()->cart->get_cart_contents();
		foreach ( $cart_contents  as $key => $value ) {
			$wapf_item_price_options = SupportHelper::get_cart_item_objects_property( $value['data'], 'wapf_item_price_options' );
			if ( $wapf_item_price_options ) {
				$original_price = SupportHelper::get_cart_item_objects_property( $value['data'], 'wapf_item_base_price' );
				$original_price = $original_price ? $original_price : $value['data']->get_price();
				$subtotal       = $subtotal + ( $original_price + $wapf_item_price_options ) * $value['quantity'];
			} else {
				$subtotal = $subtotal + YayCurrencyHelper::calculate_price_by_currency( $value['line_subtotal'], false, $apply_currency );
			}
		}
		if ( $subtotal ) {
			return $subtotal;
		}
		return $subtotal_price;
	}


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

	public function add_footer_script() {
		if ( ! is_product() ) {
			return;
		}
		?>
		<script>
			var yay_currency_rate = <?php echo esc_js( $this->yay_currency_calculate_rate_fee_again() ); ?>;
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

		</script>
		<?php
	}

	public function change_currency_info_on_frontend() {
		if ( ! is_product() || ! $this->apply_currency ) {
			return;
		}

		$format = YayCurrencyHelper::format_currency_position( $this->apply_currency['currencyPosition'] );

		echo "<script>wapf_config.display_options.format='" . esc_js( $format ) . "';wapf_config.display_options.symbol = '" . esc_js( $this->apply_currency['symbol'] ) . "';</script>";
	}

	public function get_data_options_info( $cart_item ) {

		if ( empty( $cart_item['wapf'] ) ) {
			return false;
		}
		$quantity       = $cart_item['quantity'];
		$product_id     = isset( $cart_item['variation_id'] ) && ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
		$product        = wc_get_product( $product_id );
		$original_price = $product->get_price( 'edit' );

		$currency_price = YayCurrencyHelper::calculate_price_by_currency( $original_price, false, $this->apply_currency );

		$options_total_default = 0; // indentify when not apply fixed (default currency)
		$options_total         = 0;
		foreach ( $cart_item['wapf'] as $field ) {
			if ( ! empty( $field['values'] ) ) {
				foreach ( $field['values'] as $value ) {
					if ( 0 === $value['price'] || 'none' === $value['price_type'] ) {
						continue;
					}
					$v                             = isset( $value['slug'] ) ? $value['label'] : $field['raw'];
					$qty_based                     = ( isset( $field['clone_type'] ) && 'qty' === $field['clone_type'] ) || ! empty( $field['qty_based'] );
					$price                         = Fields::do_pricing( $qty_based, $value['price_type'], $value['price'], $currency_price, $quantity, $v, $product_id, $cart_item['wapf'], $cart_item['wapf_field_groups'], isset( $cart_item['wapf_clone'] ) ? $cart_item['wapf_clone'] : 0, $options_total );
					$price_default_not_apply_fixed = false;
					if ( in_array( $value['price_type'], array( 'p', 'percent' ), true ) ) {
						$price                         = (float) ( $price / YayCurrencyHelper::get_rate_fee( $this->apply_currency ) );
						$price_default_not_apply_fixed = $original_price * ( $value['price'] / 100 );
						$price_default_not_apply_fixed = (float) $qty_based ? $price_default_not_apply_fixed : $price_default_not_apply_fixed / $quantity;
					}
					$options_total         = $options_total + $price;
					$options_total_default = $options_total_default + ( $price_default_not_apply_fixed ? $price_default_not_apply_fixed : $price );
				}
			}
		}
		$price_with_options     = $original_price + $options_total;
		$options_total_currency = YayCurrencyHelper::calculate_price_by_currency( $options_total, false, $this->apply_currency );
		$data                   = array(
			'options_total_default'       => $options_total_default,
			'options_total'               => $options_total,
			'options_total_currency'      => $options_total_currency,
			'currency_price'              => $currency_price,
			'original_price'              => $original_price,
			'price_with_options'          => $price_with_options,
			'price_with_options_currency' => $currency_price + $options_total_currency,
		);
		return $data;
	}

	public function recalculate_pricing( $cart_obj ) {

		foreach ( $cart_obj->get_cart() as $key => $item ) {
			$cart_item = WC()->cart->cart_contents[ $key ];

			if ( empty( $cart_item['wapf'] ) ) {
				continue;
			}

			$wapf_data = $this->get_data_options_info( $cart_item );
			if ( ! empty( $wapf_data ) ) {
				SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'price_with_options_by_currency', $wapf_data['price_with_options_currency'] );
				SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'wapf_item_price_options', $wapf_data['options_total_currency'] );
				SupportHelper::set_cart_item_objects_property( WC()->cart->cart_contents[ $key ]['data'], 'wapf_item_base_price', $wapf_data['currency_price'] );
			}
		}

	}
}
