(function ($) {
    'use strict';
    var yayDokanScriptAdmin = function () {
        var self = this;
        self.DokanPage = 'admin.php?page=dokan#/';
        self.DokanLiteArgs = {
            dashboard: self.DokanPage,
            withdraw: self.DokanPage + 'withdraw?status=pending',
            reverseWithdrawal: self.DokanPage + 'reverse-withdrawal',
            vendors: self.DokanPage + 'vendors'
        };
        if (yay_dokan_admin_data.dokan_pro) {
            self.DokanLiteArgs.reports = self.DokanPage + 'reports';
            self.DokanLiteArgs.reportsByDay = self.DokanPage + 'reports?tab=report&type=by-day';
            self.DokanLiteArgs.reportsByVendor = self.DokanPage + 'reports?tab=report&type=by-vendor';
            self.DokanLiteArgs.reportsByYear = self.DokanPage + 'reports?tab=report&type=by-year';

            self.DokanLiteArgs.refundPending = self.DokanPage + 'refund?status=pending';
            self.DokanLiteArgs.refundApproved = self.DokanPage + 'refund?status=completed';
            self.DokanLiteArgs.refundCancelled = self.DokanPage + 'refund?status=cancelled';
        }

        self.atAGalanceAreaDashBoard = '.postbox.dokan-postbox.dokan-status';
        self.atAGalanceSaleAreaDashBoard = self.atAGalanceAreaDashBoard + ' li.sale a';
        self.atAGalanceCommissionAreaDashBoard = self.atAGalanceAreaDashBoard + ' li.commission a';

        self.refundWrapperArea = '.dokan-refund-wrapper';
        self.refundArrays = ['refundPending', 'refundApproved', 'refundCancelled'];

        self.reportsLogsWrapperArea = '.reports-page .logs-area';
        self.tableReportsLogs = self.reportsLogsWrapperArea + ' table.wp-list-table tbody tr.yay-currency-report-log';
        self.tooltipReportLogs = '<span style="cursor:pointer" title="Fee received by Seller" class="notication-tooltip yay-currency-reports-log-tooltip" data-original-title="Fee received by Seller">!</span>';
        self.init = function () {

            // Init Load
            const currentUrl = window.location.href,
                getUrl = currentUrl.replace(yay_dokan_admin_data.admin_url, ''),
                currentPage = self.getKeyByValue(self.DokanLiteArgs, getUrl);

            self.YayDokanAction(currentPage);

            if (yay_dokan_admin_data.dokan_pro) {

                $(document).on("ajaxComplete", function (event, request, settings) {
                    if (-1 != settings.url.toLocaleLowerCase().indexOf('wp-json/dokan/v1/admin/logs')) {
                        self.YayDoKanReportsLogs();
                    }
                });
            }

            // Click Submenu
            $(document).on('click', '.wp-submenu.wp-submenu-wrap li', function (event) {
                const _this = $(this),
                    getCurrentUrl = _this.find('a').attr('href');
                const findKey = self.getKeyByValue(self.DokanLiteArgs, getCurrentUrl);
                self.YayDokanAction(findKey);
            });
            // Click Report Submenu --- Dokan Pro
            $(document).on('click', '.report-area .dokan-report-sub li', function (event) {
                const _this = $(this),
                    findUrl = self.DokanPage + _this.find('a').attr('href').replace('#/', ''),
                    findKeyInReport = self.getKeyByValue(self.DokanLiteArgs, findUrl);

                self.YayDoKanReports(findKeyInReport, true);

            });

            // Click Report Nav Tab --- Dokan Pro
            $(document).on('click', '.reports-page .nav-tab-wrapper.woo-nav-tab-wrapper .nav-tab', function (event) {
                const _this = $(this),
                    findUrl = 'admin.php?page=dokan' + _this.attr('href'),
                    findKeyInReport = self.getKeyByValue(self.DokanLiteArgs, findUrl);

                self.YayDoKanReports(findKeyInReport, true);

            });

            // Click Submenu Refunds
            $(document).on('click', self.refundWrapperArea + ' ul.subsubsub li', function (event) {
                const _this = $(this),
                    findUrl = self.DokanPage + _this.find('a').attr('href').replace('#/', ''),
                    findKeyInRefund = self.getKeyByValue(self.DokanLiteArgs, findUrl);
                self.YayDokanRefund(findKeyInRefund);
            });

        };

        self.YayDokanAction = function (findKey) {
            self.YayDoKanDashBoard(findKey);
            if (yay_dokan_admin_data.dokan_pro) {
                self.YayDoKanReports(findKey);
                self.YayDokanRefund(findKey);
            }
        }

        self.getKeyByValue = function (object, value) {
            return Object.keys(object).find(key => object[key] === value);
        };

        self.YayDoKanGetDataThisMonth = function () {
            $.ajax({
                url: yay_dokan_admin_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'yay_dokan_admin_custom_dashboard',
                    _nonce: yay_dokan_admin_data.nonce,
                },
                beforeSend: function (res) {
                    $(self.atAGalanceAreaDashBoard).css('opacity', 0.2);
                },
                success: function success(res) {

                    if (res.success && res.data && res.data.report_data) {
                        self.customDokanAtAGalanceArea(res.data.report_data);
                    }

                }
            });
        }

        self.YayDoKanDashBoard = function (findKey) {
            if (findKey && 'dashboard' === findKey) {
                self.YayDoKanGetDataThisMonth();
            }
        }

        self.customDokanAtAGalanceArea = function (reportData, actionClick = false) {
            $(self.atAGalanceAreaDashBoard).css('opacity', 0.2);
            $(self.atAGalanceAreaDashBoard).css('opacity', 0.2);
            var intervalTime = setInterval(function () {
                if ($(self.atAGalanceSaleAreaDashBoard).length > 0) {
                    clearInterval(intervalTime);
                }

                $(self.atAGalanceAreaDashBoard).css('opacity', 1);
                if ($(self.atAGalanceSaleAreaDashBoard).length > 0) {
                    const salesThisMonth = actionClick ? reportData.sales.this_period : reportData.sales.this_month;
                    $(self.atAGalanceSaleAreaDashBoard).find('strong').html(salesThisMonth);
                    if ($(self.atAGalanceSaleAreaDashBoard).find('.up').length > 0) {
                        $(self.atAGalanceSaleAreaDashBoard).find('.up').html(reportData.sales.parcent);
                    }
                    if ($(self.atAGalanceSaleAreaDashBoard).find('.down').length > 0) {
                        $(self.atAGalanceSaleAreaDashBoard).find('.down').html(reportData.sales.parcent);
                    }
                }

                if ($(self.atAGalanceCommissionAreaDashBoard).length > 0) {
                    const earningThisMonth = actionClick ? reportData.earning.this_period : reportData.earning.this_month;
                    $(self.atAGalanceCommissionAreaDashBoard).find('strong').html(earningThisMonth);
                    if ($(self.atAGalanceCommissionAreaDashBoard).find('.up').length > 0) {
                        $(self.atAGalanceCommissionAreaDashBoard).find('.up').html(reportData.earning.parcent);
                    }
                    if ($(self.atAGalanceCommissionAreaDashBoard).find('.down').length > 0) {
                        $(self.atAGalanceCommissionAreaDashBoard).find('.down').html(reportData.earning.parcent);
                    }
                }

            }, 500);
        }
        // DOKAN PRO
        self.changeDokanAtAGalanceAreaHTML = function (findKey) {

            let inputData = [];
            switch (findKey) {
                case "reportsByYear":
                    const year = $(".form-inline.report-filter").find('select.dokan-input').val();
                    self.YayDoKanReportsByYear(year);
                    break;
                case 'reportsByVendor':
                    $(".form-inline.report-filter").find('.form-group input.dokan-input').each(function (index) {
                        inputData.push($(this).val());
                    });
                default:
                    $(".form-inline.report-filter").find('input.dokan-input').each(function (index) {
                        inputData.push($(this).val());
                    });
                    break;
            }
            const sellerId = $('.multiselect__option--selected').length > 0 ? $('.multiselect__option--selected').text() : '';

            if (inputData.length > 0) {
                $.ajax({
                    url: yay_dokan_admin_data.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'yay_dokan_admin_custom_reports',
                        from: inputData[0],
                        to: inputData[1],
                        seller_id: sellerId,
                        _nonce: yay_dokan_admin_data.nonce,
                    },
                    beforeSend: function (res) {
                        $(self.atAGalanceAreaDashBoard).css('opacity', 0.2);
                    },
                    success: function success(res) {

                        if (res.success && res.data && res.data.report_data) {
                            self.customDokanAtAGalanceArea(res.data.report_data, true);
                        }

                    }
                });
            }
        }

        self.YayDoKanReports = function (findKey, actionClick = false) {
            const data_reports = ['reports', 'reportsByDay', 'reportsByVendor', 'reportsByYear'];
            if (findKey && $.inArray(findKey, data_reports) != -1) {
                if (!actionClick) {
                    self.YayDoKanGetDataThisMonth();
                } else {
                    self.changeDokanAtAGalanceAreaHTML(findKey)
                }

                $(document).on('click', '.form-inline.report-filter button[type="submit"]', function (event) {
                    self.changeDokanAtAGalanceAreaHTML(findKey);
                });

            }

        }

        self.YayDoKanReportsByYear = function (year) {

            $.ajax({
                url: yay_dokan_admin_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'yay_dokan_admin_reports_by_year',
                    _year: year,
                    _nonce: yay_dokan_admin_data.nonce,
                },
                beforeSend: function (res) {
                    $(self.atAGalanceAreaDashBoard).css('opacity', 0.2);
                },
                success: function success(res) {

                    if (res.success && res.data && res.data.report_data) {
                        self.customDokanAtAGalanceArea(res.data.report_data, true);
                    }

                }
            });
        }

        self.YayDoKanReportsLogs = function () {

            var intervalTime = setInterval(function () {

                if (!$('.logs-area .table-loading .table-loader').length) {
                    const listRowOrderIds = $('.reports-page .logs-area table.wp-list-table tbody tr td.order_id');

                    if (listRowOrderIds.length > 0) {

                        let orderIds = [];

                        listRowOrderIds.each(function (index) {
                            const orderId = $(this).find('a').text().replace('#', '');
                            $(this).closest('tr').addClass('yay-currency-report-log').attr('data-order_id', orderId);
                            orderIds.push(orderId);
                        });

                        const paramsArgs = ["vendor_id", "order_status", "start_date", "end_date", "order_id", "page"];
                        const currentURL = $(location).attr('href');
                        const reportArgs = {};

                        paramsArgs.forEach(function (item) {
                            reportArgs[item] = !self.getValueinParam(item, currentURL) ? '' : self.getValueinParam(item, currentURL);
                        });

                        $.ajax({
                            url: yay_dokan_admin_data.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'yay_dokan_admin_custom_reports_logs',
                                reportArgs: reportArgs,
                                _nonce: yay_dokan_admin_data.nonce,
                            },
                            beforeSend: function (res) {
                                $(self.reportsLogsWrapperArea).css('opacity', 0.2);
                            },
                            success: function success(res) {
                                $(self.reportsLogsWrapperArea).css('opacity', 1);

                                if (res.success && res.data.reports_logs) {

                                    $(self.tableReportsLogs).each(function (index) {

                                        const orderId = +$(this).find('td.order_id').text().replace('#', '');
                                        if (res.data.reports_logs[orderId]) {

                                            $(this).find('td.order_total').html(res.data.reports_logs[orderId].order_total);
                                            $(this).find('td.vendor_earning').html(res.data.reports_logs[orderId].vendor_earning);
                                            $(this).find('td.commission').html(res.data.reports_logs[orderId].commission);
                                            $(this).find('td.dokan_gateway_fee').html(res.data.reports_logs[orderId].dokan_gateway_fee);
                                            $(this).find('td.shipping_total div').html(res.data.reports_logs[orderId].shipping_total);
                                            $(this).find('td.shipping_total_tax div').html(res.data.reports_logs[orderId].shipping_total_tax);
                                            $(this).find('td.tax_total div').html(res.data.reports_logs[orderId].tax_total);

                                        }


                                    });
                                }

                            }
                        });
                    }

                    clearInterval(intervalTime);
                }

            }, 500);
        }

        self.YayDokanRefund = function (findKey) {
            // 0 : pending, 1: approved, 2: cancel
            if (self.refundArrays.includes(findKey)) {

                var intervalTime = setInterval(function () {
                    if (!$('.table-loading .table-loader').length) {
                        clearInterval(intervalTime);
                    }
                    const listRowOrderIds = $('.dokan-refund-wrapper table.wp-list-table tbody tr td.order_id');
                    if (listRowOrderIds.length > 0) {
                        let orderIds = [];
                        listRowOrderIds.each(function (index) {
                            const orderId = $(this).find('a strong').text().replace('#', '');
                            $(this).closest('tr').find('td.amount').attr('data-order_id', orderId);
                            orderIds.push(orderId);
                        });
                        let refundStatus = 0;
                        if ('refundApproved' === findKey) {
                            refundStatus = 1;
                        } else if ('refundCancelled' === findKey) {
                            refundStatus = 2;
                        }

                        $.ajax({
                            url: yay_dokan_admin_data.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'yay_dokan_admin_custom_refund_request',
                                orderIds: orderIds,
                                status: refundStatus,
                                _nonce: yay_dokan_admin_data.nonce,
                            },
                            beforeSend: function (res) {
                                $(self.refundWrapperArea).css('opacity', 0.2);
                            },
                            success: function success(res) {
                                $(self.refundWrapperArea).css('opacity', 1);

                                if (res.success && res.data.refunds) {
                                    $('.dokan-refund-wrapper table.wp-list-table tbody tr td.amount').each(function (index) {
                                        $(this).html(res.data.refunds[$(this).data('order_id')]);
                                    });
                                }

                            }
                        });
                    }

                }, 500);

            }
        }

        self.getValueinParam = function (param, queryString) {
            const urlParams = new URLSearchParams(queryString);
            return urlParams.get(param);
        }
    };

    jQuery(document).ready(function ($) {
        var yayDokanAd = new yayDokanScriptAdmin();
        yayDokanAd.init();
    });
})(jQuery);