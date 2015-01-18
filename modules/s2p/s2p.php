<?php

if (!defined('_PS_VERSION_'))
    exit;

/**
 * Class S2p
 */
class S2p extends PaymentModule
{
    /**
     * Static cache
     *
     * @var array
     */
    static $cache = array(
        'methodDetails' => array()
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 's2p';
        $this->tab = 'payments_gateways';
        $this->version = '0.1';
        $this->author = 'Smart2Pay';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->controllers = array('payment');

        parent::__construct();

        $this->displayName = $this->l('Smart2Pay');
        $this->description = $this->l('Payment module.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        // !! Trick for sharing s2p base module translation to methods modules
        //
        $this->l('In order for Smart2Pay methods to work, Smart2Pay Base Module has to be installed and enabled');
    }

    /**
     * Process Module Config submitted data
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
                $formValues[$name] = (string) Tools::getValue($name);
            }

            /*
             *
             * Validate and update config values
             *
             */
            foreach ($this->getConfigFormInputs() as $input) {
                $isValid = true;
                $skipValidation = false;

                if ($formValues['s2p-env'] == 'test'
                    && in_array($input['name'], array('s2p-signature-live', 's2p-post-url-live', 's2p-mid-live'))
                ) {
                    $skipValidation = true;
                }

                if ($formValues['s2p-env'] == 'live'
                    && in_array($input['name'], array('s2p-signature-test', 's2p-post-url-test', 's2p-mid-test'))
                ) {
                    $skipValidation = true;
                }

                if (!$skipValidation
                    && isset($input['_validate'])
                    && $input['_validate']
                ) {
                    foreach ((array) $input['_validate'] as $validate) {
                        switch ($validate) {
                            case 'url':
                                if (!Validate::isUrl($formValues[$input['name']])) {
                                    $output .= $this->displayError($this->l('Invalid') . ' ' . Translate::getModuleTranslation($this->name, $input['label']) . ' ' . $this->l('value') . '. ' . $this->l('Must be a valid URL'));
                                    $isValid = false;
                                }
                                break;
                            case 'notEmpty':
                                if (empty($formValues[$input['name']])) {
                                    $output .= $this->displayError($this->l('Invalid') . ' ' . Translate::getModuleTranslation($this->name, $input['label']) . ' ' . $this->l('value') . '. ' . $this->l('Must NOT be empty'));
                                    $isValid = false;
                                }
                        }
                    }
                }

                if ($isValid) {
                    Configuration::updateValue($input['name'], $formValues[$input['name']]);
                }
            }

            if (!$output) {
                $output .= $this->displayConfirmation($this->l('Settings updated successfully'));
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

        $this->context->controller->addCSS(_MODULE_DIR_ . $this->name . '/css/style.css');

        return $helper->generateForm($fields_form)
            . $this->getLogsHTML();
    }

    /**
     * Install
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install())
            return false;

        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        /*
         * Set default module config
         *
         */

        $this->createCustomOrderStatuses();

        foreach ($this->getConfigFormInputs() as $setting) {
            switch ($setting['name']) {
                case 's2p-new-order-status':
                case 's2p-order-status-on-success':
                case 's2p-order-status-on-cancel':
                case 's2p-order-status-on-fail':
                case 's2p-order-status-on-expire':
                    break;
                default:
                    if (isset($setting['_default'])) {
                        Configuration::updateValue($setting['name'], $setting['_default']);
                    }
                    break;
            }
        }

        /*
         * Install database
         */
        $this->installDatabase();

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

        // ! S2p custom order statuses are not removed in order to assure data consistency
        //   This way issues are avoided when there are orders having this type of status attached, and module is uninstalled
        //
        // $this->deleteCustomOrderStatuses();

        /*
         * Uninstall Database
         */
        $this->uninstallDatabase();

        return true;
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

        $settings['signature'] = $settings['s2p-signature-' . $settings['s2p-env']];
        $settings['mid'] = $settings['s2p-mid-' . $settings['s2p-env']];
        $settings['postURL'] = $settings['s2p-post-url-' . $settings['s2p-env']];

        return $settings;
    }

    /**
     * Compute SHA256 Hash
     *
     * @param $message
     *
     * @return string
     */
    public function computeSHA256Hash($message){
        return hash("sha256", strtolower($message));
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
            function($key, $value) {
                return $key . $value;
            },
            array_keys($data),
            $data
        );

        return join("", $mappedData);
    }

    /**
     * Get module's logs wrapped in HTML tags (mainly used to be printed within admin configuration view of the module)
     *
     * @return mixed
     */
    public function getLogsHTML()
    {
        $this->context->smarty->assign(array(
            'logs' => $this->getLogs()
        ));

        return $this->fetchTemplate('/views/templates/admin/logs.tpl');
    }

    /**
     * Get logs
     *
     * @param bool $reduceToString  When set to true it returns a string instead of an array
     *
     * @return array|string
     */
    public function getLogs($reduceToString = false) {
        $logs = Db::getInstance()->ExecuteS(
            "SELECT * FROM `" . _DB_PREFIX_ . "smart2pay_logs` ORDER BY log_created DESC, log_id DESC"
        );

        if (!$reduceToString) {
            return $logs;
        }

        $logsString = "";

        foreach ($logs as $log) {
            $logsString .= "[" . $log['log_type'] . "] "
                . "(" . $log['log_created'] . ") "
                . $log['log_data'] . " "
                . $log['log_source_file'] . ":" . $log['log_source_file_line']
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
    public function writeLog($message = '', $type = 'info') {
        $backtrace = debug_backtrace();
        $file = $backtrace[0]['file'];
        $line = $backtrace[0]['line'];

        $query = "INSERT INTO `" . _DB_PREFIX_ . "smart2pay_logs`
                    (log_data, log_type, log_source_file, log_source_file_line)
                  VALUES
                    ('" . $message . "', '" . $type . "', '" . $file . "', '" . $line . "')
        ";

        Db::getInstance()->Execute($query);
    }

    /**
     * Change order status
     *
     * @param OrderCore  $order
     * @param int        $statusId
     * @param bool       $sendCustomerEmail
     *
     * @return bool
     */
    public function changeOrderStatus($order, $statusId, $sendCustomerEmail = false)
    {
        $orderState = new OrderState((int) $statusId);

        if (!Validate::isLoadedObject($order)) {
            $this->writeLog("Can not change apply order state #" . $statusId . " to order #" . $order->id . " - Order can not be loaded");
            return false;
        }

        if (!Validate::isLoadedObject($orderState)) {
            $this->writeLog("Can not change apply order state #" . $statusId . " to order #" . $order->id . " - Order state can not be loaded");
            return false;
        }

        $history = new OrderHistory();
        $history->id_order = (int)$order->id;
        $history->changeIdOrderState($statusId, (int)($order->id));

        if ($sendCustomerEmail) {
            $history->addWithemail();
        } else {
            $history->add();
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
    public function fetchTemplate($name)
    {
        if (version_compare(_PS_VERSION_, '1.4', '<'))
            $this->context->smarty->currentTemplate = $name;
        elseif (version_compare(_PS_VERSION_, '1.5', '<'))
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

        return $this->display(__FILE__, $name);
    }

    /**
     * Get s2p payment method module by method ID
     *
     * @param $methodId
     *
     * @return null|PaymentModule  Returns null or an extended PaymentModule instance
     */
    public function getMethodModule($methodId)
    {
        $methodDetails = $this->getMethodDetails($methodId);

        if (!$methodDetails) {
            return null;
        }

        $methodModuleName = $this->resolveMethodModuleName($methodDetails['display_name']);

        if (Module::isInstalled($methodModuleName))
        {
            $module = Module::getInstanceByName($methodModuleName);

            if ($module) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Resolve method module name
     *
     * @param $displayName
     *
     * @return string
     */
    public function resolveMethodModuleName($displayName)
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($displayName));
    }

    /**
     * Get s2p method details by ID
     *
     * @param $methodId
     *
     * @return array|null
     */
    public function getMethodDetails($methodId)
    {
        if (array_key_exists($methodId, self::$cache['methodDetails'])) {
            return self::$cache['methodDetails'][$methodId];
        }

        $method = Db::getInstance()->ExecuteS(
            "SELECT * FROM `"._DB_PREFIX_."smart2pay_method` WHERE `method_id` = '" . $methodId . "'"
        );

        if (!empty($method)) {
            self::$cache['methodDetails'][$methodId] = $method[0];
            return $method[0];
        }

        self::$cache['methodDetails'][$methodId] = null;

        return null;
    }

    /**
     * Get Config Form Select Input Options
     *
     * @param null $name
     * @return array
     */
    public function getConfigFormSelectInputOptions($name = null)
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

    /**
     * Get default configuration form inputs for a s2p payment method module
     *
     * @param $methodId
     *
     * @return array
     */
    public function getMethodDefaultConfigFormInputs($methodId)
    {
        $methodDetails = $this->getMethodDetails($methodId);

        return array (
            array(
                'type' => 'select',
                'label' => $this->l('Enabled'),
                'name' => 's2p-' . $this->resolveMethodModuleName($methodDetails['display_name']) . '-enabled',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name'
                )
            )
        );
    }

    /**
     * Check if s2p method is available in some particular country
     *
     * @param $methodId
     * @param $countryISOCode
     *
     * @return bool
     */
    public function isMethodAvailable($methodId, $countryISOCode)
    {
        /*
         * Check for base module to be active
         */
        if (!Configuration::get('s2p-enabled')) {
            return false;
        }

        $methodDetails = $this->getMethodDetails($methodId);

        if (empty($methodDetails)) {
            return false;
        }

        /*
         * Check for current module to be available
         */
        $enabled = Configuration::get(
            's2p-' . $this->resolveMethodModuleName($methodDetails['display_name']) . '-enabled'
        );

        if (!$enabled) {
            return false;
        }

        $countryMethod = Db::getInstance()->executeS(
            "
                SELECT CM.method_id
                FROM " . _DB_PREFIX_ . "smart2pay_country_method CM
                LEFT JOIN " . _DB_PREFIX_ . "smart2pay_country C ON C.country_id = CM.country_id
                WHERE C.code = '" . DB::getInstance()->_escape($countryISOCode) . "' AND CM.method_id = " . $methodId . "
            "
        );

        /*
         * Check for method availability within current country
         */
        if (!$countryMethod) {
            return false;
        }

        return true;
    }

    /**
     * Remove s2p database schema
     */
    private function uninstallDatabase()
    {
        Db::getInstance()->Execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_logs`");
        Db::getInstance()->Execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_method`");
        Db::getInstance()->Execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_country`");
        Db::getInstance()->Execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_country_method`");
    }

    /**
     * Create and populate s2p database schema
     */
    private function installDatabase()
    {
        /*
         * Install module's database
         */
        Db::getInstance()->Execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_logs`");
        Db::getInstance()->Execute("
            CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_logs` (
                `log_id` int(11) NOT NULL auto_increment,
                `log_type` varchar(255) default NULL,
                `log_data` text default NULL,
                `log_source_file` varchar(255) default NULL,
                `log_source_file_line` varchar(255) default NULL,
                `log_created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (`log_id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8
        ");

        Db::getInstance()->Execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_method`");
        Db::getInstance()->Execute("
            CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_method` (
                `method_id` int(11) NOT NULL auto_increment,
                `display_name` varchar(255) default NULL,
                `provider_value` varchar(255) default NULL,
                `description` text ,
                `logo_url` varchar(255) default NULL,
                `guaranteed` int(1) default NULL,
                `active` int(1) default NULL,
                PRIMARY KEY  (`method_id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8
        ");
        Db::getInstance()->Execute("
            INSERT INTO `" . _DB_PREFIX_ . "smart2pay_method` (`method_id`, `display_name`, `provider_value`, `description`, `logo_url`, `guaranteed`, `active`) VALUES
            (1, 'Bank Transfer', 'banktransfer', 'Bank Transfer description', 'bank_transfer_logo_v5.gif', 1, 1),
            (2, 'iDEAL', 'ideal', 'iDEAL description', 'ideal.jpg', 1, 1),
            (3, 'MrCash', 'mrcash', 'MrCash description', 'mrcash.gif', 1, 1),
            (4, 'Giropay', 'giropay', 'Giropay description', 'giropay.gif', 1, 1),
            (5, 'EPS', 'eps', 'EPS description', 'eps-e-payment-standard.gif', 1, 1),
            (8, 'UseMyFunds', 'umb', 'UseMyFunds description', 'umb.gif', 1, 1),
            (9, 'Sofort Banking', 'dp24', 'Sofort Banking description', 'dp24_sofort.gif', 0, 1),
            (12, 'Przelewy24', 'p24', 'Przelewy24 description', 'p24.gif', 1, 1),
            (13, 'OneCard', 'onecard', 'OneCard description', 'onecard.gif', 1, 1),
            (14, 'CashU', 'cashu', 'CashU description', 'cashu.gif', 1, 1),
            (18, 'POLi', 'poli', 'POLi description', 'poli.gif', 0, 1),
            (19, 'DineroMail', 'dineromail', 'DineroMail description', 'dineromail_v2.gif', 0, 1),
            (20, 'Multibanco SIBS', 'sibs', 'Multibanco SIBS description', 'sibs_mb.gif', 1, 1),
            (22, 'Moneta Wallet', 'moneta', 'Moneta Wallet description', 'moneta_v2.gif', 1, 1),
            (23, 'Paysera', 'paysera', 'Paysera description', 'paysera.gif', 1, 1),
            (24, 'Alipay', 'alipay', 'Alipay description', 'alipay.jpg', 1, 1),
            (25, 'Abaqoos', 'abaqoos', 'Abaqoos description', 'abaqoos.gif', 1, 1),
            (27, 'ePlatby for eKonto', 'ebanka', 'ePlatby for eKonto description', 'eKonto.gif', 1, 1),
            (28, 'Ukash', 'ukash', 'Ukash description', 'ukash.gif', 1, 1),
            (29, 'Trustly', 'trustly', 'Trustly description', 'trustly.png', 1, 1),
            (32, 'Debito Banco do Brasil', 'debitobdb', 'Debito Banco do Brasil description', 'banco_do_brasil.jpg', 1, 1),
            (33, 'CuentaDigital', 'cuentadigital', 'CuentaDigital description', 'cuentadigital.gif', 1, 1),
            (34, 'CardsBrazil', 'cardsbrl', 'CardsBrazil description', 'cards_brl.gif', 0, 1),
            (35, 'PaysBuy', 'paysbuy', 'PaysBuy description', 'paysbuy.gif', 0, 1),
            (36, 'Mazooma', 'mazooma', 'Mazooma description', 'mazooma.gif', 0, 1),
            (37, 'eNETS Debit', 'enets', 'eNETS Debit description', 'enets.gif', 1, 1),
            (40, 'Paysafecard', 'paysafecard', 'Paysafecard description', 'paysafecard.gif', 1, 1),
            (42, 'PayPal', 'paypal', 'PayPal description', 'paypal.jpg', 1, 0),
            (43, 'PagTotal', 'pagtotal', 'PagTotal description', 'pagtotal.jpg', 0, 1),
            (44, 'Payeasy', 'payeasy', 'Payeasy description', 'payeasy.gif', 1, 1),
            (46, 'MercadoPago', 'mercadopago', 'MercadoPago description', 'mercadopago.jpg', 0, 1),
            (47, 'Mozca', 'mozca', 'Mozca description', 'mozca.jpg', 0, 1),
            (49, 'ToditoCash', 'toditocash', 'ToditoCash description', 'todito_cash.gif', 1, 1),
            (58, 'PayWithMyBank', 'pwmb', 'PayWithMyBank description', 'pwmb.png', 1 , 1),
            (62, 'Tenpay','tenpay','Tenpay description','tenpay.gif',1,1),
            (63, 'TrustPay', 'trustpay','TrustPay description', 'trustpay.png', 1 , 1),
            (64, 'MangirKart', 'mangirkart', 'MangirKart description', 'mangirkart.jpg', 1, 1),
            (65, 'Finnish Banks', 'paytrail', 'Paytrail description', 'paytrail.gif', 1,1),
            (66, 'MTCPay', 'mtcpay', 'MTCPay description', 'mtcpay.png', 1, 1),
            (67, 'DragonPay', 'dragonpay', 'DragonPay description', 'dragonpay.jpg', 1 , 1),
            (69, 'Credit Card', 's2pcards', 'S2PCards Description', 's2pcards.jpg', 0,1),
            (71, 'INTERAC® Online', 'interaconline', 'INTERAC® Online Description', 'interac-online.gif', 1, 1),
            (72, 'PagoEfectivo', 'pagoefectivo', 'PagoEfectivo Description', 'pago_efectivo.gif', 1,1),
			(73, 'MyBank', 'mybank', 'MyBank Description', 'mybank.png',1,1),
			(74, 'Yandex.Money', 'yandexmoney', 'YandexMoney description', 'yandex_money.png', 1,1),
			(76, 'Bitcoin', 'bitcoin', 'Bitcoin description', 'bitcoin.png', 1,1),
            (1000, 'Boleto', 'paganet', 'Boleto description', 'boleto.jpg', 1, 1),
            (1001, 'Debito', 'paganet', 'Debito description', 'debito_bradesco.jpg', 1, 1),
            (1002, 'Transferencia', 'paganet', 'Transferencia description', 'bradesco_transferencia.jpg', 1, 1),
            (1003, 'QIWI Wallet', 'qiwi', 'QIWI Wallet description', 'qiwi_wallet_v2.gif', 1, 1),
            (1004, 'Beeline', 'qiwi', 'Beeline description', 'beeline.gif', 1, 1),
            (1005, 'Megafon', 'qiwi', 'Megafon description', 'megafon_v1.gif', 1, 1),
            (1006, 'MTS', 'qiwi', 'MTS description', 'mts.gif', 1, 1),
            (1007, 'WebMoney', 'moneta', 'WebMoney description', 'webmoney_v1.gif', 1, 1),
            (1008, 'Yandex', 'moneta', 'Yandex description', 'yandex_money.gif', 1, 1),
            (1009, 'Alliance Online', 'asiapay', 'Alliance Online description', 'alliance_online.gif', 1, 1),
            (1010, 'AmBank', 'asiapay', 'AmBank description', 'ambankgroup.gif', 1, 1),
            (1011, 'CIMB Clicks', 'asiapay', 'CIMB Clicks description', 'cimb_clicks.gif', 1, 1),
            (1012, 'FPX', 'asiapay', 'FPX description', 'FPX.gif', 1, 1),
            (1013, 'Hong Leong Bank Transfer', 'asiapay', 'Hong Leong Bank Transfer description', 'hong_leong.gif', 1, 1),
            (1014, 'Maybank2U', 'asiapay', 'Maybank2U description', 'maybank2u.gif', 1, 1),
            (1015, 'Meps Cash', 'asiapay', 'Meps Cash description', 'meps_cash.gif', 1, 1),
            (1016, 'Mobile Money', 'asiapay', 'Mobile Money description', 'mobile_money.gif', 1, 1),
            (1017, 'RHB', 'asiapay', 'RHB description', 'rhb.gif', 1, 1),
            (1018, 'Webcash', 'asiapay', 'Webcash description', 'web_cash.gif', 1, 1),
            (1019, 'Credit Cards Colombia', 'pagosonline', 'Credit Cards Colombia description', 'cards_colombia.jpg', 1, 1),
            (1020, 'PSE', 'pagosonline', 'PSE description', 'pse.gif', 1, 1),
            (1021, 'ACH Debit', 'pagosonline', 'ACH Debit description', 'ach.gif', 1, 1),
            (1022, 'Via Baloto', 'pagosonline', 'Via Baloto description', 'payment_in_cash.gif', 1, 1),
            (1023, 'Referenced Payment', 'pagosonline', 'Referenced Payment description', 'payment_references.gif', 1, 1),
            (1024, 'Mandiri', 'asiapay', 'Mandiri description', 'mandiri.gif', 1, 1),
            (1025, 'XL Tunai', 'asiapay', 'XL Tunai description', 'XLTunai.gif', 1, 1),
            (1026, 'Bancomer Pago referenciado', 'dineromaildirect', 'Bancomer Pago referenciado description', 'bancomer.gif', 1, 1),
            (1027, 'Santander Pago referenciado', 'dineromaildirect', 'Santander Pago referenciado description', 'santander.gif', 1, 1),
            (1028, 'ScotiaBank Pago referenciado', 'dineromaildirect', 'ScotiaBank Pago referenciado description', 'scotiabank.gif', 1, 1),
            (1029, '7-Eleven Pago en efectivo', 'dineromaildirect', '7-Eleven Pago en efectivo description', '7eleven.gif', 1, 1),
            (1030, 'Oxxo Pago en efectivo', 'dineromaildirect', 'Oxxo Pago en efectivo description', 'oxxo.gif', 1, 1),
            (1031, 'IXE Pago referenciado', 'dineromaildirect', 'IXE Pago referenciado description', 'IXe.gif', 1, 1),
            (1033, 'Cards Thailand', 'paysbuy', 'Cards Thailand description', 'cards_brl.gif', 1, 1),
            (1034, 'PayPalThailand', 'paysbuy', 'PayPalThailand description', 'paypal.jpg', 1, 1),
            (1035, 'AMEXThailand', 'paysbuy', 'AMEXThailand description', 'american_express.jpg', 1, 1),
            (1036, 'Cash Options Thailand', 'paysbuy', 'Cash Options Thailand description', 'cash_paysbuy.jpg', 1, 1),
            (1037, 'OnlineBankingThailand', 'paysbuy', 'OnlineBankingThailand description', 'onlinebankingthailand.gif', 1, 1),
            (1038, 'PaysBuy Wallet', 'paysbuy', 'PaysBuy Wallet description', 'paysbuy.gif', 1, 1),
            (1039, 'Pagos en efectivo', 'dineromaildirect', 'Pagos en efectivo Chile description', 'pagos_en_efectivo_servipag_bci_chile.png', 1, 1),
			(1040, 'Pagos en efectivo', 'dineromaildirect', 'Pagos en efectivo Argentina description', 'argentina_banks.png', 1,1),
			(1041, 'OP-Pohjola', 'paytrail', 'OP-Pohjola description', 'op-pohjola.png', 1, 1),
			(1042, 'Nordea', 'paytrail','Nordea description', 'nordea.png', 1,1),
			(1043, 'Danske bank', 'paytrail','Danske description', 'danske_bank.png', 1,1),
			(1044, 'Cash-in' , 'yandexmoney', 'Cash-in description', 'cashinyandex.gif',1,1),
			(1045, 'Cards Russia', 'yandexmoney', 'Cards Russia description', 's2pcards.jpg',0,1)

        ");

        Db::getInstance()->Execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_country`");
        Db::getInstance()->Execute("
            CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_country` (
                `country_id` int(11) NOT NULL auto_increment,
                `code` varchar(3) default NULL,
                `name` varchar(100) default NULL,
                PRIMARY KEY  (`country_id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8
        ");
        Db::getInstance()->Execute("
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
            (181, 'RE', 'R'),
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
        ");


        Db::getInstance()->Execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_country_method`");
        Db::getInstance()->Execute("
            CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_country_method` (
                `id` int(11) NOT NULL auto_increment,
                `country_id` int(11) default NULL,
                `method_id` int(11) default NULL,
                `priority` int(2) default NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8
        ");
        Db::getInstance()->Execute("
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
        ");
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
                'name' => 's2p-enabled',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name'
                ),
                '_default' => 1
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Environment'),
                'name' => 's2p-env',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('envs'),
                    'id' => 'id',
                    'name' => 'name'
                ),
                '_default' => 'test'
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Post URL Test'),
                'name' => 's2p-post-url-test',
                'required' => true,
                '_default' => 'https://apitest.smart2pay.com',
                '_validate' => ['url', 'notEmpty']
            ),
            array(
                'type' => 'text',
                'label' => $this->l('MID Live'),
                'name' => 's2p-mid-live',
                'required' => true,
                '_validate' => ['notEmpty']
            ),
            array(
                'type' => 'text',
                'label' => $this->l('MID Test'),
                'name' => 's2p-mid-test',
                'required' => true,
                '_validate' => ['notEmpty']
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Site ID'),
                'name' => 's2p-site-id',
                'required' => true
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Signature Live'),
                'name' => 's2p-signature-live',
                'required' => true,
                '_validate' => ['notEmpty']
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Signature Test'),
                'name' => 's2p-signature-test',
                'required' => true,
                '_validate' => ['notEmpty']
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Return URL'),
                'name' => 's2p-return-url',
                'required' => true,
                '_default' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=s2p&controller=returnHandler&id_lang=1',
                '_validate' => ['url', 'notEmpty']
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Send order number as product description'),
                'name' => 's2p-send-order-number-as-product-description',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Custom product description'),
                'name' => 's2p-custom-product-description',
                'required' => true,
                '_default' => 'Custom product description',
                '_validate' => ['notEmpty']
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Notify customer by email'),
                'name' => 's2p-notify-customer-by-email',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name'
                ),
                '_default' => 0
            ),
            /*
            array(
                'type' => 'select',
                'label' => $this->l('Send payment instructions on order creation'),
                'name' => 's2p-send-payment-instructions-on-order-creation',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name'
                ),
                '_default' => 0
            ),*/
            /*array(
                'type' => 'select',
                'label' => $this->l('Create invoice on success'),
                'name' => 's2p-create-invoice-on-success',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name'
                ),
                '_default' => 0
            ),*/
            /*array(
                'type' => 'select',
                'label' => $this->l('Automate shipping'),
                'name' => 's2p-automate-shipping',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name'
                ),
                '_default' => 0
            ),*/
            array(
                'type' => 'select',
                'label' => $this->l('New Order Status'),
                'name' => 's2p-new-order-status',
                'required' => true,
                'options' => array(
                    'query' => OrderState::getOrderStates((int)$this->context->language->id),
                    'id' => 'id_order_state',
                    'name' => 'name'
                ),
                '_default' => 3
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Order status on SUCCESS'),
                'name' => 's2p-order-status-on-success',
                'required' => true,
                'options' => array(
                    'query' => OrderState::getOrderStates((int)$this->context->language->id),
                    'id' => 'id_order_state',
                    'name' => 'name'
                ),
                '_default' => 2
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Order status on CANCEL'),
                'name' => 's2p-order-status-on-cancel',
                'required' => true,
                'options' => array(
                    'query' => OrderState::getOrderStates((int)$this->context->language->id),
                    'id' => 'id_order_state',
                    'name' => 'name'
                ),
                '_default' => 6
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Order status on FAIL'),
                'name' => 's2p-order-status-on-fail',
                'required' => true,
                'options' => array(
                    'query' => OrderState::getOrderStates((int)$this->context->language->id),
                    'id' => 'id_order_state',
                    'name' => 'name'
                ),
                '_default' => 8
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Order status on EXPIRED'),
                'name' => 's2p-order-status-on-expire',
                'required' => true,
                'options' => array(
                    'query' => OrderState::getOrderStates((int)$this->context->language->id),
                    'id' => 'id_order_state',
                    'name' => 'name'
                ),
                '_default' => 8
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Skip payment page'),
                'name' => 's2p-skip-payment-page',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name'
                ),
                '_default' => 0
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Redirect in iFrame'),
                'name' => 's2p-redirect-in-iframe',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name'
                ),
                '_default' => 0
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message SUCCESS'),
                'name' => 's2p-message-success',
                'required' => true,
                '_default' => 'The payment succeeded',
                '_validate' => ['notEmpty']
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message FAILED'),
                'name' => 's2p-message-failed',
                'required' => true,
                '_default' => 'The payment process has failed',
                '_validate' => ['notEmpty']
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message CANCELED'),
                'name' => 's2p-message-canceled',
                'required' => true,
                '_default' => 'The payment was canceled',
                '_validate' => ['notEmpty']
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Message PENDING'),
                'name' => 's2p-message-pending',
                'required' => true,
                '_default' => 'The payment is pending',
                '_validate' => ['notEmpty']
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Debug Form'),
                'name' => 's2p-debug-form',
                'required' => true,
                'options' => array(
                    'query' => $this->getConfigFormSelectInputOptions('yesno'),
                    'id' => 'id',
                    'name' => 'name'
                ),
                '_default' => 0
            )
        );
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
                'configName' => 's2p-new-order-status',
                'orderStatusName' => 'Smart2Pay - Awaiting payment'
            ),
            'success' => array(
                'configName' => 's2p-order-status-on-success',
                'orderStatusName' => 'Smart2Pay - Successfully paid'
            ),
            'canceled' => array(
                'configName' => 's2p-order-status-on-cancel',
                'orderStatusName' => 'Smart2Pay - Canceled payment'
            ),
            'failed' => array(
                'configName' => 's2p-order-status-on-fail',
                'orderStatusName' => 'Smart2Pay - Failed payment'
            ),
            'expired' => array(
                'configName' => 's2p-order-status-on-expire',
                'orderStatusName' => 'Smart2Pay - Expired payment'
            )
        );
    }

    /**
     * Create custom s2p order statuses
     */
    private function createCustomOrderStatuses()
    {
        foreach ($this->getPaymentStatesOrderStatuses() as $status) {

            $existingStatus = Db::getInstance()->ExecuteS(
                "SELECT * FROM `"._DB_PREFIX_."order_state_lang` WHERE `name` = '" . $status['orderStatusName'] . "'"
            );

            if (!empty($existingStatus)) {
                $statusID = $existingStatus[0]['id_order_state'];
            } else {
                Db::getInstance()->Execute(
                    'INSERT INTO `'._DB_PREFIX_.'order_state` (`unremovable`, `color`, `module_name`)' .
                    'VALUES(1, \'#660099\', \'s2p\')'
                );

                $statusID = Db::getInstance()->Insert_ID();

                Db::getInstance()->Execute(
                    'INSERT INTO `'._DB_PREFIX_.'order_state_lang` (`id_order_state`, `id_lang`, `name`)
                VALUES(' . intval($statusID) . ', 1, \'' . $status['orderStatusName'] . '\')'
                );
            }

            Configuration::updateValue($status['configName'], $statusID);
        }
    }

    /**
     * Delete custom s2p order statuses
     */
    private function deleteCustomOrderStatuses()
    {
        $ids = Db::getInstance()->executeS(
            'SELECT GROUP_CONCAT(`id_order_state`) as `id_order_state` FROM `'._DB_PREFIX_.'order_state`
                WHERE `module_name` = \''.pSQL('s2p').'\''
        );

        $ids = explode(",", $ids[0]['id_order_state']);

        Db::getInstance()->execute(
            'DELETE FROM `'._DB_PREFIX_.'order_state`
                WHERE `id_order_state` IN (\'' . join('\',\'', (array)$ids) . '\')'
        );

        Db::getInstance()->execute(
            'DELETE FROM `'._DB_PREFIX_.'order_state_lang`
                WHERE `id_order_state` IN (\'' . join('\',\'', (array)$ids) . '\')'
        );
    }
}