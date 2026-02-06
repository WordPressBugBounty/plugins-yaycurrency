'use strict';
(function ($) {

    // ================Add Action Hooks================
    yayCurrencyHooks.addAction('yayCurrencyCompatibleThirdParty', function (args) {
        const currencyID = args.currencyID;
        // compatible with Measurement Price Calculator plugin
        if (window.wc_price_calculator_params) {
            const applyCurrency = YayCurrency_Callback.Helper.getCurrentCurrency(currencyID),
                rateFee = YayCurrency_Callback.Helper.getRateFeeByCurrency(applyCurrency);

            window.wc_price_calculator_params.woocommerce_currency_pos =
                applyCurrency.currencyPosition;
            window.wc_price_calculator_params.woocommerce_price_decimal_sep =
                applyCurrency.decimalSeparator;
            window.wc_price_calculator_params.woocommerce_price_num_decimals =
                applyCurrency.numberDecimal;
            window.wc_price_calculator_params.woocommerce_price_thousand_sep =
                applyCurrency.thousandSeparator;

            window.wc_price_calculator_params.pricing_rules &&
                window.wc_price_calculator_params.pricing_rules.forEach((rule) => {
                    rule.price = (parseFloat(rule.price) * rateFee).toString();
                    rule.regular_price = (
                        parseFloat(rule.regular_price) * rateFee
                    ).toString();
                    rule.sale_price = (
                        parseFloat(rule.sale_price) * rateFee
                    ).toString();
                });
        }

        // Compatible with WooCommerce PayPal Payments plugin
        if (window.yayCurrency.ppc_paypal) {

            //Refresh mini cart - not on checkout page (checkout_diff_currency)
            if (yayCurrency.checkout_diff_currency && '1' === yayCurrency.checkout_diff_currency) {
                jQuery(document).ready(function ($) {
                    if (!yayCurrency.checkout_page || '1' !== yayCurrency.checkout_page) {
                        $(document.body).trigger('wc_fragment_refresh');
                    }
                });
            }

            const setOrDeleteYayPaypalCookie = (cookieName, condition) => {
                if (condition) {
                    YayCurrency_Callback.Helper.setCookie(cookieName, 'yes', +yayCurrency.cookie_lifetime_days);
                } else if (YayCurrency_Callback.Helper.getCookie(cookieName)) {
                    YayCurrency_Callback.Helper.deleteCookie(cookieName);
                }
            };

            const updateYayPaypalCookies = () => {
                setOrDeleteYayPaypalCookie('ppc_paypal_cart_or_product_page', '1' === yayCurrency.cart_page || '1' === yayCurrency.product_page);
                setOrDeleteYayPaypalCookie('ppc_paypal_checkout_page', yayCurrency.checkout_page && '1' === yayCurrency.checkout_page);
            };

            // Initial cookie setup
            updateYayPaypalCookies();

            // Update cookies on page visibility change
            $(document).on('visibilitychange', function () {
                if ('visible' === document.visibilityState) {
                    updateYayPaypalCookies();
                }
            });
        }

    });

})(jQuery);