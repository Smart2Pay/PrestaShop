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
 * Smart2Pay Payment return script
**/
class Smart2payreturnHandlerModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;
    /** @var Smart2pay $module */
    public $module;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();

        /** @var Smart2pay $s2p_module */
        $s2p_module = $this->module;

        $s2p_module->prepare_return_page();

        if( version_compare( _PS_VERSION_, '1.7', '<' ) )
            $this->setTemplate( 'returnPage.tpl' );
        else
            $this->setTemplate( 'module:smart2pay/views/templates/front/returnPage_1_7.tpl' );
    }
}
