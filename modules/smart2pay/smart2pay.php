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
    const S2P_STATUS_OPEN = 1, S2P_STATUS_SUCCESS = 2, S2P_STATUS_CANCELLED = 3, S2P_STATUS_FAILED = 4, S2P_STATUS_EXPIRED = 5, S2P_STATUS_PROCESSING = 7;

    const CONFIG_PREFIX = 'S2P_';
    const S2PD_CONFIG_PREFIX = 'S2PD_';

    const S2P_DETECTOR_NAME = 'smart2paydetection';

    const COOKIE_NAME = 'S2P_COOKIE';

    const PAYM_BANK_TRANSFER = 1, PAYM_MULTIBANCO_SIBS = 20;

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
        $this->version = '1.0.4';
        $this->author = 'Smart2Pay';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array( 'min' => '1.5', 'max' => _PS_VERSION_ );
        $this->bootstrap = true;
        $this->controllers = array( 'payment' );

        parent::__construct();

        $this->displayName = $this->l( 'Smart2Pay' );
        $this->description = $this->l( 'Secure payments through 90 alternative payment methods.' );

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall Smart2Pay plugin?');
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
                $method_settings['priority'] = (!empty( $methods_priority_arr[$method_id] )?(int)trim( $methods_priority_arr[$method_id] ):0);

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
        elseif( Tools::isSubmit( 'submit_main_data' ) )
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
                        $field_error .= $this->displayError( $this->l( 'Invalid' ) . ' ' . Translate::getModuleTranslation( $this->name, $input['label'], 'smart2pay' ) . ' ' . $this->l('value') . '. ' . $this->l( 'Must be a valid URL' ) );
                    if( empty( $validation_result['notempty'] ) )
                        $field_error .= $this->displayError( $this->l( 'Invalid' ) . ' ' . Translate::getModuleTranslation( $this->name, $input['label'], 'smart2pay' ) . ' ' . $this->l('value') . '. ' . $this->l( 'Must NOT be empty' ) );
                    if( empty( $validation_result['country_iso'] ) )
                        $field_error .= $this->displayError( $this->l( 'Invalid' ) . ' ' . Translate::getModuleTranslation( $this->name, $input['label'], 'smart2pay' ) . ' ' . $this->l('value') . '. ' . $this->l( 'Should be a valid country.' ) );

                    if( empty( $field_error ) )
                        $field_error .= $this->displayError( $this->l( 'Value provided for' ). Translate::getModuleTranslation( $this->name, $input['label'], 'smart2pay' ) .' ' . $this->l( 'is invalid' ) );
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
        $this->context->controller->addCSS( _MODULE_DIR_ . $this->name . '/views/css/back-style.css' );

        $this->context->smarty->assign( array(
            'module_path' => $this->_path,
            'logos_path' => $this->_path.'views/img/logos/',
            'default_currency' => Currency::getDefaultCurrency()->iso_code,
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

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit_main_data';
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        foreach( $this->getConfigFormInputNames() as $name )
            $helper->fields_value[$name] = Configuration::get( $name );

        $this->context->controller->addCSS( _MODULE_DIR_ . $this->name . '/views/css/back-style.css' );

        return $helper->generateForm( $fields_form );
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

         // Displaying order details (public)
         or !$this->registerHook( 'displayOrderDetail' ) // box right above product listing

         // Displaying payment options (admin)
         or (
                version_compare( _PS_VERSION_, '1.6', '>=' )

                and

            ( !$this->registerHook( 'displayAdminOrderTabOrder' ) // Order tabs
                or !$this->registerHook( 'displayAdminOrderContentOrder' ) // Order tab content
            )
        )

         or (
                version_compare( _PS_VERSION_, '1.6', '<' )

                and

                !$this->registerHook( 'displayAdminOrder' ) // Order content for 1.5
        ) )
        {
            self::$maintenance_functionality = false;
            return false;
        }

        if( Shop::isFeatureActive() )
            Shop::setContext( Shop::CONTEXT_ALL );

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
            'transaction_arr' => $transaction_arr,
            'transaction_extra_titles' => self::transaction_logger_params_to_title(),
            'transaction_extra_data' => $transaction_extra_data,
        ) );

        return $this->fetchTemplate( '/views/templates/admin/order_payment_details.tpl' ).
               $this->fetchTemplate( '/views/templates/admin/order_payment_logs.tpl' );
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
     * Hook payment
     *
     * @param $params
     *
     * @return bool
     */
    public function hookPayment( $params )
    {
        /*
         * Check for base module to be active
         */
        if( !Configuration::get( self::CONFIG_PREFIX.'ENABLED' )
         or !($payment_methods_arr = $this->get_methods_for_country()) )
            return '';

        $this->context->controller->addCSS( $this->_path . '/views/css/style.css' );

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
            'default_currency' => Currency::getDefaultCurrency()->iso_code,
            'default_currency_id' => Currency::getDefaultCurrency()->id,
            'payment_methods' => $payment_methods_arr,
            'methods_country' => self::$cache['methods_country'],
            //'redirect_URL' => $this->context->link->getModuleLink('smart2pay', 'payment', array('methodID' => $this->_methodID)),
        ));

        return $this->fetchTemplate( 'payment.tpl' );
    }

    public function detection_module_available()
    {
        if( !Module::isInstalled( self::S2P_DETECTOR_NAME )
         or !Module::isEnabled( self::S2P_DETECTOR_NAME ) )
            return false;

        return true;
    }

    public function payment_module_available()
    {
        if( !Module::isInstalled( $this->name )
         or !Module::isEnabled( $this->name ) )
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
            elseif( !Module::isEnabled( self::S2P_DETECTOR_NAME )
                 or !Configuration::get( self::S2PD_CONFIG_PREFIX.'ENABLED' ) )
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
    public function getSettings()
    {
        static $settings = false;

        if( !empty( $settings ) )
            return $settings;

        $settings = array();

        foreach( $this->getConfigFormInputNames() as $settingName )
        {
            $settings[$settingName] = Configuration::get( $settingName );
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
        //if( function_exists( 'mb_strtolower' ) )
        //    return hash( 'sha256', mb_strtolower( $message ) );

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

        Db::getInstance()->Execute( $query );
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
    public function changeOrderStatus( $order, $statusId, $sendCustomerEmail = false, $mailTemplateVars = array() )
    {
        $orderState = new OrderState((int) $statusId);

        if( !Validate::isLoadedObject( $order ) )
        {
            $this->writeLog( 'Can not change apply order state #' . $statusId . ' to order - Order cannot be loaded', array( 'type' => 'error' ) );
            return false;
        }

        if( !Validate::isLoadedObject( $orderState ) )
        {
            $this->writeLog( 'Can not change apply order state #' . $statusId . ' to order #' . $order->id . ' - Order state cannot be loaded', array( 'type' => 'error', 'order_id' => $order->id ) );
            return false;
        }

        $history = new OrderHistory();
        $history->id_order = (int)$order->id;
        $history->changeIdOrderState( $statusId, (int)($order->id) );

        if( $sendCustomerEmail )
            $history->addWithemail( true, $mailTemplateVars );
        else
            $history->add();

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
        if( version_compare( _PS_VERSION_, '1.4', '<' ) )
            $this->context->smarty->currentTemplate = $name;

        elseif( version_compare( _PS_VERSION_, '1.6', '<' ) )
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
     * @param null|string $country_iso If parameter is null will return current value for force country (false by default)
     *
     * @return bool|string Returns current settings for force country as ISO 2 chars or false if error or nothing set yet
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

        if( empty( $country_rows ) )
            return array();

        foreach( $country_rows as $country_arr )
        {
            self::$cache['all_countries'][$country_arr['code']] = $country_arr['name'];
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
        if( !empty( self::$cache['all_method_settings_in_cache'] ) and !empty( self::$cache['method_settings'] ) )
            return self::$cache['method_settings'];

        self::$cache['method_settings'] = array();

        if( ($methods = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'smart2pay_method_settings` ORDER BY `priority` ASC' )) )
        {
            foreach( $methods as $method_arr )
            {
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
                $edit_arr['last_update'] = array( 'type' => 'sql', 'value' => 'NOW()' );

                if( Db::getInstance()->update( 'smart2pay_transactions', $edit_arr, 'id = \''.$transaction_arr['id'].'\'', 0, false, false ) )
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

            $insert_arr['last_update'] = array( 'type' => 'sql', 'value' => 'NOW()' );
            $insert_arr['created'] = array( 'type' => 'sql', 'value' => 'NOW()' );

            if( Db::getInstance()->insert( 'smart2pay_transactions', array( $insert_arr ), false, false ) )
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
     * Keys in returning array should be variable names sent back by Smart2Pay and values should be default values if variables are not found in request
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
            $insert_arr['priority'] = $params['priority'];
            $insert_arr['last_update'] = array( 'type' => 'sql', 'value' => 'NOW()' );
            $insert_arr['configured'] = array( 'type' => 'sql', 'value' => 'NOW()' );

            if( !Db::getInstance()->insert( 'smart2pay_method_settings', array( $insert_arr ), false, false ) )
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
            if( isset( $params['priority'] ) )
                $current_settings['priority'] = $edit_arr['priority'] = (int)$params['priority'];

            if( !empty( $edit_arr ) )
                $edit_arr['last_update'] = array( 'type' => 'sql', 'value' => 'NOW()' );


            if( !empty( $edit_arr ) )
            {
                if( !Db::getInstance()->update( 'smart2pay_method_settings', $edit_arr, 'method_id = \''.$method_id.'\'', 0, false, false ) )
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
     * @param null|string $countryISOCode   If no iso code is passed along, method checks if module can detect a country, else
     *                                      attempts to retrieve it from context->cart->id_address_invoice
     *
     * @return bool
     */
    public function get_methods_for_country( $country_iso = null )
    {
        /*
         * Check for base module to be active
         * Check for current module to be available
         */
        if( !Configuration::get( self::CONFIG_PREFIX.'ENABLED' ) )
            return false;

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

        $country_methods_arr = array();
        foreach( $priority_methods_arr as $priority_method_ids )
        {
            if( empty( $priority_method_ids ) or !is_array( $priority_method_ids ) )
                continue;

            foreach( $priority_method_ids as $method_id )
            {
                if( empty( $all_methods_settings_arr[ $method_id ]['surcharge_percent'] ) )
                    $all_methods_settings_arr[ $method_id ]['surcharge_percent'] = (float) 0;
                if( empty( $all_methods_settings_arr[ $method_id ]['surcharge_amount'] ) )
                    $all_methods_settings_arr[ $method_id ]['surcharge_amount'] = (float) 0;

                $country_methods_arr[ $method_id ]['method']   = $all_methods_arr[ $method_id ];
                $country_methods_arr[ $method_id ]['settings'] = $all_methods_settings_arr[ $method_id ];

                $country_methods_arr[ $method_id ]['settings']['surcharge_percent_format'] = number_format( $all_methods_settings_arr[ $method_id ]['surcharge_percent'], 2, '.', '' );
                $country_methods_arr[ $method_id ]['settings']['surcharge_amount_format']  = number_format( $all_methods_settings_arr[ $method_id ]['surcharge_amount'], 2, '.', '' );
            }
        }

        return $country_methods_arr;
    }

    /**
     * Check if s2p method is available in some particular country
     *
     * @param int $method_id                 Method ID
     * @param null|string $countryISOCode   If no iso code is passed along, method checks if module can detect a country, else
     *                                      attempts to retrieve it from context->cart->id_address_invoice
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
            if( $cookie->exists()
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
                'orderStatusName' => 'Smart2Pay - Awaiting payment'
            ),
            'success' => array(
                'configName' => self::CONFIG_PREFIX.'ORDER_STATUS_ON_SUCCESS',
                'orderStatusName' => 'Smart2Pay - Successfully paid'
            ),
            'canceled' => array(
                'configName' => self::CONFIG_PREFIX.'ORDER_STATUS_ON_CANCEL',
                'orderStatusName' => 'Smart2Pay - Canceled payment'
            ),
            'failed' => array(
                'configName' => self::CONFIG_PREFIX.'ORDER_STATUS_ON_FAIL',
                'orderStatusName' => 'Smart2Pay - Failed payment'
            ),
            'expired' => array(
                'configName' => self::CONFIG_PREFIX.'ORDER_STATUS_ON_EXPIRE',
                'orderStatusName' => 'Smart2Pay - Expired payment'
            )
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
                '_default' => 'https://api.smart2pay.com',
                '_validate' => array( 'url', 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Post URL Test'),
                'name' => self::CONFIG_PREFIX.'POST_URL_TEST',
                'required' => true,
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
                '_default' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module='.$this->name.'&controller=returnHandler&id_lang=1',
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
                'label' => $this->l('New Order Status'),
                'name' => self::CONFIG_PREFIX.'NEW_ORDER_STATUS',
                'required' => true,
                'options' => array(
                    'query' => OrderState::getOrderStates((int)$this->context->language->id),
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
                    'query' => OrderState::getOrderStates((int)$this->context->language->id),
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
                    'query' => OrderState::getOrderStates((int)$this->context->language->id),
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
                    'query' => OrderState::getOrderStates((int)$this->context->language->id),
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
                    'query' => OrderState::getOrderStates((int)$this->context->language->id),
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
                '_default' => 'The payment succeeded',
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message FAILED'),
                'name' => self::CONFIG_PREFIX.'MESSAGE_FAILED',
                'required' => true,
                '_default' => 'The payment process has failed',
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message CANCELED'),
                'name' => self::CONFIG_PREFIX.'MESSAGE_CANCELED',
                'required' => true,
                '_default' => 'The payment was canceled',
                '_validate' => array( 'notempty' ),
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message PENDING'),
                'name' => self::CONFIG_PREFIX.'MESSAGE_PENDING',
                'required' => true,
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
        if( !Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_transactions` (
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

        if( !Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_method_settings` (
                `id` int(11) NOT NULL,
                `method_id` int(11) NOT NULL DEFAULT '0',
                `enabled` tinyint(2) NOT NULL DEFAULT '0',
                `surcharge_percent` decimal(6,2) NOT NULL DEFAULT '0.00',
                `surcharge_amount` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'Amount is in shop currency',
                `priority` tinyint(4) NOT NULL DEFAULT '10' COMMENT '1 means first',
                `last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                `configured` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
                ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8 COMMENT='Smart2Pay method configurations';
        ") )
            return false;

        if( !Db::getInstance()->Execute( "ALTER TABLE `" . _DB_PREFIX_ . "smart2pay_method_settings`
              ADD PRIMARY KEY (`id`), ADD KEY `method_id` (`method_id`), ADD KEY `enabled` (`enabled`);
        ")

            or

        !Db::getInstance()->Execute( "ALTER TABLE `" . _DB_PREFIX_ . "smart2pay_method_settings`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
        ") )
        {
            $this->uninstallDatabase();
            return false;
        }

        Db::getInstance()->Execute( 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'smart2pay_logs`' );
        if( !Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_logs` (
                `log_id` int(11) NOT NULL,
                `order_id` int(11) NOT NULL default '0',
                `log_type` varchar(255) default NULL,
                `log_data` text default NULL,
                `log_source_file` varchar(255) default NULL,
                `log_source_file_line` varchar(255) default NULL,
                `log_created` timestamp NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8;
        ") )
        {
            $this->uninstallDatabase();
            return false;
        }

        if( !Db::getInstance()->Execute( "ALTER TABLE `" . _DB_PREFIX_ . "smart2pay_logs`
              ADD PRIMARY KEY (`log_id`), ADD KEY `order_id` (`order_id`), ADD KEY `log_type` (`log_type`);
        ")

            or

        !Db::getInstance()->Execute( "ALTER TABLE `" . _DB_PREFIX_ . "smart2pay_logs`
              MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;
        ") )
        {
            $this->uninstallDatabase();
            return false;
        }

        Db::getInstance()->Execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_method`");
        if( !Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_method` (
                `method_id` int(11) NOT NULL,
                `display_name` varchar(255) default NULL,
                `provider_value` varchar(255) default NULL,
                `description` text ,
                `logo_url` varchar(255) default NULL,
                `guaranteed` int(1) default NULL,
                `active` int(1) default NULL
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8
        ")

            or

        !Db::getInstance()->Execute( "ALTER TABLE `" . _DB_PREFIX_ . "smart2pay_method`
              ADD PRIMARY KEY (`method_id`), ADD KEY `active` (`active`);
        ")

            or

        !Db::getInstance()->Execute( "ALTER TABLE `" . _DB_PREFIX_ . "smart2pay_method`
              MODIFY `method_id` int(11) NOT NULL AUTO_INCREMENT;
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
            (33, 'CuentaDigital', 'cuentadigital', 'CuentaDigital description', 'cuentadigital.png', 1, 1),
            (34, 'CardsBrazil', 'cardsbrl', 'CardsBrazil description', 'cards_brl.gif', 0, 1),
            (35, 'PaysBuy', 'paysbuy', 'PaysBuy description', 'paysbuy.png', 0, 1),
            (36, 'Mazooma', 'mazooma', 'Mazooma description', 'mazooma.png', 0, 1),
            (37, 'eNETS Debit', 'enets', 'eNETS Debit description', 'enets.png', 1, 1),
            (40, 'Paysafecard', 'paysafecard', 'Paysafecard description', 'paysafecard.png', 1, 1),
            (42, 'PayPal', 'paypal', 'PayPal description', 'paypal.png', 1, 0),
            (43, 'PagTotal', 'pagtotal', 'PagTotal description', 'pagtotal.png', 0, 1),
            (44, 'Payeasy', 'payeasy', 'Payeasy description', 'payeasy.png', 1, 1),
            (46, 'MercadoPago', 'mercadopago', 'MercadoPago description', 'mercadopago.png', 0, 1),
            (47, 'Mozca', 'mozca', 'Mozca description', 'mozca.png', 0, 1),
            (49, 'ToditoCash', 'toditocash', 'ToditoCash description', 'todito_cash.png', 1, 1),
            (58, 'PayWithMyBank', 'pwmb', 'PayWithMyBank description', 'pwmb.png', 1, 1),
            (62, 'Tenpay', 'tenpay', 'Tenpay description', 'tenpay.png', 1, 1),
            (63, 'TrustPay', 'trustpay', 'TrustPay description', 'trustpay.png', 1, 1),
            (64, 'MangirKart', 'mangirkart', 'MangirKart description', 'mangir_cart.gif', 1, 1),
            (65, 'Paytrail', 'paytrail', 'Paytrail description', 'paytrail.gif', 1, 1),
            (66, 'MTCPay', 'mtcpay', 'MTCPay description', 'mtcpay.png', 1, 1),
            (67, 'DragonPay', 'dragonpay', 'DragonPay description', 'dragon_pay.png', 1, 1),
            (69, 'Credit Card', 's2pcards', 'S2PCards Description', 's2p_cards.gif', 0, 1),
            (72, 'PagoEfectivo', 'pagoefectivo', 'PagoEfectivo Description', 'pago_efectivo.gif', 1, 1),
            (73, 'MyBank', 'mybank', 'MyBank Description', 'mybank.png', 1, 1),
            (74, 'Yandex.Money', 'yandexmoney', 'YandexMoney description', 'yandex_money.png', 1, 1),
            (76, 'Bitcoin', 'bitcoin', 'Bitcoin description', 'bitcoin.png', 1, 1),
            (1000, 'Boleto', 'paganet', 'Boleto description', 'boleto_bancario.png', 1, 1),
            (1001, 'Debito', 'paganet', 'Debito description', 'debito_bradesco.png', 1, 1),
            (1002, 'Transferencia', 'paganet', 'Transferencia description', 'bradesco_transferencia.png', 1, 1),
            (1003, 'QIWI Wallet', 'qiwi', 'QIWI Wallet description', 'qiwi_wallet.png', 1, 1),
            (1004, 'Beeline', 'qiwi', 'Beeline description', 'beeline.png', 1, 1),
            (1005, 'Megafon', 'qiwi', 'Megafon description', 'megafon.png', 1, 1),
            (1006, 'MTS', 'qiwi', 'MTS description', 'mts.gif', 1, 1),
            (1007, 'WebMoney', 'moneta', 'WebMoney description', 'webmoney.png', 1, 1),
            (1008, 'Yandex', 'moneta', 'Yandex description', 'yandex.png', 1, 1),
            (1009, 'Alliance Online', 'asiapay', 'Alliance Online description', 'alliance_online.gif', 1, 1),
            (1010, 'AmBank', 'asiapay', 'AmBank description', 'ambank_group.png', 1, 1),
            (1011, 'CIMB Clicks', 'asiapay', 'CIMB Clicks description', 'cimb_clicks.png', 1, 1),
            (1012, 'FPX', 'asiapay', 'FPX description', 'fpx.png', 1, 1),
            (1013, 'Hong Leong Bank Transfer', 'asiapay', 'Hong Leong Bank Transfer description', 'hong_leong.png', 1, 1),
            (1014, 'Maybank2U', 'asiapay', 'Maybank2U description', 'maybank2u.png', 1, 1),
            (1015, 'Meps Cash', 'asiapay', 'Meps Cash description', 'meps_cash.png', 1, 1),
            (1016, 'Mobile Money', 'asiapay', 'Mobile Money description', 'mobile_money.png', 1, 1),
            (1017, 'RHB', 'asiapay', 'RHB description', 'rhb.png', 1, 1),
            (1018, 'Webcash', 'asiapay', 'Webcash description', 'web_cash.gif', 1, 1),
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
            (1033, 'Cards Thailand', 'paysbuy', 'Cards Thailand description', 'cards_brl.gif', 1, 1),
            (1034, 'PayPal Thailand', 'paysbuy', 'PayPalThailand description', 'paypal.png', 1, 1),
            (1035, 'AMEXThailand', 'paysbuy', 'AMEXThailand description', 'american_express.png', 1, 1),
            (1036, 'Cash Options Thailand', 'paysbuy', 'Cash Options Thailand description', 'counter-service-thailand_paysbuy-cash.png', 1, 1),
            (1037, 'Online Banking Thailand', 'paysbuy', 'OnlineBankingThailand description', 'online_banking_thailanda.png', 1, 1),
            (1038, 'PaysBuy Wallet', 'paysbuy', 'PaysBuy Wallet description', 'paysbuy.png', 1, 1),
            (1039, 'Pagos en efectivo', 'dineromaildirect', 'Pagos en efectivo Chile description', 'pagos_en_efectivo_servipag_bci_chile.png', 1, 1),
            (1040, 'Pagos en efectivo', 'dineromaildirect', 'Pagos en efectivo Argentina description', 'argentina_banks.png', 1, 1),
            (1041, 'OP-Pohjola', 'paytrail', 'OP-Pohjola description', 'op-pohjola.png', 1, 1),
            (1042, 'Nordea', 'paytrail', 'Nordea description', 'nordea.png', 1, 1),
            (1043, 'Danske bank', 'paytrail', 'Danske description', 'danske_bank.png', 1, 1),
            (1044, 'Cash-in', 'yandexmoney', 'Cash-in description', 'cashinyandex.gif', 1, 1),
            (1045, 'Cards Russia', 'yandexmoney', 'Cards Russia description', 's2p_cards.gif', 1, 1);
        ") )
        {
            $this->uninstallDatabase();
            return false;
        }

        Db::getInstance()->Execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_country`");
        if( !Db::getInstance()->Execute("
            CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_country` (
                `country_id` int(11) NOT NULL,
                `code` varchar(3) default NULL,
                `name` varchar(100) default NULL
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8
        ")

            or

        !Db::getInstance()->Execute( "ALTER TABLE `" . _DB_PREFIX_ . "smart2pay_country`
              ADD PRIMARY KEY (`country_id`);
        ")

            or

        !Db::getInstance()->Execute( "ALTER TABLE `" . _DB_PREFIX_ . "smart2pay_country`
              MODIFY `country_id` int(11) NOT NULL AUTO_INCREMENT;
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


        Db::getInstance()->Execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_country_method`");
        if( !Db::getInstance()->Execute("
            CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_country_method` (
                `id` int(11) NOT NULL,
                `country_id` int(11) default NULL,
                `method_id` int(11) default NULL,
                `priority` int(2) default NULL
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8
        ")

            or

        !Db::getInstance()->Execute( "ALTER TABLE `" . _DB_PREFIX_ . "smart2pay_country_method`
              ADD PRIMARY KEY (`id`), ADD KEY `country_id` (`country_id`), ADD KEY `method_id` (`method_id`);
        ")

            or

        !Db::getInstance()->Execute( "ALTER TABLE `" . _DB_PREFIX_ . "smart2pay_country_method`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
        ") )
        {
            $this->uninstallDatabase();
            return false;
        }


        if( !Db::getInstance()->Execute("
            INSERT INTO `" . _DB_PREFIX_ . "smart2pay_country_method` (`id`, `country_id`, `method_id`, `priority`) VALUES
                (1,1,76,99),
                (2,2,13,1),
                (3,2,14,2),
                (4,2,76,99),
                (5,3,76,99),
                (6,4,76,99),
                (7,5,76,99),
                (8,6,76,99),
                (9,7,76,99),
                (10,8,76,99),
                (11,9,76,99),
                (12,10,76,99),
                (13,11,76,99),
                (14,12,76,99),
                (15,13,5,1),
                (16,13,40,2),
                (17,13,9,3),
                (18,13,28,4),
                (19,13,1,5),
                (20,13,23,6),
                (21,13,69,7),
                (22,13,76,99),
                (23,14,18,1),
                (24,14,28,2),
                (25,14,69,3),
                (26,14,76,99),
                (27,15,76,99),
                (28,16,76,99),
                (29,17,63,1),
                (30,17,69,2),
                (31,17,76,99),
                (32,18,76,99),
                (33,19,76,99),
                (34,20,3,1),
                (35,20,40,2),
                (36,20,1,3),
                (37,20,9,4),
                (38,20,28,5),
                (39,20,23,5),
                (40,20,69,6),
                (41,20,76,99),
                (42,21,76,99),
                (43,22,1,1),
                (44,22,63,2),
                (45,22,69,3),
                (46,22,76,99),
                (47,23,13,1),
                (48,23,14,2),
                (49,23,76,99),
                (50,24,76,99),
                (51,25,76,99),
                (52,26,76,99),
                (53,27,76,99),
                (54,28,76,99),
                (55,29,46,1),
                (56,29,32,2),
                (57,29,1000,3),
                (58,29,1002,4),
                (59,29,43,5),
                (60,29,28,6),
                (61,29,19,8),
                (62,29,76,99),
                (63,30,76,99),
                (64,31,76,99),
                (65,32,76,99),
                (66,33,76,99),
                (67,34,76,99),
                (68,35,76,99),
                (69,36,8,1),
                (70,36,71,2),
                (71,36,28,3),
                (72,36,69,4),
                (73,36,76,99),
                (74,37,76,99),
                (75,38,76,99),
                (76,39,76,99),
                (77,40,76,99),
                (78,41,1,1),
                (79,41,40,2),
                (80,41,9,3),
                (81,41,69,4),
                (82,41,76,99),
                (83,42,76,99),
                (84,43,76,99),
                (85,44,19,1),
                (86,44,76,99),
                (87,45,76,99),
                (88,46,24,1),
                (89,46,62,2),
                (90,46,28,3),
                (91,46,76,99),
                (92,47,19,1),
                (93,47,1019,2),
                (94,47,1020,3),
                (95,47,1021,4),
                (96,47,1022,5),
                (97,47,1023,6),
                (98,47,76,99),
                (99,48,76,99),
                (100,50,76,99),
                (101,51,76,99),
                (102,52,76,99),
                (103,53,40,1),
                (104,53,28,2),
                (105,53,69,3),
                (106,53,13,4),
                (107,53,14,5),
                (108,53,76,99),
                (109,54,27,1),
                (110,54,63,2),
                (111,54,1,3),
                (112,54,40,4),
                (113,54,28,5),
                (114,54,23,6),
                (115,54,69,7),
                (116,54,76,99),
                (117,55,4,1),
                (118,55,9,2),
                (119,55,40,3),
                (120,55,28,4),
                (121,55,23,5),
                (122,55,1,6),
                (123,55,14,6),
                (124,55,76,99),
                (125,55,69,7),
                (126,56,76,99),
                (127,57,29,1),
                (128,57,1,2),
                (129,57,40,3),
                (130,57,28,4),
                (131,57,69,5),
                (132,57,76,99),
                (133,58,76,99),
                (134,59,76,99),
                (135,60,14,1),
                (136,60,76,99),
                (137,61,76,99),
                (138,62,23,1),
                (139,62,29,2),
                (140,62,63,3),
                (141,62,28,4),
                (142,62,1,5),
                (143,62,69,6),
                (144,62,76,99),
                (145,63,13,1),
                (146,63,14,2),
                (147,63,76,99),
                (148,64,76,99),
                (149,65,76,99),
                (150,66,29,1),
                (151,66,1,2),
                (152,66,28,3),
                (153,66,40,3),
                (154,66,14,4),
                (155,66,9,5),
                (156,66,13,6),
                (157,66,69,7),
                (158,66,76,99),
                (159,67,76,99),
                (160,68,65,1),
                (161,68,40,2),
                (162,68,69,3),
                (163,68,29,4),
                (164,68,28,5),
                (165,68,1,6),
                (166,68,1041,7),
                (167,68,1042,8),
                (168,68,1043,9),
                (169,68,76,99),
                (170,69,76,99),
                (171,70,76,99),
                (172,71,76,99),
                (173,72,76,99),
                (174,73,1,1),
                (175,73,40,2),
                (176,73,14,3),
                (177,73,28,4),
                (178,73,73,5),
                (179,73,9,6),
                (180,73,69,7),
                (181,73,69,8),
                (182,73,76,99),
                (183,74,76,99),
                (184,75,76,99),
                (185,76,9,1),
                (186,76,1,2),
                (187,76,40,3),
                (188,76,28,4),
                (189,76,23,5),
                (190,76,14,6),
                (191,76,13,7),
                (192,76,69,8),
                (193,76,76,99),
                (194,77,76,99),
                (195,78,76,99),
                (196,79,76,99),
                (197,80,14,1),
                (198,80,76,99),
                (199,81,76,99),
                (200,82,76,99),
                (201,83,76,99),
                (202,84,76,99),
                (203,85,76,99),
                (204,86,76,99),
                (205,87,40,1),
                (206,87,28,2),
                (207,87,69,3),
                (208,87,76,99),
                (209,88,76,99),
                (210,89,76,99),
                (211,90,76,99),
                (212,91,76,99),
                (213,92,76,99),
                (214,93,76,99),
                (215,94,76,99),
                (216,95,76,99),
                (217,96,63,1),
                (218,96,69,2),
                (219,96,76,99),
                (220,97,76,99),
                (221,98,25,1),
                (222,98,28,2),
                (223,98,63,3),
                (224,98,1,4),
                (225,98,9,4),
                (226,98,40,5),
                (227,98,69,6),
                (228,98,76,99),
                (229,99,1024,1),
                (230,99,1025,2),
                (231,99,76,99),
                (232,100,40,1),
                (233,100,1,2),
                (234,100,28,2),
                (235,100,14,3),
                (236,100,14,3),
                (237,100,69,4),
                (238,100,76,99),
                (239,101,13,1),
                (240,101,14,2),
                (241,101,69,3),
                (242,101,76,99),
                (243,102,76,99),
                (244,103,76,99),
                (245,104,13,1),
                (246,104,14,2),
                (247,104,76,99),
                (248,105,76,99),
                (249,106,76,99),
                (250,107,40,1),
                (251,107,73,2),
                (252,107,1,3),
                (253,107,28,4),
                (254,107,9,5),
                (255,107,14,6),
                (256,107,13,7),
                (257,107,69,8),
                (258,107,76,99),
                (259,108,76,99),
                (260,109,13,1),
                (261,109,14,2),
                (262,109,76,99),
                (263,110,76,99),
                (264,111,76,99),
                (265,112,76,99),
                (266,113,76,99),
                (267,114,76,99),
                (268,115,76,99),
                (269,116,76,99),
                (270,117,76,99),
                (271,118,76,99),
                (272,119,13,1),
                (273,119,14,2),
                (274,119,76,99),
                (275,120,76,99),
                (276,121,1003,1),
                (277,121,76,99),
                (278,122,76,99),
                (279,123,13,1),
                (280,123,14,2),
                (281,123,76,99),
                (282,124,76,99),
                (283,125,76,99),
                (284,126,76,99),
                (285,127,76,99),
                (286,128,76,99),
                (287,129,23,1),
                (288,129,29,2),
                (289,129,1,3),
                (290,129,69,4),
                (291,129,76,99),
                (292,130,1,1),
                (293,130,40,2),
                (294,130,73,3),
                (295,130,69,4),
                (296,130,76,99),
                (297,131,23,1),
                (298,131,63,2),
                (299,131,28,3),
                (300,131,14,4),
                (301,131,40,5),
                (302,131,69,6),
                (303,131,76,99),
                (304,132,76,99),
                (305,133,76,99),
                (306,134,76,99),
                (307,135,76,99),
                (308,136,76,99),
                (309,137,76,99),
                (310,138,76,99),
                (311,139,76,99),
                (312,140,76,99),
                (313,141,76,99),
                (314,142,76,99),
                (315,143,76,99),
                (316,144,76,99),
                (317,145,76,99),
                (318,146,76,99),
                (319,147,76,99),
                (320,148,76,99),
                (321,149,76,99),
                (322,150,76,99),
                (323,151,49,1),
                (324,151,46,2),
                (325,151,19,3),
                (326,151,40,4),
                (327,151,1026,5),
                (328,151,1027,6),
                (329,151,1028,7),
                (330,151,1029,8),
                (331,151,1030,9),
                (332,151,28,10),
                (333,151,1031,10),
                (334,151,76,99),
                (335,152,1009,1),
                (336,152,1010,2),
                (337,152,1011,3),
                (338,152,1012,4),
                (339,152,1013,5),
                (340,152,1014,6),
                (341,152,1015,7),
                (342,152,1016,8),
                (343,152,1017,9),
                (344,152,1018,10),
                (345,152,76,99),
                (346,153,76,99),
                (347,154,76,99),
                (348,155,76,99),
                (349,156,76,99),
                (350,157,76,99),
                (351,158,14,1),
                (352,158,76,99),
                (353,159,76,99),
                (354,160,2,1),
                (355,160,9,2),
                (356,160,40,3),
                (357,160,28,4),
                (358,160,1,5),
                (359,160,23,6),
                (360,160,69,7),
                (361,160,76,99),
                (362,161,1,1),
                (363,161,29,2),
                (364,161,40,3),
                (365,161,28,4),
                (366,161,69,5),
                (367,161,76,99),
                (368,162,76,99),
                (369,163,76,99),
                (370,164,76,99),
                (371,165,18,2),
                (372,165,28,2),
                (373,165,76,99),
                (374,166,13,1),
                (375,166,14,2),
                (376,166,76,99),
                (377,167,76,99),
                (378,168,40,1),
                (379,168,76,99),
                (380,169,76,99),
                (381,170,76,99),
                (382,171,44,1),
                (383,171,67,2),
                (384,171,76,99),
                (385,172,76,99),
                (386,173,12,1),
                (387,173,1,2),
                (388,173,40,3),
                (389,173,28,4),
                (390,173,14,8),
                (391,173,76,99),
                (392,174,76,99),
                (393,175,76,99),
                (394,176,76,99),
                (395,177,20,1),
                (396,177,40,2),
                (397,177,28,3),
                (398,177,14,4),
                (399,177,1,5),
                (400,177,69,6),
                (401,177,76,99),
                (402,178,76,99),
                (403,179,76,99),
                (404,180,13,1),
                (405,180,14,2),
                (406,180,76,99),
                (407,181,76,99),
                (408,182,40,1),
                (409,182,1,2),
                (410,182,63,3),
                (411,182,28,4),
                (412,182,69,5),
                (413,182,76,99),
                (414,183,74,1),
                (415,183,1003,2),
                (416,183,22,3),
                (417,183,1007,4),
                (418,183,1044,5),
                (419,183,1045,6),
                (420,183,1004,7),
                (421,183,1005,8),
                (422,183,1006,9),
                (423,183,1008,10),
                (424,183,28,11),
                (425,183,76,99),
                (426,184,76,99),
                (427,185,13,1),
                (428,185,14,2),
                (429,185,76,99),
                (430,186,76,99),
                (431,187,76,99),
                (432,188,14,1),
                (433,188,76,99),
                (434,189,29,1),
                (435,189,1,2),
                (436,189,40,3),
                (437,189,28,4),
                (438,189,69,5),
                (439,189,76,99),
                (440,190,37,1),
                (441,190,76,99),
                (442,191,76,99),
                (443,192,63,1),
                (444,192,40,2),
                (445,192,28,3),
                (446,192,14,4),
                (447,192,69,5),
                (448,192,76,99),
                (449,193,76,99),
                (450,194,1,1),
                (451,194,63,2),
                (452,194,40,3),
                (453,194,14,4),
                (454,194,23,5),
                (455,194,69,6),
                (456,194,76,99),
                (457,195,76,99),
                (458,196,76,99),
                (459,197,76,99),
                (460,198,76,99),
                (461,199,76,99),
                (462,200,76,99),
                (463,201,76,99),
                (464,202,76,99),
                (465,203,76,99),
                (466,204,76,99),
                (467,205,76,99),
                (468,206,76,99),
                (469,207,76,99),
                (470,208,76,99),
                (471,209,35,1),
                (472,209,1038,2),
                (473,209,1036,3),
                (474,209,1037,4),
                (475,209,1035,5),
                (476,209,1034,6),
                (477,209,1033,7),
                (478,209,76,99),
                (479,210,76,99),
                (480,211,76,99),
                (481,212,76,99),
                (482,213,13,1),
                (483,213,14,2),
                (484,213,76,99),
                (485,214,76,99),
                (486,215,76,99),
                (487,216,1,1),
                (488,216,66,2),
                (489,216,64,3),
                (490,216,40,4),
                (491,216,63,5),
                (492,216,13,6),
                (493,216,14,7),
                (494,216,28,8),
                (495,216,69,9),
                (496,216,76,99),
                (497,217,76,99),
                (498,218,76,99),
                (499,219,76,99),
                (500,220,76,99),
                (501,221,74,1),
                (502,221,22,2),
                (503,221,1003,3),
                (504,221,1007,4),
                (505,221,1008,5),
                (506,221,28,6),
                (507,221,76,99),
                (508,222,76,99),
                (509,223,76,99),
                (510,224,69,1),
                (511,224,58,2),
                (512,224,40,3),
                (513,224,36,4),
                (514,224,76,99),
                (515,225,76,99),
                (516,226,76,99),
                (517,227,76,99),
                (518,228,76,99),
                (519,229,76,99),
                (520,230,76,99),
                (521,231,76,99),
                (522,232,76,99),
                (523,233,76,99),
                (524,234,76,99),
                (525,235,76,99),
                (526,236,76,99),
                (527,237,76,99),
                (528,238,76,99),
                (529,239,28,1),
                (530,239,76,99),
                (531,240,76,99),
                (532,241,76,99),
                (533,242,13,1),
                (534,242,14,1),
                (535,242,76,99),
                (536,243,63,1),
                (537,243,69,2),
                (538,243,76,99),
                (539,244,63,1),
                (540,244,69,2),
                (541,244,76,99)
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
        foreach( $this->getPaymentStatesOrderStatuses() as $status )
        {
            if( ($existingStatus = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'order_state_lang` WHERE `name` = \'' . pSQL( $status['orderStatusName'] ) . '\'' )) )
                $statusID = $existingStatus[0]['id_order_state'];

            else
            {
                Db::getInstance()->Execute(
                    'INSERT INTO `'._DB_PREFIX_.'order_state` (`unremovable`, `color`, `module_name`) '.
                    'VALUES(1, \'#660099\', \''.pSQL( $this->name ).'\')'
                );

                $statusID = Db::getInstance()->Insert_ID();

                Db::getInstance()->Execute(
                    'INSERT INTO `'._DB_PREFIX_.'order_state_lang` (`id_order_state`, `id_lang`, `name`) '.
                    'VALUES(' . (int)$statusID . ', 1, \'' . pSQL( $status['orderStatusName'] ). '\')'
                );
            }

            Configuration::updateValue( $status['configName'], $statusID );
        }
    }

    /**
     * Delete custom s2p order statuses
     */
    private function deleteCustomOrderStatuses()
    {
        $ids = Db::getInstance()->executeS(
            'SELECT GROUP_CONCAT(`id_order_state`) as `id_order_state` FROM `'._DB_PREFIX_.'order_state` '.
            ' WHERE `module_name` = \''.pSQL($this->name).'\''
        );

        $ids = explode( ',', $ids[0]['id_order_state'] );

        Db::getInstance()->execute(
            'DELETE FROM `'._DB_PREFIX_.'order_state` '.
            ' WHERE `id_order_state` IN (\'' . join('\',\'', (array)$ids) . '\')'
        );

        Db::getInstance()->execute(
            'DELETE FROM `'._DB_PREFIX_.'order_state_lang` '.
            ' WHERE `id_order_state` IN (\'' . join('\',\'', (array)$ids) . '\')'
        );
    }
}
