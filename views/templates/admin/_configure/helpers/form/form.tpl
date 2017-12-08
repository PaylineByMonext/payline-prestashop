{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

{extends file="helpers/form/form.tpl"}

{block name="label"}
	{if $input.type != 'contracts' && $input.type != 'html'}
		{$smarty.block.parent}
	{/if}
{/block}

{block name="input"}
	{if $input.type == 'contracts'}
		<input type="hidden" id="{$input.name|escape:'html':'UTF-8'}" name="{$input.name|escape:'html':'UTF-8'}" value={$input.enabledContracts|json_encode nofilter} />
		<ol id="payline-contracts-list-{$input.name|escape:'html':'UTF-8'}" class="list-group payline-contracts-list" data-input-id="{$input.name|escape:'html':'UTF-8'}">
		{foreach from=$input.contractsList item=payline_contract}
			{assign var='paylineContractId' value="{$payline_contract.cardType|escape:'html':'UTF-8'}-{$payline_contract.contractNumber|escape:'html':'UTF-8'}"}
			{assign var='paylineContractIsEnabled' value=in_array($paylineContractId, $input.enabledContracts)}
			<li class="list-group-item{if !empty($payline_contract.enabled)} payline-active-contract{/if}" id="{$paylineContractId}" data-contract-id="{if $paylineContractIsEnabled}{$paylineContractId}{/if}">
				<div class="row">
					<div class="col-xs-9">
						<img src="{$module_dir|escape:'html':'UTF-8'}payline/views/img/contracts/{$payline_contract.logo}" alt="{$payline_contract.label|escape:'html':'UTF-8'}" />&nbsp;&nbsp;{$payline_contract.label|escape:'html':'UTF-8'}
					</div>
					<div class="col-xs-3">
						<span class="switch prestashop-switch fixed-width-lg payline-contract-switch">
							<input data-input-id="{$input.name|escape:'html':'UTF-8'}" data-contract-id="{$paylineContractId}" type="radio" name="{$paylineContractId}-{$input.name|escape:'html':'UTF-8'}-toggle" id="{$paylineContractId}-{$input.name|escape:'html':'UTF-8'}-toggle_on" value="1"{if $paylineContractIsEnabled} checked="checked"{/if}>
							<label for="{$paylineContractId}-{$input.name|escape:'html':'UTF-8'}-toggle_on">ON</label>
							<input data-input-id="{$input.name|escape:'html':'UTF-8'}" data-contract-id="{$paylineContractId}" type="radio" type="radio" name="{$paylineContractId}-{$input.name|escape:'html':'UTF-8'}-toggle" id="{$paylineContractId}-{$input.name|escape:'html':'UTF-8'}-toggle_off" value=""{if !$paylineContractIsEnabled} checked="checked"{/if}>
							<label for="{$paylineContractId}-{$input.name|escape:'html':'UTF-8'}-toggle_off">OFF</label>
							<a class="slide-button btn"></a>
						</span>
					</div>
				</div>
			</li>
		{/foreach}
		</ol>
	{else}
		{$smarty.block.parent}
	{/if}
{/block}