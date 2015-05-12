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
 * Smart2Pay Payment notification script
**/
class Smart2payreplyHandlerModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /** @var Smart2pay $module */
    public $module;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $this->display_column_left = false;

        /** @var Smart2pay $s2p_module */
        $s2p_module = $this->module;

        $s2p_module->writeLog( '>>> START HANDLE RESPONSE :::' );

        $moduleSettings = $s2p_module->getSettings();

        if( !($request_arr = $this->parseInput())
         or !is_array( $request_arr )
         or !($request_arr = self::normalize_request( $request_arr )) )
        {
            $s2p_module->writeLog( 'Couldn\'t obtain parameters from request.', array( 'type' => 'error' ) );
            die();
        }

        try
        {
            $recomposedHashString = $this->recomposeHashString() . $moduleSettings['signature'];

            $s2p_module->writeLog( 'NotificationRecevied: "' . $this->getRawInput() . '"', array( 'type' => 'info', 'order_id' => (!empty( $request_arr['MerchantTransactionID'] )?$request_arr['MerchantTransactionID']:0) ) );

            /*
             * Message is intact
             *
             */
            if( $s2p_module->computeSHA256Hash( $recomposedHashString ) != $request_arr['Hash'] )
                $s2p_module->writeLog( 'Hashes do not match (received: ' . $request_arr['Hash'] . ') vs (recomposed: ' . $s2p_module->computeSHA256Hash( $recomposedHashString ) . ')', array( 'type' => 'warning' ) );

            elseif( empty( $request_arr['MerchantTransactionID'] ) )
                $s2p_module->writeLog( 'Unknown order id in request.', array( 'type' => 'error' ) );

            elseif( !($smart2pay_transaction_arr = $s2p_module->get_transaction_by_order_id( $request_arr['MerchantTransactionID'] )) )
                $s2p_module->writeLog( 'Order id ['.$request_arr['MerchantTransactionID'].'] not in transactions table.', array( 'type' => 'error', 'order_id' => $request_arr['MerchantTransactionID'] ) );

            else
            {
                $s2p_module->writeLog( 'Hashes match', array( 'type' => 'info', 'order_id' => $request_arr['MerchantTransactionID'] ) );

                $order = new Order( $request_arr['MerchantTransactionID'] );
                $customer = new Customer( $order->id_customer );
                $currency = new Currency( $order->id_currency );

                if( !Validate::isLoadedObject( $order ) )
                    throw new Exception( 'Invalid order ['.$request_arr['MerchantTransactionID'].']' );

                /*
                 * Check status ID
                 *
                 */
                $request_arr['StatusID'] = (int)$request_arr['StatusID'];
                switch( $request_arr['StatusID'] )
                {
                    // Status = open
                    case $s2p_module::S2P_STATUS_OPEN:
                        if( !empty( $smart2pay_transaction_arr['method_id'] )
                        and in_array( $smart2pay_transaction_arr['method_id'], array( $s2p_module::PAYM_BANK_TRANSFER, $s2p_module::PAYM_MULTIBANCO_SIBS ) )
                        and !empty( $moduleSettings[$s2p_module::CONFIG_PREFIX.'SEND_PAYMENT_INSTRUCTIONS'] ) )
                        {
                            $info_fields = $s2p_module::defaultTransactionLoggerExtraParams();
                            $template_vars = array();
                            foreach( $info_fields as $key => $def_val )
                            {
                                if( array_key_exists( $key, $request_arr ) )
                                    $template_vars['{'.$key.'}'] = $request_arr[$key];
                                else
                                    $template_vars['{'.$key.'}'] = $def_val;
                            }

                            $template_vars['{name}'] = Tools::safeOutput( $customer->firstname );

                            $template_vars['{OrderReference}'] = Tools::safeOutput( $order->reference );
                            $template_vars['{OrderDate}'] = Tools::safeOutput( Tools::displayDate( $order->date_add, null, true ) );
                            $template_vars['{OrderPayment}'] = Tools::safeOutput( $order->payment );

                            if( $smart2pay_transaction_arr['method_id'] == $s2p_module::PAYM_BANK_TRANSFER )
                                $template = 'instructions_bank_transfer';
                            elseif( $smart2pay_transaction_arr['method_id'] == $s2p_module::PAYM_MULTIBANCO_SIBS )
                                $template = 'instructions_multibanco_sibs';

                            if( !empty( $template ) )
                            {
                                Mail::Send(
                                    (int) $order->id_lang,
                                    $template,
                                    sprintf( Mail::l( 'Payment instructions for order %1$s', $order->id_lang ), $order->reference ),
                                    $template_vars,

                                    // to
                                    $customer->email,
                                    $customer->firstname . ' ' . $customer->lastname,

                                    // from
                                    null, null,

                                    // attachment
                                    null,

                                    // mode_smtp
                                    null,

                                    // template_path
                                    realpath( dirname( __FILE__ ) . '/../../mails/' ).'/',

                                    // die
                                    false,

                                    // id_shop
                                    $order->id_shop,

                                    // bcc
                                    null
                                );
                            }
                        }
                    break;

                    // Status = success
                    case $s2p_module::S2P_STATUS_SUCCESS:
                        /*
                         * Check amount  and currency
                         */
                        $initialOrderAmount = $orderAmount = number_format( $order->getOrdersTotalPaid(), 2, '.', '' );
                        $orderCurrency = $currency->iso_code;

                        $surcharge_amount = 0;
                        // Add surcharge if we have something...
                        if( (float)$smart2pay_transaction_arr['surcharge_order_percent'] != 0 )
                            $surcharge_amount += (float)$smart2pay_transaction_arr['surcharge_order_percent'];
                        if( (float)$smart2pay_transaction_arr['surcharge_order_amount'] != 0 )
                            $surcharge_amount += (float)$smart2pay_transaction_arr['surcharge_order_amount'];

                        $orderAmount += $surcharge_amount;

                        if( !empty( $moduleSettings[$s2p_module::CONFIG_PREFIX.'ALTER_ORDER_ON_SURCHARGE'] ) )
                            $orderAmount_check = number_format( $initialOrderAmount * 100, 0, '.', '' );
                        else
                            $orderAmount_check = number_format( $orderAmount * 100, 0, '.', '' );

                        if( strcmp( $orderAmount_check, $request_arr['Amount'] ) != 0
                         or $orderCurrency != $request_arr['Currency'] )
                            $s2p_module->writeLog( 'Smart2Pay :: notification has different amount[' . $orderAmount_check . '/' . $request_arr['Amount'] . '] '.
                                                     ' and/or currency [' . $orderCurrency . '/' . $request_arr['Currency'] . ']. Please contact support@smart2pay.com.', array( 'type' => 'error', 'order_id' => $order->id ) );

                        elseif( empty( $request_arr['MethodID'] )
                             or !($method_details = $s2p_module->get_method_details( $request_arr['MethodID'] )) )
                            $s2p_module->writeLog( 'Smart2Pay :: Couldn\'t get method details ['.$request_arr['MethodID'].']', array( 'type' => 'error', 'order_id' => $order->id ) );

                        else
                        {
                            $orderAmount = number_format( $orderAmount_check / 100, 2, '.','' );

                            $s2p_module->writeLog( 'Order ['.$order->id.'] has been paid', array( 'order_id' => $order->id ) );

                            $order_only_amount = $initialOrderAmount;
                            if( !empty( $moduleSettings[$s2p_module::CONFIG_PREFIX.'ALTER_ORDER_ON_SURCHARGE'] )
                            and $surcharge_amount != 0 )
                                $order_only_amount -= $surcharge_amount;

                            $order->addOrderPayment(
                                $order_only_amount,
                                (!empty( $method_details['display_name'] )?$s2p_module->displayName.': '.$method_details['display_name']:$s2p_module->displayName),
                                $request_arr['PaymentID'],
                                $currency
                            );

                            if( $surcharge_amount != 0 )
                            {
                                $order->addOrderPayment(
                                    $surcharge_amount,
                                    $s2p_module->l( 'Payment Surcharge' ),
                                    $request_arr['PaymentID'],
                                    $currency
                                );
                            }

                            $s2p_module->changeOrderStatus(
                                $order,
                                $moduleSettings[$s2p_module::CONFIG_PREFIX.'ORDER_STATUS_ON_SUCCESS'],
                                false
                            );

                            if( !empty( $moduleSettings[$s2p_module::CONFIG_PREFIX.'NOTIFY_CUSTOMER_BY_EMAIL'] ) )
                            {
                                $template_vars = array();

                                $template_vars['{name}'] = Tools::safeOutput( $customer->firstname );

                                $template_vars['{OrderReference}'] = Tools::safeOutput( $order->reference );
                                $template_vars['{OrderDate}'] = Tools::safeOutput( Tools::displayDate( $order->date_add, null, true ) );
                                $template_vars['{OrderPayment}'] = Tools::safeOutput( $order->payment );

                                $template_vars['{TotalPaid}'] = Tools::displayPrice( $orderAmount, $currency );

                                // Send payment confirmation email...
                                Mail::Send(
                                    (int) $order->id_lang,
                                    'payment_confirmation',
                                    sprintf( Mail::l( 'Payment confirmation for order %1$s', $order->id_lang ), $order->reference ),
                                    $template_vars,

                                    // to
                                    $customer->email,
                                    $customer->firstname . ' ' . $customer->lastname,

                                    // from
                                    null, null,

                                    // attachment
                                    null,

                                    // mode_smtp
                                    null,

                                    // template_path
                                    realpath( dirname( __FILE__ ) . '/../../mails/' ).'/',

                                    // die
                                    false,

                                    // id_shop
                                    $order->id_shop,

                                    // bcc
                                    null
                                );
                            }

                            if( !empty( $moduleSettings[$s2p_module::CONFIG_PREFIX.'CREATE_INVOICE_ON_SUCCESS'] ) )
                                $order->setInvoice( true );

                            /*
                             * Todo - check framework's order shipment
                             *
                            if ($payMethod->method_config['auto_ship']) {
                                if ($order->canShip()) {
                                    $itemQty = $order->getItemsCollection()->count();
                                    $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($itemQty);
                                    $shipment = new Mage_Sales_Model_Order_Shipment_Api();
                                    $shipmentId = $shipment->create($order->getIncrementId());
                                    $order->addStatusHistoryComment('Smart2Pay :: order has been automatically shipped.', $payMethod->method_config['order_status_on_2']);
                                } else {
                                    $s2p_module->writeLog('Order can not be shipped', 'warning');
                                }
                            }
                            */

                        }
                    break;

                    // Status = canceled
                    case $s2p_module::S2P_STATUS_CANCELLED:
                        $s2p_module->writeLog( 'Payment canceled', array( 'type' => 'info', 'order_id' => $order->id ) );
                        $s2p_module->changeOrderStatus( $order, $moduleSettings[$s2p_module::CONFIG_PREFIX.'ORDER_STATUS_ON_CANCEL'] );
                        // There is no way to cancel an order other but changing it's status to canceled
                        // What we do is not changing order status to canceled, but to a user set one, instead
                    break;

                    // Status = failed
                    case $s2p_module::S2P_STATUS_FAILED:
                        $s2p_module->writeLog( 'Payment failed', array( 'type' => 'info', 'order_id' => $order->id ) );
                        $s2p_module->changeOrderStatus( $order, $moduleSettings[$s2p_module::CONFIG_PREFIX.'ORDER_STATUS_ON_FAIL'] );
                    break;

                    // Status = expired
                    case $s2p_module::S2P_STATUS_EXPIRED:
                        $s2p_module->writeLog( 'Payment expired', array( 'type' => 'info', 'order_id' => $order->id ) );
                        $s2p_module->changeOrderStatus($order, $moduleSettings[$s2p_module::CONFIG_PREFIX.'ORDER_STATUS_ON_EXPIRE']);
                    break;

                    default:
                        $s2p_module->writeLog( 'Payment status unknown', array( 'type' => 'error', 'order_id' => $order->id ) );
                    break;
                }

                $s2p_transaction_arr = array();
                $s2p_transaction_arr['order_id'] = $order->id;
                if( isset( $request_arr['PaymentID'] ) )
                    $s2p_transaction_arr['payment_id'] = $request_arr['PaymentID'];

                $s2p_transaction_extra_arr = array();
                $s2p_default_transaction_extra_arr = $s2p_module::defaultTransactionLoggerExtraParams();
                foreach( $s2p_default_transaction_extra_arr as $key => $val )
                {
                    if( array_key_exists( $key, $request_arr ) )
                        $s2p_transaction_extra_arr[$key] = $request_arr[$key];
                }

                if( !empty( $s2p_transaction_extra_arr ) )
                    $s2p_transaction_arr['extra_data'] = $s2p_transaction_extra_arr;

                $s2p_module->save_transaction( $s2p_transaction_arr );

                // NotificationType IS payment
                if( Tools::strtolower( $request_arr['NotificationType'] ) == 'payment' )
                {
                    // prepare string for hash
                    $responseHashString = "notificationTypePaymentPaymentId" . $request_arr['PaymentID'] . $moduleSettings['signature'];
                    // prepare response data
                    $responseData = array(
                        'NotificationType' => 'Payment',
                        'PaymentID' => $request_arr['PaymentID'],
                        'Hash' => $s2p_module->computeSHA256Hash( $responseHashString )
                    );

                    // output response
                    echo "NotificationType=Payment&PaymentID=" . $responseData['PaymentID'] . "&Hash=" . $responseData['Hash'];
                }
            }
        } catch( Exception $e )
        {
            $s2p_module->writeLog( $e->getMessage(), array( 'type' => 'exception', 'order_id' => (!empty( $order )?$order->id:0) ) );
        }

        $s2p_module->writeLog( '::: END HANDLE RESPONSE <<<', array( 'type' => 'info', 'order_id' => (!empty( $order )?$order->id:0) ) );

        die();
    }

    public static function get_main_request_params()
    {
        return array(
            'MethodID' => 0,
            'NotificationType' => '',
            'PaymentID' => 0,
            'MerchantTransactionID' => 0,
            'StatusID' => 0,
            'Amount' => 0,
            'Currency' => '',
            'Hash' => '',
        );
    }

    public static function normalize_request( $request_arr )
    {
        $default_main_params = self::get_main_request_params();

        if( empty( $request_arr ) or !is_array( $request_arr ) )
            return $default_main_params;

        foreach( $default_main_params as $key => $default )
        {
            if( !array_key_exists( $key, $request_arr ) )
                $request_arr[$key] = $default;
        }

        return $request_arr;
    }

    /**
     * Get raw php input
     *
     * @return string
     */
    private function getRawInput()
    {
        static $input;

        if( $input === null )
        {
            // On error, set $input as null to retry next time...
            if( ($input = @Tools::file_get_contents( 'php://input' )) === false )
                $input = null;
        }

        return $input;
    }

    /**
     * Parse php input
     *
     * @return mixed
     */
    private function parseInput()
    {
        parse_str( $this->getRawInput(), $response );

        return $response;
    }

    private function recomposeHashString()
    {
        if( !($raw_input = $this->getRawInput()) )
            return '';

        $vars = array();
        $recomposedHashString = '';
        $pairs = explode( '&', $raw_input );

        foreach( $pairs as $pair )
        {
            $nv = explode( '=', $pair );
            $name = $nv[0];
            $vars[$name] = (isset( $nv[1] )?$nv[1]:'');

            if( Tools::strtolower( $name ) != 'hash' )
                $recomposedHashString .= $name . $vars[$name];
        }

        return $recomposedHashString;
    }
}