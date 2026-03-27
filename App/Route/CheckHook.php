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

namespace Mktr\Route;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CheckHook
{
    public static function run()
    {
        if (\Mktr\Helper\Valid::getParam('show_log', false) !== false) {
            return file_get_contents(MKTR_APP . 'Storage/install.log');
        }

        if (!\Mktr::$init) {
            return ['error' => 'Please contact TheMarketer'];
        }

        $obj = \Mktr::i();
        $moduleId = (int) $obj->id;

        if ($moduleId <= 0) {
            return ['error' => 'Module not installed'];
        }

        $hooks = \Mktr::getRequiredHooks();

        $hookIds = [];
        foreach ($hooks as $hookName) {
            $idHook = (int) \Hook::getIdByName($hookName);
            if ($idHook > 0) {
                $hookIds[$hookName] = $idHook;
            }
        }

        if (\Shop::isFeatureActive()) {
            return self::healAllShops($obj, $moduleId, $hooks, $hookIds);
        }

        return self::healCurrentShop($obj, $hooks, $hookIds);
    }

    private static function healCurrentShop($obj, array $hooks, array $hookIds)
    {
        $registered = [];
        $existed = [];

        foreach ($hooks as $hookName) {
            if (!isset($hookIds[$hookName])) {
                continue;
            }
            if ($obj->isRegisteredInHook($hookName)) {
                $existed[] = $hookName;
            } else {
                if ($obj->registerHook($hookName)) {
                    $registered[] = $hookName;
                }
            }
        }

        return [
            'status' => 'done',
            'registered' => $registered,
            'existed' => $existed,
        ];
    }

    private static function healAllShops($obj, $moduleId, array $hooks, array $hookIds)
    {
        $db = \Db::getInstance();
        $shops = \Shop::getShops(true, null, true);
        $result = ['status' => 'done', 'shops' => []];

        foreach ($shops as $shopId) {
            $shopId = (int) $shopId;
            $shopResult = [
                'module_shop_fixed' => false,
                'enable_device_fixed' => false,
                'hooks_registered' => [],
                'hooks_existed' => [],
                'js_generated' => false,
            ];

            $moduleShopExists = (int) $db->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'module_shop` ' .
                'WHERE `id_module` = ' . $moduleId . ' AND `id_shop` = ' . $shopId
            );

            if (!$moduleShopExists) {
                $db->insert('module_shop', [
                    'id_module' => $moduleId,
                    'id_shop' => $shopId,
                    'enable_device' => 7,
                ]);
                $shopResult['module_shop_fixed'] = true;
            }

            $enableDevice = (int) $db->getValue(
                'SELECT `enable_device` FROM `' . _DB_PREFIX_ . 'module_shop` ' .
                'WHERE `id_module` = ' . $moduleId . ' AND `id_shop` = ' . $shopId
            );

            if ($enableDevice !== 7) {
                $db->update(
                    'module_shop',
                    ['enable_device' => 7],
                    'id_module = ' . $moduleId . ' AND id_shop = ' . $shopId
                );
                $shopResult['enable_device_fixed'] = true;
            }

            foreach ($hookIds as $hookName => $idHook) {
                $exists = (int) $db->getValue(
                    'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'hook_module` ' .
                    'WHERE `id_module` = ' . $moduleId .
                    ' AND `id_hook` = ' . $idHook .
                    ' AND `id_shop` = ' . $shopId
                );

                if ($exists) {
                    $shopResult['hooks_existed'][] = $hookName;
                } else {
                    $position = (int) $db->getValue(
                        'SELECT COALESCE(MAX(`position`), 0) FROM `' . _DB_PREFIX_ . 'hook_module` ' .
                        'WHERE `id_hook` = ' . $idHook . ' AND `id_shop` = ' . $shopId
                    );

                    $db->insert('hook_module', [
                        'id_module' => $moduleId,
                        'id_hook' => $idHook,
                        'id_shop' => $shopId,
                        'position' => $position + 1,
                    ]);
                    $shopResult['hooks_registered'][] = $hookName;
                }
            }

            try {
                $originalShop = \Mktr\Model\Config::shop();
                \Mktr\Model\Config::setShop($shopId);
                \Mktr\Model\Config::i(true);

                if (\Mktr\Model\Config::showJs(true)) {
                    $jsPrefix = \Mktr\Model\Config::getJsPrefix();
                    $jsFile = \Mktr\Model\Config::i()->js_file;

                    if ($jsFile === '' || !file_exists(MKTR_APP . $jsPrefix . $jsFile . '.js')) {
                        \Mktr\Route\refreshJS::resetConfig();
                        \Mktr\Route\refreshJS::loadJs();
                        $shopResult['js_generated'] = true;
                    }
                }

                \Mktr\Model\Config::setShop($originalShop);
                \Mktr\Model\Config::i(true);
                \Mktr\Model\Config::showJs(true);
            } catch (\Exception $e) {
                $shopResult['js_error'] = $e->getMessage();
            }

            \Configuration::updateValue('MKTR_HOOKS_OK', \Mktr::i()->version, false, null, $shopId);

            $result['shops'][$shopId] = $shopResult;
        }

        return $result;
    }
}
