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
 * Smart2Pay Detection module
**/
if (!defined('_PS_VERSION_'))
    exit;

/**
 * Class S2p
 */
class Smart2paydetection extends Module
{
    const S2P_CONFIG_PREFIX = 'S2P_';
    const CONFIG_PREFIX = 'S2PD_';

    /**
     * Static cache
     *
     * @var array
     */
    static $cache = array(
        'ips_detected' => array(),
        'all_countries' => array(),
        'last_detection_error_msg' => '',
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'smart2paydetection';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Smart2Pay';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->controllers = array( 'payment' );

        parent::__construct();

        $this->displayName = $this->l( 'Smart2Pay Detection' );
        $this->description = $this->l( 'Smart2Pay Detection is a helper module for Smart2Pay Payment module.' );

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall Smart2Pay Detection plugin?');
    }

    public static function validate_value( $value, array $checks )
    {
        if( empty( $checks ) or !is_array( $checks ) )
            return array();

        $check_result = array();
        $check_result['<all_valid>'] = true;
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

    public static function get_db_file_location()
    {
        return _PS_MODULE_DIR_ . 'smart2paydetection/GeoLite2-Country.mmdb';
    }

    public function get_country_iso( $ip = false )
    {
        if( !Configuration::get( self::CONFIG_PREFIX.'ENABLED' ) )
            return false;

        if( !($detection_result = $this->detect_details_from_ip( $ip ))
         or empty( $detection_result['country'] ) or !is_array( $detection_result['country'] )
         or empty( $detection_result['country']['iso_code'] ) )
            return false;

        return Tools::strtoupper( $detection_result['country']['iso_code'] );
    }

    public function detect_details_from_ip( $ip = false )
    {
        // Reset last error...
        self::$cache['last_detection_error_msg'] = '';

        if( !@file_exists( _PS_MODULE_DIR_ . 'smart2paydetection/Db/Reader.php' ) )
        {
            self::$cache['last_detection_error_msg'] = $this->l( 'GeoLite2 DB Reader not found. Please reinstall the plugin.' );
            return false;
        }

        if( !($db_file = self::get_db_file_location())
         or !@file_exists( $db_file ) )
        {
            self::$cache['last_detection_error_msg'] = $this->l( 'GeoLite2-Country.mmdb database file not found. Please download it and copy it in plugin directory first.' );
            return false;
        }

        require_once _PS_MODULE_DIR_ . 'smart2paydetection/Db/Reader.php';
        require_once _PS_MODULE_DIR_ . 'smart2paydetection/Db/Reader/Decoder.php';
        require_once _PS_MODULE_DIR_ . 'smart2paydetection/Db/Reader/InvalidDatabaseException.php';
        require_once _PS_MODULE_DIR_ . 'smart2paydetection/Db/Reader/Metadata.php';
        require_once _PS_MODULE_DIR_ . 'smart2paydetection/Db/Reader/Util.php';

        if( empty( $ip ) )
            $ip = (!empty( $_SERVER['REMOTE_ADDR'] )?$_SERVER['REMOTE_ADDR']:false);

        if( empty( $ip ) )
        {
            self::$cache['last_detection_error_msg'] = $this->l( 'Couldn\'t obtain IP.' );
            return false;
        }

        try
        {
            $reader = new MaxMind\Db\Reader( $db_file );

            $result = $reader->get( $ip );
        } catch( Exception $e )
        {
            self::$cache['last_detection_error_msg'] = $this->l( 'Couldn\'t locate IP in database.' );
            return false;
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

        $db_file_exists = true;
        $db_file_size = 0;
        $db_file_records = 0;
        $db_file_time = 0;
        $db_file_version = 'N/A';
        $db_file_description = 'N/A';

        if( !($db_file = self::get_db_file_location())
         or !@file_exists( $db_file ) )
            $db_file_exists = false;

        if( !empty( $db_file_exists ) )
        {
            $db_file_size = @filesize( $db_file );
            $db_file_time = @filemtime( $db_file );

            try
            {
                $reader = new MaxMind\Db\Reader( $db_file );
                if( $reader->metadata() )
                {
                    $db_file_version = $reader->metadata()->binaryFormatMajorVersion . '.' . $reader->metadata()->binaryFormatMinorVersion .
                                       ' (' . date( $this->context->language->date_format_full, $reader->metadata()->buildEpoch ) . ')'.
                                       ' - IPv'.$reader->metadata()->ipVersion ;
                    $db_file_description = $reader->metadata()->description['en'];
                    $db_file_records = $reader->metadata()->nodeCount;
                }
            } catch( Exception $e )
            {
            }
        }

        $s2p_test_ip = Tools::getValue( 's2p_test_ip', '' );

        $this->context->smarty->assign( array(
            's2p_test_ip' => $s2p_test_ip,
            'db_file_location' => $db_file,
            'db_file_installed' => $db_file_exists,
            'db_file_size' => number_format( $db_file_size, 0 ),
            'db_file_size_human' => Tools::formatBytes( $db_file_size ),
            'db_file_records' => number_format( $db_file_records, 0 ),
            'db_file_version' => $db_file_version,
            'db_file_description' => $db_file_description,
            'db_file_time' => (!empty( $db_file_time )?date( $this->context->language->date_format_full, $db_file_time ):'N/A'),
            'detection_result' => (!empty( $post_result['<detection_result>'] )?$post_result['<detection_result>']:false),
        ) );

        return $output.
               $this->displayForm().
               $this->displayTestForm();
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
         * Check submit of main form
         */
        if( Tools::isSubmit( 'submit_main_data' ) )
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
                $field_error = '';

                // Make necessary transformations before validation
                if( !empty( $input['_transform'] ) and is_array( $input['_transform'] ) )
                    $formValues[$input['name']] = self::transform_value( $formValues[$input['name']], $input['_transform'] );

                if( !empty( $input['_validate'] ) and is_array( $input['_validate'] )
                and ($validation_result = self::validate_value( $formValues[$input['name']], $input['_validate'] ))
                and empty( $validation_result['<all_valid>'] ) )
                {
                    $isValid = false;
                    if( empty( $validation_result['url'] ) )
                        $field_error .= $this->displayError( $this->l( 'Invalid' ) . ' ' . Translate::getModuleTranslation( $this->name, $input['label'], 'smart2paydetection' ) . ' ' . $this->l('value') . '. ' . $this->l( 'Must be a valid URL' ) );
                    if( empty( $validation_result['notempty'] ) )
                        $field_error .= $this->displayError( $this->l( 'Invalid' ) . ' ' . Translate::getModuleTranslation( $this->name, $input['label'], 'smart2paydetection' ) . ' ' . $this->l('value') . '. ' . $this->l( 'Must NOT be empty' ) );

                    if( empty( $field_error ) )
                        $field_error .= $this->displayError( $this->l( 'Value provided for' ). Translate::getModuleTranslation( $this->name, $input['label'], 'smart2paydetection' ) .' ' . $this->l( 'is invalid' ) );
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

        /**
         * Check submit for payment method settings
         */
        elseif( Tools::isSubmit( 'submit_test_detection' ) )
        {
            $post_data['submit'] = 'submit_test_detection';
            $post_data['<detection_result>'] = false;

            $s2p_test_ip = Tools::getValue( 's2p_test_ip', '' );

            if( !($detection_result = $this->detect_details_from_ip( $s2p_test_ip )) )
            {
                if( !empty( self::$cache['last_detection_error_msg'] ) )
                    $post_data['errors_buffer'] .= $this->displayError( self::$cache['last_detection_error_msg'] );
                else
                    $post_data['errors_buffer'] .= $this->displayError( $this->l( 'Error detecting country for IP ['.$s2p_test_ip.']' ) );
            }

            if( empty( $post_data['errors_buffer'] ) )
            {
                $post_data['success_buffer'] .= $this->displayConfirmation( $this->l( 'IP detected with success.' ) );

                $detection_arr = array();
                $detection_arr['country'] = array( 'name' => 'N/A', 'code' => '' );
                $detection_arr['continent'] = array( 'name' => 'N/A', 'code' => '' );

                if( !empty( $detection_result['continent'] ) )
                {
                    $detection_arr['continent']['name'] = $detection_result['continent']['names']['en'];
                    $detection_arr['continent']['code'] = $detection_result['continent']['code'];
                }
                if( !empty( $detection_result['country'] ) )
                {
                    $detection_arr['country']['name'] = $detection_result['country']['names']['en'];
                    $detection_arr['country']['code'] = $detection_result['country']['iso_code'];
                }

                $post_data['<detection_result>'] = $detection_arr;
            }
        }

        return $post_data;
    }

    /**
     * Display Payment Methods Config Form
     *
     * @return mixed
     */
    public function displayTestForm()
    {
        $this->context->smarty->assign( array(
            'module_path' => $this->_path,
        ) );

        return $this->fetchTemplate( '/views/templates/admin/ip_detection.tpl' );
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

        return $helper->generateForm( $fields_form );
    }

    /**
     * Install
     *
     * @return bool
     */
    public function install()
    {
        if( !parent::install() )
            return false;

        if( Shop::isFeatureActive() )
            Shop::setContext( Shop::CONTEXT_ALL );

        foreach( $this->getConfigFormInputs() as $setting )
        {
            if( isset( $setting['_default'] ) )
                Configuration::updateValue( $setting['name'], $setting['_default'] );
        }

        return true;
    }

    /**
     * Uninstall
     *
     * @return bool
     */
    public function uninstall()
    {
        $settingsCleanedSuccessfully = true;

        foreach( $this->getConfigFormInputs() as $setting )
        {
            if( !Configuration::deleteByName( $setting['name'] ) )
                $settingsCleanedSuccessfully = false;
        }

        if( !parent::uninstall() || !$settingsCleanedSuccessfully )
            return false;

        return true;
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

        return $settings;
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

        elseif( version_compare( _PS_VERSION_, '1.5', '<' ) )
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
     * Get Smart2Pay countries list
     *
     * @return array
     */
    public function get_smart2pay_countries()
    {
        /*
         * Check for base module to be active
         * Check for current module to be available
         */
        if( !Configuration::get( self::S2P_CONFIG_PREFIX.'ENABLED' ) )
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

            case 'yesno':
                return array(
                    array(
                        'id' => 0,
                        'name' => $this->l( 'No' ),
                    ),
                    array(
                        'id' => 1,
                        'name' => $this->l( 'Yes' ),
                    )
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
        );
    }
}
