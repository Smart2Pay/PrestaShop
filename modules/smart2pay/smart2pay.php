<?php
/**
 * 2018 Smart2Pay
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this plugin
 * in the future.
 *
 * @author    Smart2Pay
 * @copyright 2018 Smart2Pay
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
**/
/**
 * Smart2Pay module file
**/
if( !defined( '_PS_VERSION_' ) )
    exit;

include_once( _PS_MODULE_DIR_.'smart2pay/includes/helper.inc.php' );
include_once( _PS_MODULE_DIR_.'smart2pay/includes/phs_error.php' );
include_once( _PS_MODULE_DIR_.'smart2pay/includes/phs_params.php' );
include_once( _PS_MODULE_DIR_.'smart2pay/includes/sdk_interface.inc.php' );

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

    const PAYM_BANK_TRANSFER = 1, PAYM_MULTIBANCO_SIBS = 20, PAYM_SMARTCARDS = 6;

    const OPT_FEE_CURRENCY_FRONT = 1, OPT_FEE_CURRENCY_ADMIN = 2;

    const OPT_FEE_AMOUNT_SEPARATED = 1, OPT_FEE_AMOUNT_TOTAL_FEE = 2, OPT_FEE_AMOUNT_TOTAL_ORDER = 3;

    const DEMO_SIGNATURE = 'fc5fa3b8-746a', DEMO_MID = '1045', DEMO_SID = '30144', DEMO_POSTURL = 'https://apitest.smart2pay.com';
    const DEMO_REST_APIKEY = 'GZSRjsQeYI6aJmF7RvIPZWMt8XFIGCI5ZFKmh+fZmhENO93+3J',
          DEMO_REST_MID = '1045',
          DEMO_REST_SID = '33608';

    // Tells module if install() or uninstall() methods are currenctly called
    private static $maintenance_functionality = false;

    /** @var bool|Smart2Pay_SDK_Interface $s2p_sdk_obj  */
    private static $s2p_sdk_obj = false;

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
        'all_codes_countries' => array(),
        'all_method_countries' => array(),
        'all_method_countries_enabled' => array(),
        'all_method_countries_details' => array(),
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
        $this->version = '2.0.4';
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

        $this->_init_sdk_instance();
    }

    public function foobar( $str )
    {
        // if( !($fil = @fopen( '/home/andy/prestashop.log', 'a' )) )
        //     return false;
        //
        // @fputs( $fil, date( 'd-m-Y H:i:s' ).' - '.$str."\n" );
        //
        // @fflush( $fil );
        // @fclose( $fil );
        //
        return true;
    }

    private function _init_sdk_instance()
    {
        if( self::$s2p_sdk_obj )
            return true;

        if( !(self::$s2p_sdk_obj = new Smart2Pay_SDK_Interface( $this )) )
        {
            self::$s2p_sdk_obj = false;
            return false;
        }

        $sdk_obj = self::$s2p_sdk_obj;

        if( !$sdk_obj::init_sdk() )
        {
            $this->_errors[] = 'Failed initializing Smart2Pay SDK.';

            self::$s2p_sdk_obj = false;
            return false;
        }

        return true;
    }

    public function create_context()
    {
        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
        {
            /** @var Cart $cart */
            /** @var Controller $controller */
            /** @var Cookie $cookie */
            /** @var Smarty $smarty */
            global $smarty, $cookie, $cart, $controller;

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

            if( empty( $this->context->controller ) )
                $this->context->controller = new stdClass();

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
            $this->context->controller = $controller;

            //$this->context->shop->id_shop = 1;
            //$this->context->shop->id_shop_group = 1;
        }
    }

    public function get_current_shop_id()
    {

    }

    public function S2P_add_css( $file, $file_name = '' )
    {
        if( $file_name == '' )
            $file_name = basename( $file );

        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
            Tools::addCSS( $file, 'all' );
        elseif( version_compare( _PS_VERSION_, '1.7', '<' )
             or !@is_a( $this->context->controller, 'FrontController' ) )
            $this->context->controller->addCSS( $file );
        else
            $this->context->controller->registerStylesheet( 'modules-'.$this->name.'_'.$file_name, 'modules/'.$this->name.'/views/css/'.$file_name, array( 'media' => 'all', 'priority' => 150 ) );
    }

    public function redirect_to_step1( $messages_arr = false )
    {
        if( version_compare( _PS_VERSION_, '1.7', '>=' )
        and !empty( $messages_arr ) and is_array( $messages_arr )
        and $this->context
        and $this->context->controller
        and @is_a( $this->context->controller, 'FrontController' ) )
        {
            // Try adding messages in order form
            $messages_keys = array( 'errors', 'warning', 'success', 'info', );

            foreach( $messages_keys as $key )
            {
                if( empty( $messages_arr[$key] ) or !is_array( $messages_arr[$key] )
                 or !@property_exists( $this->context->controller, $key ) )
                    continue;

                $this->context->controller->$key = array_merge( $this->context->controller->$key, $messages_arr[$key] );

                $this->context->controller->redirectWithNotifications( '/order?step=3' );
            }
        }

        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
            Tools::redirect( 'order.php?step=3' );
        elseif( version_compare( _PS_VERSION_, '1.7', '<' ) )
            Tools::redirect( 'index.php?controller=order&step=3' );
        else
            Tools::redirect( '/order' );
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

        if( empty( self::$s2p_sdk_obj ) )
        {
            $this->writeLog( 'Couldn\'t initialize Smart2Pay SDK.', array( 'type' => 'error' ) );
            $this->redirect_to_step1( array( 'errors' => array( $this->l( 'Couldn\'t initialize Smart2Pay SDK.' ) ) ) );

            // just to make IDE not highlight variables as "might not be initialized"
            exit;
        }

        if( !($moduleSettings = $this->getSettings()) )
        {
            $this->writeLog( 'Couldn\'t obtain Smart2Pay plugin settings.', array( 'type' => 'error' ) );
            $this->redirect_to_step1( array( 'errors' => array( $this->l( 'Couldn\'t obtain Smart2Pay plugin settings.' ) ) ) );

            // just to make IDE not highlight variables as "might not be initialized"
            exit;
        }

        $sdk_obj = self::$s2p_sdk_obj;

        if( !($api_credentials = $sdk_obj->get_api_credentials( $moduleSettings )) )
        {
            $this->writeLog( 'Couldn\'t obtain Smart2Pay API credentials.', array( 'type' => 'error' ) );
            $this->redirect_to_step1( array( 'errors' => array( $this->l( 'Couldn\'t obtain Smart2Pay API credentials.' ) ) ) );

            // just to make IDE not highlight variables as "might not be initialized"
            exit;
        }

        if( empty( $cart )
         or !($cart_products = $cart->getProducts()) )
        {
            $this->writeLog( 'Couldn\'t get cart from context', array( 'type' => 'error' ) );
            $this->redirect_to_step1( array( 'errors' => array( $this->l( 'Couldn\'t get cart from context.' ) ) ) );

            // just to make IDE not highlight variables as "might not be initialized"
            exit;
        }

        $cart_currency = new Currency( $cart->id_currency );
        $customer = new Customer( $cart->id_customer );

        if( !Validate::isLoadedObject( $customer ) )
            $this->redirect_to_step1( array( 'errors' => array( $this->l( 'Couldn\'t load customer data.' ) ) ) );

        $method_id = (int) Tools::getValue( 'method_id', 0 );

        if( empty( $method_id )
         or !($payment_method = $this->method_details_if_available( $method_id, null, $moduleSettings['environment'] )) )
        {
            $this->writeLog( 'Payment method #'.$method_id.', environment '.$moduleSettings['environment'].' could not be loaded, or it is not available', array( 'type' => 'error' ) );

            $this->redirect_to_step1( array( 'errors' => array( $this->l( 'Payment method could not be loaded or it is not available.' ) ) ) );
        }

        if( empty( $payment_method['method_settings']['surcharge_currency'] )
         or !($surcharge_currency_id = Currency::getIdByIsoCode( $payment_method['method_settings']['surcharge_currency'] ))
         or !($surcharge_currency_obj = new Currency( $surcharge_currency_id )) )
        {
            $this->writeLog( 'Payment method #'.$method_id.' ('.$payment_method['method_details']['display_name'].'), '.
                             'environment '.$moduleSettings['environment'].' has an invalid currency code '.
                             ' ['.(!empty( $payment_method['method_settings']['surcharge_currency'] )?$payment_method['method_settings']['surcharge_currency']:'???').'].', array( 'type' => 'error' ) );

            $this->redirect_to_step1( array( 'errors' => array( $this->l( 'Payment method has an invalid currency code.' ) ) ) );

            // IDE fix (exit is called in redirect_to_step1())
            exit;
        }

        /**
         *    Surcharge calculation
         */
        $cart_original_amount = $amount_to_pay
            = number_format( $context->cart->getOrderTotal( true, Cart::BOTH ), 2, '.', '' );

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
        $articles_params['payment_method'] = $method_id;

        $articles_str = '';
        $articles_sdk_arr = array();
        $articles_diff = 0;
        if( ($articles_check = Smart2Pay_Helper::cart_products_to_string( $cart_products, $cart_original_amount, $articles_params )) )
        {
            $articles_str = $articles_check['buffer'];
            $articles_sdk_arr = $articles_check['articles_arr'];

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

        $order = new Order( $orderID );

        if( $moduleSettings[self::CONFIG_PREFIX.'ALTER_ORDER_ON_SURCHARGE']
        and $cart_original_amount != $amount_to_pay )
        {
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

        $delivery = false;
        $billing = false;
        if( Validate::isLoadedObject( $order ) )
        {
            $billing = new Address( (int)$order->id_address_invoice );
            if( !Validate::isLoadedObject( $billing ) )
                $billing = false;

            $delivery = new Address( (int)$order->id_address_delivery );
            if( !Validate::isLoadedObject( $delivery ) )
                $delivery = false;
        }

        $transaction_arr = array();
        $transaction_arr['method_id'] = $method_id;
        $transaction_arr['order_id'] = $orderID;
        $transaction_arr['site_id'] = $api_credentials['site_id'];
        $transaction_arr['environment'] = strtolower( $moduleSettings['environment'] );

        $transaction_arr['surcharge_amount'] = $payment_method['method_settings']['surcharge_amount'];
        $transaction_arr['surcharge_percent'] = $payment_method['method_settings']['surcharge_percent'];
        $transaction_arr['surcharge_currency'] = $surcharge_currency_obj->iso_code;

        $transaction_arr['surcharge_order_amount'] = $surcharge_order_amount;
        $transaction_arr['surcharge_order_percent'] = $surcharge_percent_amount;
        $transaction_arr['surcharge_order_currency'] = $cart_currency->iso_code;

        if( !($new_transaction = $this->save_transaction( $transaction_arr )) )
        {
            $this->writeLog( 'Failed creating transaction for order ['.$orderID.'].', array( 'type' => 'error', 'order_id' => $orderID ) );

            $this->redirect_to_step1( array( 'errors' => array( $this->l( 'Failed creating transaction for order. Please try again.' ) ) ) );

            // IDE fix (exit is called in redirect_to_step1())
            exit;
        }

        $skipPaymentPage = 0;
        if( $moduleSettings[self::CONFIG_PREFIX.'SKIP_PAYMENT_PAGE']
            and !in_array( $method_id, array( self::PAYM_BANK_TRANSFER, self::PAYM_MULTIBANCO_SIBS ) ) )
            $skipPaymentPage = 1;

        $moduleSettings['skipPaymentPage'] = $skipPaymentPage;

        if( !empty( $moduleSettings[self::CONFIG_PREFIX.'SEND_ORDER_NUMBER'] ) )
            $payment_description = 'Ref. No. '.$orderID;
        else
            $payment_description = $moduleSettings[self::CONFIG_PREFIX.'CUSTOM_PRODUCT_DESCRIPTION'];

        $first_name = trim( $customer->firstname );
        $last_name = trim( $customer->lastname );
        // if first name and last name are empty full name should be empty too
        $full_name = trim( $first_name.' '.$last_name );

        if( $first_name === '' )
            $first_name = null;
        if( $last_name === '' )
            $last_name = null;
        if( $full_name === '' )
            $full_name = null;

        if( $moduleSettings['environment'] == 'demo' )
            $merchant_transaction_id = 'PSDEMO_'.$orderID.'_'.microtime( true );
        else
            $merchant_transaction_id = $orderID;

        $payment_arr = array();
        $payment_arr['merchanttransactionid'] = $merchant_transaction_id;
        $payment_arr['amount'] = number_format( $amount_to_pay, 2, '.', '' ) * 100;
        $payment_arr['currency'] = $cart_currency->iso_code;
        $payment_arr['methodid'] = $method_id;
        $payment_arr['description'] = $payment_description;
        $payment_arr['customer'] = array(
            'email' => $customer->email,
            'firstname' => $first_name,
            'lastname' => $last_name,
        );

        if( !empty( $customer->company ) )
            $payment_arr['customer']['company'] = $customer->company;

        $phone = false;
        $delivery_country = false;
        $delivery_city = false;
        $delivery_zipcode = false;
        $delivery_address = false;
        $billing_country = false;
        $billing_city = false;
        $billing_zipcode = false;
        $billing_address = false;
        if( !empty( $billing ) )
        {
            $phone = $billing->phone ? $billing->phone : $billing->phone_mobile;

            if( !empty( $billing->id_country ) )
            {
                $country_obj = new Country( $billing->id_country );
                if( Validate::isLoadedObject( $country_obj ) )
                    $billing_country = $country_obj->iso_code;
            }

            $billing_city = $billing->city;
            $billing_zipcode = $billing->postcode;
            $billing_address = trim( $billing->address1.' '.$billing->address2 );
        }

        if( !empty( $delivery ) )
        {
            if( empty( $phone ) )
                $phone = $delivery->phone ? $delivery->phone : $delivery->phone_mobile;

            if( !empty( $delivery->id_country ) )
            {
                $country_obj = new Country( $delivery->id_country );
                if( Validate::isLoadedObject( $country_obj ) )
                    $delivery_country = $country_obj->iso_code;
            }

            $delivery_city = $delivery->city;
            $delivery_zipcode = $delivery->postcode;
            $delivery_address = trim( $delivery->address1.' '.$delivery->address2 );
        }

        $payment_arr['billingaddress'] = array();
        $payment_arr['shippingaddress'] = array();

        if( !empty( $phone ) )
            $payment_arr['customer']['phone'] = $phone;

        if( !empty( $billing_country ) )
            $payment_arr['billingaddress']['country'] = $billing_country;
        if( !empty( $delivery_country ) )
        {
            if( empty( $payment_arr['billingaddress']['country'] ) )
                $payment_arr['billingaddress']['country'] = $delivery_country;

            $payment_arr['shippingaddress']['country'] = $delivery_country;
        }

        if( !empty( $billing_city ) )
            $payment_arr['billingaddress']['city'] = $billing_city;
        if( !empty( $delivery_city ) )
            $payment_arr['shippingaddress']['city'] = $delivery_city;

        if( !empty( $billing_zipcode ) )
            $payment_arr['billingaddress']['zipcode'] = $billing_zipcode;
        if( !empty( $delivery_zipcode ) )
            $payment_arr['shippingaddress']['zipcode'] = $delivery_zipcode;

        if( !empty( $billing_address )
        and strlen( $billing_address ) > 100 )
        {
            $payment_arr['billingaddress']['street'] = Tools::substr( $billing_address, 0, 100 );
            $payment_arr['billingaddress']['streetnumber'] = Tools::substr( $billing_address, 100, 100 );
        }

        if( !empty( $delivery_address )
        and strlen( $delivery_address ) > 100 )
        {
            $payment_arr['shippingaddress']['street'] = Tools::substr( $delivery_address, 0, 100 );
            $payment_arr['shippingaddress']['streetnumber'] = Tools::substr( $delivery_address, 100, 100 );
        }

        $payment_arr['articles'] = $articles_sdk_arr;

        // ob_start();
        // var_dump( $payment_arr );
        // $buf = ob_get_clean();
        //
        // $this->writeLog( '['.$buf.']', array( 'type' => 'error' ) );

        if( $method_id == self::PAYM_SMARTCARDS )
        {
            if( !($payment_request = $sdk_obj->card_init_payment( $payment_arr, $moduleSettings )) )
            {
                if( !$sdk_obj->has_error() )
                    $error_msg = 'Couldn\'t initiate request to server.';
                else
                    $error_msg = 'Call error: '.$sdk_obj->get_error_message();

                $this->writeLog( $error_msg, array( 'type' => 'error', 'order_id' => $orderID ) );
                $this->redirect_to_step1( array( 'errors' => array( $error_msg ) ) );

                exit;
            }
        } else
        {
            if( !($payment_request = $sdk_obj->init_payment( $payment_arr, $moduleSettings )) )
            {
                if( !$sdk_obj->has_error() )
                    $error_msg = 'Couldn\'t initiate request to server.';
                else
                    $error_msg = 'Call error: '.$sdk_obj->get_error_message();

                $this->writeLog( $error_msg, array( 'type' => 'error', 'order_id' => $orderID ) );
                $this->redirect_to_step1( array( 'errors' => array( $error_msg ) ) );

                exit;
            }
        }

        // ob_start();
        // var_dump( $payment_request );
        // $buf = ob_get_clean();
        //
        // $this->writeLog( 'Req ['.$buf.']' );

        $transaction_arr = array();
        $transaction_arr['order_id'] = $orderID;
        $transaction_arr['payment_id'] = (!empty( $payment_request['id'] )?$payment_request['id']:0);
        $transaction_arr['payment_status'] = ((!empty( $payment_request['status'] ) and !empty( $payment_request['status']['id'] ))?$payment_request['status']['id']:0);

        $extra_data_arr = array();
        if( !empty( $payment_request['referencedetails'] ) and is_array( $payment_request['referencedetails'] ) )
        {
            foreach( $payment_request['referencedetails'] as $key => $val )
            {
                if( is_null( $val ) )
                    continue;

                $extra_data_arr[$key] = $val;
            }
        }

        if( !empty( $extra_data_arr ) )
            $transaction_arr['extra_data'] = $extra_data_arr;

        if( !($new_transaction = $this->save_transaction( $transaction_arr )) )
        {
            $this->writeLog( 'Failed saving transaction with payment id ['.$transaction_arr['payment_id'].'] for order ['.$orderID.'].', array( 'type' => 'error', 'order_id' => $orderID ) );

            $this->redirect_to_step1( array( 'errors' => array( $this->l( 'Failed saving transaction for order. Please try again.' ) ) ) );

            // IDE fix (exit is called in redirect_to_step1())
            exit;
        }

        if( empty( $payment_request['redirecturl'] ) )
        {
            $error_msg = 'Redirect URL not provided in API response. Please try again.';
            $this->writeLog( $error_msg, array( 'type' => 'error', 'order_id' => $orderID ) );
            $this->redirect_to_step1( array( 'errors' => array( $error_msg ) ) );

            exit;
        }

        $this->writeLog( 'Redirecting to payment page for payment id ['.$transaction_arr['payment_id'].'], order ['.$orderID.'].', array( 'order_id' => $orderID ) );

        Tools::redirect( $payment_request['redirecturl'] );

        return true;
    }

    public function prepare_notification()
    {
        $this->writeLog( '--- Notification START --------------------' );

        include_once( S2P_SDK_DIR_CLASSES . 's2p_sdk_notification.inc.php' );
        include_once( S2P_SDK_DIR_CLASSES . 's2p_sdk_helper.inc.php' );
        include_once( S2P_SDK_DIR_METHODS . 's2p_sdk_meth_payments.inc.php' );

        if( !defined( 'S2P_SDK_NOTIFICATION_IDENTIFIER' ) )
            define( 'S2P_SDK_NOTIFICATION_IDENTIFIER', microtime( true ) );

        S2P_SDK\S2P_SDK_Notification::logging_enabled( false );

        $notification_params = array();
        $notification_params['auto_extract_parameters'] = true;

        /** @var S2P_SDK\S2P_SDK_Notification $notification_obj */
        if( !($notification_obj = S2P_SDK\S2P_SDK_Module::get_instance( 'S2P_SDK_Notification', $notification_params ))
         or $notification_obj->has_error() )
        {
            if( (S2P_SDK\S2P_SDK_Module::st_has_error() and $error_arr = S2P_SDK\S2P_SDK_Module::st_get_error())
                or (!empty( $notification_obj ) and $notification_obj->has_error() and ($error_arr = $notification_obj->get_error())) )
                $error_msg = 'Error ['.$error_arr['error_no'].']: '.$error_arr['display_error'];
            else
                $error_msg = 'Error initiating notification object.';

            $this->writeLog( $error_msg );
            echo $error_msg;
            exit;
        }

        if( !($notification_type = $notification_obj->get_type())
         or !($notification_title = $notification_obj::get_type_title( $notification_type )) )
        {
            $error_msg = 'Unknown notification type.';
            $error_msg .= 'Input buffer: '.$notification_obj->get_input_buffer();

            $this->writeLog( $error_msg );
            echo $error_msg;
            exit;
        }

        if( !($result_arr = $notification_obj->get_array()) )
        {
            $error_msg = 'Couldn\'t extract notification object.';
            $error_msg .= 'Input buffer: '.$notification_obj->get_input_buffer();

            $this->writeLog( $error_msg );
            echo $error_msg;
            exit;
        }

        $order = null;
        if( $notification_type == $notification_obj::TYPE_PAYMENT
        and (
               empty( $result_arr['payment'] ) or !is_array( $result_arr['payment'] )
            or empty( $result_arr['payment']['merchanttransactionid'] )
            or !($order = new Order( $result_arr['payment']['merchanttransactionid'] ))
            or !Validate::isLoadedObject( $order )
            ) )
        {
            $error_msg = 'Couldn\'t load order as provided in notification.';
            $this->writeLog( $error_msg );
            echo $error_msg;
            exit;
        }

        if( !($plugin_settings = $this->getSettings( $order ))
         or empty( self::$s2p_sdk_obj )
         or !($api_credentials = self::$s2p_sdk_obj->get_api_credentials( $plugin_settings )) )
        {
            $error_msg = 'Couldn\'t load Smart2Pay plugin settings.';
            $this->writeLog( $error_msg );
            echo $error_msg;
            exit;
        }

        \S2P_SDK\S2P_SDK_Module::one_call_settings(
            array(
                'api_key' => $api_credentials['api_key'],
                'site_id' => $api_credentials['site_id'],
                'environment' => $api_credentials['environment'],
            ) );

        if( !$notification_obj->check_authentication() )
        {
            if( $notification_obj->has_error()
                and ($error_arr = $notification_obj->get_error()) )
                $error_msg = 'Error: '.$error_arr['display_error'];
            else
                $error_msg = 'Authentication failed.';

            $this->writeLog( $error_msg );
            echo $error_msg;
            exit;
        }

        $this->writeLog( 'Received notification type ['.$notification_title.'].'  );

        switch( $notification_type )
        {
            case $notification_obj::TYPE_PAYMENT:
                if( empty( $result_arr )
                 or empty( $result_arr['payment'] ) or !is_array( $result_arr['payment'] ) )
                {
                    $error_msg = 'Couldn\'t extract payment object.';
                    $error_msg .= 'Input buffer: '.$notification_obj->get_input_buffer();

                    $this->writeLog( $error_msg );
                    echo $error_msg;
                    exit;
                }

                $payment_arr = $result_arr['payment'];

                if( empty( $payment_arr['merchanttransactionid'] )
                 or empty( $payment_arr['status'] ) or empty( $payment_arr['status']['id'] ) )
                {
                    $error_msg = 'MerchantTransactionID or Status not provided.';
                    $error_msg .= 'Input buffer: '.$notification_obj->get_input_buffer();

                    $this->writeLog( $error_msg );
                    echo $error_msg;
                    exit;
                }

                if( !isset( $payment_arr['amount'] ) or !isset( $payment_arr['currency'] ) )
                {
                    $error_msg = 'Amount or Currency not provided.';
                    $error_msg .= 'Input buffer: '.$notification_obj->get_input_buffer();

                    $this->writeLog( $error_msg, array( 'order_id' => $payment_arr['merchanttransactionid'] ) );
                    echo $error_msg;
                    exit;
                }

                if( !($transaction_arr = $this->get_transaction_by_order_id( $payment_arr['merchanttransactionid'] )) )
                {
                    $error_msg = 'Couldn\'t obtain transaction details for id ['.$payment_arr['merchanttransactionid'].'].';
                    $this->writeLog( $error_msg, array( 'order_id' => $payment_arr['merchanttransactionid'] ) );
                    echo $error_msg;
                    exit;
                }

                // if( (string)($transaction_arr['amount'] * 100) !== (string)$payment_arr['amount']
                //  or $transaction_arr['currency'] != $payment_arr['currency'] )
                // {
                //     $error_msg = 'Transaction details don\'t match ['.
                //                  ($transaction_arr['amount'] * 100).' != '.$payment_arr['amount'].
                //                  ' OR '.
                //                  $transaction_arr['currency'].' != '.$payment_arr['currency'].']';
                //
                //     $this->writeLog( array( 'message' => $error_msg, 'order_id' => $payment_arr['merchanttransactionid'] ) );
                //     echo $error_msg;
                //     exit;
                // }
                //
                // if( !($order = wc_get_order( $transaction_arr['order_id'] )) )
                // {
                //     $error_msg = 'Couldn\'t obtain order details [#'.$transaction_arr['order_id'].']';
                //
                //     $this->writeLog( array( 'message' => $error_msg, 'order_id' => $payment_arr['merchanttransactionid'] ) );
                //     echo $error_msg;
                //     exit;
                // }

                if( !($status_title = S2P_SDK\S2P_SDK_Meth_Payments::valid_status( $payment_arr['status']['id'] )) )
                    $status_title = '(unknown)';

                $edit_arr = array();
                $edit_arr['order_id'] = $transaction_arr['order_id'];
                $edit_arr['payment_status'] = $payment_arr['status']['id'];

                if( !($new_transaction_arr = $this->save_transaction( $edit_arr )) )
                {
                    $error_msg = 'Couldn\'t save transaction details to database [#'.$transaction_arr['id'].', Order: '.$transaction_arr['order_id'].'].';
                    $this->writeLog( $error_msg, array( 'order_id' => $payment_arr['merchanttransactionid'] ) );
                    echo $error_msg;
                    exit;
                }

                $this->writeLog( 'Received '.$status_title.' notification for transaction '.$payment_arr['merchanttransactionid'].'.', array( 'order_id' => $payment_arr['merchanttransactionid'] ) );

                $customer = false;
                $currency = false;
                if( !empty( $order ) )
                {
                    $customer = new Customer( $order->id_customer );
                    $currency = new Currency( $order->id_currency );
                }

                // Update database according to payment status
                switch( $payment_arr['status']['id'] )
                {
                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_PENDING_CUSTOMER:
                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_PENDING_PROVIDER:
                    break;

                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_OPEN:
                        if( !empty( $plugin_settings[self::CONFIG_PREFIX.'SEND_PAYMENT_INSTRUCTIONS'] )
                        and !empty( $transaction_arr['method_id'] )
                        and in_array( $transaction_arr['method_id'], array( self::PAYM_BANK_TRANSFER, self::PAYM_MULTIBANCO_SIBS ) )
                        and $transaction_arr['payment_status'] != self::S2P_STATUS_OPEN
                        and !empty( $transaction_arr['extra_data'] )
                        and ($extra_vars_arr = Smart2Pay_Helper::parse_string( $transaction_arr['extra_data'] )) )
                        {
                            $info_fields = self::defaultRestTransactionLoggerExtraParams();
                            $template_vars = array();
                            foreach( $info_fields as $key => $def_val )
                            {
                                if( array_key_exists( $key, $extra_vars_arr ) )
                                    $template_vars['{'.$key.'}'] = $extra_vars_arr[$key];
                                else
                                    $template_vars['{'.$key.'}'] = $def_val;
                            }

                            $template_vars['{name}'] = Tools::safeOutput( $customer->firstname );

                            $template_vars['{OrderReference}'] = Tools::safeOutput( (version_compare( _PS_VERSION_, '1.5', '>=' )?$order->reference:'#'.$order->id) );
                            $template_vars['{OrderDate}'] = Tools::safeOutput( Tools::displayDate( $order->date_add, $order->id_lang, true ) );
                            $template_vars['{OrderPayment}'] = Tools::safeOutput( $order->payment );

                            if( $transaction_arr['method_id'] == self::PAYM_BANK_TRANSFER )
                                $template = 'instructions_bank_transfer';
                            elseif( $transaction_arr['method_id'] == self::PAYM_MULTIBANCO_SIBS )
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

                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_SUCCESS:
                        /*
                         * Check amount  and currency
                         */
                        $this->writeLog( 'Verifying order status.', array( 'type' => 'info', 'order_id' => $order->id ) );

                        $initialOrderAmount = $orderAmount = number_format( Smart2Pay_Helper::get_order_total_amount( $order ), 2, '.', '' );
                        $orderCurrency = $currency->iso_code;

                        $surcharge_amount = 0;
                        // Add surcharge if we have something...
                        if( (float)$transaction_arr['surcharge_order_percent'] != 0 )
                            $surcharge_amount += (float)$transaction_arr['surcharge_order_percent'];
                        if( (float)$transaction_arr['surcharge_order_amount'] != 0 )
                            $surcharge_amount += (float)$transaction_arr['surcharge_order_amount'];

                        $orderAmount += $surcharge_amount;

                        if( !empty( $plugin_settings[self::CONFIG_PREFIX.'ALTER_ORDER_ON_SURCHARGE'] ) )
                            $orderAmount_check = number_format( $initialOrderAmount * 100, 0, '.', '' );
                        else
                            $orderAmount_check = number_format( $orderAmount * 100, 0, '.', '' );

                        if( strcmp( $orderAmount_check, $payment_arr['amount'] ) != 0
                         or $orderCurrency != $payment_arr['currency'] )
                            $this->writeLog( 'Smart2Pay :: notification has different amount[' . $orderAmount_check . '/' . $payment_arr['amount'] . '] '.
                                                   ' and/or currency [' . $orderCurrency . '/' . $payment_arr['currency'] . ']. Please contact support@smart2pay.com.', array( 'type' => 'error', 'order_id' => $order->id ) );

                        elseif( empty( $payment_arr['methodid'] )
                                or !($method_details = $this->get_method_details( $payment_arr['methodid'], $plugin_settings['environment'] )) )
                            $this->writeLog( 'Smart2Pay :: Couldn\'t get method details ['.$payment_arr['methodid'].']', array( 'type' => 'error', 'order_id' => $order->id ) );

                        // PrestaShop updates $order->current_state pretty late so we might get another call from server with a new notification...
                        elseif( $this->get_quick_last_order_status( $order ) == $plugin_settings[self::CONFIG_PREFIX.'ORDER_STATUS_ON_SUCCESS'] )
                            $this->writeLog( 'Order already on success status.', array( 'type' => 'error', 'order_id' => $order->id ) );

                        else
                        {
                            $orderAmount = number_format( $orderAmount_check / 100, 2, '.','' );

                            $this->writeLog( 'Order ['.(version_compare( _PS_VERSION_, '1.5', '<' )?$order->id:$order->reference).'] has been paid', array( 'order_id' => $order->id ) );

                            $order_only_amount = $initialOrderAmount;
                            if( !empty( $plugin_settings[self::CONFIG_PREFIX.'ALTER_ORDER_ON_SURCHARGE'] )
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
                                    $payment_arr['id'],
                                    $currency
                                );

                                if( $surcharge_amount != 0 )
                                {
                                    $order->addOrderPayment(
                                        $surcharge_amount,
                                        $this->l( 'Payment Surcharge' ),
                                        $payment_arr['id'],
                                        $currency
                                    );
                                }
                            }

                            $this->changeOrderStatus( $order, $plugin_settings[self::CONFIG_PREFIX.'ORDER_STATUS_ON_SUCCESS'] );

                            if( !empty( $plugin_settings[self::CONFIG_PREFIX.'NOTIFY_CUSTOMER_BY_EMAIL'] ) )
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

                            if( !empty( $plugin_settings[self::CONFIG_PREFIX.'CREATE_INVOICE_ON_SUCCESS'] ) )
                                $this->check_order_invoices( $order, array( 'check_delivery' => (!empty( $plugin_settings[self::CONFIG_PREFIX.'AUTOMATE_SHIPPING'] )?true:false) ) );

                        }

                        $this->writeLog( 'Payment success!', array( 'order_id' => $payment_arr['merchanttransactionid'] ) );
                    break;

                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_CANCELLED:
                        $this->writeLog( 'Payment canceled', array( 'type' => 'info', 'order_id' => $order->id ) );
                        $this->changeOrderStatus( $order, $plugin_settings[self::CONFIG_PREFIX.'ORDER_STATUS_ON_CANCEL'] );
                        // There is no way to cancel an order other but changing it's status to canceled
                        // What we do is not changing order status to canceled, but to a user set one, instead
                    break;

                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_FAILED:
                        $this->writeLog( 'Payment failed', array( 'type' => 'info', 'order_id' => $order->id ) );
                        $this->changeOrderStatus( $order, $plugin_settings[self::CONFIG_PREFIX.'ORDER_STATUS_ON_FAIL'] );
                    break;

                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_EXPIRED:
                        $this->writeLog( 'Payment expired', array( 'type' => 'info', 'order_id' => $order->id ) );
                        $this->changeOrderStatus($order, $plugin_settings[self::CONFIG_PREFIX.'ORDER_STATUS_ON_EXPIRE']);
                    break;

                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_PROCESSING:
                    case S2P_SDK\S2P_SDK_Meth_Payments::STATUS_AUTHORIZED:
                    break;
                }
            break;

            case $notification_obj::TYPE_PREAPPROVAL:
                $this->writeLog( 'Preapprovals not implemented.' );
            break;
        }

        if( $notification_obj->respond_ok() )
            $this->writeLog( '--- Sent OK -------------------------------', array( 'type' => 'info', 'order_id' => ($order?$order->id:0) ) );

        else
        {
            if( $notification_obj->has_error()
            and ($error_arr = $notification_obj->get_error()) )
                $error_msg = 'Error: '.$error_arr['display_error'];
            else
                $error_msg = 'Couldn\'t send ok response.';

            $this->writeLog( $error_msg, array( 'type' => 'error', 'order_id' => ($order?$order->id:0) ) );
            echo $error_msg;
        }

        exit;
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

        $plugin_settings_arr = $this->getSettings();

        /**
         * Check submit for payment method settings
         */
        if( Tools::isSubmit( 'submit_syncronize_methods' ) )
        {
            $post_data['submit'] = 'submit_syncronize_methods';

            if( empty( self::$s2p_sdk_obj ) )
                $post_data['errors_buffer'] .= $this->displayError( 'Error initializing Smart2Pay SDK to syncronize payment methods.' );

            else
            {
                $sdk_obj = self::$s2p_sdk_obj;

                if( $sdk_obj->refresh_available_methods() )
                    $post_data['success_buffer'] .= $this->displayConfirmation( $this->l( 'Payment method details saved.' ) );

                else
                {
                    $error_msg = $this->l( 'Couldn\'t syncronize payment methods with Smart2Pay servers. Please try again later.' );
                    if( $sdk_obj->has_error() )
                        $error_msg = $sdk_obj->get_error_message();

                    $post_data['errors_buffer'] .= $this->displayError( $error_msg );
                }

                $this->refresh_method_countries();
                $this->clean_methods_cache();
                $this->get_all_methods( $plugin_settings_arr['environment'] );
                $this->get_all_method_settings( $plugin_settings_arr['environment'] );
            }
        }

        /**
         * Check submit for payment method settings
         */
        elseif( Tools::isSubmit( 'submit_payment_methods' )
             or Tools::isSubmit( 'submit_payment_methods_2' ) )
        {
            $post_data['submit'] = 'submit_payment_methods';

            $all_methods_arr = $this->get_all_methods( $plugin_settings_arr['environment'] );

            $enabled_methods_arr = Tools::getValue( 'enabled_methods', array() );
            $enabled_method_countries = Tools::getValue( 'enabled_method_countries', array() );
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
                $method_settings['environment'] = $plugin_settings_arr['environment'];
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

            $all_method_countries = $this->get_method_countries_all( $plugin_settings_arr['environment'] );
            $method_countries_enabled = $this->get_method_countries_enabled( $plugin_settings_arr['environment'] );
            foreach( $enabled_method_countries as $method_id => $country_methods )
            {
                if( empty( $all_method_countries[$method_id] ) or !is_array( $all_method_countries[$method_id] ) )
                    continue;

                $selected_method_countries = array();
                if( !empty( $country_methods ) )
                {
                    if( !($tmp_countries_arr = explode( ',', $country_methods )) )
                        $tmp_countries_arr = array();

                    foreach( $tmp_countries_arr as $country_id )
                    {
                        $country_id = intval( $country_id );
                        if( empty( $country_id ) )
                            continue;

                        $selected_method_countries[] = $country_id;
                    }
                }

                if( !empty( $method_countries_enabled[$method_id] )
                and !array_diff( $method_countries_enabled[$method_id], $selected_method_countries )
                and !array_diff( $selected_method_countries, $method_countries_enabled[$method_id] ) )
                    continue;

                if( !Db::getInstance()->execute( 'UPDATE `'._DB_PREFIX_.'smart2pay_country_method` SET enabled = 0 '.
                                                 ' WHERE method_id = \''.$method_id.'\' AND environment = \''.$plugin_settings_arr['environment'].'\'' )
                 or (count( $selected_method_countries )
                      and !Db::getInstance()->execute( 'UPDATE `'._DB_PREFIX_.'smart2pay_country_method` SET enabled = 1 '.
                                                       ' WHERE method_id = \''.$method_id.'\' AND environment = \''.$plugin_settings_arr['environment'].'\''.
                                                       ' AND country_id IN ('.implode( ',', $selected_method_countries ).')' ))
                 )
                {
                    $post_data['errors_buffer'] .= $this->displayError( 'Error saving country details for payment method '.$all_methods_arr[$method_id]['display_name'].'.' );
                }
            }

            $this->refresh_method_countries();

            if( empty( $valid_ids ) )
                Db::getInstance()->execute( 'TRUNCATE TABLE `'._DB_PREFIX_.'smart2pay_method_settings`' );

            else
                Db::getInstance()->execute( 'DELETE FROM `'._DB_PREFIX_.'smart2pay_method_settings` WHERE environment = \''.$plugin_settings_arr['environment'].'\''.
                                            ' AND method_id NOT IN ('.implode( ',', $valid_ids ).')' );

            $this->clean_methods_cache();

            $all_methods_arr = $this->get_all_methods( $plugin_settings_arr['environment'] );
            $this->get_all_method_settings( $plugin_settings_arr['environment'] );

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
                and in_array( $input['name'], array( self::CONFIG_PREFIX.'SITE_ID_LIVE', self::CONFIG_PREFIX.'APIKEY_LIVE' ) ) )
                    $skipValidation = true;

                if( in_array( $formValues[self::CONFIG_PREFIX.'ENV'], array( 'demo', 'live' ) )
                and in_array( $input['name'], array( self::CONFIG_PREFIX.'SITE_ID_TEST', self::CONFIG_PREFIX.'APIKEY_TEST' ) ) )
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

            $this->getSettings( null, true );

            $this->refresh_method_countries();
            $this->clean_methods_cache();
            $this->get_all_methods();
            $this->get_all_method_settings();

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

        if( !$this->_init_sdk_instance() )
            return 'Cannot initiate Smart2Pay SDK. Please make sure you also installed Smart2Pay SDK in plugin directory under includes/sdk directory.';

        $sdk_obj = self::$s2p_sdk_obj;

        $plugin_environment = 'demo';
        if( ($plugin_settings = $this->getSettings())
        and !empty( $plugin_settings['environment'] ) )
            $plugin_environment = strtolower( $plugin_settings['environment'] );

        if( !($last_sync_date = $sdk_obj->last_methods_sync_option()) )
            $last_sync_date = false;
        if( !($time_to_launch_sync = $sdk_obj->seconds_to_launch_sync_str()) )
            $time_to_launch_sync = false;

        $this->S2P_add_css( _MODULE_DIR_ . $this->name . '/views/css/back-style.css' );

        $comma_countries_methods_arr = array();
        if( ($methods_countries_enabled = $this->get_method_countries_enabled()) )
        {
            foreach( $methods_countries_enabled as $method_id => $countries_methods_arr )
            {
                if( empty( $method_id ) or !is_array( $countries_methods_arr ) )
                    continue;

                $comma_countries_methods_arr[$method_id] = implode( ',', $countries_methods_arr );
            }
        }

        if( !($all_currencies_arr = Currency::getCurrencies()) )
            $all_currencies_arr = array();

        $this->context->smarty->assign( array(
            'plugin_environment' => $plugin_environment,
            'last_sync_date' => $last_sync_date,
            'time_to_launch_sync' => $time_to_launch_sync,
            'module_path' => $this->_path,
            'logos_path' => $this->_path.'views/img/logos/',
            'default_currency_id' => Currency::getDefaultCurrency()->id,
            'default_currency_iso' => Currency::getDefaultCurrency()->iso_code,
            'all_currencies' => $all_currencies_arr,
            'countries_by_id' => $this->get_smart2pay_id_countries(),
            'method_countries' => $this->get_method_countries_all_with_details(), // $this->get_method_countries_all(),
            'comma_countries_methods' => $comma_countries_methods_arr,
            'payment_methods' => $this->get_all_methods(),
            'payment_method_settings' => $this->get_all_method_settings(),
        ) );

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

        if( !$this->_init_sdk_instance() )
            return 'Cannot initiate Smart2Pay SDK. Please make sure you also installed Smart2Pay SDK in plugin directory under includes/sdk directory.';

        $sdk_obj = self::$s2p_sdk_obj;
        if( !($sdk_version = $sdk_obj::get_sdk_version()) )
            return 'Cannot get Smart2Pay SDK version. Please make sure you also installed Smart2Pay SDK in plugin directory under includes/sdk directory.';

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

        $form_buffer = 'Plugin version: '.$this->version.'<br/>'.
                       'Smart2Pay SDK version: '.$sdk_version.'<br/>';

        $form_buffer .= '<p><strong>NOTE</strong>: For a better understanding of our plugin, please check our integration guide: <a href="https://docs.smart2pay.com/category/smart2pay-plugins/smart2pay-prestashop-plugin/" style="text-decoration: underline;" target="_blank">Smart2Pay PrestaShop Integration Guide</a></p>';

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

    private function _get_hooks_by_version()
    {
        $hooks_arr = array();
        $hooks_arr['header'] = true;

        if( version_compare( _PS_VERSION_, '1.5', '<' ) )
        {
            // Displaying invoices (1.5+ doesn't offer access to pdf renderer)
            $hooks_arr['PDFInvoice'] = true;
            // box right above product listing
            $hooks_arr['orderDetailDisplayed'] = true;
            // Order content for 1.5
            $hooks_arr['adminOrder'] = true;
        }

        if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
        {
            // box right above product listing
            $hooks_arr['displayOrderDetail'] = true;
        }

        if( version_compare( _PS_VERSION_, '1.5', '>=' )
        and version_compare( _PS_VERSION_, '1.6', '<' ) )
        {
            // Order content for 1.5
            $hooks_arr['displayAdminOrder'] = true;
        }

        if( version_compare( _PS_VERSION_, '1.6', '>=' ) )
        {
            $hooks_arr['displayAdminOrderTabOrder'] = true; // Order tabs
            $hooks_arr['displayAdminOrderContentOrder'] = true; // Order tab content
        }

        if( version_compare( _PS_VERSION_, '1.7', '<' ) )
        {
            // Pre 1.7 fron payment options display hook
            $hooks_arr['payment'] = true;
        }

        if( version_compare( _PS_VERSION_, '1.7', '>=' ) )
        {
            // Post 1.7 fron payment options display hook
            $hooks_arr['paymentOptions'] = true;
        }

        return array_keys( $hooks_arr );
    }

    /**
     * Install
     *
     * @return bool
     */
    public function install()
    {
        self::$maintenance_functionality = true;

        if( !parent::install() )
        {
            self::$maintenance_functionality = false;
            return false;
        }

        if( !($hooks_arr = $this->_get_hooks_by_version())
         or !is_array( $hooks_arr ) )
        {
            self::$maintenance_functionality = false;
            return false;
        }

        foreach( $hooks_arr as $hook_name )
        {
            if( !$this->registerHook( $hook_name ) )
            {
                self::$maintenance_functionality = false;
                return false;
            }
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

        $this->createCustomOrderStatuses();

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

        if( ($hooks_arr = $this->_get_hooks_by_version())
        and is_array( $hooks_arr ) )
        {
            foreach( $hooks_arr as $hook_name )
            {
                if( !$this->unregisterHook( $hook_name ) )
                    return false;
            }
        }

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

        if( function_exists( 'smartyRegisterFunction' ) )
            smartyRegisterFunction( $this->context->smarty, 'function', 'S2P_displayPrice', array( 'Tools', 'displayPriceSmarty' ) );

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
            'transaction_extra_titles_rest' => self::rest_transaction_logger_params_to_title(),
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
            'transaction_extra_titles_rest' => self::rest_transaction_logger_params_to_title(),
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

    public function get_payment_link( $params = false )
    {
        if( empty( $params ) or !is_array( $params )
         or (
             (empty( $params['method_id'] ) or !(int)$params['method_id'])
            and
             (empty( $params['link_for_form'] ) or !(int)$params['link_for_form'])
            ) )
            return '#';

        if( empty( $params['method_id'] ) or !(int)$params['method_id'] )
            $params['method_id'] = 0;

        $url_params_arr = array();
        $url_params_str = '';
        if( !empty( $params['method_id'] ) )
        {
            $url_params_arr['method_id'] = $params['method_id'];
            $url_params_str = '?method_id='.$params['method_id'];
        }

        if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
            return $this->context->link->getModuleLink( 'smart2pay', 'payment', $url_params_arr );

        return Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/pre15/payment.php?'.$url_params_str;
    }

    /**
     * @param FrontController $front_controller Which controller will do the redirect
     * @param string $to_controller To which controller are we redirecting
     * @param array|bool $to_params Parameters to be passed to controller
     * @param array|bool $messages_arr Messages array ('error', 'warning', 'success' or 'info' keys should be arrays)
     *
     * @return bool
     */
    private function _redirect_to_page( $front_controller, $to_controller, $to_params = false, $messages_arr = false )
    {
        if( empty( $front_controller )
         or !is_a( $front_controller, 'FrontController' ) )
            return false;

        if( !empty( $messages_arr ) and is_array( $messages_arr ) )
        {
            $messages_keys = array( 'error', 'warning', 'success', 'info' );
            foreach( $messages_keys as $key )
            {
                if( empty( $messages_arr[$key] ) or !is_array( $messages_arr[$key] ) )
                    continue;

                $front_controller->$key = array_merge( $front_controller->$key, $messages_arr[$key] );
            }
        }

        if( empty( $to_params ) or !is_array( $to_params ) )
            $to_params = array();

        $front_controller->redirectWithNotifications( $this->context->link->getPageLink( $to_controller, true, null, array(
            'step' => '3')));
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

    public function hookHeader()
    {
        $this->create_context();

        $is_front = true;
        if( @class_exists( 'FrontController', false )
        and $this->context->controller
        and !@is_a( Context::getContext()->controller, 'FrontController' ) )
            $is_front = false;

        elseif( $this->context->controller
            and !empty( $this->context->controller->controller_type ) )
        {
            if( !in_array( $this->context->controller->controller_type, array( 'front', 'modulefront' ) ) )
                $is_front = false;
        }

        else
        {
            // include all...
            $this->S2P_add_css( _MODULE_DIR_ . $this->name . '/views/css/style.css' );
            $this->S2P_add_css( _MODULE_DIR_ . $this->name . '/views/css/back-style.css' );

            return;
        }

        if( $is_front )
            $this->S2P_add_css( _MODULE_DIR_ . $this->name . '/views/css/style.css' );
        else
            $this->S2P_add_css( _MODULE_DIR_ . $this->name . '/views/css/back-style.css' );
    }

    public function hookPaymentOptions( $params )
    {
        $cart = (!empty( $this->context )?$this->context->cart:false);

        if( empty( $cart )
         or !Validate::isLoadedObject( $cart )
         or !Configuration::get( self::CONFIG_PREFIX.'ENABLED' )
         or !($template_data = $this->get_payment_template_data())
         or empty( $template_data['payment_methods'] ) )
            return;

        if( function_exists( 'smartyRegisterFunction' ) )
            smartyRegisterFunction( $this->context->smarty, 'function', 'S2P_displayPrice', array( 'Tools', 'displayPriceSmarty' ) );

        $template_data['test_price'] = Tools::displayPrice( 5, $template_data['methods_detected_currency'] );

        // $this->context->smarty->assign( $template_data );
        $this->S2P_add_css( $this->_path . '/views/css/style.css' );

        $payment_options = array();
        foreach( $template_data['payment_methods'] as $method_id => $method_arr )
        {
            $payment_option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $payment_option
                      //->setCallToActionText( $this->l( 'Pay using ' ).$method_arr['method']['display_name'] )
                      ->setAction( $this->get_payment_link( array( 'method_id' => $method_arr['method']['method_id'] ) ) )
                      ->setLogo( $method_arr['method']['logo_url'] )
            ;

            $payment_options[] = $payment_option;
        }

        return $payment_options;

        // $payment_option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        // $payment_option->setCallToActionText( $this->trans('Pay using Smart2Pay', array(), 'Modules.Smart2Pay.Admin' ) )
        //           ->setForm( $this->fetch( 'module:smart2pay/views/templates/front/payment_1_7.tpl' ) )
        // ;
        //
        // return array( $payment_option );
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
         or !Configuration::get( self::CONFIG_PREFIX.'ENABLED' )
         or !($template_data = $this->get_payment_template_data()) )
            return '';

        $this->S2P_add_css( $this->_path . '/views/css/style.css' );

        $this->smarty->assign( $template_data );

        return $this->fetchTemplate( 'payment.tpl' );
    }

    private function get_payment_template_data()
    {
        if( empty( $this->context ) or empty( $this->context->cart ) )
            return false;

        $cart = $this->context->cart;

        $cart_original_amount = number_format( $cart->getOrderTotal( true, Cart::BOTH ), 2, '.', '' );

        $cart_currency = new Currency( $cart->id_currency );

        $method_params = array();
        $method_params['cart_amount'] = $cart_original_amount;
        $method_params['opt_currency'] = Configuration::get( self::CONFIG_PREFIX.'SURFEE_CURRENCY' );
        $method_params['opt_amount'] = Configuration::get( self::CONFIG_PREFIX.'SURFEE_AMOUNT' );

        if( empty( $cart_currency )
         or !($payment_methods_arr = $this->get_methods_for_country( null, $cart_currency, $method_params ))
         or empty( $payment_methods_arr['methods'] ) )
            return false;

        $display_options = array(
            'from_admin' => self::OPT_FEE_CURRENCY_ADMIN,
            'from_front' => self::OPT_FEE_CURRENCY_FRONT,

            'amount_separated' => self::OPT_FEE_AMOUNT_SEPARATED,
            'amount_total' => self::OPT_FEE_AMOUNT_TOTAL_FEE,
            'order_total' => self::OPT_FEE_AMOUNT_TOTAL_ORDER,
        );

        $moduleSettings = $this->getSettings();

        return
            array(
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
                'settings_prefix' => self::CONFIG_PREFIX,
                'moduleSettings' => $moduleSettings,
            );
    }

    private function _module_available( $module )
    {
        if( version_compare( _PS_VERSION_, '1.7', '>=' ) )
        {
            if( !($moduleManagerBuilder = PrestaShop\PrestaShop\Core\Addon\Module\ModuleManagerBuilder::getInstance())
             or !($moduleManager = $moduleManagerBuilder->build())
             or !$moduleManager->isInstalled( $module )
             or !$moduleManager->isEnabled( $module ) )
                return false;

            return true;
        }

        if( !Module::isInstalled( $module )
         or (version_compare( _PS_VERSION_, '1.5', '>=' ) and !Module::isEnabled( $module )) )
            return false;

        return true;
    }

    public function detection_module_available()
    {
        return $this->_module_available( self::S2P_DETECTOR_NAME );
    }

    public function payment_module_available()
    {
        return $this->_module_available( $this->name );
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

    public function validate_ip( $ip )
    {
        if( function_exists( 'filter_var' ) and defined( 'FILTER_VALIDATE_IP' ) )
            return filter_var( $ip, FILTER_VALIDATE_IP );

        if( !($ip_numbers = explode( '.', $ip ))
        or !is_array( $ip_numbers ) or count( $ip_numbers ) != 4 )
            return false;

        $parsed_ip = '';
        foreach( $ip_numbers as $ip_part )
        {
            $ip_part = intval( $ip_part );
            if( $ip_part < 0 or $ip_part > 255 )
                return false;

            $parsed_ip = ($parsed_ip!=''?'.':'').$ip_part;
        }

        return $parsed_ip;
    }

    public function guess_ip()
    {
        if( !($settings_arr = $this->getSettings())
         or empty( $settings_arr[self::CONFIG_PREFIX.'PROXY_IP'] ) )
            return (!empty( $_SERVER['REMOTE_ADDR'] )?$_SERVER['REMOTE_ADDR']:'');

        $guessed_ip = '';
        if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) )
            $guessed_ip = $this->validate_ip( $_SERVER['HTTP_CLIENT_IP'] );

        if( empty( $guessed_ip )
        and !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
            $guessed_ip = $this->validate_ip( $_SERVER['HTTP_X_FORWARDED_FOR'] );

        if( empty( $guessed_ip ) )
            $guessed_ip = (!empty( $_SERVER['REMOTE_ADDR'] )?$_SERVER['REMOTE_ADDR']:'');

        return $guessed_ip;
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
            $ip = $this->guess_ip();

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
     * @param Order|null $order
     * @return array
     */
    public function getSettings( Order $order = null, $force = false )
    {
        static $settings = false;

        if( empty( $force )
        and !empty( $settings ) )
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

        if( empty( $settings[self::CONFIG_PREFIX.'ENV'] ) )
            $settings[self::CONFIG_PREFIX.'ENV'] = 'demo';

        $settings['environment'] = strtolower( $settings[self::CONFIG_PREFIX.'ENV'] );

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
     * @param bool|array $params
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
    public function get_smart2pay_codes_countries()
    {
        if( !empty( self::$maintenance_functionality ) )
            return array();

        if( !empty( self::$cache['all_codes_countries'] ) )
            return self::$cache['all_codes_countries'];

        $this->get_smart2pay_countries();

        return self::$cache['all_codes_countries'];
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
        self::$cache['all_codes_countries'] = array();

        if( empty( $country_rows ) )
            return array();

        foreach( $country_rows as $country_arr )
        {
            self::$cache['all_countries'][$country_arr['code']] = $country_arr['name'];
            self::$cache['all_id_countries'][$country_arr['country_id']] = array( 'code' => $country_arr['code'], 'name' => $country_arr['name'] );
            self::$cache['all_codes_countries'][$country_arr['code']] = $country_arr['country_id'];
        }

        return self::$cache['all_countries'];
    }

    /**
     * Get all defined Smart 2 Pay methods which are active. Result is cached per method id.
     *
     * @param bool|string $environment
     * @return array
     */
    public function get_all_methods( $environment = false )
    {
        if( !empty( self::$cache['all_method_details_in_cache'] ) and !empty( self::$cache['method_details'] ) )
            return self::$cache['method_details'];

        if( empty( $environment ) )
        {
            $plugin_settings_arr = $this->getSettings();
            $environment = $plugin_settings_arr['environment'];
        }

        self::$cache['method_details'] = array();

        if( ($methods = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'smart2pay_method` WHERE `active` = 1 AND environment = \''.$environment.'\' ORDER BY `display_name` ASC' )) )
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
     * @param bool|string $environment
     *
     * @return array
     */
    public function get_all_method_settings( $environment = false )
    {
        $this->create_context();

        if( !empty( self::$cache['all_method_settings_in_cache'] ) and !empty( self::$cache['method_settings'] ) )
            return self::$cache['method_settings'];

        if( empty( $environment ) )
        {
            $plugin_settings_arr = $this->getSettings();
            $environment = $plugin_settings_arr['environment'];
        }

        self::$cache['method_settings'] = array();

        $default_currency = null;
        if( !empty( $this->context->currency ) )
            $default_currency = $this->context->currency;

        if( ($methods = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'smart2pay_method_settings` WHERE environment = \''.$environment.'\' ORDER BY `priority` ASC' )) )
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
     * @param bool|string $environment
     *
     * @return array|null
     */
    public function get_method_details( $method_id, $environment = false )
    {
        $method_id = (int)$method_id;
        if( array_key_exists( $method_id, self::$cache['method_details'] ) )
            return self::$cache['method_details'][$method_id];

        if( empty( $environment ) )
        {
            $plugin_settings_arr = $this->getSettings();
            $environment = $plugin_settings_arr['environment'];
        }

        $method = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'smart2pay_method` WHERE `method_id` = \''.$method_id.'\' AND environment = \''.$environment.'\' LIMIT 0, 1' );
        if( empty( $method ) )
            return null;

        self::$cache['method_details'][$method_id] = $method[0];

        return self::$cache['method_details'][$method_id];
    }

    /**
     * Get countries of payment method.
     *
     * @param bool|string $environment
     *
     * @return array|null
     */
    public function get_method_countries_all_with_details( $environment = false )
    {
        if( !empty( self::$cache['all_method_countries_details'] ) )
            return self::$cache['all_method_countries_details'];

        if( empty( self::$cache['all_method_countries'] ) )
            self::$cache['all_method_countries'] = array();
        if( empty( self::$cache['all_method_countries_enabled'] ) )
            self::$cache['all_method_countries_enabled'] = array();
        if( empty( self::$cache['all_method_countries_details'] ) )
            self::$cache['all_method_countries_details'] = array();

        if( empty( $environment ) )
        {
            $plugin_settings_arr = $this->getSettings();
            $environment = $plugin_settings_arr['environment'];
        }

        if( ($methods_countries = Db::getInstance()->executeS(

            'SELECT * '.
            ' FROM `'._DB_PREFIX_.'smart2pay_country_method` CM '.
            ' LEFT JOIN `'._DB_PREFIX_.'smart2pay_country` C ON C.country_id = CM.country_id '.
            ' WHERE CM.environment = \''.$environment.'\''.
            ' ORDER BY CM.method_id ASC, C.name ASC'
        )) )
        {
            foreach( $methods_countries as $method_country )
            {
                if( empty( $method_country ) or !is_array( $method_country )
                 or empty( $method_country['country_id'] ) or empty( $method_country['method_id'] ) )
                    continue;

                if( empty( self::$cache['all_method_countries'][$method_country['method_id']] ) )
                    self::$cache['all_method_countries'][$method_country['method_id']] = array();
                if( empty( self::$cache['all_method_countries_enabled'][$method_country['method_id']] ) )
                    self::$cache['all_method_countries_enabled'][$method_country['method_id']] = array();
                if( empty( self::$cache['all_method_countries_details'][$method_country['method_id']] ) )
                    self::$cache['all_method_countries_details'][$method_country['method_id']] = array();

                self::$cache['all_method_countries'][$method_country['method_id']][] = $method_country['country_id'];
                self::$cache['all_method_countries_details'][$method_country['method_id']][] = $method_country;

                if( !empty( $method_country['enabled'] ) )
                    self::$cache['all_method_countries_enabled'][$method_country['method_id']][] = $method_country['country_id'];
            }
        }

        return self::$cache['all_method_countries_details'];
    }

    /**
     * Get countries of payment method (enabled or not).
     *
     * @param bool|string $environment
     *
     * @return array
     */
    public function get_method_countries_all( $environment = false )
    {
        if( !empty( self::$cache['all_method_countries'] ) )
            return self::$cache['all_method_countries'];

        $this->get_method_countries_all_with_details( $environment );

        return self::$cache['all_method_countries'];
    }

    /**
     * Get countries of payment method (only enabled ones).
     *
     * @param bool|string $environment
     *
     * @return array
     */
    public function get_method_countries_enabled( $environment = false )
    {
        if( !empty( self::$cache['all_method_countries_enabled'] ) )
            return self::$cache['all_method_countries_enabled'];

        $this->get_method_countries_all_with_details( $environment );

        return self::$cache['all_method_countries_enabled'];
    }

    /**
     * Refresh countries of payment method from database
     * @param bool|string $environment
     */
    public function refresh_method_countries( $environment = false )
    {
        self::$cache['all_method_countries'] = array();
        self::$cache['all_method_countries_enabled'] = array();
        self::$cache['all_method_countries_details'] = array();

        $this->get_method_countries_all_with_details( $environment );
    }

    /**
     * Get countries of payment method.
     *
     * @param $method_id
     * @param bool|string $environment
     *
     * @return array|bool
     */
    public function get_method_countries( $method_id, $environment = false )
    {
        if( empty( self::$cache['all_method_countries'] ) )
            $this->get_method_countries_all( $environment );

        $method_id = (int)$method_id;
        if( array_key_exists( $method_id, self::$cache['all_method_countries'] ) )
            return self::$cache['all_method_countries'][$method_id];

        return false;
    }

    /**
     * Get payment method settings. Result is cached.
     *
     * @param int $method_id
     * @param bool|string $environment
     *
     * @return array|null
     */
    public function get_method_settings( $method_id, $environment = false )
    {
        $method_id = intval( $method_id );
        if( array_key_exists( $method_id, self::$cache['method_settings'] ) )
            return self::$cache['method_settings'][$method_id];

        if( empty( $environment ) )
        {
            if( !($plugin_settings_arr = $this->getSettings()) )
                return null;

            $environment = $plugin_settings_arr['environment'];
        }

        $method = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'smart2pay_method_settings` WHERE `method_id` = \''.$method_id.'\' AND environment = \''.$environment.'\' LIMIT 0, 1' );

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

    public static function rest_transaction_logger_params_to_title()
    {
        return array(
            'bankcode' => 'Bank Code',
            'bankname' => 'Bank Name',
            'entityid' => 'Entity ID',
            'entitynumber' => 'Entity Number',
            'referenceid' => 'Reference ID',
            'referencenumber' => 'Reference Number',
            'swift_bic' => 'SWIFT / BIC',
            'accountcurrency' => 'Account Currency',
            'accountnumber' => 'Account Number',
            'accountholder' => 'Account Holder',
            'iban' => 'IBAN',
            'qrcodeurl' => 'QR Code URL',
            'amounttopay' => 'Amount to Pay',
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

    /**
     * Keys in returning array should be variable names sent back by Smart2Pay and values should be default values if
     * variables are not found in request
     *
     * @return array
     */
    public static function defaultRestTransactionLoggerExtraParams()
    {
        return array(
            'bankcode' => '',
            'bankname' => '',
            'entityid' => '',
            'entitynumber' => '',
            'referenceid' => '',
            'referencenumber' => '',
            'swift_bic' => '',
            'accountcurrency' => '',
            'accountnumber' => '',
            'accountholder' => '',
            'iban' => '',
            'qrcodeurl' => '',
            'amounttopay' => '',
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
     * @return array|bool
     */
    public function save_method_settings( $method_id, $params )
    {
        $method_id = (int)$method_id;
        if( empty( $method_id )
         or empty( $params ) or !is_array( $params )
         or !($plugin_settings_arr = $this->getSettings()) )
            return false;

        if( empty( $params['environment'] ) )
            $params['environment'] = $plugin_settings_arr['environment'];
        if( isset( $params['enabled'] ) )
            $params['enabled'] = (!empty( $params['enabled'] )?1:0);
        if( isset( $params['surcharge_amount'] ) )
            $params['surcharge_amount'] = (float)$params['surcharge_amount'];
        if( isset( $params['surcharge_percent'] ) )
            $params['surcharge_percent'] = (float)$params['surcharge_percent'];
        if( isset( $params['priority'] ) )
            $params['priority'] = (int)$params['priority'];

        $new_settings_arr = array();
        if( !($current_settings = $this->get_method_settings( $method_id, $params['environment'] )) )
        {
            // if payment method is new and we don't have a currency set, return error...
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
            $insert_arr['environment'] = $params['environment'];
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
                 or !Db::getInstance()->execute( $sql.' WHERE id = \''.$current_settings['id'].'\'' ) )
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
     * @param null|string $country_iso   If no iso code is passed along, method checks if module can detect a
     *     country, else attempts to retrieve it from context->cart->id_address_invoice
     * @param Currency|null $currency_obj
     * @param bool|array $params
     *
     * @return bool|array
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
                    ' WHERE C.code = \''.pSQL( $country_iso ).'\' AND CM.enabled = 1'
                )) )
        {
            $this->writeLog( 'No methods for country ['.$country_iso.']', array( 'type' => 'detection' ) );
            return false;
        }

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

        $this->writeLog( 'Found ['.count( $country_methods_arr['methods'] ).'] methods for country ['.$country_iso.']', array( 'type' => 'detection' ) );

        return $country_methods_arr;
    }

    /**
     * Check if s2p method is available in some particular country
     *
     * @param int $method_id Method ID
     * @param null|string $country_iso If no iso code is passed along, method checks if module can detect a
     *     country, else attempts to retrieve it from context->cart->id_address_invoice
     * @param string|bool $environment
     *
     * @return bool|array
     */
    public function method_details_if_available( $method_id, $country_iso = null, $environment = false )
    {
        $method_id = (int)$method_id;
        if( empty( $method_id ) )
            return false;

        if( empty( $environment ) )
        {
            $plugin_settings_arr = $this->getSettings();
            $environment = $plugin_settings_arr['environment'];
        }

        /*
         * Check for base module to be active
         * Check for current module to be available
         */
        if( !Configuration::get( self::CONFIG_PREFIX.'ENABLED' )
         or !($method_details = $this->get_method_details( $method_id, $environment ))
         or !($method_settings = $this->get_method_settings( $method_id, $environment ))
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

        $this->writeLog( 'Using method ID ['.$method_id.'] for country ['.$country_iso.'], environment ['.$environment.'].', array( 'type' => 'detection' ) );

        $country_method = Db::getInstance()->executeS(
            'SELECT CM.method_id '.
            ' FROM '._DB_PREFIX_.'smart2pay_country_method CM '.
            ' LEFT JOIN '._DB_PREFIX_.'smart2pay_country C ON C.country_id = CM.country_id '.
            ' WHERE C.code = \''.pSQL( $country_iso ).'\' AND CM.method_id = '.$method_id.' AND CM.environment = \''.$environment.'\' AND CM.enabled = 1'
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
                'label' => $this->l('Site ID Live'),
                'name' => self::CONFIG_PREFIX.'SITE_ID_LIVE',
                '_transform' => array( 'intval' ),
                '_validate' => array( 'notempty' ),
                'required' => true,
                '_default' => 0,
            ),
            array(
                'type' => 'text',
                'label' => $this->l('APIKey Live'),
                'name' => self::CONFIG_PREFIX.'APIKEY_LIVE',
                'required' => true,
                '_transform' => array( 'trim' ),
                '_validate' => array( 'notempty' ),
                '_default' => '',
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Site ID Test'),
                'name' => self::CONFIG_PREFIX.'SITE_ID_TEST',
                '_transform' => array( 'intval' ),
                '_validate' => array( 'notempty' ),
                'required' => true,
                '_default' => 0,
            ),
            array(
                'type' => 'text',
                'label' => $this->l('APIKey Test'),
                'name' => self::CONFIG_PREFIX.'APIKEY_TEST',
                'required' => true,
                '_transform' => array( 'trim' ),
                '_validate' => array( 'notempty' ),
                '_default' => '',
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Skin ID'),
                'name' => self::CONFIG_PREFIX.'SKIN_ID',
                '_transform' => array( 'intval' ),
                'required' => true,
                '_default' => 0,
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
                'label' => $this->l( 'Use IP sent by proxy' ),
                'name' => self::CONFIG_PREFIX.'PROXY_IP',
                'desc' => array(
                    $this->l( 'If your site is behind a firewall IP in headers might be set for every request to firewall IP.' ),
                    $this->l( 'If HTTP_CLIENT_IP or HTTP_X_FORWARDED_FOR header is set by firewall to the actual IP of customer, this option tells plugin to check first if such variables are set in headers and if set use that as customer IP.' ),
                    $this->l( 'Plugin will check first if HTTP_CLIENT_IP is a valid IP, then HTTP_X_FORWARDED_FOR.' ),
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
                'label' => $this->l('Custom payment description'),
                'name' => self::CONFIG_PREFIX.'CUSTOM_PRODUCT_DESCRIPTION',
                'required' => true,
                'size' => '80',
                'desc' => array(
                    $this->l( 'eg. Payment on our web shop' ),
                ),
                '_default' => 'Custom payment description',
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
            /**
            array(
                'type' => 'select',
                'label' => $this->l('Show loading modal'),
                'name' => self::CONFIG_PREFIX.'LOADING_MODAL',
                'required' => false,
                'hint' => array(
                    $this->l( 'Show a loading modal window when user selects a payment method and when he/she is redirected to payment gateway.' ),
                ),
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name',
                ),
                '_default' => 0,
            ),
            /**/
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
                'desc' => array(
                    $this->l( 'eg. The payment succeeded' ),
                ),
                '_default' => 'The payment succeeded',
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message FAILED'),
                'name' => self::CONFIG_PREFIX.'MESSAGE_FAILED',
                'required' => true,
                'size' => '80',
                'desc' => array(
                    $this->l( 'eg. The payment process has failed' ),
                ),
                '_default' => 'The payment process has failed',
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message CANCELED'),
                'name' => self::CONFIG_PREFIX.'MESSAGE_CANCELED',
                'required' => true,
                'size' => '80',
                'desc' => array(
                    $this->l( 'eg. The payment was canceled' ),
                ),
                '_default' => 'The payment was canceled',
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message PENDING'),
                'name' => self::CONFIG_PREFIX.'MESSAGE_PENDING',
                'required' => true,
                'size' => '80',
                'desc' => array(
                    $this->l( 'eg. The payment is pending' ),
                ),
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
            /**
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
            /**/
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
                `payment_status` TINYINT(2) NOT NULL DEFAULT '0' COMMENT 'Status received from server',
                `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                 PRIMARY KEY (`id`), KEY `method_id` (`method_id`), KEY `payment_id` (`payment_id`), KEY `order_id` (`order_id`)
                ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8 COMMENT='Transactions run trough Smart2Pay';

        ") )
            return false;

        if( !Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_method_settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `environment` varchar(50) default NULL,
                `method_id` int(11) NOT NULL DEFAULT '0',
                `enabled` tinyint(2) NOT NULL DEFAULT '0',
                `surcharge_percent` decimal(6,2) NOT NULL DEFAULT '0.00',
                `surcharge_amount` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'Amount of surcharge',
                `surcharge_currency` varchar(3) DEFAULT NULL COMMENT 'ISO 3 currency code of fixed surcharge amount',
                `priority` tinyint(4) NOT NULL DEFAULT '10' COMMENT '1 means first',
                `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `configured` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), KEY `method_id` (`method_id`), KEY `environment` (`environment`), KEY `enabled` (`enabled`)
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
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `method_id` int(11) NOT NULL DEFAULT 0,
                `environment` varchar(50) default NULL,
                `display_name` varchar(255) default NULL,
                `description` text ,
                `logo_url` varchar(255) default NULL,
                `guaranteed` int(1) default 0,
                `active` tinyint(2) default 0,
                PRIMARY KEY (`id`), KEY `method_id` (`method_id`), KEY `environment` (`environment`), KEY `active` (`active`)
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8
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
            INSERT INTO `" . _DB_PREFIX_ . "smart2pay_country` (`code`, `name`) VALUES
            ('AD', 'Andorra'),
            ('AE', 'United Arab Emirates'),
            ('AF', 'Afghanistan'),
            ('AG', 'Antigua and Barbuda'),
            ('AI', 'Anguilla'),
            ('AL', 'Albania'),
            ('AM', 'Armenia'),
            ('AN', 'Netherlands Antilles'),
            ('AO', 'Angola'),
            ('AQ', 'Antarctica'),
            ('AR', 'Argentina'),
            ('AS', 'American Samoa'),
            ('AT', 'Austria'),
            ('AU', 'Australia'),
            ('AW', 'Aruba'),
            ('AZ', 'Azerbaijan'),
            ('BA', 'Bosnia & Herzegowina'),
            ('BB', 'Barbados'),
            ('BD', 'Bangladesh'),
            ('BE', 'Belgium'),
            ('BF', 'Burkina Faso'),
            ('BG', 'Bulgaria'),
            ('BH', 'Bahrain'),
            ('BI', 'Burundi'),
            ('BJ', 'Benin'),
            ('BM', 'Bermuda'),
            ('BN', 'Brunei Darussalam'),
            ('BO', 'Bolivia'),
            ('BR', 'Brazil'),
            ('BS', 'Bahamas'),
            ('BT', 'Bhutan'),
            ('BV', 'Bouvet Island'),
            ('BW', 'Botswana'),
            ('BY', 'Belarus (formerly known as Byelorussia)'),
            ('BZ', 'Belize'),
            ('CA', 'Canada'),
            ('CC', 'Cocos (Keeling) Islands'),
            ('CD', 'Congo, Democratic Republic of the (formerly Zalre)'),
            ('CF', 'Central African Republic'),
            ('CG', 'Congo'),
            ('CH', 'Switzerland'),
            ('CI', 'Ivory Coast (Cote d''Ivoire)'),
            ('CK', 'Cook Islands'),
            ('CL', 'Chile'),
            ('CM', 'Cameroon'),
            ('CN', 'China'),
            ('CO', 'Colombia'),
            ('CR', 'Costa Rica'),
            ('CU', 'Cuba'),
            ('CV', 'Cape Verde'),
            ('CX', 'Christmas Island'),
            ('CY', 'Cyprus'),
            ('CZ', 'Czech Republic'),
            ('DE', 'Germany'),
            ('DJ', 'Djibouti'),
            ('DK', 'Denmark'),
            ('DM', 'Dominica'),
            ('DO', 'Dominican Republic'),
            ('DZ', 'Algeria'),
            ('EC', 'Ecuador'),
            ('EE', 'Estonia'),
            ('EG', 'Egypt'),
            ('EH', 'Western Sahara'),
            ('ER', 'Eritrea'),
            ('ES', 'Spain'),
            ('ET', 'Ethiopia'),
            ('FI', 'Finland'),
            ('FJ', 'Fiji Islands'),
            ('FK', 'Falkland Islands (Malvinas)'),
            ('FM', 'Micronesia, Federated States of'),
            ('FO', 'Faroe Islands'),
            ('FR', 'France'),
            ('FX', 'France, Metropolitan'),
            ('GA', 'Gabon'),
            ('GB', 'United Kingdom'),
            ('GD', 'Grenada'),
            ('GE', 'Georgia'),
            ('GF', 'French Guiana'),
            ('GH', 'Ghana'),
            ('GI', 'Gibraltar'),
            ('GL', 'Greenland'),
            ('GM', 'Gambia'),
            ('GN', 'Guinea'),
            ('GP', 'Guadeloupe'),
            ('GQ', 'Equatorial Guinea'),
            ('GR', 'Greece'),
            ('GS', 'South Georgia and the South Sandwich Islands'),
            ('GT', 'Guatemala'),
            ('GU', 'Guam'),
            ('GW', 'Guinea-Bissau'),
            ('GY', 'Guyana'),
            ('HK', 'Hong Kong'),
            ('HM', 'Heard and McDonald Islands'),
            ('HN', 'Honduras'),
            ('HR', 'Croatia (local name: Hrvatska)'),
            ('HT', 'Haiti'),
            ('HU', 'Hungary'),
            ('ID', 'Indonesia'),
            ('IE', 'Ireland'),
            ('IL', 'Israel'),
            ('IN', 'India'),
            ('IO', 'British Indian Ocean Territory'),
            ('IQ', 'Iraq'),
            ('IR', 'Iran, Islamic Republic of'),
            ('IS', 'Iceland'),
            ('IT', 'Italy'),
            ('JM', 'Jamaica'),
            ('JO', 'Jordan'),
            ('JP', 'Japan'),
            ('KE', 'Kenya'),
            ('KG', 'Kyrgyzstan'),
            ('KH', 'Cambodia (formerly Kampuchea)'),
            ('KI', 'Kiribati'),
            ('KM', 'Comoros'),
            ('KN', 'Saint Kitts (Christopher) and Nevis'),
            ('KP', 'Korea, Democratic People''s Republic of (North Korea)'),
            ('KR', 'Korea, Republic of (South Korea)'),
            ('KW', 'Kuwait'),
            ('KY', 'Cayman Islands'),
            ('KZ', 'Kazakhstan'),
            ('LA', 'Lao People''s Democratic Republic (formerly Laos)'),
            ('LB', 'Lebanon'),
            ('LC', 'Saint Lucia'),
            ('LI', 'Liechtenstein'),
            ('LK', 'Sri Lanka'),
            ('LR', 'Liberia'),
            ('LS', 'Lesotho'),
            ('LT', 'Lithuania'),
            ('LU', 'Luxembourg'),
            ('LV', 'Latvia'),
            ('LY', 'Libyan Arab Jamahiriya'),
            ('MA', 'Morocco'),
            ('MC', 'Monaco'),
            ('MD', 'Moldova, Republic of'),
            ('MG', 'Madagascar'),
            ('MH', 'Marshall Islands'),
            ('MK', 'Macedonia, the Former Yugoslav Republic of'),
            ('ML', 'Mali'),
            ('MM', 'Myanmar (formerly Burma)'),
            ('MN', 'Mongolia'),
            ('MO', 'Macao (also spelled Macau)'),
            ('MP', 'Northern Mariana Islands'),
            ('MQ', 'Martinique'),
            ('MR', 'Mauritania'),
            ('MS', 'Montserrat'),
            ('MT', 'Malta'),
            ('MU', 'Mauritius'),
            ('MV', 'Maldives'),
            ('MW', 'Malawi'),
            ('MX', 'Mexico'),
            ('MY', 'Malaysia'),
            ('MZ', 'Mozambique'),
            ('NA', 'Namibia'),
            ('NC', 'New Caledonia'),
            ('NE', 'Niger'),
            ('NF', 'Norfolk Island'),
            ('NG', 'Nigeria'),
            ('NI', 'Nicaragua'),
            ('NL', 'Netherlands'),
            ('NO', 'Norway'),
            ('NP', 'Nepal'),
            ('NR', 'Nauru'),
            ('NU', 'Niue'),
            ('NZ', 'New Zealand'),
            ('OM', 'Oman'),
            ('PA', 'Panama'),
            ('PE', 'Peru'),
            ('PF', 'French Polynesia'),
            ('PG', 'Papua New Guinea'),
            ('PH', 'Philippines'),
            ('PK', 'Pakistan'),
            ('PL', 'Poland'),
            ('PM', 'St Pierre and Miquelon'),
            ('PN', 'Pitcairn Island'),
            ('PR', 'Puerto Rico'),
            ('PT', 'Portugal'),
            ('PW', 'Palau'),
            ('PY', 'Paraguay'),
            ('QA', 'Qatar'),
            ('RE', 'Reunion'),
            ('RO', 'Romania'),
            ('RU', 'Russian Federation'),
            ('RW', 'Rwanda'),
            ('SA', 'Saudi Arabia'),
            ('SB', 'Solomon Islands'),
            ('SC', 'Seychelles'),
            ('SD', 'Sudan'),
            ('SE', 'Sweden'),
            ('SG', 'Singapore'),
            ('SH', 'St Helena'),
            ('SI', 'Slovenia'),
            ('SJ', 'Svalbard and Jan Mayen Islands'),
            ('SK', 'Slovakia'),
            ('SL', 'Sierra Leone'),
            ('SM', 'San Marino'),
            ('SN', 'Senegal'),
            ('SO', 'Somalia'),
            ('SR', 'Suriname'),
            ('ST', 'Sco Tom'),
            ('SU', 'Union of Soviet Socialist Republics'),
            ('SV', 'El Salvador'),
            ('SY', 'Syrian Arab Republic'),
            ('SZ', 'Swaziland'),
            ('TC', 'Turks and Caicos Islands'),
            ('TD', 'Chad'),
            ('TF', 'French Southern and Antarctic Territories'),
            ('TG', 'Togo'),
            ('TH', 'Thailand'),
            ('TJ', 'Tajikistan'),
            ('TK', 'Tokelau'),
            ('TM', 'Turkmenistan'),
            ('TN', 'Tunisia'),
            ('TO', 'Tonga'),
            ('TP', 'East Timor'),
            ('TR', 'Turkey'),
            ('TT', 'Trinidad and Tobago'),
            ('TV', 'Tuvalu'),
            ('TW', 'Taiwan, Province of China'),
            ('TZ', 'Tanzania, United Republic of'),
            ('UA', 'Ukraine'),
            ('UG', 'Uganda'),
            ('UM', 'United States Minor Outlying Islands'),
            ('US', 'United States of America'),
            ('UY', 'Uruguay'),
            ('UZ', 'Uzbekistan'),
            ('VA', 'Holy See (Vatican City State)'),
            ('VC', 'Saint Vincent and the Grenadines'),
            ('VE', 'Venezuela'),
            ('VG', 'Virgin Islands (British)'),
            ('VI', 'Virgin Islands (US)'),
            ('VN', 'Viet Nam'),
            ('VU', 'Vanautu'),
            ('WF', 'Wallis and Futuna Islands'),
            ('WS', 'Samoa'),
            ('XO', 'West Africa'),
            ('YE', 'Yemen'),
            ('YT', 'Mayotte'),
            ('ZA', 'South Africa'),
            ('ZM', 'Zambia'),
            ('ZW', 'Zimbabwe'),
            ('PS', 'Palestinian Territory'),
            ('ME', 'Montenegro'),
            ('RS', 'Serbia');" ) )
        {
            $this->uninstallDatabase();
            return false;
        }


        Db::getInstance()->Execute( "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_country_method`" );
        if( !Db::getInstance()->Execute("
            CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_country_method` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `environment` varchar(50) default NULL,
                `country_id` int(11) DEFAULT '0',
                `method_id` int(11) DEFAULT '0',
                `priority` int(2) DEFAULT '99',
                `enabled` tinyint(2) DEFAULT '1' COMMENT 'Tells if country is active for method',
                PRIMARY KEY (`id`), KEY `environment` (`environment`), KEY `country_id` (`country_id`), KEY `method_id` (`method_id`), KEY `enabled` (`enabled`)
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8
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
        if( !($all_languages = Language::getLanguages( false ))
         or !is_array( $all_languages ) )
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
            and @is_dir( _PS_IMG_DIR_.'os' )
            and @is_writable( _PS_IMG_DIR_.'os' )
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
