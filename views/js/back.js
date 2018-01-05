/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

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
});