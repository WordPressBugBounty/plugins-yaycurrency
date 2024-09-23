<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;

use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;

class BuyOnceOrSubscribeWooCommerceSubscriptions {
	use SingletonTrait;

	private $converted_currency;
	private $apply_currency;
	private $default_currency;
	private $is_dis_checkout_diff_currency;

	public function __construct() {

		if ( ! class_exists( 'WC_Subscriptions' ) || ! defined( 'BOS_IS_PLUGIN' ) ) {
			return;
		}

		$this->default_currency = Helper::default_currency_code();
		$this->apply_currency   = YayCurrencyHelper::detect_current_currency();

		if ( ! $this->apply_currency ) {
			return;
		}

		add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );

		add_filter( 'yay_currency_is_original_product_price', array( $this, 'is_original_product_price' ), 10, 3 );

		add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'product_price_3rd_with_condition' ), 10, 2 );
		add_filter( 'yay_currency_get_product_price_by_cart_item', array( $this, 'get_product_price_by_cart_item' ), 10, 3 );
		add_filter( 'yay_currency_get_product_price_subscription_by_cart_item', array( $this, 'get_product_price_by_cart_item' ), 10, 3 );

	}

	public function product_addons_set_cart_contents( $cart_contents, $key, $cart_item, $apply_currency ) {
		$bos4w_discounted_price = isset( $cart_item['bos4w_data'] ) && isset( $cart_item['bos4w_data']['discounted_price'] ) ? $cart_item['bos4w_data']['discounted_price'] : false;
		if ( $bos4w_discounted_price ) {
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_bos4w_discounted_price_subscription_default_currency', $bos4w_discounted_price );
			SupportHelper::set_cart_item_objects_property( $cart_contents[ $key ]['data'], 'yay_bos4w_discounted_price_subscription_current_currency', YayCurrencyHelper::calculate_price_by_currency( $bos4w_discounted_price, false, $apply_currency ) );
		}
	}

	public function is_original_product_price( $flag, $price, $product ) {
		if ( doing_filter( 'woocommerce_add_cart_item_data' ) && isset( $_REQUEST['bos4w-purchase-type'] ) && '1' === $_REQUEST['bos4w-purchase-type'] ) {
			return true;
		}
		return $flag;
	}

	public function product_price_3rd_with_condition( $price, $product ) {
		$discounted_price_currency = SupportHelper::get_cart_item_objects_property( $product, 'yay_bos4w_discounted_price_subscription_current_currency' );
		if ( $discounted_price_currency ) {
			return $discounted_price_currency;
		}
		return $price;
	}

	public function get_product_price_by_cart_item( $price, $cart_item, $apply_currency ) {
		$discounted_price_currency = SupportHelper::get_cart_item_objects_property( $cart_item['data'], 'yay_bos4w_discounted_price_subscription_current_currency' );
		if ( $discounted_price_currency ) {
			return $discounted_price_currency;
		}
		return $price;
	}
}
