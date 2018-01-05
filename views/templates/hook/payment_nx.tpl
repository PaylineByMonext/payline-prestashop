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
			<a class="payline" href="{$payline_href|escape:'html':'UTF-8'}">{l s='Pay by Payline' mod='payline'}{if isset($payline_title) && strlen($payline_title)} - {$payline_title|escape:'html':'UTF-8'}{/if}{if isset($payline_subtitle) && strlen($payline_subtitle)}&nbsp;&nbsp;<span>{$payline_subtitle|escape:'html':'UTF-8'}</span>{/if}</a>
			{if $payline_ux_mode == 'lightbox'}
				{include file="./../front/1.6/lightbox.tpl"}
			{/if}
		</p>
	</div>
</div>