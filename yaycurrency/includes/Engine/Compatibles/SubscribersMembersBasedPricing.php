<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class SubscribersMembersBasedPricing {
	use SingletonTrait;

	private $apply_currency = array();
	private $afc_sp_price;
	public function __construct() {

		if ( ! function_exists( 'meowcrew_membersbasedpricing_samspfw_fs' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'yay_currency_product_prices_filters_priority', array( $this, 'yay_currency_product_prices_filters_priority' ), 10, 1 );

		add_filter( 'YayCurrency/Checkout/StoreCurrency/GetCartSubtotal', array( $this, 'yay_currency_checkout_get_subtotal_price' ), 99, 4 );

	}

	public function yay_currency_product_prices_filters_priority( $priority ) {
		$priority = 9999;
		return $priority;
	}

	public function yay_currency_checkout_get_subtotal_price( $cart_subtotal, $apply_currency, $fallback_currency, $converted_currency ) {
		return WC()->cart->subtotal;
	}
}
