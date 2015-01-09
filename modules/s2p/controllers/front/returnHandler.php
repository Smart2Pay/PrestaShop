<?php

class S2pReturnHandlerModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $moduleSettings = $this->module->getSettings();

        $returnMessages = array(
            2 => $moduleSettings['s2p-message-success'],
            3 => $moduleSettings['s2p-message-canceled'],
            4 => $moduleSettings['s2p-message-failed'],
            7 => $moduleSettings['s2p-message-pending']
        );

        $returnStatus = (int) Tools::getValue('data', 0);

        $this->context->smarty->assign(array(
            'message' => $returnMessages[$returnStatus]
        ));

        $this->setTemplate('returnPage.tpl');
    }
}
