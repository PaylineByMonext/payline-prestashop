{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

<div class="panel">
    <div class="panel-heading">
        {l s='New version of the module' mod='payline'}
    </div>
    <div class="form-wrapper">
        <div class="alert alert-warning">
            <ul class="list-unstyled">
                <li>
                    <p>{l s='We have detected that you installed a new version of the module on your shop' mod='payline'}</p>
                </li>
            </ul>
        </div>

        <div class="text-center">
            <a href="{$base_config_url|escape:'html':'UTF-8'}&makeUpdate=1" class="btn btn-warning btn-footer">
                {l s='Please click here in order to finish the installation process' mod='payline'}
            </a>
        </div>
    </div>
</div>