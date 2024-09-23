(function ($) {
    'use strict';
    var yayDokanScript = function () {
        var self = this;
        self.dokanOrderWrapper = '#order-filter';
        self.dokanWithdrawArea = '.dokan-withdraw-area';
        self.balanceArea = '.dokan-withdraw-area .dokan-panel.dokan-panel-default:first-child .dokan-w8';

        self.regularPrice = '#_regular_price';
        self.salePrice = '#_sale_price';

        self.dokanReporstArea = '.dokan-reports-area .dokan-dashboard-header .entry-title';

        self.approximatelyPrice = yay_dokan_data.approximately_price && 'yes' === yay_dokan_data.approximately_price;

        self.init = function () {
            // ORDER TABLE AREA
            self.customDokanOrderTable();

            if (self.approximatelyPrice) {
                //WITHDRAW AREA
                self.convertMiniWithdrawAmount();
                self.customWithDrawArea();
                // PRODUCT AREA
                self.addNewProductAction();
                self.customProductArea();
            }

            if (yay_dokan_data.dokan_pro) {
                // REPORT AREA
                if ($(self.dokanReporstArea).length) {
                    if (!$(self.dokanReporstArea + ' .yay-currency-single-page-switcher').length) {
                        $(self.dokanReporstArea).append(yayCurrency.shortCode);
                    }
                }

                // REPORTS STATEMENT AREA
                self.customReportStatementArea();
                // COUPON AREA
                self.customCouponsArea();

            }

        };

        self.convertMiniWithdrawAmount = function () {
            const is_dokan_withdraw_area = $(self.dokanWithdrawArea);
            if (is_dokan_withdraw_area.length > 0) {
                const mini_withdraw_amount_area = self.balanceArea + ' p';
                $(mini_withdraw_amount_area).find("strong").each(function (index) {
                    if (0 != index) {
                        $(this).html(yay_dokan_data.withdraw_limit_currency);
                    }
                });

            }
        }

        self.customDokanOrderTable = function () {
            const dokan_earnings = $(self.dokanOrderWrapper).find('.dokan-order-earning');
            if (dokan_earnings.length > 0) {
                $(self.dokanOrderWrapper).css('opacity', 0.2);
                dokan_earnings.each(function (index) {
                    const _earning = $(this),
                        _parent = _earning.closest('tr'),
                        _order_total = _parent.find('.dokan-order-total'),
                        _order = _parent.find('.dokan-order-id a');

                    const _order_href = _order.attr('href'),
                        _order_id = self.getValueinParam('order_id', _order_href);
                    $.ajax({
                        url: yay_dokan_data.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'yay_custom_earning_from_order_table',
                            order_id: _order_id,
                            seller_id: yay_dokan_data.seller_id,
                            _nonce: yay_dokan_data.nonce,
                        },
                        success: function success(res) {
                            setTimeout(() => {
                                $(self.dokanOrderWrapper).css('opacity', 1);
                            }, 500);
                            if (res.success && res.data.earning && res.data.order_total) {
                                _earning.html(res.data.earning)
                                _order_total.html(res.data.order_total)
                            }
                        },
                        complete: function complete() {
                            setTimeout(() => {
                                $(self.dokanOrderWrapper).css('opacity', 1);
                            }, 500);
                        },
                    });
                });
            }
        }

        self.customWithDrawArea = function () {
            if (yay_dokan_data.last_payment_details) {
                const withdrawArea = $('.dokan-panel.dokan-panel-default .dokan-w8');
                withdrawArea.each(function (index) {
                    if (1 == index) {
                        $(this).find('p').html(yay_dokan_data.last_payment_details);
                    }
                });

            }

        }

        self.addNewProductAction = function () {
            $(document).on('click', '.dokan-add-new-product', function () {
                self.customProductArea('add-product');
            });
        }

        self.customProductArea = function (_action = 'edit-product') {
            // SIMPLE
            const regularPrice = $(self.regularPrice),
                salePrice = $(self.salePrice);
            if (regularPrice.length > 0 && yay_dokan_data.yay_dokan_regular_price) {
                const _parent = regularPrice.closest('.dokan-input-group'),
                    _regularPriceParent = 'edit-product' === _action ? '.regular-price' : '.content-half-part',
                    regularPriceArea = _parent.closest(_regularPriceParent);
                regularPriceArea.append(yay_dokan_data.yay_dokan_regular_price);

                $(document).on('input', self.regularPrice, function (event) {
                    event.preventDefault();
                    const priceVal = $(this).val();
                    self.customApproximatelyPrice(priceVal, $('.yay-dokan-regular-price-wrapper'));
                });

                $(self.regularPrice).trigger('input');

            }

            if (salePrice.length > 0 && yay_dokan_data.yay_dokan_sale_price) {
                const _parent = salePrice.closest('.dokan-input-group'),
                    salePriceArea = _parent.closest('.sale-price');
                salePriceArea.append(yay_dokan_data.yay_dokan_sale_price);

                $(document).on('input', self.salePrice, function (event) {
                    event.preventDefault();
                    const priceVal = $(this).val();
                    self.customApproximatelyPrice(priceVal, $('.yay-dokan-sale-price-wrapper'));
                });

                $(self.salePrice).trigger('input');
            }

        }

        self.customCouponsArea = function (_action = 'edit-coupon') {
            if (yay_dokan_data.default_symbol) {
                $('label[for="amount"]').append(' (' + yay_dokan_data.default_symbol + ')');
            }

            if (self.approximatelyPrice) {
                const couponAmount = '#coupon_amount';
                $(couponAmount).closest('.dokan-w5').append(yay_dokan_data.yay_dokan_coupon_amount)
                $(document).on('input', couponAmount, function (event) {
                    event.preventDefault();
                    const priceVal = $(this).val();
                    self.customApproximatelyPrice(priceVal, $('.yay-dokan-coupon-amount-wrapper'));
                });
                $(couponAmount).trigger('input');
            }

        }

        self.customApproximatelyPrice = function (_price, elem) {
            $.ajax({
                url: yay_dokan_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'yay_dokan_custom_approximately_price',
                    _price: _price,
                    _nonce: yay_dokan_data.nonce,
                },
                beforeSend: function (res) {
                },
                success: function success(res) {
                    res.success ? elem.html(res.data.price_html) : elem.html('');
                }
            });
        }

        self.customReportStatementArea = function () {
            if (yay_dokan_data.yay_dokan_report_statement_page) {
                $.ajax({
                    url: yay_dokan_data.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'yay_dokan_custom_reports_statement',
                        seller_id: yay_dokan_data.seller_id,
                        start_date: yay_dokan_data.yay_dokan_report_statement_from,
                        end_date: yay_dokan_data.yay_dokan_report_statement_to,
                        opening_balance: yay_dokan_data.yay_dokan_report_statement_opening_balance,
                        _nonce: yay_dokan_data.nonce,
                    },
                    beforeSend: function (res) {
                        $('.dokan-report-wrap').css('opacity', 0.4)
                    },
                    success: function success(res) {
                        $('.dokan-report-wrap').css('opacity', 1)
                        if (res.success && res.data.statements) {
                            const
                                ReportsStatementTable = $('.dokan-report-wrap table.table-striped tbody tr'),
                                totalReports = $('.dokan-report-wrap table.table-striped tbody tr:last td'),
                                length = ReportsStatementTable.length;

                            ReportsStatementTable.each(function (index) {
                                const rows = $(this).find('td');
                                if ('yes' === yay_dokan_data.yay_dokan_report_statement_opening_balance) {
                                    if (0 === index) {
                                        return;
                                    }
                                }
                                if ((length - 1) === index) {
                                    return;
                                }

                                $(rows[4]).html(res.data.statements[index].debit)
                                $(rows[5]).html(res.data.statements[index].credit)
                                $(rows[6]).html(res.data.total_balance)
                            });

                            $(totalReports[4]).find('b').html(res.data.total_debit)
                            $(totalReports[5]).find('b').html(res.data.total_credit)
                            $(totalReports[6]).find('b').html(res.data.total_balance)
                        }
                    },

                });
            }
        }

        self.getValueinParam = function (param, url_string) {
            const url = new URL(url_string);
            return url.searchParams.get(param);
        }

    };

    jQuery(document).ready(function ($) {
        var yayDokanFr = new yayDokanScript();
        yayDokanFr.init();
    });
})(jQuery);