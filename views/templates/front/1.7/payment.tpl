{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

{extends file='checkout/checkout.tpl'}

{block name="content"}
	<section id="content">
		<div class="card">
			<div class="card-header">
				{l s='Pay with Payline' mod='payline'}
			</div>
			<div class="card-block">
				<h4 class="mb-1">{l s='Total to pay:' mod='payline'}&nbsp;{$cart.totals.total.value}</h4>

				<div 
					id="PaylineWidget"
					data-auto-init="true"
					data-token="{$payline_token}"
					data-template="{$payline_ux_mode}"
					data-embeddedredirectionallowed="false"
				>
				</div>

				<div class="mt-1 float-xs-right">
					<a class="btn btn-primary" href="{$urls.pages.order}">
						{l s='Back' mod='payline'}
					</a>
				</div>
			</div>
		</div>
	</section>
{/block}
