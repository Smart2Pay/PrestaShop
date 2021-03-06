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
 * Smart2Pay helper class
 **/
if( !class_exists( 'Smart2Pay_SDK_Interface', false ) )
{
    class Smart2Pay_SDK_Interface extends PHS_Error
    {
        const ERR_GENERIC = 1;

        const OPTION_METHODS_LAST_CHECK = 'S2P_methods_last_check';

        // After how many hours from last sync action is merchant allowed to sync methods again?
        const RESYNC_AFTER_HOURS = 2;

        /** @var bool|Smart2pay $s2p_plugin */
        private static $s2p_plugin = false;

        private static $_sdk_inited = false;

        /**
         * Smart2Pay_SDK_Interface constructor.
         *
         * @param Smart2pay $s2p_plugin
         */
        public function __construct( $s2p_plugin )
        {
            parent::__construct();

            if( empty( self::$s2p_plugin ) )
                self::$s2p_plugin = $s2p_plugin;

            self::st_debugging_mode( false );
            self::st_throw_errors( false );

            self::init_sdk();
        }

        public static function init_sdk()
        {
            if( empty( self::$_sdk_inited )
            and @is_dir( _PS_MODULE_DIR_.'smart2pay/includes/sdk' )
            and @file_exists( _PS_MODULE_DIR_.'smart2pay/includes/sdk/bootstrap.php' ) )
            {
                include_once( _PS_MODULE_DIR_.'smart2pay/includes/sdk/bootstrap.php' );

                \S2P_SDK\S2P_SDK_Module::st_debugging_mode( false );
                \S2P_SDK\S2P_SDK_Module::st_detailed_errors( false );
                \S2P_SDK\S2P_SDK_Module::st_throw_errors( false );

                self::$_sdk_inited = true;
            }

            return self::$_sdk_inited;
        }

        public static function get_sdk_version()
        {
            if( empty( self::$_sdk_inited ) )
            {
                if( !self::init_sdk() )
                    return false;
            }

            if( !defined( 'S2P_SDK_VERSION' ) )
                return false;

            return S2P_SDK_VERSION;
        }

        public function last_methods_sync_option( $value = null, $plugin_settings_arr = false )
        {
            $s2p_plugin = self::$s2p_plugin;

            $this->reset_error();

            if( empty( $s2p_plugin ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, 'Smart2Pay SDK interface not initialized.' );
                return false;
            }

            if( empty( $plugin_settings_arr ) or !is_array( $plugin_settings_arr ) )
                $plugin_settings_arr = $s2p_plugin->getSettings();

            if( empty( $plugin_settings_arr['environment'] ) )
                $plugin_settings_arr['environment'] = 'demo';

            $option_name = self::OPTION_METHODS_LAST_CHECK.'_'.$plugin_settings_arr['environment'];

            if( $value === null )
                return Configuration::getGlobalValue( $option_name );

            if( empty( $value ) )
                $value = date( Smart2Pay_Helper::SQL_DATETIME );
            else
                $value = Smart2Pay_Helper::validate_db_datetime( $value );

            Configuration::updateGlobalValue( $option_name, $value );

            return $value;
        }

        public function get_api_credentials( $plugin_settings_arr = false )
        {
            $s2p_plugin = self::$s2p_plugin;

            $this->reset_error();

            if( empty( $s2p_plugin ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, 'Smart2Pay SDK interface not initialized.' );
                return false;
            }

            if( empty( $plugin_settings_arr ) or !is_array( $plugin_settings_arr ) )
                $plugin_settings_arr = $s2p_plugin->getSettings();

            if( empty( $plugin_settings_arr['environment'] ) )
                $plugin_settings_arr['environment'] = 'demo';
            else
                $plugin_settings_arr['environment'] = strtolower( $plugin_settings_arr['environment'] );

            $return_arr = array();
            $return_arr['api_key'] = '';
            $return_arr['site_id'] = 0;
            $return_arr['skin_id'] = 0;
            $return_arr['environment'] = 'test';

            switch( $plugin_settings_arr['environment'] )
            {
                default:
                    $this->set_error( self::ERR_GENERIC, $s2p_plugin->l( 'Unknown environment settings.' ) );
                    return false;

                case 'demo':
                    $return_arr['api_key'] = $s2p_plugin::DEMO_REST_APIKEY;
                    $return_arr['site_id'] = $s2p_plugin::DEMO_REST_SID;
                break;

                case 'test':
                    $return_arr['api_key'] = (!empty( $plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'APIKEY_TEST'] )?$plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'APIKEY_TEST']:'');
                    $return_arr['site_id'] = (!empty( $plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'SITE_ID_TEST'] )?$plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'SITE_ID_TEST']:0);
                break;

                case 'live':
                    $return_arr['api_key'] = (!empty( $plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'APIKEY_LIVE'] )?$plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'APIKEY_LIVE']:'');
                    $return_arr['site_id'] = (!empty( $plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'SITE_ID_LIVE'] )?$plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'SITE_ID_LIVE']:0);
                    $return_arr['environment'] = 'live';
                break;
            }

            $return_arr['skin_id'] = (!empty( $plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'SKIN_ID'] )?$plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'SKIN_ID']:0);

            return $return_arr;
        }

        public function get_available_methods( $plugin_settings_arr = false )
        {
            $s2p_plugin = self::$s2p_plugin;

            $this->reset_error();

            if( empty( $s2p_plugin ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, 'Smart2Pay SDK interface not initialized.' );
                return false;
            }

            if( empty( $plugin_settings_arr ) or !is_array( $plugin_settings_arr ) )
                $plugin_settings_arr = $s2p_plugin->getSettings();

            if( !($api_credentials = $this->get_api_credentials( $plugin_settings_arr )) )
                return false;

            $api_parameters['api_key'] = $api_credentials['api_key'];
            $api_parameters['site_id'] = $api_credentials['site_id'];
            $api_parameters['environment'] = $api_credentials['environment']; // test or live

            $api_parameters['method'] = 'methods';
            $api_parameters['func'] = 'assigned_methods';

            $api_parameters['get_variables'] = array(
                'additional_details' => true,
            );
            $api_parameters['method_params'] = array();

            $call_params = array();

            $finalize_params = array();
            $finalize_params['redirect_now'] = false;

            if( !($call_result = S2P_SDK\S2P_SDK_Module::quick_call( $api_parameters, $call_params, $finalize_params ))
                or empty( $call_result['call_result'] ) or !is_array( $call_result['call_result'] )
                or empty( $call_result['call_result']['methods'] ) or !is_array( $call_result['call_result']['methods'] ) )
            {
                if( ($error_arr = S2P_SDK\S2P_SDK_Module::st_get_error())
                    and !empty( $error_arr['display_error'] ) )
                    $this->set_error( self::ERR_GENERIC, $error_arr['display_error'] );
                else
                    $this->set_error( self::ERR_GENERIC, $s2p_plugin->l( 'API call failed while obtaining methods list.' ) );

                return false;
            }

            return $call_result['call_result']['methods'];
        }

        public function get_method_details( $method_id, $plugin_settings_arr = false )
        {
            $s2p_plugin = self::$s2p_plugin;

            $this->reset_error();

            if( empty( $s2p_plugin ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, 'Smart2Pay SDK interface not initialized.' );
                return false;
            }

            if( empty( $plugin_settings_arr ) or !is_array( $plugin_settings_arr ) )
                $plugin_settings_arr = $s2p_plugin->getSettings();

            $method_id = intval( $method_id );
            if( empty( $method_id )
                or !($api_credentials = $this->get_api_credentials( $plugin_settings_arr )) )
                return false;

            $api_parameters['api_key'] = $api_credentials['api_key'];
            $api_parameters['site_id'] = $api_credentials['site_id'];
            $api_parameters['environment'] = $api_credentials['environment'];

            $api_parameters['method'] = 'methods';
            $api_parameters['func'] = 'method_details';

            $api_parameters['get_variables'] = array(
                'id' => $method_id,
            );
            $api_parameters['method_params'] = array();

            $call_params = array();

            $finalize_params = array();
            $finalize_params['redirect_now'] = false;

            if( !($call_result = S2P_SDK\S2P_SDK_Module::quick_call( $api_parameters, $call_params, $finalize_params ))
                or empty( $call_result['call_result'] ) or !is_array( $call_result['call_result'] )
                or empty( $call_result['call_result']['method'] ) or !is_array( $call_result['call_result']['method'] ) )
            {
                if( ($error_arr = S2P_SDK\S2P_SDK_Module::st_get_error())
                    and !empty( $error_arr['display_error'] ) )
                    $this->set_error( self::ERR_GENERIC, $error_arr['display_error'] );
                else
                    $this->set_error( self::ERR_GENERIC, $s2p_plugin->l( 'API call failed while obtaining method details.' ) );

                return false;
            }

            return $call_result['call_result']['method'];
        }

        public function init_payment( $payment_details_arr, $plugin_settings_arr = false )
        {
            $s2p_plugin = self::$s2p_plugin;

            $this->reset_error();

            if( empty( $s2p_plugin ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, 'Smart2Pay SDK interface not initialized.' );
                return false;
            }

            if( empty( $plugin_settings_arr ) or !is_array( $plugin_settings_arr ) )
                $plugin_settings_arr = $s2p_plugin->getSettings();

            if( empty( $payment_details_arr ) or !is_array( $payment_details_arr )
                or !($api_credentials = $this->get_api_credentials( $plugin_settings_arr )) )
                return false;

            if( empty( $plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'RETURN_URL'] )
             or !PHS_params::check_type( $plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'RETURN_URL'], PHS_params::T_URL ) )
            {
                $this->set_error( self::ERR_GENERIC, $s2p_plugin->l( 'Return URL in plugin settings is invalid.' ) );
                return false;
            }

            $api_parameters['api_key'] = $api_credentials['api_key'];
            $api_parameters['site_id'] = $api_credentials['site_id'];
            $api_parameters['environment'] = $api_credentials['environment'];

            $api_parameters['method'] = 'payments';
            $api_parameters['func'] = 'payment_init';

            $api_parameters['get_variables'] = array();
            $api_parameters['method_params'] = array( 'payment' => $payment_details_arr );

            if( empty( $api_parameters['method_params']['payment']['tokenlifetime'] ) )
                $api_parameters['method_params']['payment']['tokenlifetime'] = 15;

            $api_parameters['method_params']['payment']['returnurl'] = $plugin_settings_arr[$s2p_plugin::CONFIG_PREFIX.'RETURN_URL'];

            $call_params = array();

            $finalize_params = array();
            $finalize_params['redirect_now'] = false;

            if( !($call_result = S2P_SDK\S2P_SDK_Module::quick_call( $api_parameters, $call_params, $finalize_params ))
                or empty( $call_result['call_result'] ) or !is_array( $call_result['call_result'] )
                or empty( $call_result['call_result']['payment'] ) or !is_array( $call_result['call_result']['payment'] ) )
            {
                if( ($error_arr = S2P_SDK\S2P_SDK_Module::st_get_error())
                    and !empty( $error_arr['display_error'] ) )
                    $this->set_error( self::ERR_GENERIC, $error_arr['display_error'] );
                else
                    $this->set_error( self::ERR_GENERIC, $s2p_plugin->l( 'API call to initialize payment failed. Please try again.' ) );

                return false;
            }

            return $call_result['call_result']['payment'];
        }

        public function card_init_payment( $payment_details_arr, $plugin_settings_arr = false )
        {
            $s2p_plugin = self::$s2p_plugin;

            $this->reset_error();

            if( empty( $s2p_plugin ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, 'Smart2Pay SDK interface not initialized.' );
                return false;
            }

            if( empty( $plugin_settings_arr ) or !is_array( $plugin_settings_arr ) )
                $plugin_settings_arr = $s2p_plugin->getSettings();

            if( empty( $payment_details_arr ) or !is_array( $payment_details_arr )
                or !($api_credentials = $this->get_api_credentials( $plugin_settings_arr )) )
                return false;

            if( empty( $plugin_settings_arr['return_url'] )
             or !PHS_params::check_type( $plugin_settings_arr['return_url'], PHS_params::T_URL ) )
            {
                $this->set_error( self::ERR_GENERIC, $s2p_plugin->l( 'Return URL in plugin settings is invalid.' ) );
                return false;
            }

            $api_parameters['api_key'] = $api_credentials['api_key'];
            $api_parameters['site_id'] = $api_credentials['site_id'];
            $api_parameters['environment'] = $api_credentials['environment'];

            $api_parameters['method'] = 'cards';
            $api_parameters['func'] = 'payment_init';

            $api_parameters['get_variables'] = array();
            $api_parameters['method_params'] = array( 'payment' => $payment_details_arr );

            if( empty( $api_parameters['method_params']['payment']['tokenlifetime'] ) )
                $api_parameters['method_params']['payment']['tokenlifetime'] = 15;

            if( !isset( $api_parameters['method_params']['payment']['capture'] ) )
                $api_parameters['method_params']['payment']['capture'] = true;
            if( !isset( $api_parameters['method_params']['payment']['retry'] ) )
                $api_parameters['method_params']['payment']['retry'] = false;
            if( !isset( $api_parameters['method_params']['payment']['3dsecure'] ) )
                $api_parameters['method_params']['payment']['3dsecure'] = true;
            if( !isset( $api_parameters['method_params']['payment']['generatecreditcardtoken'] ) )
                $api_parameters['method_params']['payment']['generatecreditcardtoken'] = false;

            $api_parameters['method_params']['payment']['returnurl'] = $plugin_settings_arr['return_url'];

            $call_params = array();

            $finalize_params = array();
            $finalize_params['redirect_now'] = false;

            if( !($call_result = S2P_SDK\S2P_SDK_Module::quick_call( $api_parameters, $call_params, $finalize_params ))
                or empty( $call_result['call_result'] ) or !is_array( $call_result['call_result'] )
                or empty( $call_result['call_result']['payment'] ) or !is_array( $call_result['call_result']['payment'] ) )
            {
                if( ($error_arr = S2P_SDK\S2P_SDK_Module::st_get_error())
                    and !empty( $error_arr['display_error'] ) )
                    $this->set_error( self::ERR_GENERIC, $error_arr['display_error'] );
                else
                    $this->set_error( self::ERR_GENERIC, $s2p_plugin->l( 'API call to initialize card payment failed. Please try again.' ) );

                return false;
            }

            return $call_result['call_result']['payment'];
        }

        public function seconds_to_launch_sync_str( $plugin_settings_arr = false )
        {
            $s2p_plugin = self::$s2p_plugin;

            $this->reset_error();

            if( empty( $s2p_plugin ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, 'Smart2Pay SDK interface not initialized.' );
                return false;
            }

            if( empty( $plugin_settings_arr ) or !is_array( $plugin_settings_arr ) )
                $plugin_settings_arr = $s2p_plugin->getSettings();

            if( !($seconds_to_sync = $this->seconds_to_launch_sync( $plugin_settings_arr )) )
                return false;

            $hours_to_sync = floor( $seconds_to_sync / 1200 );
            $minutes_to_sync = floor( ($seconds_to_sync - ($hours_to_sync * 1200)) / 60 );
            $seconds_to_sync -= ($hours_to_sync * 1200) + ($minutes_to_sync * 60);

            $sync_interval = '';
            if( $hours_to_sync )
                $sync_interval = $hours_to_sync.' hour(s)';

            if( $hours_to_sync or $minutes_to_sync )
                $sync_interval .= ($sync_interval!=''?', ':'').$minutes_to_sync.' minute(s)';

            $sync_interval .= ($sync_interval!=''?', ':'').$seconds_to_sync.' seconds.';

            return $sync_interval;
        }

        public function seconds_to_launch_sync( $plugin_settings_arr = false )
        {
            $s2p_plugin = self::$s2p_plugin;

            $this->reset_error();

            if( empty( $s2p_plugin ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, 'Smart2Pay SDK interface not initialized.' );
                return false;
            }

            if( empty( $plugin_settings_arr ) or !is_array( $plugin_settings_arr ) )
                $plugin_settings_arr = $s2p_plugin->getSettings();

            $resync_seconds = self::RESYNC_AFTER_HOURS * 1200;
            $time_diff = 0;
            if( !($last_sync_date = $this->last_methods_sync_option( null, $plugin_settings_arr ))
             or ($time_diff = abs( Smart2Pay_Helper::seconds_passed( $last_sync_date ) )) > $resync_seconds )
                return 0;

            return $resync_seconds - $time_diff;
        }

        public function refresh_available_methods( $plugin_settings_arr = false )
        {
            $s2p_plugin = self::$s2p_plugin;

            $this->reset_error();

            if( empty( $s2p_plugin ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, 'Smart2Pay SDK interface not initialized.' );
                return false;
            }

            if( empty( $plugin_settings_arr ) or !is_array( $plugin_settings_arr ) )
                $plugin_settings_arr = $s2p_plugin->getSettings();

            if( ($seconds_to_sync = $this->seconds_to_launch_sync( $plugin_settings_arr )) )
            {
                $hours_to_sync = floor( $seconds_to_sync / 1200 );
                $minutes_to_sync = floor( ($seconds_to_sync - ($hours_to_sync * 1200)) / 60 );
                $seconds_to_sync -= ($hours_to_sync * 1200) + ($minutes_to_sync * 60);

                $sync_interval = '';
                if( $hours_to_sync )
                    $sync_interval = $hours_to_sync.' hour(s)';

                if( $hours_to_sync or $minutes_to_sync )
                    $sync_interval .= ($sync_interval!=''?', ':'').$minutes_to_sync.' minute(s)';

                $sync_interval .= ($sync_interval!=''?', ':'').$seconds_to_sync.' seconds.';

                $this->set_error( self::ERR_GENERIC, 'You can syncronize methods once every '.self::RESYNC_AFTER_HOURS.' hours. Time left: '.$sync_interval );
                return false;
            }

            if( !($available_methods = $this->get_available_methods( $plugin_settings_arr ))
             or !is_array( $available_methods ) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_GENERIC, $s2p_plugin->l( 'Couldn\'t obtain a list of methods.' ) );
                return false;
            }

            $saved_methods = array();
            foreach( $available_methods as $method_arr )
            {
                if( empty( $method_arr ) or !is_array( $method_arr )
                 or empty( $method_arr['id'] ) )
                    continue;

                $row_method_arr = array();
                $row_method_arr['display_name'] = $method_arr['displayname'];
                $row_method_arr['description'] = $method_arr['description'];
                $row_method_arr['logo_url'] = $method_arr['logourl'];
                $row_method_arr['guaranteed'] = (!empty( $method_arr['guaranteed'] )?1:0);
                $row_method_arr['active'] = (!empty( $method_arr['active'] )?1:0);

                if( ($existing_method_arr = Db::getInstance()->executeS( 'SELECT * FROM `'._DB_PREFIX_.'smart2pay_method` '.
                                                                         ' WHERE method_id = \''.$method_arr['id'].'\' AND environment = \''.$plugin_settings_arr['environment'].'\' LIMIT 0, 1' )) )
                {
                    if( !($sql = Smart2Pay_Helper::quick_edit( _DB_PREFIX_.'smart2pay_method', $row_method_arr ))
                     or !Db::getInstance()->execute( $sql.' WHERE id = \''.$existing_method_arr['id'].'\'' ) )
                    {
                        $this->set_error( self::ERR_GENERIC, $s2p_plugin->l( 'Error saving method details in database.' ) );
                        return false;
                    }

                    foreach( $row_method_arr as $key => $val )
                        $existing_method_arr[$key] = $val;

                    $saved_method = $existing_method_arr;
                } else
                {
                    $row_method_arr['method_id'] = $method_arr['id'];
                    $row_method_arr['environment'] = $plugin_settings_arr['environment'];

                    if( !($sql = Smart2Pay_Helper::quick_insert( _DB_PREFIX_.'smart2pay_method', $row_method_arr ))
                     or !Db::getInstance()->execute( $sql ) )
                    {
                        $this->set_error( self::ERR_GENERIC, $s2p_plugin->l( 'Error adding method details in database.' ) );
                        return false;
                    }

                    $saved_method = $row_method_arr;
                    $saved_method['id'] = Db::getInstance()->Insert_ID();
                }

                $saved_methods[$saved_method['method_id']] = $saved_method;

                if( !empty( $method_arr['countries'] ) and is_array( $method_arr['countries'] ) )
                {
                    if( $this->update_method_countries( $saved_method, $method_arr['countries'] ) === false )
                    {
                        if( !$this->has_error() )
                            $this->set_error( self::ERR_GENERIC, $s2p_plugin->l( 'Error updating method countries.' ) );

                        return false;
                    }
                }
            }

            if( !($method_ids = array_keys( $saved_methods )) )
                Db::getInstance()->execute( 'UPDATE `'._DB_PREFIX_.'smart2pay_method` SET active = 0 WHERE environment = \''.$plugin_settings_arr['environment'].'\'' );
            else
                Db::getInstance()->execute( 'UPDATE `'._DB_PREFIX_.'smart2pay_method` SET active = 0 WHERE environment = \''.$plugin_settings_arr['environment'].'\' '.
                              ' AND method_id NOT IN ('.implode( ',', $method_ids ).')' );

            $this->last_methods_sync_option( false, $plugin_settings_arr );

            return $saved_methods;
        }

        /**
         * @param array $method_arr Method MUST be an array from smart2pay_methods table
         * @param array $countries_arr Array of countries to be set for provided method
         * @param array|bool $environment
         *
         * @return array|bool
         */
        public function update_method_countries( $method_arr, $countries_arr, $environment = false )
        {
            $s2p_plugin = self::$s2p_plugin;

            $this->reset_error();

            if( empty( $s2p_plugin ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, 'Smart2Pay SDK interface not initialized.' );
                return false;
            }

            if( empty( $method_arr ) or !is_array( $method_arr ) )
            {
                $this->set_error( self::ERR_PARAMETERS, $s2p_plugin->l( 'Please provide a method to update countries.' ) );
                return false;
            }

            if( !is_array( $countries_arr ) )
            {
                $this->set_error( self::ERR_PARAMETERS, $s2p_plugin->l( 'Countries codes required.' ) );
                return false;
            }

            if( !($codes_to_ids = $s2p_plugin->get_smart2pay_codes_countries()) )
            {
                $this->set_error( self::ERR_PARAMETERS, $s2p_plugin->l( 'Couldn\'t get country codes to ids conversion.' ) );
                return false;
            }

            if( empty( $environment ) )
            {
                if( !($plugin_settings_arr = $s2p_plugin->getSettings()) )
                    return null;

                $environment = $plugin_settings_arr['environment'];
            }

            $table_name = _DB_PREFIX_.'smart2pay_country_method';

            $return_arr = array();
            $country_ids = array();
            foreach( $countries_arr as $country_code )
            {
                $country_code = strtoupper( trim( $country_code ) );
                if( empty( $country_code ) or strlen( $country_code ) != 2
                 or empty( $codes_to_ids[$country_code] ) )
                    continue;

                $country_ids[] = $codes_to_ids[$country_code];

                if( !($db_method_country = Db::getInstance()->executeS(
                    'SELECT * FROM '.$table_name.' WHERE country_id = \''.$codes_to_ids[$country_code].'\' '.
                    ' AND method_id = \''.$method_arr['method_id'].'\' AND environment = \''.$environment.'\' LIMIT 0, 1' )) )
                {
                    $db_method_country = array();
                    $db_method_country['environment'] = $environment;
                    $db_method_country['country_id'] = $codes_to_ids[$country_code];
                    $db_method_country['method_id'] = $method_arr['method_id'];
                    $db_method_country['priority'] = 99;
                    $db_method_country['enabled'] = 1;

                    if( !($sql = Smart2Pay_Helper::quick_insert( $table_name, $db_method_country ))
                     or !Db::getInstance()->execute( $sql ) )
                    {
                        if( !$this->has_error() )
                            $this->set_error( self::ERR_GENERIC, $s2p_plugin->l( 'Error saving method countries ['.$sql.'].' ) );
                        return false;
                    }

                    $db_method_country['id'] = Db::getInstance()->Insert_ID();
                    $db_method_country['<new_in_db>'] = true;

                    $return_arr[$db_method_country['id']] = $db_method_country;
                }
            }

            if( empty( $country_ids ) )
                Db::getInstance()->execute( 'DELETE FROM '.$table_name.' WHERE method_id = \''.$method_arr['method_id'].'\' AND environment = \''.$environment.'\'' );
            else
                Db::getInstance()->execute( 'DELETE FROM '.$table_name.' WHERE method_id = \''. $method_arr['method_id'].'\'' .
                                        ' AND environment = \''.$environment.'\' AND country_id NOT IN ('.implode( ',', $country_ids ).')' );

            return $return_arr;
        }

    }
}
