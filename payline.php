<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 * @version   2.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payline' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'autoload.php');
require_once(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payline' . DIRECTORY_SEPARATOR . 'class' . DIRECTORY_SEPARATOR . 'PaylinePaymentGateway.php');

class payline extends PaymentModule
{
    /**
     * Order state for Payline specific processes
     * @var array
     */
    protected $customOrderStateList = array(
        'PAYLINE_ID_STATE_AUTOR' => array(
            'name' => array(
                'en' => 'Authorized payment',
                'fr' => 'Paiement autorisé',
            ),
            'send_email' => true,
            'color' => '#dfe0ff',
            'hidden' => false,
            'delivery' => false,
            'logable' => true,
            'invoice' => true,
            'template' => 'payment',
        ),
        'PAYLINE_ID_STATE_PENDING' => array(
            'name' => array(
                'en' => 'Waiting for payment confirmation',
                'fr' => 'En attente de confirmation de paiement',
            ),
            'send_email' => false,
            'color' => '#4169e1',
            'hidden' => false,
            'delivery' => false,
            'logable' => true,
            'invoice' => false,
            'template' => 'payment',
        ),
    );

    /**
     * Response code that does define the transaction approved state
     * @var array
     */
    protected $approvedResponseCode = array(
        '34230',
        '34330',
        '02500',
        '02501',
    );

    /**
     * Response code that does define the pending state
     * @var array
     */
    protected $pendingResponseCode = array(
        '02000',
        '02005',
        '02016',
    );

    // Errors constants
    const INVALID_AMOUNT = 1;
    const INVALID_CART_ID = 2;

    /**
     * Module __construct
     * @since 2.0.0
     * @return void
     */
    public function __construct()
    {
        $this->name = 'payline';
        $this->tab = 'payments_gateways';
        $this->module_key = '';
        $this->version = '2.0.0';
        $this->author = 'Monext';
        $this->need_instance = true;

        $this->bootstrap = true;

        parent::__construct();
        $this->displayName = 'Payline';
        $this->description = $this->l('Pay with secure payline gateway');
        $this->confirmUninstall = $this->l('Do you really want to remove the module?');
        $this->limited_countries = array();
        $this->limited_currencies = array();

        // if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            // Set minimum compliancy for PrestaShop 1.7
        //     $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
        // }
    }

    /**
     * Create custom order state
     * @since 2.0.0
     * @return bool
     */
    public function createCustomOrderState()
    {
        foreach ($this->customOrderStateList as $configurationKey => $customOrderState) {
            $idOrderState = Configuration::get($configurationKey);
            if (empty($idOrderState)) {
                // Order state has to be created
                $orderState = new OrderState($idOrderState);
                $orderState->logo = 'paylineLogo';
                foreach ($customOrderState as $k => $v) {
                    if ($k != 'name') {
                        $orderState->{$k} = $v;
                    } else {
                        $orderState->name = array();
                        foreach ($v as $isoLang => $name) {
                            $idLang = Language::getIdByIso($isoLang);
                            if (!empty($idLang)) {
                                $orderState->name[$idLang] = $name;
                            }
                        }
                    }
                }
                if ($orderState->save()) {
                    // Save id_order_state
                    Configuration::updateValue($configurationKey, $orderState->id);
                    // Associate icon
                    $sourceLogo = _PS_MODULE_DIR_ . $this->name . DIRECTORY_SEPARATOR . 'logo.gif';
                    if (file_exists($sourceLogo) && is_readable($sourceLogo)) {
                        if (is_writable(_PS_IMG_DIR_ . 'os')) {
                            copy($sourceLogo, _PS_IMG_DIR_ . 'os' . DIRECTORY_SEPARATOR . (int)$orderState->id. '.gif');
                        }
                    }
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Module install
     * @since 2.0.0
     * @return bool
     */
    public function install()
    {
        // Init some configuration values
        Configuration::updateValue('PAYLINE_API_STATUS', false);
        Configuration::updateValue('PAYLINE_LIVE_MODE', false);
        Configuration::updateValue('PAYLINE_MERCHANT_ID', false);
        Configuration::updateValue('PAYLINE_ACCESS_KEY', false);
        Configuration::updateValue('PAYLINE_POS', false);
        Configuration::updateValue('PAYLINE_PROXY_HOST', false);
        Configuration::updateValue('PAYLINE_PROXY_PORT', false);
        Configuration::updateValue('PAYLINE_PROXY_LOGIN', false);
        Configuration::updateValue('PAYLINE_PROXY_PASSWORD', false);
        Configuration::updateValue('PAYLINE_CONTRACTS', false);
        Configuration::updateValue('PAYLINE_ALT_CONTRACTS', false);

        // Run parent install process, register to hooks, then force update module position
        if (!parent::install()
            // Generic hooks
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('displayHeader')
            || !$this->registerHook('displayAdminOrderLeft')
            || !$this->registerHook('actionObjectOrderSlipAddAfter')
            || !$this->registerHook('actionOrderStatusUpdate')
            || (version_compare(_PS_VERSION_, '1.7.0.0', '<') && !$this->registerHook('displayPayment'))
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('paymentOptions')
            // Install custom order state
            || !$this->createCustomOrderState()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the FO.
     * @since 2.0.0
     * @return void
     */
    public function hookDisplayHeader()
    {
        // Display alert if payment failed
        if (($this->context->controller instanceof OrderController || $this->context->controller instanceof OrderOpcController || $this->context->controller instanceof paylinePaymentModuleFrontController) && Tools::getValue('paylineError') && Tools::getValue('paylinetoken')) {
            $this->context->controller->errors[] = $this->l('There was an error while processing your previous payment.');
            if (Tools::getIsset('paylineErrorCode')) {
                $errorCode = (int)Tools::getValue('paylineErrorCode');
                $humanErrorCode = $this->getHumanErrorCode($errorCode);
                if (!empty($humanErrorCode)) {
                    $this->context->controller->errors[] = $humanErrorCode;
                }
            }
            $this->context->controller->errors[] = $this->l('Please try to use another payment method or another credit card.');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     * @since 2.0.0
     * @return void
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name || Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJquery();
            $this->context->controller->addJqueryUi('ui.sortable');
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
        if (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order')) {
            $this->context->controller->addJquery();
            $this->context->controller->addJS($this->_path.'views/js/order.js');
        }
        if (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order') && Tools::getValue('paylineCapture')) {
            $idTransaction = Tools::getValue('paylineCapture');
            $idOrder = (int)Tools::getValue('id_order');
            $order = new Order($idOrder);
            if (!empty($idTransaction) && Validate::isLoadedObject($order)) {
                // Process capture of a specific transaction
                $this->processTransactionCapture($order, $idTransaction);
            }
        } elseif (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order') && Tools::getValue('paylineReset')) {
            $idTransaction = Tools::getValue('paylineReset');
            $idOrder = (int)Tools::getValue('id_order');
            $order = new Order($idOrder);
            if (!empty($idTransaction) && Validate::isLoadedObject($order)) {
                // Process reset of a specific transaction
                $this->processTransactionReset($order, $idTransaction);
            }
        } elseif (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order') && Tools::getValue('paylineProcessFullRefund')) {
            $idOrder = (int)Tools::getValue('id_order');
            $order = new Order($idOrder);
            if (Validate::isLoadedObject($order)) {
                // Process full refund of a specific order
                $this->processFullOrderRefund($order);
            }
        } elseif (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order') && Tools::getValue('paylineCaptureOK')) {
            // Capture OK, show confirmation message
            $this->context->controller->confirmations[] = $this->l('Order was successfully captured');
        } elseif (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order') && Tools::getValue('paylineResetOK')) {
            // Reset OK, show confirmation message
            $this->context->controller->confirmations[] = $this->l('Order was successfully cancelled (authorization reset)');
        } elseif (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order') && Tools::getValue('paylineFullRefundOK')) {
            // Full refund OK, show confirmation message
            $this->context->controller->confirmations[] = $this->l('Order was successfully refunded');
        }
    }

    /**
     * Process full refund on an order (from BO)
     * @param Order $order
     * @return void
     */
    protected function processFullOrderRefund(Order $order)
    {
        // Check if transaction ID is the same
        $idTransaction = null;
        $orderPayments = OrderPayment::getByOrderId($order->id);
        if (sizeof($orderPayments)) {
            // Retrieve transaction ID
            $paylineTransaction = current($orderPayments);
            $idTransaction = $paylineTransaction->transaction_id;

            $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
            if (PaylinePaymentGateway::isValidResponse($transaction)) {
                $refund = PaylinePaymentGateway::refundTransaction($idTransaction, null, $this->l('Manual refund from PrestaShop BackOffice'));
                if (PaylinePaymentGateway::isValidResponse($refund)) {
                    // Refund OK
                    // Change order state
                    $history = new OrderHistory();
                    $history->id_order = (int)$order->id;
                    $history->changeIdOrderState(_PS_OS_REFUND_, (int)$order->id);
                    $history->addWithemail();

                    // Force reload of $order (because it has been edited by OrderHistory)
                    $order = new Order($order->id);

                    $orderInvoice = new OrderInvoice($order->invoice_number);
                    if (!Validate::isLoadedObject($orderInvoice)) {
                        $orderInvoice = null;
                    }

                    $orderSlipDetailsList = array();
                    // Amount for refund
                    $amountToRefund = 0.00;
                    foreach ($order->getProducts() as $idOrderDetail => $product) {
                        if (($product['product_quantity'] - $product['product_quantity_refunded']) > 0) {
                            $orderSlipDetailsList[(int)$idOrderDetail] = array(
                                'id_order_detail' => $idOrderDetail,
                                'quantity' => ($product['product_quantity'] - $product['product_quantity_refunded']),
                                'unit_price' => (float)$product['unit_price_tax_excl'],
                                'amount' => $product['unit_price_tax_incl'] * ($product['product_quantity'] - $product['product_quantity_refunded']),
                            );
                        }
                        $amountToRefund += round(($product['product_price_wt'] * $product['product_quantity']), 2);
                    }
                    $amountToRefund += (float)($order->total_shipping);

                    // Create order slip (available since PS 1.6.0.11)
                    if (method_exists('OrderSlip', 'create')) {
                        OrderSlip::create($order, $orderSlipDetailsList, null);
                    }

                    $this->addOrderPayment($order, $amountToRefund * -1, null, $refund['transaction']['id'], null, null, $orderInvoice);

                    // Wait 1s because Payline API may take some time to be updated after a refund
                    sleep(1);

                    Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $order->id . '&vieworder&paylineFullRefundOK=1');
                } else {
                    // Refund NOK
                    $errors = PaylinePaymentGateway::getErrorResponse($refund);
                    $this->context->controller->errors[] = sprintf($this->l('Unable to process the refund, Payline reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
                }
            } else {
                $errors = PaylinePaymentGateway::getErrorResponse($transaction);
                $this->context->controller->errors[] = sprintf($this->l('Unable to process the refund, Payline reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
            }
        } else {
            $this->context->controller->errors[] = $this->l('Unable to find any Payline transaction ID on this order');
        }
    }

    /**
     * Process transaction capture (from BO)
     * @param Order $order
     * @param int $idTransaction
     * @param bool $doRedirect
     * @return void
     */
    protected function processTransactionCapture(Order $order, $idTransaction, $doRedirect = true)
    {
        $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
        if (PaylinePaymentGateway::isValidResponse($transaction)) {
            $capture = PaylinePaymentGateway::captureTransaction($idTransaction, 'CPT', $this->l('Manual capture from PrestaShop BackOffice'));
            if (PaylinePaymentGateway::isValidResponse($capture)) {
                // Capture OK
                // Change order state
                $history = new OrderHistory();
                $history->id_order = (int)$order->id;
                $history->changeIdOrderState(_PS_OS_PAYMENT_, (int)$order->id);
                $history->addWithemail();

                if ($doRedirect) {
                    // Wait 1s because Payline API may take some time to be updated after a capture
                    sleep(1);

                    Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $order->id . '&vieworder&paylineCaptureOK=1');
                }
            } else {
                // Capture NOK
                $errors = PaylinePaymentGateway::getErrorResponse($capture);
                $this->context->controller->errors[] = sprintf($this->l('Unable to process the capture, Payline reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
            }
        } else {
            $errors = PaylinePaymentGateway::getErrorResponse($transaction);
            $this->context->controller->errors[] = sprintf($this->l('Unable to process the capture, Payline reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
        }
    }

    /**
     * Process transaction reset (from BO)
     * @param Order $order
     * @param int $idTransaction
     * @return void
     */
    protected function processTransactionReset(Order $order, $idTransaction)
    {
        $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
        if (PaylinePaymentGateway::isValidResponse($transaction)) {
            $capture = PaylinePaymentGateway::resetTransaction($idTransaction, $this->l('Manual reset from PrestaShop BackOffice'));
            if (PaylinePaymentGateway::isValidResponse($capture)) {
                // Reset OK
                // Change order state
                $history = new OrderHistory();
                $history->id_order = (int)$order->id;
                $history->changeIdOrderState(_PS_OS_ERROR_, (int)$order->id);
                $history->addWithemail();

                // Wait 1s because Payline API may take some time to be updated after a capture
                sleep(1);

                Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $order->id . '&vieworder&paylineResetOK=1');
            } else {
                // Reset NOK
                $errors = PaylinePaymentGateway::getErrorResponse($capture);
                $this->context->controller->errors[] = sprintf($this->l('Unable to process the reset, Payline reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
            }
        } else {
            $errors = PaylinePaymentGateway::getErrorResponse($transaction);
            $this->context->controller->errors[] = sprintf($this->l('Unable to process the reset, Payline reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
        }
    }

    /**
     * Display payment result on confirmation page
     * @since 2.0.0
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderLeft($params)
    {
        // Check if module is enabled
        if (!$this->active) {
            return;
        }
        if (!empty($params['id_order'])) {
            $order = new Order($params['id_order']);
            if (Validate::isLoadedObject($order) && $order->module == 'payline') {
                $orderPayments = OrderPayment::getByOrderId($order->id);
                $sameTransactionID = false;
                $transactionsList = array();
                foreach ($orderPayments as $orderPayment) {
                    if (preg_match('/payline/i', $orderPayment->payment_method) && !empty($orderPayment->transaction_id)) {
                        $transaction = PaylinePaymentGateway::getTransactionInformations($orderPayment->transaction_id);
                        if (isset($transaction['associatedTransactionsList']) && isset($transaction['associatedTransactionsList']['associatedTransactions']) && sizeof($transaction['associatedTransactionsList']['associatedTransactions'])) {
                            foreach ($transaction['associatedTransactionsList']['associatedTransactions'] as $associatedTransaction) {
                                $transactionsList[$associatedTransaction['transactionId']] = $associatedTransaction;
                                $transactionsList[$associatedTransaction['transactionId']]['originalTransaction'] = $transaction;
                            }
                        }
                    }
                }
                // Do we allow capture action ?
                $allowCapture = !count($order->getHistory((int)$this->context->language->id, false, true, OrderState::FLAG_PAID));
                // Do we allow refund action ?
                $allowRefund = !$allowCapture && !count($order->getHistory((int)$this->context->language->id, _PS_OS_REFUND_, true));
                // Do we allow reset action
                $allowReset = $allowCapture;

                $this->context->smarty->assign(array(
                    'id_order' => (int)$params['id_order'],
                    'transactionsList' => $transactionsList,
                    'allowCapture' => $allowCapture,
                    'allowRefund' => $allowRefund,
                    'allowReset' => $allowReset,
                ));
                if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
                    return $this->context->smarty->fetch($this->local_path.'views/templates/hook/admin_order.tpl');
                } else {
                    return $this->display(__FILE__, 'admin_order.tpl');
                }
            }
        }
    }

    /**
     * Process capture when order enter in a specific state
     * @since 2.0.0
     * @param array $params
     * @return void
     */
    public function hookActionOrderStatusUpdate($params)
    {
        if (!empty($params['id_order']) && !empty($params['newOrderStatus']) && Validate::isLoadedObject($params['newOrderStatus']) && $params['newOrderStatus']->id == Configuration::get('PAYLINE_WEB_CASH_VALIDATION')) {
            // We have to trigger capture here
            $idTransaction = null;
            $order = new Order((int)$params['id_order']);
            if (Validate::isLoadedObject($order)) {
                $orderPayments = OrderPayment::getByOrderId($order->id);
                if (sizeof($orderPayments)) {
                    // Retrieve transaction ID
                    $paylineTransaction = current($orderPayments);
                    $idTransaction = $paylineTransaction->transaction_id;
                }
            }
            if (!empty($idTransaction) && Validate::isLoadedObject($order)) {
                // Process capture of a specific transaction
                $this->processTransactionCapture($order, $idTransaction, false);
            }
        }
    }

    /**
     * Process partial refund on card used for the payment
     * @since 2.0.0
     * @param array $params
     * @return void
     */
    public function hookActionObjectOrderSlipAddAfter($params)
    {
        // Prevent order slip creation in case we are into a full refund process
        if (Tools::getValue('paylineProcessFullRefund')) {
            return;
        }

        $order = new Order($params['object']->id_order);
        $amountToRefund = (float)$params['object']->total_products_tax_incl + (float)$params['object']->total_shipping_tax_incl;
        
        if (Context::getContext()->employee->isLoggedBack()
            && Validate::isLoadedObject($order)
            && $order->module == $this->name
            && $order->hasBeenPaid()
            && $amountToRefund > 0
            && !Tools::getValue('generateDiscount') && !Tools::getValue('generateDiscountRefund')) {
            $idTransaction = null;
            $orderPayments = OrderPayment::getByOrderId($order->id);
            if (sizeof($orderPayments)) {
                // Retrieve transaction ID
                $paylineTransaction = current($orderPayments);
                $idTransaction = $paylineTransaction->transaction_id;

                // Get transaction informations
                $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
                if (PaylinePaymentGateway::isValidResponse($transaction)) {
                    $refund = PaylinePaymentGateway::refundTransaction($idTransaction, $amountToRefund, $this->l('Manual partial refund from PrestaShop BackOffice'));
                    if (PaylinePaymentGateway::isValidResponse($refund)) {
                        // Refund OK
                        $orderInvoice = new OrderInvoice($order->invoice_number);
                        if (!Validate::isLoadedObject($orderInvoice)) {
                            $orderInvoice = null;
                        }

                        $this->addOrderPayment($order, $amountToRefund * -1, null, $refund['transaction']['id'], null, null, $orderInvoice);

                        // Wait 1s because Payline API may take some time to be updated after a refund
                        sleep(1);

                        // Partial refund OK, show confirmation message
                        $this->context->controller->confirmations[] = $this->l('Order was successfully partially refunded');
                    } else {
                        // Refund NOK
                        $errors = PaylinePaymentGateway::getErrorResponse($refund);
                        $this->context->controller->errors[] = sprintf($this->l('Unable to process the refund, Payline reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
                    }
                } else {
                    $errors = PaylinePaymentGateway::getErrorResponse($transaction);
                    $this->context->controller->errors[] = sprintf($this->l('Unable to process the refund, Payline reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
                }
            } else {
                $this->context->controller->errors[] = $this->l('Unable to find any Payline transaction ID on this order');
            }
        }
    }

    /**
     * Display payment result on confirmation page
     * @since 2.0.0
     * @param string $params
     * @return array
     */
    public function hookPaymentReturn($params)
    {
        // Check if module is enabled and PS < 1.7
        if (!$this->active || version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            return;
        }

        // Order
        $order = $params['objOrder'];
        // Last order state
        // $state = $order->getCurrentState();

        $idTransaction = null;
        $orderPayments = OrderPayment::getByOrderId($order->id);
        if (sizeof($orderPayments)) {
            // Retrieve transaction ID
            $paylineTransaction = current($orderPayments);
            $idTransaction = $paylineTransaction->transaction_id;
        }

        $this->smarty->assign(array(
            'payline_transaction_id' => $idTransaction,
        ));

        return $this->display(__FILE__, 'payment_return.tpl');
    }

    /**
     * Display payment button into payment module list (last order step)
     * @since 2.0.0
     * @param array $params
     * @return array of PaymentOption
     */
    public function hookPaymentOptions($params)
    {
        // Check if module is enabled and payment gateway is configured for at least one payment method
        // Check if current cart currency is allowed
        if (!$this->isPaymentAvailable()) {
            return;
        }

        $paymentMethodList = array();

        if (Configuration::get('PAYLINE_WEB_CASH_ENABLE')) {
            $uxMode = Configuration::get('PAYLINE_WEB_CASH_UX');

            $webCash = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $webCash->setModuleName($this->name)->setCallToActionText($this->l('Pay by Payline'));

            if ($uxMode == 'lightbox') {
                $paymentRequest = PaylinePaymentGateway::createPaymentRequest($this->context);
                if (!empty($paymentRequest['token'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_ux_mode' => Configuration::get('PAYLINE_WEB_CASH_UX'),
                        'payline_assets' => PaylinePaymentGateway::getAssetsToRegister(),
                    ));
                    $webCash->setAction('javascript:Payline.Api.init()');
                    $webCash->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/lightbox.tpl'));
                } else {
                    $webCash = null;
                }
            } elseif ($uxMode == 'redirect') {
                $paymentRequest = PaylinePaymentGateway::createPaymentRequest($this->context);
                if (!empty($paymentRequest['redirectURL'])) {
                    $webCash->setAction($paymentRequest['redirectURL']);
                } else {
                    $webCash = null;
                }
            } else {
                $webCash->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true));
            }

            if ($webCash !== null) {
                $paymentMethodList[] = $webCash;
            }
        }

        return $paymentMethodList;
    }

    /**
     * Display payment button into payment module list (last order step)
     * @since 2.0.0
     * @param array $params
     * @return string
     */
    public function hookDisplayPayment($params)
    {
        // Check if module is enabled and payment gateway is configured for at least one payment method
        // Check if PS < 1.7
        if (!$this->isPaymentAvailable() || version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            return;
        }

        $this->context->controller->addCSS($this->_path.'views/css/front.css');

        $paymentReturn = '';

        if (Configuration::get('PAYLINE_WEB_CASH_ENABLE')) {
            $uxMode = Configuration::get('PAYLINE_WEB_CASH_UX');

            $this->smarty->assign(array(
                'payline_ux_mode' => Configuration::get('PAYLINE_WEB_CASH_UX'),
            ));

            if ($uxMode == 'lightbox') {
                $paymentRequest = PaylinePaymentGateway::createPaymentRequest($this->context);
                if (!empty($paymentRequest['token'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_assets' => PaylinePaymentGateway::getAssetsToRegister(),
                        'payline_href' => 'javascript:Payline.Api.init()',
                    ));

                    $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment.tpl');
                }
            } elseif ($uxMode == 'redirect') {
                $paymentRequest = PaylinePaymentGateway::createPaymentRequest($this->context);
                if (!empty($paymentRequest['redirectURL'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_href' => $paymentRequest['redirectURL'],
                    ));

                    $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment.tpl');
                }
            } else {
                $this->smarty->assign(array(
                    'payline_href' => $this->context->link->getModuleLink($this->name, 'payment', array(), true),
                ));

                $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment.tpl');
            }
        }

        return $paymentReturn;
    }

    /**
     * Check if the payment is available
     * @since 2.0.0
     * @return bool
     */
    public function isPaymentAvailable()
    {
        if (!$this->active) {
            return;
        }
        // Check for module and API state
        if (!Configuration::get('PAYLINE_API_STATUS')) {
            return false;
        }
        // Check if at least one payment method is available
        if (!Configuration::get('PAYLINE_WEB_CASH_ENABLE')) {
            return false;
        }
        // Check if current cart currency is allowed
        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=') && !$this->checkAllowedCurrency($this->context->cart)) {
            return false;
        }

        // Payment gateway configuration is OK and module is enabled
        return true;
    }

    /**
     * Check if the current cart currency is allowed
     * @since 2.0.0
     * @param Cart $cart
     * @return bool
     */
    public function checkAllowedCurrency(Cart $cart)
    {
        $currency_order = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if module is up to date
     * @since 2.0.0
     * @param bool $updateDb
     * @param bool $displayConfirm
     * @return bool
     */
    public function checkIfModuleIsUpdate($updateDb = false, $displayConfirm = true)
    {
        if (!$updateDb && $this->version != Configuration::get('PAYLINE_LAST_VERSION')) {
            return false;
        }

        if ($updateDb) {
            Configuration::updateValue('PAYLINE_LAST_VERSION', $this->version);
            // Specific code for module

            // End - Specific code for module
            if ($displayConfirm) {
                $this->context->controller->confirmations[] = $this->l('Module updated successfully');
            }
        }
        return true;
    }

    /**
     * Check module requirements and dependencies
     * @since 2.0.0
     * @return bool
     */
    private function checkModuleRequirements()
    {
        $moduleRequirements = true;

        // Check PHP version
        if (PHP_VERSION_ID < 50400) {
            $this->context->controller->errors[] = $this->l('Your PHP version is too old, you must run at least PHP version 5.4.0');
            $moduleRequirements = false;
        }
        // Check curl PHP extension
        if (!extension_loaded('curl') || !function_exists('curl_init')) {
            $this->context->controller->errors[] = $this->l('PHP curl extension is missing, please ask your technical contact to enable it before continue.');
            $moduleRequirements = false;
        }
        // Check soap PHP extension
        if (!extension_loaded('soap')) {
            $this->context->controller->errors[] = $this->l('PHP soap extension is missing, please ask your technical contact to enable it before continue.');
            $moduleRequirements = false;
        }
        // Check openssl PHP extension
        if (!extension_loaded('openssl')) {
            $this->context->controller->errors[] = $this->l('PHP openssl extension is missing, please ask your technical contact to enable it before continue.');
            $moduleRequirements = false;
        }

        return $moduleRequirements;
    }

    /**
     * Load the configuration form
     * @since 2.0.0
     * @return string
     */
    public function getContent()
    {
        // Check module requirements
        if (!$this->checkModuleRequirements()) {
            // Save API status
            Configuration::updateValue('PAYLINE_API_STATUS', false);
            return;
        }
        $this->context->smarty->assign('module_dir', $this->_path);

        // Is the module up to date ?
        // if (Tools::getValue('makeUpdate')) {
        //     $this->checkIfModuleIsUpdate(true);
        // }
        // if (!$this->checkIfModuleIsUpdate(false)) {
        //     $this->context->smarty->assign(array(
        //         'base_config_url' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name,
        //     ));

        //     return $this->display($this->getLocalPath(), 'views/templates/admin/core/new_version_available.tpl');
        // }

        // If values have been submitted in the form, process.
        $this->postProcess();

        // Check payline credentials
        $paylineCheckCredentials = PaylinePaymentGateway::checkCredentials();
        Configuration::updateValue('PAYLINE_API_STATUS', $paylineCheckCredentials);
        $paylineCheckNoEnabledContract = false;

        // Add error messages if fields are empty
        if (Configuration::get('PAYLINE_MERCHANT_ID') && Configuration::get('PAYLINE_ACCESS_KEY') && !$paylineCheckCredentials) {
            $this->context->controller->errors[] = $this->l('Payline credentials are invalid, please fix them before continue.');
        }

        // Assign the first POS by default
        $currentPos = Configuration::get('PAYLINE_POS');
        if ($paylineCheckCredentials && !$currentPos) {
            $posList = PaylinePaymentGateway::getPointOfSales();
            if (sizeof($posList)) {
                $firstPos = current($posList);
                $currentPos = $firstPos;
                Configuration::updateValue('PAYLINE_POS', trim($firstPos['label']));
            }
        }

        // Force payline tab to be the activated tab as it's not configured yet
        if (!$paylineCheckCredentials) {
            // Show landing as default tab if merchant ID is not filled
            if (!Configuration::get('PAYLINE_MERCHANT_ID')) {
                $activeTab = 'landing';
            } else {
                $activeTab = 'payline';
            }
        } else {
            // Else, default tab is web-payment if there is no submitted form
            $activeTab = 'web-payment';
            if (Tools::isSubmit('submitPaylineweb-payment')) {
                $activeTab = 'web-payment';
            } elseif (Tools::isSubmit('submitPaylinepayline')) {
                $activeTab = 'payline';
            } elseif (Tools::isSubmit('submitPaylinecontracts')) {
                $activeTab = 'contracts';
            }

            // Add alert if all contracts are disabled
            $paylineCheckNoEnabledContract = (Configuration::get('PAYLINE_WEB_CASH_ENABLE') && !sizeof(PaylinePaymentGateway::getEnabledContracts()));
            if ($paylineCheckNoEnabledContract) {
                $this->context->controller->warnings[] = $this->l('You must enable at least one contract.');
                $activeTab = 'contracts';
            }
        }

        $this->context->smarty->assign('payline_active_tab', $activeTab);
        $this->context->smarty->assign('payline_api_status', Configuration::get('PAYLINE_API_STATUS'));
        $this->context->smarty->assign('payline_contracts_errors', $paylineCheckNoEnabledContract);

        $this->context->smarty->assign('payline_credentials_configuration', $this->renderForm('payline'));
        $this->context->smarty->assign('payline_web_payment_configuration', $this->renderForm('web-payment'));
        $this->context->smarty->assign('payline_contracts_configuration', $this->renderForm('contracts'));
        return $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     * @since 2.0.0
     * @return string
     */
    protected function renderForm($tabName)
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPayline' . $tabName;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues($tabName),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm($tabName)));
    }

    /**
     * Retrieve values for <select> items into HelperForm
     * @since 2.0.0
     * @param string $listName
     * @return array
     */
    protected function getConfigSelectList($listName)
    {
        if ($listName == 'payment-action') {
            return array(
                array(
                    'value' => 101,
                    'name' => $this->l('Autorization + Capture'),
                ),
                array(
                    'value' => 100,
                    'name' => $this->l('Autorization'),
                ),
            );
        } elseif ($listName == 'user-experience') {
            return array(
                array(
                    'value' => 'tab',
                    'name' => $this->l('in-shop tab'),
                ),
                array(
                    'value' => 'column',
                    'name' => $this->l('in-shop column'),
                ),
                array(
                    'value' => 'lightbox',
                    'name' => $this->l('lightbox'),
                ),
                array(
                    'value' => 'redirect',
                    'name' => $this->l('Redirect to payment page'),
                ),
            );
        } elseif ($listName == 'order-states') {
            $orderStatusListForSelect = array();
            foreach (OrderState::getOrderStates($this->context->language->id) as $os) {
                // Ignore order states related to a specific module or error/refund/waiting to be paid states
                if (!empty($os['module_name']) || in_array((int)$os['id_order_state'], array(_PS_OS_ERROR_, _PS_OS_REFUND_, (int)Configuration::get('PAYLINE_ID_STATE_AUTOR'), (int)Configuration::get('PAYLINE_ID_STATE_PENDING')))) {
                    continue;
                }
                $orderStatusListForSelect[] = array(
                    'value' => $os['id_order_state'],
                    'name' => $os['name'],
                );
            }

            return $orderStatusListForSelect;
        } elseif ($listName == 'pos') {
            $posListForSelect = array();
            $paylineCheckCredentials = PaylinePaymentGateway::checkCredentials();
            if ($paylineCheckCredentials) {
                foreach (PaylinePaymentGateway::getPointOfSales() as $pos) {
                    $posListForSelect[] = array(
                        'value' => $pos['label'],
                        'name' => $pos['label'],
                    );
                }
            }

            return $posListForSelect;
        } elseif ($listName == 'contracts') {
            $currentPos = Configuration::get('PAYLINE_POS');
            $contractsListForSelect = array();
            if (!empty($currentPos)) {
                $contractsList = PaylinePaymentGateway::getContractsByPosLabel($currentPos);
                foreach ($contractsList as $contract) {
                    $contractsListForSelect[] = array(
                        'value' => $contract['contractNumber'],
                        'name' => $contract['label'] . ' ('. $contract['contractNumber'] .')',
                    );
                }
            }

            return $contractsListForSelect;
        }
    }

    /**
     * Create the structure of your form.
     * @since 2.0.0
     * @param string $tabName
     * @return array
     */
    protected function getConfigForm($tabName)
    {
        $paylineCheckCredentials = PaylinePaymentGateway::checkCredentials();
        
        if ($tabName == 'payline') {
            return array(
                'form' => array(
                    'legend' => array(
                    'title' => $this->l('Payline configuration'),
                    'icon' => 'icon-money',
                    ),
                    'input' => array(
                        array(
                            'type' => 'switch',
                            'label' => $this->l('Live mode'),
                            'name' => 'PAYLINE_LIVE_MODE',
                            'is_bool' => true,
                            'desc' => $this->l('Set the payment as live (real charge) or test mode (no charge)'),
                            'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => true,
                                    'label' => $this->l('Enabled'),
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => false,
                                    'label' => $this->l('Disabled'),
                                )
                            ),
                        ),
                        array(
                            'type' => 'html',
                            'name' => '
                            <h2>' . $this->l('Payline API credentials') . '</h2>
                            <p>' . $this->l('You can retrieve all theses credentials here:') . ' <a target="_blank" href="https://www.payline.com/">https://www.payline.com/</a></p>',
                        ),
                        array(
                            'form_group_class' => ($paylineCheckCredentials === false ? 'has-error' : 'has-success'),
                            'required' => true,
                            'type' => 'text',
                            'prefix' => '<i class="icon icon-key"></i>',
                            'desc' => '',
                            'name' => 'PAYLINE_MERCHANT_ID',
                            'label' => $this->l('Merchant Id'),
                            'placeholder' => '',
                        ),
                        array(
                            'form_group_class' => ($paylineCheckCredentials === false ? 'has-error' : 'has-success'),
                            'required' => true,
                            'type' => 'text',
                            'prefix' => '<i class="icon icon-key"></i>',
                            'desc' => '',
                            'name' => 'PAYLINE_ACCESS_KEY',
                            'label' => $this->l('Access key'),
                            'placeholder' => '',
                        ),
                        array(
                            'form_group_class' => ($paylineCheckCredentials === false ? 'has-error hidden' : 'has-success'),
                            'required' => true,
                            'type' => 'select',
                            'desc' => '',
                            'name' => 'PAYLINE_POS',
                            'label' => $this->l('Point of sale'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('pos'),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'type' => 'html',
                            'name' => '
                            <h2>' . $this->l('Proxy configuration') . '</h2>',
                        ),
                        array(
                            'type' => 'text',
                            'desc' => '',
                            'name' => 'PAYLINE_PROXY_HOST',
                            'label' => $this->l('Host'),
                            'placeholder' => '',
                        ),
                        array(
                            'type' => 'text',
                            'maxlength' => 5,
                            'desc' => '',
                            'name' => 'PAYLINE_PROXY_PORT',
                            'label' => $this->l('Port'),
                            'placeholder' => '',
                        ),
                        array(
                            'type' => 'text',
                            'desc' => '',
                            'name' => 'PAYLINE_PROXY_LOGIN',
                            'label' => $this->l('Login'),
                            'placeholder' => '',
                        ),
                        array(
                            'type' => 'text',
                            'desc' => '',
                            'name' => 'PAYLINE_PROXY_PASSWORD',
                            'label' => $this->l('Password'),
                            'placeholder' => '',
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                    ),
                ),
            );
        } elseif ($tabName == 'web-payment') {
            return array(
                'form' => array(
                    'legend' => array(
                    'title' => $this->l('Web payment'),
                    'icon' => 'icon-cogs',
                    ),
                    'input' => array(
                        array(
                            'type' => 'html',
                            'name' => '
                            <h2>'.$this->l('Simple payment').'</h2>
                            <p>'.$this->l('Contracts activated through contracts configuration menu are displayed in the checkout. All payment information is filled on our secure user interface.').'</p>',
                        ),
                        array(
                            'type' => 'switch',
                            'label' => $this->l('Enable Web Payment'),
                            'name' => 'PAYLINE_WEB_CASH_ENABLE',
                            'is_bool' => true,
                            'desc' => $this->l('choose wether to display Payline simple payment in your checkout or not'),
                            'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => true,
                                    'label' => $this->l('Enabled'),
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => false,
                                    'label' => $this->l('Disabled'),
                                )
                            ),
                        ),
                        array(
                            'type' => 'select',
                            'desc' => $this->l('Select authorization+capture if you want to charge your customer at order creation. Charge him later with authorization'),
                            'name' => 'PAYLINE_WEB_CASH_ACTION',
                            'label' => $this->l('Debit mode'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('payment-action'),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'form_group_class' => 'payline-autorization-only' . (Configuration::get('PAYLINE_WEB_CASH_ACTION') != '100' ? ' hidden' : ''),
                            'type' => 'select',
                            'desc' => $this->l('Choose which order status will trigger payment capture'),
                            'name' => 'PAYLINE_WEB_CASH_VALIDATION',
                            'label' => $this->l('Capture payment on'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('order-states'),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'type' => 'select',
                            'desc' => $this->l('Redirect customer to secure payment page or display secure form in the checkout'),
                            'name' => 'PAYLINE_WEB_CASH_UX',
                            'label' => $this->l('User experience'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('user-experience'),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'form_group_class' => 'payline-redirect-only' . (Configuration::get('PAYLINE_WEB_CASH_UX') != 'redirect' ? ' hidden' : ''),
                            'type' => 'text',
                            'desc' => $this->l('Apply customization created through administration center to the payment page'),
                            'name' => 'PAYLINE_WEB_CASH_CUSTOM_CODE',
                            'label' => $this->l('Payment page customization ID'),
                            'placeholder' => '1fd51s2dfs51',
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                    ),
                ),
            );
        } elseif ($tabName == 'contracts') {
            $currentPos = Configuration::get('PAYLINE_POS');
            $contractsList = array();
            $enabledContracts = array();
            
            $enabledFallbackContracts = array();
            $fallbackContractsList = array();
            if (!empty($currentPos)) {
                $enabledContracts = PaylinePaymentGateway::getEnabledContracts();
                $enabledFallbackContracts = PaylinePaymentGateway::getFallbackEnabledContracts();
                $contractsList = PaylinePaymentGateway::getContractsByPosLabel($currentPos, $enabledContracts);
                $fallbackContractsList = PaylinePaymentGateway::getContractsByPosLabel($currentPos, $enabledFallbackContracts);
            }
            return array(
                'form' => array(
                    'legend' => array(
                    'title' => $this->l('Contracts configuration'),
                    'icon' => 'icon-credit-card',
                    ),
                    'input' => array(
                        array(
                            'type' => 'html',
                            'name' => '
                            <h2>' . $this->l('Select and sort the contracts you want to make available to your customers') . '</h2>
                            <p>' . $this->l('You can sort contracts by using drag & drop method') . '</p>',
                            'col' => 12,
                            'label' => '',
                        ),
                        array(
                            'type' => 'contracts',
                            'name' => 'PAYLINE_CONTRACTS',
                            'label' => '',
                            'col' => 12,
                            'contractsList' => $contractsList,
                            'enabledContracts' => $enabledContracts,
                        ),
                        array(
                            'type' => 'html',
                            'name' => '
                            <h2>' . $this->l('Select and sort the alternative contracts you want to make available to your customers') . '</h2>
                            <p>' . $this->l('You can sort contracts by using drag & drop method') . '</p>',
                            'col' => 12,
                            'label' => '',
                        ),
                        array(
                            'type' => 'contracts',
                            'name' => 'PAYLINE_ALT_CONTRACTS',
                            'label' => '',
                            'col' => 12,
                            'contractsList' => $fallbackContractsList,
                            'enabledContracts' => $enabledFallbackContracts,
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                    ),
                ),
            );
        }
    }

    /**
     * Set values for the inputs.
     * @since 2.0.0
     * @param string $tabName
     * @return array
     */
    protected function getConfigFormValues($tabName)
    {
        if ($tabName == 'web-payment') {
            return array(
                'PAYLINE_WEB_CASH_ENABLE' => Configuration::get('PAYLINE_WEB_CASH_ENABLE'),
                'PAYLINE_WEB_CASH_ACTION' => Configuration::get('PAYLINE_WEB_CASH_ACTION'),
                'PAYLINE_WEB_CASH_VALIDATION' => Configuration::get('PAYLINE_WEB_CASH_VALIDATION'),
                'PAYLINE_WEB_CASH_UX' => Configuration::get('PAYLINE_WEB_CASH_UX'),
                'PAYLINE_WEB_CASH_CUSTOM_CODE' => Configuration::get('PAYLINE_WEB_CASH_CUSTOM_CODE'),
            );
        } elseif ($tabName == 'payline') {
            return array(
                'PAYLINE_API_STATUS' => Configuration::get('PAYLINE_API_STATUS'),
                'PAYLINE_LIVE_MODE' => Configuration::get('PAYLINE_LIVE_MODE'),
                'PAYLINE_MERCHANT_ID' => Configuration::get('PAYLINE_MERCHANT_ID'),
                'PAYLINE_ACCESS_KEY' => Configuration::get('PAYLINE_ACCESS_KEY'),
                'PAYLINE_POS' => Configuration::get('PAYLINE_POS'),
                'PAYLINE_PROXY_HOST' => Configuration::get('PAYLINE_PROXY_HOST'),
                'PAYLINE_PROXY_PORT' => Configuration::get('PAYLINE_PROXY_PORT'),
                'PAYLINE_PROXY_LOGIN' => Configuration::get('PAYLINE_PROXY_LOGIN'),
                'PAYLINE_PROXY_PASSWORD' => Configuration::get('PAYLINE_PROXY_PASSWORD'),
            );
        } elseif ($tabName == 'contracts') {
            return array(
                'PAYLINE_CONTRACTS' => Configuration::get('PAYLINE_CONTRACTS'),
                'PAYLINE_ALT_CONTRACTS' => Configuration::get('PAYLINE_ALT_CONTRACTS'),
            );
        }
    }

    /**
     * Save form data.
     * @since 2.0.0
     * @return void
     */
    protected function postProcess()
    {
        $tabName = null;
        if (Tools::isSubmit('submitPaylineweb-payment')) {
            $tabName = 'web-payment';
            // Add confirmation message
            $this->context->controller->confirmations[] = $this->l('Configuration saved.');
        } elseif (Tools::isSubmit('submitPaylinepayline')) {
            $tabName = 'payline';
        } elseif (Tools::isSubmit('submitPaylinecontracts')) {
            $tabName = 'contracts';
        }
        if (!empty($tabName)) {
            $form_values = $this->getConfigFormValues($tabName);

            // Prevent rounding spaces into some values
            $keysToTrim = array(
                'PAYLINE_MERCHANT_ID',
                'PAYLINE_ACCESS_KEY',
                'PAYLINE_POS',
                'PAYLINE_PROXY_HOST',
                'PAYLINE_PROXY_PORT',
                'PAYLINE_PROXY_LOGIN',
                'PAYLINE_PROXY_PASSWORD',
                'PAYLINE_WEB_CASH_CUSTOM_CODE',
                'PAYLINE_CONTRACTS',
                'PAYLINE_ALT_CONTRACTS',
            );
            foreach (array_keys($form_values) as $key) {
                if ($key == 'PAYLINE_CONTRACTS' || $key == 'PAYLINE_ALT_CONTRACTS') {
                    $jsonData = Tools::getValue($key);
                    $jsonData = Tools::jsonDecode($jsonData);
                    $contractsList = array();
                    foreach ($jsonData as $val) {
                        if ($val != '') {
                            $contractsList[] = $val;
                        }
                    }
                    $newValue = Tools::jsonEncode($contractsList);
                } elseif (in_array($key, $keysToTrim)) {
                    $newValue = trim(Tools::getValue($key));
                } else {
                    $newValue = Tools::getValue($key);
                }

                // Configuration key is dirty ?
                $dirtyValue = (Configuration::get($key) != $newValue);

                if ($dirtyValue) {
                    // Value has changed, we might need to reset some others fields depending on what has been updated
                    if (in_array($key, array('PAYLINE_MERCHANT_ID', 'PAYLINE_ACCESS_KEY', 'PAYLINE_POS'))) {
                        // Reset contract list on credentials update
                        Configuration::updateValue('PAYLINE_CONTRACTS', '[]');
                        Configuration::updateValue('PAYLINE_ALT_CONTRACTS', '[]');
                    }
                }
                Configuration::updateValue($key, $newValue);
            }
        }
    }

    /**
     * Get readable human code from error code
     * @param int $errorCode
     * @return string
     */
    public function getHumanErrorCode($errorCode)
    {
        switch ($errorCode) {
            case payline::INVALID_AMOUNT:
                return $this->l('Order can\'t be created because paid amount is different than total cart amount.');
            case payline::INVALID_CART_ID:
                return $this->l('Order can\'t be created because related cart does not exists.');
            default:
                return null;
        }
    }

    /**
     * Process payment validation (customer shop return)
     * @todo
     * @param Cart $cart
     * @param array $paymentInfos
     * @since 2.0.0
     * @return array
     */
    protected function createOrder(Cart $cart, $paymentInfos)
    {
        $amountPaid = ($paymentInfos['payment']['amount'] / 100);
        // Set right order state depending on defined payment action
        if ($paymentInfos['payment']['action'] == 100) {
            $idOrderState = (int)Configuration::get('PAYLINE_ID_STATE_AUTOR');
        } else {
            if ($paymentInfos['result']['code'] == '00000') {
                // Transaction accepted
                $idOrderState = (int)Configuration::get('PS_OS_PAYMENT');
            } else {
                // Transaction is pending
                $idOrderState = (int)Configuration::get('PAYLINE_ID_STATE_PENDING');
            }
        }
        $paymentMethod = 'Payline';
        $orderMessage = 'Transaction #' . $paymentInfos['transaction']['id'];
        $extraVars = array(
            'transaction_id' => $paymentInfos['transaction']['id'],
        );
        $idCurrency = (int)$cart->id_currency;
        $secureKey = $paymentInfos['formatedPrivateDataList']['secure_key'];

        // Always clean Cart::orderExists cache before trying to create the order
        if (class_exists('Cache', false) && method_exists('Cache', 'clean')) {
            Cache::clean('Cart::orderExists_' . $cart->id);
        }

        $validateOrderResult = false;
        $order = null;
        $errorMessage = null;
        $errorCode = null;
        $orderExists = $cart->OrderExists();

        $totalCartAmount = (float)Tools::ps_round((float)$cart->getOrderTotal(true, Cart::BOTH), 2);
        $totalAmountPaid = Tools::ps_round((float)$amountPaid, 2);
        if (number_format($totalCartAmount, _PS_PRICE_COMPUTE_PRECISION_) != number_format($totalAmountPaid, _PS_PRICE_COMPUTE_PRECISION_)) {
            // Wrong amount paid, do not create order

            // We try to refund/cancel the current transaction
            // If refund can't be done, we continue the classic process. Order will be marked as invalid
            if ($paymentInfos['payment']['action'] == 100) {
                // Cancel author
                $cancel = PaylinePaymentGateway::resetTransaction($paymentInfos['transaction']['id'], null, $this->l('Error: automatic reset (cart total != amount paid)'));
                $validResponse = PaylinePaymentGateway::isValidResponse($cancel, array('02601', '02602'));
            } else {
                // Refund
                $cancel = PaylinePaymentGateway::refundTransaction($paymentInfos['transaction']['id'], null, $this->l('Error: automatic refund (cart total != amount paid)'));
                $validResponse = PaylinePaymentGateway::isValidResponse($cancel);
            }
            if ($validResponse) {
                $errorCode = payline::INVALID_AMOUNT;

                // Set a cookie value that expose how many try users has made with invalid amount
                if (!isset($this->context->cookie->pl_try)) {
                    $this->context->cookie->pl_try = 2;
                } else {
                    $this->context->cookie->pl_try += 1;
                }

                return array($order, $validateOrderResult, $errorMessage, $errorCode);
            }
        }

        // Unset pl_try cookie value
        if (isset($this->context->cookie->pl_try)) {
            unset($this->context->cookie->pl_try);
        }

        if (!$orderExists) {
            // Validate the order
            try {
                $validateOrderResult = $this->validateOrder(
                    $cart->id,
                    $idOrderState,
                    $amountPaid,
                    $paymentMethod,
                    $orderMessage,
                    $extraVars,
                    $idCurrency,
                    false,
                    $secureKey
                );
                if ($validateOrderResult) {
                    $order = new Order($this->currentOrder);
                }
            } catch (Exception $e) {
                $validateOrderResult = false;
                $errorMessage = $e->getMessage();
            }
        } elseif ($cart->secure_key == $paymentInfos['formatedPrivateDataList']['secure_key']) {
            // Secure key is OK
            $idOrder = Order::getOrderByCartId($cart->id);
            $order = new Order($idOrder);
            // Retrieve order
            if (Validate::isLoadedObject($order)) {
                // Check if transaction ID is the same
                $orderPayments = OrderPayment::getByOrderId($order->id);
                $sameTransactionID = false;
                foreach ($orderPayments as $orderPayment) {
                    if ($orderPayment->transaction_id == $paymentInfos['transaction']['id']) {
                        $sameTransactionID = true;
                    }
                }
                if (!$sameTransactionID) {
                    // Order already exists, but it looks to be a new transaction
                    // What should we do ?
                    $order = null;
                }
            } else {
                // Unable to retrieve order ?
                // TODO
            }
            $validateOrderResult = true;
        } elseif ($cart->secure_key != $paymentInfos['formatedPrivateDataList']['secure_key']) {
            // Order already exists for this cart and secure key is different
            // Secure key is NOK
            // TODO
            $validateOrderResult = false;
        }

        if (!$validateOrderResult) {
            // TODO redirection vers controleur + erreur
            // Unable to create the order
        }

        return array($order, $validateOrderResult, $errorMessage, $errorCode);
    }

    /**
     * Process payment validation (customer shop return)
     * @param string $token
     * @since 2.0.0
     * @return void
     */
    public function processCustomerPaymentReturn($token)
    {
        $paymentInfos = PaylinePaymentGateway::getPaymentInformations($token);
        $errorCode = null;

        // Check if id_cart and secure_key are the same
        if (isset($paymentInfos['formatedPrivateDataList']) && is_array($paymentInfos['formatedPrivateDataList'])
            && isset($paymentInfos['formatedPrivateDataList']['id_cart'])
            && isset($paymentInfos['formatedPrivateDataList']['secure_key'])
        ) {
            if (PaylinePaymentGateway::isValidResponse($paymentInfos, $this->approvedResponseCode) || PaylinePaymentGateway::isValidResponse($paymentInfos, $this->pendingResponseCode)) {
                // Transaction approved or pending

                // OK we can process the order via customer return
                $idCart = (int)$paymentInfos['formatedPrivateDataList']['id_cart'];
                // Check if cart exists
                $cart = new Cart($idCart);
                if (Validate::isLoadedObject($cart)) {
                    // Create the order
                    list($order, $validateOrderResult, $errorMessage, $errorCode) = $this->createOrder($cart, $paymentInfos);

                    if ($order instanceof Order && Validate::isLoadedObject($order)) {
                        Tools::redirect('index.php?controller=order-confirmation&id_cart='.$idCart.'&id_module='.$this->id.'&id_order='.$this->currentOrder.'&key='.$this->context->customer->secure_key);
                    }
                } else {
                    // Invalid Cart ID
                    $errorCode = payline::INVALID_CART_ID;
                }
            }
        }
        $urlParams = array(
            'paylineError' => 1,
            'paylinetoken' => $token,
        );
        if (isset($errorCode)) {
            $urlParams['paylineErrorCode'] = $errorCode;
        }
        // Refused payment, or any other error case (customer case)
        if (Configuration::get('PAYLINE_WEB_CASH_UX') == 'lightbox' || Configuration::get('PAYLINE_WEB_CASH_UX') == 'redirect') {
            Tools::redirect($this->context->link->getPageLink('order', true, $this->context->language->id, $urlParams));
        } else {
            Tools::redirect($this->context->link->getModuleLink($this->name, 'payment', $urlParams, true));
        }
    }

    /**
     * Process payment from notification
     * @param string $token
     * @since 2.0.0
     * @return void
     */
    public function processNotification($token)
    {
        $validateOrderResult = false;
        $paymentInfos = PaylinePaymentGateway::getPaymentInformations($token);
        // Check if id_cart and secure_key are the same
        if (isset($paymentInfos['formatedPrivateDataList']) && is_array($paymentInfos['formatedPrivateDataList'])
            && isset($paymentInfos['formatedPrivateDataList']['id_cart'])
        ) {
            if (PaylinePaymentGateway::isValidResponse($paymentInfos, $this->approvedResponseCode) || PaylinePaymentGateway::isValidResponse($paymentInfos, $this->pendingResponseCode)) {
                // Transaction approved or pending
                // OK we can process the order via customer return
                $idCart = (int)$paymentInfos['formatedPrivateDataList']['id_cart'];
                // Check if cart exists
                $cart = new Cart($idCart);
                if (!Validate::isLoadedObject($cart)) {
                    die(Tools::jsonEncode(array(
                        'result' => $validateOrderResult,
                        'error' => 'Invalid Cart ID #'.$idCart.' - Cart does not exists',
                    )));
                }

                // Create the order
                list($order, $validateOrderResult, $errorMessage) = $this->createOrder($cart, $paymentInfos);
            } else {
                // Refused payment, or any other error case (customer case)
                die(Tools::jsonEncode(array(
                    'result' => $validateOrderResult,
                    'error' => 'Transaction was not approved, or any other error case (customer case)',
                    'errorCode' => $paymentInfos['result']['code'],
                )));
            }
            if (ob_get_length() > 0) {
                ob_clean();
            }
            die(Tools::jsonEncode(array('result' => $validateOrderResult)));
        }
    }

    /**
     * Process order update from transaction notification
     * @param string $idTransaction
     * @since 2.0.0
     * @return void
     */
    public function processTransactionNotification($idTransaction)
    {
        $validateOrderResult = false;
        $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
        // Wait for a transaction into pending state
        if (PaylinePaymentGateway::isValidResponse($transaction, $this->pendingResponseCode)) {
            if (isset($transaction['formatedPrivateDataList']) && is_array($transaction['formatedPrivateDataList'])
                && isset($transaction['formatedPrivateDataList']['id_cart'])
                && isset($transaction['formatedPrivateDataList']['id_customer'])
                && isset($transaction['formatedPrivateDataList']['secure_key'])
            ) {
                // OK we can process the order via customer return
                $idCart = (int)$transaction['formatedPrivateDataList']['id_cart'];
                // Check if cart exists
                $cart = new Cart($idCart);
                if (!Validate::isLoadedObject($cart)) {
                    die(Tools::jsonEncode(array(
                        'result' => $validateOrderResult,
                        'error' => 'Invalid Cart ID #'.$idCart.' - Cart does not exists',
                    )));
                }
                // Check secure_key and id_customer on the cart, compare it to the transaction
                if ($cart->secure_key != $transaction['formatedPrivateDataList']['secure_key'] || $cart->id_customer != $transaction['formatedPrivateDataList']['id_customer']) {
                    die(Tools::jsonEncode(array(
                        'result' => $validateOrderResult,
                        'error' => 'Transaction is not linked to the right Customer for Cart ID #'.$idCart,
                    )));
                }
                // Check that the transaction have at least one statusHistoryList items
                if (!isset($transaction['statusHistoryList']) || !is_array($transaction['statusHistoryList']) || !sizeof($transaction['statusHistoryList'])) {
                    die(Tools::jsonEncode(array(
                        'result' => $validateOrderResult,
                        'error' => 'Transaction does not contains any statusHistoryList item',
                    )));
                }
                // Check that the transaction have at least one statusHistory items
                if (!isset($transaction['statusHistoryList']['statusHistory']) || !is_array($transaction['statusHistoryList']['statusHistory']) || !sizeof($transaction['statusHistoryList']['statusHistory'])) {
                    die(Tools::jsonEncode(array(
                        'result' => $validateOrderResult,
                        'error' => 'Transaction does not contains any statusHistory item',
                    )));
                }

                // Always clean Cart::orderExists cache before trying to create the order
                if (class_exists('Cache', false) && method_exists('Cache', 'clean')) {
                    Cache::clean('Cart::orderExists_' . $cart->id);
                }
                $orderExists = $cart->OrderExists();
                if (!$orderExists) {
                    // There is no order for this cart
                    die(Tools::jsonEncode(array(
                        'result' => $validateOrderResult,
                        'error' => 'Invalid Cart ID #'.$idCart.' - Order does not exists',
                    )));
                }

                // Retrieve order
                $idOrder = Order::getOrderByCartId($cart->id);
                $order = new Order($idOrder);
                if (Validate::isLoadedObject($order)) {
                    $statusHistoryList = $transaction['statusHistoryList']['statusHistory'];

                    // Retrieve the latest status (already sorted by date into PaylinePaymentGateway)
                    $statusHistory = current($statusHistoryList);
                    if ($statusHistory['status'] == 'ACCEPTED') {
                        // Transaction accepted
                        if (!$order->hasBeenPaid()) {
                            // Change order state if order has not already been paid
                            $validateOrderResult = true;

                            $history = new OrderHistory();
                            $history->id_order = (int)$order->id;
                            $history->changeIdOrderState(_PS_OS_PAYMENT_, (int)$order->id);
                            $history->addWithemail();
                        }
                    } elseif ($statusHistory['status'] == 'ON_HOLD_PARTNER') {
                        // We are still waiting for the transaction validation, nothing to do here
                    } else {
                        // Transaction refused
                        if ($order->getCurrentState() != _PS_OS_CANCELED_) {
                            // Change order state if order has not already been canceled
                            $validateOrderResult = true;

                            // Change order state
                            $history = new OrderHistory();
                            $history->id_order = (int)$order->id;
                            $history->changeIdOrderState(_PS_OS_CANCELED_, (int)$order->id);
                            $history->addWithemail();
                        }
                    }
                }
            }
        }
        if (ob_get_length() > 0) {
            ob_clean();
        }
        die(Tools::jsonEncode(array('result' => $validateOrderResult)));
    }

    /**
     * Clone of Order::addOrderPayment() - We force total_paid_real to be = 0 instead of a negative value, so we can update without warning
     * @since 2.0.0
     * @return bool
     */
    public function addOrderPayment($order, $amount_paid, $payment_method = null, $payment_transaction_id = null, $currency = null, $date = null, $order_invoice = null)
    {
        $order_payment = new OrderPayment();
        $order_payment->order_reference = $order->reference;
        $order_payment->id_currency = ($currency ? $currency->id : $order->id_currency);
        // we kept the currency rate for historization reasons
        $order_payment->conversion_rate = ($currency ? $currency->conversion_rate : 1);
        // if payment_method is define, we used this
        $order_payment->payment_method = ($payment_method ? $payment_method : $order->payment);
        $order_payment->transaction_id = $payment_transaction_id;
        $order_payment->amount = $amount_paid;
        $order_payment->date_add = ($date ? $date : null);

        // Force total_paid_real to 0
        $order->total_paid_real = 0;

        // We put autodate parameter of add method to true if date_add field is null
        $res = $order_payment->add(is_null($order_payment->date_add)) && $order->update();

        if (!$res) {
            return false;
        }

        if (!is_null($order_invoice)) {
            $res = Db::getInstance()->execute('
            INSERT INTO `'._DB_PREFIX_.'order_invoice_payment` (`id_order_invoice`, `id_order_payment`, `id_order`)
            VALUES('.(int)$order_invoice->id.', '.(int)$order_payment->id.', '.(int)$order->id.')');

            // Clear cache
            Cache::clean('order_invoice_paid_*');
        }

        return $res;
    }
}
