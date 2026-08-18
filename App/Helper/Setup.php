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
            'ico' => '',
        ],
        'MktrTracker' => [
            'name' => 'TheMarketer - Tracker',
            'ico' => '',
        ],
        'MktrGoogle' => [
            'name' => 'TheMarketer - Google',
            'ico' => '',
        ],
    ];

    private static function getTabId($className)
    {
        return (int) \Tab::getIdFromClassName($className);
    }

    public static function AddTabs()
    {
        $parent = null;
        $lang = (int) \Configuration::get('PS_LANG_DEFAULT');
        $mktr = null;

        foreach (self::TABS as $key => $value) {
            if (self::getTabId($key) === 0) {
                $tab = new \Tab();
                $tab->class_name = $key;
                $tab->module = 'mktr';
                $tab->active = true;
                $tab->name[$lang] = $value['name'];
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

    /**
     * One row per order, so the shop can answer "did this order reach
     * TheMarketer" instead of only "how far did the sweeper get".
     *
     * @return string
     */
    public static function orderSyncTable()
    {
        // id_order is auto increment across the whole installation, so it is
        // the key on its own. Everything else about the order, the shop it
        // belongs to included, stays in the orders table.
        //
        // The index leads on `sent` and carries `id_order` so the sweeper's
        // "oldest pending first" reads straight off the index. Putting
        // `attempts` in between would turn the ordering into a filesort,
        // because it is matched as a range rather than a constant.
        return 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mktr_order_sync` (
            `id_order` int(10) unsigned NOT NULL,
            `sent` tinyint(1) unsigned NOT NULL DEFAULT 0,
            `attempts` int(10) unsigned NOT NULL DEFAULT 0,
            `last_error` varchar(255) DEFAULT NULL,
            `date_add` datetime NOT NULL,
            `date_sent` datetime DEFAULT NULL,
            `date_next_try` datetime DEFAULT NULL,
            PRIMARY KEY (`id_order`),
            KEY `mktr_pending` (`sent`, `id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';
    }

    /**
     * Brings a table created by an earlier build of this version up to date.
     * CREATE TABLE IF NOT EXISTS leaves an existing table alone, so the column
     * added after the first 1.1.6 packages went out has to be filled in here.
     *
     * @return bool
     */
    public static function orderSyncColumns()
    {
        $columns = \Mktr\Model\Config::db()->executeS(
            'SHOW COLUMNS FROM `' . _DB_PREFIX_ . "mktr_order_sync` LIKE 'date_next_try'"
        );

        if (!empty($columns)) {
            return true;
        }

        return (bool) \Mktr\Model\Config::db()->execute(
            'ALTER TABLE `' . _DB_PREFIX_ . 'mktr_order_sync`' .
            ' ADD `date_next_try` datetime DEFAULT NULL'
        );
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

        $sql[] = self::orderSyncTable();

        foreach ($sql as $query) {
            if (\Mktr\Model\Config::db()->execute($query) == false) {
                return false;
            }
        }

        if (!self::orderSyncColumns()) {
            return false;
        }

        self::AddTabs();

        self::ensureStorageDirectories();

        \Mktr\Model\Config::AddDefault();
        \Mktr\Model\Config::cronToken();

        $data = \Mktr\Model\Config::nws();
        /* @phpstan-ignore-next-line */
        \Mktr\Model\Config::setConfig('MKTR_TRACKER_CONFIRMATION', \Mktr\Model\Config::getConfig($data['CONFIRMATION']));
        /* @phpstan-ignore-next-line */
        \Mktr\Model\Config::setConfig('MKTR_TRACKER_NOTIFICATION', \Mktr\Model\Config::getConfig($data['NOTIFICATION']));

        return true;
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
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mktr_order_sync`;',
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
