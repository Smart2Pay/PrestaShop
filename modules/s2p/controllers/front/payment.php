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

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $moduleSettings = $this->module->getSettings();

        $this->module->validateOrder(
            $cart->id,
            $moduleSettings['s2p-new-order-status'],
            0,
            $this->module->displayName,
            null
        );

        $orderID = Order::getOrderByCartId($context->cart->id);
        $paymentMethodID = (int) Tools::getValue('methodID', 0);
        $skipPaymentPage = 0;

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
