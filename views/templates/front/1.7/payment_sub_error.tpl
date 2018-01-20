{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

<div class="alert alert-danger">
	{l s='Your cart contains mixed products (recurring products and classic products).' mod='payline'}<br />
	{l s='In order to be able to pay with Payline, please remove these products:' mod='payline'}<br /><br />
	<ul>
		{foreach from=$paylineBreakingProductList item=paylineBreakingProduct}
		<li>- {$paylineBreakingProduct}</li>
		{/foreach}
	</ul>
</div>
