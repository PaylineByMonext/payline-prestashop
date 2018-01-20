/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

// Subscriptions
$(document).ready(function() {
	$(document).on('click', '.payline-display-subscription-details', function() {
		$('#' + $(this).data('subscription-id')).toggleClass('hidden hidden-xs-up');
	});
});