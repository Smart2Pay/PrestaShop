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

function upgrade_module_2_0_3( $module )
{
    /** @var Smart2pay $module */

    if( !Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_transactions` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `method_id` int(11) NOT NULL DEFAULT '0',
                `payment_id` int(11) NOT NULL DEFAULT '0',
                `order_id` int(11) NOT NULL DEFAULT '0',
                `site_id` int(11) NOT NULL DEFAULT '0',
                `environment` varchar(20) DEFAULT NULL,
                `extra_data` text,
                `surcharge_amount` decimal(6,2) NOT NULL,
                `surcharge_currency` varchar(3) DEFAULT NULL COMMENT 'Currency ISO 3',
                `surcharge_percent` decimal(6,2) NOT NULL,
                `surcharge_order_amount` decimal(6,2) NOT NULL,
                `surcharge_order_percent` decimal(6,2) NOT NULL,
                `surcharge_order_currency` varchar(3) DEFAULT NULL COMMENT 'Currency ISO 3',
                `payment_status` TINYINT(2) NOT NULL DEFAULT '0' COMMENT 'Status received from server',
                `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                 PRIMARY KEY (`id`), KEY `method_id` (`method_id`), KEY `payment_id` (`payment_id`), KEY `order_id` (`order_id`)
                ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8 COMMENT='Transactions run trough Smart2Pay';

        ") )
    {
        if( $module )
            $module->erros[] = 'Error updating transactions table.';
        return false;
    }

    Db::getInstance()->Execute( 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'smart2pay_logs`' );
    if( !Db::getInstance()->Execute("CREATE TABLE `" . _DB_PREFIX_ . "smart2pay_logs` (
                `log_id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) NOT NULL default '0',
                `log_type` varchar(255) default NULL,
                `log_data` text default NULL,
                `log_source_file` varchar(255) default NULL,
                `log_source_file_line` varchar(255) default NULL,
                `log_created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`log_id`), KEY `order_id` (`order_id`), KEY `log_type` (`log_type`)
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8;
        ") )
    {
        if( $module )
            $module->erros[] = 'Error updating logs table.';
        return false;
    }

    Db::getInstance()->Execute( 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'smart2pay_method_settings`' );
    if( !Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "smart2pay_method_settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `environment` varchar(50) default NULL,
                `method_id` int(11) NOT NULL DEFAULT '0',
                `enabled` tinyint(2) NOT NULL DEFAULT '0',
                `surcharge_percent` decimal(6,2) NOT NULL DEFAULT '0',
                `surcharge_amount` decimal(6,2) NOT NULL DEFAULT '0' COMMENT 'Amount of surcharge',
                `surcharge_currency` varchar(3) DEFAULT NULL COMMENT 'ISO 3 currency code of fixed surcharge amount',
                `priority` tinyint(4) NOT NULL DEFAULT '10' COMMENT '1 means first',
                `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `configured` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), KEY `method_id` (`method_id`), KEY `environment` (`environment`), KEY `enabled` (`enabled`)
                ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8 COMMENT='Smart2Pay method configurations';
        ") )
    {
        if( $module )
            $module->erros[] = 'Error updating methods settings table.';
        return false;
    }

    Db::getInstance()->execute( "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_method`" );
    if( !Db::getInstance()->execute("CREATE TABLE `" . _DB_PREFIX_ . "smart2pay_method` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `method_id` int(11) NOT NULL DEFAULT 0,
                `environment` varchar(50) default NULL,
                `display_name` varchar(255) default NULL,
                `description` text ,
                `logo_url` varchar(255) default NULL,
                `guaranteed` int(1) default 0,
                `active` tinyint(2) default 0,
                PRIMARY KEY (`id`), KEY `method_id` (`method_id`), KEY `environment` (`environment`), KEY `active` (`active`)
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8
        ") )
    {
        if( $module )
            $module->erros[] = 'Error updating methods table.';
        return false;
    }

    Db::getInstance()->execute( "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_country_method`" );
    if( !Db::getInstance()->execute("
            CREATE TABLE `" . _DB_PREFIX_ . "smart2pay_country_method` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `environment` varchar(50) default NULL,
                `country_id` int(11) DEFAULT '0',
                `method_id` int(11) DEFAULT '0',
                `priority` int(2) DEFAULT '99',
                `enabled` tinyint(2) DEFAULT '1' COMMENT 'Tells if country is active for method',
                PRIMARY KEY (`id`), KEY `environment` (`environment`), KEY `country_id` (`country_id`), KEY `method_id` (`method_id`), KEY `enabled` (`enabled`)
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8
        ") )
    {
        if( $module )
            $module->erros[] = 'Error updating country methods table.';
        return false;
    }

    Db::getInstance()->Execute( "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "smart2pay_country`" );
    if( !Db::getInstance()->Execute("
            CREATE TABLE `" . _DB_PREFIX_ . "smart2pay_country` (
                `country_id` int(11) NOT NULL AUTO_INCREMENT,
                `code` varchar(3) default NULL,
                `name` varchar(100) default NULL,
                PRIMARY KEY (`country_id`)
            ) ENGINE="._MYSQL_ENGINE_."  DEFAULT CHARSET=utf8
        ") )
    {
        if( $module )
            $module->erros[] = 'Error updating countries table.';
        return false;
    }

    if( !Db::getInstance()->Execute("
            INSERT INTO `" . _DB_PREFIX_ . "smart2pay_country` (`code`, `name`) VALUES
            ('AD', 'Andorra'),
            ('AE', 'United Arab Emirates'),
            ('AF', 'Afghanistan'),
            ('AG', 'Antigua and Barbuda'),
            ('AI', 'Anguilla'),
            ('AL', 'Albania'),
            ('AM', 'Armenia'),
            ('AN', 'Netherlands Antilles'),
            ('AO', 'Angola'),
            ('AQ', 'Antarctica'),
            ('AR', 'Argentina'),
            ('AS', 'American Samoa'),
            ('AT', 'Austria'),
            ('AU', 'Australia'),
            ('AW', 'Aruba'),
            ('AZ', 'Azerbaijan'),
            ('BA', 'Bosnia & Herzegowina'),
            ('BB', 'Barbados'),
            ('BD', 'Bangladesh'),
            ('BE', 'Belgium'),
            ('BF', 'Burkina Faso'),
            ('BG', 'Bulgaria'),
            ('BH', 'Bahrain'),
            ('BI', 'Burundi'),
            ('BJ', 'Benin'),
            ('BM', 'Bermuda'),
            ('BN', 'Brunei Darussalam'),
            ('BO', 'Bolivia'),
            ('BR', 'Brazil'),
            ('BS', 'Bahamas'),
            ('BT', 'Bhutan'),
            ('BV', 'Bouvet Island'),
            ('BW', 'Botswana'),
            ('BY', 'Belarus (formerly known as Byelorussia)'),
            ('BZ', 'Belize'),
            ('CA', 'Canada'),
            ('CC', 'Cocos (Keeling) Islands'),
            ('CD', 'Congo, Democratic Republic of the (formerly Zalre)'),
            ('CF', 'Central African Republic'),
            ('CG', 'Congo'),
            ('CH', 'Switzerland'),
            ('CI', 'Ivory Coast (Cote d''Ivoire)'),
            ('CK', 'Cook Islands'),
            ('CL', 'Chile'),
            ('CM', 'Cameroon'),
            ('CN', 'China'),
            ('CO', 'Colombia'),
            ('CR', 'Costa Rica'),
            ('CU', 'Cuba'),
            ('CV', 'Cape Verde'),
            ('CX', 'Christmas Island'),
            ('CY', 'Cyprus'),
            ('CZ', 'Czech Republic'),
            ('DE', 'Germany'),
            ('DJ', 'Djibouti'),
            ('DK', 'Denmark'),
            ('DM', 'Dominica'),
            ('DO', 'Dominican Republic'),
            ('DZ', 'Algeria'),
            ('EC', 'Ecuador'),
            ('EE', 'Estonia'),
            ('EG', 'Egypt'),
            ('EH', 'Western Sahara'),
            ('ER', 'Eritrea'),
            ('ES', 'Spain'),
            ('ET', 'Ethiopia'),
            ('FI', 'Finland'),
            ('FJ', 'Fiji Islands'),
            ('FK', 'Falkland Islands (Malvinas)'),
            ('FM', 'Micronesia, Federated States of'),
            ('FO', 'Faroe Islands'),
            ('FR', 'France'),
            ('FX', 'France, Metropolitan'),
            ('GA', 'Gabon'),
            ('GB', 'United Kingdom'),
            ('GD', 'Grenada'),
            ('GE', 'Georgia'),
            ('GF', 'French Guiana'),
            ('GH', 'Ghana'),
            ('GI', 'Gibraltar'),
            ('GL', 'Greenland'),
            ('GM', 'Gambia'),
            ('GN', 'Guinea'),
            ('GP', 'Guadeloupe'),
            ('GQ', 'Equatorial Guinea'),
            ('GR', 'Greece'),
            ('GS', 'South Georgia and the South Sandwich Islands'),
            ('GT', 'Guatemala'),
            ('GU', 'Guam'),
            ('GW', 'Guinea-Bissau'),
            ('GY', 'Guyana'),
            ('HK', 'Hong Kong'),
            ('HM', 'Heard and McDonald Islands'),
            ('HN', 'Honduras'),
            ('HR', 'Croatia (local name: Hrvatska)'),
            ('HT', 'Haiti'),
            ('HU', 'Hungary'),
            ('ID', 'Indonesia'),
            ('IE', 'Ireland'),
            ('IL', 'Israel'),
            ('IN', 'India'),
            ('IO', 'British Indian Ocean Territory'),
            ('IQ', 'Iraq'),
            ('IR', 'Iran, Islamic Republic of'),
            ('IS', 'Iceland'),
            ('IT', 'Italy'),
            ('JM', 'Jamaica'),
            ('JO', 'Jordan'),
            ('JP', 'Japan'),
            ('KE', 'Kenya'),
            ('KG', 'Kyrgyzstan'),
            ('KH', 'Cambodia (formerly Kampuchea)'),
            ('KI', 'Kiribati'),
            ('KM', 'Comoros'),
            ('KN', 'Saint Kitts (Christopher) and Nevis'),
            ('KP', 'Korea, Democratic People''s Republic of (North Korea)'),
            ('KR', 'Korea, Republic of (South Korea)'),
            ('KW', 'Kuwait'),
            ('KY', 'Cayman Islands'),
            ('KZ', 'Kazakhstan'),
            ('LA', 'Lao People''s Democratic Republic (formerly Laos)'),
            ('LB', 'Lebanon'),
            ('LC', 'Saint Lucia'),
            ('LI', 'Liechtenstein'),
            ('LK', 'Sri Lanka'),
            ('LR', 'Liberia'),
            ('LS', 'Lesotho'),
            ('LT', 'Lithuania'),
            ('LU', 'Luxembourg'),
            ('LV', 'Latvia'),
            ('LY', 'Libyan Arab Jamahiriya'),
            ('MA', 'Morocco'),
            ('MC', 'Monaco'),
            ('MD', 'Moldova, Republic of'),
            ('MG', 'Madagascar'),
            ('MH', 'Marshall Islands'),
            ('MK', 'Macedonia, the Former Yugoslav Republic of'),
            ('ML', 'Mali'),
            ('MM', 'Myanmar (formerly Burma)'),
            ('MN', 'Mongolia'),
            ('MO', 'Macao (also spelled Macau)'),
            ('MP', 'Northern Mariana Islands'),
            ('MQ', 'Martinique'),
            ('MR', 'Mauritania'),
            ('MS', 'Montserrat'),
            ('MT', 'Malta'),
            ('MU', 'Mauritius'),
            ('MV', 'Maldives'),
            ('MW', 'Malawi'),
            ('MX', 'Mexico'),
            ('MY', 'Malaysia'),
            ('MZ', 'Mozambique'),
            ('NA', 'Namibia'),
            ('NC', 'New Caledonia'),
            ('NE', 'Niger'),
            ('NF', 'Norfolk Island'),
            ('NG', 'Nigeria'),
            ('NI', 'Nicaragua'),
            ('NL', 'Netherlands'),
            ('NO', 'Norway'),
            ('NP', 'Nepal'),
            ('NR', 'Nauru'),
            ('NU', 'Niue'),
            ('NZ', 'New Zealand'),
            ('OM', 'Oman'),
            ('PA', 'Panama'),
            ('PE', 'Peru'),
            ('PF', 'French Polynesia'),
            ('PG', 'Papua New Guinea'),
            ('PH', 'Philippines'),
            ('PK', 'Pakistan'),
            ('PL', 'Poland'),
            ('PM', 'St Pierre and Miquelon'),
            ('PN', 'Pitcairn Island'),
            ('PR', 'Puerto Rico'),
            ('PT', 'Portugal'),
            ('PW', 'Palau'),
            ('PY', 'Paraguay'),
            ('QA', 'Qatar'),
            ('RE', 'Reunion'),
            ('RO', 'Romania'),
            ('RU', 'Russian Federation'),
            ('RW', 'Rwanda'),
            ('SA', 'Saudi Arabia'),
            ('SB', 'Solomon Islands'),
            ('SC', 'Seychelles'),
            ('SD', 'Sudan'),
            ('SE', 'Sweden'),
            ('SG', 'Singapore'),
            ('SH', 'St Helena'),
            ('SI', 'Slovenia'),
            ('SJ', 'Svalbard and Jan Mayen Islands'),
            ('SK', 'Slovakia'),
            ('SL', 'Sierra Leone'),
            ('SM', 'San Marino'),
            ('SN', 'Senegal'),
            ('SO', 'Somalia'),
            ('SR', 'Suriname'),
            ('ST', 'Sco Tom'),
            ('SU', 'Union of Soviet Socialist Republics'),
            ('SV', 'El Salvador'),
            ('SY', 'Syrian Arab Republic'),
            ('SZ', 'Swaziland'),
            ('TC', 'Turks and Caicos Islands'),
            ('TD', 'Chad'),
            ('TF', 'French Southern and Antarctic Territories'),
            ('TG', 'Togo'),
            ('TH', 'Thailand'),
            ('TJ', 'Tajikistan'),
            ('TK', 'Tokelau'),
            ('TM', 'Turkmenistan'),
            ('TN', 'Tunisia'),
            ('TO', 'Tonga'),
            ('TP', 'East Timor'),
            ('TR', 'Turkey'),
            ('TT', 'Trinidad and Tobago'),
            ('TV', 'Tuvalu'),
            ('TW', 'Taiwan, Province of China'),
            ('TZ', 'Tanzania, United Republic of'),
            ('UA', 'Ukraine'),
            ('UG', 'Uganda'),
            ('UM', 'United States Minor Outlying Islands'),
            ('US', 'United States of America'),
            ('UY', 'Uruguay'),
            ('UZ', 'Uzbekistan'),
            ('VA', 'Holy See (Vatican City State)'),
            ('VC', 'Saint Vincent and the Grenadines'),
            ('VE', 'Venezuela'),
            ('VG', 'Virgin Islands (British)'),
            ('VI', 'Virgin Islands (US)'),
            ('VN', 'Viet Nam'),
            ('VU', 'Vanautu'),
            ('WF', 'Wallis and Futuna Islands'),
            ('WS', 'Samoa'),
            ('XO', 'West Africa'),
            ('YE', 'Yemen'),
            ('YT', 'Mayotte'),
            ('ZA', 'South Africa'),
            ('ZM', 'Zambia'),
            ('ZW', 'Zimbabwe'),
            ('PS', 'Palestinian Territory'),
            ('ME', 'Montenegro'),
            ('RS', 'Serbia');" ) )
    {
        if( $module )
            $module->erros[] = 'Error populating countries table.';
        return false;
    }

    return $module;
}
