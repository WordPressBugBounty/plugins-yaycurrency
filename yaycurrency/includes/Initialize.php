<?php
namespace Yay_Currency;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Utils\SingletonTrait;

/**
 * Yay_Currency Plugin Initializer
 */
class Initialize {

	use SingletonTrait;

	/**
	 * The Constructor that load the engine classes
	 */
	protected function __construct() {
		// Engine
		\Yay_Currency\Engine\Hooks::get_instance();
		\Yay_Currency\Engine\Ajax::get_instance();

		// Register
		\Yay_Currency\Engine\Register\RegisterFacade::get_instance();
		\Yay_Currency\Engine\Register\RestAPI::get_instance();

		// BEPages
		\Yay_Currency\Engine\BEPages\WooCommerceFilterAnalytics::get_instance();
		\Yay_Currency\Engine\BEPages\WooCommerceFilterReport::get_instance();
		\Yay_Currency\Engine\BEPages\Settings::get_instance();
		\Yay_Currency\Engine\BEPages\FixedPricesPerProduct::get_instance();
		\Yay_Currency\Engine\BEPages\WooCommerceSettingGeneral::get_instance();
		\Yay_Currency\Engine\BEPages\WooCommerceOrderAdmin::get_instance();

		// Appearance
		\Yay_Currency\Engine\Appearance\MenuDropdown::get_instance();
		\Yay_Currency\Engine\Appearance\Widget::get_instance();

		// FEPages
		\Yay_Currency\Engine\FEPages\WooCommerceCurrency::get_instance();
		\Yay_Currency\Engine\FEPages\WooCommerceCheckoutPage::get_instance();
		\Yay_Currency\Engine\FEPages\SingleProductDropdown::get_instance();
		\Yay_Currency\Engine\FEPages\Shortcodes::get_instance();

		// =========COMPATIBLES==========

		// Themes
		\Yay_Currency\Engine\Compatibles\BreakdanceTheme::get_instance();
		\Yay_Currency\Engine\Compatibles\WoodmartTheme::get_instance();

		// Caches
		\Yay_Currency\Engine\Compatibles\WooCommerceProductOptions::get_instance();

		// =======Plugins==========
		\Yay_Currency\Engine\Compatibles\PayTr::get_instance();
		\Yay_Currency\Engine\Compatibles\ThirdPartyPlugins::get_instance();
		\Yay_Currency\Engine\Compatibles\AdvancedProductFieldsForWooCommerce::get_instance();

		\Yay_Currency\Engine\Compatibles\BundlerPro::get_instance();
		\Yay_Currency\Engine\Compatibles\WPCProductBundles::get_instance();
		\Yay_Currency\Engine\Compatibles\B2BMarket::get_instance();
		\Yay_Currency\Engine\Compatibles\B2BKingPro::get_instance();
		\Yay_Currency\Engine\Compatibles\BookingsAppointmentsForWooCommercePremium::get_instance();
		\Yay_Currency\Engine\Compatibles\Cartflows::get_instance();
		\Yay_Currency\Engine\Compatibles\CheckoutWC::get_instance();
		\Yay_Currency\Engine\Compatibles\Dokan::get_instance();
		\Yay_Currency\Engine\Compatibles\EventTickets::get_instance();
		\Yay_Currency\Engine\Compatibles\RoleBasedPricingFoWooCommerce::get_instance();
		\Yay_Currency\Engine\Compatibles\HivePress::get_instance();
		\Yay_Currency\Engine\Compatibles\PaymentPluginsBraintreeForWooCommerce::get_instance();
		\Yay_Currency\Engine\Compatibles\JetSmartFilters::get_instance();

		\Yay_Currency\Engine\Compatibles\FunnelKitAutomations::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommerceSimpleAuction::get_instance();

		\Yay_Currency\Engine\Compatibles\WooCommerceSubscriptions::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommercePointsAndRewards::get_instance();
		\Yay_Currency\Engine\Compatibles\BuyOnceOrSubscribeWooCommerceSubscriptions::get_instance();
		\Yay_Currency\Engine\Compatibles\WPCFrequentlyBoughtTogetherForWooCommerce::get_instance();

		\Yay_Currency\Engine\Compatibles\WooCommerceProductFeed::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommercePayments::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommercePayPalPayments::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommerceStripePaymentGateway::get_instance();
		\Yay_Currency\Engine\Compatibles\WooDiscountRules::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommerceTMExtraProductOptions::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommerceProductAddons::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommerceProductAddOnsUltimate::get_instance();
		\Yay_Currency\Engine\Compatibles\WoocommerceCustomProductAddons::get_instance();
		\Yay_Currency\Engine\Compatibles\QuantityDiscountsAndPricingForWoocommerce::get_instance();
		\Yay_Currency\Engine\Compatibles\Barn2WooCommerceWholesalePro::get_instance();
		\Yay_Currency\Engine\Compatibles\Barn2WooCommerceDiscountManager::get_instance();
		\Yay_Currency\Engine\Compatibles\YayExtra::get_instance();
		\Yay_Currency\Engine\Compatibles\WPFunnels::get_instance();

		\Yay_Currency\Engine\Compatibles\WooCommerceTeraWallet::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommerceProductBundles::get_instance();
		\Yay_Currency\Engine\Compatibles\LearnPress::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommerceNameYourPrice::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommerceRequestAQuote::get_instance();
		\Yay_Currency\Engine\Compatibles\PPOM::get_instance();
		\Yay_Currency\Engine\Compatibles\YITHPointsAndRewards::get_instance();
		\Yay_Currency\Engine\Compatibles\YITHWoocommerceGiftCards::get_instance();
		\Yay_Currency\Engine\Compatibles\YITHWooCommerceAddOnsExtraPremiumOptions::get_instance();
		\Yay_Currency\Engine\Compatibles\YITHWooCommerceSubscription::get_instance();
		\Yay_Currency\Engine\Compatibles\YITHBookingAndAppointmentForWooCommercePremium::get_instance();
		\Yay_Currency\Engine\Compatibles\WoocommerceGiftCards::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommerceDeposits::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommerceBookings::get_instance();
		\Yay_Currency\Engine\Compatibles\WooCommerceAppointments::get_instance();
		\Yay_Currency\Engine\Compatibles\TranslatePressMultilingual::get_instance();
		\Yay_Currency\Engine\Compatibles\TieredPricingTableForWooCommerce::get_instance();
		\Yay_Currency\Engine\Compatibles\Measurement_Price_Calculator::get_instance();
		\Yay_Currency\Engine\Compatibles\ModernCart::get_instance();
		\Yay_Currency\Engine\Compatibles\PaymentGatewayForPayPalWooCommerce::get_instance();

		\Yay_Currency\Engine\Compatibles\FunnelKitPlugins::get_instance();
		\Yay_Currency\Engine\Compatibles\TravelBooking::get_instance();
		\Yay_Currency\Engine\Compatibles\WooPaymentDiscounts::get_instance();
		\Yay_Currency\Engine\Compatibles\WCDP::get_instance();
		\Yay_Currency\Engine\Compatibles\SubscribersMembersBasedPricing::get_instance();

		// Shipping plugins
		\Yay_Currency\Engine\Compatibles\WooCommerceShipit::get_instance();
	}
}
