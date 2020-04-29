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
 * Smart2Pay Payment notification script
 **/
class Smart2payreplyHandlerModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /** @var Smart2pay */
    public $module;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $this->display_column_left = false;

        /** @var Smart2pay $s2p_module */
        $s2p_module = $this->module;

        $s2p_module->prepare_notification();

        die();
    }
}
