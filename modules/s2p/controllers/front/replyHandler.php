<?php

class S2pReplyHandlerModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $this->module->writeLog('>>> START HANDLE RESPONSE :::', 'info');

        $moduleSettings = $this->module->getSettings();

        try {
            $response = $this->parseInput();
            $recomposedHashString = $this->recomposeHashString() . $moduleSettings['signature'];

            $this->module->writeLog('NotificationRecevied: "' . $this->getRawInput() . '"', 'info');

            /*
             * Message is intact
             *
             */
            if ($this->module->computeSHA256Hash($recomposedHashString) == $response['Hash']) {

                $this->module->writeLog('Hashes match', 'info');

                $order = new Order($response['MerchantTransactionID']);
                $cart = new Cart($order->id_cart);
                $currency = new Currency($cart->id_currency);

                if (!Validate::isLoadedObject($order)) {
                    throw new Exception('Invalid order');
                }

                /*
                 * Check status ID
                 *
                 */
                switch ($response['StatusID']) {
                    // Status = success
                    case "2":
                        /*
                         * Check amount  and currency
                         */
                        $orderAmount = number_format($cart->getOrderTotal(), 2, '.', '') * 100;
                        $orderCurrency = $currency->iso_code;

                        if (strcmp($orderAmount, $response['Amount']) == 0 && $orderCurrency == $response['Currency']) {
                            $this->module->writeLog('Order has been paid', 'info');
                            $this->module->changeOrderStatus($order->id_cart, $moduleSettings['s2p-order-status-on-success']);
                            /*
                             * Todo - add order payment entry
                             * $order = new Order(10);
                             * $order->addOrderPayment(23.48, 's2pmybank');
                             */

                            //!>> $order->addStatusHistoryComment('Smart2Pay :: order has been paid. [MethodID:' . $response['MethodID'] . ']', $payMethod->method_config['order_status_on_2']);

                            /*

                            if ($payMethod->method_config['auto_invoice']) {
                                // Create and pay Order Invoice
                                if ($order->canInvoice()) {
                                    $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                                    $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                                    $invoice->register();
                                    $transactionSave = Mage::getModel('core/resource_transaction')
                                        ->addObject($invoice)
                                        ->addObject($invoice->getOrder());
                                    $transactionSave->save();
                                    $order->addStatusHistoryComment('Smart2Pay :: order has been automatically invoiced.', $payMethod->method_config['order_status_on_2']);
                                } else {
                                    $this->module->writeLog('Order can not be invoiced', 'warning');
                                }
                            }

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

                            if ($payMethod->method_config['notify_customer']) {
                                // Inform customer
                                $this->informCustomer($order, $response['Amount'], $response['Currency']);
                            }

                            */
                        } else {
                            //!>> $order->addStatusHistoryComment('Smart2Pay :: notification has different amount[' . $orderAmount . '/' . $response['Amount'] . '] and/or currency[' . $orderCurrency . '/' . $response['Currency'] . ']!. Please contact support@smart2pay.com', $payMethod->method_config['order_status_on_4']);
                        }
                        break;
                    // Status = canceled
                    case 3:
                        $this->module->writeLog('Payment canceled', 'info');
                        $this->module->changeOrderStatus($order->id_cart, $moduleSettings['s2p-order-status-on-cancel']);
                        /*
                         * Todo - find a way to actually cancel the order too
                         */
                        break;
                    // Status = failed
                    case 4:
                        $this->module->writeLog('Payment failed', 'info');
                        $this->module->changeOrderStatus($order->id_cart, $moduleSettings['s2p-order-status-on-fail']);
                        break;
                    // Status = expired
                    case 5:
                        $this->module->writeLog('Payment expired', 'info');
                        $this->module->changeOrderStatus($order->id_cart, $moduleSettings['s2p-order-status-on-expire']);
                        break;

                    default:
                        $this->module->writeLog('Payment status unknown', 'info');
                        break;
                }

                //$order->save();

                // NotificationType IS payment
                if (strtolower($response['NotificationType']) == 'payment') {
                    // prepare string for hash
                    $responseHashString = "notificationTypePaymentPaymentId" . $response['PaymentID'] . $moduleSettings['signature'];
                    // prepare response data
                    $responseData = array(
                        'NotificationType' => 'Payment',
                        'PaymentID' => $response['PaymentID'],
                        'Hash' => $this->module->computeSHA256Hash($responseHashString)
                    );
                    // output response
                    echo "NotificationType=payment&PaymentID=" . $responseData['PaymentID'] . "&Hash=" . $responseData['Hash'];
                }
            } else {
                $this->module->writeLog('Hashes do not match (received: ' . $response['Hash'] . ') vs (recomposed: ' . $this->module->computeSHA256Hash($recomposedHashString) . ')', 'warning');
            }
        } catch (Exception $e) {
            $this->module->writeLog($e->getMessage(), 'exception');
        }

        $this->module->writeLog('::: END HANDLE RESPONSE <<<', 'info');

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

        if ($input === null) {
            $input = file_get_contents("php://input");
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
        parse_str($this->getRawInput(), $response);

        return $response;
    }

    private function recomposeHashString()
    {
        $raw_input = $this->getRawInput();
        $vars = array();
        $recomposedHashString = '';

        if (!empty($raw_input)) {
            $pairs = explode("&", $raw_input);
            foreach ($pairs as $pair) {
                $nv = explode("=", $pair);
                $name = $nv[0];
                $vars[$name] = $nv[1];
                if (strtolower($name) != 'hash') {
                    $recomposedHashString .= $name . $vars[$name];
                }
            }
        }

        return $recomposedHashString;
    }
}