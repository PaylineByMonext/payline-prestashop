/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

function payline_initProductsAutocomplete()
{
    $('#product_autocomplete_input').autocomplete('ajax_products_list.php?forceJson=1', {
        // Use ?forceJson=1 to get image link in the returned values
        minLength: 2,
        minChars: 1,
        // Disable to prevent json to be displayed as autocompletion
        autoFill: true,
        max: 20,
        matchContains: true,
        mustMatch: false,
        scroll: false,
        cacheLength: 0,
        parse: function(data) {
            var parsed = [];
            if (payline_isPrestaShop16) {
                var rows = data.split("\n");
                for (var index in rows) {
                    var row = rows[index].split("|");
                    if (row.length == 2) {
                        parsed[parsed.length] = {
                            data: row,
                            value: row[0],
                            result: row
                        };
                    }
                }
            } else {
                var rows = JSON.parse(data);
                for (var index in rows) { 
                    var row = rows[index];
                    parsed[parsed.length] = {
                        data: row,
                        value: row.name,
                        result: row
                    };
                }
            }
            return parsed;
        },
        formatItem: function(item) {
            if (payline_isPrestaShop16) {
                return item[1] + ' - ' + item[0];
            } else {
                return '<div style="margin-right: 10px;float:left;"><img width=45 height=45 src="'+ item.image +'" /></div>' + '<h4 class="media-heading">' + item.name + '</h4>';
            }
        }
    }).result(payline_addProduct);

    $('#product_autocomplete_input').setOptions({
        extraParams: {
            excludeIds: payline_getProductsIds(),
            exclude_packs : 0 
        },
    });
};

function payline_getProductsIds()
{
    if ($('#PAYLINE_SUBSCRIBE_PLIST').val() === "") {
        $('#PAYLINE_SUBSCRIBE_PLIST').val(',');
        $('#PAYLINE_SUBSCRIBE_PLIST_PRODUCTS').val('¤');
    }
    return $('#PAYLINE_SUBSCRIBE_PLIST').val();
}

function payline_addProduct(event, data, formatted)
{
    if (data == null) {
        return false;
    }

    if (payline_isPrestaShop16) {
        var productId = data[1];
        var productName = data[0];
        var productImage = '../img/tmp/product_mini_' + productId + '_' + payline_idShop + '.jpg';
    } else {
        var productId = data.id;
        var productName = data.name;
        var productImage = data.image;
    }

    var $divProducts = $('#PAYLINE_SUBSCRIBE_PLIST_CONTAINER');
    var $inputProducts = $('#PAYLINE_SUBSCRIBE_PLIST');
    var $nameProducts = $('#PAYLINE_SUBSCRIBE_PLIST_PRODUCTS');

    /* delete product from select + add product line to the div, input_name, input_ids elements */
    $divProducts.html($divProducts.html() + '<div id="PAYLINE_SUBSCRIBE_PLIST-PRODUCT-'+ productId +'" class="form-control-static"><button type="button" class="btn btn-default" onclick="payline_delProduct('+ productId +')" name="' + productId + '"><i class="icon-remove text-danger"></i></button><img width=45 height=45 src="' + productImage + '" />&nbsp;' + productName +'</div>');
    $nameProducts.val($nameProducts.val() + productName + '¤');
    $inputProducts.val($inputProducts.val() + productId + ',');
    $('#product_autocomplete_input').val('');
    $('#product_autocomplete_input').setOptions({
        extraParams: { 
        	excludeIds : payline_getProductsIds(),
        	exclude_packs : 0  
        }
    });
};

function payline_delProduct(id)
{
    var input = getE('PAYLINE_SUBSCRIBE_PLIST');
    var name = getE('PAYLINE_SUBSCRIBE_PLIST_PRODUCTS');

    // Cut hidden fields in array
    var inputCut = input.value.split(',');
    var nameCut = name.value.split('¤');;

    if (inputCut.length != nameCut.length) {
        return jAlert('Bad size');
    }

    // Reset all hidden fields
    input.value = '';
    name.value = '';

    for (i in inputCut) {
        // If empty, error, next
        if (!inputCut[i] || !nameCut[i]) {
            continue;
        }
        if (inputCut[i] == '' && nameCut[i] == '') {
            continue;
        }

        // Add to hidden fields no selected products OR add to select field selected product
        if (inputCut[i] != id) {
            input.value += inputCut[i] + ',';
            name.value += nameCut[i] + '¤';
        }
    }

    // Remove div containing the product from the list
    $("#PAYLINE_SUBSCRIBE_PLIST-PRODUCT-" + id).remove();

    $('#product_autocomplete_input').setOptions({
        extraParams: {
        	excludeIds : payline_getProductsIds(),
        	exclude_packs : 0 
        }
    });
};

// AdminModules
$(document).ready(function() {
    // Module configuration tab
    $(document).on('click', 'a.list-group-item[data-toggle=tab]', function(e) {
        $('a.list-group-item').removeClass('active');
        $(this).addClass('active');
        $('input[name=selected_tab]').val($(this).data('identifier'));
    });
    $(document).on('change', 'select#PAYLINE_WEB_CASH_ACTION', function() {
        $('#web-payment-configuration div.payline-autorization-only').toggleClass('hidden');
    });
    $(document).on('change', 'select#PAYLINE_WEB_CASH_UX', function() {
        if ($(this).val() == 'redirect') {
            $('#web-payment-configuration div.payline-redirect-only').removeClass('hidden');
        } else {
            $('#web-payment-configuration div.payline-redirect-only').addClass('hidden');
        }
    });
    $(document).on('change', 'select#PAYLINE_RECURRING_UX', function() {
        if ($(this).val() == 'redirect') {
            $('#recurring-payment-configuration div.payline-redirect-only').removeClass('hidden');
        } else {
            $('#recurring-payment-configuration div.payline-redirect-only').addClass('hidden');
        }
    });

    // Contracts
    $('.payline-contracts-list').sortable({
        placeholder: 'sortable-placeholder active list-group-item',
        start: function(e, ui){
            ui.placeholder.height(ui.item.height());
            ui.placeholder.width(ui.item.width());
        },
        update: function(event, ui) {
            inputId = $(this).attr('data-input-id');
            $('#' + inputId).val(JSON.stringify($('#payline-contracts-list-' + inputId).sortable('toArray', {attribute: 'data-contract-id'})));
        }
    });
    $(document).on('change', '.payline-contract-switch input', function() {
        if ($(this).val() == 1) {
            $(this).parents('.list-group-item').addClass('payline-active-contract').attr('data-contract-id', $(this).attr('data-contract-id'));
        } else {
            $(this).parents('.list-group-item').removeClass('payline-active-contract').attr('data-contract-id', '');
        }
        inputId = $(this).attr('data-input-id');
        $('#' + inputId).val(JSON.stringify($('#payline-contracts-list-' + inputId).sortable('toArray', {attribute: 'data-contract-id'})));
    });

    // Product autocomplete
    payline_initProductsAutocomplete();
});