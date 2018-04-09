<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

class paylinepayment_nxModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;
    public $display_column_right = false;

    /**
     * @see FrontController::setMedia()
     */
    public function setMedia()
    {
        parent::setMedia();

        $assets = PaylinePaymentGateway::getAssetsToRegister();
        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            foreach ($assets['js'] as $file) {
                $this->context->controller->registerJavascript('modules-'.$this->module->name.'-payment-js-1', $file, array('server' => 'remote', 'position' => 'bottom', 'priority' => 150));
            }
            foreach ($assets['css'] as $file) {
                $this->context->controller->registerStylesheet('modules-'.$this->module->name.'-payment-css-1', $file, array('server' => 'remote', 'media' => 'all', 'priority' => 900));
            }
        } else {
            foreach ($assets['js'] as $file) {
                $this->context->controller->addJS($file);
            }
            foreach ($assets['css'] as $file) {
                $this->context->controller->addCSS($file);
            }
        }
    }

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        // Redirect to order page if we are using redirect/lightbox method
        if (Configuration::get('PAYLINE_RECURRING_UX') == 'redirect' || Configuration::get('PAYLINE_RECURRING_UX') == 'lightbox') {
            Tools::redirect('index.php?controller=order');
        }

        parent::initContent();

        $cart = $this->context->cart;
        if (!$this->module->isPaymentAvailable(PaylinePaymentGateway::RECURRING_PAYMENT_METHOD)) {
            Tools::redirect('index.php?controller=order');
        }

        $recurringTitle = Configuration::get('PAYLINE_RECURRING_TITLE', $this->context->language->id);
        $recurringSubTitle = Configuration::get('PAYLINE_RECURRING_SUBTITLE', $this->context->language->id);

        list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::RECURRING_PAYMENT_METHOD);

        $this->context->smarty->assign(array(
            'payline_token' => $paymentRequest['token'],
            'payline_title' => $recurringTitle,
            'payline_subtitle' => $recurringSubTitle,
            'payline_first_amount' => Tools::displayPrice($paymentRequestParams['recurring']['firstAmount']/100),
            'payline_next_amount' => Tools::displayPrice($paymentRequestParams['recurring']['amount']/100),
            'payline_billing_left' => ((int)$paymentRequestParams['recurring']['billingLeft'] - 1),
            'payline_ux_mode' => Configuration::get('PAYLINE_RECURRING_UX'),
            'payline_cart_total' => $cart->getOrderTotal(),
        ));

        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $this->setTemplate('module:payline/views/templates/front/1.7/payment_nx.tpl');
        } else {
            $this->setTemplate('1.6/payment_nx.tpl');
        }
    }
}
