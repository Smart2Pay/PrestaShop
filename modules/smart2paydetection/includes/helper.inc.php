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
 * Smart2Pay helper class
**/
if( !class_exists( 'Smart2Pay_Helper', false ) )
{
    class Smart2Pay_Helper
    {
        /**
         * Returns total amount for shipping
         *
         * @param Cart $cart_obj
         *
         * @return float Shipping total
         */
        public static function get_total_shipping_cost( $cart_obj )
        {
            if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
                return $cart_obj->getTotalShippingCost();

            return $cart_obj->getOrderShippingCost();
        }

        public static function cart_products_to_string( $products_arr, $cart_original_amount, $params = false )
        {
            $return_arr = array();
            $return_arr['total_check'] = 0;
            $return_arr['total_to_pay'] = 0;
            $return_arr['total_before_difference_amount'] = 0;
            $return_arr['total_difference_amount'] = 0;
            $return_arr['surcharge_difference_amount'] = 0;
            $return_arr['surcharge_difference_index'] = 0;
            $return_arr['buffer'] = '';
            $return_arr['articles_arr'] = array();
            $return_arr['articles_meta_arr'] = array();

            $cart_original_amount = floatval( $cart_original_amount );

            if( $cart_original_amount == 0
             or empty( $products_arr ) or !is_array( $products_arr ) )
                return $return_arr;

            if( empty( $params ) or !is_array( $params ) )
                $params = array();

            if( empty( $params['transport_amount'] ) )
                $params['transport_amount'] = 0;
            if( empty( $params['total_surcharge'] ) )
                $params['total_surcharge'] = 0;
            if( empty( $params['amount_to_pay'] ) )
                $params['amount_to_pay'] = $cart_original_amount;

            $amount_to_pay = floatval( $params['amount_to_pay'] );

            $return_arr['total_to_pay'] = $amount_to_pay;

            $articles_arr = array();
            $articles_meta_arr = array();
            $articles_knti = 0;
            $items_total_amount = 0;
            $biggest_price = 0;
            $biggest_price_knti = 0;
            foreach( $products_arr as $product_arr )
            {
                if( empty( $product_arr ) or !is_array( $product_arr ) )
                    continue;

                // 1 => 'Product', 2 => 'Shipping', 3 => 'Handling',
                $article_arr = array();
                $article_arr['ID'] = $product_arr['id_product'];
                $article_arr['Name'] = $product_arr['name'];
                $article_arr['Quantity'] = $product_arr['quantity'];
                $article_arr['Price'] = Tools::ps_round( $product_arr['price_wt'], 2 );
                $article_arr['VAT'] = Tools::ps_round( $product_arr['rate'], 2 );
                // $article_arr['Discount'] = 0;
                $article_arr['Type'] = 1;

                if( $article_arr['Price'] > $biggest_price )
                    $biggest_price_knti = $articles_knti;

                $articles_arr[$articles_knti] = $article_arr;

                $article_meta_arr = array();
                $article_meta_arr['total_price'] = $article_arr['Price'] * $article_arr['Quantity'];
                $article_meta_arr['price_perc'] = ($article_meta_arr['total_price'] * 100) / $cart_original_amount;
                $article_meta_arr['surcharge_amount'] = 0;

                $articles_meta_arr[$articles_knti] = $article_meta_arr;

                $items_total_amount += $article_meta_arr['total_price'];

                $articles_knti++;
            }

            if( empty( $articles_arr ) )
                return $return_arr;

            if( $params['transport_amount'] != 0 )
            {
                // 1 => 'Product', 2 => 'Shipping', 3 => 'Handling',
                $article_arr = array();
                $article_arr['ID'] = 0;
                $article_arr['Name'] = 'Transport';
                $article_arr['Quantity'] = 1;
                $article_arr['Price'] = Tools::ps_round( $params['transport_amount'], 2 );
                $article_arr['VAT'] = 0;
                //$article_arr['Discount'] = 0;
                $article_arr['Type'] = 2;

                $articles_arr[$articles_knti] = $article_arr;

                $article_meta_arr = array();
                $article_meta_arr['total_price'] = $article_arr['Price'] * $article_arr['Quantity'];
                $article_meta_arr['price_perc'] = 0;
                $article_meta_arr['surcharge_amount'] = 0;

                $articles_meta_arr[$articles_knti] = $article_meta_arr;

                $items_total_amount += $article_meta_arr['total_price'];

                $articles_knti++;
            }

            // Apply surcharge (if required) depending on product price percentage of full amount
            $total_surcharge = 0;
            if( $params['total_surcharge'] != 0 )
            {
                $total_surcharge = $params['total_surcharge'];
                foreach( $articles_arr as $knti => $article_arr )
                {
                    if( $articles_arr[$knti]['Type'] != 1 )
                        continue;

                    $total_article_surcharge = (($articles_meta_arr[$knti]['price_perc'] * $params['total_surcharge'])/100);

                    $article_unit_surcharge = Tools::ps_round( $total_article_surcharge/$articles_arr[$knti]['Quantity'], 2 );

                    $articles_arr[$knti]['Price'] += $article_unit_surcharge;
                    $articles_meta_arr[$knti]['surcharge_amount'] = $article_unit_surcharge;

                    $items_total_amount += ($article_unit_surcharge * $articles_arr[$knti]['Quantity']);
                    $total_surcharge -= ($article_unit_surcharge * $articles_arr[$knti]['Quantity']);
                }

                // If after applying all surcharge amounts as percentage of each product price we still have a difference, apply difference on product with biggest price
                if( $total_surcharge != 0 )
                {
                    $article_unit_surcharge = Tools::ps_round( $total_surcharge/$articles_arr[$biggest_price_knti]['Quantity'], 2 );

                    $articles_arr[$biggest_price_knti]['Price'] += $article_unit_surcharge;
                    $articles_meta_arr[$biggest_price_knti]['surcharge_amount'] += $article_unit_surcharge;
                    $items_total_amount += ($article_unit_surcharge * $articles_arr[$biggest_price_knti]['Quantity']);

                    $return_arr['surcharge_difference_amount'] = $total_surcharge;
                    $return_arr['surcharge_difference_index'] = $biggest_price_knti;
                }
            }

            $return_arr['total_before_difference_amount'] = $items_total_amount;

            // If we still have a difference apply it on biggest price product
            if( Tools::ps_round( $items_total_amount, 2 ) != Tools::ps_round( $amount_to_pay, 2 ) )
            {
                $amount_diff = Tools::ps_round( ($amount_to_pay - $items_total_amount)/$articles_arr[$biggest_price_knti]['Quantity'], 2 );

                $articles_arr[$biggest_price_knti]['Price'] += $amount_diff;

                $return_arr['total_difference_amount'] = Tools::ps_round( $amount_to_pay - $items_total_amount, 2 );
            }

            $total_check = 0;
            foreach( $articles_arr as $knti => $article_arr )
            {
                $total_check += ($article_arr['Price'] * $article_arr['Quantity']);

                $article_arr['Price'] = $article_arr['Price'] * 100;
                $article_arr['VAT'] = $article_arr['VAT'] * 100;
                //$article_arr['Discount'] = $article_arr['Discount'] * 100;

                $article_buf = '';
                foreach( $article_arr as $key => $val )
                {
                    $article_buf .= ($article_buf!=''?'&':'').$key.'='.str_replace( array( '&', ';', '=' ), ' ', $val );
                }

                $return_arr['buffer'] .= $article_buf.';';
            }

            $return_arr['buffer'] = substr( $return_arr['buffer'], 0, -1 );

            // $return_arr['buffer'] = rawurlencode( $return_arr['buffer'] );

            $return_arr['total_check'] = $total_check;
            $return_arr['articles_arr'] = $articles_arr;
            $return_arr['articles_meta_arr'] = $articles_meta_arr;

            return $return_arr;
        }

        /**
         * @param Order $order
         *
         * @return float
         */
        public static function get_order_total_amount( $order )
        {
            if( empty( $order ) )
                return 0;

            if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
                return $order->getOrdersTotalPaid();

            return $order->total_paid;
        }

        public static function send_mail( $id_lang, $template, $subject, $templateVars, $to,
            $toName = null, $from = null, $fromName = null, $fileAttachment = null, $modeSMTP = null,
            $templatePath = _PS_MAIL_DIR_, $die = false, $id_shop = null, $bcc = null )
        {
            if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
                Mail::Send( $id_lang, $template, $subject, $templateVars, $to,
                    $toName, $from, $fromName, $fileAttachment, $modeSMTP,
                    $templatePath, $die, $id_shop, $bcc );
            else
                Mail::Send( $id_lang, $template, $subject, $templateVars, $to,
                    $toName, $from, $fromName, $fileAttachment, $modeSMTP,
                    $templatePath, $die );
        }

        public static function get_documentation_file_name()
        {
            return 'Smart2Pay_PrestaShop_Integration_Guide.pdf';
        }

        public static function get_documentation_url()
        {
            return Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/smart2pay/'.self::get_documentation_file_name();
        }

        public static function get_documentation_path()
        {
            return _PS_MODULE_DIR_.'smart2pay/'.self::get_documentation_file_name();
        }

        public static function get_return_url( $module_name )
        {
            if( version_compare( _PS_VERSION_, '1.5', '>=' ) )
                return Tools::getShopDomainSsl( true, true ) . __PS_BASE_URI__ . 'index.php?fc=module&module='.$module_name.'&controller=returnHandler';

            return Tools::getShopDomainSsl( true, true ).__PS_BASE_URI__.'modules/'.$module_name.'/pre15/returnhandler.php';
        }

        public static function convert_price( $amount, $currency_from = null, $currency_to = null )
        {
            if( version_compare( _PS_VERSION_, '1.5', '>=' )
            and method_exists( 'Tools', 'convertPriceFull' ) )
                return Tools::convertPriceFull( $amount, $currency_from, $currency_to );

            if ($currency_from === $currency_to)
                return $amount;

            if ($currency_from === null)
                $currency_from = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));

            if ($currency_to === null)
                $currency_to = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));

            if ($currency_from->id == Configuration::get('PS_CURRENCY_DEFAULT'))
                $amount *= $currency_to->conversion_rate;
            else
            {
                $conversion_rate = ($currency_from->conversion_rate == 0 ? 1 : $currency_from->conversion_rate);
                // Convert amount to default currency (using the old currency rate)
                $amount = Tools::ps_round($amount / $conversion_rate, 2 );
                // Convert to new currency
                $amount *= $currency_to->conversion_rate;
            }

            return Tools::ps_round( $amount, 2 );
        }

        public static function generate_ancient_form( $fields, $form_data, $form_values )
        {
            if( empty( $fields ) or ! is_array( $fields )
                or empty( $form_data ) or ! is_array( $form_data )
                or empty( $form_data['submit_action'] )
                or empty( $fields[0] ) or ! is_array( $fields[0] )
                or empty( $fields[0]['form'] ) or ! is_array( $fields[0]['form'] )
            )
                return '';

            if( empty( $form_values ) or ! is_array( $form_values ) )
                $form_values = array();

            $form_buffer = '<form action="' . Tools::safeOutput( $_SERVER['REQUEST_URI'] ) . '" method="post">' .
                           '<fieldset>' . ( ! empty( $fields[0]['form']['legend']['title'] ) ? ' <legend>' . $fields[0]['form']['legend']['title'] . '</legend>' : '' );

            if( ! empty( $fields[0]['form']['input'] ) and is_array( $fields[0]['form']['input'] ) )
            {
                foreach( $fields[0]['form']['input'] as $input_arr )
                {
                    if( empty( $input_arr['label'] ) )
                        $input_arr['label'] = 'No label (??)';
                    if( empty( $input_arr['name'] ) )
                        $input_arr['name'] = 'foobar';

                    $form_buffer .= '<label ' . ( ( ! empty( $input_arr['hint'] ) and is_array( $input_arr['hint'] ) ) ? 'title="' . implode( '', $input_arr['hint'] ) . '"' : '' ) . '>' . $input_arr['label'] . ( ! empty( $input_arr['required'] ) ? '<span style="color:red">*</span>' : '' ) . '</label><div class="margin-form">';

                    $current_value = null;
                    if( array_key_exists( $input_arr['name'], $form_values ) )
                        $current_value = $form_values[ $input_arr['name'] ];

                    if( is_null( $current_value ) and isset( $input_arr['_default'] ) )
                        $current_value = $input_arr['_default'];

                    switch( $input_arr['type'] )
                    {
                        default:
                        case 'text':
                            if( $input_arr['type'] != 'text' )
                                $form_buffer .= 'Unknown input type [' . $input_arr['type'] . ']<br/>';

                            $form_buffer .= '<input type="text" name="' . $input_arr['name'] . '" value="' . ( ! is_null( $current_value ) ? $current_value : '' ) . '" style="width: 550px;" />';
                            break;

                        case 'select':
                            $form_buffer .= '<select name="' . $input_arr['name'] . '">';

                            if( ! empty( $input_arr['options'] ) and is_array( $input_arr['options'] )
                                                                     and ! empty( $input_arr['options']['query'] ) and is_array( $input_arr['options']['query'] )
                            )
                            {
                                if( empty( $input_arr['options']['id'] ) )
                                    $input_arr['options']['id'] = 'id';
                                if( empty( $input_arr['options']['name'] ) )
                                    $input_arr['options']['name'] = 'name';

                                foreach( $input_arr['options']['query'] as $query_arr )
                                {
                                    if( ! isset( $query_arr[ $input_arr['options']['id'] ] ) or ! isset( $query_arr[ $input_arr['options']['name'] ] ) )
                                        continue;

                                    $key  = $query_arr[ $input_arr['options']['id'] ];
                                    $text = $query_arr[ $input_arr['options']['name'] ];

                                    $form_buffer .= '<option value="' . $key . '" ' . ( $key == $current_value ? 'selected="selected"' : '' ) . '>' . $text . '</option>';
                                }
                            }

                            $form_buffer .= '</select>';
                            break;
                    }

                    if( ! empty( $input_arr['desc'] ) and is_array( $input_arr['desc'] ) )
                    {
                        foreach( $input_arr['desc'] as $desc_item )
                        {
                            $form_buffer .= '<p class="clear">' . $desc_item . '</p>';
                        }
                    }

                    $form_buffer .= '</div><div class="clear"></div>' . "\r\n\r\n";
                }
            }


            $form_buffer .= '<div style="text-align: center;"><input type="submit" name="' . $form_data['submit_action'] . '" value="' . ( ! empty( $fields[0]['form']['submit']['title'] ) ? $fields[0]['form']['submit']['title'] : 'Save' ) . '" class="' . ( ! empty( $fields[0]['form']['submit']['class'] ) ? $fields[0]['form']['submit']['class'] : 'button' ) . '" /></div>' .
                            '</fieldset></form>';

            return $form_buffer;
        }

        static public function value_to_string( $val )
        {
            if( is_object( $val ) or is_resource( $val ) )
                return false;

            if( is_array( $val ) )
                return Tools::jsonEncode( $val );

            if( is_string( $val ) )
                return '\'' . $val . '\'';

            if( is_bool( $val ) )
                return ( ! empty( $val ) ? 'true' : 'false' );

            if( is_null( $val ) )
                return 'null';

            if( is_numeric( $val ) )
                return $val;

            return false;
        }

        static public function string_to_value( $str )
        {
            if( ! is_string( $str ) )
                return null;

            if( ( $val = @Tools::jsonDecode( $str, true ) ) !== null )
                return $val;

            if( is_numeric( $str ) )
                return $str;

            if( ( $tch = Tools::substr( $str, 0, 1 ) ) == '\'' or $tch = '"' )
                $str = Tools::substr( $str, 1 );
            if( ( $tch = Tools::substr( $str, - 1 ) ) == '\'' or $tch = '"' )
                $str = Tools::substr( $str, 0, - 1 );

            $str_lower = Tools::strtolower( $str );
            if( $str_lower == 'null' )
                return null;

            if( $str_lower == 'false' )
                return false;

            if( $str_lower == 'true' )
                return true;

            return $str;
        }

        static public function to_string( $lines_data )
        {
            if( empty( $lines_data ) or ! is_array( $lines_data ) )
                return '';

            $lines_str  = '';
            $first_line = true;
            foreach( $lines_data as $key => $val )
            {
                if( ! $first_line )
                    $lines_str .= "\r\n";

                $first_line = false;

                // In normal cases there cannot be '=' char in key so we interpret that value should just be passed as-it-is
                if( Tools::substr( $key, 0, 1 ) == '=' )
                {
                    $lines_str .= $val;
                    continue;
                }

                // Don't save if error converting to string
                if( ( $line_val = self::value_to_string( $val ) ) === false )
                    continue;

                $lines_str .= $key . '=' . $line_val;
            }

            return $lines_str;
        }

        static public function parse_string_line( $line_str, $comment_no = 0 )
        {
            if( ! is_string( $line_str ) )
                $line_str = '';

            // allow empty lines (keeps file 'styling' same)
            if( trim( $line_str ) == '' )
                $line_str = '';

            $return_arr               = array();
            $return_arr['key']        = '';
            $return_arr['val']        = '';
            $return_arr['comment_no'] = $comment_no;

            $first_char = Tools::substr( $line_str, 0, 1 );
            if( $line_str == '' or $first_char == '#' or $first_char == ';' )
            {
                $comment_no ++;

                $return_arr['key']        = '=' . $comment_no . '='; // comment count added to avoid comment key overwrite
                $return_arr['val']        = $line_str;
                $return_arr['comment_no'] = $comment_no;

                return $return_arr;
            }

            $line_details = explode( '=', $line_str, 2 );
            $key          = trim( $line_details[0] );

            if( $key == '' )
                return false;

            if( ! isset( $line_details[1] ) )
            {
                $return_arr['key'] = $key;
                $return_arr['val'] = '';

                return $return_arr;
            }

            $return_arr['key'] = $key;
            $return_arr['val'] = self::string_to_value( $line_details[1] );

            return $return_arr;
        }

        static public function parse_string( $string )
        {
            if( empty( $string )
                or ( ! is_array( $string ) and ! is_string( $string ) )
            )
                return array();

            if( is_array( $string ) )
                return $string;

            $string    = str_replace( "\r", "\n", str_replace( array( "\r\n", "\n\r" ), "\n", $string ) );
            $lines_arr = explode( "\n", $string );

            $return_arr = array();
            $comment_no = 1;
            foreach( $lines_arr as $line_nr => $line_str )
            {
                if( ! ( $line_data = self::parse_string_line( $line_str, $comment_no ) )
                    or ! is_array( $line_data ) or ! isset( $line_data['key'] ) or $line_data['key'] == ''
                )
                    continue;

                $return_arr[ $line_data['key'] ] = $line_data['val'];
                $comment_no                      = $line_data['comment_no'];
            }

            return $return_arr;
        }

        static public function update_line_params( $current_data, $append_data )
        {
            if( empty( $append_data ) or ( ! is_array( $append_data ) and ! is_string( $append_data ) ) )
                $append_data = array();
            if( empty( $current_data ) or ( ! is_array( $current_data ) and ! is_string( $current_data ) ) )
                $current_data = array();

            if( ! is_array( $append_data ) )
                $append_arr = self::parse_string( $append_data );
            else
                $append_arr = $append_data;

            if( ! is_array( $current_data ) )
                $current_arr = self::parse_string( $current_data );
            else
                $current_arr = $current_data;

            if( ! empty( $append_arr ) )
            {
                foreach( $append_arr as $key => $val )
                    $current_arr[ $key ] = $val;
            }

            return $current_arr;
        }

        public static function prepare_data( $data )
        {
            $data = str_replace( '\'', '\\\'', str_replace( '\\\'', '\'', $data ) );

            return $data;
        }

        public static function quick_insert( $into, $arr, $params = false )
        {
            if( ! is_array( $arr ) or ! count( $arr ) )
                return '';

            if( empty( $params ) or ! is_array( $params ) )
                $params = array();

            if( ! isset( $params['secure'] ) )
                $params['secure'] = true;

            $return = '';
            foreach( $arr as $key => $val )
            {
                if( is_array( $val ) )
                {
                    if( ! isset( $val['value'] ) )
                        continue;

                    if( empty( $val['raw_field'] ) )
                        $val['raw_field'] = false;

                    $field_value = $val['value'];

                    if( empty( $val['raw_field'] ) )
                    {
                        if( ! empty( $params['secure'] ) )
                            $field_value = self::prepare_data( $field_value );

                        $field_value = '\'' . $field_value . '\'';
                    }
                }
                else
                    $field_value = '\'' . ( ! empty( $params['secure'] ) ? self::prepare_data( $val ) : $val ) . '\'';

                $return .= '`' . $key . '`=' . $field_value . ', ';
            }

            if( $return == '' )
                return '';

            return 'INSERT INTO `' . $into . '` SET ' . substr( $return, 0, - 2 );
        }

        public static function quick_edit( $into, $arr, $params = false )
        {
            if( ! is_array( $arr ) or ! count( $arr ) )
                return '';

            if( empty( $params ) or ! is_array( $params ) )
                $params = array();

            if( ! isset( $params['secure'] ) )
                $params['secure'] = true;

            $return = '';
            foreach( $arr as $key => $val )
            {
                if( is_array( $val ) )
                {
                    if( ! isset( $val['value'] ) )
                        continue;

                    if( empty( $val['raw_field'] ) )
                        $val['raw_field'] = false;

                    $field_value = $val['value'];

                    if( empty( $val['raw_field'] ) )
                    {
                        if( ! empty( $params['secure'] ) )
                            $field_value = self::prepare_data( $field_value );

                        $field_value = '\'' . $field_value . '\'';
                    }
                }
                else
                    $field_value = '\'' . ( ! empty( $params['secure'] ) ? self::prepare_data( $val ) : $val ) . '\'';

                $return .= '`' . $key . '`=' . $field_value . ', ';
            }

            if( $return == '' )
                return '';

            return 'UPDATE `' . $into . '` SET ' . substr( $return, 0, - 2 );
        }

        public static function get_main_notification_request_params()
        {
            return array(
                'MethodID' => 0,
                'NotificationType' => '',
                'PaymentID' => 0,
                'MerchantTransactionID' => 0,
                'StatusID' => 0,
                'Amount' => 0,
                'Currency' => '',
                'Hash' => '',
            );
        }

        public static function normalize_notification_request( $request_arr )
        {
            $default_main_params = self::get_main_notification_request_params();

            if( empty( $request_arr ) or !is_array( $request_arr ) )
                return $default_main_params;

            foreach( $default_main_params as $key => $default )
            {
                if( !array_key_exists( $key, $request_arr ) )
                    $request_arr[$key] = $default;
            }

            return $request_arr;
        }

        /**
         * Parse php input
         *
         * @return mixed
         */
        public static function parse_php_input()
        {
            parse_str( self::get_php_raw_input(), $response );

            return $response;
        }

        /**
         * Get raw php input
         *
         * @return string
         */
        public static function get_php_raw_input()
        {
            static $input;

            if( $input === null )
            {
                // On error, set $input as null to retry next time...
                if( ($input = @Tools::file_get_contents( 'php://input' )) === false )
                    $input = null;
            }

            return $input;
        }

        public static function recompose_hash_string( $raw_input = null )
        {
            if( $raw_input === null )
                $raw_input = self::get_php_raw_input();

            if( empty( $raw_input ) )
                return (!is_string( $raw_input )?'':$raw_input);

            $vars = array();
            $recomposedHashString = '';
            $pairs = explode( '&', $raw_input );

            foreach( $pairs as $pair )
            {
                $nv = explode( '=', $pair );
                $name = $nv[0];
                $vars[$name] = (isset( $nv[1] )?$nv[1]:'');

                if( Tools::strtolower( $name ) != 'hash' )
                    $recomposedHashString .= $name . $vars[$name];
            }

            return $recomposedHashString;
        }


    }
}
