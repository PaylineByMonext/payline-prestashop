<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

class paylineValidationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $paylineToken = null;

        if (Tools::getValue('paylinetoken')) {
            // Token from widget
            $paylineToken = Tools::getValue('paylinetoken');
        } elseif (Tools::getValue('token')) {
            // Token from Payline (redirect)
            $paylineToken = Tools::getValue('token');
        }

        if (!empty($paylineToken)) {
            $this->module->processCustomerPaymentReturn($paylineToken);
        }
    }
}
