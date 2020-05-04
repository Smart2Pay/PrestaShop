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
     * PrestaShop 1.4 payment notification script
     **/
    $useSSL = true;

if (@file_exists(dirname(__FILE__) . '/../../../config/config.inc.php')) {
    $root_path = dirname(__FILE__) . '/../../../';
} elseif (!empty($_SERVER['SCRIPT_FILENAME'])
    and @file_exists(
        realpath(dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME']))))) . '/config/config.inc.php'
    )
) {
    $root_path = realpath(dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME']))))) . '/';
} else {
    echo 'Cannot find main configuration file...';
    exit;
}

    include $root_path . 'config/config.inc.php';
    include _PS_MODULE_DIR_ . 'smart2pay/smart2pay.php';

    $smart2pay = new Smart2pay();

    $smart2pay->prepareNotification();

    exit;
