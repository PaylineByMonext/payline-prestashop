<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 * @version   2.1.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payline' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'autoload.php');
require_once(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payline' . DIRECTORY_SEPARATOR . 'class' . DIRECTORY_SEPARATOR . 'PaylineToken.php');
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
        'PAYLINE_ID_ORDER_STATE_NX' => array(
            'name' => array(
                'en' => 'Partially paid with Payline',
                'fr' => 'Payé partiellement via Payline',
            ),
            'send_email' => false,
            'color' => '#bbddee',
            'hidden' => false,
            'delivery' => false,
            'logable' => true,
            'invoice' => true,
            'template' => 'payment',
        ),
        'PAYLINE_ID_STATE_ALERT_SCHEDULE' => array(
            'name' => array(
                'en' => 'Alert scheduler',
                'fr' => 'Alerte échéancier',
            ),
            'send_email' => false,
            'color' => '#ffcdcf',
            'hidden' => false,
            'delivery' => false,
            'logable' => true,
            'invoice' => true,
        ),
    );

    // Errors constants
    const INVALID_AMOUNT = 1;
    const INVALID_CART_ID = 2;
    const SUBSCRIPTION_ERROR = 3;

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
        $this->version = '2.2.0';
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
     * Create Payline-related tables
     * @since 2.1.0
     * @return bool
     */
    protected function createTables()
    {
        $res = Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'payline_token` (
            `id_order` int(10) UNSIGNED NOT NULL,
            `id_cart` int(10) UNSIGNED NOT NULL,
            `token` varchar(255) NULL,
            `payment_record_id` varchar(12),
            `transaction_id` varchar(50),
            UNIQUE `id_order` (`id_order`),
            UNIQUE `id_cart` (`id_cart`)
        ) ENGINE='._MYSQL_ENGINE_.' CHARSET=utf8');

        return $res;
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
            if (!empty($idOrderState)) {
                // Check if order state needs update...
                $orderState = new OrderState($idOrderState);
                if (!Validate::isLoadedObject($orderState)) {
                    $idOrderState = false;
                }
            }
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
            } else {
                // Update order state if needed
                $orderState = new OrderState($idOrderState);
                if (Validate::isLoadedObject($orderState)) {
                    $dirty = false;
                    foreach ($customOrderState as $k => $v) {
                        if ($k != 'name' && $k != 'color') {
                            if ($orderState->{$k} != $v) {
                                $dirty = true;
                                $orderState->{$k} = $v;
                            }
                        }
                    }
                    if ($dirty) {
                        try {
                            $orderState->save();
                        } catch (Exception $e) {
                            PrestaShopLogger::addLog('payline::createCustomOrderState - Cannot save Order State', 3, null, 'OrderState', $idOrderState);
                        }
                    }
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
            || !$this->registerHook('displayCustomerAccount')
            || !$this->registerHook('actionAdminOrdersListingResultsModifier')
            || !$this->registerHook('actionObjectOrderSlipAddAfter')
            || !$this->registerHook('actionOrderStatusUpdate')
            || (version_compare(_PS_VERSION_, '1.7.0.0', '<') && !$this->registerHook('displayPayment'))
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('paymentOptions')
            // Install custom order state
            || !$this->createCustomOrderState()
            // Install tables
            || !$this->createTables()
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
        // Add front.css on OPC
        if ($this->isPaymentAvailable() && version_compare(_PS_VERSION_, '1.7.0.0', '<') && $this->context->controller instanceof OrderOpcController) {
            $this->context->controller->addCSS($this->_path.'views/css/front.css');
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
        $orderPayments = OrderPayment::getByOrderReference($order->reference);
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

                    $this->addOrderPaymentAfterRefund($order, $amountToRefund * -1, null, $refund['transaction']['id'], null, null, $orderInvoice);

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
     * @since 2.1.0
     * @param array $params
     * @return void
     */
    public function hookActionAdminOrdersListingResultsModifier($params)
    {
        $idOrderStateNx = (int)Configuration::get('PAYLINE_ID_ORDER_STATE_NX');
        if (!empty($idOrderStateNx) && !empty($params['list']) && is_array($params['list'])) {
            foreach ($params['list'] as $orderListRow) {
                $idOrderList[] = (int)$orderListRow['id_order'];
            }
            // Get id_order list with the right order state
            $idOrderWaitingList = array();
            $idOrderWaitingListResult = Db::getInstance()->executeS('SELECT `id_order` FROM `'._DB_PREFIX_.'orders` WHERE `current_state`=' . (int)$idOrderStateNx . ' AND `id_shop` IN ('.implode(', ', Shop::getContextListShopID()).')');
            if (is_array($idOrderWaitingListResult)) {
                foreach ($idOrderWaitingListResult as $row) {
                    $idOrderWaitingList[] = (int)$row['id_order'];
                }
            }

            foreach ($params['list'] as &$orderListRow) {
                if (in_array((int)$orderListRow['id_order'], $idOrderWaitingList)) {
                    // Retrieve info from Payline
                    $order = new Order((int)$orderListRow['id_order']);
                    if (Validate::isLoadedObject($order) && $order->module == 'payline') {
                        // Retrieve original transaction via token
                        $token = PaylineToken::getTokenByIdOrder($order->id);
                        if (!empty($token)) {
                            $originalTransaction = PaylinePaymentGateway::getPaymentInformations($token);
                            if (!empty($originalTransaction['paymentRecordId']) && !empty($originalTransaction['payment']['mode']) &&
                                $originalTransaction['payment']['mode'] == 'NX' &&
                                isset($originalTransaction['billingRecordList']) && is_array($originalTransaction['billingRecordList']) &&
                                isset($originalTransaction['billingRecordList']['billingRecord']) && is_array($originalTransaction['billingRecordList']['billingRecord'])
                            ) {
                                $paymentRecord = PaylinePaymentGateway::getPaymentRecord($originalTransaction['payment']['contractNumber'], $originalTransaction['paymentRecordId']);
                                if (!empty($paymentRecord['recurring'])) {
                                    // Retrieve validated payment count
                                    $validTransactionCount = PaylinePaymentGateway::getValidatedRecurringPayment($paymentRecord);
                                    // Change order state name
                                    $orderListRow['osname'] = sprintf($this->l('Scheduler %s/%s paid'), (int)$validTransactionCount, (int)$paymentRecord['recurring']['billingLeft']);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Display subscribe information into customer account
     * @since 2.2.0
     * @param array $params
     * @return string
     */
    public function hookDisplayCustomerAccount($params)
    {
        $output = '';

        $this->context->smarty->assign(array(
            'subscriptionControllerLink' => $this->context->link->getModuleLink('payline', 'subscriptions', array(), true),
        ));
        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $output .= $this->context->smarty->fetch($this->local_path.'views/templates/hook/1.7/customer_account.tpl');
        } else {
            $output .= $this->display(__FILE__, 'customer_account.tpl');
        }

        return $output;
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

        $output = '';
        if (!empty($params['id_order'])) {
            $order = new Order($params['id_order']);
            if (Validate::isLoadedObject($order) && $order->module == 'payline') {
                // Retrieve original transaction via token
                $token = PaylineToken::getTokenByIdOrder($order->id);
                if (!empty($token)) {
                    $originalTransaction = PaylinePaymentGateway::getPaymentInformations($token);
                } else {
                    $idTransaction = PaylineToken::getIdTransactionByIdOrder($order->id);
                    if (!empty($idTransaction)) {
                        $originalTransaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
                    }
                }
                if (!empty($originalTransaction['formatedPrivateDataList']['payment_method']) && $originalTransaction['formatedPrivateDataList']['payment_method'] == PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD) {
                    // Subscription, get payment recordId
                    $paymentRecordId = PaylineToken::getPaymentRecordIdByIdOrder($order->id);
                    if (!empty($paymentRecordId)) {
                        // Get payment record
                        $paymentRecord = PaylinePaymentGateway::getPaymentRecord($originalTransaction['payment']['contractNumber'], $paymentRecordId);
                        if (PaylinePaymentGateway::isValidResponse($paymentRecord, array('02500'))) {
                            // Add Order ID to each rows
                            foreach ($paymentRecord['billingRecordList']['billingRecord'] as &$billingRecord) {
                                if (isset($billingRecord['transaction']['id'])) {
                                    $linkedIdOrder = PaylineToken::getIdOrderByIdTransaction($billingRecord['transaction']['id']);
                                    if (!empty($linkedIdOrder)) {
                                        $billingRecord['pl_linkedIdOrder'] = $linkedIdOrder;
                                        $billingRecord['pl_linkToOrder'] = $this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $linkedIdOrder . '&vieworder';
                                    }
                                }
                            }

                            // Display all records
                            $this->context->smarty->assign(array(
                                'id_order' => (int)$params['id_order'],
                                'billingListRec' => $paymentRecord['billingRecordList']['billingRecord'],
                                'paymentRecordId' => $paymentRecordId,
                            ));
                        }
                    }
                } elseif (!empty($originalTransaction['payment']['mode']) && $originalTransaction['payment']['mode'] == 'NX') {
                    if (isset($originalTransaction['billingRecordList']) && is_array($originalTransaction['billingRecordList']) && isset($originalTransaction['billingRecordList']['billingRecord']) && is_array($originalTransaction['billingRecordList']['billingRecord'])) {
                        // Display all records
                        $this->context->smarty->assign(array(
                            'id_order' => (int)$params['id_order'],
                            'billingList' => $originalTransaction['billingRecordList']['billingRecord'],
                            'paymentRecordId' => $originalTransaction['paymentRecordId'],
                        ));
                    }
                }

                // Retrieve order payments
                $orderPayments = OrderPayment::getByOrderReference($order->reference);
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
                    $output .= $this->context->smarty->fetch($this->local_path.'views/templates/hook/admin_order.tpl');
                } else {
                    $output .= $this->display(__FILE__, 'admin_order.tpl');
                }
            }
        }

        return $output;
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
                $orderPayments = OrderPayment::getByOrderReference($order->reference);
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
            $orderPayments = OrderPayment::getByOrderReference($order->reference);
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

                        $this->addOrderPaymentAfterRefund($order, $amountToRefund * -1, null, $refund['transaction']['id'], null, null, $orderInvoice);

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
        $orderPayments = OrderPayment::getByOrderReference($order->reference);
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
        // Assign to template enabled cards/contracts
        $currentPos = Configuration::get('PAYLINE_POS');
        $enabledContracts = PaylinePaymentGateway::getEnabledContracts();
        $contractsList = PaylinePaymentGateway::getContractsByPosLabel($currentPos, $enabledContracts);
        $this->smarty->assign(array(
            'payline_contracts' => $contractsList,
        ));

        // Web payment
        if (Configuration::get('PAYLINE_WEB_CASH_ENABLE')) {
            $uxMode = Configuration::get('PAYLINE_WEB_CASH_UX');

            $webCash = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $webCashTitle = Configuration::get('PAYLINE_WEB_CASH_TITLE', $this->context->language->id);
            $webCashSubTitle = Configuration::get('PAYLINE_WEB_CASH_SUBTITLE', $this->context->language->id);
            if (!strlen($webCashTitle)) {
                $webCashTitle = $this->l('Simple payment');
            }
            $webCash->setModuleName($this->name)->setCallToActionText($webCashTitle);
            // Add additionnal information text
            $this->smarty->assign(array(
                'payline_title' => $webCashTitle,
                'payline_subtitle' => $webCashSubTitle,
            ));
            $webCash->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/payment_additional_information.tpl'));

            if ($uxMode == 'lightbox') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::WEB_PAYMENT_METHOD);
                if (!empty($paymentRequest['token'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_ux_mode' => Configuration::get('PAYLINE_WEB_CASH_UX'),
                        'payline_assets' => PaylinePaymentGateway::getAssetsToRegister(),
                        'payline_title' => $webCashTitle,
                        'payline_subtitle' => $webCashSubTitle,
                    ));
                    $webCash->setAction('javascript:Payline.Api.init()');
                    $webCash->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/lightbox.tpl'));
                } else {
                    $webCash = null;
                }
            } elseif ($uxMode == 'redirect') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::WEB_PAYMENT_METHOD);
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

        // Recurring payment
        if (Configuration::get('PAYLINE_RECURRING_ENABLE') && (!Configuration::get('PAYLINE_RECURRING_TRIGGER') || ($this->context->cart->getOrderTotal() > Configuration::get('PAYLINE_RECURRING_TRIGGER')))) {
            $uxMode = Configuration::get('PAYLINE_RECURRING_UX');

            $recurringPayment = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $recurringTitle = Configuration::get('PAYLINE_RECURRING_TITLE', $this->context->language->id);
            $recurringSubTitle = Configuration::get('PAYLINE_RECURRING_SUBTITLE', $this->context->language->id);
            if (!strlen($recurringTitle)) {
                $recurringTitle = $this->l('Nx payment');
            }
            $recurringPayment->setModuleName($this->name)->setCallToActionText($recurringTitle);
            // Add additionnal information text
            $this->smarty->assign(array(
                'payline_title' => $recurringTitle,
                'payline_subtitle' => $recurringSubTitle,
            ));
            $recurringPayment->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/payment_additional_information.tpl'));

            if ($uxMode == 'lightbox') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::RECURRING_PAYMENT_METHOD);
                if (!empty($paymentRequest['token'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_ux_mode' => Configuration::get('PAYLINE_RECURRING_UX'),
                        'payline_assets' => PaylinePaymentGateway::getAssetsToRegister(),
                        'payline_title' => $recurringTitle,
                        'payline_subtitle' => $recurringSubTitle,
                    ));
                    $recurringPayment->setAction('javascript:Payline.Api.init()');
                    $recurringPayment->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/lightbox.tpl'));
                } else {
                    $recurringPayment = null;
                }
            } elseif ($uxMode == 'redirect') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::RECURRING_PAYMENT_METHOD);
                if (!empty($paymentRequest['redirectURL'])) {
                    $recurringPayment->setAction($paymentRequest['redirectURL']);
                } else {
                    $recurringPayment = null;
                }
            } else {
                $recurringPayment->setAction($this->context->link->getModuleLink($this->name, 'payment_nx', array(), true));
            }

            if ($recurringPayment !== null) {
                $paymentMethodList[] = $recurringPayment;
            }
        }

        // Subscribe payment
        if (Configuration::get('PAYLINE_SUBSCRIBE_ENABLE')) {
            $subscribePayment = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $subscribeTitle = Configuration::get('PAYLINE_SUBSCRIBE_TITLE', $this->context->language->id);
            $subscribeSubTitle = Configuration::get('PAYLINE_SUBSCRIBE_SUBTITLE', $this->context->language->id);
            if (!strlen($subscribeTitle)) {
                $subscribeTitle = $this->l('Recurring payment');
            }
            $subscribePayment->setModuleName($this->name)->setCallToActionText($subscribeTitle);
            // Add additionnal information text
            $this->smarty->assign(array(
                'payline_title' => $subscribeTitle,
                'payline_subtitle' => $subscribeSubTitle,
            ));
            $subscribePayment->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/payment_additional_information.tpl'));

            list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD);
            if (!empty($paymentRequest['redirectURL'])) {
                $subscribePayment->setAction($paymentRequest['redirectURL']);
            } else {
                $subscribePayment = null;
            }

            if ($subscribePayment !== null) {
                // Retrieve exclusive product list
                $exclusiveProductList = $this->getSubscribeProductList();

                if (!Configuration::get('PAYLINE_SUBSCRIBE_EXCLUSIVE')) {
                    // Non-exclusive method, check if products in cart are correct and eligible
                    if (is_array($exclusiveProductList) && sizeof($exclusiveProductList)) {
                        $cartProductList = $this->context->cart->getProducts();
                        if (is_array($cartProductList)) {
                            foreach ($cartProductList as $cartProduct) {
                                // We have to disable this method, no product are eligible
                                if (!in_array($cartProduct['id_product'], $exclusiveProductList)) {
                                    $subscribePayment = null;
                                    break;
                                }
                            }
                        }
                    }

                    if ($subscribePayment !== null) {
                        $paymentMethodList[] = $subscribePayment;
                    }
                } else {
                    // Exclusive method, check if products in cart are correct
                    $cartProductList = $this->context->cart->getProducts();
                    $cartIntegrity = false;
                    $cartFullIntegrity = true;
                    $breakingIntegrityList = array();
                    // We have at least, one product OK
                    if (is_array($cartProductList)) {
                        foreach ($cartProductList as $cartProduct) {
                            if (in_array($cartProduct['id_product'], $exclusiveProductList)) {
                                $cartIntegrity = true;
                            } else {
                                $cartFullIntegrity = false;
                                $breakingIntegrityList[] = $cartProduct['id_product'];
                            }
                        }
                    }

                    if (!$cartIntegrity) {
                        // We have to disable this method, no product are eligible
                        $subscribePayment = null;
                    } elseif (!$cartFullIntegrity) {
                        // We have to disable payment via Payline, wrong cart content
                        $breakingProductList = array();
                        foreach ($breakingIntegrityList as $idProduct) {
                            $product = new Product($idProduct, false, $this->context->cookie->id_lang);
                            $breakingProductList[] = $product->name;
                        }

                        $this->smarty->assign(array(
                            'paylineBreakingProductList' => $breakingProductList
                        ));

                        $subscribePayment->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/payment_sub_error.tpl'));
                        if ($subscribePayment !== null) {
                            $paymentMethodList[] = $subscribePayment;
                        }
                    } elseif ($cartIntegrity && $cartFullIntegrity) {
                        // We have to hide any other methods...
                        $paymentMethodList = array($subscribePayment);
                    }
                }
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

        // Assign to template enabled cards/contracts
        $currentPos = Configuration::get('PAYLINE_POS');
        $enabledContracts = PaylinePaymentGateway::getEnabledContracts();
        $contractsList = PaylinePaymentGateway::getContractsByPosLabel($currentPos, $enabledContracts);
        $this->smarty->assign(array(
            'payline_contracts' => $contractsList,
        ));

        $this->context->controller->addCSS($this->_path.'views/css/front.css');

        $paymentReturn = '';

        if (Configuration::get('PAYLINE_WEB_CASH_ENABLE')) {
            $uxMode = Configuration::get('PAYLINE_WEB_CASH_UX');
            $webCashTitle = Configuration::get('PAYLINE_WEB_CASH_TITLE', $this->context->language->id);
            if (!strlen($webCashTitle)) {
                $webCashTitle = $this->l('Simple payment');
            }
            $webCashSubTitle = Configuration::get('PAYLINE_WEB_CASH_SUBTITLE', $this->context->language->id);

            $this->smarty->assign(array(
                'payline_ux_mode' => Configuration::get('PAYLINE_WEB_CASH_UX'),
                'payline_title' => $webCashTitle,
                'payline_subtitle' => $webCashSubTitle,
            ));

            if ($uxMode == 'lightbox') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::WEB_PAYMENT_METHOD);
                if (!empty($paymentRequest['token'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_assets' => PaylinePaymentGateway::getAssetsToRegister(),
                        'payline_href' => 'javascript:Payline.Api.init()',
                    ));

                    $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment.tpl');
                }
            } elseif ($uxMode == 'redirect') {
                list($paymentRequest, $paymentRequestParams)= PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::WEB_PAYMENT_METHOD);
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

        // Recurring payment
        if (Configuration::get('PAYLINE_RECURRING_ENABLE') && (!Configuration::get('PAYLINE_RECURRING_TRIGGER') || ($this->context->cart->getOrderTotal() > Configuration::get('PAYLINE_RECURRING_TRIGGER')))) {
            $uxMode = Configuration::get('PAYLINE_RECURRING_UX');
            $recurringTitle = Configuration::get('PAYLINE_RECURRING_TITLE', $this->context->language->id);
            if (!strlen($recurringTitle)) {
                $recurringTitle = $this->l('Nx payment');
            }
            $recurringSubTitle = Configuration::get('PAYLINE_RECURRING_SUBTITLE', $this->context->language->id);

            $this->smarty->assign(array(
                'payline_ux_mode' => $uxMode,
                'payline_title' => $recurringTitle,
                'payline_subtitle' => $recurringSubTitle,
            ));

            if ($uxMode == 'lightbox') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::RECURRING_PAYMENT_METHOD);
                if (!empty($paymentRequest['token'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_assets' => PaylinePaymentGateway::getAssetsToRegister(),
                    ));
                    $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment_nx.tpl');
                }
            } elseif ($uxMode == 'redirect') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::RECURRING_PAYMENT_METHOD);
                if (!empty($paymentRequest['redirectURL'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_href' => $paymentRequest['redirectURL'],
                    ));

                    $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment_nx.tpl');
                }
            } else {
                $this->smarty->assign(array(
                    'payline_href' => $this->context->link->getModuleLink($this->name, 'payment_nx', array(), true),
                ));

                $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment_nx.tpl');
            }
        }

        // Subscribe payment
        if (Configuration::get('PAYLINE_SUBSCRIBE_ENABLE')) {
            $subscribeTitle = Configuration::get('PAYLINE_SUBSCRIBE_TITLE', $this->context->language->id);
            if (!strlen($subscribeTitle)) {
                $subscribeTitle = $this->l('Recurring payment');
            }
            $subscribeSubTitle = Configuration::get('PAYLINE_SUBSCRIBE_SUBTITLE', $this->context->language->id);

            $this->smarty->assign(array(
                'payline_title' => $subscribeTitle,
                'payline_subtitle' => $subscribeSubTitle,
            ));

            list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD);
            if (!empty($paymentRequest['redirectURL'])) {
                $this->smarty->assign(array(
                    'payline_token' => $paymentRequest['token'],
                    'payline_href' => $paymentRequest['redirectURL'],
                ));

                // Retrieve exclusive product list
                $exclusiveProductList = $this->getSubscribeProductList();

                if (!Configuration::get('PAYLINE_SUBSCRIBE_EXCLUSIVE')) {
                    // Non-exclusive method, check if products in cart are correct and eligible
                    $cartIntegrity = true;
                    if (is_array($exclusiveProductList) && sizeof($exclusiveProductList)) {
                        $cartProductList = $this->context->cart->getProducts();
                        if (is_array($cartProductList)) {
                            foreach ($cartProductList as $cartProduct) {
                                // We have to disable this method, no product are eligible
                                if (!in_array($cartProduct['id_product'], $exclusiveProductList)) {
                                    $cartIntegrity = false;
                                    break;
                                }
                            }
                        }
                    }
                    if ($cartIntegrity) {
                        $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment_sub.tpl');
                    }
                } else {
                    // Exclusive method, check if products in cart are correct
                    $cartProductList = $this->context->cart->getProducts();
                    $cartIntegrity = false;
                    $cartFullIntegrity = true;
                    $breakingIntegrityList = array();
                    // We have at least, one product OK
                    if (is_array($cartProductList)) {
                        foreach ($cartProductList as $cartProduct) {
                            if (in_array($cartProduct['id_product'], $exclusiveProductList)) {
                                $cartIntegrity = true;
                            } else {
                                $cartFullIntegrity = false;
                                $breakingIntegrityList[] = $cartProduct['id_product'];
                            }
                        }
                    }

                    if (!$cartIntegrity) {
                        // We have to disable this method, no product are eligible
                        // Nothing to do here
                    } elseif (!$cartFullIntegrity) {
                        // We have to disable payment via Payline, wrong cart content
                        $breakingProductList = array();
                        foreach ($breakingIntegrityList as $idProduct) {
                            $product = new Product($idProduct, false, $this->context->cookie->id_lang);
                            $breakingProductList[] = $product->name;
                        }

                        // Reset payment method list
                        $paymentReturn = '';

                        $this->context->controller->errors[] = $this->l('Your cart contains mixed products (recurring products and classic products).');
                        $this->context->controller->errors[] = $this->l('In order to be able to pay with Payline, please remove these products:');
                        foreach ($breakingProductList as $productName) {
                            $this->context->controller->errors[] = $productName;
                        }
                    } elseif ($cartIntegrity && $cartFullIntegrity) {
                        // We have to hide any other methods...
                        $paymentReturn = $this->display(__FILE__, 'views/templates/hook/payment_sub.tpl');
                    }
                }
            }
        }

        return $paymentReturn;
    }

    /**
     * Check if the payment is available
     * @since 2.0.0
     * @param int $paymentMethod
     * @return bool
     */
    public function isPaymentAvailable($paymentMethod = null)
    {
        if (!$this->active) {
            return;
        }
        // Check for module and API state
        if (!Configuration::get('PAYLINE_API_STATUS')) {
            return false;
        }
        // Check if at least one payment method is available
        if ($paymentMethod == null && !Configuration::get('PAYLINE_WEB_CASH_ENABLE') && !Configuration::get('PAYLINE_RECURRING_ENABLE') && !Configuration::get('PAYLINE_SUBSCRIBE_ENABLE')) {
            return false;
        }
        // Check if web payment method is available
        if ($paymentMethod == PaylinePaymentGateway::WEB_PAYMENT_METHOD && !Configuration::get('PAYLINE_WEB_CASH_ENABLE')) {
            return false;
        }
        // Check if recurring payment method is available
        if ($paymentMethod == PaylinePaymentGateway::RECURRING_PAYMENT_METHOD && !Configuration::get('PAYLINE_RECURRING_ENABLE')) {
            return false;
        }
        // Check if subscribe payment method is available
        if ($paymentMethod == PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD && !Configuration::get('PAYLINE_SUBSCRIBE_ENABLE')) {
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
    protected function checkAllowedCurrency(Cart $cart)
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
            } elseif (Tools::isSubmit('submitPaylinerecurring-web-payment')) {
                $activeTab = 'recurring-web-payment';
            } elseif (Tools::isSubmit('submitPaylinesubscribe-payment')) {
                $activeTab = 'subscribe-payment';
            } elseif (Tools::isSubmit('submitPaylinepayline')) {
                $activeTab = 'payline';
            } elseif (Tools::isSubmit('submitPaylinecontracts')) {
                $activeTab = 'contracts';
            }

            // Add alert if notification URL is empty or invalid
            $pointOfSale = PaylinePaymentGateway::getPointOfSale($currentPos);
            $notificationUrl = $this->context->link->getModuleLink('payline', 'notification', array(), true);
            if (empty($pointOfSale['notificationURL']) || trim($pointOfSale['notificationURL']) != trim($notificationUrl)) {
                $this->context->controller->warnings[] = sprintf($this->l('You must define the notification URL into your point of sale configuration, the current value is empty or invalid. %s The correct URL is: %s %s %s'), '<br />', '<strong>', $notificationUrl, '</strong>');
                $this->context->controller->warnings[] = $this->l('When editing the URL, please be sure to check all the checkbox below the text input too.');
            }

            // Add alert if all contracts are disabled
            $paylineCheckNoEnabledContract = ((Configuration::get('PAYLINE_WEB_CASH_ENABLE') || Configuration::get('PAYLINE_RECURRING_ENABLE') || Configuration::get('PAYLINE_SUBSCRIBE_ENABLE')) && !sizeof(PaylinePaymentGateway::getEnabledContracts()));
            if ($paylineCheckNoEnabledContract) {
                $this->context->controller->warnings[] = $this->l('You must enable at least one contract.');
                $activeTab = 'contracts';
            }
        }

        $this->context->smarty->assign('payline_id_shop', (int)$this->context->shop->id);
        $this->context->smarty->assign('payline_is_ps16', version_compare(_PS_VERSION_, '1.7.0.0', '<'));
        $this->context->smarty->assign('payline_is_ps17', version_compare(_PS_VERSION_, '1.7.0.0', '>='));
        $this->context->smarty->assign('payline_active_tab', $activeTab);
        $this->context->smarty->assign('payline_api_status', Configuration::get('PAYLINE_API_STATUS'));
        $this->context->smarty->assign('payline_contracts_errors', $paylineCheckNoEnabledContract);

        $this->context->smarty->assign('payline_credentials_configuration', $this->renderForm('payline'));
        $this->context->smarty->assign('payline_web_payment_configuration', $this->renderForm('web-payment'));
        $this->context->smarty->assign('payline_recurring_payment_configuration', $this->renderForm('recurring-web-payment'));
        $this->context->smarty->assign('payline_subscribe_payment_configuration', $this->renderForm('subscribe-payment'));
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
            'id_shop' => $this->context->shop->id,
        );

        return $helper->generateForm(array($this->getConfigForm($tabName)));
    }

    /**
     * Retrieve values for <select> items into HelperForm
     * @since 2.0.0
     * @param string $listName
     * @param int $paymentMethod
     * @return array
     */
    protected function getConfigSelectList($listName, $paymentMethod = null)
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
            $ux = array(
                0 => array(
                    'value' => 'tab',
                    'name' => $this->l('in-shop tab'),
                ),
                1 => array(
                    'value' => 'column',
                    'name' => $this->l('in-shop column'),
                ),
                4 => array(
                    'value' => 'redirect',
                    'name' => $this->l('Redirect to payment page'),
                ),
            );

            // Only allow lightbox UX mode once
            $lightboxExperienceAlreadyEnabled = false;
            if ($paymentMethod == PaylinePaymentGateway::WEB_PAYMENT_METHOD) {
                $lightboxExperienceAlreadyEnabled = (Configuration::get('PAYLINE_RECURRING_UX') == 'lightbox');
            } elseif ($paymentMethod == PaylinePaymentGateway::RECURRING_PAYMENT_METHOD) {
                $lightboxExperienceAlreadyEnabled = (Configuration::get('PAYLINE_WEB_CASH_UX') == 'lightbox');
            }
            if (!$lightboxExperienceAlreadyEnabled) {
                $ux[3] = array(
                    'value' => 'lightbox',
                    'name' => $this->l('lightbox'),
                );
            }

            ksort($ux);
            return $ux;
        } elseif ($listName == 'order-states') {
            $orderStatusListForSelect = array();
            foreach (OrderState::getOrderStates($this->context->language->id) as $os) {
                // Ignore order states related to a specific module or error/refund/waiting to be paid states
                if (!empty($os['module_name']) || in_array((int)$os['id_order_state'], array(_PS_OS_ERROR_, _PS_OS_REFUND_, (int)Configuration::get('PAYLINE_ID_STATE_AUTOR'), (int)Configuration::get('PAYLINE_ID_STATE_PENDING'), (int)Configuration::get('PAYLINE_ID_ORDER_STATE_NX'), (int)Configuration::get('PAYLINE_ID_STATE_ALERT_SCHEDULE')))) {
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
        } elseif ($listName == 'recurring-periods' || $listName == 'subscribe-periods') {
            $recurringPeriods = array();
            if ($listName == 'subscribe-periods') {
                $recurringPeriods[] = array('value' => '0', 'name' => $this->l('No limit'));
            }
            for ($period = 2; $period <= 99; $period++) {
                $recurringPeriods[] = array(
                    'value' => $period,
                    'name' => $period,
                );
            }

            return $recurringPeriods;
        } elseif ($listName == 'recurring-frequency' || $listName == 'subscribe-frequency') {
            return array(
                array('value' => '10', 'name' => $this->l('Daily')),
                array('value' => '20', 'name' => $this->l('Weekly')),
                array('value' => '30', 'name' => $this->l('Bimonthly')),
                array('value' => '40', 'name' => $this->l('Monthly')),
                array('value' => '50', 'name' => $this->l('Two quaterly')),
                array('value' => '60', 'name' => $this->l('Quaterly')),
                array('value' => '70', 'name' => $this->l('Semiannual')),
                array('value' => '80', 'name' => $this->l('Annual')),
                array('value' => '90', 'name' => $this->l('Biannual')),
            );
        } elseif ($listName == 'recurring-first-period-weight') {
            $periodWeight = array();
            for ($weight = 0; $weight <= 70; $weight+=5) {
                $periodWeight[] = array(
                    'value' => $weight,
                    'name' => $weight . ' %',
                );
            }

            return $periodWeight;
        } elseif ($listName == 'subscribe-start-date') {
            return array(
                array('value' => 0, 'name' => $this->l('Due day')),
                array('value' => 1, 'name' => $this->l('After a period')),
                array('value' => 2, 'name' => $this->l('After two periods')),
            );
        } elseif ($listName == 'subscribe-days') {
            $subscribeDays = array();
            for ($day = 0; $day <= 31; $day++) {
                $subscribeDays[] = array(
                    'value' => $day,
                    'name' => $day,
                );
            }

            return $subscribeDays;
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
                            'label' => $this->l('Enable simple payment'),
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
                            'form_group_class' => ($this->langConfigHaveAtLeastOneEmptyValue('PAYLINE_WEB_CASH_TITLE') ? 'has-error' : ''),
                            'lang' => true,
                            'required' => true,
                            'type' => 'text',
                            'name' => 'PAYLINE_WEB_CASH_TITLE',
                            'label' => $this->l('Title'),
                            'placeholder' => '',
                            'maxlength' => 255,
                        ),
                        array(
                            'lang' => true,
                            'type' => 'text',
                            'name' => 'PAYLINE_WEB_CASH_SUBTITLE',
                            'label' => $this->l('Subtitle'),
                            'placeholder' => '',
                            'maxlength' => 255,
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
                            'type' => 'switch',
                            'label' => $this->l('Payment by wallet'),
                            'name' => 'PAYLINE_WEB_CASH_BY_WALLET',
                            'is_bool' => true,
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
                            'desc' => $this->l('Redirect customer to secure payment page or display secure form in the checkout'),
                            'name' => 'PAYLINE_WEB_CASH_UX',
                            'label' => $this->l('User experience'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('user-experience', PaylinePaymentGateway::WEB_PAYMENT_METHOD),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'form_group_class' => 'payline-redirect-only' . (Configuration::get('PAYLINE_WEB_CASH_UX') != 'redirect' ? ' hidden' : ''),
                            'class' => 'fixed-width-md',
                            'type' => 'text',
                            'desc' => $this->l('Apply customization created through administration center to the payment page'),
                            'name' => 'PAYLINE_WEB_CASH_CUSTOM_CODE',
                            'label' => $this->l('Payment page customization ID'),
                            'placeholder' => '',
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                    ),
                ),
            );
        } elseif ($tabName == 'recurring-web-payment') {
            return array(
                'form' => array(
                    'legend' => array(
                    'title' => $this->l('Nx payment'),
                    'icon' => 'icon-cogs',
                    ),
                    'input' => array(
                        array(
                            'type' => 'html',
                            'name' => '
                            <h2>'.$this->l('Nx payment').'</h2>
                            <p>'.$this->l('Contracts activated through contracts configuration menu are displayed in the checkout. All payment information is filled on our secure user interface.').'</p>',
                        ),
                        array(
                            'type' => 'switch',
                            'label' => $this->l('Enable Nx payment'),
                            'name' => 'PAYLINE_RECURRING_ENABLE',
                            'is_bool' => true,
                            'desc' => $this->l('choose wether to display Payline recurring payment in your checkout or not'),
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
                            'form_group_class' => ($this->langConfigHaveAtLeastOneEmptyValue('PAYLINE_RECURRING_TITLE') ? 'has-error' : ''),
                            'lang' => true,
                            'required' => true,
                            'type' => 'text',
                            'name' => 'PAYLINE_RECURRING_TITLE',
                            'label' => $this->l('Title'),
                            'placeholder' => '',
                            'maxlength' => 255,
                        ),
                        array(
                            'lang' => true,
                            'type' => 'text',
                            'name' => 'PAYLINE_RECURRING_SUBTITLE',
                            'label' => $this->l('Subtitle'),
                            'placeholder' => '',
                            'maxlength' => 255,
                        ),
                        array(
                            'class' => 'fixed-width-sm',
                            'type' => 'text',
                            'desc' => $this->l('Amount under which payment in several times is not displayed'),
                            'name' => 'PAYLINE_RECURRING_TRIGGER',
                            'label' => $this->l('Minimal order total to allow recurring'),
                            'placeholder' => '0',
                        ),
                        array(
                            'type' => 'select',
                            'name' => 'PAYLINE_RECURRING_NUMBER',
                            'label' => $this->l('Number of payments'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('recurring-periods'),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'type' => 'select',
                            'name' => 'PAYLINE_RECURRING_PERIODICITY',
                            'label' => $this->l('Periodicity of payments'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('recurring-frequency'),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'type' => 'select',
                            'desc' => $this->l('Percentage of total amount for first payment'),
                            'name' => 'PAYLINE_RECURRING_FIRST_WEIGHT',
                            'label' => $this->l('First payment weight'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('recurring-first-period-weight'),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'type' => 'switch',
                            'label' => $this->l('Payment by wallet'),
                            'name' => 'PAYLINE_RECURRING_BY_WALLET',
                            'is_bool' => true,
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
                            'desc' => $this->l('Redirect customer to secure payment page or display secure form in the checkout'),
                            'name' => 'PAYLINE_RECURRING_UX',
                            'label' => $this->l('User experience'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('user-experience', PaylinePaymentGateway::RECURRING_PAYMENT_METHOD),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'form_group_class' => 'payline-redirect-only' . (Configuration::get('PAYLINE_RECURRING_UX') != 'redirect' ? ' hidden' : ''),
                            'class' => 'fixed-width-md',
                            'type' => 'text',
                            'desc' => $this->l('Apply customization created through administration center to the payment page'),
                            'name' => 'PAYLINE_RECURRING_CUSTOM_CODE',
                            'label' => $this->l('Payment page customization ID'),
                            'placeholder' => '',
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                    ),
                ),
            );
        } elseif ($tabName == 'subscribe-payment') {
            $subscribeProductList = array();
            $subscribeProductListId = $this->getSubscribeProductList();
            if (!empty($subscribeProductListId)) {
                foreach ($subscribeProductListId as $idProduct) {
                    if (empty($idProduct)) {
                        continue;
                    }
                    $product = new Product($idProduct, false, $this->context->employee->id_lang);
                    if (Validate::isLoadedObject($product)) {
                        $subscribeProductList[] = array(
                            'id' => (int)$product->id,
                            'name' => $product->name . " (ref: " . $product->reference . ")",
                            'id_image' => $product->getCoverWs(),
                        );
                    }
                }
            }
            return array(
                'form' => array(
                    'legend' => array(
                    'title' => $this->l('Recurring payment'),
                    'icon' => 'icon-cogs',
                    ),
                    'input' => array(
                        array(
                            'type' => 'html',
                            'name' => '
                            <h2>'.$this->l('Recurring payment').'</h2>
                            <p>'.$this->l('Contracts activated through contracts configuration menu are displayed in the checkout. All payment information is filled on our secure user interface.').'</p>',
                        ),
                        array(
                            'type' => 'switch',
                            'label' => $this->l('Enable recurring payment'),
                            'name' => 'PAYLINE_SUBSCRIBE_ENABLE',
                            'is_bool' => true,
                            'desc' => $this->l('choose wether to display Payline subscribe payment in your checkout or not'),
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
                            'form_group_class' => ($this->langConfigHaveAtLeastOneEmptyValue('PAYLINE_SUBSCRIBE_TITLE') ? 'has-error' : ''),
                            'lang' => true,
                            'required' => true,
                            'type' => 'text',
                            'name' => 'PAYLINE_SUBSCRIBE_TITLE',
                            'label' => $this->l('Title'),
                            'placeholder' => '',
                            'maxlength' => 255,
                        ),
                        array(
                            'lang' => true,
                            'type' => 'text',
                            'name' => 'PAYLINE_SUBSCRIBE_SUBTITLE',
                            'label' => $this->l('Subtitle'),
                            'placeholder' => '',
                            'maxlength' => 255,
                        ),
                        array(
                            'type' => 'select',
                            'name' => 'PAYLINE_SUBSCRIBE_START_DATE',
                            'label' => $this->l('Start date of scheduler'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('subscribe-start-date'),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'type' => 'select',
                            'name' => 'PAYLINE_SUBSCRIBE_NUMBER',
                            'label' => $this->l('Number of payments'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('subscribe-periods'),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'type' => 'select',
                            'name' => 'PAYLINE_SUBSCRIBE_PERIODICITY',
                            'label' => $this->l('Periodicity of payments'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('subscribe-frequency'),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'type' => 'select',
                            'desc' => $this->l('0 if you want the payment to take place the same day as the date of the first order'),
                            'name' => 'PAYLINE_SUBSCRIBE_DAY',
                            'label' => $this->l('Recurring days'),
                            'options' => array(
                                'query' => $this->getConfigSelectList('subscribe-days'),
                                'id' => 'value',
                                'name' => 'name',
                            ),
                        ),
                        array(
                            'type' => 'product-selector',
                            'name' => 'PAYLINE_SUBSCRIBE_PLIST',
                            'label' => $this->l('Allowed product list'),
                            'values' => $subscribeProductList,
                        ),
                        array(
                            'type' => 'switch',
                            'desc' => $this->l('If a product from the cart is in this list, only this method will be shown. Else, this method will only be available if a product will be in your cart.'),
                            'label' => $this->l('Set this product list as exclusive'),
                            'name' => 'PAYLINE_SUBSCRIBE_EXCLUSIVE',
                            'is_bool' => true,
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
     * Get list of product id allowed by subscription method
     * @since 2.2.0
     * @return array
     */
    protected function getSubscribeProductList()
    {
        $subscribeProductListId = array();
        $subscribeProductListIdValue = Configuration::get('PAYLINE_SUBSCRIBE_PLIST');
        if (!empty($subscribeProductListIdValue)) {
            $subscribeProductListId = array_map('intval', explode(',', $subscribeProductListIdValue));
        }

        return $subscribeProductListId;
    }

    /**
     * Get multilang values for a specific input
     * @since 2.1.0
     * @param string $configKey
     * @return array
     */
    protected function getConfigLangValue($configKey)
    {
        $languages = Language::getLanguages(false);

        $langValues = array();
        foreach ($languages as $lang) {
            $langValues[(int)$lang['id_lang']] = Configuration::get($configKey, (int)$lang['id_lang']);
        }

        return $langValues;
    }

    /**
     * Check if a multilang values has an empty value
     * @since 2.2.0
     * @param string $configKey
     * @return array
     */
    protected function langConfigHaveAtLeastOneEmptyValue($configKey)
    {
        foreach ($this->getConfigLangValue($configKey) as $langValue) {
            if (!strlen($langValue)) {
                return true;
            }
        }

        return false;
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
                'PAYLINE_WEB_CASH_TITLE' => $this->getConfigLangValue('PAYLINE_WEB_CASH_TITLE'),
                'PAYLINE_WEB_CASH_SUBTITLE' => $this->getConfigLangValue('PAYLINE_WEB_CASH_SUBTITLE'),
                'PAYLINE_WEB_CASH_ACTION' => Configuration::get('PAYLINE_WEB_CASH_ACTION'),
                'PAYLINE_WEB_CASH_VALIDATION' => Configuration::get('PAYLINE_WEB_CASH_VALIDATION'),
                'PAYLINE_WEB_CASH_BY_WALLET' => Configuration::get('PAYLINE_WEB_CASH_BY_WALLET'),
                'PAYLINE_WEB_CASH_UX' => Configuration::get('PAYLINE_WEB_CASH_UX'),
                'PAYLINE_WEB_CASH_CUSTOM_CODE' => Configuration::get('PAYLINE_WEB_CASH_CUSTOM_CODE'),
            );
        } elseif ($tabName == 'recurring-web-payment') {
            return array(
                'PAYLINE_RECURRING_ENABLE' => Configuration::get('PAYLINE_RECURRING_ENABLE'),
                'PAYLINE_RECURRING_TITLE' => $this->getConfigLangValue('PAYLINE_RECURRING_TITLE'),
                'PAYLINE_RECURRING_SUBTITLE' => $this->getConfigLangValue('PAYLINE_RECURRING_SUBTITLE'),
                'PAYLINE_RECURRING_TRIGGER' => (float)Configuration::get('PAYLINE_RECURRING_TRIGGER'),
                'PAYLINE_RECURRING_NUMBER' => (int)Configuration::get('PAYLINE_RECURRING_NUMBER'),
                'PAYLINE_RECURRING_PERIODICITY' => Configuration::get('PAYLINE_RECURRING_PERIODICITY'),
                'PAYLINE_RECURRING_FIRST_WEIGHT' => Configuration::get('PAYLINE_RECURRING_FIRST_WEIGHT'),
                'PAYLINE_RECURRING_BY_WALLET' => Configuration::get('PAYLINE_RECURRING_BY_WALLET'),
                'PAYLINE_RECURRING_UX' => Configuration::get('PAYLINE_RECURRING_UX'),
                'PAYLINE_RECURRING_CUSTOM_CODE' => Configuration::get('PAYLINE_RECURRING_CUSTOM_CODE'),
            );
        } elseif ($tabName == 'subscribe-payment') {
            return array(
                'PAYLINE_SUBSCRIBE_ENABLE' => Configuration::get('PAYLINE_SUBSCRIBE_ENABLE'),
                'PAYLINE_SUBSCRIBE_TITLE' => $this->getConfigLangValue('PAYLINE_SUBSCRIBE_TITLE'),
                'PAYLINE_SUBSCRIBE_SUBTITLE' => $this->getConfigLangValue('PAYLINE_SUBSCRIBE_SUBTITLE'),
                'PAYLINE_SUBSCRIBE_START_DATE' => (int)Configuration::get('PAYLINE_SUBSCRIBE_START_DATE'),
                'PAYLINE_SUBSCRIBE_NUMBER' => (int)Configuration::get('PAYLINE_SUBSCRIBE_NUMBER'),
                'PAYLINE_SUBSCRIBE_PERIODICITY' => Configuration::get('PAYLINE_SUBSCRIBE_PERIODICITY'),
                'PAYLINE_SUBSCRIBE_DAY' => (int)Configuration::get('PAYLINE_SUBSCRIBE_DAY'),
                'PAYLINE_SUBSCRIBE_PLIST' => Configuration::get('PAYLINE_SUBSCRIBE_PLIST'),
                'PAYLINE_SUBSCRIBE_EXCLUSIVE' => Configuration::get('PAYLINE_SUBSCRIBE_EXCLUSIVE'),
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
        } elseif (Tools::isSubmit('submitPaylinerecurring-web-payment')) {
            $tabName = 'recurring-web-payment';
            // Add confirmation message
            $this->context->controller->confirmations[] = $this->l('Configuration saved.');
        } elseif (Tools::isSubmit('submitPaylinesubscribe-payment')) {
            $tabName = 'subscribe-payment';
            // Add confirmation message
            $this->context->controller->confirmations[] = $this->l('Configuration saved.');
        } elseif (Tools::isSubmit('submitPaylinepayline')) {
            $tabName = 'payline';
        } elseif (Tools::isSubmit('submitPaylinecontracts')) {
            $tabName = 'contracts';
        }
        if (!empty($tabName)) {
            $form_values = $this->getConfigFormValues($tabName);
            $languages = Language::getLanguages(false);

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
                'PAYLINE_RECURRING_CUSTOM_CODE',
                'PAYLINE_CONTRACTS',
                'PAYLINE_ALT_CONTRACTS',
                'PAYLINE_SUBSCRIBE_PLIST',
            );
            // Multilang fields
            $multiLangFields = array(
                'PAYLINE_WEB_CASH_TITLE',
                'PAYLINE_WEB_CASH_SUBTITLE',
                'PAYLINE_RECURRING_TITLE',
                'PAYLINE_RECURRING_SUBTITLE',
                'PAYLINE_SUBSCRIBE_TITLE',
                'PAYLINE_SUBSCRIBE_SUBTITLE',
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
                } elseif ($key == 'PAYLINE_SUBSCRIBE_PLIST') {
                    $newValue = ltrim(rtrim(trim(Tools::getValue($key)), ','), ',');
                    $newValue = implode(',', array_map('intval', explode(',', $newValue)));
                } elseif (in_array($key, $multiLangFields)) {
                    $newValue = array();
                    foreach ($languages as $lang) {
                        $newValue[(int)$lang['id_lang']] = Tools::getValue($key . '_' . (int)$lang['id_lang']);
                    }
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

            // Required fields, check value and display errors
            $requiredMultiLangFields = array();
            if ($tabName == 'web-payment') {
                $requiredMultiLangFields['PAYLINE_WEB_CASH_TITLE'] = $this->l('Title');
            }
            if ($tabName == 'recurring-web-payment') {
                $requiredMultiLangFields['PAYLINE_RECURRING_TITLE'] = $this->l('Title');
            }
            if ($tabName == 'subscribe-payment') {
                $requiredMultiLangFields['PAYLINE_SUBSCRIBE_TITLE'] = $this->l('Title');
            }

            foreach ($requiredMultiLangFields as $requiredKey => $label) {
                foreach ($languages as $lang) {
                    $langValue = Tools::getValue($requiredKey . '_' . (int)$lang['id_lang']);
                    if (!strlen($langValue)) {
                        $this->context->controller->errors[] = sprintf($this->l('You must fill each required fields (check %s - %s language).'), $label, $lang['iso_code']);
                    }
                }
            }
            if (sizeof($this->context->controller->errors)) {
                $this->context->controller->confirmations = array();
                $this->context->controller->errors = array_unique($this->context->controller->errors);
            }
        }
    }

    /**
     * Get readable human code from error code
     * @param int $errorCode
     * @return string
     */
    protected function getHumanErrorCode($errorCode)
    {
        switch ($errorCode) {
            case payline::INVALID_AMOUNT:
                return $this->l('Order can\'t be created because paid amount is different than total cart amount.');
            case payline::INVALID_CART_ID:
                return $this->l('Order can\'t be created because related cart does not exists.');
            case payline::SUBSCRIPTION_ERROR:
                return $this->l('Order can\'t be created because subscription failed.');
            default:
                return null;
        }
    }

    /**
     * Process payment validation (customer shop return)
     * @param Cart $cart
     * @param array $paymentInfos
     * @param string $token
     * @param string $paymentRecordId
     * @since 2.0.0
     * @return array
     */
    protected function createOrder(Cart $cart, $paymentInfos, $token, $paymentRecordId = null)
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

        $checkAmountToPay = true;
        $fixOrderPayment = false;
        $totalAmountPaid = Tools::ps_round((float)$amountPaid, 2);

        if ($paymentInfos['payment']['mode'] == 'NX') {
            // Recurring payment
            $nxConfiguration = PaylinePaymentGateway::getNxConfiguration(round($cart->getOrderTotal() * 100));
            if (!$orderExists) {
                // First amount
                $totalAmountToPay = (float)Tools::ps_round((float)($nxConfiguration['firstAmount'] / 100), 2);

                // Set order state
                $idOrderState = (int)Configuration::get('PAYLINE_ID_ORDER_STATE_NX');

                // Fake $amountPaid in order to create the order without payment error, we will fix the order payment after order creation
                $amountPaid = $cart->getOrderTotal();
                $fixOrderPayment = true;
            } else {
                // Recurrent amount
                $totalAmountToPay = (float)Tools::ps_round((float)($nxConfiguration['amount'] / 100), 2);
                // Do not check amount to pay
                $checkAmountToPay = false;
            }
        } else {
            // Web payment
            $totalAmountToPay = (float)Tools::ps_round((float)$cart->getOrderTotal(true, Cart::BOTH), 2);
        }

        if ($checkAmountToPay && number_format($totalAmountToPay, _PS_PRICE_COMPUTE_PRECISION_) != number_format($totalAmountPaid, _PS_PRICE_COMPUTE_PRECISION_)) {
            // Wrong amount paid, do not create order
            PrestaShopLogger::addLog('payline::createOrder - Wrong amount paid, we do not create order', 1, null, 'Cart', $cart->id);

            // We try to refund/cancel the current transaction
            // If refund can't be done, we continue the classic process. Order will be marked as invalid
            $cancelTransactionResult = PaylinePaymentGateway::cancelTransaction($paymentInfos, $this->l('Error: automatic cancel (cart total != amount paid)'));
            if ($cancelTransactionResult) {
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
                    if (Validate::isLoadedObject($order)) {
                        // Save token and payment record id (if defined) for later usage
                        PaylineToken::insert($order, $cart, $token, $paymentRecordId, $paymentInfos['transaction']['id']);

                        if ($fixOrderPayment) {
                            // We need to fix the total paid real amount here
                            $order->total_paid_real = 0;
                            $order->save();
                            // Remove the previous order payment
                            $orderPayments = OrderPayment::getByOrderReference($order->reference);
                            foreach ($orderPayments as $orderPayment) {
                                $orderPayment->delete();
                            }
                            // Add the fixed order payment
                            $this->addOrderPaymentToOrder($order, $totalAmountPaid, $paymentInfos['transaction']['id']);
                        }
                    }
                }
            } catch (Exception $e) {
                $validateOrderResult = false;
                $errorMessage = $e->getMessage();
                PrestaShopLogger::addLog('payline::createOrder - Failed to create order: ' . $errorMessage, 1, null, 'Cart', $cart->id);
            }
        } elseif ($cart->secure_key == $paymentInfos['formatedPrivateDataList']['secure_key']) {
            // Secure key is OK
            $idOrder = Order::getOrderByCartId($cart->id);
            $order = new Order($idOrder);
            // Retrieve order
            if (Validate::isLoadedObject($order)) {
                // Save token for later usage (if needed)
                PaylineToken::insert($order, $cart, $token, $paymentRecordId, $paymentInfos['transaction']['id']);

                // Check if transaction ID is the same
                $orderPayments = OrderPayment::getByOrderReference($order->reference);
                $sameTransactionID = false;
                foreach ($orderPayments as $orderPayment) {
                    if ($orderPayment->transaction_id == $paymentInfos['transaction']['id']) {
                        $sameTransactionID = true;
                    }
                }
                if (!$sameTransactionID) {
                    // Order already exists, but it looks to be a new transaction - What should we do ?
                    if ($paymentInfos['payment']['mode'] == 'NX') {
                        // New recurring payment, add a new transaction to the current order
                        $this->addOrderPaymentToOrder($order, $totalAmountPaid, $paymentInfos['transaction']['id']);
                    } else {
                        $order = null;
                    }
                }
            } else {
                // Unable to retrieve order ?
                PrestaShopLogger::addLog('payline::createOrder - Unable to retrieve order', 1, null, 'Cart', $cart->id);
            }
            $validateOrderResult = true;
        } elseif ($cart->secure_key != $paymentInfos['formatedPrivateDataList']['secure_key']) {
            // Order already exists for this cart and secure key is different
            // Secure key is NOK
            PrestaShopLogger::addLog('payline::createOrder - Secure key is different', 1, null, 'Cart', $cart->id);
            $validateOrderResult = false;
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
        $order = null;

        // Check if id_cart and secure_key are the same
        if (isset($paymentInfos['formatedPrivateDataList']) && is_array($paymentInfos['formatedPrivateDataList'])
            && isset($paymentInfos['formatedPrivateDataList']['id_cart'])
            && isset($paymentInfos['formatedPrivateDataList']['secure_key'])
        ) {
            if (PaylinePaymentGateway::isValidResponse($paymentInfos, PaylinePaymentGateway::$approvedResponseCode) || PaylinePaymentGateway::isValidResponse($paymentInfos, PaylinePaymentGateway::$pendingResponseCode)) {
                // Transaction approved or pending

                // OK we can process the order via customer return
                $idCart = (int)$paymentInfos['formatedPrivateDataList']['id_cart'];
                // Check if cart exists
                $cart = new Cart($idCart);
                if (Validate::isLoadedObject($cart)) {
                    // Create the recurrent wallet payment
                    if (!empty($paymentInfos['formatedPrivateDataList']['payment_method']) && $paymentInfos['formatedPrivateDataList']['payment_method'] == PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD) {
                        $subscriptionRequest = PaylinePaymentGateway::createSubscriptionRequest($paymentInfos);
                        if (PaylinePaymentGateway::isValidResponse($subscriptionRequest, array('02500', '02501'))) {
                            // Create the order
                            list($order, $validateOrderResult, $errorMessage, $errorCode) = $this->createOrder($cart, $paymentInfos, $token, $subscriptionRequest['paymentRecordId']);
                        } else {
                            // Unable to create subscription request...
                            $errorCode = payline::SUBSCRIPTION_ERROR;
                            $cancelTransactionResult = PaylinePaymentGateway::cancelTransaction($paymentInfos, $this->l('Error: automatic cancel (cannot create subscription)'));
                            if ($cancelTransactionResult) {
                                // Set a cookie value that expose how many try users has made with invalid amount
                                if (!isset($this->context->cookie->pl_try)) {
                                    $this->context->cookie->pl_try = 2;
                                } else {
                                    $this->context->cookie->pl_try += 1;
                                }
                            }
                        }
                    } else {
                        // Create the order
                        list($order, $validateOrderResult, $errorMessage, $errorCode) = $this->createOrder($cart, $paymentInfos, $token);
                    }
                } else {
                    // Invalid Cart ID
                    $errorCode = payline::INVALID_CART_ID;
                }
            }
        }

        // Order has been created, redirect customer to confirmation page
        if (isset($order) && $order instanceof Order && Validate::isLoadedObject($order)) {
            Tools::redirect('index.php?controller=order-confirmation&id_cart='.$idCart.'&id_module='.$this->id.'&id_order='.$this->currentOrder.'&key='.$this->context->customer->secure_key);
        }

        $urlParams = array(
            'paylineError' => 1,
            'paylinetoken' => $token,
        );
        if (isset($errorCode)) {
            $urlParams['paylineErrorCode'] = $errorCode;
        }

        // Refused payment, or any other error case (customer case)
        if (!empty($paymentInfos['formatedPrivateDataList']['payment_method']) && $paymentInfos['formatedPrivateDataList']['payment_method'] == PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD) {
            Tools::redirect($this->context->link->getPageLink('order', true, $this->context->language->id, $urlParams));
        } else {
            if (Configuration::get('PAYLINE_WEB_CASH_UX') == 'lightbox' || Configuration::get('PAYLINE_WEB_CASH_UX') == 'redirect') {
                Tools::redirect($this->context->link->getPageLink('order', true, $this->context->language->id, $urlParams));
            } else {
                Tools::redirect($this->context->link->getModuleLink($this->name, 'payment', $urlParams, true));
            }
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
            if (PaylinePaymentGateway::isValidResponse($paymentInfos, PaylinePaymentGateway::$approvedResponseCode) || PaylinePaymentGateway::isValidResponse($paymentInfos, PaylinePaymentGateway::$pendingResponseCode)) {
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

                // Create the recurrent wallet payment
                if (!empty($paymentInfos['formatedPrivateDataList']['payment_method']) && $paymentInfos['formatedPrivateDataList']['payment_method'] == PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD) {
                    $subscriptionRequest = PaylinePaymentGateway::createSubscriptionRequest($paymentInfos);
                    if (PaylinePaymentGateway::isValidResponse($subscriptionRequest, array('02500', '02501'))) {
                        // Create the order
                        list($order, $validateOrderResult, $errorMessage, $errorCode) = $this->createOrder($cart, $paymentInfos, $token, $subscriptionRequest['paymentRecordId']);
                    } else {
                        // Unable to create subscription
                        $errorCode = payline::SUBSCRIPTION_ERROR;
                        // Cancel the previous transaction
                        $cancelTransactionResult = PaylinePaymentGateway::cancelTransaction($paymentInfos, $this->l('Error: automatic cancel (cannot create subscription)'));
                        die(Tools::jsonEncode(array(
                            'result' => $validateOrderResult,
                            'error' => 'Unable to create subscription',
                            'errorCode' => $subscriptionRequest['result']['code'],
                        )));
                    }
                } else {
                    // Create the order
                    list($order, $validateOrderResult, $errorMessage, $errorCode) = $this->createOrder($cart, $paymentInfos, $token);
                }
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
        if (PaylinePaymentGateway::isValidResponse($transaction, PaylinePaymentGateway::$pendingResponseCode)) {
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
     * Process order update from NX transaction notification
     * @param string $idTransaction
     * @param string $paymentRecordId
     * @since 2.1.0
     * @return void
     */
    public function processNxNotification($idTransaction, $paymentRecordId)
    {
        $notificationResult = false;
        $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
        // Wait for a transaction into pending state
        if (!empty($transaction['payment']['contractNumber'])) {
            // Get payment record
            $paymentRecord = PaylinePaymentGateway::getPaymentRecord($transaction['payment']['contractNumber'], $paymentRecordId);
            if (!PaylinePaymentGateway::isValidResponse($paymentRecord, array('02500'))) {
                die(Tools::jsonEncode(array(
                    'result' => $notificationResult,
                    'error' => 'Invalid paymentRecord response',
                )));
            }

            // Retrieve cart
            $idCart = null;
            $cart = null;
            if (isset($transaction['formatedPrivateDataList']) && is_array($transaction['formatedPrivateDataList']) && isset($transaction['formatedPrivateDataList']['id_cart'])) {
                $idCart = (int)$transaction['formatedPrivateDataList']['id_cart'];
            } else {
                // Retrieve id_cart from order reference
                $idCart = PaylinePaymentGateway::getCartIdFromOrderReference($transaction['order']['ref']);
            }
            // Check if cart exists
            $cart = new Cart($idCart);
            if (!Validate::isLoadedObject($cart)) {
                die(Tools::jsonEncode(array(
                    'result' => $notificationResult,
                    'error' => 'Invalid Cart ID #'.$idCart.' - Cart does not exists',
                )));
            }

            if (isset($transaction['formatedPrivateDataList']) && is_array($transaction['formatedPrivateDataList'])
                && isset($transaction['formatedPrivateDataList']['id_customer'])
                && isset($transaction['formatedPrivateDataList']['secure_key'])
            ) {
                // OK we can process the order via customer return
                // Check secure_key and id_customer on the cart, compare it to the transaction
                if ($cart->secure_key != $transaction['formatedPrivateDataList']['secure_key'] || $cart->id_customer != $transaction['formatedPrivateDataList']['id_customer']) {
                    die(Tools::jsonEncode(array(
                        'result' => $notificationResult,
                        'error' => 'Transaction is not linked to the right Customer for Cart ID #'.$idCart,
                    )));
                }
            }

            // Always clean Cart::orderExists cache before trying to create the order
            if (class_exists('Cache', false) && method_exists('Cache', 'clean')) {
                Cache::clean('Cart::orderExists_' . $cart->id);
            }
            $orderExists = $cart->OrderExists();
            if (!$orderExists) {
                // There is no order for this cart
                die(Tools::jsonEncode(array(
                    'result' => $notificationResult,
                    'error' => 'Invalid Cart ID #'.$idCart.' - Order does not exists',
                )));
            }

            // Retrieve order
            $idOrder = Order::getOrderByCartId($cart->id);
            $order = new Order($idOrder);
            if (Validate::isLoadedObject($order)) {
                // Update payment_record_id
                if (!PaylineToken::getPaymentRecordIdByIdOrder($order->id)) {
                    PaylineToken::setPaymentRecordIdByIdOrder($order, $paymentRecordId);
                }

                // Check if transaction ID is the same
                $orderPayments = OrderPayment::getByOrderReference($order->reference);
                if (isset($paymentRecord['billingRecordList']) && is_array($paymentRecord['billingRecordList']) &&
                    isset($paymentRecord['billingRecordList']['billingRecord']) && is_array($paymentRecord['billingRecordList']['billingRecord'])) {
                    $validTransactionCount = PaylinePaymentGateway::getValidatedRecurringPayment($paymentRecord);

                    // Check if the recurring is finished and full paid
                    foreach ($paymentRecord['billingRecordList']['billingRecord'] as $kBillingRecord => $billingRecord) {
                        // Delayed
                        if ($billingRecord['calculated_status'] == 4) {
                            continue;
                        }
                        // A transaction has been refused, check if the next transaction has not yet been processed
                        if ($billingRecord['calculated_status'] == 2) {
                            $nextBillingRecord = null;
                            if (isset($paymentRecord['billingRecordList']['billingRecord'][$kBillingRecord+1])) {
                                $nextBillingRecord = $paymentRecord['billingRecordList']['billingRecord'][$kBillingRecord+1];
                            }
                            if ($nextBillingRecord === null || $nextBillingRecord['calculated_status'] != 1) {
                                // The next transaction has not been processed yet, or is also invalid
                                // Or there is no more planned transaction for this billingRecord
                                if (!count($order->getHistory((int)$this->context->language->id, (int)Configuration::get('PAYLINE_ID_STATE_ALERT_SCHEDULE'), true))) {
                                    // Change order state
                                    $history = new OrderHistory();
                                    $history->id_order = (int)$order->id;
                                    $history->changeIdOrderState((int)Configuration::get('PAYLINE_ID_STATE_ALERT_SCHEDULE'), (int)$order->id);
                                    $history->addWithemail();
                                }
                            }
                        }
                    }

                    // Loop on billing list to add payment records on Order
                    foreach ($paymentRecord['billingRecordList']['billingRecord'] as $billingRecord) {
                        if ($billingRecord['calculated_status'] == 1) {
                            // Check if OrderPayment exists for this transaction
                            $orderPaymentExists = false;
                            foreach ($orderPayments as $orderPayment) {
                                if ($orderPayment->transaction_id == $billingRecord['transaction']['id']) {
                                    $orderPaymentExists = true;
                                    break;
                                }
                            }
                            if (!$orderPaymentExists) {
                                // There is OrderPayment for this transaction, add a new order payment to the current order
                                if ($billingRecord['calculated_status'] == 1) {
                                    $notificationResult &= $this->addOrderPaymentToOrder($order, Tools::ps_round($billingRecord['amount'] / 100, 2), $billingRecord['transaction']['id'], date('Y-m-d H:i:s', PaylinePaymentGateway::getTimestampFromPaylineDate($billingRecord['transaction']['date'])));
                                }
                            }
                        }
                    }
                    if ($validTransactionCount == $paymentRecord['recurring']['billingLeft']) {
                        // Order is now 100% paid
                        if (!count($order->getHistory((int)$this->context->language->id, _PS_OS_PAYMENT_, true))) {
                            // Change order state
                            $history = new OrderHistory();
                            $history->id_order = (int)$order->id;
                            $history->changeIdOrderState(_PS_OS_PAYMENT_, (int)$order->id, true);
                            $history->addWithemail();
                        }
                    }
                }
            }
        }
        if (ob_get_length() > 0) {
            ob_clean();
        }
        die(Tools::jsonEncode(array('result' => $notificationResult)));
    }

    /**
     * Process order update from REC transaction notification
     * @param string $idTransaction
     * @param string $paymentRecordId
     * @since 2.2.0
     * @return void
     */
    public function processRecNotification($idTransaction, $paymentRecordId)
    {
        $notificationResult = false;
        $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);

        if (!empty($transaction['formatedPrivateDataList']['payment_method']) && $transaction['formatedPrivateDataList']['payment_method'] == PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD) {
            // Wait for a transaction into pending state
            if (!empty($transaction['payment']['contractNumber'])) {
                // Get payment record
                $paymentRecord = PaylinePaymentGateway::getPaymentRecord($transaction['payment']['contractNumber'], $paymentRecordId);
                if (!PaylinePaymentGateway::isValidResponse($paymentRecord, array('02500'))) {
                    die(Tools::jsonEncode(array(
                        'result' => $notificationResult,
                        'error' => 'Invalid paymentRecord response',
                    )));
                }

                // Check if an order has already been created for this transaction id
                $idOrder = PaylineToken::getIdOrderByIdTransaction($transaction['transaction']['id']);
                if (!empty($idOrder)) {
                    die(Tools::jsonEncode(array(
                        'result' => false,
                        'error' => 'An order already exists for transaction ' . $transaction['transaction']['id'],
                    )));
                }

                // Retrieve cart
                $idCart = null;
                $cart = null;
                if (isset($transaction['formatedPrivateDataList']) && is_array($transaction['formatedPrivateDataList']) && isset($transaction['formatedPrivateDataList']['id_cart'])) {
                    $idCart = (int)$transaction['formatedPrivateDataList']['id_cart'];
                }
                // Check if cart original exists
                $cart = new Cart($idCart);
                if (!Validate::isLoadedObject($cart)) {
                    die(Tools::jsonEncode(array(
                        'result' => $notificationResult,
                        'error' => 'Invalid Cart ID #'.$idCart.' - Cart does not exists',
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
                        'result' => $notificationResult,
                        'error' => 'Invalid Cart ID #'.$idCart.' - Original order does not exists',
                    )));
                }

                // Retrieve order
                $idOrder = Order::getOrderByCartId($cart->id);
                $order = new Order($idOrder);
                if (Validate::isLoadedObject($order)) {
                    // Let's duplicate original cart as order exists
                    $newCartDuplicate = $cart->duplicate();
                    if (!empty($newCartDuplicate['success']) && isset($newCartDuplicate['cart'])) {
                        $newCart = $newCartDuplicate['cart'];
                        // Create the order
                        list($order, $notificationResult, $errorMessage, $errorCode) = $this->createOrder($newCart, $transaction, '', $paymentRecordId);
                    }
                }
            }
        }
        if (ob_get_length() > 0) {
            ob_clean();
        }
        die(Tools::jsonEncode(array('result' => $notificationResult)));
    }

    /**
     * Clone of Order::addOrderPayment() - We force total_paid_real to be = 0 instead of a negative value, so we can update without warning
     * @since 2.0.0
     * @return bool
     */
    protected function addOrderPaymentAfterRefund(Order $order, $amount_paid, $payment_method = null, $payment_transaction_id = null, $currency = null, $date = null, $order_invoice = null)
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

    /**
     * Use Order::addOrderPayment(), but retrieve invoice first
     * @since 2.1.0
     * @return bool
     */
    protected function addOrderPaymentToOrder(Order $order, $amountPaid, $transactionId, $date = null)
    {
        // Get first invoice
        $invoice = $order->getInvoicesCollection()->getFirst();
        if (!($invoice instanceof OrderInvoice)) {
            $invoice = null;
        }

        return $order->addOrderPayment($amountPaid, null, $transactionId, null, $date, $invoice);
    }
}
