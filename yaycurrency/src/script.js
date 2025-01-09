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

    // Use Param Url
    if (yayCurrency.yay_currency_use_params) {
      if (yayCurrency.yay_currency_param__name && currencyID) {
        YayCurrency_Callback.Helper.setCookie(yayCurrency.cookie_switcher_name ?? 'yay_currency_do_change_switcher', currencyID, 1);
      }
    }

    $(window).on('load resize scroll', YayCurrency_Callback.Helper.switcherUpwards());
    YayCurrency_Callback.Helper.switcherAction();
    YayCurrency_Callback.Helper.reCalculateCartSubtotalCheckoutBlocksPage();

    // Convert
    YayCurrency_Callback.Helper.currencyConverter();
  });
})(jQuery);
