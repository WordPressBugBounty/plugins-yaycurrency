<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
use Bundler\Controllers\OfferController;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://wcbundler.com/

class BundlerPro {
	use SingletonTrait;

	private $apply_currency = array();
	protected $offer_controller;

	public function __construct() {

		if ( ! defined( 'WBDL_VERSION' ) ) {
			return;
		}

		$this->apply_currency   = YayCurrencyHelper::detect_current_currency();
		$this->offer_controller = new OfferController();

		add_filter( 'wbdl_get_wmc_price', array( $this, 'wbdl_get_wmc_price' ), 10, 3 );

		// product price hooks
		add_filter( 'woocommerce_product_get_price', array( $this, 'wbdl_revert_product_price' ), 100, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'wbdl_revert_product_variation_price' ), 100, 2 );

	}

	public function wbdl_get_wmc_price( $price, $wmc_price = false, $is_wmc_custom_price = 'on' ) {
		if ( ! $price ) {
			return;
		}
		$current_currency = $this->apply_currency['currency'];
		if ( Helper::default_currency_code() !== $current_currency ) {
			$bundle_price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
			return $bundle_price;
		}
		return $price;
	}

	public function wbdl_revert_product_price( $price, $product ) {
		$product_id = $product->get_id();
		$bundles    = $this->offer_controller->get_the_bundles_desc( $product_id );
		if ( $bundles && is_array( $bundles ) ) {
			return YayCurrencyHelper::reverse_calculate_price_by_currency( $price, $this->apply_currency );
		}

		return $price;
	}

	public function wbdl_revert_product_variation_price( $price, $variation ) {
		$product_id = $variation->get_parent_id();
		$bundles    = $this->offer_controller->get_the_bundles_desc( $product_id );
		if ( $bundles && is_array( $bundles ) ) {
			return YayCurrencyHelper::reverse_calculate_price_by_currency( $price, $this->apply_currency );
		}

		return $price;
	}
}
