'use strict';
(function ($) {

  const yay_currency = () => {
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }
  };

  // Define Filter & Action Hooks from YayCurrency
  window.yayCurrencyHooks = {
    addFilter: function (hookName, callback) {
      YayCurrency_Callback.Helper.addHook('filters', hookName, callback);
    },
    applyFilters: YayCurrency_Callback.Helper.applyFilters,
    addAction: function (hookName, callback) {
      YayCurrency_Callback.Helper.addHook('actions', hookName, callback);
    },
    doAction: YayCurrency_Callback.Helper.doAction
  };

  jQuery(document).ready(function ($) {
    yay_currency($);
    const { yayCurrency } = window;
    const currencyID = YayCurrency_Callback.Helper.getCookie(yayCurrency.cookie_name);

    // Filter by Price (WooCommerce plugin)
    YayCurrency_Callback.Helper.handleFilterByPrice(currencyID);

    $(document.body).trigger('wc_fragment_refresh');

    $(window).on('load resize scroll', YayCurrency_Callback.Helper.switcherUpwards());

    // Use Param Url
    if (yayCurrency.yay_currency_use_params) {
      if (yayCurrency.yay_currency_param__name && currencyID) {
        YayCurrency_Callback.Helper.setCookie(yayCurrency.cookie_switcher_name ?? 'yay_currency_do_change_switcher', currencyID, 1);
      }
    }
    // Switcher Action
    YayCurrency_Callback.Helper.switcherAction();

    // Convert
    YayCurrency_Callback.Helper.currencyConverter();

    // Compatible with third party [Themes / Plugins]
    yayCurrencyHooks.doAction('yayCurrencyCompatibleThirdParty', [{ currencyID: currencyID }]);

  });
})(jQuery);
