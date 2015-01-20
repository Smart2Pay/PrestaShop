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
                            $methodModule = $this->module->getMethodModule($response['MethodID']);
                            $methodDisplayName = $methodModule ? $methodModule->displayName : $this->module->displayName;

                            $this->module->writeLog('Order has been paid', 'info');

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

                            if ($moduleSettings['s2p-create-invoice-on-success']) {
                                $order->setInvoice(true);
                            }

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

                        } else {
                            $this->module->writeLog('Smart2Pay :: notification has different amount[' . $orderAmount . '/' . $response['Amount'] . '] and/or currency[' . $orderCurrency . '/' . $response['Currency'] . ']!. Please contact support@smart2pay.com', 'info');
                        }
                        break;
                    // Status = canceled
                    case 3:
                        $this->module->writeLog('Payment canceled', 'info');
                        $this->module->changeOrderStatus($order, $moduleSettings['s2p-order-status-on-cancel']);
                        // There is no way to cancel an order other but changing it's status to canceled
                        // What we do is not changing order status to canceled, but to a user set one, instead
                        break;
                    // Status = failed
                    case 4:
                        $this->module->writeLog('Payment failed', 'info');
                        $this->module->changeOrderStatus($order, $moduleSettings['s2p-order-status-on-fail']);
                        break;
                    // Status = expired
                    case 5:
                        $this->module->writeLog('Payment expired', 'info');
                        $this->module->changeOrderStatus($order, $moduleSettings['s2p-order-status-on-expire']);
                        break;

                    default:
                        $this->module->writeLog('Payment status unknown', 'info');
                        break;
                }

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