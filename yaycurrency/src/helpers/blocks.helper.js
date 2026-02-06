
(function ($, window) {
    YayCurrency_Blocks = window.YayCurrency_Blocks || {};

    YayCurrency_Blocks.Helper = {
        // WooCommerce Blocks: Cart, Checkout pages
        detectCheckoutBlocks: function () {
            var flag = false;
            if ($(yay_currency_data_args.blocks_data_args.checkout).length) {
                flag = true;
            }
            return yayCurrencyHooks.applyFilters('yayCurrencyDetectCheckoutBlocks', flag);
        },
        detectCartBlocks: function () {
            var flag = false;

            if ($(yay_currency_data_args.blocks_data_args.cart).length) {
                flag = true;
            }

            return yayCurrencyHooks.applyFilters('yayCurrencyDetectCartBlocks', flag);
        },
        /**
         * Debounces a function to limit its execution rate.
         * @param {Function} func - The function to debounce.
         * @param {number} wait - The debounce delay in milliseconds.
         * @returns {Function} - The debounced function.
         */
        debounce: function (func, wait) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        },
        /**
         * Waits for a DOM element to appear and executes a callback.
         * @param {string} selector - CSS selector for the target element.
         * @param {Function} callback - Function to call when element is found.
         * @param {number} [timeout=10000] - Maximum time to wait (ms).
         */
        waitForElement: function (selector, callback, timeout = 10000) {
            const el = document.querySelector(selector);
            if (el) {
                callback(el);
                return;
            }

            const startTime = Date.now();
            const check = () => {
                const el = document.querySelector(selector);
                if (el) {
                    callback(el);
                } else if (Date.now() - startTime < timeout) {
                    requestAnimationFrame(check);
                }
            };
            requestAnimationFrame(check);
        },
        handleParsePrice: function (priceText) {

            // Remove all non-numeric characters except potential separators
            let cleanPrice = priceText.replace(/[^0-9\s_,.]/g, '').trim(); // Keep digits, spaces, commas, dots, underscores

            if (!cleanPrice) {
                return 0; // Return 0 if no numeric content
            }

            // Split by all possible separators
            let allParts = cleanPrice.split(/[\s_,.]+/); // Split by space, comma, dot, underscore
            if (allParts.length < 1) {
                return parseFloat(cleanPrice) || 0; // No separators, treat as whole number
            }

            // The last part is the decimal portion (keep all digits)
            let decimalPart = allParts.pop() || '0';
            let integerPart = allParts.join(''); // Join remaining parts as integer

            // Combine with a standard decimal separator
            let combinedPrice = integerPart + (decimalPart ? '.' + decimalPart : '');

            // Parse to float, preserving the original decimal places
            let numericValue = parseFloat(combinedPrice) || 0;

            return numericValue;
        },
        approximatePriceHTML: function (originalPrice, applyCurrency) {
            const approximatePrice = YayCurrency_Callback.Helper.formatPriceByCurrency(originalPrice, true, applyCurrency)
            const price_html = " <span class='yay-currency-checkout-converted-approximately'>(~" + approximatePrice + ")</span>";
            return price_html;
        },
        reCalculateCartSubtotalCheckoutBlocksPage: function () {
            // Detect Checkout Blocks
            if (YayCurrency_Blocks.Helper.detectCheckoutBlocks()) {

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
        // Checkout Blocks: Force Currency by Payment Method [Full]
        isTurnOffCheckout: function (currencyID) {
            const currentCurrency = YayCurrency_Callback.Helper.getCurrentCurrency(currencyID);
            const flagTurnOff = ('0' === yayCurrency.checkout_diff_currency && yayCurrency.default_currency_code !== currentCurrency.currency) || ('1' === yayCurrency.checkout_diff_currency && '0' === currentCurrency.status);

            if (!flagTurnOff) {
                return false;
            }

            return true;
        },

        /**
         * Formats prices in cart/checkout blocks for the given currency.
         * @param {Object} applyCurrency - The currency configuration object.
         */
        handleFormatPriceCartCheckoutBlocks: function (applyCurrency) {
            const processedElements = new WeakSet(); // Track processed elements to prevent infinite loops
            const priceElements = document.querySelectorAll('.wc-block-formatted-money-amount, .wc-block-components-product-price__regular, .wc-block-components-product-price__value.is-discounted');

            priceElements.forEach((el) => {
                if (processedElements.has(el)) return; // Skip already processed elements

                const priceText = el.textContent.trim();
                const numericValue = YayCurrency_Blocks.Helper.handleParsePrice(priceText);
                if (isNaN(numericValue)) return; // Skip invalid prices
                const formattedPrice = YayCurrency_Callback.Helper.formatPriceByCurrency(numericValue, false, applyCurrency);

                if (priceText !== formattedPrice) {
                    el.textContent = formattedPrice;
                    processedElements.add(el); // Mark as processed
                }
            });
        },
        detectSpecialCurrencyNotAllowFormat: function (applyCurrency) {
            const listCurrencyCode = ['SAR', 'BHD', 'AED', 'KWD', 'QAR', 'OMR', 'JOD', 'TND'];
            const isSpecialCurrency = listCurrencyCode.includes(applyCurrency.currency);
            if (isSpecialCurrency) {
                if (applyCurrency.currencyPosition === 'right' || applyCurrency.currencyPosition === 'right_space') {
                    return true;
                }
                return false;
            }
            return false;
        },
        /**
         * Formats prices in WooCommerce cart/checkout blocks based on the selected currency.
         * @param {string} currencyID - The ID of the currency to apply.
         */
        formatPriceCartCheckoutBlocks: function (currencyID) {
            // If not detect cart or checkout blocks then not format price
            if (!YayCurrency_Blocks.Helper.detectCartBlocks() && !YayCurrency_Blocks.Helper.detectCheckoutBlocks()) {
                return;
            }

            const applyCurrency = YayCurrency_Callback.Helper.getCurrentCurrency(currencyID);
            if (!applyCurrency) {
                return;
            }

            // Check if the currency is special then not format price
            if (YayCurrency_Blocks.Helper.detectSpecialCurrencyNotAllowFormat(applyCurrency)) {
                return;
            }

            // // Check if the currency symbol position is not left then not format price
            if (!applyCurrency || (applyCurrency.currencyPosition === window.yayCurrency.currency_symbol_position)) {
                return;
            }

            // Check if the checkout page is turned off then not format price
            const isTurnOff = YayCurrency_Blocks.Helper.isTurnOffCheckout(currencyID);
            if (YayCurrency_Blocks.Helper.detectCheckoutBlocks() && isTurnOff) {
                return;
            }

            // Debounce price formatting to prevent excessive updates
            const debouncedHandleFormatPrice = YayCurrency_Blocks.Helper.debounce(YayCurrency_Blocks.Helper.handleFormatPriceCartCheckoutBlocks.bind(this, applyCurrency), 100);

            YayCurrency_Blocks.Helper.waitForElement('.wc-block-cart,.wc-block-checkout', (targetNode) => {
                const observer = new MutationObserver(debouncedHandleFormatPrice);
                observer.observe(targetNode, { childList: true, subtree: true, characterData: true });
                debouncedHandleFormatPrice(); // Initial call for existing elements

                // Cleanup observer on page unload
                window.addEventListener('beforeunload', () => observer.disconnect(), { once: true });
            });
        },

        approximatePriceHTML: function (originalPrice, applyCurrency) {
            const approximatePrice = YayCurrency_Callback.Helper.formatPriceByCurrency(originalPrice, true, applyCurrency)
            const price_html = " <span class='yay-currency-checkout-converted-approximately'>(~" + approximatePrice + ")</span>";
            return price_html;
        },

        approximatePriceCheckoutBlocks: function (currencyID) {
            if (YayCurrency_Blocks.Helper.detectCheckoutBlocks()) {
                const applyCurrency = YayCurrency_Callback.Helper.getCurrentCurrency(currencyID);
                const turn_off_checkout = ('0' === yayCurrency.checkout_diff_currency && yayCurrency.default_currency_code !== applyCurrency.currency) || ('1' === yayCurrency.checkout_diff_currency && '0' === applyCurrency.status);
                if (turn_off_checkout) {
                    // Run on page load
                    YayCurrency_Blocks.Helper.handleAddApproximatePrices(applyCurrency);

                    // Observe DOM changes
                    const observer = new MutationObserver(function (mutations) {
                        YayCurrency_Blocks.Helper.handleAddApproximatePrices(applyCurrency);
                    });
                    observer.observe(document.querySelector('.wc-block-checkout'), { childList: true, subtree: true });

                    // Cleanup
                    $(window).on('unload', function () {
                        observer.disconnect();
                    });
                }
            }

        },

        handleAddApproximatePrices: function (applyCurrency) {
            $('.wc-block-formatted-money-amount, .wc-block-components-product-price__regular, .wc-block-components-product-price__value.is-discounted').each(function () {
                if (!$(this).find('.yay-currency-checkout-converted-approximately').length) {
                    const priceText = $(this).text().trim(); // e.g., "1_234 56" or "1,234.56 €"
                    let numericValue = YayCurrency_Blocks.Helper.handleParsePrice(priceText);

                    if (!isNaN(numericValue)) {
                        const approximatePriceHTML = YayCurrency_Blocks.Helper.approximatePriceHTML(numericValue, applyCurrency);
                        $(this).append(approximatePriceHTML);
                    }
                }
            });
        },
        /**
        * Set header param to detect cart or checkout blocks when call REST API
        */
        setHeaderParamToDetectCartOrCheckoutBlocks: function () {
            // Override fetch
            const origFetch = window.fetch;
            window.fetch = function (input, init) {
                init = init || {};
                init.headers = init.headers || {};
                // console.log({ input })
                const url = typeof input === "string" ? input : input.url;
                // console.log({ url })
                if (url && url.includes("/wc/store/v1/")) {
                    let page = "";
                    if (document.body.classList.contains("woocommerce-cart")) page = "cart";
                    if (document.body.classList.contains("woocommerce-checkout")) page = "checkout";
                    // console.log({ page });
                    init.headers["YayCurrency-WC-Blocks-Context"] = page;
                }
                return origFetch(input, init);
            };
        },
    };

})(jQuery, window);