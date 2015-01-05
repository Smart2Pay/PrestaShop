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

        $returnStatus = (int) Tools::getValue('returnStatus', 0);

        $this->context->smarty->assign(array(
            'returnStatus' => $returnStatus
        ));

        $this->setTemplate('returnPage.tpl');
    }
}
