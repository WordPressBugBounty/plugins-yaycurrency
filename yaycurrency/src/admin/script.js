(function ($) {
    'use strict';
    var yayCurrencyAdmin = function () {
        var self = this;

        self.sync_orders_button = '.yay-currency-analytics-fetch-button';
        self.loadingButtonClass = 'updating-message button';

        self.init = function () {

            // Show Notice Sync Orders to Base Currency
            self.showNotice();

            // Sync Orders to Base currency
            $(document).on('click', self.sync_orders_button, function (event) {
                event.preventDefault();
                self.fetchAnalyticsButton();
            });

        };

        self.setOpacityArea = function (elementArea, type = 'before') {
            if (type === 'before') {
                $(elementArea).css('opacity', 0.6);
            } else {
                $(elementArea).css('opacity', 1);
            }
        }

        self.showNotice = function () {
            const sync_orders = yayCurrency_Admin.sync_orders ?? false;
            if ((sync_orders && sync_orders.reverted && 'yes' === sync_orders.reverted) || !$('.woocommerce-layout__primary').length) {
                return;
            }
            const html = `<div data-wp-component="Card" style="background:#ffffff;" class="components-surface components-card woocommerce-store-alerts is-alert-update yay-currency-admin-notice-container">
            <div class="yay-currency-admin-notice-wrapper">
                <div data-wp-component="CardHeader" class="components-flex components-card__header components-card-header yay-currency-admin-notice-heading">
                    <h2 style="color: rgb(30, 30, 30);margin: 0px; font-size: calc(24px);font-weight: normal;line-height: 32px;" data-wp-component="Text" class="components-truncate components-text">${sync_orders.notice_title}</h2>
                </div>
                <div data-wp-component="CardBody" class="components-card__body components-card-body yay-currency-admin-notice-content">
                    <div class="woocommerce-store-alerts__message">${sync_orders.notice_desc}</div>
                </div>
                <div data-wp-component="CardFooter" class="components-flex components-card__footer components-card-footer yay-currency-admin-notice-footer">
                    <div class="woocommerce-store-alerts__actions"><a href="javascript:void(0)" class="components-button is-secondary yay-currency-analytics-fetch-button">${sync_orders.notice_button}</a></div>
                </div>
            </div>
            
        </div>
        `;
            $('.woocommerce-layout__primary').prepend(html)
        }

        self.fetchAnalyticsButton = function (paged = 1) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'yayCurrency_sync_orders_revert_to_base',
                    _yay_sync: 'yes',
                    _sync_currencies: yayCurrency_Admin.sync_currencies,
                    _paged: paged,
                    _nonce: yayCurrency_Admin.nonce,
                },
                beforeSend: function (res) {
                    $(self.sync_orders_button).addClass(self.loadingButtonClass);
                    $('#adminmenumain,.woocommerce-layout__header,.woocommerce-layout__main').css({
                        'pointer-events': 'none',
                        'opacity': '0.4',
                    })
                },
                success: function success(res) {
                    if (res.success) {
                        if (res.data.paged) {
                            self.fetchAnalyticsButton(res.data.paged);
                        } else {
                            $(self.sync_orders_button).removeClass(self.loadingButtonClass);
                            location.reload();
                        }
                    } else {
                        $(self.sync_orders_button).removeClass(self.loadingButtonClass);
                        location.reload();
                    }

                }
            });

        }
    };

    jQuery(document).ready(function ($) {
        var yay_currency_admin = new yayCurrencyAdmin();
        yay_currency_admin.init();
    });
})(jQuery);