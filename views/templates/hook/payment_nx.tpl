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
			<a class="payline" href="{$payline_href|escape:'html':'UTF-8'}">
				{if isset($payline_contracts)}
					{foreach from=$payline_contracts item=payline_contract}
						{if !empty($payline_contract.enabled)}
							<img src="{$module_dir|escape:'html':'UTF-8'}views/img/contracts/{$payline_contract.logo}" alt="{$payline_contract.label|escape:'html':'UTF-8'}" title="{$payline_contract.label|escape:'html':'UTF-8'}" />
						{/if}
					{/foreach}
				{/if}
				{$payline_title|escape:'html':'UTF-8'}{if isset($payline_subtitle) && strlen($payline_subtitle)}&nbsp;&nbsp;<span>{$payline_subtitle|escape:'html':'UTF-8'}</span>{/if}
			</a>
			{if $payline_ux_mode == 'lightbox'}
				{include file="./../front/1.6/lightbox.tpl"}
			{/if}
		</p>
	</div>
</div>