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
     * @param string $paymentRecordId
     * @param string $transactionId
     * @return bool
     */
    public static function insert(Order $order, Cart $cart, $token, $paymentRecordId = null, $transactionId = null)
    {
        if (empty($paymentRecordId)) {
            $paymentRecordId = '';
        }
        if (empty($transactionId)) {
            $transactionId = '';
        }
        return Db::getInstance()->execute('
            INSERT IGNORE INTO `'._DB_PREFIX_.'payline_token` (`id_order`, `id_cart`, `token`, `payment_record_id`, `transaction_id`)
            VALUES('.(int)$order->id.', '.(int)$cart->id.', "'.pSQL($token).'", "'.pSQL($paymentRecordId).'", "'.pSQL($transactionId).'")');
    }

    /**
     * Update payment_record_id into table
     * @param Order $order
     * @param string $paymentRecordId
     * @return bool
     */
    public static function setPaymentRecordIdByIdOrder(Order $order, $paymentRecordId)
    {
        return Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'payline_token` SET `payment_record_id`="'.pSQL($paymentRecordId).'" WHERE `id_order`='.(int)$order->id);
    }

    /**
     * Retrieve token by id_order
     * @param int $idOrder
     * @return string
     */
    public static function getTokenByIdOrder($idOrder)
    {
        $result = Db::getInstance()->getValue('SELECT `token` FROM `'._DB_PREFIX_.'payline_token` WHERE `id_order`='.(int)$idOrder);
        if (!empty($result)) {
            return $result;
        }

        return null;
    }

    /**
     * Retrieve payment record id by id_order
     * @param int $idOrder
     * @return string
     */
    public static function getPaymentRecordIdByIdOrder($idOrder)
    {
        $result = Db::getInstance()->getValue('SELECT `payment_record_id` FROM `'._DB_PREFIX_.'payline_token` WHERE `id_order`='.(int)$idOrder);
        if (!empty($result)) {
            return $result;
        }

        return null;
    }

    /**
     * Retrieve list of payment record id by id_customer
     * @param int $idCustomer
     * @return array
     */
    public static function getPaymentRecordIdListByIdCustomer($idCustomer)
    {
        $paymentRecordIdList = array();

        $result = Db::getInstance()->executeS('SELECT DISTINCT `payment_record_id` FROM `'._DB_PREFIX_.'payline_token` WHERE `id_order` IN (SELECT `id_order` FROM `'._DB_PREFIX_.'orders` WHERE `id_customer`='.(int)$idCustomer . ')');
        if (!empty($result)) {
            foreach ($result as $paymentRecordRow) {
                $paymentRecordIdList[] = $paymentRecordRow['payment_record_id'];
            }
        }

        return $paymentRecordIdList;
    }

    /**
     * Retrieve list of id_order for a payment_record_id
     * @param string $paymentRecordId
     * @return array
     */
    public static function getIdOrderListByPaymentRecordId($paymentRecordId)
    {
        $idOrderList = array();

        $result = Db::getInstance()->executeS('SELECT DISTINCT `id_order` FROM `'._DB_PREFIX_.'payline_token` WHERE `payment_record_id`="'.pSQL($paymentRecordId).'" ORDER BY `id_order`');
        if (!empty($result)) {
            foreach ($result as $paymentRecordRow) {
                $idOrderList[] = $paymentRecordRow['id_order'];
            }
        }

        return $idOrderList;
    }

    /**
     * Retrieve id_transaction by id_order
     * @param int $idOrder
     * @return string
     */
    public static function getIdTransactionByIdOrder($idOrder)
    {
        $result = Db::getInstance()->getValue('SELECT `transaction_id` FROM `'._DB_PREFIX_.'payline_token` WHERE `id_order`='.(int)$idOrder);
        if (!empty($result)) {
            return $result;
        }

        return null;
    }

    /**
     * Retrieve id_order by transaction_id
     * @param int $idTransaction
     * @return string
     */
    public static function getIdOrderByIdTransaction($idTransaction)
    {
        $result = Db::getInstance()->getValue('SELECT `id_order` FROM `'._DB_PREFIX_.'payline_token` WHERE `transaction_id`="'.pSQL($idTransaction).'"');
        if (!empty($result)) {
            return $result;
        } else {
            // Fallback method, use native PrestaShop table
            $orderReference = Db::getInstance()->getValue('SELECT `order_reference` FROM `'._DB_PREFIX_.'order_payment` WHERE `transaction_id`="'.pSQL($idTransaction).'"');
            if (!empty($orderReference)) {
                $idOrder = Db::getInstance()->getValue('SELECT `id_order` FROM `'._DB_PREFIX_.'orders` WHERE `reference`="'.pSQL($orderReference).'"');
                if (!empty($idOrder)) {
                    return $idOrder;
                }
            }
        }

        return null;
    }
}
