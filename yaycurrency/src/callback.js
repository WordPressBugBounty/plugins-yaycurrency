
(function ($, window) {
    YayCurrency_Callback = window.YayCurrency_Callback || {};
    yay_currency_data_args = {
        common_data_args: {
            yayCurrencySymbolWrapper: 'span.woocommerce-Price-currencySymbol',
            yayCurrencySwitcher: '.yay-currency-single-page-switcher',
            yayCurrencyWidget: '.yay-currency-widget-switcher',
            yayCurrencyBlock: '.yay-currency-block-switcher',
            yayCurrencyCartContents: 'a.cart-contents',
        },
        converter_args: {
            converterWrapper: '.yay-currency-converter-container',
            converterAmount: '.yay-currency-converter-amount',
            converterFrom: '.yay-currency-converter-from-currency',
            converterTo: '.yay-currency-converter-to-currency',
            converterResultWrapper: '.yay-currency-converter-result-wrapper',
            converterResultAmount: '.yay-currency-converter-amount-value',
            converterResultFrom: '.yay-currency-converter-from-currency-code',
            converterResultValue: '.yay-currency-converter-result-value',
            converterResultTo: '.yay-currency-converter-to-currency-code',
        },

        switcher_data_args: {
            activeClass: 'active',
            upwardsClass: 'upwards',
            openClass: 'open',
            selectedClass: 'selected',
            currencySwitcher: '.yay-currency-switcher',
            currencyFlag: '.yay-currency-flag',
            currencySelectedFlag: '.yay-currency-flag.selected',
            customLoader: '.yay-currency-custom-loader',
            customOption: '.yay-currency-custom-options',
            customArrow: '.yay-currency-custom-arrow',
            customOptionArrow: '.yay-currency-custom-option-row',
            customOptionArrowSelected: '.yay-currency-custom-option-row.selected',
            selectTrigger: '.yay-currency-custom-select__trigger',
            selectWrapper: '.yay-currency-custom-select-wrapper',
            customSelect: '.yay-currency-custom-select',
            selectedOption: '.yay-currency-custom-select__trigger .yay-currency-selected-option',
        },
        blocks_data_args: {
            checkout: ".wp-block-woocommerce-checkout[data-block-name='woocommerce/checkout']",
            cart: ".wp-block-woocommerce-cart[data-block-name='woocommerce/cart']",
        },
        cookies_data_args: {
            cartBlocks: 'yay_cart_blocks_page',
            checkoutBlocks: 'yay_checkout_blocks_page',
        }
    }

    YayCurrency_Callback.Helper = {
        // Cookie
        setCookie: function (cname, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = cname + "=" + (value || "") + expires + "; path=/";
        },
        getCookie: function (cname) {
            let name = cname + '=';
            let decodedCookie = decodeURIComponent(document.cookie);
            let ca = decodedCookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') {
                    c = c.substring(1);
                }
                if (c.indexOf(name) == 0) {
                    return c.substring(name.length, c.length);
                }
            }
            return '';
        },
        deleteCookie: function (cname) {
            YayCurrency_Callback.Helper.setCookie(cname, '', -1);
        },
        getCurrentCurrency: function (currency_id = false) {
            currency_id = currency_id ? currency_id : YayCurrency_Callback.Helper.getCookie(window.yayCurrency.cookie_name);
            let currentCurrency = false;
            if (window.yayCurrency.converted_currency) {
                window.yayCurrency.converted_currency.forEach((currency) => {
                    if (currency.ID === +currency_id) {
                        currentCurrency = currency;
                    }
                });
            }
            return currentCurrency;
        },
        getRateFeeByCurrency: function (current_currency = false) {
            current_currency = current_currency ? current_currency : YayCurrency_Callback.Helper.getCurrentCurrency();
            let rate_after_fee = parseFloat(current_currency.rate);
            if ('percentage' === current_currency.fee.type) {
                rate_after_fee = parseFloat(current_currency.rate) + parseFloat(current_currency.rate) * (parseFloat(current_currency.fee.value) / 100);
            } else {
                rate_after_fee = parseFloat(current_currency.rate) + parseFloat(current_currency.fee.value);
            }
            return rate_after_fee;
        },
        // Common
        getBlockData: function () {
            let block = {};
            if ($(yay_currency_data_args.common_data_args.yayCurrencyBlock).length) {
                const yay_block = $(yay_currency_data_args.common_data_args.yayCurrencyBlock);
                block.isShowFlag = yay_block.data('show-flag');
                block.isShowCurrencyName = yay_block.data('show-currency-name');
                block.isShowCurrencySymbol = yay_block.data('show-currency-symbol');
                block.isShowCurrencyCode = yay_block.data('show-currency-code');
                block.widgetSize = yay_block.data('switcher-size');
            }
            return block;
        },
        blockLoading: function (element) {
            $(element).addClass('processing').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        },
        unBlockLoading: function (element) {
            $(element).removeClass('processing').unblock();
        },
        // Switcher Dropdown
        switcherUpwards: function () {
            const allSwitcher = $(yay_currency_data_args.common_data_args.yayCurrencySwitcher);

            allSwitcher.each(function () {
                const SWITCHER_LIST_HEIGT = 250;

                const offsetTop =
                    $(this).offset().top + $(this).height() - $(window).scrollTop();

                const offsetBottom =
                    $(window).height() -
                    $(this).height() -
                    $(this).offset().top +
                    $(window).scrollTop();

                if (
                    offsetBottom < SWITCHER_LIST_HEIGT &&
                    offsetTop > SWITCHER_LIST_HEIGT
                ) {
                    $(this).find(yay_currency_data_args.switcher_data_args.customOption).addClass(yay_currency_data_args.switcher_data_args.upwardsClass);
                    $(this).find(yay_currency_data_args.switcher_data_args.customArrow).addClass(yay_currency_data_args.switcher_data_args.upwardsClass);
                    $(this)
                        .find(yay_currency_data_args.switcher_data_args.selectTrigger)
                        .addClass(yay_currency_data_args.switcher_data_args.upwardsClass);
                } else {
                    $(this).find(yay_currency_data_args.switcher_data_args.customOption).removeClass(yay_currency_data_args.switcher_data_args.upwardsClass);
                    $(this).find(yay_currency_data_args.switcher_data_args.customArrow).removeClass(yay_currency_data_args.switcher_data_args.upwardsClass);
                    $(this)
                        .find(yay_currency_data_args.switcher_data_args.selectTrigger)
                        .removeClass(yay_currency_data_args.switcher_data_args.upwardsClass);
                }
            });
        },
        switcherAction: function () {
            const switcher_args = yay_currency_data_args.switcher_data_args;
            $(document).on('click', switcher_args.selectWrapper, function () {
                $(switcher_args.customSelect, this).toggleClass(switcher_args.openClass);
                $('#slide-out-widget-area')
                    .find(switcher_args.customOption)
                    .toggleClass('overflow-fix');
                $('[id^=footer]').toggleClass('z-index-fix');
                $(switcher_args.customSelect, this)
                    .parents('.handheld-navigation')
                    .toggleClass('overflow-fix');
            });

            $(document).on('click', switcher_args.customOptionArrow, function () {
                const currencyID = $(this).data('value') ? $(this).data('value') : $(this).data('currency-id');
                const countryCode = $(this)
                    .children(switcher_args.currencyFlag)
                    .data('country_code');
                YayCurrency_Callback.Helper.refreshCartFragments();
                $(switcher_args.currencySwitcher).val(currencyID).change();

                if (!$(this).hasClass(switcher_args.selectedClass)) {
                    const clickedSwitcher = $(this).closest(switcher_args.customSelect);

                    $(this)
                        .parent()
                        .find(switcher_args.customOptionArrowSelected)
                        .removeClass(switcher_args.selectedClass);

                    $(this).addClass(switcher_args.selectedClass);

                    clickedSwitcher.find(switcher_args.currencySelectedFlag).css({
                        background: `url(${yayCurrency.yayCurrencyPluginURL}assets/dist/flags/${countryCode}.svg)`,
                    });

                    clickedSwitcher.find(switcher_args.selectedOption).text($(this).text());

                    clickedSwitcher.find(switcher_args.customLoader).addClass(switcher_args.activeClass);

                    clickedSwitcher.find(switcher_args.customArrow).hide();
                }
            });

            window.addEventListener('click', function (e) {
                const selects = document.querySelectorAll(yay_currency_data_args.switcher_data_args.customSelect);
                selects.forEach((select) => {
                    if (!select.contains(e.target)) {
                        select.classList.remove(yay_currency_data_args.switcher_data_args.openClass);
                    }
                });
            });
        },
        refreshCartFragments: function () {
            if (typeof wc_cart_fragments_params !== 'undefined' && wc_cart_fragments_params !== null) {
                sessionStorage.removeItem(wc_cart_fragments_params.fragment_name);
            }
        },

        // WooCommerce Blocks: Cart, Checkout pages
        detectCheckoutBlocks: function () {
            if ($(yay_currency_data_args.blocks_data_args.checkout).length) {
                return true;
            }
            return false;
        },
        detectCartBlocks: function () {
            if ($(yay_currency_data_args.blocks_data_args.cart).length) {
                return true;
            }
            return false;
        },
        reCalculateCartSubtotalCheckoutBlocksPage: function () {
            // Detect Checkout Blocks
            const cookie_checkout_block = yay_currency_data_args.cookies_data_args.checkoutBlocks;
            YayCurrency_Callback.Helper.deleteCookie(cookie_checkout_block);
            if (YayCurrency_Callback.Helper.detectCheckoutBlocks()) {
                YayCurrency_Callback.Helper.setCookie(cookie_checkout_block, 'yes', 1);
                if (yayCurrency.checkout_notice_html) {
                    if ('' != yayCurrency.checkout_notice_html) {
                        $(yay_currency_data_args.blocks_data_args.checkout).before(yayCurrency.checkout_notice_html);
                    }
                    const cart_contents_el = yay_currency_data_args.common_data_args.yayCurrencyCartContents;
                    $(document.body).on('wc_fragments_refreshed', function () {
                        $.ajax({
                            url: yayCurrency.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'yayCurrency_get_cart_subtotal_default_blocks',
                                nonce: yayCurrency.nonce,
                            },
                            beforeSend: function (res) {
                                // Loading Switcher
                                YayCurrency_Callback.Helper.blockLoading(cart_contents_el);
                            },
                            xhrFields: {
                                withCredentials: true
                            },
                            success: function success(res) {
                                YayCurrency_Callback.Helper.unBlockLoading(cart_contents_el);
                                if (res.success && res.data.cart_subtotal) {
                                    $(cart_contents_el).find('.woocommerce-Price-amount.amount').html(res.data.cart_subtotal);
                                }

                            },
                            error: function (xhr, ajaxOptions, thrownError) {
                                YayCurrency_Callback.Helper.unBlockLoading(cart_contents_el);
                                console.log("Error responseText: ", xhr.responseText);
                            }
                        });
                    });
                }
            }
        },

        // Converter
        getCurrentCurrencyByCode: function (currency_code = false, converted_currency = false) {
            currency_code = currency_code ? currency_code : window.yayCurrency.default_currency_code;
            converted_currency = converted_currency ? converted_currency : window.yayCurrency.converted_currency;
            let currentCurrency = false;
            if (converted_currency) {
                converted_currency.forEach((convert_currency) => {
                    if (convert_currency.currency === currency_code) {
                        currentCurrency = convert_currency;
                    }
                });
            }
            return currentCurrency;
        },
        currencyConverter: function () {
            const currency_converter_el = yay_currency_data_args.converter_args.converterWrapper;
            if ($(currency_converter_el).length) {
                $(currency_converter_el).each(function (index, element) {
                    YayCurrency_Callback.Helper.doConverterCurrency($(element))
                });
            }
        },
        doFormatNumber: function (number, decimals, decPoint, thousandsSep, haveZeroInDecimal = false) {
            if (number === 'N/A' || number === '') {
                return number
            }
            // Strip all characters but numerical ones.
            number = (number + '').replace(/[^0-9+\-Ee.]/g, '')
            let n = !isFinite(+number) ? 0 : +number,
                prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
                sep = typeof thousandsSep === 'undefined' ? ',' : thousandsSep,
                dec = typeof decPoint === 'undefined' ? '.' : decPoint,
                s = '',
                toFixedFix = function (n, prec) {
                    let k = Math.pow(10, prec)
                    return '' + Math.round(n * k) / k
                }
            // Fix for IE parseFloat(0.55).toFixed(0) = 0;
            s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.')
            if (s[0].length > 3) {
                s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep)
            }
            if ((s[1] || '').length < prec) {
                s[1] = s[1] || ''
                s[1] += new Array(prec - s[1].length + 1).join('0')
            }

            return haveZeroInDecimal
                ? s.join(dec)
                : s
                    .join(dec)
                    .replace(/([0-9]*\.0*[1-9]+)0+$/gm, '$1')
                    .replace(/.00+$/, '')
        },
        roundedAmountByCurrency: function (amount, apply_currency) {
            if (!apply_currency) {
                return amount;
            }
            const { roundingType, roundingValue, subtractAmount, numberDecimal, decimalSeparator, thousandSeparator } = apply_currency;
            switch (roundingType) {
                case 'up':
                    amount = Math.ceil(amount / roundingValue) * roundingValue - subtractAmount
                    break
                case 'down':
                    amount = Math.floor(amount / roundingValue) * roundingValue - subtractAmount
                    break
                case 'nearest':
                    amount = Math.round(amount / roundingValue) * roundingValue - subtractAmount
                    break
                default:
                    break;
            }
            const formattedTestAmount = YayCurrency_Callback.Helper.doFormatNumber(
                amount,
                Number(numberDecimal),
                decimalSeparator,
                thousandSeparator,
                true
            );
            return formattedTestAmount;
        },
        doApplyResultConverter: function (_this, data) {
            const
                from_el = _this.find(yay_currency_data_args.converter_args.converterFrom),
                to_el = _this.find(yay_currency_data_args.converter_args.converterTo),
                from_currency_code = data.from_currency_code ? data.from_currency_code : $(from_el).val(),
                to_currency_code = data.to_currency_code ? data.to_currency_code : $(to_el).val();
            let amount = data.amount_value ? +data.amount_value : + $(_this.find(yay_currency_data_args.converter_args.converterAmount)).val();

            if (to_currency_code === from_currency_code) {
                $(_this.find(yay_currency_data_args.converter_args.converterResultValue)).text(amount);
            } else {
                const from_apply_currency = YayCurrency_Callback.Helper.getCurrentCurrencyByCode(from_currency_code),
                    to_apply_currency = YayCurrency_Callback.Helper.getCurrentCurrencyByCode(to_currency_code),
                    exchange_rate_fee = YayCurrency_Callback.Helper.getRateFeeByCurrency(to_apply_currency);
                if (from_apply_currency && from_currency_code !== yayCurrency.default_currency_code) {
                    const rate_after_fee = YayCurrency_Callback.Helper.getRateFeeByCurrency(from_apply_currency);
                    amount = amount * parseFloat(1 / rate_after_fee);
                }

                $(_this.find(yay_currency_data_args.converter_args.converterResultValue)).text(YayCurrency_Callback.Helper.roundedAmountByCurrency(amount * exchange_rate_fee, to_apply_currency));
            }
        },
        doConverterCurrency: function (_this) {
            const amount_el = _this.find(yay_currency_data_args.converter_args.converterAmount),
                from_el = _this.find(yay_currency_data_args.converter_args.converterFrom),
                to_el = _this.find(yay_currency_data_args.converter_args.converterTo),
                result_wrapper = _this.find(yay_currency_data_args.converter_args.converterResultWrapper);

            $(from_el).change(function () {
                $(_this.find(yay_currency_data_args.converter_args.converterResultFrom)).text($(this).val());
                YayCurrency_Callback.Helper.doApplyResultConverter(_this, {
                    'from_currency_code': $(this).val()
                });
            });
            $(to_el).change(function () {
                $(_this.find(yay_currency_data_args.converter_args.converterResultTo)).text($(this).val());
                YayCurrency_Callback.Helper.doApplyResultConverter(_this, {
                    'to_currency_code': $(this).val()
                });
            });
            $(amount_el).on("input", function () {
                const amount = $(this).val();
                $(this).val(amount.replace(/\D/g, '')); // do not allow enter character
                if (amount) {
                    $(result_wrapper).show();
                    $(_this.find(yay_currency_data_args.converter_args.converterResultAmount)).text(amount);
                    YayCurrency_Callback.Helper.doApplyResultConverter(_this, {
                        'amount_value': amount
                    });
                } else {
                    $(result_wrapper).hide();
                }
            });
            $(amount_el).trigger('input');
            $(from_el).trigger('change');
            $(to_el).trigger('change');
        },

        // Compatible With 3rd Plugins
        compatibleWithThirdPartyPlugins: function (currencyID) {
            // compatible with Measurement Price Calculator plugin
            if (window.wc_price_calculator_params) {
                const applyCurrency = YayCurrency_Callback.Helper.getCurrentCurrency(currencyID);
                const rate_after_fee = YayCurrency_Callback.Helper.getRateFeeByCurrency(applyCurrency);

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
                        rule.price = (parseFloat(rule.price) * rate_after_fee).toString();
                        rule.regular_price = (
                            parseFloat(rule.regular_price) * rate_after_fee
                        ).toString();
                        rule.sale_price = (
                            parseFloat(rule.sale_price) * rate_after_fee
                        ).toString();
                    });
            }
            // compatible with WooCommerce PayPal Payments plugin
            if (window.yayCurrency.ppc_paypal) {
                const ppc_cart_cookie_name = 'ppc_paypal_cart_or_product_page';
                const cart_product_cookie = YayCurrency_Callback.Helper.getCookie(ppc_cart_cookie_name);
                if ('1' === yayCurrency.cart_page || '1' === yayCurrency.product_page) {
                    if (!cart_product_cookie) {
                        YayCurrency_Callback.Helper.setCookie(ppc_cart_cookie_name, 'yes', +yayCurrency.cookie_lifetime_days);
                    }
                } else {
                    if (cart_product_cookie) {
                        YayCurrency_Callback.Helper.deleteCookie(ppc_cart_cookie_name);
                    }
                }

                $(document).on('visibilitychange', function () {
                    if ('visible' === document.visibilityState) {
                        const cart_product_cookie = YayCurrency_Callback.Helper.getCookie(ppc_cart_cookie_name);
                        if ('1' === yayCurrency.cart_page || '1' === yayCurrency.product_page) {
                            if (!cart_product_cookie) {
                                YayCurrency_Callback.Helper.setCookie(ppc_cart_cookie_name, 'yes', +yayCurrency.cookie_lifetime_days);
                            }
                        } else {
                            if (cart_product_cookie) {
                                YayCurrency_Callback.Helper.deleteCookie(ppc_cart_cookie_name);
                            }
                        }

                    }
                });
            }
        }
    };

})(jQuery, window);