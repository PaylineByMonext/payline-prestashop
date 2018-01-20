{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

{capture name=path}{$payline_title|escape:'html':'UTF-8'}{/capture}

{include file="$tpl_dir./errors.tpl"}

<h1 class="page-heading">{$payline_title|escape:'html':'UTF-8'}{if isset($payline_subtitle) && strlen($payline_subtitle)}<br />{$payline_subtitle|escape:'html':'UTF-8'}{/if}</h1>

<div class="block-center">
	<h4>{l s='Total to pay:' mod='payline'}&nbsp;{$payline_first_amount|escape:'html':'UTF-8'}</h4>
	<h4>{l s='Amount of the %s following payments:' sprintf=[$payline_billing_left] mod='payline'}&nbsp;{$payline_next_amount|escape:'html':'UTF-8'}</h4>

	<div 
		id="PaylineWidget"
		data-auto-init="true"
		data-token="{$payline_token|escape:'html':'UTF-8'}"
		data-template="{$payline_ux_mode|escape:'html':'UTF-8'}"
		data-embeddedredirectionallowed="false"
	>
	</div>
	<p>&nbsp;</p>
</div>

<ul class="footer_links clearfix">
	<li>
		<a class="btn btn-default button button-small" href="{$link->getPageLink('order', true)|escape:'html':'UTF-8'}"><span><i class="icon-chevron-left"> </i> {l s='Back' mod='payline'}</span></a>
	</li>
</ul>