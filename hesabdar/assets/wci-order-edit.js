/**
 * Hesabdar — ایجاد/ویرایش سفارش (جستجوی محصول و مشتری)
 */
(function ($) {
    'use strict';

    if (typeof wciOrderEdit === 'undefined') {
        return;
    }

    var lineIdx = $('#wci_line_items_body .wci-line-row').length;
    var ajaxUrl = wciOrderEdit.ajaxUrl;
    var nonce = wciOrderEdit.nonce;

    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments;
            var ctx = this;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(ctx, args);
            }, ms);
        };
    }

    function hideResults($box) {
        $box.hide().empty();
    }

    function formatMoney(n) {
        n = parseFloat(n) || 0;
        return n.toLocaleString('fa-IR');
    }

    function updateLineSum($row) {
        var total = parseFloat($row.find('.wci-line-total').val()) || 0;
        $row.find('.wci-line-sum').text(formatMoney(total));
    }

    function toggleLineEmpty() {
        var has = $('#wci_line_items_body .wci-line-row').length > 0;
        $('#wci_line_empty').toggle(!has);
    }

    function addLineItem(product) {
        var idx = lineIdx++;
        var price = parseFloat(product.price) || 0;
        var productId = product.parent_id > 0 ? product.parent_id : product.id;
        var variationId = product.parent_id > 0 ? product.id : 0;
        var html = '<tr class="wci-line-row" data-idx="' + idx + '">' +
            '<td>' +
            '<input type="hidden" name="line_items[' + idx + '][product_id]" value="' + productId + '">' +
            '<input type="hidden" name="line_items[' + idx + '][variation_id]" value="' + variationId + '">' +
            '<strong>' + $('<div>').text(product.name).html() + '</strong>' +
            (product.sku ? '<br><span class="description">SKU: ' + $('<div>').text(product.sku).html() + '</span>' : '') +
            '</td>' +
            '<td><input type="number" name="line_items[' + idx + '][qty]" class="small-text wci-line-qty" min="0" step="1" value="1"></td>' +
            '<td><input type="number" name="line_items[' + idx + '][line_total]" class="small-text wci-line-total" min="0" step="1" value="' + Math.round(price) + '"></td>' +
            '<td class="wci-line-sum">' + formatMoney(price) + '</td>' +
            '<td><button type="button" class="button-link wci-line-remove" title="حذف">&times;</button></td>' +
            '</tr>';
        $('#wci_line_items_body').append(html);
        toggleLineEmpty();
        hideResults($('#wci_product_results'));
        $('#wci_product_search').val('');
    }

    function fillBilling(data) {
        if (!data) return;
        var map = {
            first_name: data.first_name,
            last_name: data.last_name,
            email: data.email,
            phone: data.phone,
            city: data.city,
            state: data.state,
            address_1: data.address_1,
            postcode: data.postcode
        };
        $.each(map, function (key, val) {
            if (val) {
                $('[name="billing[' + key + ']"]').val(val);
            }
        });
        if (data.id) {
            $('#wci_customer_id').val(data.id);
        }
    }

    // ─── جستجوی محصول ───────────────────────────────────────────
    var searchProducts = debounce(function () {
        var term = $('#wci_product_search').val().trim();
        var $box = $('#wci_product_results');
        if (term.length < 2) {
            hideResults($box);
            return;
        }
        $box.html('<div class="wci-ac-loading">در حال جستجو...</div>').show();
        $.get(ajaxUrl, {
            action: 'wci_search_products',
            nonce: nonce,
            term: term
        }).done(function (res) {
            if (!res.success || !res.data || !res.data.length) {
                $box.html('<div class="wci-ac-empty">محصولی یافت نشد</div>');
                return;
            }
            var html = '';
            $.each(res.data, function (i, p) {
                html += '<button type="button" class="wci-ac-item" data-product=\'' + JSON.stringify(p).replace(/'/g, '&#39;') + '\'>' +
                    '<span class="wci-ac-item-name">' + $('<div>').text(p.name).html() + '</span>' +
                    '<span class="wci-ac-item-meta">' + (p.price_html || '') + (p.sku ? ' — ' + p.sku : '') + '</span>' +
                    '</button>';
            });
            $box.html(html);
        }).fail(function () {
            $box.html('<div class="wci-ac-empty">خطا در جستجو</div>');
        });
    }, 350);

    $('#wci_product_search').on('input', searchProducts);

    $(document).on('click', '#wci_product_results .wci-ac-item', function () {
        var raw = $(this).attr('data-product');
        try {
            addLineItem(JSON.parse(raw));
        } catch (e) { /* ignore */ }
    });

    // ─── جستجوی مشتری ───────────────────────────────────────────
    var searchCustomers = debounce(function () {
        var term = $('#wci_customer_search').val().trim();
        var $box = $('#wci_customer_results');
        if (term.length < 2) {
            hideResults($box);
            return;
        }
        $box.html('<div class="wci-ac-loading">در حال جستجو...</div>').show();
        $.get(ajaxUrl, {
            action: 'wci_search_customers',
            nonce: nonce,
            term: term
        }).done(function (res) {
            if (!res.success || !res.data || !res.data.length) {
                $box.html('<div class="wci-ac-empty">مشتری یافت نشد</div>');
                return;
            }
            var html = '';
            $.each(res.data, function (i, c) {
                html += '<button type="button" class="wci-ac-item" data-customer=\'' + JSON.stringify(c).replace(/'/g, '&#39;') + '\'>' +
                    '<span class="wci-ac-item-name">' + $('<div>').text(c.label || c.email).html() + '</span>' +
                    '<span class="wci-ac-item-meta">' + $('<div>').text(c.email || '').html() + (c.phone ? ' — ' + c.phone : '') + '</span>' +
                    '</button>';
            });
            $box.html(html);
        }).fail(function () {
            $box.html('<div class="wci-ac-empty">خطا در جستجو</div>');
        });
    }, 350);

    $('#wci_customer_search').on('input', searchCustomers);

    $(document).on('click', '#wci_customer_results .wci-ac-item', function () {
        var raw = $(this).attr('data-customer');
        try {
            fillBilling(JSON.parse(raw));
        } catch (e) { /* ignore */ }
        hideResults($('#wci_customer_results'));
        $('#wci_customer_search').val('');
    });

    // ─── خطوط سفارش ─────────────────────────────────────────────
    $(document).on('click', '.wci-line-remove', function () {
        $(this).closest('.wci-line-row').remove();
        toggleLineEmpty();
    });

    $(document).on('input', '.wci-line-total, .wci-line-qty', function () {
        updateLineSum($(this).closest('.wci-line-row'));
    });

    // ─── ارسال = صورتحساب ───────────────────────────────────────
    function syncShipToBilling() {
        var on = $('#wci_ship_to_billing').is(':checked');
        var $ship = $('#wci_shipping_fields');
        if (on) {
            $ship.addClass('is-disabled');
            $ship.find('input, select').prop('disabled', true);
        } else {
            $ship.removeClass('is-disabled');
            $ship.find('input, select').prop('disabled', false);
        }
    }

    $('#wci_ship_to_billing').on('change', syncShipToBilling);
    syncShipToBilling();

    // بستن نتایج با کلیک بیرون
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.wci-product-search-wrap').length) {
            hideResults($('#wci_product_results'));
        }
        if (!$(e.target).closest('.wci-customer-search-wrap').length) {
            hideResults($('#wci_customer_results'));
        }
    });

    // اعتبارسنجی ساده قبل از ارسال
    $('#wci-order-form').on('submit', function () {
        var hasLines = $('#wci_line_items_body .wci-line-row').length > 0;
        if (!hasLines) {
            alert('حداقل یک محصول به سفارش اضافه کنید.');
            return false;
        }
        var email = $('[name="billing[email]"]').val();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            alert('ایمیل صورتحساب معتبر نیست.');
            return false;
        }
        return true;
    });

    toggleLineEmpty();
})(jQuery);
