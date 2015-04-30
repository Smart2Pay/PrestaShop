<?php

class S2pReturnHandlerModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;
    /** @var S2P $module */
    public $module;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();

        $s2p_module = $this->module;

        $moduleSettings = $this->module->getSettings();

        $returnMessages = array(
            $s2p_module::S2P_STATUS_SUCCESS => $moduleSettings['s2p-message-success'],
            $s2p_module::S2P_STATUS_CANCELLED => $moduleSettings['s2p-message-canceled'],
            $s2p_module::S2P_STATUS_FAILED => $moduleSettings['s2p-message-failed'],
            $s2p_module::S2P_STATUS_PROCESSING => $moduleSettings['s2p-message-pending'],
        );

        $data = (int) Tools::getValue( 'data', 0 );

        if( !($path = $this->context->smarty->getVariable( 'path', null, true, false ))
         or ($path instanceof Undefined_Smarty_Variable) )
            $path = '';

        $path .= '<a >'.$s2p_module->l( 'Transaction Completed' ).'</a>';

        $this->context->smarty->assign( array( 'path' => $path ) );

        if( !isset( $returnMessages[$data] ) )
            $this->context->smarty->assign( array( 'message' => $s2p_module->l( 'Unknown return status.' ) ) );
        else
            $this->context->smarty->assign( array( 'message' => $returnMessages[$data] ) );

        $this->setTemplate( 'returnPage.tpl' );
    }
}
