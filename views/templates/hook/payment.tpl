{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

<div id="payline-payment-container" class="row">
	<div class="col-xs-12">
		<p class="payment_module">
			<a class="payline" href="{$payline_href|escape:'html':'UTF-8'}">{l s='Pay by Payline' mod='payline'}</a>
			{if $payline_ux_mode == 'lightbox'}
				{include file="./../front/1.6/lightbox.tpl"}
			{/if}
		</p>
	</div>
</div>