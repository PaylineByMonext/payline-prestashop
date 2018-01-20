<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_2_0($module)
{
    Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'payline_token` ADD COLUMN `payment_record_id` varchar(12) AFTER `token`;');
    Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'payline_token` ADD COLUMN `transaction_id` varchar(50) AFTER `payment_record_id`;');
    Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'payline_token` CHANGE COLUMN `token` `token` varchar(255);');

    $module->registerHook('displayCustomerAccount');

    return true;
}
