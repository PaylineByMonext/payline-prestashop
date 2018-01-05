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
				{l s='Pay with Payline' mod='payline'}{if isset($payline_title) && strlen($payline_title)} - {$payline_title}{/if}
				{if isset($payline_subtitle) && strlen($payline_subtitle)}<br />{$payline_subtitle}{/if}
			</div>
			<div class="card-block">
				<h4 class="mb-1">{l s='Total to pay now:' mod='payline'}&nbsp;{$payline_first_amount}</h4>
				<h3 class="mb-1">{l s='Amount of the %s following payments:' sprintf=[$payline_billing_left] mod='payline'}&nbsp;{$payline_next_amount}</h3>

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
