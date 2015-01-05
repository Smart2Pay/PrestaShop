<?php

if (!defined('_PS_VERSION_'))
    exit;


class S2pmybank extends PaymentModule
{
    protected $_methodName = 'mybank';
    protected $_methodID = 73;
    protected $_methodDisplayName = 'My Bank';
    protected $_moduleName;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 's2p' . $this->_methodName;
        $this->tab = 'payments_gateways';
        $this->version = '0.1';
        $this->author = 'Smart2Pay';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->controllers = array('payment');

        parent::__construct();

        $this->displayName = $this->l('Smart2Pay MyBank');
        $this->description = $this->l('Payment module.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('s2p-enabled'))
            $this->warning = $this->l('In order for Smart2Pay methods to work, Smart2Pay Base Module has to be installed and enabled');
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
        if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('paymentReturn'))
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
        if (!parent::uninstall() ||
            !Configuration::deleteByName('s2p' . $this->_methodName)
        )
            return false;

        return true;
    }

    public function hookPayment($params)
    {
        if (!$this->isMethodAvailable()
            || !$this->active
        ) {
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

    public function hookPaymentReturn($params)
    {
        die(__METHOD__);
        /*if (!$this->active)
            return;

        $state = $params['objOrder']->getCurrentState();
        if ($state == Configuration::get('PS_OS_BANKWIRE') || $state == Configuration::get('PS_OS_OUTOFSTOCK'))
        {
            $this->smarty->assign(array(
                'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
                'bankwireDetails' => Tools::nl2br($this->details),
                'bankwireAddress' => Tools::nl2br($this->address),
                'bankwireOwner' => $this->owner,
                'status' => 'ok',
                'id_order' => $params['objOrder']->id
            ));
            if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference))
                $this->smarty->assign('reference', $params['objOrder']->reference);
        }
        else
            $this->smarty->assign('status', 'failed');
        return $this->display(__FILE__, 'payment_return.tpl');*/
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
        return array(
            array(
                'type' => 'select',
                'label' => $this->l('Enabled'),
                'name' => 's2p-' . $this->_methodName . '-enabled',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name'
                )
            )
        );
    }

    private function getConfigFormSelectInputOptions($name = null)
    {
        $options = array(
            'envs' => array(
                array(
                    'id' => 'test',
                    'name' => 'Test'
                ),
                array(
                    'id' => 'live',
                    'name' => 'Live'
                )
            ),
            'yesno' => array(
                array(
                    'id' => 0,
                    'name' => 'No'
                ),
                array(
                    'id' => 1,
                    'name' => 'Yes'
                )
            )
        );

        return $name ? $options[$name] : $options;
    }

    private function isMethodAvailable()
    {
//        echo "<pre>";
//        print_r($this->context->country);
//        print_r($this->context);
//        echo "</pre>";

        $enabled = Configuration::get('s2p-' . $this->_methodName . '-enabled');

        if (!$enabled) {
            return false;
        }

        $countryMethod = Db::getInstance()->executeS(
            "
                SELECT CM.method_id
                FROM " . _DB_PREFIX_ . "smart2pay_country_method CM
                LEFT JOIN " . _DB_PREFIX_ . "smart2pay_country C ON C.country_id = CM.country_id
                WHERE C.code = '" . DB::getInstance()->_escape($this->context->country->iso_code) . "' AND CM.method_id = " . $this->_methodID . "
            "
        );

        if (!$countryMethod) {
            return false;
        }

        return true;
    }
}