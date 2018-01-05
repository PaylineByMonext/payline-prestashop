{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

<div 
	id="PaylineWidget"
	data-auto-init="false"
	data-token="{$payline_token|escape:'html':'UTF-8'}"
	data-template="{$payline_ux_mode|escape:'html':'UTF-8'}"
	data-embeddedredirectionallowed="false"
>
</div>
{foreach from=$payline_assets item=paylineAssetsUrls key=assetType}
	{foreach from=$paylineAssetsUrls item=paylineAssetsUrl}
		{if $assetType == 'js'}
			<script src="{$paylineAssetsUrl|escape:'html':'UTF-8'}"></script>
		{elseif $assetType == 'css'}
			<link href="{$paylineAssetsUrl|escape:'html':'UTF-8'}" rel="stylesheet" />
		{/if}
	{/foreach}
{/foreach}