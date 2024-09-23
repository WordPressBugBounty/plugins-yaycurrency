'use strict';
const ready = function ready(fn) {
  return (
    document.attachEvent
      ? document.readyState === 'complete'
      : document.readyState !== 'loading'
  )
    ? fn()
    : document.addEventListener('DOMContentLoaded', fn);
};
ready(function () {
  const { addFilter } = wp.hooks;
  const { __ } = wp.i18n;

  const addCurrencyFilters = (filters) => {
    return [
      {
        label: __('Currency', 'yay-currency'),
        staticParams: [],
        param: 'currency',
        showFilters: () => true,
        defaultValue: yayCurrencyAnalytics.defaultCurrency,
        filters: [...(wcSettings.multiCurrency || [])],
      },
      ...filters,
    ];
  };

  const currencies = Object.assign({}, ...yayCurrencyAnalytics.currencies);
  const updateReportCurrencies = (config, { currency }) => {
    if (currency && currencies[currency]) {
      return currencies[currency];
    }
    return config;
  };

  addFilter(
    'woocommerce_admin_report_currency',
    'yay-currency',
    updateReportCurrencies
  );
});
