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
	{elseif $input.type == 'product-selector'}
		<div class="form-group{if isset($input.form_group_class)}{$input.form_group_class|escape:'html':'UTF-8'}{/if}">
			<div class="col-lg-5">
				<input type="hidden" name="{$input.name|escape:'html':'UTF-8'}" id="{$input.name|escape:'html':'UTF-8'}" value="{foreach $input.values as $product}{$product.id|intval},{/foreach}" />
				<input type="hidden" name="{$input.name|escape:'html':'UTF-8'}_PRODUCTS" id="{$input.name|escape:'html':'UTF-8'}_PRODUCTS" value="{foreach $input.values as $product}{$product.name|escape:'html':'UTF-8'}¤{/foreach}" />
				<div id="ajax_choose_product">
					<div class="input-group">
						<input type="text" id="product_autocomplete_input" name="product_autocomplete_input" placeholder="{l s='Start typing an ID, reference, or product name' mod='payline'}" />
						<span class="input-group-addon"><i class="icon-search"></i></span>
					</div>
				</div>

				<div id="{$input.name|escape:'html':'UTF-8'}_CONTAINER">
					{foreach $input.values as $product}
						<div id="{$input.name|escape:'html':'UTF-8'}-PRODUCT-{$product.id|intval}" class="form-control-static">
							<button type="button" class="btn btn-default" name="{$product.id|intval}" onclick="payline_delProduct({$product.id|intval})">
								<i class="icon-remove text-danger"></i>
							</button>
							{if (version_compare($smarty.const._PS_VERSION_, '1.7.0.0', '>='))}
								<img src="../img/tmp/product_mini_{$product.id_image|intval}.jpg" />{$product.name|escape:'html':'UTF-8'}
							{else}
								<img src="../img/tmp/product_mini_{$product.id|intval}_{$id_shop|intval}.jpg" />{$product.name|escape:'html':'UTF-8'}
							{/if}
						</div>
					{/foreach}
				</div>
			</div>
		</div>
	{else}
		{$smarty.block.parent}
	{/if}
{/block}