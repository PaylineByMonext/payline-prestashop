{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

{extends file='customer/page.tpl'}

{block name='page_title'}
  {l s='Subscriptions' mod='payline'}
{/block}

{block name='page_content'}
	{if isset($paymentRecordIdList) && sizeof($paymentRecordIdList)}
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
							<td>{$paymentRecordInformation.subscriptionStartDate}</td>
							<td>{$paymentRecordInformation.subscriptionEndDate}</td>
							<td>{$paymentRecordInformation.subscriptionAmount}</td>
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
									<a href="{$paymentRecordInformation.cancelSubscriptionLink}" class="btn btn-danger">{l s='Disable' mod='payline'}</a>
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
												<td><a href="{$relatedOrder.order_detail_link}">#{$relatedOrder.id|intval}</a></td>
												<td>{$relatedOrder.date}</td>
												<td>{$relatedOrder.order_state}</td>
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
{/block}