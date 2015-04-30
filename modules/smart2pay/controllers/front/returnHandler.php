<?php

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

        $moduleSettings = $s2p_module->getSettings();

        $returnMessages = array(
            $s2p_module::S2P_STATUS_SUCCESS => $moduleSettings[$s2p_module::CONFIG_PREFIX.'MESSAGE_SUCCESS'],
            $s2p_module::S2P_STATUS_CANCELLED => $moduleSettings[$s2p_module::CONFIG_PREFIX.'MESSAGE_CANCELED'],
            $s2p_module::S2P_STATUS_FAILED => $moduleSettings[$s2p_module::CONFIG_PREFIX.'MESSAGE_FAILED'],
            $s2p_module::S2P_STATUS_PROCESSING => $moduleSettings[$s2p_module::CONFIG_PREFIX.'MESSAGE_PENDING'],
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
