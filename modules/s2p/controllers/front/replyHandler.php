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

//        echo "zzz";

        $order = new Order(7);
        $cart = new Cart($order->id_cart);
        $currency = new Currency($cart->id_currency);

        //print_r($order);
        //print_r($cart);
        print_r($currency->iso_code);
        //print_r(Cart::getTotalCart($order->id_cart));

        // Create new OrderHistory
        $history = new OrderHistory();
        $history->id_order = $order->id;
        //$history->id_employee = (int)$this->context->employee->id;

        $use_existings_payment = false;
        if (!$order->hasInvoice())
            $use_existings_payment = true;
        $history->changeIdOrderState(22, $order, $use_existings_payment);
        $history->add();

        $this->module->writeLog('>>> START s2p reply :::', 'info');
        $raw_input = file_get_contents("php://input");
        parse_str($raw_input, $response);
        $this->module->writeLog($response, 'info');
        $this->module->writeLog('::: END s2p reply <<<', 'info');

        die();

        $context = $this->context;
        $this->module->writeLog('>>> START HANDLE RESPONSE :::', 'info');

// get assets
        /* @var Mage_Sales_Model_Order $order
         * @var Smart2Pay_Globalpay_Model_Pay $payMethod
         */
        $s2pHelper = Mage::helper('globalpay/helper');
        $payMethod = Mage::getModel('globalpay/pay');
        $order = Mage::getModel('sales/order');

        $moduleSettings = $this->module->getSettings();

        try {
            $raw_input = file_get_contents("php://input");
            parse_str($raw_input, $response);

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

            $recomposedHashString .= $moduleSettings['signature'];

            $this->module->writeLog('NotificationRecevied:\"' . $raw_input . '\"', 'info');

            // Message is intact
            if ($this->module->computeSHA256Hash($recomposedHashString) == $response['Hash']) {

                $this->module->writeLog('Hashes match', 'info');

                $order = new Order($response['MerchantTransactionID']);
                $cart = new Cart($order->id_cart);
                $currency = new Currency($cart->id_currency);
                //!>> $order->addStatusHistoryComment('Smart2Pay :: notification received:<br>' . $raw_input);

                /*
                 * Check status ID
                 */
                switch ($response['StatusID']) {
                    // Status = success
                    case "2":
                        // cheking amount  and currency
                        $orderAmount = number_format($cart->getOrderTotal(), 2, '.', '') * 100;
                        $orderCurrency = $currency->iso_code;

                        if (strcmp($orderAmount, $response['Amount']) == 0 && $orderCurrency == $response['Currency']) {
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
                        $order->addStatusHistoryComment('Smart2Pay :: payment has been canceled.', $payMethod->method_config['order_status_on_3']);
                        if ($order->canCancel()) {
                            $order->cancel();
                        } else {
                            $this->module->writeLog('Can not cancel the order', 'warning');
                        }
                        break;
                    // Status = failed
                    case 4:
                        $order->addStatusHistoryComment('Smart2Pay :: payment has failed.', $payMethod->method_config['order_status_on_4']);
                        break;
                    // Status = expired
                    case 5:
                        $order->addStatusHistoryComment('Smart2Pay :: payment has expired.', $payMethod->method_config['order_status_on_5']);
                        break;

                    default:
                        $order->addStatusHistoryComment('Smart2Pay status "' . $response['StatusID'] . '" occurred.', $payMethod->method_config['order_status']);
                        break;
                }

                //$order->save();

                // NotificationType IS payment
                if (strtolower($response['NotificationType']) == 'payment') {
                    // prepare string for 'da hash
                    $responseHashString = "notificationTypePaymentPaymentId" . $response['PaymentID'] . $payMethod->method_config['signature'];
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
                $this->module->writeLog('Hashes do not match (received:' . $response['Hash'] . ')(recomposed:' . $this->module->computeSHA256Hash($recomposedHashString) . ')', 'warning');
            }
        } catch (Exception $e) {
            $this->module->writeLog($e->getMessage(), 'exception');
        }
        $this->module->writeLog('::: END HANDLE RESPONSE <<<', 'info');
    }
}