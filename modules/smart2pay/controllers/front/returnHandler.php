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

        $order_id = (int)Tools::getValue( 'MerchantTransactionID', 0 );
        if( empty( $order_id )
         or !($transaction_arr = $s2p_module->get_transaction_by_order_id( $order_id ))
         or !($method_id = $transaction_arr['method_id'])
         or !($method_details = $s2p_module->get_method_details( $method_id )) )
        {
            $transaction_arr = array();
            $method_id = 0;
            $method_details = array();
        }

        $transaction_extra_data = array();
        if( ($transaction_details_titles = $s2p_module::transaction_logger_params_to_title())
        and is_array( $transaction_details_titles ) )
        {
            foreach( $transaction_details_titles as $key => $title )
            {
                $value = Tools::getValue( $key, false );
                if( $value === false )
                    continue;

                $transaction_extra_data[$key] = $value;
            }
        }

        if( empty( $transaction_details_titles ) )
            $transaction_details_titles = array();

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

        $this->context->smarty->assign( array(
            'path' => $path,
            'transaction_extra_titles' => $transaction_details_titles,
            'transaction_extra_data' => $transaction_extra_data,
        ) );

        if( !isset( $returnMessages[$data] ) )
            $this->context->smarty->assign( array( 'message' => $s2p_module->l( 'Unknown return status.' ) ) );
        else
            $this->context->smarty->assign( array( 'message' => $returnMessages[$data] ) );

        $this->setTemplate( 'returnPage.tpl' );
    }
}
