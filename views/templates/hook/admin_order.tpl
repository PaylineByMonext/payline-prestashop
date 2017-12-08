{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

<div id="payline-order-slip-alert" class="alert alert-warning" style="display: none">
	{l s='In order to refund the customer via Payline, you must check the “[1]“ option' sprintf=['[1]' => {l s='Generate a credit slip' d='Admin.Orderscustomers.Feature'}] mod='payline'}
</div>

<div class="panel col-xs-12">
	<div class="panel-heading"><i class="icon-money"></i> {l s='Payline information' mod='payline'}</div>
	{if $allowRefund}
		<div class="well">
			<a href="{$link->getAdminLink('AdminOrders')}&vieworder&id_order={$id_order|intval}&paylineProcessFullRefund=1" class="btn btn-default"><i class="icon-exchange"></i>&nbsp;&nbsp;{l s='Full refund' mod='payline'}</a>
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
			<tr>
				<td>{$associatedTransaction.date}</td>
				<td>{$associatedTransaction.transactionId}</td>
				<td>{$associatedTransaction.type}</td>
				<td>
					<span class="badge badge-{if $associatedTransaction.status == 'OK'}success{else}danger{/if}">{$associatedTransaction.status}</span>
				</td>
				<td>
					{if $associatedTransaction.originalTransaction.payment.action == '100' && $associatedTransaction.type == 'AUTHOR'}
						{if $allowCapture}
							<a href="{$link->getAdminLink('AdminOrders')}&vieworder&id_order={$id_order|intval}&paylineCapture={$associatedTransaction.transactionId}" class="btn btn-default">{l s='Capture' mod='payline'}</a>
						{/if}
						{if $allowReset}
							<a href="{$link->getAdminLink('AdminOrders')}&vieworder&id_order={$id_order|intval}&paylineReset={$associatedTransaction.transactionId}" class="btn btn-default">{l s='Reset' mod='payline'}</a>
						{/if}
					{/if}
				</td>
			</tr>
		{/foreach}
			<tbody>
		</table>
	</div>
</div>