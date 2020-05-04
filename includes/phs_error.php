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
 * Smar2Pay error class
 **/

class PHSError
{
    const ERR_OK = 0;
    const ERR_PARAMETERS = 10000;
    const ERR_FUNCTIONALITY = 10001;

    const WARNING_NOTAG = -1;

    //! Error code as integer
    /** @var int */
    private $error_no = self::ERR_OK;
    //! Contains error message including debugging information
    /** @var string */
    private $error_msg = '';
    //! Contains only error message
    /** @var string */
    private $error_simple_msg = '';
    //! Contains a debugging error message
    /** @var string */
    private $error_debug_msg = '';

    //! Warnings count
    /** @var int */
    private $warnings_no = 0;
    //! Warning messages as array. Warnings are categorized by tags saved as array keys
    /** @var array */
    private $warnings_arr = [];

    //! If true platform will automatically throw errors in set_error() method
    /** @var bool */
    private $throw_errors = false;

    //! Tells if platform in is debugging mode
    /** @var bool */
    private $debugging_mode = false;

    //! Tells if we should get full backtrace when we have an error.
    //! In some cases debug_backtrace() would result in Segmentation fault because of some resources
    // (eg. SSH2/SFTP connections)
    /** @var bool */
    private $suppress_backtrace = false;

    public function __construct(
        $error_no = self::ERR_OK,
        $error_msg = '',
        $error_debug_msg = '',
        $static_instance = false
    ) {
        $error_no = (int) $error_no;
        $error_msg = trim($error_msg);

        $this->error_no = $error_no;
        $this->error_msg = $error_msg;
        $this->error_debug_msg = $error_debug_msg;

        // Make sure we inherit debugging mode from static call...
        if (empty($static_instance)) {
            $this->throwErrors(self::stThrowErrors());
            $this->debuggingMode(self::stDebuggingMode());
            $this->suppressBacktrace(self::stSuppressBacktrace());
        }
    }

    public function throwErrors($mode = null)
    {
        if (is_null($mode)) {
            return $this->throw_errors;
        }

        $this->throw_errors = !empty($mode);

        return $this->throw_errors;
    }

    public static function stThrowErrors($mode = null)
    {
        return self::getErrorStaticInstance()->throwErrors($mode);
    }

    //! Tells if we have an error

    public static function getErrorStaticInstance()
    {
        static $error_instance = false;

        if (empty($error_instance)) {
            $error_instance = new PHSError(self::ERR_OK, '', '', true);
        }

        return $error_instance;
    }

    public function debuggingMode($mode = null)
    {
        if (is_null($mode)) {
            return $this->debugging_mode;
        }

        $this->debugging_mode = !empty($mode);

        return $this->debugging_mode;
    }

    public static function stDebuggingMode($mode = null)
    {
        return self::getErrorStaticInstance()->debuggingMode($mode);
    }

    public function suppressBacktrace($mode = null)
    {
        if (is_null($mode)) {
            return $this->suppress_backtrace;
        }

        $this->suppress_backtrace = !empty($mode);

        return $this->suppress_backtrace;
    }

    //! Get number of warnings

    public static function stSuppressBacktrace($mode = null)
    {
        return self::getErrorStaticInstance()->suppressBacktrace($mode);
    }

    public static function stThrowError()
    {
        //$error_instance = self::get_error_static_instance();
        //if( ($error_arr = $error_instance->get_error())
        //and $error_arr['error_no'] == self::ERR_OK )
        //    return false;
        //
        //if( self::st_debugging_mode() )
        //    throw new \Exception( $error_arr['error_msg'], $error_arr['error_no'] );
        //else
        //    throw new \Exception( $error_arr['error_simple_msg'], $error_arr['error_no'] );
        return self::getErrorStaticInstance()->throwError();
    }

    /**
     * Throw exception with error code and error message only if there is an error code diffrent than self::ERR_OK
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function throwError()
    {
        if ($this->error_no == self::ERR_OK) {
            return false;
        }

        echo 'Full backtrace:' . "\n" .
            $this->debugCallBacktrace(1);

        if ($this->debuggingMode()) {
            throw new \Exception($this->error_debug_msg . ":\n" . $this->error_msg, $this->error_no);
        } else {
            throw new \Exception($this->error_simple_msg, $this->error_no);
        }
    }

    /**
     *  Used for debugging calls to functions or methods.
     *
     * @param int $lvl Tells from which level of backtrace should we cut trace
     *  (helps not showing calls to internal PHS_Error methods)
     * @param int|null $limit Tells how many calls in backtrace to return
     *
     * @return string Method will return a string representing function/method calls.
     */
    public function debugCallBacktrace($lvl = 0, $limit = null)
    {
        if ($this->suppressBacktrace()) {
            return '';
        }

        ++$lvl;
        if (!($err_info = @debug_backtrace())
            or !is_array($err_info)
            or !($err_info = @array_slice($err_info, $lvl))
            or !is_array($err_info)) {
            return '';
        }

        if ($limit !== null) {
            $limit = (int) $limit;
            if (!($err_info = @array_slice($err_info, 0, $limit))
                or !is_array($err_info)) {
                return '';
            }
        }

        $backtrace = '';
        $err_info_len = count($err_info);
        foreach ($err_info as $i => $trace_data) {
            if (!isset($trace_data['args'])) {
                $trace_data['args'] = '';
            }
            if (!isset($trace_data['class'])) {
                $trace_data['class'] = '';
            }
            if (!isset($trace_data['type'])) {
                $trace_data['type'] = '';
            }
            if (!isset($trace_data['function'])) {
                $trace_data['function'] = '';
            }
            if (!isset($trace_data['file'])) {
                $trace_data['file'] = '(unknown)';
            }
            if (!isset($trace_data['line'])) {
                $trace_data['line'] = 0;
            }

            $args_str = '';
            if (is_array($trace_data['args'])) {
                foreach ($trace_data['args'] as $val) {
                    $args_str .= self::mixedToString($val) . ', ';
                }

                $args_str = Tools::substr($args_str, 0, -2);
            } else {
                $args_str = $trace_data['args'];
            }

            $backtrace = '#' . ($err_info_len - $i) . '. ' . $trace_data['class'] . $trace_data['type'] .
                $trace_data['function'] . '( ' . $args_str . ' ) - ' .
                $trace_data['file'] . ':' . $trace_data['line'] . "\n" . $backtrace;
        }

        unset($err_info);

        return $backtrace;
    }

    //! Set error code and error message in an array

    public static function mixedToString($value)
    {
        if (is_bool($value)) {
            return '(' . gettype($value) . ') [' . ($value ? 'true' : 'false') . ']';
        }

        if (is_resource($value)) {
            return '(' . @get_resource_type($value) . ')';
        }

        if (is_array($value)) {
            return '(array) [' . count($value) . ']';
        }

        if (!is_object($value)) {
            $return_str = '(' . gettype($value) . ') [';
            if (is_string($value) and Tools::strlen($value) > 100) {
                $return_str .= Tools::substr($value, 0, 100) . '[...]';
            } else {
                $return_str .= $value;
            }

            $return_str .= ']';

            return $return_str;
        }

        return '(' . @get_class($value) . ')';
    }

    //! Set error code and error message

    public static function stHasError()
    {
        return self::getErrorStaticInstance()->hasError();
    }

    /**
     *   Tells if current error is different than default error code provided in constructor meaning there is an error.
     *
     * @return bool True if there is an error, false if no error
     **/
    public function hasError()
    {
        return $this->error_no != self::ERR_OK;
    }

    public static function stHasWarnings($tag = false)
    {
        return self::getErrorStaticInstance()->hasWarnings($tag);
    }

    /**
     *   Method returns number of warnings warnings (for specified tag or as total)
     *
     * @param string|bool $tag Check if we have warnings for provided tag (false by default)
     *
     * @return int Return warnings number (for specified tag or as total)
     **/
    public function hasWarnings($tag = false)
    {
        if ($tag === false) {
            return $this->warnings_no;
        } elseif (isset($this->warnings_arr[$tag]) and is_array($this->warnings_arr[$tag])) {
            return count($this->warnings_arr[$tag]);
        }

        return 0;
    }

    //! Add a warning message

    public static function varDump($var, $params = false)
    {
        if (empty($params) or !is_array($params)) {
            $params = [];
        }

        if (empty($params['level'])) {
            $params['level'] = 0;
        }
        if (!isset($params['max_level'])) {
            $params['max_level'] = 3;
        }

        if ($params['level'] >= $params['max_level']) {
            if (is_scalar($var)) {
                if (!empty($params['level'])) {
                    return $var;
                }

                ob_start();
                var_dump($var);

                return ob_get_clean();
            }

            return '[Max recursion lvl reached: ' . $params['max_level'] . '] (' . gettype($var) . ' ' .
                self::mixedToString($var) . ')';
        }

        $new_params = $params;
        ++$new_params['level'];

        if (is_array($var)) {
            $new_var = [];
            foreach ($var as $key => $arr_val) {
                $new_var[$key] = self::varDump($arr_val, $new_params);
            }
        } elseif (is_object($var)) {
            $new_var = new \stdClass();
            if (($var_vars = get_object_vars($var))) {
                foreach ($var_vars as $key => $arr_val) {
                    $new_var->$key = self::varDump($arr_val, $new_params);
                }
            }
        } elseif (is_resource($var)) {
            $new_var = 'Resource (' . @get_resource_type($var) . ')';
        } else {
            $new_var = $var;
        }

        if (empty($params['level'])) {
            ob_start();
            var_dump($new_var);

            return ob_get_clean();
        }

        return $new_var;
    }

    public static function stSetError($error_no, $error_msg, $error_debug_msg = '')
    {
        self::getErrorStaticInstance()->setError($error_no, $error_msg, $error_debug_msg);
    }

    //! Remove warnings

    /**
     *   Set an error code and error message. Also method will make a backtrace of this call and present all
     *      functions/methods called (with their parameters) and files/line of call.
     *
     * @param int $error_no Error code
     * @param string $error_msg Error message
     * @param string $error_debug_msg Debugging error message
     * @param bool|array $params Extra parameters
     **/
    public function setError($error_no, $error_msg, $error_debug_msg = '', $params = false)
    {
        if (empty($params) or !is_array($params)) {
            $params = [];
        }

        if (empty($params['prevent_throwing_errors'])) {
            $params['prevent_throwing_errors'] = false;
        }

        if (!($arr = self::arrSetError($error_no, $error_msg, $error_debug_msg))) {
            $arr = self::defaultErrorArray();
        }

        $this->error_no = $arr['error_no'];
        $this->error_simple_msg = $arr['error_simple_msg'];
        $this->error_debug_msg = $arr['error_debug_msg'];
        $this->error_msg = $arr['error_msg'];

        if (empty($params['prevent_throwing_errors'])
            and $this->throwErrors()) {
            $this->throwError();
        }
    }

    /**
     *   Set an error code and error message in an array. Also method will make a backtrace of this call
     *      and present all functions/methods called (with their parameters) and files/line of call.
     *
     * @param int $error_no Error code
     * @param string $error_msg Error message
     * @param string $error_debug_msg Error message
     *
     * @return array
     **/
    public static function arrSetError($error_no, $error_msg, $error_debug_msg = '')
    {
        $backtrace = self::stDebugCallBacktrace();

        $error_arr = self::defaultErrorArray();
        $error_arr['error_no'] = $error_no;
        $error_arr['error_simple_msg'] = $error_msg;
        if ($error_debug_msg != '') {
            $error_arr['error_debug_msg'] = $error_debug_msg;
        } else {
            $error_arr['error_debug_msg'] = $error_msg;
        }
        $error_arr['error_msg'] = 'Error: (' . $error_msg . ')' . "\n" .
            'Code: (' . $error_no . ')' . "\n" .
            'Backtrace:' . "\n" .
            $backtrace;

        if (self::stDebuggingMode()) {
            $error_arr['display_error'] = $error_arr['error_debug_msg'];
        } else {
            $error_arr['display_error'] = $error_arr['error_simple_msg'];
        }

        return $error_arr;
    }

    /**
     *  Used for debugging calls to functions or methods.
     *
     * @return string Method will return a string representing function/method calls.
     */
    public static function stDebugCallBacktrace($lvl = 0)
    {
        return self::getErrorStaticInstance()->debugCallBacktrace($lvl);
    }

    /**
     * @return array Returns default error array structure with default values (no error)
     */
    public static function defaultErrorArray()
    {
        return [
            'error_no' => self::ERR_OK,
            'error_msg' => '',
            'error_simple_msg' => '',
            'error_debug_msg' => '',
            'display_error' => '',
        ];
    }

    public static function stAddWarning($warning, $tag = false)
    {
        self::getErrorStaticInstance()->addWarning($warning, $tag);
    }

    //! Get error details

    /**
     *   Add a warning message for a speficied tag or as general warning. Also method will make a backtrace of this
     *      call and present all functions/methods called (with their parameters) and files/line of call.
     *
     * @param string $warning string Warning message
     * @param bool|string $tag string Add warning for a specific tag (default false).
     *                         If this is not provided warning will be added as general warning.
     **/
    public function addWarning($warning, $tag = false)
    {
        if (empty($this->warnings_arr[self::WARNING_NOTAG])) {
            $this->warnings_arr[self::WARNING_NOTAG] = [];
        }

        $backtrace = $this->debugCallBacktrace(1);

        $warning_unit = [
            'warning_msg' => $warning,
            'debug_msg' => $warning . "\n" .
                'Backtrace:' . "\n" .
                $backtrace,
        ];

        if (!empty($tag)) {
            if (!isset($this->warnings_arr[$tag])) {
                $this->warnings_arr[$tag] = [];
            }

            $this->warnings_arr[$tag][] = $warning_unit;
        } else {
            $this->warnings_arr[self::WARNING_NOTAG][] = $warning_unit;
        }

        ++$this->warnings_no;
    }

    public static function stResetWarnings($tag = false)
    {
        return self::getErrorStaticInstance()->resetWarnings($tag);
    }

    /**
     * Remove warning messages for a speficied tag or all warnings.
     *
     * @param bool|string $tag string Remove warnings of specific tag or all warnings. (default false)
     *
     * @return int Returns number of warnings left after removing required warnings
     **/
    public function resetWarnings($tag = false)
    {
        if ($tag !== false) {
            if (isset($this->warnings_arr[$tag]) and is_array($this->warnings_arr[$tag])) {
                $this->warnings_no -= count($this->warnings_arr[$tag]);
                unset($this->warnings_arr[$tag]);

                if (!$this->warnings_no) {
                    $this->warnings_arr = [];
                }
            }
        } else {
            $this->warnings_arr = [];
            $this->warnings_no = 0;
        }

        return $this->warnings_no;
    }

    /**
     *  Reset error of static instance
     */
    public static function stResetError()
    {
        self::getErrorStaticInstance()->resetError();
    }

    /**
     * Reset instance error
     */
    public function resetError()
    {
        $this->error_no = self::ERR_OK;
        $this->error_msg = '';
        $this->error_simple_msg = '';
        $this->error_debug_msg = '';
    }

    public static function stCopyError($obj, $force_error_code = false)
    {
        return self::getErrorStaticInstance()->copyError($obj, $force_error_code);
    }

    /**
     * Copies error set in $obj to current object
     *
     * @param PHSError $obj
     * @param int|bool $force_error_code
     *
     * @return bool
     */
    public function copyError($obj, $force_error_code = false)
    {
        if (empty($obj) or !($obj instanceof PHSError)
            or !($error_arr = $obj->getError())
            or !is_array($error_arr)) {
            return false;
        }

        $this->error_no = $error_arr['error_no'];
        $this->error_msg = $error_arr['error_msg'];
        $this->error_simple_msg = $error_arr['error_simple_msg'];
        $this->error_debug_msg = $error_arr['error_debug_msg'];

        if ($force_error_code !== false) {
            $this->error_no = $force_error_code;
        }

        return true;
    }

    public static function stGetErrorCode()
    {
        return self::getErrorStaticInstance()->getErrorCode();
    }

    /**
     * @return int Returns error code
     */
    public function getErrorCode()
    {
        return $this->error_no;
    }

    public static function stGetErrorMessage()
    {
        return self::getErrorStaticInstance()->getErrorMessage();
    }

    /**
     * @return string Returns error message
     */
    public function getErrorMessage()
    {
        if ($this->debuggingMode()) {
            return $this->error_debug_msg;
        }

        return $this->error_simple_msg;
    }

    public static function arrGetErrorMessage($err_arr)
    {
        $err_arr = self::validateErrorArr($err_arr);

        if (self::stDebuggingMode()) {
            return $err_arr['error_debug_msg'];
        }

        return $err_arr['error_simple_msg'];
    }

    public static function validateErrorArr($err_arr)
    {
        if (empty($err_arr) or !is_array($err_arr)) {
            $err_arr = [];
        }

        return array_merge(self::defaultErrorArray(), $err_arr);
    }

    public static function arrChangeErrorMessage($err_arr, $error_msg, $error_debug_msg = '')
    {
        $err_arr = self::validateErrorArr($err_arr);

        if (empty($error_debug_msg)) {
            $error_debug_msg = $error_msg;
        }

        $err_arr['error_debug_msg'] = $error_debug_msg;
        $err_arr['error_simple_msg'] = $error_msg;

        return $err_arr;
    }

    public static function arrAppendErrorToArray($error_arr, $error_msg, $error_code = false)
    {
        if (empty($error_msg)) {
            return false;
        }

        $error_arr = self::validateErrorArr($error_arr);

        if ($error_code === false) {
            $error_code = self::arrGetErrorCode($error_arr);
        }

        $append_error_arr = self::arrSetError($error_code, $error_msg);

        return self::arrMergeErrorToArray($error_arr, $append_error_arr);
    }

    public static function arrGetErrorCode($err_arr)
    {
        $err_arr = self::validateErrorArr($err_arr);

        return $err_arr['error_no'];
    }

    public static function arrMergeErrorToArray($source_error_arr, $error_arr)
    {
        $source_error_arr = self::validateErrorArr($source_error_arr);
        $error_arr = self::validateErrorArr($error_arr);

        if ($error_arr['error_msg'] != '') {
            $source_error_arr['error_msg'] .=
                ($source_error_arr['error_msg'] != '' ? "\n\n" : '') . $error_arr['error_msg'];
        }
        if ($error_arr['error_simple_msg'] != '') {
            $source_error_arr['error_simple_msg'] .=
                ($source_error_arr['error_simple_msg'] != '' ? "\n\n" : '') . $error_arr['error_simple_msg'];
        }
        if ($error_arr['error_debug_msg'] != '') {
            $source_error_arr['error_debug_msg'] .=
                ($source_error_arr['error_debug_msg'] != '' ? "\n\n" : '') . $error_arr['error_debug_msg'];
        }
        if ($error_arr['display_error'] != '') {
            $source_error_arr['display_error'] .=
                ($source_error_arr['display_error'] != '' ? "\n\n" : '') . $error_arr['display_error'];
        }

        if (!self::arrHasError($source_error_arr)
            and self::arrHasError($error_arr)) {
            $source_error_arr['error_no'] = $error_arr['error_no'];
        }

        return $source_error_arr;
    }

    public static function arrHasError($err_arr)
    {
        $err_arr = self::validateErrorArr($err_arr);

        return $err_arr['error_no'] != self::ERR_OK;
    }

    public static function stRestoreErrors($errors_arr)
    {
        if (!empty($errors_arr['static_error'])
            and ($static_errors = self::validateErrorArr($errors_arr['static_error']))) {
            self::stCopyErrorFromArray($static_errors);
        }
    }

    public static function stGetWarnings($simple_messages = true, $tag = false)
    {
        return self::getErrorStaticInstance()->getWarnings($simple_messages, $tag);
    }

    /**
     *   Return warnings array for specified tag (if any) or
     *
     * @param bool $simple_messages Tells which set of messages to get (simple or debugging)
     * @param string|bool $tag Check if we have warnings for provided tag (false by default)
     *
     * @return mixed Return array of warnings (all or for specified tag) or false if no warnings
     **/
    public function getWarnings($simple_messages = true, $tag = false)
    {
        if (empty($this->warnings_arr)
            or ($tag !== false and !isset($this->warnings_arr[$tag]))) {
            return false;
        }

        if ($tag === false) {
            $warning_pool = $this->warnings_arr[self::WARNING_NOTAG];
        } else {
            $warning_pool = $this->warnings_arr[$tag];
        }

        if (empty($warning_pool) or !is_array($warning_pool)) {
            return [];
        }

        $ret_warnings = [];
        foreach ($warning_pool as $warning_unit) {
            if (!is_array($warning_unit)
                or empty($warning_unit['warning_msg'])
                or empty($warning_unit['debug_msg'])) {
                continue;
            }

            $ret_warnings[] = ($simple_messages ? $warning_unit['warning_msg'] : $warning_unit['debug_msg']);
        }

        return $ret_warnings;
    }

    //! Return warnings for specified tag or all warnings

    public static function stGetAllWarnings($simple_messages = true)
    {
        return self::getErrorStaticInstance()->getAllWarnings($simple_messages);
    }

    //! Return warnings for specified tag or all warnings

    /**
     *   Return all warnings array
     *
     * @param bool $simple_messages Tells which set of messages to get (simple or debugging)
     *
     * @return array Return array of all warnings
     **/
    public function getAllWarnings($simple_messages = true)
    {
        if (empty($this->warnings_arr)) {
            return [];
        }

        $ret_warnings = [];
        foreach ($this->warnings_arr as $warnings_arr) {
            if (!is_array($warnings_arr)) {
                continue;
            }

            foreach ($warnings_arr as $warning_unit) {
                if (!is_array($warning_unit)
                    or empty($warning_unit['warning_msg'])
                    or empty($warning_unit['debug_msg'])) {
                    continue;
                }

                $ret_warnings[] = ($simple_messages ? $warning_unit['warning_msg'] : $warning_unit['debug_msg']);
            }
        }

        return $ret_warnings;
    }

    public function stChangeErrorMessage($error_msg, $error_debug_msg = '')
    {
        return self::getErrorStaticInstance()->changeErrorMessage($error_msg, $error_debug_msg);
    }

    public function changeErrorMessage($error_msg, $error_debug_msg = '')
    {
        if (empty($error_debug_msg)) {
            $error_debug_msg = $error_msg;
        }

        $this->error_simple_msg = $error_msg;
        $this->error_debug_msg = $error_debug_msg;

        return $this->getError();
    }

    //! \brief Returns function/method call backtrace

    /**
     *   Method returns an array with current error code and message.
     *
     * @return array Array with indexes 'error_no' for error code and 'error_msg' for error message
     **/
    public function getError()
    {
        $return_arr = self::defaultErrorArray();

        $return_arr['error_no'] = $this->error_no;
        $return_arr['error_msg'] = $this->error_msg;
        $return_arr['error_simple_msg'] = $this->error_simple_msg;
        $return_arr['error_debug_msg'] = $this->error_debug_msg;

        if ($this->debuggingMode()) {
            $return_arr['display_error'] = $this->error_debug_msg;
        } else {
            $return_arr['display_error'] = $this->error_simple_msg;
        }

        return $return_arr;
    }

    //! @brief Returns function/method call backtrace. Used for static calls

    public function copyStaticError($force_error_code = false)
    {
        return $this->copyError(self::getErrorStaticInstance(), $force_error_code);
    }

    public function stackAllErrors()
    {
        return array_merge(
            $this->stackError(),
            self::stStackError()
        );
    }

    public function stackError()
    {
        return [
            'instance_error' => $this->getError(),
        ];
    }

    public static function stStackError()
    {
        return [
            'static_error' => self::stGetError(),
        ];
    }

    public static function stGetError()
    {
        return self::getErrorStaticInstance()->getError();
    }

    public function restoreErrors($errors_arr)
    {
        if (!empty($errors_arr['instance_error'])
            and ($instance_errors = self::validateErrorArr($errors_arr['instance_error']))) {
            $this->copyErrorFromArray($instance_errors);
        }

        if (!empty($errors_arr['static_error'])
            and ($static_errors = self::validateErrorArr($errors_arr['static_error']))) {
            self::stCopyErrorFromArray($static_errors);
        }
    }

    /**
     * Copies error set in $error_arr array to current object
     *
     * @param array $error_arr
     *
     * @return bool
     */
    public function copyErrorFromArray($error_arr, $force_error_code = false)
    {
        if (empty($error_arr) or !is_array($error_arr)
            or !isset($error_arr['error_no']) or !isset($error_arr['error_msg'])
            or !isset($error_arr['error_simple_msg']) or !isset($error_arr['error_debug_msg'])) {
            return false;
        }

        $this->error_no = $error_arr['error_no'];
        $this->error_msg = $error_arr['error_msg'];
        $this->error_simple_msg = $error_arr['error_simple_msg'];
        $this->error_debug_msg = $error_arr['error_debug_msg'];

        if ($force_error_code !== false) {
            $this->error_no = $force_error_code;
        }

        return true;
    }

    public static function stCopyErrorFromArray($error_arr, $force_error_code = false)
    {
        return self::getErrorStaticInstance()->copyErrorFromArray($error_arr, $force_error_code);
    }
}
