<?php

class S2pReplyHandlerModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /** @var S2P $module */
    public $module;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $this->display_column_left = false;

        $s2p_module = $this->module;

        $this->module->writeLog( '>>> START HANDLE RESPONSE :::', 'info' );

        $moduleSettings = $this->module->getSettings();

        try
        {
            $response = $this->parseInput();
            $recomposedHashString = $this->recomposeHashString() . $moduleSettings['signature'];

            $this->module->writeLog('NotificationRecevied: "' . $this->getRawInput() . '"', 'info');

            /*
             * Message is intact
             *
             */
            if( $this->module->computeSHA256Hash($recomposedHashString) != $response['Hash'] )
                $this->module->writeLog( 'Hashes do not match (received: ' . $response['Hash'] . ') vs (recomposed: ' . $this->module->computeSHA256Hash( $recomposedHashString ) . ')', 'warning' );

            else
            {
                $this->module->writeLog('Hashes match', 'info');

                $order = new Order($response['MerchantTransactionID']);
                $cart = new Cart($order->id_cart);
                $currency = new Currency($cart->id_currency);

                if( !Validate::isLoadedObject( $order ) )
                    throw new Exception( 'Invalid order' );

                /*
                 * Check status ID
                 *
                 */
                $response['StatusID'] = intval( $response['StatusID'] );
                switch( $response['StatusID'] )
                {
                    // Status = success
                    case $s2p_module::S2P_STATUS_SUCCESS:
                        /*
                         * Check amount  and currency
                         */
                        $orderAmount = number_format( $cart->getOrderTotal(), 2, '.', '' ) * 100;
                        $orderCurrency = $currency->iso_code;

                        if( strcmp( $orderAmount, $response['Amount'] ) != 0
                         or $orderCurrency != $response['Currency'] )
                            $this->module->writeLog( 'Smart2Pay :: notification has different amount[' . $orderAmount . '/' . $response['Amount'] . '] '.
                                                     ' and/or currency[' . $orderCurrency . '/' . $response['Currency'] . ']. Please contact support@smart2pay.com.', 'info' );

                        else
                        {
                            $methodModule = $this->module->getMethodModule( $response['MethodID'] );
                            $methodDisplayName = $methodModule ? $methodModule->displayName : $this->module->displayName;

                            $this->module->writeLog( 'Order ['.$order->id.'] has been paid', 'info' );

                            $order->addOrderPayment(
                                $response['Amount'] / 100,
                                $methodDisplayName,
                                $response['PaymentID'],
                                $currency
                            );

                            $this->module->changeOrderStatus(
                                $order,
                                $moduleSettings['s2p-order-status-on-success'],
                                $moduleSettings['s2p-notify-customer-by-email']
                            );

                            if( $moduleSettings['s2p-create-invoice-on-success'] )
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
                                    $this->module->writeLog('Order can not be shipped', 'warning');
                                }
                            }
                            */

                        }
                    break;

                    // Status = canceled
                    case $s2p_module::S2P_STATUS_CANCELLED:
                        $this->module->writeLog( 'Payment canceled', 'info' );
                        $this->module->changeOrderStatus( $order, $moduleSettings['s2p-order-status-on-cancel'] );
                        // There is no way to cancel an order other but changing it's status to canceled
                        // What we do is not changing order status to canceled, but to a user set one, instead
                    break;

                    // Status = failed
                    case $s2p_module::S2P_STATUS_FAILED:
                        $this->module->writeLog( 'Payment failed', 'info' );
                        $this->module->changeOrderStatus( $order, $moduleSettings['s2p-order-status-on-fail'] );
                    break;

                    // Status = expired
                    case $s2p_module::S2P_STATUS_EXPIRED:
                        $this->module->writeLog( 'Payment expired', 'info' );
                        $this->module->changeOrderStatus($order, $moduleSettings['s2p-order-status-on-expire']);
                    break;

                    default:
                        $this->module->writeLog( 'Payment status unknown', 'info' );
                    break;
                }

                // NotificationType IS payment
                if( strtolower( $response['NotificationType'] ) == 'payment' )
                {
                    // prepare string for hash
                    $responseHashString = "notificationTypePaymentPaymentId" . $response['PaymentID'] . $moduleSettings['signature'];
                    // prepare response data
                    $responseData = array(
                        'NotificationType' => 'Payment',
                        'PaymentID' => $response['PaymentID'],
                        'Hash' => $this->module->computeSHA256Hash( $responseHashString )
                    );

                    // output response
                    echo "NotificationType=payment&PaymentID=" . $responseData['PaymentID'] . "&Hash=" . $responseData['Hash'];
                }
            }
        } catch( Exception $e )
        {
            $this->module->writeLog( $e->getMessage(), 'exception' );
        }

        $this->module->writeLog( '::: END HANDLE RESPONSE <<<', 'info' );

        die();
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
            if( ($input = @file_get_contents( 'php://input' )) === false )
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

            if( strtolower( $name ) != 'hash' )
                $recomposedHashString .= $name . $vars[$name];
        }

        return $recomposedHashString;
    }
}