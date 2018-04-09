<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

class paylineSubscriptionsModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $authRedirection = 'my-account';
    public $ssl = true;
    public $display_column_left = false;
    public $display_column_right = false;

    /**
     * @see FrontController::setMedia()
     */
    public function setMedia()
    {
        parent::setMedia();

        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $this->registerJavascript('modules-payline-subscriptions-js-1', 'modules/payline/views/js/subscriptions.js', array('position' => 'bottom', 'priority' => 150));
        } else {
            $this->addJS(_MODULE_DIR_.'payline/views/js/subscriptions.js');
        }
    }

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        if (Tools::getValue('cancelSubscriptionId')) {
            // Retrieve all customer subscriptions
            $paymentRecordIdList = PaylineToken::getPaymentRecordIdListByIdCustomer($this->context->customer->id);
            $paymentRecordId = Tools::getValue('cancelSubscriptionId');
            if (in_array($paymentRecordId, $paymentRecordIdList)) {
                // Cancel subscription
                $idOrderList = PaylineToken::getIdOrderListByPaymentRecordId($paymentRecordId);
                if (sizeof($idOrderList)) {
                    $firstIdOrder = current($idOrderList);
                    if (!empty($firstIdOrder)) {
                        $order = new Order($firstIdOrder);
                        // Retrieve original transaction via token or transaction id
                        $token = PaylineToken::getTokenByIdOrder($order->id);
                        if (!empty($token)) {
                            $originalTransaction = PaylinePaymentGateway::getPaymentInformations($token);
                        } else {
                            $idTransaction = PaylineToken::getIdTransactionByIdOrder($order->id);
                            if (!empty($idTransaction)) {
                                $originalTransaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
                            }
                        }
                        if (!empty($originalTransaction)) {
                            // Retrieve payment record
                            $paymentRecord = PaylinePaymentGateway::getPaymentRecord($originalTransaction['payment']['contractNumber'], $paymentRecordId);
                            if (PaylinePaymentGateway::isValidResponse($paymentRecord, array('02500'))) {
                                $disablePaymentRecord = PaylinePaymentGateway::disablePaymentRecord($originalTransaction['payment']['contractNumber'], $paymentRecordId);
                                if (PaylinePaymentGateway::isValidResponse($disablePaymentRecord, array('02500'))) {
                                    Tools::redirect($this->context->link->getModuleLink('payline', 'subscriptions', array('cancelSubscriptionOK' => 1), true));
                                }
                            }
                        }
                    }
                }
            }
            Tools::redirect($this->context->link->getModuleLink('payline', 'subscriptions', array('cancelSubscriptionNOK' => 1), true));
        }

        if (Tools::getValue('cancelSubscriptionOK')) {
            $this->success[] = $this->module->l('Your subscription has been canceled.', 'subscriptions');
        }
        if (Tools::getValue('cancelSubscriptionNOK')) {
            $this->errors[] = $this->module->l('An error occured while trying to cancel your subscription. Please contact us.', 'subscriptions');
        }

        // Retrieve all customer subscriptions
        $paymentRecordIdList = PaylineToken::getPaymentRecordIdListByIdCustomer($this->context->customer->id);
        $paymentRecordInformations = array();

        if (sizeof($paymentRecordIdList)) {
            // Retrieve information of each payment record
            foreach ($paymentRecordIdList as $paymentRecordId) {
                $idOrderList = PaylineToken::getIdOrderListByPaymentRecordId($paymentRecordId);
                if (sizeof($idOrderList)) {
                    $firstIdOrder = current($idOrderList);
                    if (empty($firstIdOrder)) {
                        continue;
                    }
                    $order = new Order($firstIdOrder);

                    // Retrieve original transaction via token or transaction id
                    $token = PaylineToken::getTokenByIdOrder($order->id);
                    if (!empty($token)) {
                        $originalTransaction = PaylinePaymentGateway::getPaymentInformations($token);
                    } else {
                        $idTransaction = PaylineToken::getIdTransactionByIdOrder($order->id);
                        if (!empty($idTransaction)) {
                            $originalTransaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
                        }
                    }
                    if (empty($originalTransaction)) {
                        continue;
                    }

                    // Retrieve payment record
                    $paymentRecord = PaylinePaymentGateway::getPaymentRecord($originalTransaction['payment']['contractNumber'], $paymentRecordId);
                    if (PaylinePaymentGateway::isValidResponse($paymentRecord, array('02500'))) {
                        $lastBillingRecord = null;
                        $billingRecord = array();
                        if (isset($paymentRecord['billingRecordList']) && isset($paymentRecord['billingRecordList']['billingRecord']) && !empty($paymentRecord['billingRecordList']['billingRecord'])) {
                            $billingRecord = $paymentRecord['billingRecordList']['billingRecord'];
                            $lastBillingRecord = end($billingRecord);
                        }
                        $subscriptionEndDate = (!empty($lastBillingRecord['date']) ? date('Y-m-d', PaylinePaymentGateway::getTimestampFromPaylineDate($lastBillingRecord['date'])) : null);
                        $subscriptionDisableDate = (!empty($paymentRecord['disableDate']) ? date('Y-m-d H:i:s', PaylinePaymentGateway::getTimestampFromPaylineDate($paymentRecord['disableDate'])) : null);

                        // Create related order list
                        $ordersList = array();
                        foreach ($idOrderList as $idOrder) {
                            $linkedOrder = new Order($idOrder);
                            $currentOrderState = new OrderState($linkedOrder->current_state, $this->context->language->id);
                            $ordersList[] = array(
                                'id' => $linkedOrder->id,
                                'amount' => $linkedOrder->getTotalPaid(),
                                'date' => Tools::displayDate($linkedOrder->date_add),
                                'order_state' => $currentOrderState->name,
                                'order_detail_link' => $this->context->link->getPageLink('order-detail', true, null, 'id_order='.(int)$linkedOrder->id),
                            );
                        }

                        $paymentRecordInformations[$paymentRecordId] = array(
                            'billingRecord' => $billingRecord,
                            'subscriptionStartDate' => Tools::displayDate($order->date_add),
                            'subscriptionEndDate' => Tools::displayDate($subscriptionEndDate),
                            'subscriptionAmount' => Tools::displayPrice($order->getTotalPaid()),
                            'subscriptionEnabled' => empty($paymentRecord['isDisabled']),
                            'subscriptionDisableDate' => (!empty($subscriptionDisableDate) ? Tools::displayDate($subscriptionDisableDate, true, true) : ''),
                            'cancelSubscriptionLink' => $this->context->link->getModuleLink('payline', 'subscriptions', array('cancelSubscriptionId' => $paymentRecordId), true),
                            'firstOrder' => $order,
                            'ordersList' => $ordersList,
                        );
                    }
                }
            }

            $this->context->smarty->assign(array(
                'paymentRecordIdList' => $paymentRecordIdList,
                'paymentRecordInformations' => $paymentRecordInformations,
            ));
        } else {
            if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
                $this->warning[] = $this->module->l('You do not have any subscriptions.', 'subscriptions');
            } else {
                $this->errors[] = $this->module->l('You do not have any subscriptions.', 'subscriptions');
            }
        }

        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $this->setTemplate('module:payline/views/templates/front/1.7/subscriptions.tpl');
        } else {
            $this->context->smarty->assign(array(
                'errors' => $this->errors,
                'success' => $this->success,
            ));
            $this->setTemplate('1.6/subscriptions.tpl');
        }
    }
}
