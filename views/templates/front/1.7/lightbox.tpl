{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

{if isset($payline_subtitle) && strlen($payline_subtitle)}
	<p>{$payline_subtitle}</p>
{/if}

<div 
	id="PaylineWidget"
	data-auto-init="false"
	data-token="{$payline_token}"
	data-template="{$payline_ux_mode}"
	data-embeddedredirectionallowed="false"
>
</div>
{foreach from=$payline_assets item=paylineAssetsUrls key=assetType}
	{foreach from=$paylineAssetsUrls item=paylineAssetsUrl}
		{if $assetType == 'js'}
			<script src="{$paylineAssetsUrl}"></script>
		{elseif $assetType == 'css'}
			<link href="{$paylineAssetsUrl}" rel="stylesheet" />
		{/if}
	{/foreach}
{/foreach}