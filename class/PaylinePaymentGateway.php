<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

use \Payline\PaylineSDK as PaylineSDK;

class PaylinePaymentGateway
{
    private static $merchantSettings = null;

    /**
     * Get Payline instance
     * @since 2.0.0
     * @return PaylineSDK
     */
    public static function getInstance()
    {
        static $instance = null;

        if ($instance === null) {
            $instance =  new PaylineSDK(
                Configuration::get('PAYLINE_MERCHANT_ID'),
                Configuration::get('PAYLINE_ACCESS_KEY'),
                Configuration::get('PAYLINE_PROXY_HOST'),
                Configuration::get('PAYLINE_PROXY_PORT'),
                Configuration::get('PAYLINE_PROXY_LOGIN'),
                Configuration::get('PAYLINE_PROXY_PASSWORD'),
                self::getEnvMode(),
                _PS_MODULE_DIR_ . 'payline' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR
            );
            // Expose Payline module version for API calls
            $paylineModuleInstance = Module::getInstanceByName('payline');
            $instance->usedBy('PS v' . $paylineModuleInstance->version);
        }

        return $instance;
    }

    /**
     * Get current env mode
     * @since 2.0.0
     * @return string
     */
    public static function getEnvMode()
    {
        return (Configuration::get('PAYLINE_LIVE_MODE') == '1' ? PaylineSDK::ENV_PROD : PaylineSDK::ENV_HOMO);
    }

    /**
     * Get production mode status
     * @since 2.0.0
     * @return bool
     */
    public static function isProductionMode()
    {
        return (self::getEnvMode() == PaylineSDK::ENV_PROD);
    }

    /**
     * Get homologation mode status
     * @since 2.0.0
     * @return bool
     */
    public static function isHomologationMode()
    {
        return (self::getEnvMode() == PaylineSDK::ENV_HOMO);
    }

    /**
     * Check if credentials are correct
     * @since 2.0.0
     * @return bool
     */
    public static function checkCredentials()
    {
        static $result = null;

        if ($result === null) {
            // Get merchant settings
            $merchantSettings = self::getInstance()->getMerchantSettings(array());

            $result = (is_array($merchantSettings) && !empty($merchantSettings['result']) && self::isValidResponse($merchantSettings));
            if ($result) {
                self::$merchantSettings = $merchantSettings;
            }
        }

        return $result;
    }

    /**
     * Get all point of sales (POS) related to the current account
     * @since 2.0.0
     * @return array
     */
    public static function getPointOfSales()
    {
        if (self::checkCredentials() && isset(self::$merchantSettings['listPointOfSell']) && is_array(self::$merchantSettings['listPointOfSell']) && isset(self::$merchantSettings['listPointOfSell']['pointOfSell']) && is_array(self::$merchantSettings['listPointOfSell']['pointOfSell'])) {
            return self::$merchantSettings['listPointOfSell']['pointOfSell'];
        }

        return array();
    }

    /**
     * Get all contracts related to a specific POS
     * @since 2.0.0
     * @param string $posLabel
     * @param array $enabledContracts
     * @return array
     */
    public static function getContractsByPosLabel($posLabel, $enabledContracts = array())
    {
        $posList = self::getPointOfSales();
        foreach ($posList as $pos) {
            if ($pos['label'] == $posLabel && isset($pos['contracts']) && is_array($pos['contracts']) && isset($pos['contracts']['contract']) && is_array($pos['contracts']['contract'])) {
                // Retrieve contracts and sort them
                $finalContractsList = array();
                $disabledContracts = array();
                $contractsList = $pos['contracts']['contract'];

                // Assign logo for each contract
                self::assignLogoToContracts($contractsList);

                // Assign "enabled attriburte
                foreach ($contractsList as &$contract) {
                    $contractId = $contract['cardType'] . '-' . $contract['contractNumber'];
                    $contract['enabled'] = (in_array($contractId, $enabledContracts));
                    if (!$contract['enabled']) {
                        $disabledContracts[] = $contract;
                    }
                }
                // Sort contracts, enabled first
                foreach ($enabledContracts as $enabledContractId) {
                    foreach ($contractsList as &$contract) {
                        $contractId = $contract['cardType'] . '-' . $contract['contractNumber'];
                        if ($contractId == $enabledContractId) {
                            $finalContractsList[] = $contract;
                            break;
                        }
                    }
                }

                $finalContractsList = array_merge($finalContractsList, $disabledContracts);

                return $finalContractsList;
            }
        }

        return array();
    }

    /**
     * Assign logo image to each contract
     * @since 2.0.0
     * @param array $contracts
     * @return array
     */
    private static function assignLogoToContracts(&$contractsList)
    {
        $logoFileByCardType = array(
            '3XCB' => '3xcb.png',
            '3XONEY' => '3xoney.png',
            '1EURO' => '1euro.png',
            '1EURO.COM' => '1euro.png',
            'AURORE' => 'aurore.png',
            '3XONEY_SF' => '3xfacilipaycb.png',
            '4XONEY' => '4xoney.png',
            '4XONEY_SF' => '4xfacilipaycb.png',
            'AMEX' => 'amex.png',
            'AMEX-ONE CLICK' => 'amex_one_clic.png',
            'AMEX-REC BILLING' => 'amex_rec.png',
            'ANCV' => 'ancv.png',
            'BC/MC' => 'bcmc.png',
            'BCMC' => 'bcmc.png',
            'BUYSTER' => 'buyster.png',
            'CASINO' => 'casino.png',
            'CB/VISA/MASTERCARD' => 'cb_visa_mastercard.png',
            'CB/MC PASS' => 'cbpass.png',
            'CB' => 'cb_visa_mastercard.png',
            'CDGP' => 'cdgp.png',
            'COFINOGA' => 'cofinoga.png',
            'CYRILLUS' => 'cyrillus.png',
            'DINERS' => 'diners.png',
            'DISCOVER' => 'discover.png',
            'ELV' => 'elv.png',
            'FNAC' => 'fnac.png',
            'GIROPAY' => 'giropay.png',
            'IDEAL' => 'ideal.png',
            'ILLICADO' => 'illicado.png',
            'JCB' => 'jcb.png',
            'KANGOUROU' => 'kangourou.png',
            'KLARNA' => 'klarna.png',
            'LEETCHI' => 'leetchi.png',
            'LYDIA' => 'lydia.png',
            'MAESTRO' => 'maestro.png',
            'MANDARINE' => 'mandarine.png',
            'MASTERPASS' => 'masterpass.png',
            'MAXICHEQUE' => 'maxicheque.png',
            'VISA/MASTERCARD' => 'visa_mastercard.png',
            'MCVISA' => 'visa_mastercard.png',
            'MONEYCLIC' => 'moneyclic.png',
            'EMONEO' => 'moneo.png',
            'NEOSURF' => 'neosurf.png',
            'NETELLER' => 'neteller.png',
            'OKSHOPPING' => 'okshopping.png',
            'PASS' => 'pass.png',
            'CBPASS' => 'pass.png',
            'PAYFAIR' => 'payfair.png',
            'PAYLIB' => 'paylib.png',
            'PAYPAL' => 'paypal.png',
            'PAYSAFECARD' => 'paysafecard.png',
            'POSTFINANCE' => 'postfinance.png',
            'TICKETSURF' => 'premium.png',
            'PRINTEMPS' => 'printemps.png',
            'PRZELEWY24' => 'przelewy24.png',
            'SACARTE' => 'sacarte.png',
            'SDD' => 'sdd.png',
            'SKRILL(MONEYBOOKERS)' => 'skrill.png',
            'SOFORT' => 'sofort.png',
            'SWITCH' => 'switch.png',
            'SURCOUF' => 'surcouf.png',
            'TOTALGR' => 'totalgr.png',
            'UKASH' => 'ukash.png',
            'WEXPAY' => 'wexpay.png',
            'YANDEX' => 'yandex.png',
            'YAPITAL' => 'yapital.png',
            'VISAPREPAID' => 'visaprepaid.png',
            'FCB3X' => 'fcb3x.png',
            'FCB4X' => 'fcb4x.png',
            'PRESTO' => 'presto.png'
        );
        foreach ($contractsList as &$contract) {
            if (!empty($contract['cardType']) && !empty($logoFileByCardType[$contract['cardType']])) {
                $contract['logo'] = $logoFileByCardType[$contract['cardType']];
            }
        }
    }

    /**
     * Create a request for a web payment
     * @since 2.0.0
     * @param Context $context
     * @return array
     */
    public static function createPaymentRequest(Context $context)
    {
        $invoiceAddress = new Address($context->cart->id_address_invoice);
        $deliveryAddress = new Address($context->cart->id_address_delivery);
        $invoiceCountry = new Country($invoiceAddress->id_country);
        $deliveryCountry = new Country($deliveryAddress->id_country);

        $orderTotal = $context->cart->getOrderTotal(false);
        $orderTotalWt = $context->cart->getOrderTotal();
        $orderTaxes = ($orderTotalWt - $orderTotal);

        // Get Payline instance
        $instance = self::getInstance();

        // Get custom payment page code
        $customPaymentPageCode = Configuration::get('PAYLINE_WEB_CASH_CUSTOM_CODE');
        if (empty($customPaymentPageCode)) {
            $customPaymentPageCode = null;
        }

        // Get contracts
        $contracts = self::getEnabledContracts(true);
        $secondContracts = self::getFallbackEnabledContracts(true);
        // Use first enabled contract
        $contractNumber = current($contracts);

        $params = array(
            'payment' => array(
                'amount' => round($orderTotalWt * 100),
                'currency' => $context->currency->iso_code_num,
                // TODO Switch depending on payment method
                'mode' => 'CPT',
                'action' => Configuration::get('PAYLINE_WEB_CASH_ACTION'),
                'contractNumber' => $contractNumber,
            ),
            'order' => array(
                'ref' => 'cart' . (int)$context->cart->id . (!empty($context->cookie->pl_try) ? 'try' . $context->cookie->pl_try : ''),
                'country' => $invoiceCountry->iso_code,
                'amount' => round($orderTotal * 100),
                'taxes' => round($orderTaxes * 100),
                'date' => date('d/m/Y H:i'),
                'currency' => $context->currency->iso_code_num,
                // TODO
                // 'deliveryMode' => 4,
                // 'deliveryTime' => 1,
                // 'deliveryExpectedDelay' => 7,
                // 'deliveryExpectedDate' => date('d/m/Y', strtotime('now + 7 day')),
            ),
            'contracts' => (sizeof($contracts) ? $contracts : null),
            'secondContracts' => (sizeof($secondContracts) ? $secondContracts : null),
            'buyer' => array(
                'title' => null,
                'lastName' => $context->customer->lastname,
                'firstName' => $context->customer->firstname,
                'email' => $context->customer->email,
                'customerId' => $context->customer->id,
                'mobilePhone' => null,
                'birthDate' => (Validate::isDate($context->customer->birthday) ? $context->customer->birthday : null),
                'ip' => Tools::getRemoteAddr(),
                'accountCreateDate' => (strtotime($context->customer->date_add) ? date('d/m/y', strtotime($context->customer->date_add)) : null),
                'accountOrderCount' => (int)Order::getCustomerNbOrders($context->customer->id),
            ),
            'shippingAddress' => self::formatAddressForPaymentRequest($deliveryAddress),
            'billingAddress' => self::formatAddressForPaymentRequest($invoiceAddress),
            // URL
            'notificationURL' => $context->link->getModuleLink('payline', 'notification', array(), true),
            'returnURL' => $context->link->getModuleLink('payline', 'validation', array(), true),
            'cancelURL' => $context->link->getPageLink('order'),
        );
        // Set mobile phone for buyer (check shipping first, then billing)
        if (!empty($params['shippingAddress']['mobilePhone'])) {
            $params['buyer']['mobilePhone'] = $params['shippingAddress']['mobilePhone'];
        } elseif (!empty($params['billingAddress']['mobilePhone'])) {
            $params['buyer']['mobilePhone'] = $params['billingAddress']['mobilePhone'];
        }
        // Set buyer gender
        $customerGender = new Gender($context->customer->id_gender);
        if (Validate::isLoadedObject($customerGender)) {
            if ($customerGender->type == 0) {
                // Male
                $params['buyer']['title'] = 4;
            } elseif ($customerGender->type == 1) {
                $params['buyer']['title'] = 1;
            }
        }
        // Customization
        if (!empty($customPaymentPageCode)) {
            $params['customPaymentPageCode'] = $customPaymentPageCode;
        }

        // Add order details infos
        foreach ($context->cart->getProducts() as $cartProduct) {
            $instance->addOrderDetail(array(
                'ref' => (string)$cartProduct['reference'],
                'price' => round((float)$cartProduct['price'] * 100),
                'quantity' => (int)$cartProduct['cart_quantity'],
                'comment' => (string)$cartProduct['name'],
                'category' => 'Cat. ' . (int)$cartProduct['id_category_default'],
                'brand' => (int)$cartProduct['id_manufacturer'],
                'taxRate' => round((float)$cartProduct['rate'] * 100),
            ));
        }

        // Add private data to the payment request
        $instance->addPrivateData(array('key' => 'id_cart', 'value' => (int)$context->cart->id));
        $instance->addPrivateData(array('key' => 'id_customer', 'value' => (int)$context->customer->id));
        $instance->addPrivateData(array('key' => 'secure_key', 'value' => (string)$context->cart->secure_key));
        $result = $instance->doWebPayment($params);

        if (self::isValidResponse($result)) {
            return $result;
        }
    }

    /**
     * Format address for Payline API request
     * @since 2.0.0
     * @param Address $address
     * @return array
     */
    private static function formatAddressForPaymentRequest(Address $address)
    {
        $stateIsoCode = '';
        $countryIsoCode = '';
        if (isset($address->id_state) && !empty($address->id_state)) {
            $addressState = new State($address->id_state);
            if (Validate::isLoadedObject($addressState)) {
                // ISO of state code is required by Paypal
                $stateIsoCode = $addressState->iso_code;
            }
        }
        if (isset($address->id_country) && !empty($address->id_country)) {
            $addressCountry = new Country($address->id_country);
            if (Validate::isLoadedObject($addressCountry)) {
                $countryIsoCode = $addressCountry->iso_code;
            }
        }

        return array(
            'name' => $address->alias,
            'firstName' => $address->firstname,
            'lastName' => $address->lastname,
            'street1' => $address->address1,
            'street2' => $address->address2,
            'cityName' => $address->city,
            'zipCode' => $address->postcode,
            'country' => $countryIsoCode,
            'state' => $stateIsoCode,
            'phone' => str_replace(array(' ', '.', '(', ')', '-'), '', $address->phone),
            'mobilePhone' => str_replace(array(' ', '.', '(', ')', '-'), '', $address->phone_mobile),
        );
    }

    /**
     * Parse list of private data and create key/value association instead of classic key/value list
     * @since 2.0.0
     * @param array $result
     * @return array
     */
    public static function parsePrivateDataList(&$result)
    {
        $result['formatedPrivateDataList'] = array();

        if (is_array($result) && isset($result['privateDataList']) && is_array($result['privateDataList']) && isset($result['privateDataList']['privateData']) && is_array($result['privateDataList']['privateData'])) {
            if (isset($result['privateDataList']) && is_array($result['privateDataList']) && isset($result['privateDataList']['privateData']) && is_array($result['privateDataList']['privateData'])) {
                foreach ($result['privateDataList']['privateData'] as $k => $v) {
                    if (is_array($v) && isset($v['key']) && isset($v['value'])) {
                        $result['formatedPrivateDataList'][$v['key']] = $v['value'];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Return payment informations
     * @since 2.0.0
     * @param string $token
     * @return array
     */
    public static function getPaymentInformations($token)
    {
        $instance = self::getInstance();
        $params = array(
            'token' => $token,
        );
        $result = $instance->getWebPaymentDetails($params);
        // Parse list of private data and create key/value association instead of classic key/value list
        self::parsePrivateDataList($result);

        return $result;
    }

    /**
     * Return transaction informations
     * @since 2.0.0
     * @param string $transactionId
     * @return array
     */
    public static function getTransactionInformations($transactionId)
    {
        $instance = self::getInstance();
        $params = array(
            'transactionId' => $transactionId,
            'transactionHistory' => 'Y',
            'archiveSearch' => null,
            'startDate' => null,
            'endDate' => null,
        );
        $result = $instance->getTransactionDetails($params);

        if (self::isValidResponse($result)) {
            // Sort associatedTransactionsList by date, latest first (not done by the API)
            if (isset($result['associatedTransactionsList']) && isset($result['associatedTransactionsList']['associatedTransactions']) && is_array($result['associatedTransactionsList']['associatedTransactions'])) {
                uasort($result['associatedTransactionsList']['associatedTransactions'], function ($a, $b) {
                    if (self::getTimestampFromPaylineDate($a['date']) == self::getTimestampFromPaylineDate($b['date'])) {
                        return 0;
                    } elseif (self::getTimestampFromPaylineDate($a['date']) > self::getTimestampFromPaylineDate($b['date'])) {
                        return -1;
                    } else {
                        return 1;
                    }
                });
            }
            // Sort statusHistoryList by date, latest first (not done by the API)
            if (isset($result['statusHistoryList']) && isset($result['statusHistoryList']['statusHistory']) && is_array($result['statusHistoryList']['statusHistory'])) {
                uasort($result['statusHistoryList']['statusHistory'], function ($a, $b) {
                    if (self::getTimestampFromPaylineDate($a['date']) == self::getTimestampFromPaylineDate($b['date'])) {
                        return 0;
                    } elseif (self::getTimestampFromPaylineDate($a['date']) > self::getTimestampFromPaylineDate($b['date'])) {
                        return -1;
                    } else {
                        return 1;
                    }
                });
            }
            // Parse list of private data and create key/value association instead of classic key/value list
            self::parsePrivateDataList($result);
        }

        return $result;
    }

    /**
     * Do capture on a specific transaction, after an old capture (7 days), we will use doReAuthorization instead of doCapture
     * @since 2.0.0
     * @param string $transactionId
     * @param string $paymentMode (default CPT)
     * @param string $comment
     * @return array
     */
    public static function captureTransaction($transactionId, $paymentMode = 'CPT', $comment = null)
    {
        $instance = self::getInstance();
        $transaction = self::getTransactionInformations($transactionId);
        $transactionTime = self::getTimestampFromPaylineDate($transaction['transaction']['date']);

        $params = array(
            'transactionID' => $transactionId,
            'payment' => array(
                'amount' => $transaction['payment']['amount'],
                'currency' => $transaction['payment']['currency'],
                'mode' => $paymentMode,
                'contractNumber' => $transaction['payment']['contractNumber'],
            ),
            'sequenceNumber' => null,
            'comment' => $comment,
        );

        if (time() >= strtotime('+7 day', $transactionTime)) {
            // Do re-autorization
            $params['payment']['action'] = 101;
            $params['order'] = $transaction['order'];
            $result = $instance->doReAuthorization($params);
        } else {
            // Do capture
            $params['payment']['action'] = 201;
            $result = $instance->doCapture($params);
        }

        return $result;
    }

    /**
     * Do refund on a specific transaction
     * @since 2.0.0
     * @param string $transactionId
     * @param float $specificAmount
     * @param string $comment
     * @return array
     */
    public static function refundTransaction($transactionId, $specificAmount = null, $comment = null)
    {
        $instance = self::getInstance();
        $transaction = self::getTransactionInformations($transactionId);

        $params = array(
            'transactionID' => $transactionId,
            'payment' => array(
                'amount' => (isset($specificAmount) ? round($specificAmount * 100) : $transaction['payment']['amount']),
                'currency' => $transaction['payment']['currency'],
                'mode' => 'CPT',
                'action' => 421,
                'contractNumber' => $transaction['payment']['contractNumber'],
            ),
            'sequenceNumber' => null,
            'comment' => $comment,
        );

        // Do refund
        $result = $instance->doRefund($params);

        return $result;
    }

    /**
     * Do reset on a specific transaction
     * @since 2.0.0
     * @param string $transactionId
     * @param string $comment
     * @return array
     */
    public static function resetTransaction($transactionId, $comment = null)
    {
        $instance = self::getInstance();
        $transaction = self::getTransactionInformations($transactionId);

        $params = array(
            'transactionID' => $transactionId,
            'comment' => $comment,
        );

        // Do reset
        $result = $instance->doReset($params);

        return $result;
    }

    /**
     * Check if an API response is valid or not (check code 00000)
     * @since 2.0.0
     * @param array $result
     * @param array $validFallbackCodeList
     * @return bool
     */
    public static function isValidResponse($result, $validFallbackCodeList = array())
    {
        $validClassic = (is_array($result) && isset($result['result']['code']) && $result['result']['code'] == '00000');
        $validFallback = (is_array($result) && isset($result['result']['code']) && in_array($result['result']['code'], $validFallbackCodeList));

        return ($validClassic || $validFallback);
    }

    /**
     * Retrieve error from Payline API
     * @since 2.0.0
     * @param array $result
     * @return array
     */
    public static function getErrorResponse($result)
    {
        if (!self::isValidResponse($result)) {
            return array(
                'code' => $result['result']['code'],
                'shortMessage' => $result['result']['shortMessage'],
                'longMessage' => $result['result']['longMessage'],
            );
        }
    }

    /**
     * Parse Payline date to timestamp
     * @since 2.0.0
     * @param string $date
     * @return int
     */
    public static function getTimestampFromPaylineDate($date)
    {
        list($day, $month, $year) = explode('/', $date);
        list($year, $hour) = explode(' ', $year);
        $timestamp = explode(':', $hour);
        if (sizeof($timestamp) == 3) {
            list($hour, $minutes, $seconds) = explode(':', $hour);
        } else {
            list($hour, $minutes) = explode(':', $hour);
            $seconds = 0;
        }

        return mktime((int)$hour, (int)$minutes, (int)$seconds, (int)$month, (int)$day, (int)$year);
    }

    /**
     * Return all needed assets regarding the current mode
     * @since 2.0.0
     * @return array
     */
    public static function getAssetsToRegister()
    {
        $assetsList = array(
            'js' => array(),
            'css' => array(),
        );
        if (self::isHomologationMode()) {
            $assetsList['js'][] = PaylineSDK::HOMO_WDGT_JS;
            $assetsList['css'][] = PaylineSDK::HOMO_WDGT_CSS;
        } else {
            $assetsList['js'][] = PaylineSDK::PROD_WDGT_JS;
            $assetsList['css'][] = PaylineSDK::PROD_WDGT_CSS;
        }

        return $assetsList;
    }

    /**
     * Extract contract number from contract id
     * @since 2.0.0
     * @param string $contractId
     * @return string
     */
    private static function extractContractNumber($contractId)
    {
        $contractId = explode('-', $contractId);
        return $contractId[1];
    }

    /**
     * Retrieve list of enabled contracts
     * @since 2.0.0
     * @param bool $contractNumberOnly
     * @return array
     */
    public static function getEnabledContracts($contractNumberOnly = false)
    {
        $enabledContractsList = array();
        $enabledContracts = Configuration::get('PAYLINE_CONTRACTS');
        if (!empty($enabledContracts) && json_decode($enabledContracts) !== false) {
            $enabledContractsList = json_decode($enabledContracts);
            if ($contractNumberOnly && is_array($enabledContractsList)) {
                $enabledContractsList = array_map('PaylinePaymentGateway::extractContractNumber', $enabledContractsList);
            }
        }
        return $enabledContractsList;
    }

    /**
     * Retrieve list of enabled contracts (fallback method)
     * @since 2.0.0
     * @param bool $contractNumberOnly
     * @return array
     */
    public static function getFallbackEnabledContracts($contractNumberOnly = false)
    {
        $enabledContractsList = array();
        $enabledContracts = Configuration::get('PAYLINE_ALT_CONTRACTS');
        if (!empty($enabledContracts) && json_decode($enabledContracts) !== false) {
            $enabledContractsList = json_decode($enabledContracts);
            if ($contractNumberOnly && is_array($enabledContractsList)) {
                $enabledContractsList = array_map('PaylinePaymentGateway::extractContractNumber', $enabledContractsList);
            }
        }
        return $enabledContractsList;
    }
}
