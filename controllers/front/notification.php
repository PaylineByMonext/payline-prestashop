<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

class paylineNotificationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $notificationType = Tools::getValue('notificationType');

        if ($notificationType == 'WEBTRS' && Tools::getValue('token')) {
            $this->module->processNotification(Tools::getValue('token'));
        } elseif ($notificationType == 'TRS' && Tools::getValue('transactionId')) {
            $this->module->processTransactionNotification(Tools::getValue('transactionId'));
        } elseif ($notificationType == 'BILL' && Tools::getValue('transactionId') && Tools::getValue('paymentRecordId') && Tools::getValue('paymentMode') == 'NX') {
            $this->module->processNxNotification(Tools::getValue('transactionId'), Tools::getValue('paymentRecordId'));
        } elseif ($notificationType == 'BILL' && Tools::getValue('transactionId') && Tools::getValue('paymentRecordId') && Tools::getValue('paymentMode') == 'REC') {
            $this->module->processRecNotification(Tools::getValue('transactionId'), Tools::getValue('paymentRecordId'));
        } else {
            PrestaShopLogger::addLog('Payline - Unknown notification type "'. $notificationType .'"');
        }
    }

    /**
     * @see FrontController::displayMaintenancePage()
     */
    protected function displayMaintenancePage()
    {
        // Prevent maintenance page to be triggered
    }
}
