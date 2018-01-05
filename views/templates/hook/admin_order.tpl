{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

<div id="payline-order-slip-alert" class="alert alert-warning" style="display: none">
	{l s='In order to refund the customer via Payline, you must check the “[1]“ option' sprintf=['[1]' => {l s='Generate a credit slip' mod='payline'}] mod='payline'}
</div>

{if isset($billingList)}
<div class="panel col-xs-12{if empty($billingList)} hidden{/if}">
	<div class="panel-heading"><i class="icon-money"></i> {l s='Recurring information' mod='payline'} - {l s='Payment record #%s' sprintf=[$paymentRecordId] mod='payline'}</div>
	<div class="table-responsive">
		<table class="table">
			<thead>
				<th><span class="title_box">{l s='Due date' mod='payline'}</span></th>
				<th><span class="title_box">{l s='Amount' mod='payline'}</span></th>
				<th><span class="title_box">{l s='Transaction #' mod='payline'}</span></th>
				<th><span class="title_box">{l s='Status' mod='payline'}</span></th>
			</thead>
			<tbody>
		{foreach from=$billingList item=billingRecord}
			<tr>
				<td>{$billingRecord.date|escape:'html':'UTF-8'}</td>
				<td>{displayPrice price=($billingRecord.amount/100)}</td>
				<td>
					{if isset($billingRecord.transaction)}
						<a href="#payline-transaction-{$billingRecord.transaction.id|escape:'html':'UTF-8'}">{$billingRecord.transaction.id|escape:'html':'UTF-8'}</a>
					{else}
						{l s='N/A' mod='payline'}
					{/if}
				</td>
				<td>
					<span class="badge badge-{if $billingRecord.calculated_status == 1}success{elseif $billingRecord.calculated_status == 2}danger{elseif $billingRecord.calculated_status == 3 || $billingRecord.calculated_status == 4}warning{elseif $billingRecord.calculated_status == 0}default{/if}">
						{if $billingRecord.calculated_status == 0}
							{l s='WAITING' mod='payline'}
						{elseif $billingRecord.calculated_status == 1}
							{l s='OK' mod='payline'}
						{elseif $billingRecord.calculated_status == 2}
							{l s='NOK' mod='payline'}
						{elseif $billingRecord.calculated_status == 3}
							{l s='IN PROGRESS' mod='payline'}
						{elseif $billingRecord.calculated_status == 4}
							{l s='CANCELED' mod='payline'}
						{/if}
					</span>
				</td>
			</tr>
		{/foreach}
			<tbody>
		</table>
	</div>
</div>
{/if}

<div class="panel col-xs-12">
	<div class="panel-heading"><i class="icon-money"></i> {l s='Payline information' mod='payline'}</div>
	{if $allowRefund}
		<div class="well">
			<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&vieworder&id_order={$id_order|intval}&paylineProcessFullRefund=1" class="btn btn-default"><i class="icon-exchange"></i>&nbsp;&nbsp;{l s='Full refund' mod='payline'}</a>
		</div>
	{/if}
	<div class="table-responsive">
		<table class="table">
			<thead>
				<th><span class="title_box">{l s='Date' mod='payline'}</span></th>
				<th><span class="title_box">{l s='Transaction #' mod='payline'}</span></th>
				<th><span class="title_box">{l s='Type' mod='payline'}</span></th>
				<th><span class="title_box">{l s='Status' mod='payline'}</span></th>
				<th><span class="title_box">{l s='Action' mod='payline'}</span></th>
			</thead>
			<tbody>
		{foreach from=$transactionsList item=associatedTransaction}
			<tr id="payline-transaction-{$associatedTransaction.transactionId|escape:'html':'UTF-8'}">
				<td>{$associatedTransaction.date|escape:'html':'UTF-8'}</td>
				<td>{$associatedTransaction.transactionId|escape:'html':'UTF-8'}</td>
				<td>{$associatedTransaction.type|escape:'html':'UTF-8'}</td>
				<td>
					<span class="badge badge-{if $associatedTransaction.status == 'OK'}success{else}danger{/if}">{$associatedTransaction.status|escape:'html':'UTF-8'}</span>
				</td>
				<td>
					{if $associatedTransaction.originalTransaction.payment.action == '100' && $associatedTransaction.type == 'AUTHOR'}
						{if $allowCapture}
							<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&vieworder&id_order={$id_order|intval}&paylineCapture={$associatedTransaction.transactionId|escape:'html':'UTF-8'}" class="btn btn-default">{l s='Capture' mod='payline'}</a>
						{/if}
						{if $allowReset}
							<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&vieworder&id_order={$id_order|intval}&paylineReset={$associatedTransaction.transactionId|escape:'html':'UTF-8'}" class="btn btn-default">{l s='Reset' mod='payline'}</a>
						{/if}
					{/if}
				</td>
			</tr>
		{/foreach}
			<tbody>
		</table>
	</div>
</div>