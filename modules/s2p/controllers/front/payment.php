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
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();

        $context = $this->context;

        $cart = $this->context->cart;

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $moduleSettings = $this->module->getSettings();

        $paymentMethodID = (int) Tools::getValue('methodID', 0);
        $paymentModule = $this->module->getMethodModule($paymentMethodID);

        if (
            !$paymentModule
            || (!$paymentModule->isMethodAvailable() && !$moduleSettings['s2p_debug_form'])
        ) {
            $this->module->writeLog('Module for method #' . $paymentMethodID . ' could not be loaded, or it is not available', 'error');
            Tools::redirect('index.php?controller=order&step=1'); // Todo - give some feedback to the user
        }

        $this->module->validateOrder(
            $cart->id,
            $moduleSettings['s2p_new_order_status'],
            0,
            $paymentModule->displayName,
            null
        );

        $orderID = Order::getOrderByCartId($context->cart->id);

        $skipPaymentPage = 0;

        if ($moduleSettings['s2p_skip_payment_page']
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
            'ReturnURL'         => $moduleSettings['s2p_return_url'],
            'IncludeMethodIDs'  => $paymentMethodID,
            'CustomerName'      => $context->customer->firstname . ' ' . $context->customer->lastname,
            'CustomerFirstName' => $context->customer->firstname,
            'CustomerLastName'  => $context->customer->lastname,
            'CustomerEmail'     => $context->customer->email,
            'Country'           => $context->country->iso_code,
            'MethodID'          => $paymentMethodID,
            'Description'       => $moduleSettings['s2p_send_order_number_as_product_description'] ? $orderID : $moduleSettings['s2p_custom_product_description'],
            'SkipHPP'           => $moduleSettings['s2p_skip_payment_page'],
            'RedirectInIframe'  => $moduleSettings['s2p_redirect_in_iframe'],
            'SkinID'            => $moduleSettings['s2p_skin_id'],
            'SiteID'            => $moduleSettings['s2p_site_id'] ? $moduleSettings['s2p_site_id'] : null
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
