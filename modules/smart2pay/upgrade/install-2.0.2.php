<?php
/**
 * 2018 Smart2Pay
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this plugin
 * in the future.
 *
 * @author    Smart2Pay
 * @copyright 2018 Smart2Pay
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 **/

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_0_2( $module )
{
    Configuration::deleteByName( 'S2P_SITE_ID' );
    Configuration::deleteByName( 'S2P_SIGNATURE_TEST' );
    Configuration::deleteByName( 'S2P_SIGNATURE_LIVE' );
    Configuration::deleteByName( 'S2P_POST_URL_LIVE' );
    Configuration::deleteByName( 'S2P_POST_URL_TEST' );

    return true;
}
