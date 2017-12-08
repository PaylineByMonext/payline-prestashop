{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

{capture name=path}{l s='Pay with Payline' mod='payline'}{/capture}

{include file="$tpl_dir./errors.tpl"}

<h1 class="page-heading">{l s='Pay with Payline' mod='payline'}</h1>

<div class="block-center">
	<h4>{l s='Total to pay:' mod='payline'}&nbsp;{displayPrice price=$payline_cart_total}</h4>

	<div 
		id="PaylineWidget"
		data-auto-init="true"
		data-token="{$payline_token}"
		data-template="{$payline_ux_mode}"
		data-embeddedredirectionallowed="false"
	>
	</div>
	<p>&nbsp;</p>
</div>

<ul class="footer_links clearfix">
	<li>
		<a class="btn btn-default button button-small" href="{$link->getPageLink('order', true)}"><span><i class="icon-chevron-left"> </i> {l s='Back' mod='payline'}</span></a>
	</li>
</ul>