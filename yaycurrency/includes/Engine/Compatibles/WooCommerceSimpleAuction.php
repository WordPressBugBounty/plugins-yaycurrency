<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\YayCurrencyHelper;
use Yay_Currency\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class WooCommerceSimpleAuction {
	use SingletonTrait;

	private $apply_currency = array();
	public function __construct() {

		if ( ! class_exists( 'WooCommerce_simple_auction' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();
		add_filter( 'woocommerce_simple_auctions_get_current_bid', array( $this, 'custom_woocommerce_simple_auction_price' ), 10, 2 );
		add_filter( 'woocommerce_place_bid_bid', array( $this, 'custom_woocommerce_place_bid_bid' ), 10, 1 );
		add_filter( 'woocommerce_simple_auctions_minimal_bid_value', array( $this, 'woocommerce_simple_auctions_minimal_bid_value' ), 10, 2 );
	}

	// WooCommerce Simple Auction.
	public function custom_woocommerce_simple_auction_price( $price, $product ) {
		$converted_price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		return $converted_price;
	}

	public function custom_woocommerce_place_bid_bid( $bid ) {
		$converted_price = YayCurrencyHelper::reverse_calculate_price_by_currency( $bid, $this->apply_currency );
		return $converted_price;
	}

	public function woocommerce_simple_auctions_minimal_bid_value( $bid_value, $product_data ) {
		$converted_price = YayCurrencyHelper::reverse_calculate_price_by_currency( $bid_value, $this->apply_currency );
		return $converted_price;
	}
}
