<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @docs        https://themarketer.com/resources/api
 **/

namespace Mktr\Helper;

class Setup
{
    const TABS = [
        'Mktr' => [
            'name' => 'TheMarketer',
            'ico' => null,
        ],
        'MktrTracker' => [
            'name' => 'TheMarketer - Tracker',
            'ico' => 'mktr',
        ],
        'MktrGoogle' => [
            'name' => 'TheMarketer - Google',
            'ico' => 'mktr',
        ],
    ];

    public static function AddTabs()
    {
        $parent = null;
        $lang = (int) \Configuration::get('PS_LANG_DEFAULT');
        $mktr = null;

        foreach (self::TABS as $key => $value) {
            if ((int) \Tab::getIdFromClassName($key) === 0) {
                $tab = new \Tab();
                $tab->class_name = $key;
                $tab->module = 'mktr';
                $tab->active = 1;
                $tab->name[$lang] = $value['name'];
                if (_PS_VERSION_ >= 1.7) {
                    $tab->icon = $value['ico'];
                    $tab->wording = 'Mktr';
                    $tab->wording_domain = 'Admin.Navigation.Menu';
                }
                if ($key !== 'Mktr' && $parent === null) {
                    $parent = (int) \Tab::getIdFromClassName('Mktr');
                }
                $tab->id_parent = $key === 'Mktr' ? 0 : $parent;
                $tab->add();
                $tab->position = $key === 'Mktr' ? 1 : 2;
                $tab->save();
            }
        }
    }

    public static function install()
    {
        $sql = [];

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mktr` (
            `uid` varchar(50) NOT NULL,
            `data` longtext, 
            `expire` datetime,
            PRIMARY KEY  (uid)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        foreach ($sql as $query) {
            if (\Mktr\Model\Config::db()->execute($query) == false) {
                return false;
            }
        }

        self::AddTabs();

        \Mktr\Model\Config::AddDefault();

        $data = \Mktr\Model\Config::nws();

        \Mktr\Model\Config::setConfig('MKTR_TRACKER_CONFIRMATION', \Mktr\Model\Config::getConfig($data['CONFIRMATION']));
        \Mktr\Model\Config::setConfig('MKTR_TRACKER_NOTIFICATION', \Mktr\Model\Config::getConfig($data['NOTIFICATION']));
    }

    public static function uninstall()
    {
        $sql = [
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mktr`;',
        ];

        foreach ($sql as $query) {
            if (\Mktr\Model\Config::db()->execute($query) == false) {
                return false;
            }
        }

        foreach (self::TABS as $key => $value) {
            $id_tab = (int) \Tab::getIdFromClassName($key);
            if ($id_tab) {
                $tab = new \Tab($id_tab);
                $tab->delete();
            }
        }

        $data = \Mktr\Model\Config::nws();

        \Mktr\Model\Config::setConfig($data['CONFIRMATION'], \Mktr\Model\Config::getConfig('MKTR_TRACKER_CONFIRMATION'));
        \Mktr\Model\Config::setConfig($data['NOTIFICATION'], \Mktr\Model\Config::getConfig('MKTR_TRACKER_NOTIFICATION'));

        /* must be after MKTR_TRACKER_CONFIRMATION And MKTR_TRACKER_NOTIFICATION * */
        \Mktr\Model\Config::delete();
    }
}
