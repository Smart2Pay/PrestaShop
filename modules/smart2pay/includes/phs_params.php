<?php

//! \version 1.71

class PHS_params
{
    const ERR_OK = 0, ERR_PARAMS = 1;

    const T_ASIS = 1, T_INT = 2, T_FLOAT = 3, T_ALPHANUM = 4, T_SAFEHTML = 5, T_NOHTML = 6, T_EMAIL = 7,
          T_REMSQL_CHARS = 8, T_ARRAY = 9, T_DATE = 10, T_URL = 11, T_BOOL = 12, T_NUMERIC_BOOL = 13;

    const FLOAT_PRECISION = 10;

    function __construct()
    {
    }

    static function get_valid_types()
    {
        return array(
            self::T_ASIS, self::T_INT, self::T_FLOAT, self::T_ALPHANUM, self::T_SAFEHTML, self::T_NOHTML, self::T_EMAIL,
            self::T_REMSQL_CHARS, self::T_ARRAY, self::T_DATE, self::T_URL, self::T_BOOL, );
    }

    static function valid_type( $type )
    {
        return in_array( $type, self::get_valid_types() );
    }

    static function check_type( $val, $type, $extra = false )
    {
        switch( $type )
        {
            default:
                return true;
            break;

            case self::T_INT:
                if( preg_match( '/^[+-]?\d+$/', $val ) )
                    return true;
            break;

            case self::T_FLOAT:
                if( preg_match( '/^[+-]?\d+\.?\d*$/', $val ) )
                    return true;
            break;

            case self::T_ALPHANUM:
                if( ctype_alnum( $val ) )
                    return true;
            break;

            case self::T_EMAIL:
                if( preg_match( '/^[a-zA-Z0-9]+[a-zA-Z0-9\._\-\+]*@[a-zA-Z0-9_-]+\.[a-zA-Z0-9\._-]+$/', $val ) )
                    return true;
            break;

            case self::T_DATE:
                if( !empty( $val ) and @strtotime( $val ) !== false )
                    return true;
            break;

            case self::T_URL:
                if( preg_match( '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))(?::\d{2,5})?(?:/[^\s]*)?$_iuS', $val ) )
                    return true;
            break;
        }

        return false;
    }

    static function set_type( $val, $type, $extra = false )
    {
        if( $val === null )
            return null;
        
        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        if( empty( $extra['trim_before'] ) )
            $extra['trim_before'] = false;

        if( !empty( $extra['trim_before'] )
        and is_scalar( $val ) )
            $val = trim( $val );

        switch( $type )
        {
            default:
            case self::T_ASIS:
                return $val;
            break;

            case self::T_INT:
                if( empty( $extra['trim_before'] ) )
                    $val = trim( $val );

                if( $val != '' )
                    $val = intval( $val );
                return $val;
            break;

            case self::T_FLOAT:
                if( empty( $extra['trim_before'] ) )
                    $val = trim( $val );

                if( empty( $extra ) or !is_array( $extra ) )
                    $extra = array();

                if( empty( $extra['digits'] ) )
                    $extra['digits'] = self::FLOAT_PRECISION;

                if( $val != '' )
                {
                    if( @function_exists( 'bcmul' ) )
                    {
                        $val = @bcmul( $val, 1, $extra['digits'] );
                    } else
                    {
                        $val = @number_format( $val, $extra['digits'], '.', '' );
                    }

                    if( strstr( $val, '.' ) !== false )
                    {
                        $val = trim( $val, '0' );
                        if( substr( $val, -1 ) == '.' )
                            $val = substr( $val, 0, -1 );
                        if( substr( $val, 0, 1 ) == '.' )
                            $val = '0'.$val;
                    }

                    $val = floatval( $val );
                }

                return $val;
            break;

            case self::T_ALPHANUM:
                return preg_replace( '/^([a-zA-Z0-9]+)$/', '$1', strip_tags( $val ) );
            break;

            case self::T_SAFEHTML:
                return htmlspecialchars( $val );
            break;

            case self::T_EMAIL:
                return strip_tags( $val );
            break;

            case self::T_NOHTML:
                return strip_tags( $val );
            break;

            case self::T_REMSQL_CHARS:
                return str_replace( array( '--', '\b', '\Z', '%' ), '', $val );
            break;

            case self::T_ARRAY:
                if( empty( $val ) or !is_array( $val ) )
                    return array();

                if( empty( $extra ) or !is_array( $extra ) )
                    $extra = array();

                if( empty( $extra['type'] ) )
                    $extra['type'] = self::T_ASIS;

                foreach( $val as $key => $vval )
                    $val[$key] = self::set_type( $vval, $extra['type'], $extra );

                return $val;
            break;

            case self::T_DATE:
                if( empty( $extra['trim_before'] ) )
                    $val = trim( $val );

                if( empty( $val ) or ($val = @strtotime( $val )) === false or $val === -1 )
                    $val = false;
                else
                {
                    if( !empty( $extra['format'] ) )
                        $val = @date( $extra['format'], $val );
                }

                return $val;
            break;

            case self::T_URL:
                return strip_tags( $val );
            break;

            case self::T_BOOL:
            case self::T_NUMERIC_BOOL:
                if( is_string( $val ) )
                {
                    if( empty( $extra['trim_before'] ) )
                        $val = trim( $val );

                    $low_val = strtolower( $val );

                    if( $low_val == 'true' )
                        $val = true;
                    elseif( $low_val == 'false' )
                        $val = false;
                }

                if( $type == self::T_BOOL )
                    return (!empty( $val )?true:false);
                elseif( $type == self::T_NUMERIC_BOOL )
                    return (!empty( $val )?1:0);
            break;
        }

        return null;
    }

    static function _gp( $v, $type = self::T_ASIS, $extra = false )
    {
        if( isset( $_GET[$v] ) )
            $var = $_GET[$v];
        elseif( isset( $_POST[$v] ) )
            $var = $_POST[$v];
        else
            return null;

        return self::set_type( $var, $type, $extra );
    }

    static function _pg( $v, $type = self::T_ASIS, $extra = false )
    {
        if( isset( $_POST[$v] ) )
            $var = $_POST[$v];
        elseif( isset( $_GET[$v] ) )
            $var = $_GET[$v];
        else
            return null;

        return self::set_type( $var, $type, $extra );
    }

    static function _var( $from, $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $from ) )
            return null;

        $from = strtolower( $from );
        while( !empty( $from[0] ) )
        {
            switch( $from[0] )
            {
                case 'g':
                    if( isset( $_GET[$v] ) )
                        return self::_g( $v, $type, $extra );
                break;
                case 'p':
                    if( isset( $_POST[$v] ) )
                        return self::_p( $v, $type, $extra );
                break;
                case 's':
                    if( isset( $_SESSION[$v] ) )
                        return self::_s( $v, $type, $extra );
                break;
                case 'f':
                    if( isset( $_FILES[$v] ) )
                        return self::_f( $v );
                break;
                case 'c':
                    if( isset( $_COOKIE[$v] ) )
                        return self::_c( $v, $type, $extra );
                break;
                case 'r':
                    if( isset( $_REQUEST[$v] ) )
                        return self::_r( $v, $type, $extra );
                break;
                case 'e':
                    if( isset( $_ENV[$v] ) )
                        return self::_e( $v, $type, $extra );
                break;
                case 'v':
                    if( isset( $_SERVER[$v] ) )
                        return self::_v( $v, $type, $extra );
                break;
            }

            $from = substr( $from, 1 );
        }

        return null;
    }

    static function _g( $v, $type = self::T_ASIS, $extra = false )
    {
        if( !isset( $_GET[$v] ) )
            return null;

        return self::set_type( $_GET[$v], $type, $extra );
    }

    static function _p( $v, $type = self::T_ASIS, $extra = false )
    {
        if( !isset( $_POST[$v] ) )
            return null;

        return self::set_type( $_POST[$v], $type, $extra );
    }

    static function _f( $v )
    {
        if( !isset( $_FILES[$v] ) or $_FILES[$v]['name'] == '' )
            return null;

        return $_FILES[$v];
    }

    static function _s( $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $_SESSION ) or !isset( $_SESSION[$v] ) )
            return null;

        return self::set_type( $_SESSION[$v], $type, $extra );
    }

    static function _c( $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $_COOKIE ) or !isset( $_COOKIE[$v] ) )
            return null;

        return self::set_type( $_COOKIE[$v], $type, $extra );
    }

    static function _r( $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $_REQUEST ) or !isset( $_REQUEST[$v] ) )
            return null;

        return self::set_type( $_REQUEST[$v], $type, $extra );
    }

    static function _v( $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $_SERVER ) or !isset( $_SERVER[$v] ) )
            return null;

        return self::set_type( $_SERVER[$v], $type, $extra );
    }

    static function _e( $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $_ENV ) or !isset( $_ENV[$v] ) )
            return null;

        return self::set_type( $_ENV[$v], $type, $extra );
    }
}

