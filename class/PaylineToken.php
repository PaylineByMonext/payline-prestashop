<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

class PaylineToken
{
    /**
     * Insert token into table
     * @param Order $order
     * @param Cart $cart
     * @param string $token
     * @return bool
     */
    public static function insert(Order $order, Cart $cart, $token)
    {
        return Db::getInstance()->execute('
            INSERT IGNORE INTO `'._DB_PREFIX_.'payline_token` (`id_order`, `id_cart`, `token`)
            VALUES('.(int)$order->id.', '.(int)$cart->id.', "'.pSQL($token).'")');
    }

    /**
     * Retrieve token by id_order
     * @param int $idOrder
     * @return string
     */
    public static function getByIdOrder($idOrder)
    {
        $result = Db::getInstance()->getValue('SELECT `token` FROM `'._DB_PREFIX_.'payline_token` WHERE `id_order`='.(int)$idOrder);
        if (!empty($result)) {
            return $result;
        }

        return null;
    }

    /**
     * Retrieve token by id_cart
     * @param int $idCart
     * @return string
     */
    public static function getByIdCart($idCart)
    {
        $result = Db::getInstance()->getValue('SELECT `token` FROM `'._DB_PREFIX_.'payline_token` WHERE `id_order`='.(int)$idCart);
        if (!empty($result)) {
            return $result;
        }

        return null;
    }
}
