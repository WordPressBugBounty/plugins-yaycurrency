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
		// Custom Add To Cart button text
		add_filter( 'single_add_to_cart_text', array( $this, 'custom_single_add_to_cart_text' ), 20, 2 );
		// Convert Auction History Data
		add_filter( 'woocommerce__auction_history_data', array( $this, 'woocommerce_auction_history_data' ), 20, 1 );
		// Bid increment
		add_filter( 'woocommerce_simple_auctions_get_increase_bid_value', array( $this, 'custom_simple_auctions_increase_value' ), 10, 2 );
		// Script Convert Auction Step
		add_action( 'wp_footer', array( $this, 'convert_step_bid_price_script' ), 999 );

		add_filter( 'woocommerce_simple_auctions_get_current_bid', array( $this, 'custom_woocommerce_simple_auction_price' ), 10, 2 );
		add_filter( 'woocommerce_place_bid_bid', array( $this, 'custom_woocommerce_place_bid_bid' ), 10, 1 );
		add_filter( 'woocommerce_simple_auctions_minimal_bid_value', array( $this, 'woocommerce_simple_auctions_minimal_bid_value' ), 10, 2 );
	}

	public function custom_single_add_to_cart_text( $button_text, $product ) {
		if ( ! function_exists( 'wsa_is_auction' ) || ! wsa_is_auction() ) {
			return $button_text;
		}
		$regular_price = apply_filters( 'yay_currency_convert_price', $product->get_regular_price() );
		/* translators: %s: regular price */
		$button_text = sprintf( __( 'Buy now for %s', 'wc_simple_auctions' ), wc_price( $regular_price ) );
		return $button_text;
	}

	public function woocommerce_auction_history_data( $auction_history_data ) {
		foreach ( $auction_history_data as $key => $auction_data ) {
			$auction_history_data[ $key ]->bid = apply_filters( 'yay_currency_convert_price', $auction_data->bid );
		}
		return $auction_history_data;
	}

	public function custom_simple_auctions_increase_value( $price, $product ) {
		$converted_price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
		return $converted_price;
	}

	public function convert_step_bid_price_script() {
		if ( is_product() || is_singular( 'product' ) ) {
			?>
			<script>
				var yay_currency_rate = <?php echo esc_js( YayCurrencyHelper::get_rate_fee( $this->apply_currency ) ); ?>;
				jQuery(document).ready(function ($) {
					const  bidElement = $('input[name="bid_value"]');
					if(bidElement.length){
						const bidValue = bidElement.attr('step');
						if (!isNaN(price)) {
							bidElement.attr('step', bidValue*yay_currency_rate);
						}
					}
				});	
			</script>
			<?php
		}
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
