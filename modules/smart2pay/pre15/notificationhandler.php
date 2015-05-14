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

include( dirname( __FILE__ ) . '/../../../config/config.inc.php' );
include( dirname( __FILE__ ) . '/../smart2pay.php' );

$smart2pay = new Smart2pay();

$smart2pay->prepare_notification();

exit;
