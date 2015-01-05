<?php

class S2pPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $context = $this->context;

        $cart = $this->context->cart;

//        var_dump($cart);
//        die('zz');
//        if (!$this->module->checkCurrency($cart))
//        Tools::redirect('index.php?controller=order');
//        echo "<pre>";
//        echo "<p><b>s2p-env</b></p>";
//
//        print_r(Configuration::get('s2p-env'));
//        print_r($this->module->name);
//
//        echo "<p><b>Currency</b></p>";
//        print_r($this->module->getCurrency((int)$cart->id_currency));
//        print_r($this->context->currency->iso_code);
//        print_r($this->context->currency);
//        echo "<p><b>Country</b></p>";
//        print_r($this->context->country);
//        echo "<p><b>Customer</b></p>";
//        print_r($this->context->customer);
//        echo "<p><b>Cart</b></p>";
//        print_r($this->context->cart);
//        echo "</pre>";

        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
            Tools::redirect('index.php?controller=order&step=1');

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module)
            if ($module['name'] == 'bankwire')
            {
                $authorized = true;
                break;
            }
        if (!$authorized)
            die($this->module->l('This payment method is not available.', 'validation'));

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $mailVars = array(
            '{bankwire_owner}' => Configuration::get('BANK_WIRE_OWNER'),
            '{bankwire_details}' => nl2br(Configuration::get('BANK_WIRE_DETAILS')),
            '{bankwire_address}' => nl2br(Configuration::get('BANK_WIRE_ADDRESS'))
        );

//        $this->module->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $total, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);

//        var_dump($this->module->getSettings());

        $orderID = $context->cart->id;
        $paymentMethodID = (int) Tools::getValue('methodID', 0);
        $skipPaymentPage = 0;

        $moduleSettings = $this->module->getSettings();

        if ($moduleSettings['s2p-skip-payment-page']
            && !in_array($paymentMethodID, array(1, 20))
        ) {
            $skipPaymentPage = 1;
        }

        $moduleSettings['skipPaymentPage'] = $skipPaymentPage;

        $paymentData = array(
            'MerchantID'        => $moduleSettings['mid'],
            'MerchantTransactionID' => $orderID,
            'Amount'            => round($context->cart->getOrderTotal() * 100),
            'Currency'          => $context->currency->iso_code,
            'ReturnURL'         => $moduleSettings['s2p-return-url'],
            'IncludeMethodIDs'  => $paymentMethodID,
            'CustomerName'      => $context->customer->firstname . ' ' . $context->customer->lastname,
            'CustomerFirstName' => $context->customer->firstname,
            'CustomerLastName'  => $context->customer->lastname,
            'CustomerEmail'     => $context->customer->email,
            'Country'           => $context->country->iso_code,
            'MethodID'          => $paymentMethodID,
            'Description'       => $moduleSettings['s2p-send-order-number-as-product-description'] ? $orderID : $moduleSettings['s2p-custom-product-description'],
            'SkipHPP'           => $moduleSettings['s2p-skip-payment-page'],
            'RedirectInIframe'  => $moduleSettings['s2p-redirect-in-iframe'],
            'SkinID'            => $moduleSettings['s2p-skin-id'],
            'SiteID'            => $moduleSettings['s2p-site-id'] ? $moduleSettings['s2p-site-id'] : null
        );

        $notSetPaymentData = array();

        foreach ($paymentData as $key => $value) {
            if ( $value === null) {
                $notSetPaymentData[$key] = $value;
                unset($paymentData[$key]);
            }
        }

        $messageToHash = $this->module->createStringToHash($paymentData);

        $paymentData['Hash'] = $this->module->computeHash($messageToHash, $moduleSettings['signature']);

        $this->context->smarty->assign(array(
            'paymentData' => $paymentData,
            'messageToHash' => $messageToHash,
            'moduleSettings' => $moduleSettings,
            'notSetPaymentData' => $notSetPaymentData
        ));

        $this->setTemplate('sendForm.tpl');
    }
}
