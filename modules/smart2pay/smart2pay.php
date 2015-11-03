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
 * Smart2Pay module file
**/
if (!defined('_PS_VERSION_'))
    exit;

include_once( _PS_MODULE_DIR_.'smart2pay/includes/helper.inc.php' );

class Smart2pay extends PaymentModule
{
    const S2P_STATUS_OPEN = 1, S2P_STATUS_SUCCESS = 2, S2P_STATUS_CANCELLED = 3, S2P_STATUS_FAILED = 4, S2P_STATUS_EXPIRED = 5, S2P_STATUS_PENDING_CUSTOMER = 6,
        S2P_STATUS_PENDING_PROVIDER = 7, S2P_STATUS_SUBMITTED = 8, S2P_STATUS_AUTHORIZED = 9, S2P_STATUS_APPROVED = 10, S2P_STATUS_CAPTURED = 11, S2P_STATUS_REJECTED = 12,
        S2P_STATUS_PENDING_CAPTURE = 13, S2P_STATUS_EXCEPTION = 14, S2P_STATUS_PENDING_CANCEL = 15, S2P_STATUS_REVERSED = 16, S2P_STATUS_COMPLETED = 17, S2P_STATUS_PROCESSING = 18,
        S2P_STATUS_DISPUTED = 19, S2P_STATUS_CHARGEBACK = 20;

    const CONFIG_PREFIX = 'S2P_';
    const S2PD_CONFIG_PREFIX = 'S2PD_';

    const S2P_DETECTOR_NAME = 'smart2paydetection';

    const COOKIE_NAME = 'S2P_COOKIE';

    const PAYM_BANK_TRANSFER = 1, PAYM_MULTIBANCO_SIBS = 20;

    const OPT_FEE_CURRENCY_FRONT = 1, OPT_FEE_CURRENCY_ADMIN = 2;

    const OPT_FEE_AMOUNT_SEPARATED = 1, OPT_FEE_AMOUNT_TOTAL_FEE = 2, OPT_FEE_AMOUNT_TOTAL_ORDER = 3;

    const DEMO_SIGNATURE = 'fc5fa3b8-746a', DEMO_MID = '1045', DEMO_SID = '30144', DEMO_POSTURL = 'https://apitest.smart2pay.com';

    // Tells module if install() or uninstall() methods are currenctly called
    private static $maintenance_functionality = false;

    /**
     * Static cache
     *
     * @var array
     */
    static $cache = array(
        'all_method_details_in_cache' => false,
        'all_method_settings_in_cache' => false,
        'all_countries' => array(),
        'all_id_countries' => array(),
        'all_method_countries' => array(),
        'method_details' => array(),
        'method_settings' => array(),
        'methods_country' => '',
        'detected_country' => false,
        'detected_country_ip' => '',
        'force_country' => false,
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'smart2pay';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.8';
        $this->author = 'Smart2Pay';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array( 'min' => '1.4', 'max' => _PS_VERSION_ );
        $this->bootstrap = true;
        $this->controllers = array( 'payment' );

        parent::__construct();

        $this->displayName = $this->l( 'Smart2Pay' );
        $this->description = $this->l( 'Secure payments through 100+ alternative payment options.' );

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall Smart2Pay plugin?');

        $this->create_context();
    }

    public function create_context()
    {
        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
        {
            /** @var Cart $cart */
            /** @var Cookie $cookie */
            /** @var Smarty $smarty */
            global $smarty, $cookie, $cart;

            if( is_object( $cookie ) )
            {
                $lang_id = (int) $cookie->id_lang;
                $currency = new Currency( (int)$cookie->id_currency );
            } else
            {
                $lang_id = 1;
                $currency = null;
            }

            $language = new Language( $lang_id );

            // create context object for PrestaShop 1.4...
            if( empty( $this->context ) )
                $this->context = new stdClass();

            if( empty( $this->context->shop ) )
                $this->context->shop = new stdClass();

            /** @var Currency $this->context->currency */
            /** @var Cart $this->context->cart */
            /** @var Cookie $this->context->cookie */
            /** @var Smarty $this->smarty */
            $this->smarty = $smarty;
            $this->context->smarty = $smarty;
            $this->context->cart = $cart;
            $this->context->language = $language;
            $this->context->cookie = $cookie;
            $this->context->currency = $currency;
            $this->context->currency = $currency;

            //$this->context->shop->id_shop = 1;
            //$this->context->shop->id_shop_group = 1;
        }
    }

    public function get_current_shop_id()
    {

    }


    public function S2P_add_css( $file )
    {
        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
            Tools::addCSS( $file, 'all' );
        else
            $this->context->controller->addCSS( $file );
    }

    public static function redirect_to_step1()
    {
        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
            Tools::redirect( 'order.php?step=1' );
        else
            Tools::redirect( 'index.php?controller=order&step=1' );
    }

    /**
     * @param Order $order
     *
     * @return bool|int
     */
    public function get_quick_last_order_status( $order )
    {
        if( empty( $order )
         or !Validate::isLoadedObject( $order ) )
            return 0;

        $id_order_state = Db::getInstance()->getValue( '
		SELECT `id_order_state`
		FROM `'._DB_PREFIX_.'order_history`
		WHERE `id_order` = '.(int)$order->id.'
		ORDER BY `date_add` DESC, `id_order_history` DESC');

        // returns false if there is no state
        if (!$id_order_state)
            return false;

        return $id_order_state;
    }

    public function prepare_return_page()
    {
        $this->create_context();

        $order_id = (int)Tools::getValue( 'MerchantTransactionID', 0 );
        if( empty( $order_id )
         or !($transaction_arr = $this->get_transaction_by_order_id( $order_id ))
         or !($method_id = $transaction_arr['method_id'])
         or !$this->get_method_details( $method_id ) )
        {
            $transaction_arr = array();
            $method_id = 0;
        }

        if( empty( $order_id )
         or !($order = new Order( $order_id ))
         or !Validate::isLoadedObject( $order ) )
            $order = null;

        $transaction_extra_data = array();
        if( ($transaction_details_titles = self::transaction_logger_params_to_title())
            and is_array( $transaction_details_titles ) )
        {
            foreach( $transaction_details_titles as $key => $title )
            {
                $value = Tools::getValue( $key, false );
                if( $value === false )
                    continue;

                $transaction_extra_data[$key] = $value;
            }
        }

        if( empty( $transaction_details_titles ) )
            $transaction_details_titles = array();

        $moduleSettings = $this->getSettings( $order );

        $returnMessages = array(
            self::S2P_STATUS_SUCCESS => $moduleSettings[self::CONFIG_PREFIX.'MESSAGE_SUCCESS'],
            self::S2P_STATUS_CANCELLED => $moduleSettings[self::CONFIG_PREFIX.'MESSAGE_CANCELED'],
            self::S2P_STATUS_FAILED => $moduleSettings[self::CONFIG_PREFIX.'MESSAGE_FAILED'],
            self::S2P_STATUS_PENDING_PROVIDER => $moduleSettings[self::CONFIG_PREFIX.'MESSAGE_PENDING'],
            self::S2P_STATUS_AUTHORIZED => $moduleSettings[self::CONFIG_PREFIX.'MESSAGE_PENDING'],
        );

        $s2p_statuses = array(
            'open' => self::S2P_STATUS_OPEN,
            'success' => self::S2P_STATUS_SUCCESS,
            'cancelled' => self::S2P_STATUS_CANCELLED,
            'failed' => self::S2P_STATUS_FAILED,
            'expired' => self::S2P_STATUS_EXPIRED,
            'processing' => self::S2P_STATUS_PENDING_PROVIDER,
            'authorized' => self::S2P_STATUS_AUTHORIZED,
        );

        $data = (int) Tools::getValue( 'data', 0 );

        if( empty( $data ) )
            $data = self::S2P_STATUS_FAILED;

        if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
        {
            if( !($path = $this->context->smarty->getVariable( 'path', null, true, false ))
             or ($path instanceof Undefined_Smarty_Variable ) )
                $path = '';

            $path .= '<a >' . $this->l( 'Transaction Completed' ) . '</a>';

            $this->context->smarty->assign( array( 'path' => $path ) );
        }

        $this->context->smarty->assign( array(
            'front_tpl_dir' => _PS_MODULE_DIR_.$this->name.'/views/templates/front/',
            'transaction_extra_titles' => $transaction_details_titles,
            'transaction_extra_data' => $transaction_extra_data,
            's2p_transaction' => $transaction_arr,
            's2p_data' => $data,
            's2p_statuses' => $s2p_statuses,
        ) );

        if( !isset( $returnMessages[$data] ) )
            $this->context->smarty->assign( array( 'message' => $this->l( 'Unknown return status.' ) ) );
        else
            $this->context->smarty->assign( array( 'message' => $returnMessages[$data] ) );
    }

    public function prepare_send_form()
    {
        $this->create_context();

        $context = $this->context;
        $cart = $this->context->cart;

        if( empty( $cart )
         or !($cart_products = $cart->getProducts()) )
        {
            $this->writeLog( 'Couldn\'t get cart from context', array( 'type' => 'error' ) );
            self::redirect_to_step1();

            // just to make IDE not highlight variables as "might not be initialized"
            exit;
        }

        $cart_currency = new Currency( $cart->id_currency );
        $customer = new Customer( $cart->id_customer );

        if( !Validate::isLoadedObject( $customer ) )
            self::redirect_to_step1();

        $moduleSettings = $this->getSettings();

        $method_id = (int) Tools::getValue( 'method_id', 0 );

        if( empty( $method_id )
         or !($payment_method = $this->method_details_if_available( $method_id )) )
        {
            $this->writeLog( 'Payment method #' . $method_id . ' could not be loaded, or it is not available', array( 'type' => 'error' ) );
            // Todo - give some feedback to the user
            self::redirect_to_step1();
        }

        if( empty( $payment_method['method_settings']['surcharge_currency'] )
         or !($surcharge_currency_id = Currency::getIdByIsoCode( $payment_method['method_settings']['surcharge_currency'] ))
         or !($surcharge_currency_obj = new Currency( $surcharge_currency_id )) )
        {
            $this->writeLog( 'Payment method #' . $method_id . ' ('.$payment_method['method_details']['display_name'].') has an invalid currency code ['.(!empty( $payment_method['method_settings']['surcharge_currency'] )?$payment_method['method_settings']['surcharge_currency']:'???').'].', array( 'type' => 'error' ) );
            // Todo - give some feedback to the user
            self::redirect_to_step1();
        }

        $site_id = $moduleSettings[self::CONFIG_PREFIX.'SITE_ID'] ? $moduleSettings[self::CONFIG_PREFIX.'SITE_ID'] : null;

        /**
         *    Surcharge calculation
         */
        $cart_original_amount = $amount_to_pay = number_format( $context->cart->getOrderTotal( true, Cart::BOTH ), 2, '.', '' );

        $surcharge_percent_amount = 0;
        // Amount in shop currency (base currency)
        $surcharge_amount = 0;
        // Amount in order currency
        $surcharge_order_amount = 0;
        if( (float)$payment_method['method_settings']['surcharge_percent'] != 0 )
            $surcharge_percent_amount = Tools::ps_round( ( $amount_to_pay * $payment_method['method_settings']['surcharge_percent'] ) / 100, 2 );
        if( (float)$payment_method['method_settings']['surcharge_amount'] != 0 )
            $surcharge_amount = Tools::ps_round( $payment_method['method_settings']['surcharge_amount'], 2 );

        if( $surcharge_amount != 0 )
        {
            if( $surcharge_currency_id != $context->cart->id_currency )
                $surcharge_order_amount = Tools::ps_round( Smart2Pay_Helper::convert_price( $surcharge_amount, $surcharge_currency_obj, $cart_currency ), 2 );
            else
                $surcharge_order_amount = $surcharge_amount;
        }

        $total_surcharge = $surcharge_percent_amount + $surcharge_order_amount;
        $amount_to_pay += $total_surcharge;

        if( !($shipping_price = Smart2Pay_Helper::get_total_shipping_cost( $cart )) )
            $shipping_price = 0;

        $articles_params = array();
        $articles_params['transport_amount'] = $shipping_price;
        $articles_params['total_surcharge'] = $total_surcharge;
        $articles_params['amount_to_pay'] = $amount_to_pay;

        $articles_str = '';
        $articles_diff = 0;
        if( ($articles_check = Smart2Pay_Helper::cart_products_to_string( $cart_products, $cart_original_amount, $articles_params )) )
        {
            $articles_str = $articles_check['buffer'];

            if( !empty( $articles_check['total_difference_amount'] )
            and $articles_check['total_difference_amount'] >= -0.01 and $articles_check['total_difference_amount'] <= 0.01 )
            {
                $articles_diff = $articles_check['total_difference_amount'];
                $amount_to_pay += $articles_diff;
            }
        }

        $this->validateOrder(
            $cart->id,
            $moduleSettings[ self::CONFIG_PREFIX . 'NEW_ORDER_STATUS' ],
            $cart_original_amount,
            $payment_method['method_details']['display_name'],

            // $message
            null,

            // $extraVars
            array(),

            // $currency_special
            null,

            // $dont_touch_amount
            false,

            // $secure_key
            $cart->secure_key
        );

        if( !empty( $this->currentOrder ) )
            $orderID = $this->currentOrder;
        else
            $orderID = Order::getOrderByCartId( $context->cart->id );

        if( $moduleSettings[self::CONFIG_PREFIX.'ALTER_ORDER_ON_SURCHARGE']
        and $cart_original_amount != $amount_to_pay )
        {
            $order = new Order( $orderID );

            if( Validate::isLoadedObject( $order ) )
            {
                $order->total_paid += $total_surcharge + $articles_diff;
                if( property_exists( $order, 'total_paid_tax_incl' ) )
                    $order->total_paid_tax_incl += $total_surcharge + $articles_diff;

                $order->update();

            }
        }
        /**
         *    END Surcharge calculation
         */

        $transaction_arr = array();
        $transaction_arr['method_id'] = $method_id;
        $transaction_arr['order_id'] = $orderID;
        if( !empty( $site_id ) )
            $transaction_arr['site_id'] = $site_id;
        $transaction_arr['environment'] = Tools::strtolower( $moduleSettings[self::CONFIG_PREFIX.'ENV'] );

        $transaction_arr['surcharge_amount'] = $payment_method['method_settings']['surcharge_amount'];
        $transaction_arr['surcharge_percent'] = $payment_method['method_settings']['surcharge_percent'];
        $transaction_arr['surcharge_currency'] = $surcharge_currency_obj->iso_code;

        $transaction_arr['surcharge_order_amount'] = $surcharge_order_amount;
        $transaction_arr['surcharge_order_percent'] = $surcharge_percent_amount;
        $transaction_arr['surcharge_order_currency'] = $cart_currency->iso_code;

        $this->save_transaction( $transaction_arr );

        $skipPaymentPage = 0;
        if( $moduleSettings[self::CONFIG_PREFIX.'SKIP_PAYMENT_PAGE']
            and !in_array( $method_id, array( self::PAYM_BANK_TRANSFER, self::PAYM_MULTIBANCO_SIBS ) ) )
            $skipPaymentPage = 1;

        $moduleSettings['skipPaymentPage'] = $skipPaymentPage;

        if( !empty( $moduleSettings[self::CONFIG_PREFIX.'SEND_ORDER_NUMBER'] ) )
            $payment_description = 'Ref. No. '.$orderID;
        else
            $payment_description = $moduleSettings[self::CONFIG_PREFIX.'CUSTOM_PRODUCT_DESCRIPTION'];

        $paymentData = array(
            'MerchantID'        => $moduleSettings['mid'],
            'MerchantTransactionID' => $orderID,
            'Amount'            => $amount_to_pay * 100,
            'Currency'          => $cart_currency->iso_code,
            'ReturnURL'         => $moduleSettings[self::CONFIG_PREFIX.'RETURN_URL'],
            'IncludeMethodIDs'  => $method_id,
            'CustomerName'      => $customer->firstname . ' ' . $customer->lastname,
            'CustomerFirstName' => $customer->firstname,
            'CustomerLastName'  => $customer->lastname,
            'CustomerEmail'     => $customer->email,
            'Country'           => $payment_method['country_iso'],
            'MethodID'          => $method_id,
            'Description'       => $payment_description,
            'SkipHPP'           => (!empty( $moduleSettings[self::CONFIG_PREFIX.'SKIP_PAYMENT_PAGE'] )?1:0),
            'RedirectInIframe'  => (!empty( $moduleSettings[self::CONFIG_PREFIX.'REDIRECT_IN_IFRAME'] )?1:0),
            'SkinID'            => (!empty( $moduleSettings[self::CONFIG_PREFIX.'SKIN_ID'] )?$moduleSettings[self::CONFIG_PREFIX.'SKIN_ID']:null),
            'SiteID'            => $site_id,
            'Articles'          => $articles_str,
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

        $messageToHash = $this->createStringToHash( $paymentData );

        $paymentData['Hash'] = $this->computeHash( $messageToHash, $moduleSettings['signature'] );

        $this->context->smarty->assign( array(
            'paymentData' => $paymentData,
            'messageToHash' => $messageToHash,
            'settings_prefix' => self::CONFIG_PREFIX,
            'moduleSettings' => $moduleSettings,
            'notSetPaymentData' => $notSetPaymentData,
        ) );

        $this->writeLog( 'Message to hash ['.$messageToHash.'], Hash ['.$paymentData['Hash'].']', array( 'order_id' => $orderID ) );
    }

    public function prepare_notification()
    {
        $this->writeLog( '>>> START HANDLE RESPONSE :::' );

        if( !($request_arr = Smart2Pay_Helper::parse_php_input())
         or !is_array( $request_arr )
         or !($request_arr = Smart2Pay_Helper::normalize_notification_request( $request_arr )) )
        {
            $this->writeLog( 'Couldn\'t obtain parameters from request.', array( 'type' => 'error' ) );
            die();
        }

        if( empty( $request_arr['MerchantTransactionID'] )
         or !($order = new Order( $request_arr['MerchantTransactionID'] ))
         or !Validate::isLoadedObject( $order ) )
        {
            $this->writeLog( 'Couldn\'t load order ['.(!empty( $request_arr['MerchantTransactionID'] )?$request_arr['MerchantTransactionID']:0).'] from database.',
                array( 'type' => 'error', array( 'order_id' => (!empty( $request_arr['MerchantTransactionID'] )?$request_arr['MerchantTransactionID']:0) ) ) );
            die();
        }

        try
        {
            $moduleSettings = $this->getSettings( $order );

            $recomposedHashString = Smart2Pay_Helper::recompose_hash_string() . $moduleSettings['signature'];

            $this->writeLog( 'NotificationRecevied: "' . Smart2Pay_Helper::get_php_raw_input() . '"', array( 'type' => 'info', 'order_id' => $request_arr['MerchantTransactionID'] ) );

            /*
             * Message is intact
             *
             */
            if( $this->computeSHA256Hash( $recomposedHashString ) != $request_arr['Hash'] )
                $this->writeLog( 'Hashes do not match (received: ' . $request_arr['Hash'] . ') vs (recomposed: ' . $this->computeSHA256Hash( $recomposedHashString ) . ')', array( 'type' => 'warning' ) );

            elseif( empty( $request_arr['MerchantTransactionID'] ) )
                $this->writeLog( 'Unknown order id in request.', array( 'type' => 'error' ) );

            elseif( !($smart2pay_transaction_arr = $this->get_transaction_by_order_id( $request_arr['MerchantTransactionID'] )) )
                $this->writeLog( 'Order id ['.$request_arr['MerchantTransactionID'].'] not in transactions table.', array( 'type' => 'error', 'order_id' => $request_arr['MerchantTransactionID'] ) );

            else
            {
                $this->writeLog( 'Hashes match', array( 'type' => 'info', 'order_id' => $request_arr['MerchantTransactionID'] ) );

                $customer = new Customer( $order->id_customer );
                $currency = new Currency( $order->id_currency );

                /*
                 * Check status ID
                 *
                 */
                $request_arr['StatusID'] = (int)$request_arr['StatusID'];
                switch( $request_arr['StatusID'] )
                {
                    // Status = open
                    case self::S2P_STATUS_OPEN:
                        if( !empty( $smart2pay_transaction_arr['method_id'] )
                        and in_array( $smart2pay_transaction_arr['method_id'], array( self::PAYM_BANK_TRANSFER, self::PAYM_MULTIBANCO_SIBS ) )
                        and $smart2pay_transaction_arr['payment_status'] != self::S2P_STATUS_OPEN
                        and !empty( $moduleSettings[self::CONFIG_PREFIX.'SEND_PAYMENT_INSTRUCTIONS'] ) )
                        {
                            $info_fields = self::defaultTransactionLoggerExtraParams();
                            $template_vars = array();
                            foreach( $info_fields as $key => $def_val )
                            {
                                if( array_key_exists( $key, $request_arr ) )
                                    $template_vars['{'.$key.'}'] = $request_arr[$key];
                                else
                                    $template_vars['{'.$key.'}'] = $def_val;
                            }

                            $template_vars['{name}'] = Tools::safeOutput( $customer->firstname );

                            $template_vars['{OrderReference}'] = Tools::safeOutput( (version_compare( _PS_VERSION_, '1.5', '>=' )?$order->reference:'#'.$order->id) );
                            $template_vars['{OrderDate}'] = Tools::safeOutput( Tools::displayDate( $order->date_add, $order->id_lang, true ) );
                            $template_vars['{OrderPayment}'] = Tools::safeOutput( $order->payment );

                            if( $smart2pay_transaction_arr['method_id'] == self::PAYM_BANK_TRANSFER )
                                $template = 'instructions_bank_transfer';
                            elseif( $smart2pay_transaction_arr['method_id'] == self::PAYM_MULTIBANCO_SIBS )
                                $template = 'instructions_multibanco_sibs';

                            if( !empty( $template ) )
                            {
                                $this->writeLog( 'Sending payment instructions email for order ['.(version_compare( _PS_VERSION_, '1.5', '<' )?$order->id:$order->reference).']', array( 'order_id' => $order->id ) );

                                Smart2Pay_Helper::send_mail(
                                    (int) $order->id_lang,
                                    $template,
                                    sprintf( Mail::l( 'Payment instructions for order %1$s', $order->id_lang ), (version_compare( _PS_VERSION_, '1.5', '>=' )?$order->reference:'#'.$order->id) ),
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
                                    _PS_MODULE_DIR_ . $this->name . '/mails/',

                                    // die
                                    false,

                                    // id_shop
                                    (version_compare( _PS_VERSION_, '1.5', '>=' )?$order->id_shop:0),

                                    // bcc
                                    null
                                );
                            }
                        }
                    break;

                    // Status = success
                    case self::S2P_STATUS_SUCCESS:
                        /*
                         * Check amount  and currency
                         */
                        $initialOrderAmount = $orderAmount = number_format( Smart2Pay_Helper::get_order_total_amount( $order ), 2, '.', '' );
                        $orderCurrency = $currency->iso_code;

                        $surcharge_amount = 0;
                        // Add surcharge if we have something...
                        if( (float)$smart2pay_transaction_arr['surcharge_order_percent'] != 0 )
                            $surcharge_amount += (float)$smart2pay_transaction_arr['surcharge_order_percent'];
                        if( (float)$smart2pay_transaction_arr['surcharge_order_amount'] != 0 )
                            $surcharge_amount += (float)$smart2pay_transaction_arr['surcharge_order_amount'];

                        $orderAmount += $surcharge_amount;

                        if( !empty( $moduleSettings[self::CONFIG_PREFIX.'ALTER_ORDER_ON_SURCHARGE'] ) )
                            $orderAmount_check = number_format( $initialOrderAmount * 100, 0, '.', '' );
                        else
                            $orderAmount_check = number_format( $orderAmount * 100, 0, '.', '' );

                        if( strcmp( $orderAmount_check, $request_arr['Amount'] ) != 0
                            or $orderCurrency != $request_arr['Currency'] )
                            $this->writeLog( 'Smart2Pay :: notification has different amount[' . $orderAmount_check . '/' . $request_arr['Amount'] . '] '.
                                                   ' and/or currency [' . $orderCurrency . '/' . $request_arr['Currency'] . ']. Please contact support@smart2pay.com.', array( 'type' => 'error', 'order_id' => $order->id ) );

                        elseif( empty( $request_arr['MethodID'] )
                                or !($method_details = $this->get_method_details( $request_arr['MethodID'] )) )
                            $this->writeLog( 'Smart2Pay :: Couldn\'t get method details ['.$request_arr['MethodID'].']', array( 'type' => 'error', 'order_id' => $order->id ) );

                        // PrestaShop updates $order->current_state pretty late so we might get another call from server with a new notification...
                        elseif( $this->get_quick_last_order_status( $order ) == $moduleSettings[self::CONFIG_PREFIX.'ORDER_STATUS_ON_SUCCESS'] )
                            $this->writeLog( 'Order already on success status.', array( 'type' => 'error', 'order_id' => $order->id ) );

                        else
                        {
                            $orderAmount = number_format( $orderAmount_check / 100, 2, '.','' );

                            $this->writeLog( 'Order ['.(version_compare( _PS_VERSION_, '1.5', '<' )?$order->id:$order->reference).'] has been paid', array( 'order_id' => $order->id ) );

                            $order_only_amount = $initialOrderAmount;
                            if( !empty( $moduleSettings[self::CONFIG_PREFIX.'ALTER_ORDER_ON_SURCHARGE'] )
                                and $surcharge_amount != 0 )
                                $order_only_amount -= $surcharge_amount;

                            if( version_compare( _PS_VERSION_, '1.5', '<' ) )
                            {
                                // In PrestaShop 1.4 we don't have OrderPayment class... we update total_paid_real only...
                                $order->total_paid_real = $orderAmount;
                                $order->total_products_wt = $orderAmount - $order->total_shipping;

                                $order->update();
                            } else
                            {
                                $order->addOrderPayment(
                                    $order_only_amount,
                                    ( ! empty( $method_details['display_name'] ) ? $method_details['display_name'] : $this->displayName ),
                                    $request_arr['PaymentID'],
                                    $currency
                                );

                                if( $surcharge_amount != 0 )
                                {
                                    $order->addOrderPayment(
                                        $surcharge_amount,
                                        $this->l( 'Payment Surcharge' ),
                                        $request_arr['PaymentID'],
                                        $currency
                                    );
                                }
                            }

                            $this->changeOrderStatus( $order, $moduleSettings[self::CONFIG_PREFIX.'ORDER_STATUS_ON_SUCCESS'] );

                            if( !empty( $moduleSettings[self::CONFIG_PREFIX.'NOTIFY_CUSTOMER_BY_EMAIL'] ) )
                            {
                                $template_vars = array();

                                $template_vars['{name}'] = Tools::safeOutput( $customer->firstname );

                                if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
                                {
                                    $order_reference = $order->reference;
                                    $order_date = Tools::displayDate( $order->date_add, null, true );
                                } else
                                {
                                    $order_reference = '#'.$order->id;
                                    $order_date = Tools::displayDate( $order->date_add, $order->id_lang, true );
                                }

                                $template_vars['{OrderReference}'] = Tools::safeOutput( $order_reference );
                                $template_vars['{OrderDate}'] = Tools::safeOutput( $order_date );
                                $template_vars['{OrderPayment}'] = Tools::safeOutput( $order->payment );

                                $template_vars['{TotalPaid}'] = Tools::displayPrice( $orderAmount, $currency );

                                // Send payment confirmation email...
                                Smart2Pay_Helper::send_mail(
                                    (int) $order->id_lang,
                                    'payment_confirmation',
                                    sprintf( Mail::l( 'Payment confirmation for order %1$s', $order->id_lang ), $order_reference ),
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
                                    _PS_MODULE_DIR_ . $this->name . '/mails/',

                                    // die
                                    false,

                                    // id_shop
                                    (version_compare( _PS_VERSION_, '1.5', '>=' )?$order->id_shop:0),

                                    // bcc
                                    null
                                );

                                $this->writeLog( 'Customer notified about payment.', array( 'order_id' => $order->id ) );
                            }

                            if( !empty( $moduleSettings[self::CONFIG_PREFIX.'CREATE_INVOICE_ON_SUCCESS'] ) )
                                $this->check_order_invoices( $order, array( 'check_delivery' => (!empty( $moduleSettings[self::CONFIG_PREFIX.'AUTOMATE_SHIPPING'] )?true:false) ) );

                        }
                    break;

                    // Status = canceled
                    case self::S2P_STATUS_CANCELLED:
                        $this->writeLog( 'Payment canceled', array( 'type' => 'info', 'order_id' => $order->id ) );
                        $this->changeOrderStatus( $order, $moduleSettings[self::CONFIG_PREFIX.'ORDER_STATUS_ON_CANCEL'] );
                        // There is no way to cancel an order other but changing it's status to canceled
                        // What we do is not changing order status to canceled, but to a user set one, instead
                    break;

                    // Status = failed
                    case self::S2P_STATUS_FAILED:
                        $this->writeLog( 'Payment failed', array( 'type' => 'info', 'order_id' => $order->id ) );
                        $this->changeOrderStatus( $order, $moduleSettings[self::CONFIG_PREFIX.'ORDER_STATUS_ON_FAIL'] );
                    break;

                    // Status = expired
                    case self::S2P_STATUS_EXPIRED:
                        $this->writeLog( 'Payment expired', array( 'type' => 'info', 'order_id' => $order->id ) );
                        $this->changeOrderStatus($order, $moduleSettings[self::CONFIG_PREFIX.'ORDER_STATUS_ON_EXPIRE']);
                    break;

                    default:
                        $this->writeLog( 'Payment status unknown', array( 'type' => 'error', 'order_id' => $order->id ) );
                    break;
                }

                $s2p_transaction_arr = array();
                $s2p_transaction_arr['order_id'] = $order->id;
                $s2p_transaction_arr['payment_status'] = $request_arr['StatusID'];
                if( isset( $request_arr['PaymentID'] ) )
                    $s2p_transaction_arr['payment_id'] = $request_arr['PaymentID'];

                $s2p_transaction_extra_arr = array();
                $s2p_default_transaction_extra_arr = self::defaultTransactionLoggerExtraParams();
                foreach( $s2p_default_transaction_extra_arr as $key => $val )
                {
                    if( array_key_exists( $key, $request_arr ) )
                        $s2p_transaction_extra_arr[$key] = $request_arr[$key];
                }

                if( !empty( $s2p_transaction_extra_arr ) )
                    $s2p_transaction_arr['extra_data'] = $s2p_transaction_extra_arr;

                $this->save_transaction( $s2p_transaction_arr );

                // NotificationType IS payment
                if( Tools::strtolower( $request_arr['NotificationType'] ) == 'payment' )
                {
                    // prepare string for hash
                    $responseHashString = "notificationTypePaymentPaymentId" . $request_arr['PaymentID'] . $moduleSettings['signature'];
                    // prepare response data
                    $responseData = array(
                        'NotificationType' => 'Payment',
                        'PaymentID' => $request_arr['PaymentID'],
                        'Hash' => $this->computeSHA256Hash( $responseHashString )
                    );

                    // output response
                    echo "NotificationType=Payment&PaymentID=" . $responseData['PaymentID'] . "&Hash=" . $responseData['Hash'];
                }
            }
        } catch( Exception $e )
        {
            $this->writeLog( $e->getMessage(), array( 'type' => 'exception', 'order_id' => $request_arr['MerchantTransactionID'] ) );
        }

        $this->writeLog( '::: END HANDLE RESPONSE <<<', array( 'type' => 'info', 'order_id' => $request_arr['MerchantTransactionID'] ) );
    }

    public function clean_methods_cache()
    {
        self::$cache['method_details'] = array();
        self::$cache['method_settings'] = array();
        self::$cache['all_method_details_in_cache'] = false;
        self::$cache['all_method_settings_in_cache'] = false;
    }

    public static function validate_value( $value, array $checks )
    {
        if( empty( $checks ) or !is_array( $checks ) )
            return array();

        $check_result = array();
        $check_result['<all_valid>'] = true;
        $check_result['url'] = true;
        $check_result['notempty'] = true;
        $check_result['country_iso'] = true;

        foreach( $checks as $check_function )
        {
            $check_function = Tools::strtolower( trim( $check_function ) );
            $result = false;
            switch( $check_function )
            {
                case 'url':
                    $result = (Validate::isUrl( $value )?true:false);
                break;

                case 'notempty':
                    $result = (!empty( $value )?true:false);
                break;

                case 'country_iso':
                    $result = ((empty( $value ) or Country::getByIso( $value ))?true:false);
                break;
            }

            if( !$result )
                $check_result['<all_valid>'] = false;

            $check_result[$check_function] = $result;
        }

        return $check_result;
    }

    public static function transform_value( $value, array $transforms )
    {
        if( empty( $transforms ) or !is_array( $transforms ) )
            return $value;

        $result = $value;
        foreach( $transforms as $transform_function )
        {
            $transform_function = Tools::strtolower( trim( $transform_function ) );
            switch( $transform_function )
            {
                case 'floatval':
                    $result = (float)$result;
                break;

                case 'intval':
                    $result = (int)$result;
                break;

                case 'trim':
                    $result = @trim( (string)$result );
                break;

                case 'toupper':
                    $result = @Tools::strtoupper( (string)$result );
                break;
            }
        }

        return $result;
    }

    /**
     * Process Module Config submitted data
     *
     * @return string
     */
    public function getContent()
    {
        // Caching method details and method settings (they will be used when saving data and when displaying data)
        $this->get_all_methods();
        $this->get_all_method_settings();

        $post_result = $this->process_post_data();

        $output = '';

        if( !empty( $post_result['submit'] ) )
        {
            if( !empty( $post_result['errors_buffer'] ) )
                $output .= $post_result['errors_buffer'];
            if( !empty( $post_result['success_buffer'] ) )
                $output .= $post_result['success_buffer'];
        }

        switch( $post_result['submit'] )
        {
            case 'submit_main_data':

            break;
        }

        return $output.
               $this->displayForm().
               $this->displayPaymentMethodsForm().
               $this->getLogsHTML();
    }

    private function process_post_data()
    {
        $post_data = array();
        $post_data['fields'] = array();
        $post_data['raw_fields'] = array();
        $post_data['field_errors'] = array();
        $post_data['success_buffer'] = '';
        $post_data['errors_buffer'] = '';
        $post_data['submit'] = '';

        /**
         * Check submit for payment method settings
         */
        if( Tools::isSubmit( 'submit_payment_methods' ) )
        {
            $post_data['submit'] = 'submit_payment_methods';

            $all_methods_arr = $this->get_all_methods();

            $enabled_methods_arr = Tools::getValue( 'enabled_methods', array() );
            $surcharge_percents_arr = Tools::getValue( 'surcharge_percent', array() );
            $surcharge_amounts_arr = Tools::getValue( 'surcharge_amount', array() );
            $surcharge_currencies_arr = Tools::getValue( 'surcharge_currency', array() );
            $methods_priority_arr = Tools::getValue( 'method_priority', array() );

            $valid_ids = array();
            foreach( $enabled_methods_arr as $method_id )
            {
                $method_id = (int)$method_id;
                if( empty( $all_methods_arr[$method_id] ) )
                    continue;

                $valid_ids[] = $method_id;

                $method_settings = array();
                $method_settings['enabled'] = 1;
                $method_settings['surcharge_amount'] = (!empty( $surcharge_amounts_arr[$method_id] )?(float)trim( $surcharge_amounts_arr[$method_id] ):0);
                $method_settings['surcharge_percent'] = (!empty( $surcharge_percents_arr[$method_id] )?(float)trim( $surcharge_percents_arr[$method_id] ):0);
                $method_settings['surcharge_currency'] = (!empty( $surcharge_currencies_arr[$method_id] )?trim( $surcharge_currencies_arr[$method_id] ):'');
                $method_settings['priority'] = (!empty( $methods_priority_arr[$method_id] )?(int)trim( $methods_priority_arr[$method_id] ):0);

                if( empty( $method_settings['surcharge_currency'] )
                 or !($currency_id = Currency::getIdByIsoCode( $method_settings['surcharge_currency'] )) )
                {
                    $post_data['errors_buffer'] .= $this->displayError( 'Unknown currency selected for payment method '.$all_methods_arr[$method_id]['display_name'].'.' );
                    continue;
                }

                if( !$this->save_method_settings( $method_id, $method_settings ) )
                    $post_data['errors_buffer'] .= $this->displayError( 'Error saving details for payment method '.$all_methods_arr[$method_id]['display_name'].'.' );
            }

            if( empty( $valid_ids ) )
                Db::getInstance()->execute( 'TRUNCATE TABLE `'._DB_PREFIX_.'smart2pay_method_settings`' );

            else
                Db::getInstance()->execute( 'DELETE FROM `'._DB_PREFIX_.'smart2pay_method_settings` WHERE method_id NOT IN ('.implode( ',', $valid_ids ).')' );

            $this->clean_methods_cache();

            $all_methods_arr = $this->get_all_methods();
            $this->get_all_method_settings();

            if( empty( $post_data['errors_buffer'] ) )
                $post_data['success_buffer'] .= $this->displayConfirmation( $this->l( 'Payment method details saved.' ) );
        }

        /**
         * Check submit of main form
         */
        elseif( Tools::isSubmit( 'submit_main_data' ) and isset( $_POST[self::CONFIG_PREFIX.'ENABLED'] ) )
        {
            $post_data['submit'] = 'submit_main_data';

            $formValues = array();

            foreach( $this->getConfigFormInputNames() as $name )
                $formValues[$name] = (string) Tools::getValue( $name, '' );

            $post_data['raw_fields'] = $formValues;

            /*
             *
             * Validate and update config values
             *
             */
            foreach( $this->getConfigFormInputs() as $input )
            {
                $isValid = true;
                $skipValidation = false;
                $field_error = '';

                if( in_array( $formValues[self::CONFIG_PREFIX.'ENV'], array( 'demo', 'test' ) )
                and in_array( $input['name'], array( self::CONFIG_PREFIX.'SIGNATURE_LIVE', self::CONFIG_PREFIX.'POST_URL_LIVE', self::CONFIG_PREFIX.'MID_LIVE' ) ) )
                    $skipValidation = true;

                if( in_array( $formValues[self::CONFIG_PREFIX.'ENV'], array( 'demo', 'live' ) )
                and in_array( $input['name'], array( self::CONFIG_PREFIX.'SIGNATURE_TEST', self::CONFIG_PREFIX.'POST_URL_TEST', self::CONFIG_PREFIX.'MID_TEST' ) ) )
                    $skipValidation = true;

                // Make necessary transformations before validation
                if( !empty( $input['_transform'] ) and is_array( $input['_transform'] ) )
                    $formValues[$input['name']] = self::transform_value( $formValues[$input['name']], $input['_transform'] );

                if( !$skipValidation
                and !empty( $input['_validate'] ) and is_array( $input['_validate'] )
                and ($validation_result = self::validate_value( $formValues[$input['name']], $input['_validate'] ))
                and empty( $validation_result['<all_valid>'] ) )
                {
                    $isValid = false;
                    if( empty( $validation_result['url'] ) )
                        $field_error .= $this->displayError( $this->l( 'Invalid value for input.' ) . ' '. $this->l( $input['label'] ).': ' . $this->l( 'Must be a valid URL' ) );
                    if( empty( $validation_result['notempty'] ) )
                        $field_error .= $this->displayError( $this->l( 'Invalid value for input.' ) . ' '. $this->l( $input['label'] ).': ' . $this->l( 'Must NOT be empty' ) );
                    if( empty( $validation_result['country_iso'] ) )
                        $field_error .= $this->displayError( $this->l( 'Invalid value for input.' ) . ' '. $this->l( $input['label'] ).': ' . $this->l( 'Should be a valid country.' ) );

                    if( empty( $field_error ) )
                        $field_error .= $this->displayError( $this->l( 'Value provided for input is invalid' ).' ('.$this->l( $input['label'] ).')' );
                }

                if( $isValid )
                    Configuration::updateValue( $input['name'], $formValues[$input['name']] );
                else
                {
                    $post_data['field_errors'][ $input['name'] ] = true;
                    $post_data['errors_buffer'] .= $field_error;
                }
            }

            if( empty( $post_data['errors_buffer'] ) )
                $post_data['success_buffer'] .= $this->displayConfirmation( $this->l( 'Settings updated successfully' ) );
        }

        return $post_data;
    }

    /**
     * Display Payment Methods Config Form
     *
     * @return mixed
     */
    public function displayPaymentMethodsForm()
    {
        $this->create_context();

        $this->S2P_add_css( _MODULE_DIR_ . $this->name . '/views/css/back-style.css' );

        if( !($all_currencies_arr = Currency::getCurrencies()) )
            $all_currencies_arr = array();

        //var_dump( $all_currencies_arr );

        $this->context->smarty->assign( array(
            'module_path' => $this->_path,
            'logos_path' => $this->_path.'views/img/logos/',
            'default_currency_id' => Currency::getDefaultCurrency()->id,
            'default_currency_iso' => Currency::getDefaultCurrency()->iso_code,
            'all_currencies' => $all_currencies_arr,
            'countries_by_id' => $this->get_smart2pay_id_countries(),
            'method_countries' => $this->get_method_countries_all(),
            'payment_methods' => $this->get_all_methods(),
            'payment_method_settings' => $this->get_all_method_settings(),
        ) );

        //if( !($fil = @fopen( '/home/andy/export.csv', 'w' )) )
        //    echo 'Nu am putut scrie fisierul';
        //
        //else
        //{
        //
        //    $all_methods = $this->get_all_methods();
        //    $all_countries = $this->get_smart2pay_id_countries();
        //    $all_method_countries = $this->get_method_countries_all();
        //
        //    @fputs( $fil, "Method ID,Method Name,Countries\r\n" );
        //
        //    ksort( $all_methods );
        //
        //    foreach( $all_methods as $method_id => $method_arr )
        //    {
        //        $str = $method_id.',"'.$method_arr['display_name'].'",';
        //
        //        $str_country = '';
        //        if( !empty( $all_method_countries[$method_id] ) )
        //        {
        //            foreach( $all_method_countries[$method_id] as $country_id )
        //            {
        //                if( empty( $all_countries[$country_id] ) )
        //                    echo 'pula country ['.$country_id.']';
        //
        //                $str_country .= (empty( $str_country )?'"':', ').$all_countries[$country_id]['name'].' ('.$all_countries[$country_id]['code'].')';
        //            }
        //            $str_country .= '"';
        //        }
        //
        //        $str .= $str_country."\r\n";
        //
        //        @fputs( $fil, $str );
        //    }
        //
        //    echo 'end';
        //
        //    @fflush( $fil );
        //    @fclose( $fil );
        //}
        //
        //exit;

        return $this->fetchTemplate( '/views/templates/admin/payment_methods.tpl' );
    }

    /**
     * Display Config Form
     *
     * @return mixed
     */
    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_form = array();

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l( 'Settings' ),
            ),
            'input' => $this->getConfigFormInputs(),
            'submit' => array(
                'title' => $this->l( 'Save' ),
                'class' => 'button'
            )
        );

        $form_buffer = '';

        if( @file_exists( Smart2Pay_Helper::get_documentation_path() ) )
        {
            $form_buffer .= '<p><strong>NOTE</strong>: For a better understanding of our plugin, please check our integration guide: <a href="'.Smart2Pay_Helper::get_documentation_url().'" style="text-decoration: underline;">'.Smart2Pay_Helper::get_documentation_file_name().'</a></p>';
        }

        $form_data = array();
        $form_data['submit_action'] = 'submit_main_data';

        $form_values = array();
        // Load current value
        foreach( $this->getConfigFormInputNames() as $name )
            $form_values[ $name ] = Configuration::get( $name );

        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
            $form_buffer .= Smart2Pay_Helper::generate_ancient_form( $fields_form, $form_data, $form_values );

        else
        {
            $helper = new HelperForm();

            // Module, token and currentIndex
            $helper->module          = $this;
            $helper->name_controller = $this->name;
            $helper->token           = Tools::getAdminTokenLite( 'AdminModules' );
            $helper->currentIndex    = AdminController::$currentIndex . '&configure=' . $this->name;

            // Language
            $helper->default_form_language    = $default_lang;
            $helper->allow_employee_form_lang = $default_lang;

            // Title and toolbar
            $helper->title          = $this->displayName;
            $helper->show_toolbar   = true;        // false -> remove toolbar
            $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
            $helper->submit_action  = $form_data['submit_action'];
            $helper->toolbar_btn    = array(
                'save' =>
                    array(
                        'desc' => $this->l( 'Save' ),
                        'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                                  '&token=' . Tools::getAdminTokenLite( 'AdminModules' ),
                    ),
                'back' => array(
                    'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite( 'AdminModules' ),
                    'desc' => $this->l( 'Back to list' )
                )
            );

            $helper->fields_value = $form_values;

            $this->S2P_add_css( _MODULE_DIR_ . $this->name . '/views/css/back-style.css' );

            $form_buffer .= $helper->generateForm( $fields_form );
        }

        return $form_buffer;
    }

    /**
     * Install
     *
     * @return bool
     */
    public function install()
    {
        self::$maintenance_functionality = true;

        if( !parent::install()

         // Displaying payment options
         or !$this->registerHook( 'payment' )

         // Displaying invoices (1.5+ doesn't offer access to pdf renderer)
         or (version_compare( _PS_VERSION_, '1.5', '<' ) and !$this->registerHook( 'PDFInvoice' ))

         // Displaying order details (public)
         or (

             version_compare( _PS_VERSION_, '1.5', '>=' )

             and

             !$this->registerHook( 'displayOrderDetail' ) // box right above product listing
         )

         or (

             version_compare( _PS_VERSION_, '1.5', '<' )

             and

             !$this->registerHook( 'orderDetailDisplayed' ) // box right above product listing
         )

         // Displaying payment options (admin)
         or (
                version_compare( _PS_VERSION_, '1.6', '>=' )

                and

            (
               !$this->registerHook( 'displayAdminOrderTabOrder' ) // Order tabs
            or !$this->registerHook( 'displayAdminOrderContentOrder' ) // Order tab content
            )
         )

         or (
                version_compare( _PS_VERSION_, '1.5', '<' )

                and

                !$this->registerHook( 'adminOrder' ) // Order content for 1.5
         )

         or (
                version_compare( _PS_VERSION_, '1.5', '>=' ) and version_compare( _PS_VERSION_, '1.6', '<' )

                and

                !$this->registerHook( 'displayAdminOrder' ) // Order content for 1.5
         ) )
        {
            self::$maintenance_functionality = false;
            return false;
        }

        if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
        {
            if( Shop::isFeatureActive() )
                Shop::setContext( Shop::CONTEXT_ALL );
        }

        /*
         * Install database
         */
        if( !$this->installDatabase() )
        {
            self::$maintenance_functionality = false;
            return false;
        }

        /*
         * Set default module config
         *
         */
        $this->createCustomOrderStatuses();

        foreach( $this->getConfigFormInputs() as $setting )
        {
            switch( $setting['name'] )
            {
                case self::CONFIG_PREFIX.'NEW_ORDER_STATUS':
                case self::CONFIG_PREFIX.'ORDER_STATUS_ON_SUCCESS':
                case self::CONFIG_PREFIX.'ORDER_STATUS_ON_CANCEL':
                case self::CONFIG_PREFIX.'ORDER_STATUS_ON_FAIL':
                case self::CONFIG_PREFIX.'ORDER_STATUS_ON_EXPIRE':
                break;

                default:
                    if( isset( $setting['_default'] ) )
                        Configuration::updateValue( $setting['name'], $setting['_default'] );
                break;
            }
        }

        self::$maintenance_functionality = false;

        return true;
    }

    /**
     * Uninstall
     *
     * @return bool
     */
    public function uninstall()
    {
        self::$maintenance_functionality = true;

        $settingsCleanedSuccessfully = true;

        foreach( $this->getConfigFormInputs() as $setting )
        {
            if( !Configuration::deleteByName( $setting['name'] ) )
                $settingsCleanedSuccessfully = false;
        }

        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
            $settingsCleanedSuccessfully = true;

        if( !parent::uninstall() || !$settingsCleanedSuccessfully )
        {
            self::$maintenance_functionality = false;
            return false;
        }

        // ! S2p custom order statuses are not removed in order to assure data consistency
        //   This way issues are avoided when there are orders having this type of status attached, and module is uninstalled
        //
        // $this->deleteCustomOrderStatuses();

        /*
         * Uninstall Database
         */
        $this->uninstallDatabase();

        self::$maintenance_functionality = false;

        return true;
    }

    public function hookPDFInvoice( $params )
    {
        if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
            return;

        if( empty( $params['pdf'] )
         or !($pdf = $params['pdf']) or !is_object( $pdf )
         or empty( $params['id_order'] )
         or !($order = new Order( $params['id_order'] ))
         or !Validate::isLoadedObject( $order )
         or !($transaction_arr = $this->get_transaction_by_order_id( $order->id ))
         or (!(float)$transaction_arr['surcharge_order_amount'] and !(float)$transaction_arr['surcharge_order_percent'])
         or empty( $transaction_arr['surcharge_order_currency'] )
         or !($order_currency_id = Currency::getIdByIsoCode( $transaction_arr['surcharge_order_currency'] ))
         or !($order_currency_obj = new Currency( $order_currency_id )) )
            return;

        /** @var PDF $pdf */
        $pdf->Ln( 4 );
        $pdf->Ln( 4 );

        $old_style = $pdf->FontStyle;

        $total_surcharge = (float)$transaction_arr['surcharge_order_amount'] + (float)$transaction_arr['surcharge_order_percent'];

        $pdf->SetFont( '', 'B', 8 );

        $pdf->Cell( 165, 0, $this->l( 'Order Total Amount' ).' : ', 0, 0, 'R' );
        $pdf->Cell( 0, 0, Tools::displayPrice( $order->total_paid - $total_surcharge, $order_currency_obj, true ), 0, 0, 'R' );
        $pdf->Ln( 4 );

        $pdf->Cell( 165, 0, $this->l( 'Payment Method Fees' ).' : ', 0, 0, 'R' );
        $pdf->Cell( 0, 0, Tools::displayPrice( $total_surcharge, $order_currency_obj, true ), 0, 0, 'R' );
        $pdf->Ln( 4 );

        $pdf->Cell( 165, 0, $this->l( 'Total Paid' ).' : ', 0, 0, 'R' );
        $pdf->Cell( 0, 0, Tools::displayPrice( $order->total_paid, $order_currency_obj, true ), 0, 0, 'R' );
        $pdf->Ln( 4 );

        $pdf->SetFont( '', $old_style );
    }

    /**
     * Public order details
     *
     * @param OrderCore $order
     *
     * @return string
     */
    public function hookDisplayOrderDetail( $params )
    {
        /** @var OrderCore $order */
        if( empty( $params ) or !is_array( $params )
         or empty( $params['order'] ) or !($order = $params['order'])
         or !Validate::isLoadedObject( $order )
         or empty( $order->id )
         or !($transaction_arr = $this->get_transaction_by_order_id( $order->id ))
         or empty( $transaction_arr['method_id'] )
         or !($method_details_arr = $this->get_method_details( $transaction_arr['method_id'] )) )
            return '';

        $this->create_context();

        if( !empty( $transaction_arr['extra_data'] ) )
            $transaction_extra_data = Smart2Pay_Helper::parse_string( $transaction_arr['extra_data'] );
        else
            $transaction_extra_data = array();

        $surcharge_currency_id = Currency::getIdByIsoCode( $transaction_arr['surcharge_currency'] );
        $order_currency_id = Currency::getIdByIsoCode( $transaction_arr['surcharge_order_currency'] );

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
            'surcharge_currency_iso' => $transaction_arr['surcharge_currency'],
            'surcharge_currency_id' => $surcharge_currency_id,
            'order_currency_iso' => $transaction_arr['surcharge_order_currency'],
            'order_currency_id' => $order_currency_id,
            'method_details' => $method_details_arr,
            'transaction_arr' => $transaction_arr,
            'transaction_extra_titles' => self::transaction_logger_params_to_title(),
            'transaction_extra_data' => $transaction_extra_data,
        ));

        return $this->fetchTemplate( '/views/templates/front/order_payment_details.tpl' );
    }

    /**
     * Front-office order details content
     *
     * @param array $params
     *
     * @return string
     */
    public function hookOrderDetailDisplayed( $params )
    {
        /** @var OrderCore $order */
        if( empty( $params ) or !is_array( $params )
         or empty( $params['order'] ) or !($order = $params['order'])
         or !Validate::isLoadedObject( $order )
         or empty( $order->id ) )
            return '';

        $hook_params = array();
        $hook_params['order'] = $order;

        return $this->hookDisplayOrderDetail( $hook_params );
    }

    /**
     * Admin order details tab and tab content
     *
     * @param OrderCore $order
     * @param array $products
     * @param CustomerCore $customer
     *
     * @return string
     */
    public function hookDisplayAdminOrderTabOrder( $params )
    {
        if( empty( $params ) or !is_array( $params )
         or empty( $params['order'] ) or !($order = $params['order'])
         or !Validate::isLoadedObject( $order )
         or empty( $order->id )
         or !($transaction_arr = $this->get_transaction_by_order_id( $order->id ))
         or empty( $transaction_arr['method_id'] )
         or !$this->get_method_details( $transaction_arr['method_id'] ) )
            return '';

        $this->create_context();

        if( !($order_logs = $this->getLogs( array( 'order_id' => $order->id ) ))
         or !is_array( $order_logs ) )
            $order_logs = array();

        $this->smarty->assign( array(
            'order_logs' => $order_logs,
        ) );

        return '<li><a href="#s2p-payment-details"><i class="icon-money"></i> '.$this->l( 'Payment Method' ).' <span class="badge">1</span></a></li>'.
               '<li><a href="#s2p-payment-logs"><i class="icon-book"></i> '.$this->l( 'Payment Logs' ).' <span class="badge">'.count( $order_logs ).'</span></a></li>';
    }

    /**
     * Admin order details tab and tab content
     *
     * @param OrderCore $order
     * @param array $products
     * @param CustomerCore $customer
     *
     * @return string
     */
    public function hookDisplayAdminOrderContentOrder( $params )
    {
        if( empty( $params ) or !is_array( $params )
         or empty( $params['order'] ) or !($order = $params['order'])
         or !Validate::isLoadedObject( $order )
         or empty( $order->id )
         or !($transaction_arr = $this->get_transaction_by_order_id( $order->id ))
         or empty( $transaction_arr['method_id'] )
         or !($method_details_arr = $this->get_method_details( $transaction_arr['method_id'] )) )
            return '';

        $this->create_context();

        if( !empty( $transaction_arr['extra_data'] ) )
            $transaction_extra_data = Smart2Pay_Helper::parse_string( $transaction_arr['extra_data'] );
        else
            $transaction_extra_data = array();

        $surcharge_currency_id = Currency::getIdByIsoCode( $transaction_arr['surcharge_currency'] );
        $order_currency_id = Currency::getIdByIsoCode( $transaction_arr['surcharge_order_currency'] );

        $this->smarty->assign( array(
            'this_path' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
            'surcharge_currency_iso' => $transaction_arr['surcharge_currency'],
            'surcharge_currency_id' => $surcharge_currency_id,
            'order_currency_iso' => $transaction_arr['surcharge_order_currency'],
            'order_currency_id' => $order_currency_id,
            'method_details' => $method_details_arr,
            'order_logs' => $this->getLogs( array( 'order_id' => $order->id ) ),
            'language_id' => $this->context->language->id,
            'transaction_arr' => $transaction_arr,
            'transaction_extra_titles' => self::transaction_logger_params_to_title(),
            'transaction_extra_data' => $transaction_extra_data,
        ) );

        return $this->fetchTemplate( '/views/templates/admin/order_payment_details.tpl' ).
               $this->fetchTemplate( '/views/templates/admin/order_payment_logs.tpl' );
    }

    /**
     * Admin order details content
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayAdminOrder( $params )
    {
        if( empty( $params ) or !is_array( $params )
         or empty( $params['id_order'] )
         or !($order = new Order( $params['id_order'] ))
         or !Validate::isLoadedObject( $order )
         or empty( $order->id ) )
            return '';

        $hook_params = array();
        $hook_params['order'] = $order;

        return $this->hookDisplayAdminOrderContentOrder( $hook_params );
    }

    /**
     * Admin order details content
     *
     * @param array $params
     *
     * @return string
     */
    public function hookAdminOrder( $params )
    {
        if( empty( $params ) or !is_array( $params )
         or empty( $params['id_order'] )
         or !($order = new Order( $params['id_order'] ))
         or !Validate::isLoadedObject( $order )
         or empty( $order->id ) )
            return '';

        $hook_params = array();
        $hook_params['order'] = $order;

        return $this->hookDisplayAdminOrderContentOrder( $hook_params );
    }

    public function get_payment_link( $params )
    {
        if( empty( $params ) or !is_array( $params )
         or empty( $params['method_id'] ) or !(int)$params['method_id'] )
            return '#';

        if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
            return $this->context->link->getModuleLink( 'smart2pay', 'payment', array( 'method_id' => $params['method_id'] ) );

        return Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/pre15/payment.php?method_id='.$params['method_id'];
    }

    public function get_notification_link( $params = false )
    {
        $this->create_context();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
            return $this->context->link->getModuleLink( 'smart2pay', 'replyHandler', $params );

        return Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/pre15/notificationhandler.php';
    }

    public function get_return_link( $params = false )
    {
        $this->create_context();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
            return $this->context->link->getModuleLink( 'smart2pay', 'returnHandler', $params );

        return Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/pre15/returnhandler.php';
    }

    /**
     * Hook payment
     *
     * @param $params
     *
     * @return bool
     */
    public function hookPayment( $params )
    {
        $this->create_context();

        $cart = (!empty( $this->context )?$this->context->cart:false);

        if( empty( $cart )
         or !Validate::isLoadedObject( $cart )
         or !Configuration::get( self::CONFIG_PREFIX.'ENABLED' ) )
            return '';

        $cart_original_amount = number_format( $cart->getOrderTotal( true, Cart::BOTH ), 2, '.', '' );

        $cart_currency = new Currency( $cart->id_currency );

        $method_params = array();
        $method_params['cart_amount'] = $cart_original_amount;
        $method_params['opt_currency'] = Configuration::get( self::CONFIG_PREFIX.'SURFEE_CURRENCY' );
        $method_params['opt_amount'] = Configuration::get( self::CONFIG_PREFIX.'SURFEE_AMOUNT' );

        if( empty( $cart_currency )
         or !($payment_methods_arr = $this->get_methods_for_country( null, $cart_currency, $method_params ))
         or empty( $payment_methods_arr['methods'] ) )
            return '';

        $this->S2P_add_css( $this->_path . '/views/css/style.css' );

        $display_options = array(
            'from_admin' => self::OPT_FEE_CURRENCY_ADMIN,
            'from_front' => self::OPT_FEE_CURRENCY_FRONT,

            'amount_separated' => self::OPT_FEE_AMOUNT_SEPARATED,
            'amount_total' => self::OPT_FEE_AMOUNT_TOTAL_FEE,
            'order_total' => self::OPT_FEE_AMOUNT_TOTAL_ORDER,
        );

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
            'display_options' => $display_options,
            'cart_amount' => $cart_original_amount,
            'config_opt_currency' => Configuration::get( self::CONFIG_PREFIX.'SURFEE_CURRENCY' ),
            'config_opt_amount' => Configuration::get( self::CONFIG_PREFIX.'SURFEE_AMOUNT' ),
            'default_currency' => Currency::getDefaultCurrency()->iso_code,
            'default_currency_id' => Currency::getDefaultCurrency()->id,
            'current_currency_id' => $this->context->currency->id,
            'methods_detected_currency' => $cart_currency->id,
            'payment_methods' => $payment_methods_arr['methods'],
            'methods_country' => self::$cache['methods_country'],
            's2p_module_obj' => $this,
        ));

        return $this->fetchTemplate( 'payment.tpl' );
    }

    public function detection_module_available()
    {
        if( !Module::isInstalled( self::S2P_DETECTOR_NAME )
         or (version_compare( _PS_VERSION_, '1.5', '>=' ) and !Module::isEnabled( self::S2P_DETECTOR_NAME )) )
            return false;

        return true;
    }

    public function payment_module_available()
    {
        if( !Module::isInstalled( $this->name )
         or (version_compare( _PS_VERSION_, '1.5', '>=' ) and !Module::isEnabled( $this->name )) )
            return false;

        return true;
    }

    public function detection_module_active()
    {
        if( !$this->detection_module_available()
         or !Configuration::get( self::S2PD_CONFIG_PREFIX.'ENABLED' ) )
            return false;

        return true;
    }

    public function payment_module_active()
    {
        if( !$this->payment_module_available()
         or !Configuration::get( self::CONFIG_PREFIX.'ENABLED' ) )
            return false;

        return true;
    }

    public function detect_country( $ip = false )
    {
        if( !($settings_arr = $this->getSettings())
         or empty( $settings_arr[self::CONFIG_PREFIX.'COUNTRY_DETECTION'] )
         or !$this->detection_module_active() )
        {
            $log_msg = '';
            if( empty( $settings_arr[self::CONFIG_PREFIX.'COUNTRY_DETECTION'] ) )
                $log_msg .= 'Coutry detection option is disabled in Smart2Pay module. ';
            if( !Module::isInstalled( self::S2P_DETECTOR_NAME ) )
                $log_msg .= 'Module Smart2Pay Detection is not installed.';
            elseif( version_compare( _PS_VERSION_, '1.5', '>=' )
                and (!Module::isEnabled( self::S2P_DETECTOR_NAME ) or !Configuration::get( self::S2PD_CONFIG_PREFIX.'ENABLED' )) )
                $log_msg .= 'Module Smart2Pay Detection is not enabled.';

            $this->writeLog( $log_msg, array( 'type' => 'detection' ) );

            return false;
        }

        if( empty( $ip ) )
            $ip = (!empty( $_SERVER['REMOTE_ADDR'] )?$_SERVER['REMOTE_ADDR']:'');

        if( empty( $ip ) )
            return false;

        $this->writeLog( 'Trying to detect country for IP ['.$ip.']', array( 'type' => 'detection' ) );

        if( !empty( self::$cache['detected_country_ip'] ) and self::$cache['detected_country_ip'] == $ip )
        {
            $this->writeLog( 'Cached country for IP ['.$ip.'] is ['.self::$cache['detected_country'].']', array( 'type' => 'detection' ) );
            return self::$cache['detected_country'];
        }

        /** @var Smart2paydetection $smart2pay_detection */
        if( !($smart2pay_detection = Module::getInstanceByName( self::S2P_DETECTOR_NAME )) )
        {
            $this->writeLog( 'Couldn\'t obtain Smart2Pay Detection instance. Make sure plugin is installed correctly.', array( 'type' => 'detection' ) );
            return false;
        }

        if( !($country_iso = $smart2pay_detection->get_country_iso( $ip )) )
        {
            $this->writeLog( 'Failed country detection for IP ['.$ip.'].', array( 'type' => 'detection' ) );
            return false;
        }

        self::$cache['detected_country_ip'] = $ip;
        self::$cache['detected_country'] = $country_iso;

        return self::$cache['detected_country'];
    }

    /**
     * Get module settings
     *
     * @return array
     */
    public function getSettings( Order $order = null )
    {
        static $settings = false;

        if( !empty( $settings ) )
            return $settings;

        $id_shop_group = null;
        $id_shop = null;
        $id_lang = null;

        if( empty( $order )
         or !Validate::isLoadedObject( $order ) )
        {
            $order = null;
        } elseif( version_compare( _PS_VERSION_, '1.5', '>=' ) && Shop::isFeatureActive() )
        {
            $id_shop_group = $order->id_shop_group;
            $id_shop       = $order->id_shop;
        }

        $settings = array();

        foreach( $this->getConfigFormInputNames() as $settingName )
        {
            if( version_compare( _PS_VERSION_, '1.5', '<' ) )
                $settings[$settingName] = Configuration::get( $settingName, $id_lang );
            else
                $settings[$settingName] = Configuration::get( $settingName, $id_lang, $id_shop_group, $id_shop );
        }

        $env = Tools::strtoupper( $settings[self::CONFIG_PREFIX.'ENV'] );

        if( $env == 'DEMO' )
        {
            $settings[self::CONFIG_PREFIX.'SITE_ID'] = self::DEMO_SID;
            $settings['signature'] = self::DEMO_SIGNATURE;
            $settings['mid']       = self::DEMO_MID;
            $settings['posturl']   = self::DEMO_POSTURL;
        } else
        {
            $settings['signature'] = $settings[ self::CONFIG_PREFIX . 'SIGNATURE_' . $env ];
            $settings['mid']       = $settings[ self::CONFIG_PREFIX . 'MID_' . $env ];
            $settings['posturl']   = $settings[ self::CONFIG_PREFIX . 'POST_URL_' . $env ];
        }

        return $settings;
    }

    /**
     * Compute SHA256 Hash
     *
     * @param $message
     *
     * @return string
     */
    public function computeSHA256Hash( $message )
    {
        return hash( 'sha256', Tools::strtolower( $message ) );
    }

    /**
     * Compute Hash
     *
     * @param string $data
     * @param string $signature
     *
     * @return string
     */
    public function computeHash($data, $signature)
    {
        return $this->computeSHA256Hash($data . $signature);
    }

    /**
     * Create string to hash from data
     *
     * @param array $data
     *
     * @return string
     */
    public function createStringToHash(array $data = array())
    {
        $mappedData = array_map(
            function( $key, $value )
            {
                return $key . $value;
            },
            array_keys( $data ),
            $data
        );

        return join( '', $mappedData );
    }

    /**
     * Get module's logs wrapped in HTML tags (mainly used to be printed within admin configuration view of the module)
     *
     * @return mixed
     */
    public function getLogsHTML()
    {
        $this->create_context();

        $this->context->smarty->assign( array(
            'logs' => $this->getLogs( array( 'limit' => 10 ) )
        ) );

        return $this->fetchTemplate( '/views/templates/admin/logs.tpl' );
    }

    /**
     * Get logs
     *
     * @param bool $reduceToString  When set to true it returns a string instead of an array
     *
     * @return array|string
     */
    public function getLogs( $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['log_type'] ) )
            $params['log_type'] = false;

        if( empty( $params['order_id'] ) )
            $params['order_id'] = 0;
        else
            $params['order_id'] = (int)$params['order_id'];
        if( empty( $params['limit'] ) )
            $params['limit'] = 0;
        else
            $params['limit'] = (int)$params['limit'];
        if( empty( $params['to_string'] ) )
            $params['to_string'] = false;

        $where_sql = '';
        if( !empty( $params['order_id'] ) )
            $where_sql .= ' AND order_id = \''.$params['order_id'].'\'';
        if( !empty( $params['log_type'] ) )
            $where_sql .= ' AND log_type = \''.pSQL( $params['log_type'] ).'\'';

        $logs = Db::getInstance()->executeS( 'SELECT * FROM `' . _DB_PREFIX_ . 'smart2pay_logs` '.
                                             (!empty( $where_sql )?' WHERE 1 '.$where_sql:'').
                                             ' ORDER BY log_created DESC, log_id DESC'.
                                             (!empty( $params['limit'] )?' LIMIT 0, '.$params['limit']:'') );

        if( empty( $logs ) )
            $logs = array();

        if( empty( $params['to_string'] ) )
            return $logs;

        $logsString = '';

        foreach( $logs as $log )
        {
            $logsString .= '[' . $log['log_type'] . '] '
                . '(' . $log['log_created'] . ') '
                . $log['log_data'] . ' '
                . $log['log_source_file'] . ':' . $log['log_source_file_line']
                . "\r\n";
        }

        return $logsString;
    }

    /**
     * Write log
     *
     * @param string $message
     * @param string $type
     */
    public function writeLog( $message, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['type'] ) )
            $params['type'] = 'info';

        if( empty( $params['order_id'] ) )
            $params['order_id'] = 0;
        else
            $params['order_id'] = (int)$params['order_id'];

        $backtrace = debug_backtrace();
        $file = $backtrace[0]['file'];
        $line = $backtrace[0]['line'];

        $query = "INSERT INTO `" . _DB_PREFIX_ . "smart2pay_logs`
                    (order_id, log_data, log_type, log_source_file, log_source_file_line)
                  VALUES
                    ('".$params['order_id']."', '" . pSQL( $message ) . "', '" . pSQL( $params['type'] ) . "', '" . $file . "', '" . $line . "')
        ";

        Db::getInstance()->execute( $query );
    }

    /**
     * Change order status
     *
     * @param OrderCore  $order
     * @param int        $statusId
     * @param bool       $sendCustomerEmail
     * @param array      $mailTemplateVars
     *
     * @return bool
     */
    public function changeOrderStatus( $order, $statusId )
    {
        $orderState = new OrderState((int) $statusId);

        if( !Validate::isLoadedObject( $order ) )
        {
            $this->writeLog( 'Can not apply order state #' . $statusId . ' to order - Order cannot be loaded', array( 'type' => 'error' ) );
            return false;
        }

        if( !Validate::isLoadedObject( $orderState ) )
        {
            $this->writeLog( 'Can not apply order state #' . $statusId . ' to order #' . $order->id . ' - Order state cannot be loaded', array( 'type' => 'error', 'order_id' => $order->id ) );
            return false;
        }

        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
        {
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState( $statusId, (int)($order->id) );
            $history->add();
        } else
        {
            if( ($orders_collection = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'orders` WHERE `reference` = \''.pSQL( $order->reference ).'\'' )) )
            {
                /** @var Order $single_order */
                foreach( $orders_collection as $single_order_arr )
                {
                    if( !($single_order = new Order( $single_order_arr['id_order'] ))
                     or !Validate::isLoadedObject( $single_order ) )
                        continue;

                    $history = new OrderHistory();
                    $history->id_order = (int)$single_order->id;
                    $history->changeIdOrderState( $statusId, (int)($single_order->id) );
                    $history->add();
                }
            }
        }

        return true;
    }

    /**
     * @param Order $order
     * @param bool|array $params
     *
     * @return bool
     * @throws PrestaShopException
     */
    public function check_order_invoices( $order, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['automate_shipping'] ) )
            $params['automate_shipping'] = false;

        if( !Validate::isLoadedObject( $order ) )
        {
            $this->writeLog( 'Cannot generate invoices for order - Order cannot be loaded', array( 'type' => 'error' ) );
            return false;
        }

        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
        {
            $order->setInvoice( true );
            $this->writeLog( 'Order invoice generated.', array( 'order_id' => $order->id ) );

            // Order delivery depends on invoice generation
            //if( !empty( $params['automate_shipping'] ) )
            //{
            //    $order->setDelivery();
            //    $this->writeLog( 'Order delivery generated.', array( 'order_id' => $order->id ) );
            //}
        } else
        {
            if( ($orders_collection = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'orders` WHERE `reference` = \''.pSQL( $order->reference ).'\'' )) )
            {
                /** @var Order $single_order */
                foreach( $orders_collection as $single_order_arr )
                {
                    if( !($single_order = new Order( $single_order_arr['id_order'] ))
                     or !Validate::isLoadedObject( $single_order ) )
                        continue;

                    $single_order->setInvoice( true );
                    $this->writeLog( 'Order invoice generated.', array( 'order_id' => $single_order->id ) );

                    // Order delivery depends on invoice generation
                    //if( !empty( $params['automate_shipping'] ) )
                    //{
                    //    $order->setDelivery();
                    //    $this->writeLog( 'Order delivery generated.', array( 'order_id' => $single_order->id ) );
                    //}
                }
            }
        }

        return true;
    }

    /**
     * Fetch template method - cross version implementation
     *
     * @param $name
     *
     * @return mixed
     */
    public function fetchTemplate( $name )
    {
        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
            $this->create_context();

        if( version_compare( _PS_VERSION_, '1.6', '<' ) )
        {
            $views = 'views/templates/';
            if (@filemtime(dirname(__FILE__).'/'.$name))
                return $this->display(__FILE__, $name);
            elseif (@filemtime(dirname(__FILE__).'/'.$views.'hook/'.$name))
                return $this->display(__FILE__, $views.'hook/'.$name);
            elseif (@filemtime(dirname(__FILE__).'/'.$views.'front/'.$name))
                return $this->display(__FILE__, $views.'front/'.$name);
            elseif (@filemtime(dirname(__FILE__).'/'.$views.'admin/'.$name))
                return $this->display(__FILE__, $views.'admin/'.$name);
        }

        return $this->display( __FILE__, $name );
    }

    /**
     * Set or retrieve current country ISO 2 chars code for payment method country
     *
     * @param null|string $country_iso If parameter is null will return current value for force country (false by
     *     default)
     *
     * @return bool|string Returns current settings for force country as ISO 2 chars or false if error or nothing set
     *     yet
     */
    public function force_country( $country_iso = null )
    {
        if( is_null( $country_iso ) )
            return self::$cache['force_country'];

        $country_iso = Tools::strtoupper( trim( $country_iso ) );
        if( !Country::getByIso( $country_iso ) )
            return false;

        self::$cache['force_country'] = $country_iso;
        return self::$cache['force_country'];
    }

    /**
     * Get Smart2Pay countries list
     *
     * @return array
     */
    public function get_smart2pay_id_countries()
    {
        if( !empty( self::$maintenance_functionality ) )
            return array();

        if( !empty( self::$cache['all_id_countries'] ) )
            return self::$cache['all_id_countries'];

        $this->get_smart2pay_countries();

        return self::$cache['all_id_countries'];
    }

    /**
     * Get Smart2Pay countries list
     *
     * @return array
     */
    public function get_smart2pay_countries()
    {
        if( !empty( self::$maintenance_functionality ) )
            return array();
        
        if( !empty( self::$cache['all_countries'] ) )
            return self::$cache['all_countries'];

        $country_rows = Db::getInstance()->executeS(
            'SELECT * '.
            ' FROM '._DB_PREFIX_.'smart2pay_country '.
            ' ORDER BY name'
        );

        self::$cache['all_countries'] = array();
        self::$cache['all_id_countries'] = array();

        if( empty( $country_rows ) )
            return array();

        foreach( $country_rows as $country_arr )
        {
            self::$cache['all_countries'][$country_arr['code']] = $country_arr['name'];
            self::$cache['all_id_countries'][$country_arr['country_id']] = array( 'code' => $country_arr['code'], 'name' => $country_arr['name'] );
        }

        return self::$cache['all_countries'];
    }

    /**
     * Get all defined Smart 2 Pay methods which are active. Result is cached per method id.
     *
     * @return array
     */
    public function get_all_methods()
    {
        if( !empty( self::$cache['all_method_details_in_cache'] ) and !empty( self::$cache['method_details'] ) )
            return self::$cache['method_details'];

        self::$cache['method_details'] = array();

        if( ($methods = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'smart2pay_method` WHERE `active` = 1 ORDER BY `display_name` ASC' )) )
        {
            foreach( $methods as $method_arr )
            {
                self::$cache['method_details'][$method_arr['method_id']] = $method_arr;
            }
        }

        self::$cache['all_method_details_in_cache'] = true;

        return self::$cache['method_details'];
    }

    /**
     * Get payment methods settings. Result is cached per id.
     *
     * @return array
     */
    public function get_all_method_settings()
    {
        $this->create_context();

        if( !empty( self::$cache['all_method_settings_in_cache'] ) and !empty( self::$cache['method_settings'] ) )
            return self::$cache['method_settings'];

        self::$cache['method_settings'] = array();

        $default_currency = null;
        if( !empty( $this->context->currency ) )
            $default_currency = $this->context->currency;

        if( ($methods = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'smart2pay_method_settings` ORDER BY `priority` ASC' )) )
        {
            foreach( $methods as $method_arr )
            {
                $method_currency = $default_currency->id;
                if( !empty( $method_arr['surcharge_currency'] )
                and ($ccurrency_id = Currency::getIdByIsoCode( $method_arr['surcharge_currency'] )) )
                    $method_currency = $ccurrency_id;

                $method_arr['surcharge_currency_id'] = $method_currency;

                self::$cache['method_settings'][$method_arr['method_id']] = $method_arr;
            }
        }

        self::$cache['all_method_settings_in_cache'] = true;

        return self::$cache['method_settings'];
    }

    /**
     * Get payment method details. Result is cached.
     *
     * @param $method_id
     *
     * @return array|null
     */
    public function get_method_details( $method_id )
    {
        $method_id = (int)$method_id;
        if( array_key_exists( $method_id, self::$cache['method_details'] ) )
            return self::$cache['method_details'][$method_id];

        $method = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'smart2pay_method` WHERE `method_id` = \'' . $method_id . '\' LIMIT 0, 1' );

        if( empty( $method ) )
            return null;

        self::$cache['method_details'][$method_id] = $method[0];

        return self::$cache['method_details'][$method_id];
    }

    /**
     * Get countries of payment method.
     *
     * @param $method_id
     *
     * @return array|null
     */
    public function get_method_countries_all()
    {
        if( !empty( self::$cache['all_method_countries'] ) )
            return self::$cache['all_method_countries'];

        if( ($methods_countries = Db::getInstance()->executeS(

            'SELECT * '.
            ' FROM `'._DB_PREFIX_.'smart2pay_country_method` CM '.
            ' LEFT JOIN `'._DB_PREFIX_.'smart2pay_country` C ON C.country_id = CM.country_id '.
            ' ORDER BY CM.method_id ASC, C.name ASC'

            //'SELECT * FROM `'._DB_PREFIX_.'smart2pay_country_method` ORDER BY method_id ASC, `priority` ASC'
        )) )
        {
            foreach( $methods_countries as $method_country )
            {
                if( empty( $method_country ) or !is_array( $method_country )
                 or empty( $method_country['country_id'] ) or empty( $method_country['method_id'] ) )
                    continue;

                if( empty( self::$cache['all_method_countries'][$method_country['method_id']] ) )
                    self::$cache['all_method_countries'][$method_country['method_id']] = array();

                self::$cache['all_method_countries'][$method_country['method_id']][] = $method_country['country_id'];
            }
        }

        return self::$cache['all_method_countries'];
    }

    /**
     * Get countries of payment method.
     *
     * @param $method_id
     *
     * @return array|null
     */
    public function get_method_countries( $method_id )
    {
        if( empty( self::$cache['all_method_countries'] ) )
            $this->get_method_countries_all();

        $method_id = (int)$method_id;
        if( array_key_exists( $method_id, self::$cache['all_method_countries'] ) )
            return self::$cache['all_method_countries'][$method_id];

        return false;
    }

    /**
     * Get payment method settings. Result is cached.
     *
     * @param $method_id
     *
     * @return array|null
     */
    public function get_method_settings( $method_id )
    {
        $method_id = (int)$method_id;
        if( array_key_exists( $method_id, self::$cache['method_settings'] ) )
            return self::$cache['method_settings'][$method_id];

        $method = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'smart2pay_method_settings` WHERE `method_id` = \'' . $method_id . '\' LIMIT 0, 1' );

        if( empty( $method ) )
            return null;

        self::$cache['method_settings'][$method_id] = $method[0];

        return self::$cache['method_settings'][$method_id];
    }

    public static function transaction_fields()
    {
        return array(
            'method_id' => 0,
            'payment_id' => 0,
            'order_id' => 0,
            'site_id' => 0,
            'environment' => '',
            'extra_data' => '',
            'surcharge_amount' => 0.0,
            'surcharge_percent' => 0.0,
            'surcharge_currency' => '',
            'surcharge_order_amount' => 0.0,
            'surcharge_order_percent' => 0.0,
            'surcharge_order_currency' => '',
            'payment_status' => 0,
        );
    }

    public static function validate_transaction_fields( $transaction_arr )
    {
        if( empty( $transaction_arr ) or !is_array( $transaction_arr ) )
            $transaction_arr = array();

        $default_values = self::transaction_fields();
        foreach( $default_values as $key => $default )
        {
            if( !array_key_exists( $key, $transaction_arr ) )
                $transaction_arr[$key] = $default;

            else
            {
                if( is_int( $default ) )
                    $transaction_arr[$key] = (int)$transaction_arr[$key];
                elseif( is_float( $default ) )
                    $transaction_arr[$key] = (float)$transaction_arr[$key];
                elseif( is_string( $default ) )
                    $transaction_arr[$key] = trim( $transaction_arr[$key] );
            }
        }

        return $transaction_arr;
    }

    public function get_transaction_by_order_id( $order_id )
    {
        $order_id = (int)$order_id;
        if( empty( $order_id )
         or !($transaction_arr = Db::getInstance()->executeS(
                'SELECT * FROM `'._DB_PREFIX_.'smart2pay_transactions` WHERE order_id = \'' . $order_id . '\' LIMIT 0, 1'
            ))
         or empty( $transaction_arr[0] ) )
            return false;

        return $transaction_arr[0];
    }

    public function save_transaction( $params )
    {
        if( empty( $params['order_id'] )
         or !(int)$params['order_id'] )
            return false;

        if( !empty( $params['extra_data'] ) )
        {
            if( is_string( $params['extra_data'] ) )
                $params['extra_data'] = Smart2Pay_Helper::parse_string( $params['extra_data'] );

            $params['extra_data'] = Smart2Pay_Helper::prepare_data( Smart2Pay_Helper::to_string( $params['extra_data'] ) );
        }

        $transaction_arr = false;
        if( ($existing_transaction = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'smart2pay_transactions` WHERE order_id = \'' . $params['order_id'] . '\' LIMIT 0, 1' ))
        and !empty( $existing_transaction[0] ) )
        {
            $transaction_arr = $existing_transaction[0];

            // Get only transaction keys from function parameters
            $default_values = self::transaction_fields();
            $edit_arr = array();
            foreach( $default_values as $key => $default )
            {
                if( !array_key_exists( $key, $params ) )
                    continue;

                if( is_int( $default ) )
                    $params[$key] = (int)$params[$key];
                elseif( is_float( $default ) )
                    $params[$key] = (float)$params[$key];
                elseif( is_string( $default ) )
                    $params[$key] = trim( $params[$key] );

                $edit_arr[$key] = $params[$key];
            }

            if( !empty( $edit_arr ) )
            {
                // $edit_arr['last_update'] = array( 'type' => 'sql', 'value' => 'NOW()' );
                $edit_arr['last_update'] = array( 'raw_field' => true, 'value' => 'NOW()' );

                //if( Db::getInstance()->update( 'smart2pay_transactions', $edit_arr, 'id = \''.$transaction_arr['id'].'\'', 0, false, false ) )
                if( ($sql = Smart2Pay_Helper::quick_edit( _DB_PREFIX_.'smart2pay_transactions', $edit_arr ))
                and Db::getInstance()->execute( $sql.' WHERE id = \''.$transaction_arr['id'].'\'' ) )
                {
                    $transaction_arr['last_update'] = time();

                    unset( $edit_arr['last_update'] );

                    foreach( $edit_arr as $key => $val )
                        $transaction_arr[$key] = $val;
                }
            }
        } else
        {
            $params = self::validate_transaction_fields( $params );

            // Get only transaction keys from function parameters
            $default_values = self::transaction_fields();
            $insert_arr = array();
            foreach( $default_values as $key => $val )
                $insert_arr[$key] = $params[$key];

            //$insert_arr['last_update'] = array( 'type' => 'sql', 'value' => 'NOW()' );
            //$insert_arr['created'] = array( 'type' => 'sql', 'value' => 'NOW()' );
            $insert_arr['last_update'] = array( 'raw_field' => true, 'value' => 'NOW()' );
            $insert_arr['created'] = array( 'raw_field' => true, 'value' => 'NOW()' );

            // In 1.4 Mysql->insert() is not defined...
            // if( Db::getInstance()->insert( 'smart2pay_transactions', array( $insert_arr ), false, false ) )
            if( ($sql = Smart2Pay_Helper::quick_insert( _DB_PREFIX_.'smart2pay_transactions', $insert_arr ))
            and Db::getInstance()->execute( $sql ) )
            {
                $insert_arr['last_update'] = $insert_arr['created'] = time();

                $insert_arr['id'] = Db::getInstance()->Insert_ID();

                $transaction_arr = $insert_arr;
            }

        }

        return $transaction_arr;
    }

    public static function transaction_logger_params_to_title()
    {
        return array(
            'AccountHolder' => 'Account Holder',
            'BankName' => 'Bank Name',
            'AccountNumber' => 'Account Number',
            'IBAN' => 'IBAN',
            'SWIFT_BIC' => 'SWIFT / BIC',
            'AccountCurrency' => 'Account Currency',

            'EntityNumber' => 'Entity Number',

            'ReferenceNumber' => 'Reference Number',
            'AmountToPay' => 'Amount To Pay',
        );
    }

    /**
     * Keys in returning array should be variable names sent back by Smart2Pay and values should be default values if
     * variables are not found in request
     *
     * @return array
     */
    public static function defaultTransactionLoggerExtraParams()
    {
        return array(
            // Method ID 1 (Bank transfer)
            'AccountHolder' => '',
            'BankName' => '',
            'AccountNumber' => '',
            'IBAN' => '',
            'SWIFT_BIC' => '',
            'AccountCurrency' => '',

            // Method ID 20 (Multibanco SIBS)
            'EntityNumber' => '',

            // Common to method id 20 and 1
            'ReferenceNumber' => '',
            'AmountToPay' => '',
        );
    }

    public static function validateTransactionLoggerExtraParams( $params_arr )
    {
        if( empty( $params_arr ) or !is_array( $params_arr ) )
            return array();

        $default_values = self::defaultTransactionLoggerExtraParams();
        $new_params_arr = array();
        foreach( $default_values as $key => $val )
        {
            if( !array_key_exists( $key, $params_arr ) )
                continue;

            if( is_int( $val ) )
                $new_val = (int)$params_arr[$key];
            elseif( is_string( $val ) )
                $new_val = trim( $params_arr[$key] );
            else
                $new_val = $params_arr[$key];

            if( $new_val === $val )
                continue;

            $new_params_arr[$key] = $new_val;
        }

        return $new_params_arr;
    }

    /**
     * Save payment method settings
     *
     * @param $method_id
     *
     * @return array|null
     */
    public function save_method_settings( $method_id, $params )
    {
        $method_id = (int)$method_id;
        if( empty( $method_id )
         or empty( $params ) or !is_array( $params ) )
            return false;

        if( isset( $params['enabled'] ) )
            $params['enabled'] = (!empty( $params['enabled'] )?1:0);
        if( isset( $params['surcharge_amount'] ) )
            $params['surcharge_amount'] = (float)$params['surcharge_amount'];
        if( isset( $params['surcharge_percent'] ) )
            $params['surcharge_percent'] = (float)$params['surcharge_percent'];
        if( isset( $params['priority'] ) )
            $params['priority'] = (int)$params['priority'];

        $new_settings_arr = array();
        if( !($current_settings = $this->get_method_settings( $method_id )) )
        {
            // if payment method is new and we don't have a currency set return error...
            if( empty( $params['surcharge_currency'] ) )
                return false;

            // we should insert...
            if( empty( $params['surcharge_amount'] ) )
                $params['surcharge_amount'] = (float)0;
            if( empty( $params['surcharge_percent'] ) )
                $params['surcharge_percent'] = (float)0;
            if( !isset( $params['enabled'] ) )
                $params['enabled'] = 1;
            if( !isset( $params['priority'] ) )
                $params['priority'] = 10;

            $insert_arr = array();
            $insert_arr['method_id'] = $method_id;
            $insert_arr['enabled'] = $params['enabled'];
            $insert_arr['surcharge_percent'] = $params['surcharge_percent'];
            $insert_arr['surcharge_amount'] = $params['surcharge_amount'];
            $insert_arr['surcharge_currency'] = $params['surcharge_currency'];
            $insert_arr['priority'] = $params['priority'];

            //$insert_arr['last_update'] = array( 'type' => 'sql', 'value' => 'NOW()' );
            //$insert_arr['configured'] = array( 'type' => 'sql', 'value' => 'NOW()' );
            $insert_arr['last_update'] = array( 'raw_field' => true, 'value' => 'NOW()' );
            $insert_arr['configured'] = array( 'raw_field' => true, 'value' => 'NOW()' );

            //if( !Db::getInstance()->insert( 'smart2pay_method_settings', array( $insert_arr ), false, false ) )
            if( !($sql = Smart2Pay_Helper::quick_insert( _DB_PREFIX_.'smart2pay_method_settings', $insert_arr ))
             or !Db::getInstance()->execute( $sql ) )
                $new_settings_arr = false;

            else
            {
                $insert_arr['last_update'] = $insert_arr['configured'] = time();

                $insert_arr['id'] = Db::getInstance()->Insert_ID();

                self::$cache['method_settings'][ $method_id ] = $insert_arr;

                $new_settings_arr = $insert_arr;
            }

        } else
        {
            // we edit...
            $edit_arr = array();
            if( isset( $params['enabled'] ) )
                $current_settings['enabled'] = $edit_arr['enabled'] = (!empty( $params['enabled'] )?1:0);
            if( isset( $params['surcharge_percent'] ) )
                $current_settings['surcharge_percent'] = $edit_arr['surcharge_percent'] = (float)$params['surcharge_percent'];
            if( isset( $params['surcharge_amount'] ) )
                $current_settings['surcharge_amount'] = $edit_arr['surcharge_amount'] = (float)$params['surcharge_amount'];
            if( isset( $params['surcharge_currency'] ) )
                $current_settings['surcharge_currency'] = $edit_arr['surcharge_currency'] = Tools::strtoupper( trim( $params['surcharge_currency'] ) );
            if( isset( $params['priority'] ) )
                $current_settings['priority'] = $edit_arr['priority'] = (int)$params['priority'];

            if( !empty( $edit_arr ) )
            {
                //$edit_arr['last_update'] = array( 'type' => 'sql', 'value' => 'NOW()' );
                $edit_arr['last_update'] = array( 'raw_field' => true, 'value' => 'NOW()' );
            }


            if( !empty( $edit_arr ) )
            {
                //if( !Db::getInstance()->update( 'smart2pay_method_settings', $edit_arr, 'method_id = \''.$method_id.'\'', 0, false, false ) )
                if( !($sql = Smart2Pay_Helper::quick_edit( _DB_PREFIX_.'smart2pay_method_settings', $edit_arr ))
                 or !Db::getInstance()->execute( $sql.' WHERE method_id = \''.$method_id.'\'' ) )
                    $new_settings_arr = false;

                else
                {
                    $current_settings['last_update'] = time();

                    self::$cache['method_settings'][ $method_id ] = $current_settings;

                    $new_settings_arr = $current_settings;
                }
            }
        }

        return $new_settings_arr;
    }

    protected function get_country_iso()
    {
        $this->create_context();

        $country_iso = false;

        if( ($forced_country = $this->force_country()) )
        {
            $country_iso = $forced_country;
            $this->writeLog( 'Using country ['.$country_iso.'] from programming restriction.', array( 'type' => 'detection' ) );
        }

        elseif( ($forced_config_country = Configuration::get( self::CONFIG_PREFIX.'FORCED_COUNTRY' )) )
        {
            $country_iso = $forced_config_country;
            $this->writeLog( 'Using country ['.$country_iso.'] from Force country field.', array( 'type' => 'detection' ) );
        }

        elseif( ($detected_country = $this->detect_country()) )
        {
            $country_iso = $detected_country;
            $this->writeLog( 'Using country ['.$country_iso.'] from detection.', array( 'type' => 'detection' ) );
        }

        elseif( ($fallback_country = Configuration::get( self::CONFIG_PREFIX.'FALLBACK_COUNTRY' )) )
        {
            $country_iso = $fallback_country;
            $this->writeLog( 'Using country ['.$country_iso.'] from fallback settings.', array( 'type' => 'detection' ) );
        }

        elseif( $this->context->cart )
        {
            $billing_address = new Address( $this->context->cart->id_address_invoice );
            $country = new Country( $billing_address->id_country );
            $country_iso = $country->iso_code;
            $this->writeLog( 'Using country ['.$country_iso.'] from billing address.', array( 'type' => 'detection' ) );
        }

        return $country_iso;
    }

    /**
     * Check if s2p method is available in some particular country
     *
     * @param int $method_id                 Method ID
     * @param null|string $countryISOCode   If no iso code is passed along, method checks if module can detect a
     *     country, else attempts to retrieve it from context->cart->id_address_invoice
     *
     * @return bool
     */
    public function get_methods_for_country( $country_iso = null, $currency_obj = null, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['cart_amount'] ) )
            $params['cart_amount'] = 0;
        if( empty( $params['opt_currency'] ) or !in_array( $params['opt_currency'], array( self::OPT_FEE_CURRENCY_FRONT, self::OPT_FEE_CURRENCY_ADMIN ) ) )
            $params['opt_currency'] = self::OPT_FEE_CURRENCY_FRONT;
        if( empty( $params['opt_amount'] ) or !in_array( $params['opt_amount'], array( self::OPT_FEE_AMOUNT_SEPARATED, self::OPT_FEE_AMOUNT_TOTAL_FEE, self::OPT_FEE_AMOUNT_TOTAL_ORDER ) ) )
            $params['opt_amount'] = self::OPT_FEE_AMOUNT_SEPARATED;

        /*
         * Check for base module to be active
         * Check for current module to be available
         */
        if( !Configuration::get( self::CONFIG_PREFIX.'ENABLED' ) )
            return false;

        $this->create_context();

        if( is_null( $country_iso ) )
        {
            if( !($country_iso = $this->get_country_iso()) )
                return false;
        }

        self::$cache['methods_country'] = $country_iso;

        $cookie = new Cookie( self::COOKIE_NAME );
        $cookie->last_country = $country_iso;
        $cookie->write();

        $this->writeLog( 'Getting list of methods for country ['.$country_iso.']', array( 'type' => 'detection' ) );

        if( !($country_method_ids = Db::getInstance()->executeS(
                    'SELECT CM.method_id '.
                    ' FROM '._DB_PREFIX_.'smart2pay_country_method CM '.
                    ' LEFT JOIN '._DB_PREFIX_.'smart2pay_country C ON C.country_id = CM.country_id '.
                    ' WHERE C.code = \''.pSQL( $country_iso ).'\''
                )) )
            return false;

        $all_methods_arr = $this->get_all_methods();
        $all_methods_settings_arr = $this->get_all_method_settings();

        $priority_methods_arr = array();
        foreach( $country_method_ids as $db_row )
        {
            $method_id = (int)$db_row['method_id'];
            if( empty( $method_id )
             or empty( $all_methods_arr[$method_id] )
             or empty( $all_methods_settings_arr[$method_id] )
             or empty( $all_methods_arr[$method_id]['active'] )
             or empty( $all_methods_settings_arr[$method_id]['enabled'] ) )
                continue;

            if( empty( $priority_methods_arr[$all_methods_settings_arr[$method_id]['priority']] ) )
                $priority_methods_arr[$all_methods_settings_arr[$method_id]['priority']] = array();

            $priority_methods_arr[$all_methods_settings_arr[$method_id]['priority']][] = $method_id;
        }

        if( empty( $priority_methods_arr ) )
            return false;

        ksort( $priority_methods_arr );

        if( is_null( $currency_obj ) or !is_object( $currency_obj ) )
            $currency_obj = $this->context->currency;
        if( is_null( $currency_obj ) )
            $currency_obj = Currency::getDefaultCurrency();

        if( !($all_currencies_arr = Currency::getCurrencies( true )) )
            $all_currencies_arr = array();

        $all_currencies_iso_arr = array();
        foreach( $all_currencies_arr as $curr_obj )
        {
            $all_currencies_iso_arr[Tools::strtoupper($curr_obj->iso_code)] = $curr_obj;
        }

        $country_methods_arr = array();
        $country_methods_arr['detected_currency'] = (!empty( $currency_obj )?$currency_obj:null);
        $country_methods_arr['methods'] = array();
        foreach( $priority_methods_arr as $priority_method_ids )
        {
            if( empty( $priority_method_ids ) or !is_array( $priority_method_ids ) )
                continue;

            foreach( $priority_method_ids as $method_id )
            {
                if( empty( $all_methods_settings_arr[ $method_id ]['surcharge_currency'] )
                 or empty( $all_currencies_iso_arr[Tools::strtoupper($all_methods_settings_arr[ $method_id ]['surcharge_currency'])] ) )
                    continue;

                $method_currency_obj = $all_currencies_iso_arr[Tools::strtoupper($all_methods_settings_arr[ $method_id ]['surcharge_currency'])];

                if( empty( $all_methods_settings_arr[ $method_id ]['surcharge_percent'] ) )
                    $all_methods_settings_arr[ $method_id ]['surcharge_percent'] = (float) 0;
                if( empty( $all_methods_settings_arr[ $method_id ]['surcharge_amount'] ) )
                    $all_methods_settings_arr[ $method_id ]['surcharge_amount'] = (float) 0;

                $country_methods_arr['methods'][ $method_id ]['method']   = $all_methods_arr[ $method_id ];
                $country_methods_arr['methods'][ $method_id ]['settings'] = $all_methods_settings_arr[ $method_id ];

                $cart_total_amount = $params['cart_amount'];
                if( $params['opt_currency'] == self::OPT_FEE_CURRENCY_ADMIN )
                    $cart_total_amount = Smart2Pay_Helper::convert_price( $cart_total_amount, $currency_obj, $method_currency_obj );

                $country_methods_arr['methods'][ $method_id ]['settings']['cart_amount'] = $cart_total_amount;

                $country_methods_arr['methods'][ $method_id ]['settings']['surcharge_percent_format'] = number_format( $all_methods_settings_arr[ $method_id ]['surcharge_percent'], 2, '.', '' );

                if( (float)$all_methods_settings_arr[ $method_id ]['surcharge_percent'] == 0 )
                    $country_methods_arr['methods'][ $method_id ]['settings']['surcharge_percent_amount'] = 0;
                else
                    $country_methods_arr['methods'][ $method_id ]['settings']['surcharge_percent_amount'] = Tools::ps_round( ( $cart_total_amount * $all_methods_settings_arr[ $method_id ]['surcharge_percent'] ) / 100, 2 );

                $country_methods_arr['methods'][ $method_id ]['settings']['surcharge_amount_converted'] = $all_methods_settings_arr[ $method_id ]['surcharge_amount'];
                $country_methods_arr['methods'][ $method_id ]['settings']['surcharge_currency_id'] = $method_currency_obj->id;

                if( !empty( $currency_obj )
                and $currency_obj->id != $method_currency_obj->id
                and (float)$all_methods_settings_arr[ $method_id ]['surcharge_amount'] != 0 )
                    $country_methods_arr['methods'][ $method_id ]['settings']['surcharge_amount_converted'] = Smart2Pay_Helper::convert_price( $all_methods_settings_arr[ $method_id ]['surcharge_amount'], $method_currency_obj, $currency_obj );

                //$country_methods_arr['methods'][ $method_id ]['settings']['surcharge_amount_format']  = number_format( $all_methods_settings_arr[ $method_id ]['surcharge_amount'], 2, '.', '' );
            }
        }

        return $country_methods_arr;
    }

    /**
     * Check if s2p method is available in some particular country
     *
     * @param int $method_id                 Method ID
     * @param null|string $countryISOCode   If no iso code is passed along, method checks if module can detect a
     *     country, else attempts to retrieve it from context->cart->id_address_invoice
     *
     * @return bool
     */
    public function method_details_if_available( $method_id, $country_iso = null )
    {
        $method_id = (int)$method_id;
        if( empty( $method_id ) )
            return false;

        /*
         * Check for base module to be active
         * Check for current module to be available
         */
        if( !Configuration::get( self::CONFIG_PREFIX.'ENABLED' )
         or !($method_details = $this->get_method_details( $method_id ))
         or !($method_settings = $this->get_method_settings( $method_id ))
         or empty( $method_details['active'] )
         or empty( $method_settings['enabled'] ) )
            return false;

        if( is_null( $country_iso ) )
        {
            $cookie = new Cookie( self::COOKIE_NAME );
            if( isset( $_COOKIE[self::COOKIE_NAME] )
            and !empty( $cookie->last_country ) )
                $country_iso = $cookie->last_country;
        }

        if( is_null( $country_iso ) )
        {
            if( !($country_iso = $this->get_country_iso()) )
                return false;
        }

        $this->writeLog( 'Using method ID ['.$method_id.'] for country ['.$country_iso.'].', array( 'type' => 'detection' ) );

        $country_method = Db::getInstance()->executeS(
            'SELECT CM.method_id '.
            ' FROM '._DB_PREFIX_.'smart2pay_country_method CM '.
            ' LEFT JOIN '._DB_PREFIX_.'smart2pay_country C ON C.country_id = CM.country_id '.
            ' WHERE C.code = \''.pSQL( $country_iso ).'\' AND CM.method_id = ' . $method_id
        );

        /*
         * Check for method availability within current country
         */
        if( empty( $country_method ) )
            return false;

        return array(
            'method_details' => $method_details,
            'method_settings' => $method_settings,
            'country_iso' => $country_iso,
        );
    }

    /**
     * Get Config Form Select Input Options
     *
     * @param null $name
     * @return array
     */
    public function getConfigFormSelectInputOptions( $name, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        switch( $name )
        {
            default:
                return array();

            case 'envs':
                return array(
                    array(
                        'id' => 'demo',
                        'name' => $this->l( 'Demo' ),
                    ),
                    array(
                        'id' => 'test',
                        'name' => $this->l( 'Test' ),
                    ),
                    array(
                        'id' => 'live',
                        'name' => $this->l( 'Live' ),
                    ),
                );

            case 'yesno':
                return array(
                    array(
                        'id' => 0,
                        'name' => $this->l( 'No' ),
                    ),
                    array(
                        'id' => 1,
                        'name' => $this->l( 'Yes' ),
                    ),
                );

            case 'fee_currency':
                return array(
                    array(
                        'id' => self::OPT_FEE_CURRENCY_FRONT,
                        'name' => $this->l( 'Setup in front-end' ),
                    ),
                    array(
                        'id' => self::OPT_FEE_CURRENCY_ADMIN,
                        'name' => $this->l( 'Used in payment method setup' ),
                    ),
                );

            case 'fee_display':
                return array(
                    array(
                        'id' => self::OPT_FEE_AMOUNT_SEPARATED,
                        'name' => $this->l( 'Separated percent and fixed amount' ),
                    ),
                    array(
                        'id' => self::OPT_FEE_AMOUNT_TOTAL_FEE,
                        'name' => $this->l( 'As sum of percent and fixed amount' ),
                    ),
                    array(
                        'id' => self::OPT_FEE_AMOUNT_TOTAL_ORDER,
                        'name' => $this->l( 'As total amount for the order' ),
                    ),
                );

            case 'country_list':
                if( !($countries_list = $this->get_smart2pay_countries())
                    or !is_array( $countries_list ) )
                    return array();

                if( empty( $params['no_option_title'] ) )
                    $params['no_option_title'] = $this->l( '- No Option -' );

                $return_arr = array();
                $return_arr[] = array( 'id' => '', 'name' => $params['no_option_title'] );

                foreach( $countries_list as $code => $name )
                {
                    $return_arr[] = array(
                        'id' => $code,
                        'name' => $name.' ['.$code.']',
                    );
                }

                return $return_arr;
        }
    }

    /**
     * Get an array containing order statuses parameters based on s2p payment state
     *
     * @return array
     */
    private function getPaymentStatesOrderStatuses()
    {
        return array(
            'new' => array(
                'configName' => self::CONFIG_PREFIX.'NEW_ORDER_STATUS',
                'orderStatusName' => 'Smart2Pay - Awaiting payment',
                'icon' => 'awaiting.gif',
            ),
            'success' => array(
                'configName' => self::CONFIG_PREFIX.'ORDER_STATUS_ON_SUCCESS',
                'orderStatusName' => 'Smart2Pay - Successfully paid',
                'icon' => 'success.gif',
            ),
            'canceled' => array(
                'configName' => self::CONFIG_PREFIX.'ORDER_STATUS_ON_CANCEL',
                'orderStatusName' => 'Smart2Pay - Canceled payment',
                'icon' => 'canceled.gif',
            ),
            'failed' => array(
                'configName' => self::CONFIG_PREFIX.'ORDER_STATUS_ON_FAIL',
                'orderStatusName' => 'Smart2Pay - Failed payment',
                'icon' => 'failed.gif',
            ),
            'expired' => array(
                'configName' => self::CONFIG_PREFIX.'ORDER_STATUS_ON_EXPIRE',
                'orderStatusName' => 'Smart2Pay - Expired payment',
                'icon' => 'expired.gif',
            ),
        );
    }

    /**
     * Get Config Form Input Names
     *
     * @return array
     */
    private function getConfigFormInputNames()
    {
        $names = array();

        foreach( $this->getConfigFormInputs() as $input )
            $names[] = $input['name'];

        return $names;
    }

    /**
     * Get Config Form Inputs
     *
     * @return array
     */
    private function getConfigFormInputs()
    {
        $this->create_context();

        /*
        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
        {
            global $cookie;

            if( $cookie )
                $lang_id = (int) $cookie->id_lang;
            else
                $lang_id = 1;
        } else
            $lang_id = (int)$this->context->language->id;
        */

        $lang_id = (int)$this->context->language->id;

        return array(
            array(
                'type' => 'select',
                'label' => $this->l('Enabled'),
                'name' => self::CONFIG_PREFIX.'ENABLED',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 1,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Environment'),
                'name' => self::CONFIG_PREFIX.'ENV',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('envs'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 'demo',
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Post URL Live'),
                'name' => self::CONFIG_PREFIX.'POST_URL_LIVE',
                'required' => true,
                'size' => '80',
                '_default' => 'https://api.smart2pay.com',
                '_validate' => array( 'url', 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Post URL Test'),
                'name' => self::CONFIG_PREFIX.'POST_URL_TEST',
                'required' => true,
                'size' => '80',
                '_default' => 'https://apitest.smart2pay.com',
                '_validate' => array( 'url', 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('MID Live'),
                'name' => self::CONFIG_PREFIX.'MID_LIVE',
                'required' => true,
                '_transform' => array( 'intval' ),
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('MID Test'),
                'name' => self::CONFIG_PREFIX.'MID_TEST',
                'required' => true,
                '_transform' => array( 'intval' ),
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Site ID'),
                'name' => self::CONFIG_PREFIX.'SITE_ID',
                '_transform' => array( 'intval' ),
                'required' => true,
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Skin ID'),
                'name' => self::CONFIG_PREFIX.'SKIN_ID',
                '_transform' => array( 'intval' ),
                'required' => true,
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Signature Live'),
                'name' => self::CONFIG_PREFIX.'SIGNATURE_LIVE',
                'required' => true,
                '_transform' => array( 'trim' ),
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Signature Test'),
                'name' => self::CONFIG_PREFIX.'SIGNATURE_TEST',
                'required' => true,
                '_transform' => array( 'trim' ),
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Return URL'),
                'name' => self::CONFIG_PREFIX.'RETURN_URL',
                'required' => true,
                'size' => '80',
                '_default' => Smart2Pay_Helper::get_return_url( $this->name ),
                'desc' => array(
                    $this->l( 'Default Return URL for this store configuration is: ' ),
                    $this->get_return_link(),
                    '',
                    $this->l( 'Notification URL for this store configuration is: ' ),
                    $this->get_notification_link(),
                ),
                '_validate' => array( 'url', 'notempty' ),
                '_transform' => array( 'trim' ),
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Force country'),
                'name' => self::CONFIG_PREFIX.'FORCED_COUNTRY',
                'desc' => array(
                    $this->l( 'If this option is selected Country detection will be disregarded.' ),
                    $this->l( 'NOTE: Please be sure all your clients can make payments in selected country.' ),
                ),
                'hint' => $this->l( 'Always use this country for payment module.' ),
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions( 'country_list', array( 'no_option_title' => $this->l( '- Don\'t force country -' ) ) ),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_transform' => array( 'trim', 'toupper' ),
                '_validate' => array( 'country_iso' ),
                '_default' => '',
            ),
            array(
                'type' => 'select',
                'label' => $this->l( 'Country detection' ),
                'name' => self::CONFIG_PREFIX.'COUNTRY_DETECTION',
                'desc' => array(
                    $this->l( 'Plugin will try detecting visitor\'s country by IP. Country is important for plugin as payment methods are displayed depending on country.' ),
                    $this->l( 'Country detection is available when you install and activate Smart2Pay Detection plugin.' ),
                    $this->l( 'If you select Yes and country detection plugin is not installed, plugin will use as fallback country set in customer\'s billing address.' ),
                ),
                'required' => false,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 0,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Fallback country'),
                'name' => self::CONFIG_PREFIX.'FALLBACK_COUNTRY',
                'hint' => $this->l( 'If country detection fails, use this country as fallback.' ),
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions( 'country_list', array( 'no_option_title' => $this->l( '- Country From Billing Address -' ) ) ),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_transform' => array( 'trim', 'toupper' ),
                '_validate' => array( 'country_iso' ),
                '_default' => '',
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Send order number as product description'),
                'name' => self::CONFIG_PREFIX.'SEND_ORDER_NUMBER',
                'required' => false,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 1,
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Custom product description'),
                'name' => self::CONFIG_PREFIX.'CUSTOM_PRODUCT_DESCRIPTION',
                'required' => true,
                'size' => '80',
                '_default' => 'Custom product description',
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Create invoice on success'),
                'name' => self::CONFIG_PREFIX.'CREATE_INVOICE_ON_SUCCESS',
                'required' => false,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 0,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Notify customer by email'),
                'name' => self::CONFIG_PREFIX.'NOTIFY_CUSTOMER_BY_EMAIL',
                'required' => false,
                'desc' => array(
                    $this->l( 'When payment is completed with success should system send an email to the customer?' ),
                ),
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 0,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Send payment instructions on order creation'),
                'name' => self::CONFIG_PREFIX.'SEND_PAYMENT_INSTRUCTIONS',
                'required' => false,
                'desc' => array(
                    $this->l( 'Some payment methods (like Bank Transfer and Multibanco SIBS) generate information required by costomer to complete the payment.' ),
                    $this->l( 'These informations are displayed to customer on return page, but plugin can also send an email to customer with these details.' ),
                ),
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 0,
            ),
            /*array(
                'type' => 'select',
                'label' => $this->l('Automate shipping'),
                'name' => self::CONFIG_PREFIX.'AUTOMATE_SHIPPING',
                'required' => false,
                'desc' => array(
                    $this->l( 'When payment is completed with success should system try to automate shipping?' ),
                    $this->l( 'Please note that automate shipping on yes needs Create invoice on success option on yes, as delivery depends on invoice creation.' ),
                ),
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 0,
            ),*/
            array(
                'type' => 'select',
                'label' => $this->l('Alter order total based on surcharge'),
                'name' => self::CONFIG_PREFIX.'ALTER_ORDER_ON_SURCHARGE',
                'required' => false,
                'desc' => array(
                    $this->l( 'When using a payment method which has a surcharge amount or percent set, order total will be incremented with resulting surcharge amount.' ),
                ),
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 0,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Surcharge will use currency (display only)'),
                'name' => self::CONFIG_PREFIX.'SURFEE_CURRENCY',
                'required' => false,
                'hint' => array(
                    $this->l( 'When displaying surcharge amount in checkout flow, what currency to use.' ),
                ),
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('fee_currency'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => self::OPT_FEE_CURRENCY_FRONT,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Surcharge display amount'),
                'name' => self::CONFIG_PREFIX.'SURFEE_AMOUNT',
                'required' => false,
                'hint' => array(
                    $this->l( 'How to display surcharge amount in checkout flow.' ),
                ),
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('fee_display'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => self::OPT_FEE_CURRENCY_FRONT,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('New Order Status'),
                'name' => self::CONFIG_PREFIX.'NEW_ORDER_STATUS',
                'required' => true,
                'options' => array(
                    'query' => OrderState::getOrderStates( $lang_id ),
                    'id' => 'id_order_state',
                    'name' => 'name',
                ),
                '_default' => 3,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Order status on SUCCESS'),
                'name' => self::CONFIG_PREFIX.'ORDER_STATUS_ON_SUCCESS',
                'required' => true,
                'options' => array(
                    'query' => OrderState::getOrderStates( $lang_id ),
                    'id' => 'id_order_state',
                    'name' => 'name',
                ),
                '_default' => 2,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Order status on CANCEL'),
                'name' => self::CONFIG_PREFIX.'ORDER_STATUS_ON_CANCEL',
                'required' => true,
                'options' => array(
                    'query' => OrderState::getOrderStates( $lang_id ),
                    'id' => 'id_order_state',
                    'name' => 'name',
                ),
                '_default' => 6,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Order status on FAIL'),
                'name' => self::CONFIG_PREFIX.'ORDER_STATUS_ON_FAIL',
                'required' => true,
                'options' => array(
                    'query' => OrderState::getOrderStates( $lang_id ),
                    'id' => 'id_order_state',
                    'name' => 'name',
                ),
                '_default' => 8,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Order status on EXPIRED'),
                'name' => self::CONFIG_PREFIX.'ORDER_STATUS_ON_EXPIRE',
                'required' => true,
                'options' => array(
                    'query' => OrderState::getOrderStates( $lang_id ),
                    'id' => 'id_order_state',
                    'name' => 'name',
                ),
                '_default' => 8,
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message SUCCESS'),
                'name' => self::CONFIG_PREFIX.'MESSAGE_SUCCESS',
                'required' => true,
                'size' => '80',
                '_default' => 'The payment succeeded',
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message FAILED'),
                'name' => self::CONFIG_PREFIX.'MESSAGE_FAILED',
                'required' => true,
                'size' => '80',
                '_default' => 'The payment process has failed',
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message CANCELED'),
                'name' => self::CONFIG_PREFIX.'MESSAGE_CANCELED',
                'required' => true,
                'size' => '80',
                '_default' => 'The payment was canceled',
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message PENDING'),
                'name' => self::CONFIG_PREFIX.'MESSAGE_PENDING',
                'required' => true,
                'size' => '80',
                '_default' => 'The payment is pending',
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Skip payment page'),
                'name' => self::CONFIG_PREFIX.'SKIP_PAYMENT_PAGE',
                'required' => false,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 0,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Redirect in iFrame'),
                'name' => self::CONFIG_PREFIX.'REDIRECT_IN_IFRAME',
                'required' => false,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 0,
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Debug Form'),
                'name' => self::CONFIG_PREFIX.'DEBUG_FORM',
                'required' => false,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 0,
            ),
        );
    }

    /**
     * Remove s2p database schema
     */
    private function uninstallDatabase()
    {
        Db::getInstance()->Execute( 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'smart2pay_logs`' );
        Db::getInstance()->Execute( 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'smart2pay_method`' );
        Db::getInstance()->Execute( 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'smart2pay_method_settings`' );
        Db::getInstance()->Execute( 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'smart2pay_country`' );
        Db::getInstance()->Execute( 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'smart2pay_country_method`' );
    }

    /**
     * Create and populate s2p database schema
     */
    private function installDatabase()
    {
        /*
         * Install module's database
         */
        if( !Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_transactions` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `method_id` int(11) NOT NULL DEFAULT '0',
                `payment_id` int(11) NOT NULL DEFAULT '0',
                `order_id` int(11) NOT NULL DEFAULT '0',
                `site_id` int(11) NOT NULL DEFAULT '0',
                `environment` varchar(20) DEFAULT NULL,
                `extra_data` text,
                `surcharge_amount` decimal(6,2) NOT NULL,
                `surcharge_currency` varchar(3) DEFAULT NULL COMMENT 'Currency ISO 3',
                `surcharge_percent` decimal(6,2) NOT NULL,
                `surcharge_order_amount` decimal(6,2) NOT NULL,
                `surcharge_order_percent` decimal(6,2) NOT NULL,
                `surcharge_order_currency` varchar(3) DEFAULT NULL COMMENT 'Currency ISO 3',
                `last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                 PRIMARY KEY (`id`), KEY `method_id` (`method_id`), KEY `payment_id` (`payment_id`), KEY `order_id` (`order_id`)
                ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8 COMMENT='Transactions run trough Smart2Pay';

        ") )
            return false;

        // New columns starting with plugin version 1.0.6
        $transactions_columns = array(
            array( 'name' => 'payment_status', 'type' => 'TINYINT(2) NOT NULL DEFAULT \'0\' COMMENT \'Status received from server\' AFTER `surcharge_order_currency`' ),
        );

        foreach( $transactions_columns as $column )
        {
            $sql = 'SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'smart2pay_transactions`
					LIKE \'' . pSQL($column['name']) . '\'';

            if( !Db::getInstance()->executeS( $sql ) )
            {
                $sql = 'ALTER TABLE `' . _DB_PREFIX_ . 'smart2pay_transactions`
						ADD `' . pSQL( $column['name'] ) . '` ' . $column['type'];

                Db::getInstance()->execute( $sql );
            }
        }

        if( !Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_method_settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `method_id` int(11) NOT NULL DEFAULT '0',
                `enabled` tinyint(2) NOT NULL DEFAULT '0',
                `surcharge_percent` decimal(6,2) NOT NULL DEFAULT '0.00',
                `surcharge_amount` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'Amount of surcharge',
                `surcharge_currency` varchar(3) DEFAULT NULL COMMENT 'ISO 3 currency code of fixed surcharge amount',
                `priority` tinyint(4) NOT NULL DEFAULT '10' COMMENT '1 means first',
                `last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                `configured` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY (`id`), KEY `method_id` (`method_id`), KEY `enabled` (`enabled`)
                ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8 COMMENT='Smart2Pay method configurations';
        ") )
            return false;

        Db::getInstance()->Execute( 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'smart2pay_logs`' );
        if( !Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_logs` (
                `log_id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) NOT NULL default '0',
                `log_type` varchar(255) default NULL,
                `log_data` text default NULL,
                `log_source_file` varchar(255) default NULL,
                `log_source_file_line` varchar(255) default NULL,
                `log_created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`log_id`), KEY `order_id` (`order_id`), KEY `log_type` (`log_type`)
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8;
        ") )
        {
            $this->uninstallDatabase();
            return false;
        }

        Db::getInstance()->Execute( "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_method`" );
        if( !Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_method` (
                `method_id` int(11) NOT NULL AUTO_INCREMENT,
                `display_name` varchar(255) default NULL,
                `provider_value` varchar(255) default NULL,
                `description` text ,
                `logo_url` varchar(255) default NULL,
                `guaranteed` int(1) default NULL,
                `active` int(1) default NULL,
                PRIMARY KEY (`method_id`), KEY `active` (`active`)
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8
        ") )
        {
            $this->uninstallDatabase();
            return false;
        }

        if( !Db::getInstance()->Execute("
            INSERT INTO `" . _DB_PREFIX_ . "smart2pay_method` (`method_id`, `display_name`, `provider_value`, `description`, `logo_url`, `guaranteed`, `active`) VALUES
            (1, 'Bank Transfer', 'banktransfer', 'Bank Transfer description', 'bank_transfer_logo_v6.png', 1, 1),
            (2, 'iDEAL', 'ideal', 'iDEAL description', 'ideal.png', 1, 1),
            (3, 'MrCash', 'mrcash', 'MrCash description', 'mrcash.png', 1, 1),
            (4, 'Giropay', 'giropay', 'Giropay description', 'giropay.png', 1, 1),
            (5, 'EPS', 'eps', 'EPS description', 'eps-e-payment-standard.png', 1, 1),
            (8, 'UseMyFunds', 'umb', 'UseMyFunds description', 'umb.png', 1, 1),
            (9, 'Sofort Banking', 'dp24', 'Sofort Banking description', 'dp24_sofort.png', 0, 1),
            (12, 'Przelewy24', 'p24', 'Przelewy24 description', 'p24.png', 1, 1),
            (13, 'OneCard', 'onecard', 'OneCard description', 'onecard.png', 1, 1),
            (14, 'CashU', 'cashu', 'CashU description', 'cashu.png', 1, 1),
            (18, 'POLi', 'poli', 'POLi description', 'poli.png', 0, 1),
            (19, 'DineroMail', 'dineromail', 'DineroMail description', 'dineromail.png', 0, 1),
            (20, 'Multibanco SIBS', 'sibs', 'Multibanco SIBS description', 'sibs_mb.png', 1, 1),
            (22, 'Moneta Wallet', 'moneta', 'Moneta Wallet description', 'moneta.png', 1, 1),
            (23, 'Paysera', 'paysera', 'Paysera description', 'paysera.gif', 1, 1),
            (24, 'Alipay', 'alipay', 'Alipay description', 'alipay.png', 1, 1),
            (25, 'Abaqoos', 'abaqoos', 'Abaqoos description', 'abaqoos.png', 1, 1),
            (27, 'ePlatby for eKonto', 'ebanka', 'eBanka description', 'eKonto.png', 1, 1),
            (28, 'Ukash', 'ukash', 'Ukash description', 'ukash.png', 1, 1),
            (29, 'Trustly', 'trustly', 'Trustly description', 'trustly.png', 1, 1),
            (32, 'Debito Banco do Brasil', 'debitobdb', 'Debito Banco do Brasil description', 'banco_do_brasil.gif', 1, 1),
            (33, 'CuentaDigital', 'cuentadigital', 'CuentaDigital description', 'cuentadigital.png', 1, 0),
            (34, 'CardsBrazil', 'cardsbrl', 'CardsBrazil description', 'cards_brl.gif', 0, 1),
            (35, 'PaysBuy', 'paysbuy', 'PaysBuy description', 'paysbuy.png', 0, 1),
            (36, 'Mazooma', 'mazooma', 'Mazooma description', 'mazooma.png', 0, 0),
            (37, 'eNETS Debit', 'enets', 'eNETS Debit description', 'enets.png', 1, 1),
            (40, 'Paysafecard', 'paysafecard', 'Paysafecard description', 'paysafecard.png', 1, 1),
            (42, 'PayPal', 'paypal', 'PayPal description', 'paypal.png', 1, 0),
            (43, 'PagTotal', 'pagtotal', 'PagTotal description', 'pagtotal.png', 0, 0),
            (44, 'Payeasy', 'payeasy', 'Payeasy description', 'payeasy.png', 1, 1),
            (46, 'MercadoPago', 'mercadopago', 'MercadoPago description', 'mercadopago.png', 0, 1),
            (47, 'Mozca', 'mozca', 'Mozca description', 'mozca.png', 0, 0),
            (49, 'ToditoCash', 'toditocash', 'ToditoCash description', 'todito_cash.png', 1, 1),
            (58, 'PayWithMyBank', 'pwmb', 'PayWithMyBank description', 'pwmb.png', 1, 1),
            (62, 'Tenpay', 'tenpay', 'Tenpay description', 'tenpay.png', 1, 1),
            (63, 'TrustPay', 'trustpay', 'TrustPay description', 'trustpay.png', 1, 1),
            (64, 'MangirKart', 'mangirkart', 'MangirKart description', 'mangir_cart.gif', 1, 1),
            (65, 'Finish Banks', 'paytrail', 'Paytrail description', 'paytrail.gif', 1, 1),
            (66, 'MTCPay', 'mtcpay', 'MTCPay description', 'mtcpay.png', 1, 1),
            (67, 'DragonPay', 'dragonpay', 'DragonPay description', 'dragon_pay.png', 1, 1),
            (69, 'Credit Card', 's2pcards', 'S2PCards Description', 's2p_cards.gif', 0, 1),
            (72, 'PagoEfectivo', 'pagoefectivo', 'PagoEfectivo Description', 'pago_efectivo.gif', 1, 1),
            (73, 'MyBank', 'mybank', 'MyBank Description', 'mybank.png', 1, 1),
            (74, 'Yandex.Money', 'yandexmoney', 'YandexMoney description', 'yandex_money.png', 1, 1),
            (75, 'Klarna Invoice', 'klarnainvoice', 'KlarnaInvoice description', 'klarna.gif', 1, 1),
            (76, 'Bitcoin', 'bitcoin', 'Bitcoin description', 'bitcoin.png', 1, 1),
            (77, 'VoguePay', 'voguepay', 'VoguePay Description', 'voguepay.gif', 1, 1),
            (78, 'Skrill', 'skrill', 'Skrill Description', 'skrill.jpg', 1, 1),
            (79, 'Pay by mobile', 'paybymobile', 'Pay by mobile Description', 'pay_by_mobile_v1.gif', 1, 1),
            (81, 'WebMoney Transfer', 'webmoneytransfer', 'WebMoney Transfer Description', 'webmoney.gif', 1, 1),
            (1000, 'Boleto', 'paganet', 'Boleto description', 'boleto_bancario.png', 1, 1),
            (1001, 'Debito', 'paganet', 'Debito description', 'debito_bradesco.png', 1, 0),
            (1002, 'Transferencia', 'paganet', 'Transferencia description', 'bradesco_transferencia.png', 1, 1),
            (1003, 'QIWI Wallet', 'qiwi', 'QIWI Wallet description', 'qiwi_wallet.png', 1, 1),
            (1004, 'Beeline', 'qiwi', 'Beeline description', 'beeline.png', 1, 1),
            (1005, 'Megafon', 'qiwi', 'Megafon description', 'megafon.png', 1, 1),
            (1006, 'MTS', 'qiwi', 'MTS description', 'mts.gif', 1, 1),
            (1007, 'WebMoney', 'moneta', 'WebMoney description', 'webmoney.png', 1, 0),
            (1008, 'Yandex', 'moneta', 'Yandex description', 'yandex.png', 1, 0),
            (1009, 'Alliance Online', 'asiapay', 'Alliance Online description', 'alliance_online.gif', 1, 0),
            (1010, 'AmBank', 'asiapay', 'AmBank description', 'ambank_group.png', 1, 1),
            (1011, 'CIMB Clicks', 'asiapay', 'CIMB Clicks description', 'cimb_clicks.png', 1, 1),
            (1012, 'FPX', 'asiapay', 'FPX description', 'fpx.png', 1, 1),
            (1013, 'Hong Leong Bank Transfer', 'asiapay', 'Hong Leong Bank Transfer description', 'hong_leong.png', 1, 1),
            (1014, 'Maybank2U', 'asiapay', 'Maybank2U description', 'maybank2u.png', 1, 1),
            (1015, 'Meps Cash', 'asiapay', 'Meps Cash description', 'meps_cash.png', 1, 1),
            (1016, 'Mobile Money', 'asiapay', 'Mobile Money description', 'mobile_money.png', 1, 1),
            (1017, 'RHB', 'asiapay', 'RHB description', 'rhb.png', 1, 1),
            (1018, 'Webcash', 'asiapay', 'Webcash description', 'web_cash.gif', 1, 0),
            (1019, 'Credit Cards Colombia', 'pagosonline', 'Credit Cards Colombia description', 'cards_colombia.gif', 1, 1),
            (1020, 'PSE', 'pagosonline', 'PSE description', 'pse.png', 1, 1),
            (1021, 'ACH Debit', 'pagosonline', 'ACH Debit description', 'ach.png', 1, 1),
            (1022, 'Via Baloto', 'pagosonline', 'Via Baloto description', 'payment_via_baloto.png', 1, 1),
            (1023, 'Referenced Payment', 'pagosonline', 'Referenced Payment description', 'payment_references.png', 1, 1),
            (1024, 'Mandiri', 'asiapay', 'Mandiri description', 'mandiri.png', 1, 1),
            (1025, 'XL Tunai', 'asiapay', 'XL Tunai description', 'xltunai.png', 1, 1),
            (1026, 'Bancomer Pago referenciado', 'dineromaildirect', 'Bancomer Pago referenciado description', 'bancomer.png', 1, 1),
            (1027, 'Santander Pago referenciado', 'dineromaildirect', 'Santander Pago referenciado description', 'santander.gif', 1, 1),
            (1028, 'ScotiaBank Pago referenciado', 'dineromaildirect', 'ScotiaBank Pago referenciado description', 'scotiabank.gif', 1, 1),
            (1029, '7-Eleven Pago en efectivo', 'dineromaildirect', '7-Eleven Pago en efectivo description', '7-Eleven.gif', 1, 1),
            (1030, 'Oxxo Pago en efectivo', 'dineromaildirect', 'Oxxo Pago en efectivo description', 'oxxo.gif', 1, 1),
            (1031, 'IXE Pago referenciado', 'dineromaildirect', 'IXE Pago referenciado description', 'IXe.gif', 1, 1),
            (1033, 'Cards Thailand', 'paysbuy', 'Cards Thailand description', 'cards_brl.gif', 1, 0),
            (1034, 'PayPal Thailand', 'paysbuy', 'PayPalThailand description', 'paypal.png', 1, 0),
            (1035, 'AMEXThailand', 'paysbuy', 'AMEXThailand description', 'american_express.png', 1, 0),
            (1036, 'Cash Options Thailand', 'paysbuy', 'Cash Options Thailand description', 'counter-service-thailand_paysbuy-cash.png', 1, 1),
            (1037, 'Online Banking Thailand', 'paysbuy', 'OnlineBankingThailand description', 'online_banking_thailanda.png', 1, 1),
            (1038, 'PaysBuy Wallet', 'paysbuy', 'PaysBuy Wallet description', 'paysbuy.png', 1, 1),
            (1039, 'Pagos en efectivo Chile', 'dineromaildirect', 'Pagos en efectivo Chile description', 'pagos_en_efectivo_servipag_bci_chile.png', 1, 1),
            (1040, 'Pagos en efectivo Argentina', 'dineromaildirect', 'Pagos en efectivo Argentina description', 'argentina_banks.png', 1, 0),
            (1041, 'OP-Pohjola', 'paytrail', 'OP-Pohjola description', 'op-pohjola.png', 1, 1),
            (1042, 'Nordea', 'paytrail', 'Nordea description', 'nordea.png', 1, 1),
            (1043, 'Danske bank', 'paytrail', 'Danske description', 'danske_bank.png', 1, 1),
            (1044, 'Cash-in', 'yandexmoney', 'Cash-in description', 'cashinyandex.gif', 1, 1),
            (1045, 'Cards Russia', 'yandexmoney', 'Cards Russia description', 's2p_cards.gif', 1, 1),
            (1048, 'BankTransfer Japan', 'degica', 'BankTransfer Japan description', 'degica_bank_transfer.gif', 1, 1),
            (1046, 'Konbini', 'degica', 'Konbini description', 'degica_kombini.png', 1, 1),
            (1047, 'Cards Japan', 'cardsjapan', 'Cards Japan Description', 'degica_cards.gif', 1, 1),
            (1049, 'PayEasy Japan', 'payeasyjapan', 'PayEasy Japan Description', 'degica_payeasy.gif', 1, 1),
            (1050, 'WebMoney Japan', 'webmoneyjapan', 'WebMoney Japan Description', 'degica_webmoney.gif', 1, 1),
            (1051, 'Globe GCash', 'dragonpay', 'Globe GCash description', 'gcashlogo.jpg', 1, 1),
            (1052, 'Klarna Checkout', 'klarnacheckout', 'Klarna Checkout Description', 'klarna_checkout.gif', 1, 1),
            (1053, 'Credit Cards Indonesia', 'creditcardsindonesia', 'Credit Cards Indonesia Description', '1053_credit_cards.gif', 1, 1),
            (1054, 'BII VA', 'biiva', 'BII VA Description', '1054_BII-VA.gif', 1, 1),
            (1055, 'Kartuku', 'kartuku', 'Kartuku Description', '1055_Kartuku.gif', 1, 1),
            (1056, 'CIMB Clicks', 'cimbclicks', 'CIMBClicks Description', '1056_Cimb_Clicks.gif', 1, 1),
            (1057, 'Mandiri e-Cash', 'mandiriecash', 'Mandiri e-Cash Description', '1057_Mandiri_ecash.gif', 1, 1),
            (1058, 'IB Muamalat', 'ibmuamalat', 'IB Muamalat Description', '1058_IB_Muamalat.gif', 1, 1),
            (1059, 'T-Cash', 'tcash', 'T-Cash Description', '1059_T-cash.gif', 1, 1),
            (1060, 'Indosat Dompetku', 'indosatdompetku', 'Indosat Dompetku Description', '1060_Indosat_Dompetku.gif', 1, 1),
            (1061, 'Mandiri ATM Automatic', 'mandiriatmautomatic', 'Mandiri ATM Automatic Description', '1061_Mandiri_atm_automatic.gif', 1, 1),
            (1062, 'Pay4ME', 'pay4me', 'Pay4ME Description', '1062_pay4me.gif', 1, 1),
            (1063, 'Danamon Online Banking', 'danamononlinebanking', 'Danamon Online Banking Description', '1063_Danamon.gif', 1, 1);
        ") )
        {
            $this->uninstallDatabase();
            return false;
        }

        Db::getInstance()->Execute( "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_country`" );
        if( !Db::getInstance()->Execute("
            CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_country` (
                `country_id` int(11) NOT NULL AUTO_INCREMENT,
                `code` varchar(3) default NULL,
                `name` varchar(100) default NULL,
                PRIMARY KEY (`country_id`)
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8
        ") )
        {
            $this->uninstallDatabase();
            return false;
        }


        if( !Db::getInstance()->Execute("
            INSERT INTO `" . _DB_PREFIX_ . "smart2pay_country` (`country_id`, `code`, `name`) VALUES
            (1, 'AD', 'Andorra'),
            (2, 'AE', 'United Arab Emirates'),
            (3, 'AF', 'Afghanistan'),
            (4, 'AG', 'Antigua and Barbuda'),
            (5, 'AI', 'Anguilla'),
            (6, 'AL', 'Albania'),
            (7, 'AM', 'Armenia'),
            (8, 'AN', 'Netherlands Antilles'),
            (9, 'AO', 'Angola'),
            (10, 'AQ', 'Antarctica'),
            (11, 'AR', 'Argentina'),
            (12, 'AS', 'American Samoa'),
            (13, 'AT', 'Austria'),
            (14, 'AU', 'Australia'),
            (15, 'AW', 'Aruba'),
            (16, 'AZ', 'Azerbaijan'),
            (17, 'BA', 'Bosnia & Herzegowina'),
            (18, 'BB', 'Barbados'),
            (19, 'BD', 'Bangladesh'),
            (20, 'BE', 'Belgium'),
            (21, 'BF', 'Burkina Faso'),
            (22, 'BG', 'Bulgaria'),
            (23, 'BH', 'Bahrain'),
            (24, 'BI', 'Burundi'),
            (25, 'BJ', 'Benin'),
            (26, 'BM', 'Bermuda'),
            (27, 'BN', 'Brunei Darussalam'),
            (28, 'BO', 'Bolivia'),
            (29, 'BR', 'Brazil'),
            (30, 'BS', 'Bahamas'),
            (31, 'BT', 'Bhutan'),
            (32, 'BV', 'Bouvet Island'),
            (33, 'BW', 'Botswana'),
            (34, 'BY', 'Belarus (formerly known as Byelorussia)'),
            (35, 'BZ', 'Belize'),
            (36, 'CA', 'Canada'),
            (37, 'CC', 'Cocos (Keeling) Islands'),
            (38, 'CD', 'Congo, Democratic Republic of the (formerly Zalre)'),
            (39, 'CF', 'Central African Republic'),
            (40, 'CG', 'Congo'),
            (41, 'CH', 'Switzerland'),
            (42, 'CI', 'Ivory Coast (Cote d''Ivoire)'),
            (43, 'CK', 'Cook Islands'),
            (44, 'CL', 'Chile'),
            (45, 'CM', 'Cameroon'),
            (46, 'CN', 'China'),
            (47, 'CO', 'Colombia'),
            (48, 'CR', 'Costa Rica'),
            (50, 'CU', 'Cuba'),
            (51, 'CV', 'Cape Verde'),
            (52, 'CX', 'Christmas Island'),
            (53, 'CY', 'Cyprus'),
            (54, 'CZ', 'Czech Republic'),
            (55, 'DE', 'Germany'),
            (56, 'DJ', 'Djibouti'),
            (57, 'DK', 'Denmark'),
            (58, 'DM', 'Dominica'),
            (59, 'DO', 'Dominican Republic'),
            (60, 'DZ', 'Algeria'),
            (61, 'EC', 'Ecuador'),
            (62, 'EE', 'Estonia'),
            (63, 'EG', 'Egypt'),
            (64, 'EH', 'Western Sahara'),
            (65, 'ER', 'Eritrea'),
            (66, 'ES', 'Spain'),
            (67, 'ET', 'Ethiopia'),
            (68, 'FI', 'Finland'),
            (69, 'FJ', 'Fiji Islands'),
            (70, 'FK', 'Falkland Islands (Malvinas)'),
            (71, 'FM', 'Micronesia, Federated States of'),
            (72, 'FO', 'Faroe Islands'),
            (73, 'FR', 'France'),
            (74, 'FX', 'France, Metropolitan'),
            (75, 'GA', 'Gabon'),
            (76, 'GB', 'United Kingdom'),
            (77, 'GD', 'Grenada'),
            (78, 'GE', 'Georgia'),
            (79, 'GF', 'French Guiana'),
            (80, 'GH', 'Ghana'),
            (81, 'GI', 'Gibraltar'),
            (82, 'GL', 'Greenland'),
            (83, 'GM', 'Gambia'),
            (84, 'GN', 'Guinea'),
            (85, 'GP', 'Guadeloupe'),
            (86, 'GQ', 'Equatorial Guinea'),
            (87, 'GR', 'Greece'),
            (88, 'GS', 'South Georgia and the South Sandwich Islands'),
            (89, 'GT', 'Guatemala'),
            (90, 'GU', 'Guam'),
            (91, 'GW', 'Guinea-Bissau'),
            (92, 'GY', 'Guyana'),
            (93, 'HK', 'Hong Kong'),
            (94, 'HM', 'Heard and McDonald Islands'),
            (95, 'HN', 'Honduras'),
            (96, 'HR', 'Croatia (local name: Hrvatska)'),
            (97, 'HT', 'Haiti'),
            (98, 'HU', 'Hungary'),
            (99, 'ID', 'Indonesia'),
            (100, 'IE', 'Ireland'),
            (101, 'IL', 'Israel'),
            (102, 'IN', 'India'),
            (103, 'IO', 'British Indian Ocean Territory'),
            (104, 'IQ', 'Iraq'),
            (105, 'IR', 'Iran, Islamic Republic of'),
            (106, 'IS', 'Iceland'),
            (107, 'IT', 'Italy'),
            (108, 'JM', 'Jamaica'),
            (109, 'JO', 'Jordan'),
            (110, 'JP', 'Japan'),
            (111, 'KE', 'Kenya'),
            (112, 'KG', 'Kyrgyzstan'),
            (113, 'KH', 'Cambodia (formerly Kampuchea)'),
            (114, 'KI', 'Kiribati'),
            (115, 'KM', 'Comoros'),
            (116, 'KN', 'Saint Kitts (Christopher) and Nevis'),
            (117, 'KP', 'Korea, Democratic People''s Republic of (North Korea)'),
            (118, 'KR', 'Korea, Republic of (South Korea)'),
            (119, 'KW', 'Kuwait'),
            (120, 'KY', 'Cayman Islands'),
            (121, 'KZ', 'Kazakhstan'),
            (122, 'LA', 'Lao People''s Democratic Republic (formerly Laos)'),
            (123, 'LB', 'Lebanon'),
            (124, 'LC', 'Saint Lucia'),
            (125, 'LI', 'Liechtenstein'),
            (126, 'LK', 'Sri Lanka'),
            (127, 'LR', 'Liberia'),
            (128, 'LS', 'Lesotho'),
            (129, 'LT', 'Lithuania'),
            (130, 'LU', 'Luxembourg'),
            (131, 'LV', 'Latvia'),
            (132, 'LY', 'Libyan Arab Jamahiriya'),
            (133, 'MA', 'Morocco'),
            (134, 'MC', 'Monaco'),
            (135, 'MD', 'Moldova, Republic of'),
            (136, 'MG', 'Madagascar'),
            (137, 'MH', 'Marshall Islands'),
            (138, 'MK', 'Macedonia, the Former Yugoslav Republic of'),
            (139, 'ML', 'Mali'),
            (140, 'MM', 'Myanmar (formerly Burma)'),
            (141, 'MN', 'Mongolia'),
            (142, 'MO', 'Macao (also spelled Macau)'),
            (143, 'MP', 'Northern Mariana Islands'),
            (144, 'MQ', 'Martinique'),
            (145, 'MR', 'Mauritania'),
            (146, 'MS', 'Montserrat'),
            (147, 'MT', 'Malta'),
            (148, 'MU', 'Mauritius'),
            (149, 'MV', 'Maldives'),
            (150, 'MW', 'Malawi'),
            (151, 'MX', 'Mexico'),
            (152, 'MY', 'Malaysia'),
            (153, 'MZ', 'Mozambique'),
            (154, 'NA', 'Namibia'),
            (155, 'NC', 'New Caledonia'),
            (156, 'NE', 'Niger'),
            (157, 'NF', 'Norfolk Island'),
            (158, 'NG', 'Nigeria'),
            (159, 'NI', 'Nicaragua'),
            (160, 'NL', 'Netherlands'),
            (161, 'NO', 'Norway'),
            (162, 'NP', 'Nepal'),
            (163, 'NR', 'Nauru'),
            (164, 'NU', 'Niue'),
            (165, 'NZ', 'New Zealand'),
            (166, 'OM', 'Oman'),
            (167, 'PA', 'Panama'),
            (168, 'PE', 'Peru'),
            (169, 'PF', 'French Polynesia'),
            (170, 'PG', 'Papua New Guinea'),
            (171, 'PH', 'Philippines'),
            (172, 'PK', 'Pakistan'),
            (173, 'PL', 'Poland'),
            (174, 'PM', 'St Pierre and Miquelon'),
            (175, 'PN', 'Pitcairn Island'),
            (176, 'PR', 'Puerto Rico'),
            (177, 'PT', 'Portugal'),
            (178, 'PW', 'Palau'),
            (179, 'PY', 'Paraguay'),
            (180, 'QA', 'Qatar'),
            (181, 'RE', 'Reunion'),
            (182, 'RO', 'Romania'),
            (183, 'RU', 'Russian Federation'),
            (184, 'RW', 'Rwanda'),
            (185, 'SA', 'Saudi Arabia'),
            (186, 'SB', 'Solomon Islands'),
            (187, 'SC', 'Seychelles'),
            (188, 'SD', 'Sudan'),
            (189, 'SE', 'Sweden'),
            (190, 'SG', 'Singapore'),
            (191, 'SH', 'St Helena'),
            (192, 'SI', 'Slovenia'),
            (193, 'SJ', 'Svalbard and Jan Mayen Islands'),
            (194, 'SK', 'Slovakia'),
            (195, 'SL', 'Sierra Leone'),
            (196, 'SM', 'San Marino'),
            (197, 'SN', 'Senegal'),
            (198, 'SO', 'Somalia'),
            (199, 'SR', 'Suriname'),
            (200, 'ST', 'Sco Tom'),
            (201, 'SU', 'Union of Soviet Socialist Republics'),
            (202, 'SV', 'El Salvador'),
            (203, 'SY', 'Syrian Arab Republic'),
            (204, 'SZ', 'Swaziland'),
            (205, 'TC', 'Turks and Caicos Islands'),
            (206, 'TD', 'Chad'),
            (207, 'TF', 'French Southern and Antarctic Territories'),
            (208, 'TG', 'Togo'),
            (209, 'TH', 'Thailand'),
            (210, 'TJ', 'Tajikistan'),
            (211, 'TK', 'Tokelau'),
            (212, 'TM', 'Turkmenistan'),
            (213, 'TN', 'Tunisia'),
            (214, 'TO', 'Tonga'),
            (215, 'TP', 'East Timor'),
            (216, 'TR', 'Turkey'),
            (217, 'TT', 'Trinidad and Tobago'),
            (218, 'TV', 'Tuvalu'),
            (219, 'TW', 'Taiwan, Province of China'),
            (220, 'TZ', 'Tanzania, United Republic of'),
            (221, 'UA', 'Ukraine'),
            (222, 'UG', 'Uganda'),
            (223, 'UM', 'United States Minor Outlying Islands'),
            (224, 'US', 'United States of America'),
            (225, 'UY', 'Uruguay'),
            (226, 'UZ', 'Uzbekistan'),
            (227, 'VA', 'Holy See (Vatican City State)'),
            (228, 'VC', 'Saint Vincent and the Grenadines'),
            (229, 'VE', 'Venezuela'),
            (230, 'VG', 'Virgin Islands (British)'),
            (231, 'VI', 'Virgin Islands (US)'),
            (232, 'VN', 'Viet Nam'),
            (233, 'VU', 'Vanautu'),
            (234, 'WF', 'Wallis and Futuna Islands'),
            (235, 'WS', 'Samoa'),
            (236, 'XO', 'West Africa'),
            (237, 'YE', 'Yemen'),
            (238, 'YT', 'Mayotte'),
            (239, 'ZA', 'South Africa'),
            (240, 'ZM', 'Zambia'),
            (241, 'ZW', 'Zimbabwe'),
            (242, 'PS', 'Palestinian Territory'),
            (243, 'ME', 'Montenegro'),
            (244, 'RS', 'Serbia')
        ") )
        {
            $this->uninstallDatabase();
            return false;
        }


        Db::getInstance()->Execute( "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_country_method`" );
        if( !Db::getInstance()->Execute("
            CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_country_method` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `country_id` int(11) default NULL,
                `method_id` int(11) default NULL,
                `priority` int(2) default NULL,
                PRIMARY KEY (`id`), KEY `country_id` (`country_id`), KEY `method_id` (`method_id`)
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8
        ") )
        {
            $this->uninstallDatabase();
            return false;
        }


        if( !Db::getInstance()->Execute("
            INSERT INTO `" . _DB_PREFIX_ . "smart2pay_country_method` (`country_id`, `method_id`, `priority`) VALUES
                (1,76,99),
                (2,13,1),
                (2,14,2),
                (2,76,99),
                (2,81,99),
                (3,76,99),
                (4,76,99),
                (5,76,99),
                (6,76,99),
                (7,76,99),
                (7,22,99),
                (7,74,99),
                (7,81,99),
                (7,1003,99),
                (8,76,99),
                (9,76,99),
                (10,76,99),
                (11,76,99),
                (11,40,99),
                (11,69,99),
                (12,76,99),
                (13,5,1),
                (13,40,2),
                (13,9,3),
                (13,28,4),
                (13,1,5),
                (13,23,6),
                (13,69,7),
                (13,76,99),
                (13,75,99),
                (13,78,99),
                (13,1052,99),
                (14,18,1),
                (14,28,2),
                (14,69,3),
                (14,76,99),
                (14,40,99),
                (14,78,99),
                (15,76,99),
                (16,76,99),
                (16,74,99),
                (16,81,99),
                (16,1003,99),
                (17,69,2),
                (17,76,99),
                (17,78,99),
                (18,76,99),
                (19,76,99),
                (20,3,1),
                (20,40,2),
                (20,1,3),
                (20,9,4),
                (20,28,5),
                (20,69,6),
                (20,76,99),
                (20,73,99),
                (20,78,99),
                (21,76,99),
                (22,1,1),
                (22,63,2),
                (22,69,3),
                (22,76,99),
                (22,40,99),
                (22,78,99),
                (22,81,99),
                (23,13,1),
                (23,14,2),
                (23,76,99),
                (24,76,99),
                (25,76,99),
                (26,76,99),
                (27,76,99),
                (28,76,99),
                (29,46,1),
                (29,32,2),
                (29,34,2),
                (29,1000,3),
                (29,1002,4),
                (29,28,6),
                (29,19,8),
                (29,76,99),
                (30,76,99),
                (31,76,99),
                (32,76,99),
                (33,76,99),
                (34,76,99),
                (34,74,1),
                (34,22,2),
                (34,1003,3),
                (34,23,5),
                (34,81,99),
                (35,76,99),
                (36,8,1),
                (36,71,2),
                (36,28,3),
                (36,69,4),
                (36,76,99),
                (36,40,99),
                (36,78,99),
                (37,76,99),
                (38,76,99),
                (39,76,99),
                (40,76,99),
                (41,1,1),
                (41,40,2),
                (41,9,3),
                (41,69,4),
                (41,76,99),
                (41,78,99),
                (42,76,99),
                (43,76,99),
                (44,19,1),
                (44,76,99),
                (44,46,99),
                (44,1039,99),
                (45,76,99),
                (46,24,1),
                (46,62,2),
                (46,28,3),
                (46,76,99),
                (46,81,99),
                (47,1019,2),
                (47,1020,3),
                (47,1021,4),
                (47,1022,5),
                (47,1023,6),
                (47,76,99),
                (47,46,99),
                (48,76,99),
                (50,76,99),
                (51,76,99),
                (52,76,99),
                (53,40,1),
                (53,28,2),
                (53,69,3),
                (53,13,4),
                (53,14,5),
                (53,76,99),
                (53,78,99),
                (54,27,1),
                (54,63,2),
                (54,1,3),
                (54,40,4),
                (54,28,5),
                (54,69,7),
                (54,76,99),
                (54,78,99),
                (54,81,99),
                (55,4,1),
                (55,9,2),
                (55,40,3),
                (55,28,4),
                (55,23,5),
                (55,1,6),
                (55,14,6),
                (55,76,99),
                (55,69,7),
                (55,75,99),
                (55,78,99),
                (55,79,99),
                (55,1052,99),
                (56,76,99),
                (57,29,1),
                (57,1,2),
                (57,40,3),
                (57,28,4),
                (57,69,5),
                (57,76,99),
                (57,75,99),
                (57,78,99),
                (57,1052,99),
                (58,76,99),
                (59,76,99),
                (60,14,1),
                (60,76,99),
                (61,76,99),
                (62,23,1),
                (62,29,2),
                (62,63,3),
                (62,28,4),
                (62,1,5),
                (62,69,6),
                (62,76,99),
                (62,78,99),
                (62,1003,99),
                (62,81,99),
                (63,13,1),
                (63,14,2),
                (63,76,99),
                (64,76,99),
                (65,76,99),
                (66,29,1),
                (66,1,2),
                (66,28,3),
                (66,40,3),
                (66,14,4),
                (66,9,5),
                (66,69,7),
                (66,76,99),
                (66,78,99),
                (67,76,99),
                (68,65,1),
                (68,40,2),
                (68,69,3),
                (68,29,4),
                (68,28,5),
                (68,1,6),
                (68,1041,7),
                (68,1042,8),
                (68,1043,9),
                (68,76,99),
                (68,75,99),
                (68,78,99),
                (68,1052,99),
                (69,76,99),
                (70,76,99),
                (71,76,99),
                (72,76,99),
                (73,1,1),
                (73,40,2),
                (73,14,3),
                (73,28,4),
                (73,73,5),
                (73,9,6),
                (73,69,7),
                (73,76,99),
                (73,78,99),
                (74,76,99),
                (75,76,99),
                (76,9,1),
                (76,1,2),
                (76,40,3),
                (76,28,4),
                (76,23,5),
                (76,14,6),
                (76,69,8),
                (76,76,99),
                (76,78,99),
                (76,1003,99),
                (77,76,99),
                (78,76,99),
                (78,74,99),
                (78,1003,99),
                (79,76,99),
                (80,14,1),
                (80,76,99),
                (81,76,99),
                (82,76,99),
                (83,76,99),
                (84,76,99),
                (85,76,99),
                (86,76,99),
                (87,40,1),
                (87,28,2),
                (87,69,3),
                (87,76,99),
                (87,78,99),
                (88,76,99),
                (89,76,99),
                (90,76,99),
                (91,76,99),
                (92,76,99),
                (93,76,99),
                (94,76,99),
                (95,76,99),
                (96,63,1),
                (96,69,2),
                (96,76,99),
                (96,40,99),
                (96,78,99),
                (97,76,99),
                (98,25,1),
                (98,28,2),
                (98,63,3),
                (98,1,4),
                (98,9,4),
                (98,40,5),
                (98,69,6),
                (98,76,99),
                (98,78,99),
                (99,1024,1),
                (99,1025,2),
                (99,76,99),
                (99,1053,99),
                (99,1054,99),
                (99,1055,99),
                (99,1056,99),
                (99,1057,99),
                (99,1058,99),
                (99,1059,99),
                (99,1060,99),
                (99,1061,99),
                (99,1062,99),
                (99,1063,99),
                (100,40,1),
                (100,1,2),
                (100,28,2),
                (100,14,3),
                (100,69,4),
                (100,76,99),
                (100,78,99),
                (101,13,1),
                (101,14,2),
                (101,69,3),
                (101,76,99),
                (101,78,99),
                (101,1003,99),
                (101,81,99),
                (102,76,99),
                (102,1003,99),
                (103,76,99),
                (104,13,1),
                (104,14,2),
                (104,76,99),
                (105,76,99),
                (106,76,99),
                (107,40,1),
                (107,73,2),
                (107,1,3),
                (107,28,4),
                (107,9,5),
                (107,14,6),
                (107,69,8),
                (107,76,99),
                (107,78,99),
                (108,76,99),
                (109,13,1),
                (109,14,2),
                (109,76,99),
                (110,76,99),
                (110,1048,99),
                (110,1046,99),
                (110,1047,99),
                (110,1049,99),
                (110,1050,99),
                (110,1003,99),
                (111,76,99),
                (112,76,99),
                (112,22,99),
                (112,74,99),
                (112,81,99),
                (112,1003,99),
                (113,76,99),
                (114,76,99),
                (115,76,99),
                (116,76,99),
                (117,76,99),
                (118,76,99),
                (118,1003,99),
                (119,13,1),
                (119,14,2),
                (119,76,99),
                (120,76,99),
                (121,1003,1),
                (121,76,99),
                (121,22,99),
                (121,74,99),
                (121,81,99),
                (122,76,99),
                (123,13,1),
                (123,14,2),
                (123,76,99),
                (124,76,99),
                (125,76,99),
                (126,76,99),
                (127,76,99),
                (128,76,99),
                (129,23,1),
                (129,1,3),
                (129,69,4),
                (129,76,99),
                (129,40,99),
                (129,78,99),
                (129,1003,99),
                (129,81,99),
                (130,1,1),
                (130,40,2),
                (130,73,3),
                (130,69,4),
                (130,76,99),
                (130,78,99),
                (131,23,1),
                (131,63,2),
                (131,28,3),
                (131,40,5),
                (131,69,6),
                (131,76,99),
                (131,78,99),
                (131,81,99),
                (131,1003,99),
                (132,76,99),
                (133,76,99),
                (134,76,99),
                (135,76,99),
                (135,22,99),
                (135,74,99),
                (135,81,99),
                (135,1003,99),
                (136,76,99),
                (137,76,99),
                (138,76,99),
                (139,76,99),
                (140,76,99),
                (141,76,99),
                (142,76,99),
                (143,76,99),
                (144,76,99),
                (145,76,99),
                (146,76,99),
                (147,76,99),
                (147,40,99),
                (148,76,99),
                (149,76,99),
                (150,76,99),
                (151,49,1),
                (151,46,2),
                (151,19,3),
                (151,40,4),
                (151,1026,5),
                (151,1027,6),
                (151,1028,7),
                (151,1029,8),
                (151,1030,9),
                (151,28,10),
                (151,1031,10),
                (151,76,99),
                (152,1010,2),
                (152,1011,3),
                (152,1012,4),
                (152,1013,5),
                (152,1014,6),
                (152,1015,7),
                (152,1016,8),
                (152,1017,9),
                (152,76,99),
                (153,76,99),
                (154,76,99),
                (155,76,99),
                (156,76,99),
                (157,76,99),
                (158,14,1),
                (158,76,99),
                (158,77,99),
                (159,76,99),
                (160,2,1),
                (160,9,2),
                (160,40,3),
                (160,28,4),
                (160,1,5),
                (160,69,7),
                (160,76,99),
                (160,75,99),
                (160,78,99),
                (160,1052,99),
                (161,1,1),
                (161,40,3),
                (161,28,4),
                (161,69,5),
                (161,76,99),
                (161,75,99),
                (161,78,99),
                (161,1052,99),
                (162,76,99),
                (163,76,99),
                (164,76,99),
                (165,18,2),
                (165,28,2),
                (165,76,99),
                (165,40,99),
                (166,13,1),
                (166,14,2),
                (166,76,99),
                (167,76,99),
                (167,1003,99),
                (168,40,1),
                (168,76,99),
                (168,72,99),
                (169,76,99),
                (170,76,99),
                (171,44,1),
                (171,67,2),
                (171,76,99),
                (171,1051,99),
                (172,76,99),
                (173,12,1),
                (173,1,2),
                (173,40,3),
                (173,28,4),
                (173,14,8),
                (173,76,99),
                (174,76,99),
                (175,76,99),
                (176,76,99),
                (177,20,1),
                (177,40,2),
                (177,28,3),
                (177,14,4),
                (177,1,5),
                (177,69,6),
                (177,76,99),
                (177,78,99),
                (178,76,99),
                (179,76,99),
                (180,13,1),
                (180,14,2),
                (180,76,99),
                (181,76,99),
                (182,40,1),
                (182,1,2),
                (182,63,3),
                (182,28,4),
                (182,69,5),
                (182,76,99),
                (182,78,99),
                (182,79,99),
                (183,74,1),
                (183,1003,2),
                (183,22,3),
                (183,1044,5),
                (183,1045,6),
                (183,1004,7),
                (183,1005,8),
                (183,1006,9),
                (183,28,11),
                (183,76,99),
                (183,81,99),
                (184,76,99),
                (185,13,1),
                (185,14,2),
                (185,76,99),
                (186,76,99),
                (187,76,99),
                (188,14,1),
                (188,76,99),
                (189,29,1),
                (189,1,2),
                (189,40,3),
                (189,28,4),
                (189,69,5),
                (189,76,99),
                (189,75,99),
                (189,78,99),
                (189,1052,99),
                (190,37,1),
                (190,76,99),
                (191,76,99),
                (192,63,1),
                (192,40,2),
                (192,28,3),
                (192,14,4),
                (192,69,5),
                (192,76,99),
                (192,78,99),
                (193,76,99),
                (194,1,1),
                (194,63,2),
                (194,40,3),
                (194,69,6),
                (194,76,99),
                (194,78,99),
                (195,76,99),
                (196,76,99),
                (197,76,99),
                (198,76,99),
                (199,76,99),
                (200,76,99),
                (201,76,99),
                (202,76,99),
                (203,76,99),
                (204,76,99),
                (205,76,99),
                (206,76,99),
                (207,76,99),
                (208,76,99),
                (209,35,1),
                (209,1038,2),
                (209,1036,3),
                (209,1037,4),
                (209,76,99),
                (209,1003,99),
                (210,76,99),
                (210,22,99),
                (210,74,99),
                (210,81,99),
                (210,1003,99),
                (211,76,99),
                (212,76,99),
                (212,74,99),
                (212,81,99),
                (213,13,1),
                (213,14,2),
                (213,76,99),
                (214,76,99),
                (215,76,99),
                (216,1,1),
                (216,66,2),
                (216,64,3),
                (216,13,6),
                (216,14,7),
                (216,28,8),
                (216,69,9),
                (216,76,99),
                (216,78,99),
                (216,1003,99),
                (216,81,99),
                (217,76,99),
                (218,76,99),
                (219,76,99),
                (220,76,99),
                (221,74,1),
                (221,22,2),
                (221,1003,3),
                (221,28,6),
                (221,76,99),
                (221,81,99),
                (222,76,99),
                (223,76,99),
                (224,69,1),
                (224,58,2),
                (224,40,3),
                (224,76,99),
                (224,78,99),
                (224,1003,99),
                (225,76,99),
                (225,40,99),
                (226,76,99),
                (226,74,99),
                (226,1003,99),
                (226,81,99),
                (227,76,99),
                (228,76,99),
                (229,76,99),
                (230,76,99),
                (231,76,99),
                (232,76,99),
                (232,1003,99),
                (232,81,99),
                (233,76,99),
                (234,76,99),
                (235,76,99),
                (236,76,99),
                (237,76,99),
                (238,76,99),
                (239,28,1),
                (239,76,99),
                (240,76,99),
                (241,76,99),
                (242,13,1),
                (242,14,1),
                (242,76,99),
                (243,69,2),
                (243,76,99),
                (243,78,99),
                (244,69,2),
                (244,76,99),
                (244,78,99);
        ") )
        {
            $this->uninstallDatabase();
            return false;
        }

        return true;
    }

    /**
     * Create custom s2p order statuses
     */
    private function createCustomOrderStatuses()
    {
        if( !($all_languages = Language::getLanguages( false )) )
            $all_languages = array();

        foreach( $this->getPaymentStatesOrderStatuses() as $status )
        {
            if( ($existingStatus = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'order_state_lang` WHERE `name` = \'' . pSQL( $status['orderStatusName'] ) . '\' LIMIT 0, 1' )) )
                $statusID = $existingStatus[0]['id_order_state'];

            else
            {
                if( version_compare( _PS_VERSION_, '1.5', '<' ) )
                {
                    Db::getInstance()->execute(
                        'INSERT INTO `' . _DB_PREFIX_ . 'order_state` (`unremovable`, `color`) ' .
                        'VALUES(1, \'#660099\')'
                    );
                } else
                {
                    Db::getInstance()->execute(
                        'INSERT INTO `' . _DB_PREFIX_ . 'order_state` (`unremovable`, `color`, `module_name`) ' .
                        'VALUES(1, \'#660099\', \'' . pSQL( $this->name ) . '\')'
                    );
                }

                $statusID = Db::getInstance()->Insert_ID();
            }

            $statusID = intval( $statusID );

            foreach( $all_languages as $language_arr )
            {
                if( empty( $language_arr['id_lang'] ) )
                    continue;

                if( Db::getInstance()->executeS( 'SELECT id_order_state FROM `'._DB_PREFIX_.'order_state_lang` WHERE `id_order_state` = \'' . $statusID . '\' AND `id_lang` = \''.$language_arr['id_lang'].'\' LIMIT 0, 1' ) )
                    continue;

                Db::getInstance()->execute(
                    'INSERT INTO `'._DB_PREFIX_.'order_state_lang` (`id_order_state`, `id_lang`, `name`) '.
                    'VALUES(' . $statusID . ', \''.$language_arr['id_lang'].'\', \'' . pSQL( $status['orderStatusName'] ). '\')'
                );
            }

            if( !empty( $status['icon'] )
            and @file_exists( _PS_MODULE_DIR_.$this->name.'/views/img/statuses/'.$status['icon'] )
            and !@file_exists( _PS_IMG_DIR_.'os/'.$statusID.'.gif' ) )
                @copy( _PS_MODULE_DIR_.$this->name.'/views/img/statuses/'.$status['icon'], _PS_IMG_DIR_.'os/'.$statusID.'.gif' );

            Configuration::updateValue( $status['configName'], $statusID );
        }
    }

    private function deleteCustomOrderStatusesImages()
    {
        foreach( $this->getPaymentStatesOrderStatuses() as $status )
        {
            if( ( $existingStatus = Db::getInstance()->executeS( 'SELECT * FROM `' . _DB_PREFIX_ . 'order_state_lang` WHERE `name` = \'' . pSQL( $status['orderStatusName'] ) . '\'' ) ) )
            {
                $statusID = $existingStatus[0]['id_order_state'];

                if( !empty( $status['icon'] )
                and @file_exists( _PS_IMG_DIR_ . 'os/' . $statusID . '.gif' ) )
                    @unlink( _PS_IMG_DIR_ . 'os/' . $statusID . '.gif' );
            }
        }
    }

    /**
     * Delete custom s2p order statuses
     */
    private function deleteCustomOrderStatuses()
    {
        $this->deleteCustomOrderStatusesImages();

        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
        {
            foreach( $this->getPaymentStatesOrderStatuses() as $status )
            {
                if( !( $existingStatus = Db::getInstance()->executeS( 'SELECT * FROM `' . _DB_PREFIX_ . 'order_state_lang` WHERE `name` = \'' . pSQL( $status['orderStatusName'] ) . '\'' ) ) )
                    continue;

                $statusID = (int)$existingStatus[0]['id_order_state'];

                Db::getInstance()->execute( 'DELETE FROM `' . _DB_PREFIX_ . 'order_state` WHERE id_order_state = \''.$statusID.'\'' );
                Db::getInstance()->execute( 'DELETE FROM `' . _DB_PREFIX_ . 'order_state_lang` WHERE id_order_state = \''.$statusID.'\'' );
            }
        } else
        {
            $ids = Db::getInstance()->executeS(
                'SELECT GROUP_CONCAT(`id_order_state`) AS `id_order_state` FROM `' . _DB_PREFIX_ . 'order_state` ' .
                ' WHERE `module_name` = \'' . pSQL( $this->name ) . '\''
            );

            $ids = explode( ',', $ids[0]['id_order_state'] );

            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'order_state` ' .
                ' WHERE `id_order_state` IN (\'' . join( '\',\'', (array) $ids ) . '\')'
            );

            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'order_state_lang` ' .
                ' WHERE `id_order_state` IN (\'' . join( '\',\'', (array) $ids ) . '\')'
            );
        }
    }
}
