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

/*
 * Smar2Pay params class
 * version 1.71
 **/

class PHSParams
{
    const ERR_OK = 0;
    const ERR_PARAMS = 1;

    const T_ASIS = 1;
    const T_INT = 2;
    const T_FLOAT = 3;
    const T_ALPHANUM = 4;
    const T_SAFEHTML = 5;
    const T_NOHTML = 6;
    const T_EMAIL = 7;
    const T_REMSQL_CHARS = 8;
    const T_ARRAY = 9;
    const T_DATE = 10;
    const T_URL = 11;
    const T_BOOL = 12;
    const T_NUMERIC_BOOL = 13;

    const FLOAT_PRECISION = 10;

    public function __construct()
    {
    }

    public static function validType($type)
    {
        return in_array($type, self::getValidTypes());
    }

    public static function getValidTypes()
    {
        return [
            self::T_ASIS, self::T_INT, self::T_FLOAT, self::T_ALPHANUM, self::T_SAFEHTML, self::T_NOHTML, self::T_EMAIL,
            self::T_REMSQL_CHARS, self::T_ARRAY, self::T_DATE, self::T_URL, self::T_BOOL,];
    }

    public static function checkType($val, $type)
    {
        $return = false;
        switch ($type) {
            default:
                $return = true;
                break;

            case self::T_INT:
                if (preg_match('/^[+-]?\d+$/', $val)) {
                    $return = true;
                }
                break;

            case self::T_FLOAT:
                if (preg_match('/^[+-]?\d+\.?\d*$/', $val)) {
                    $return = true;
                }
                break;

            case self::T_ALPHANUM:
                if (ctype_alnum($val)) {
                    $return = true;
                }
                break;

            case self::T_EMAIL:
                if (preg_match('/^[a-zA-Z0-9]+[a-zA-Z0-9\._\-\+]*@[a-zA-Z0-9_-]+\.[a-zA-Z0-9\._-]+$/', $val)) {
                    $return = true;
                }
                break;

            case self::T_DATE:
                if (!empty($val) and @strtotime($val) !== false) {
                    $return = true;
                }
                break;

            case self::T_URL:
                if (preg_match(self::urlRegex(), $val)) {
                    $return = true;
                }
                break;
        }

        return $return;
    }

    public static function setType($val, $type, $extra = false)
    {
        if ($val === null) {
            return null;
        }

        if (empty($extra) or !is_array($extra)) {
            $extra = [];
        }

        if (empty($extra['trim_before'])) {
            $extra['trim_before'] = false;
        }

        if (!empty($extra['trim_before'])
            and is_scalar($val)) {
            $val = trim($val);
        }
        $return = null;

        switch ($type) {
            default:
            case self::T_ASIS:
                $return = $val;
                break;

            case self::T_INT:
                if (empty($extra['trim_before'])) {
                    $val = trim($val);
                }

                if ($val != '') {
                    $val = (int)$val;
                }

                $return = $val;
                break;

            case self::T_FLOAT:
                if (empty($extra['trim_before'])) {
                    $val = trim($val);
                }

                if (empty($extra) or !is_array($extra)) {
                    $extra = [];
                }

                if (empty($extra['digits'])) {
                    $extra['digits'] = self::FLOAT_PRECISION;
                }

                if ($val != '') {
                    $val = @number_format($val, $extra['digits'], '.', '');

                    if (strstr($val, '.') !== false) {
                        $val = trim($val, '0');
                        if (Tools::substr($val, -1) == '.') {
                            $val = Tools::substr($val, 0, -1);
                        }
                        if (Tools::substr($val, 0, 1) == '.') {
                            $val = '0' . $val;
                        }
                    }

                    $val = (float)$val;
                }

                $return = $val;
                break;

            case self::T_ALPHANUM:
                $return = preg_replace('/^([a-zA-Z0-9]+)$/', '$1', strip_tags($val));
                break;

            case self::T_SAFEHTML:
                $return = htmlspecialchars($val);
                break;

            case self::T_EMAIL:
            case self::T_NOHTML:
            case self::T_URL:
                $return = strip_tags($val);
                break;

            case self::T_REMSQL_CHARS:
                $return = str_replace(['--', '\b', '\Z', '%'], '', $val);
                break;

            case self::T_ARRAY:
                if (empty($val) or !is_array($val)) {
                    $return = [];
                    continue;
                }

                if (empty($extra) or !is_array($extra)) {
                    $extra = [];
                }

                if (empty($extra['type'])) {
                    $extra['type'] = self::T_ASIS;
                }

                foreach ($val as $key => $vval) {
                    $val[$key] = self::setType($vval, $extra['type'], $extra);
                }

                $return = $val;
                break;

            case self::T_DATE:
                if (empty($extra['trim_before'])) {
                    $val = trim($val);
                }

                if (empty($val) or ($val = @strtotime($val)) === false or $val === -1) {
                    $val = false;
                } else {
                    if (!empty($extra['format'])) {
                        $val = @date($extra['format'], $val);
                    }
                }

                $return = $val;
                break;

            case self::T_BOOL:
            case self::T_NUMERIC_BOOL:
                if (is_string($val)) {
                    if (empty($extra['trim_before'])) {
                        $val = trim($val);
                    }

                    $low_val = Tools::strtolower($val);

                    if ($low_val == 'true') {
                        $val = true;
                    } elseif ($low_val == 'false') {
                        $val = false;
                    }
                }

                if ($type == self::T_BOOL) {
                    $return = !empty($val);
                } elseif ($type == self::T_NUMERIC_BOOL) {
                    $return = !empty($val) ? 1 : 0;
                }
                break;
        }

        return $return;
    }

    private static function urlRegex()
    {
        return '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?'.
        '(?:(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})'.
        '(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})'.
        '(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])'.
        '(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}'.
        '(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)'.
        '(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*'.
        '(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))'.
        '(?::\d{2,5})?'.
        '(?:/[^\s]*)?$_iuS';
    }
}
