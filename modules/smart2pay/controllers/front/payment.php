<?php
/**
 * 2015 Smart2Pay
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this plugin
 * in the future.
 *
 * @author    Smart2Pay
 * @copyright 2015 Smart2Pay
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
**/
/**
 * Smart2Pay Payment initiation
**/
class smart2paypaymentModuleFrontController extends ModuleFrontController
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
        $cart_currency = new Currency( $cart->id_currency );
        /** @var Smart2Pay $smart2pay_module */
        $smart2pay_module = $this->module;

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $moduleSettings = $smart2pay_module->getSettings();

        $method_id = (int) Tools::getValue( 'method_id', 0 );

        if( empty( $method_id )
         or !($payment_method = $smart2pay_module->method_details_if_available( $method_id )) )
        {
            $smart2pay_module->writeLog( 'Payment method #' . $method_id . ' could not be loaded, or it is not available', 'error' );
            Tools::redirect( 'index.php?controller=order&step=1' ); // Todo - give some feedback to the user
        }

        $smart2pay_module->validateOrder(
            $cart->id,
            $moduleSettings[$smart2pay_module::CONFIG_PREFIX.'NEW_ORDER_STATUS'],
            0,
            $smart2pay_module->displayName.': '.$payment_method['method_details']['display_name'],
            null
        );

        $orderID = Order::getOrderByCartId( $context->cart->id );

        $site_id = $moduleSettings[$smart2pay_module::CONFIG_PREFIX.'SITE_ID'] ? $moduleSettings[$smart2pay_module::CONFIG_PREFIX.'SITE_ID'] : null;

        /**
         *    Surcharge calculation
         */
        $amount_to_pay = number_format( $context->cart->getOrderTotal(), 2, '.', '' );

        $surcharge_percent_amount = 0;
        // Amount in shop currency (base currency)
        $surcharge_amount = 0;
        // Amount in order currency
        $surcharge_order_amount = 0;
        if( (float)$payment_method['method_settings']['surcharge_percent'] != 0 )
            $surcharge_percent_amount = number_format( ( $amount_to_pay * $payment_method['method_settings']['surcharge_percent'] ) / 100, 2, '.', '' );
        if( (float)$payment_method['method_settings']['surcharge_amount'] != 0 )
            $surcharge_amount = number_format( $payment_method['method_settings']['surcharge_amount'], 2, '.', '' );

        if( $surcharge_amount != 0 )
        {
            if( Currency::getDefaultCurrency()->id != $context->cart->id_currency )
                $surcharge_order_amount = number_format( Tools::convertPriceFull( $surcharge_amount, Currency::getDefaultCurrency(), $cart_currency ), 2, '.', '' );
            else
                $surcharge_order_amount = $surcharge_amount;
        }

        $amount_to_pay += $surcharge_percent_amount + $surcharge_order_amount;
        /**
         *    END Surcharge calculation
         */

        $transaction_arr = array();
        $transaction_arr['method_id'] = $method_id;
        $transaction_arr['order_id'] = $orderID;
        if( !empty( $site_id ) )
            $transaction_arr['site_id'] = $site_id;
        $transaction_arr['environment'] = Tools::strtolower( $moduleSettings[$smart2pay_module::CONFIG_PREFIX.'ENV'] );

        $transaction_arr['surcharge_amount'] = $payment_method['method_settings']['surcharge_amount'];
        $transaction_arr['surcharge_percent'] = $payment_method['method_settings']['surcharge_percent'];
        $transaction_arr['surcharge_currency'] = Currency::getDefaultCurrency()->iso_code;

        $transaction_arr['surcharge_order_amount'] = $surcharge_order_amount;
        $transaction_arr['surcharge_order_percent'] = $surcharge_percent_amount;
        $transaction_arr['surcharge_order_currency'] = $cart_currency->iso_code;

        $smart2pay_module->save_transaction( $transaction_arr );

        $skipPaymentPage = 0;
        if( $moduleSettings[$smart2pay_module::CONFIG_PREFIX.'SKIP_PAYMENT_PAGE']
        and !in_array( $method_id, array( $smart2pay_module::PAYM_BANK_TRANSFER, $smart2pay_module::PAYM_MULTIBANCO_SIBS ) ) )
            $skipPaymentPage = 1;

        $moduleSettings['skipPaymentPage'] = $skipPaymentPage;

        if( !empty( $moduleSettings[$smart2pay_module::CONFIG_PREFIX.'SEND_ORDER_NUMBER_AS_PRODUCT_DESCRIPTION'] ) )
            $payment_description = 'Ref. No. '.$orderID;
        else
            $payment_description = $moduleSettings[$smart2pay_module::CONFIG_PREFIX.'CUSTOM_PRODUCT_DESCRIPTION'];

        $paymentData = array(
            'MerchantID'        => $moduleSettings['mid'],
            'MerchantTransactionID' => $orderID,
            'Amount'            => $amount_to_pay * 100,
            'Currency'          => $context->currency->iso_code,
            'ReturnURL'         => $moduleSettings[$smart2pay_module::CONFIG_PREFIX.'RETURN_URL'],
            'IncludeMethodIDs'  => $method_id,
            'CustomerName'      => $context->customer->firstname . ' ' . $context->customer->lastname,
            'CustomerFirstName' => $context->customer->firstname,
            'CustomerLastName'  => $context->customer->lastname,
            'CustomerEmail'     => $context->customer->email,
            'Country'           => $context->country->iso_code,
            'MethodID'          => $method_id,
            'Description'       => $payment_description,
            'SkipHPP'           => (!empty( $moduleSettings[$smart2pay_module::CONFIG_PREFIX.'SKIP_PAYMENT_PAGE'] )?1:0),
            'RedirectInIframe'  => (!empty( $moduleSettings[$smart2pay_module::CONFIG_PREFIX.'REDIRECT_IN_IFRAME'] )?1:0),
            'SkinID'            => $moduleSettings[$smart2pay_module::CONFIG_PREFIX.'SKIN_ID'],
            'SiteID'            => $site_id,
        );

        $notSetPaymentData = array();

        foreach( $paymentData as $key => $value )
        {
            if ( $value === null )
            {
                $notSetPaymentData[$key] = $value;
                unset( $paymentData[$key] );
            }
        }

        $messageToHash = $smart2pay_module->createStringToHash( $paymentData );

        $paymentData['Hash'] = $smart2pay_module->computeHash( $messageToHash, $moduleSettings['signature'] );

        $this->context->smarty->assign( array(
            'paymentData' => $paymentData,
            'messageToHash' => $messageToHash,
            'settings_prefix' => $smart2pay_module::CONFIG_PREFIX,
            'moduleSettings' => $moduleSettings,
            'notSetPaymentData' => $notSetPaymentData,
        ) );

        $this->setTemplate('sendForm.tpl');
    }
}
