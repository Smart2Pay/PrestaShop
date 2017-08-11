<?php
/**
 * 2017 Smart2Pay
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this plugin
 * in the future.
 *
 * @author    Smart2Pay
 * @copyright 2017 Smart2Pay
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 **/

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_0_0()
{
    // Configuration::deleteByName( 'S2P_SITE_ID' );
    // Configuration::deleteByName( 'S2P_SIGNATURE_TEST' );
    // Configuration::deleteByName( 'S2P_SIGNATURE_LIVE' );
    // Configuration::deleteByName( 'S2P_POST_URL_LIVE' );
    // Configuration::deleteByName( 'S2P_POST_URL_TEST' );
    //
    // Db::getInstance()->Execute( 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'smart2pay_method_settings`' );
    // if( !Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_method_settings` (
    //             `id` int(11) NOT NULL AUTO_INCREMENT,
    //             `environment` varchar(50) default NULL,
    //             `method_id` int(11) NOT NULL DEFAULT '0',
    //             `enabled` tinyint(2) NOT NULL DEFAULT '0',
    //             `surcharge_percent` decimal(6,2) NOT NULL DEFAULT '0.00',
    //             `surcharge_amount` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'Amount of surcharge',
    //             `surcharge_currency` varchar(3) DEFAULT NULL COMMENT 'ISO 3 currency code of fixed surcharge amount',
    //             `priority` tinyint(4) NOT NULL DEFAULT '10' COMMENT '1 means first',
    //             `last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
    //             `configured` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
    //             PRIMARY KEY (`id`), KEY `method_id` (`method_id`), KEY `environment` (`environment`), KEY `enabled` (`enabled`)
    //             ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8 COMMENT='Smart2Pay method configurations';
    //     ") )
    //     return false;
    //
    // Db::getInstance()->Execute( "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_method`" );
    // if( !Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_method` (
    //             `id` int(11) NOT NULL AUTO_INCREMENT,
    //             `method_id` int(11) NOT NULL DEFAULT 0,
    //             `environment` varchar(50) default NULL,
    //             `display_name` varchar(255) default NULL,
    //             `description` text ,
    //             `logo_url` varchar(255) default NULL,
    //             `guaranteed` int(1) default 0,
    //             `active` tinyint(2) default 0,
    //             PRIMARY KEY (`id`), KEY `method_id` (`method_id`), KEY `environment` (`environment`), KEY `active` (`active`)
    //         ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8
    //     ") )
    //     return false;

    return true;
}
