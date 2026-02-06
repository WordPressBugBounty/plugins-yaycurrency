'use strict';
(function ($) {

    yayCurrencyHooks.addAction('yayCurrencyCompatibleThirdParty', function (args) {
        const currencyID = args.currencyID;

        // Display Approximate Price in Checkout Blocks pages
        if (typeof YayCurrency_Blocks.Helper.approximatePriceCheckoutBlocks === 'function' && 'yes' === yayCurrency.show_approximate_price) {
            YayCurrency_Blocks.Helper.approximatePriceCheckoutBlocks(currencyID);
        }

        // Set header param to detect cart or checkout blocks when call REST API
        if (typeof YayCurrency_Blocks.Helper.setHeaderParamToDetectCartOrCheckoutBlocks === 'function') {
            YayCurrency_Blocks.Helper.setHeaderParamToDetectCartOrCheckoutBlocks();
        }

        // Format Price in Cart & Checkout Blocks pages
        // if (typeof YayCurrency_Blocks.Helper.formatPriceCartCheckoutBlocks === 'function' && 'yes' === yayCurrency.formatted_price_woo_blocks) {
        //     YayCurrency_Blocks.Helper.formatPriceCartCheckoutBlocks(currencyID);
        // }

    });

})(jQuery);