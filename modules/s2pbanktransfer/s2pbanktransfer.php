<?php

if (!defined('_PS_VERSION_'))
    exit;

/**
 * Class S2pbanktransfer
 *
 *  // !! IMPORTANT:
 *  //
 *  // Resulting module name has to have the 's2pmethodname' format
 *  //
 *  //   Therefore, set:
 *  //     _methodName - @__construct
 *  //                   Lowercase version of method's 'display_name' from database table,
 *  //                   with no spaces, or special chars
 *  //                   (note that 's2p' is prepended bellow in constructor, so do not repeat it here)
 *  //                   ##REGEX## [0-9a-z]+
 *  //                   EX: display_name: 'INTER-AC® Online 9' => _methodName: 'interaconline9'
 *  //     class       - 'S2p' + _methodName
 *
 */
class S2pbanktransfer extends PaymentModule
{
    /** @var S2p $s2p */
    private $s2p;

    /**
     * Constructor
     */
    public function __construct()
    {
        /** @var S2p $m */
        $this->s2p = $m = Module::getInstanceByName( 's2p' );

        $this->_methodDisplayName = $this->l('Bank Transfer');
        $this->_methodDescription = $this->l('Bank Transfer description');
        $this->_moduleDescription = $this->l('Payment module');

        foreach( $this->s2p->get_default_module_vars() as $key => $val )
            $this->$key = $val;

        /**
         * This is the main thing in the module !!!
         */
        $this->_methodID = $m::MOD_BANKTRANSFER;

        $this->version = '0.1';
        $this->database_version = '0.1';
        $this->displayName = $this->_methodDisplayName;
        $this->description = $this->_moduleDescription;

        if( !($module_details = $m::valid_module( $this->_methodID )) )
        {
            $this->name = strtolower( get_class( $this ) );

            parent::__construct();
            $this->warning = $this->_methodDisplayName.' is not a valid method or it\'s ID is changed. Please contact Smart2Pay support.';
            return;
        }

        /*
         * Method settings
         */
        $this->_methodName = $module_details['module_name'];

        $this->name = 's2p' . $this->_methodName;

        $this->trusted = true;

        if( !Configuration::get( 's2p_enabled' ) )
            $this->warning = $this->s2p->l( 'In order for Smart2Pay methods to work, Smart2Pay Base Module has to be installed and enabled.' );

        parent::__construct();
    }

    /**
     * Get content
     *
     * @return string
     */
    public function getContent()
    {
        return $this->s2p->getPluginContent( $this );
    }

    /**
     * Display Config Form
     *
     * @return mixed
     */
    public function displayForm()
    {
        return $this->s2p->displayPluginForm( $this );
    }

    /**
     * Install
     *
     * @return bool
     */
    public function install()
    {
        if( !parent::install() or !$this->registerHook( 'payment' ) )
            return false;

        if( Shop::isFeatureActive() )
            Shop::setContext( Shop::CONTEXT_ALL );

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
        if( !Configuration::get( 's2p_enabled' )
         or !$this->isMethodAvailable() or !$this->active
         or !($methodDetails = $this->s2p->getMethodDetails( $this->_methodID )) )
            return false;

        $scope_arr = array();
        $scope_arr['method_id'] = $this->_methodID;
        $scope_arr['module_path'] = $this->_path;
        $scope_arr['module_name'] = $this->name;
        $scope_arr['method_display_name'] = $this->_methodDisplayName;


        $percent_key = 's2p_' . $this->s2p->resolveMethodModuleName( $methodDetails['display_name'] ) . '_surcharge_percent';
        $amount_key = 's2p_' . $this->s2p->resolveMethodModuleName( $methodDetails['display_name'] ) . '_surcharge_amount';
        if( empty( $settings_arr[$percent_key] ) )
            $settings_arr[$percent_key] = 0;
        if( empty( $settings_arr[$amount_key] ) )
            $settings_arr[$amount_key] = 0;

        $this->s2p->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_bw' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
            'method_name' => $this->_methodDisplayName,
            'module_name' => 's2p',
            'method_id' => $this->_methodID,
            'settings' => $settings_arr,
            'surcharge_amount' => Tools::displayPrice( $settings_arr[$amount_key] ),
            'surcharge_percent' => $settings_arr[$percent_key].'%',
            'redirect_URL' => $this->context->link->getModuleLink( 's2p', 'payment', array( 'methodID' => $this->_methodID ) )
        ));

        // return $this->display( __FILE__, 'payment.tpl' );
        return $this->s2p->fetchTemplate( 'payment.tpl' );
    }

    /**
     * Get module settings
     *
     * @return array
     */
    public function getSettings()
    {
        return $this->s2p->get_module_settings( $this->_methodID );
    }

    /**
     * Check if method is available
     *
     * @return bool
     */
    public function isMethodAvailable()
    {
        return $this->s2p->isMethodAvailable( $this->_methodID );
    }

    /**
     * Get Config Form Input Names
     *
     * @return array
     */
    public function getConfigFormInputNames()
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
    public function getConfigFormInputs()
    {
        return $this->s2p->getMethodDefaultConfigFormInputs( $this->_methodID );
    }
}