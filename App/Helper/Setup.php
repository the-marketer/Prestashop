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
 *
 * @project     TheMarketer.com
 *
 * @website     https://themarketer.com/
 *
 * @docs        https://themarketer.com/resources/api
 **/

namespace Mktr\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Setup
{
    const TABS = [
        'Mktr' => [
            'name' => 'TheMarketer',
            'ico' => 'trending_up',
        ],
        'MktrTracker' => [
            'name' => 'TheMarketer - Tracker',
            'ico' => 'trending_up',
        ],
        'MktrGoogle' => [
            'name' => 'TheMarketer - Google',
            'ico' => 'analytics',
        ],
    ];

    private static function getTabId($className)
    {
        return (int) \Tab::getIdFromClassName($className);
    }

    public static function AddTabs()
    {
        $parent = null;
        $languages = \Language::getLanguages(false);
        $mktr = null;

        foreach (self::TABS as $key => $value) {
            if (self::getTabId($key) === 0) {
                $tab = new \Tab();
                $tab->class_name = $key;
                $tab->module = 'mktr';
                $tab->active = true;
                foreach ($languages as $language) {
                    $tab->name[$language['id_lang']] = $value['name'];
                }
                if (_PS_VERSION_ >= 1.7) {
                    $tab->icon = $value['ico'];
                    $tab->wording = $value['name'];
                    $tab->wording_domain = 'Modules.Mktr.Admin';
                }
                if ($key !== 'Mktr' && $parent === null) {
                    $parent = self::getTabId('Mktr');
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

        self::ensureStorageDirectories();

        \Mktr\Model\Config::AddDefault();

        $data = \Mktr\Model\Config::nws();
        /* @phpstan-ignore-next-line */
        \Mktr\Model\Config::setConfig('MKTR_TRACKER_CONFIRMATION', \Mktr\Model\Config::getConfig($data['CONFIRMATION']));
        /* @phpstan-ignore-next-line */
        \Mktr\Model\Config::setConfig('MKTR_TRACKER_NOTIFICATION', \Mktr\Model\Config::getConfig($data['NOTIFICATION']));
    }

    public static function ensureStorageDirectories()
    {
        $baseStorage = MKTR_APP . 'Storage/';
        if (!is_dir($baseStorage)) {
            @mkdir($baseStorage, 0755, true);
        }

        if (\Shop::isFeatureActive()) {
            $shops = \Shop::getShops(true, null, true);
            foreach ($shops as $shopId) {
                $shopDir = $baseStorage . (int) $shopId . '/';
                if (!is_dir($shopDir)) {
                    @mkdir($shopDir, 0755, true);
                }

                if (!file_exists($shopDir . 'index.php')) {
                    @file_put_contents($shopDir . 'index.php', "<?php\nheader('Expires: Mon, 26 Jul 1997 05:00:00 GMT');\nheader('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');\n\nheader('Cache-Control: no-store, no-cache, must-revalidate');\nheader('Cache-Control: post-check=0, pre-check=0', false);\nheader('Pragma: no-cache');\n\nheader('Location: ../../../');\nexit;\n");
                }
            }
        }
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
            $id_tab = self::getTabId($key);
            if ($id_tab) {
                $tab = new \Tab($id_tab);
                $tab->delete();
            }
        }

        self::cleanStorageDirectories();
        self::cleanJsFiles();

        $data = \Mktr\Model\Config::nws();
        /* @phpstan-ignore-next-line */
        \Mktr\Model\Config::setConfig($data['CONFIRMATION'], \Mktr\Model\Config::getConfig('MKTR_TRACKER_CONFIRMATION'));
        /* @phpstan-ignore-next-line */
        \Mktr\Model\Config::setConfig($data['NOTIFICATION'], \Mktr\Model\Config::getConfig('MKTR_TRACKER_NOTIFICATION'));

        /* must be after MKTR_TRACKER_CONFIRMATION And MKTR_TRACKER_NOTIFICATION * */
        \Mktr\Model\Config::delete();
    }

    private static function cleanStorageDirectories()
    {
        $baseStorage = MKTR_APP . 'Storage/';
        if (is_dir($baseStorage)) {
            $dirs = glob($baseStorage . '[0-9]*', GLOB_ONLYDIR);
            if ($dirs) {
                foreach ($dirs as $dir) {
                    $files = glob($dir . '/*');
                    if ($files) {
                        foreach ($files as $file) {
                            if (is_file($file)) {
                                @unlink($file);
                            }
                        }
                    }
                    @rmdir($dir);
                }
            }
        }
    }

    private static function cleanJsFiles()
    {
        $jsFiles = glob(MKTR_APP . 'mktr.*.js');
        if ($jsFiles) {
            foreach ($jsFiles as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
}
