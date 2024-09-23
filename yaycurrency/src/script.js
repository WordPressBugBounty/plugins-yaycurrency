'use strict';
(function ($) {

  const yay_currency = () => {
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }
  };

  jQuery(document).ready(function ($) {
    yay_currency($);
    const { yayCurrency } = window;
    const currencyID = YayCurrency_Callback.Helper.getCookie(yayCurrency.cookie_name);

    // Compatible with 3rd Plugins
    YayCurrency_Callback.Helper.compatibleWithThirdPartyPlugins(currencyID);

    $(document.body).trigger('wc_fragment_refresh');

    $(window).on('load resize scroll', YayCurrency_Callback.Helper.switcherUpwards());
    YayCurrency_Callback.Helper.switcherAction();
    YayCurrency_Callback.Helper.reCalculateCartSubtotalCheckoutBlocksPage();

    // Convert
    YayCurrency_Callback.Helper.currencyConverter();
  });
})(jQuery);
