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

function upgrade_module_2_1_0($module)
{
    $module->registerHook('actionAdminOrdersListingResultsModifier');
    Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'payline_token`');
    Db::getInstance()->execute('
    CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'payline_token` (
        `id_order` int(10) UNSIGNED NOT NULL,
        `id_cart` int(10) UNSIGNED NOT NULL,
        `token` VARCHAR(255) NOT NULL,
        UNIQUE `id_order` (`id_order`),
        UNIQUE `id_cart` (`id_cart`)
    ) ENGINE='._MYSQL_ENGINE_.' CHARSET=utf8');

    return true;
}
