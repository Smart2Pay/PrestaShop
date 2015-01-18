<?php

if (!defined('_PS_VERSION_'))
    exit;

/**
 * Class S2pideal
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
class S2pideal extends PaymentModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        /*
         * Method settings
         */
        $this->_methodName = 'ideal';
        $this->_methodID = 2;
        $this->_methodDisplayName = $this->l('iDEAL');
        $this->_methodDescription = $this->l('iDEAL description');
        $this->_moduleDescription = $this->l('Payment module');

        /*
         * Module settings
         */
        $this->name = 's2p' . $this->_methodName;
        $this->tab = 'payments_gateways';
        $this->version = '0.1';
        $this->author = 'Smart2Pay';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->controllers = array('payment');
        $this->displayName = $this->_methodDisplayName;
        $this->description = $this->_moduleDescription;

        /**
         * S2p base module instance
         */
        $this->s2p = $m = Module::getInstanceByName('s2p');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('s2p-enabled'))
            $this->warning = $this->s2p->l('In order for Smart2Pay methods to work, Smart2Pay Base Module has to be installed and enabled');

        parent::__construct();
    }

    /**
     * Get content
     *
     * @return string
     */
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name))
        {
            $formValues = array();

            foreach ($this->getConfigFormInputNames() as $name) {
                $formValues[$name] = strval(Tools::getValue($name));
            }

            foreach ($this->getConfigFormInputNames() as $name) {
                Configuration::updateValue($name, $formValues[$name]);
            }
        }

        return $output.$this->displayForm();
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

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => $this->getConfigFormInputs(),
            'submit' => array(
                'title' => $this->l('Save'),
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
        $helper->submit_action = 'submit'.$this->name;
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
        foreach ($this->getConfigFormInputNames() as $name) {
            $helper->fields_value[$name] = Configuration::get($name);
        }

        return $helper->generateForm($fields_form);
    }

    /**
     * Install
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install() || !$this->registerHook('payment'))
            return false;

        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

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

        foreach ($this->getConfigFormInputs() as $setting) {
            if (!Configuration::deleteByName($setting['name'])) {
                $settingsCleanedSuccessfully = false;
            }
        }

        if (!parent::uninstall() || !$settingsCleanedSuccessfully) {
            return false;
        }

        return true;
    }

    /**
     * Hook payment
     *
     * @param $params
     *
     * @return bool
     */
    public function hookPayment($params)
    {
        /*
         * Check for base module to be active
         */
        if (!Configuration::get('s2p-enabled')) {
            return false;
        }

        /*
         * Check for current method to be available and active
         */
        if (!$this->isMethodAvailable() || !$this->active) {
            return false;
        }

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_bw' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
            'method_name' => $this->_methodDisplayName,
            'module_name' => 's2p',
            'method_id' => $this->_methodID,
            'redirect_URL' => $this->context->link->getModuleLink('s2p', 'payment', array('methodID' => $this->_methodID))
        ));

        return $this->display(__FILE__, 'payment.tpl');
    }

    /**
     * Get module settings
     *
     * @return array
     */
    public function getSettings()
    {
        $settings = array();

        foreach ($this->getConfigFormInputNames() as $settingName) {
            $settings[$settingName] = Configuration::get($settingName);
        }

        return $settings;
    }

    /**
     * Get Config Form Input Names
     *
     * @return array
     */
    private function getConfigFormInputNames()
    {
        $names = array();

        foreach ($this->getConfigFormInputs() as $input) {
            $names[] = $input['name'];
        }

        return $names;
    }

    /**
     * Get Config Form Inputs
     *
     * @return array
     */
    private function getConfigFormInputs()
    {
        return $this->s2p->getMethodDefaultConfigFormInputs();
    }

    /**
     * Check if method is available
     *
     * @return bool
     */
    private function isMethodAvailable()
    {
        return $this->s2p->isMethodAvailable($this->_methodID, $this->context->country->iso_code);
    }
}