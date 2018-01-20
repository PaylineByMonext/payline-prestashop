{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

{if isset($success) && $success}
	<div class="alert alert-success">
		<ol>
		{foreach from=$success item=successMessage}
			<li>{$successMessage|escape:'html':'UTF-8'}</li>
		{/foreach}
		</ol>
	</div>
{/if}

{include file="$tpl_dir./errors.tpl"}

{capture name=path}<a href="{$link->getPageLink('my-account', true)}">{l s='My account' mod='payline'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Subscriptions' mod='payline'}{/capture}

{if isset($paymentRecordIdList) && sizeof($paymentRecordIdList)}
<h1 class="page-heading">{l s='Subscriptions' mod='payline'}</h1>
<div class="card">
	<div class="card-block">
		<table class="table">
			<thead>
				<tr>
					<th>{l s='Subscription ID' mod='payline'}</th>
					<th>{l s='Start' mod='payline'}</th>
					<th>{l s='End' mod='payline'}</th>
					<th>{l s='Amount' mod='payline'}</th>
					<th>{l s='State' mod='payline'}</th>
					<th>{l s='Actions' mod='payline'}</th>
				</tr>
			</thead>
			<tbody>
				{foreach from=$paymentRecordInformations item=paymentRecordInformation key=paymentRecordId}
					<tr>
						<td>#{$paymentRecordId|intval}</td>
						<td>{$paymentRecordInformation.subscriptionStartDate|escape:'html':'UTF-8'}</td>
						<td>{$paymentRecordInformation.subscriptionEndDate|escape:'html':'UTF-8'}</td>
						<td>{$paymentRecordInformation.subscriptionAmount|escape:'html':'UTF-8'}</td>
						<td>
							{if $paymentRecordInformation.subscriptionEnabled}
								{l s='Enabled' mod='payline'}
							{else}
								{l s='Disabled on %s' mod='payline' sprintf=[$paymentRecordInformation.subscriptionDisableDate]}
							{/if}
						</td>
						<td>
							<button class="btn btn-primary payline-display-subscription-details" data-subscription-id="payline-linked-orders-{$paymentRecordId|intval}">{l s='Details' mod='payline'}</button>
							{if $paymentRecordInformation.subscriptionEnabled}
								<a href="{$paymentRecordInformation.cancelSubscriptionLink|escape:'html':'UTF-8'}" class="btn btn-danger">{l s='Disable' mod='payline'}</a>
							{/if}
						</td>
					</tr>
					<tr id="payline-linked-orders-{$paymentRecordId|intval}" class="hidden hidden-xs-up">
						<td colspan="6">
							<table class="table table-striped table-bordered">
								<thead>
									<tr>
										<th>{l s='Order ID' mod='payline'}</th>
										<th>{l s='Date' mod='payline'}</th>
										<th>{l s='Order state' mod='payline'}</th>
									</tr>
								</thead>
								<tbody>
									{foreach from=$paymentRecordInformation.ordersList item=relatedOrder}
										<tr>
											<td><a href="{$relatedOrder.order_detail_link|escape:'html':'UTF-8'}">#{$relatedOrder.id|intval}</a></td>
											<td>{$relatedOrder.date|escape:'html':'UTF-8'}</td>
											<td>{$relatedOrder.order_state|escape:'html':'UTF-8'}</td>
										</tr>
									{/foreach}
								</tbody>
							</table>
						</td>
					</tr>
				{/foreach}
			</tbody>
		</table>
	</div>
</div>
{/if}