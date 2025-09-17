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
        if (\Mktr\Helper\Valid::getParam('show_log', false) === false) {
            if (!\Mktr::$init) {
                return ['error' => 'Please contact TheMarketer'];
            }
            $obj = \Mktr::i();

            if (_PS_VERSION_ >= 1.6) {
                $hook = [
                    /* Front */
                    'Header',
                    'displayHeader',
                    'moduleRoutes',
                    'displayFooterAfter',
                    'displayFooterBefore',
                    'actionDispatcher',
                    'actionDispatcherBefore',
                    'actionControllerInitBefore',
                    /* Admin */
                    'displayBackOfficeHeader',
                    'actionOrderStatusUpdate',
                ];
            } else {
                $hook = [
                    /* Front */
                    'displayHeader',
                    'moduleRoutes',
                    'actionDispatcher',
                    'actionDispatcherBefore',
                    'actionControllerInitBefore',
                    /* Admin */
                    'displayBackOfficeHeader',
                    'actionOrderStatusUpdate',
                ];
            }

            if (_PS_VERSION_ >= 1.7) {
                $hook[] = 'displayBeforeBodyClosingTag';
            } else {
                $hook[] = 'displayFooter';
            }

            if (_PS_VERSION_ >= 1.6) {
                foreach ($hook as $kk => $vv) {
                    if (!(\Hook::getIdByName($vv) > 0)) {
                        unset($hook[$kk]);
                        file_put_contents(MKTR_APP . 'Storage/install.log', date('Y-m-d H:i:s') . '[NOT_FOUND] ' . $obj->gFile(__FILE__) . ' - Line ' . __LINE__ . ' [' . $vv . "]\n", FILE_APPEND);
                    }
                }
                $exist = [];
                foreach ($hook as $kk => $vv) {
                    if ($obj->isRegisteredInHook($vv)) {
                        $exist[] = $vv;
                        unset($hook[$kk]);
                        file_put_contents(MKTR_APP . 'Storage/install.log', date('Y-m-d H:i:s') . '[EXIST] ' . $obj->gFile(__FILE__) . ' - Line ' . __LINE__ . ' [' . $vv . "]\n", FILE_APPEND);
                    }
                }
                if (in_array('displayHeader', $hook) && in_array('Header', $exist)) {
                    $key = array_search('displayHeader', $hook);
                    if ($key !== false) {
                        unset($hook[$key]);
                    }
                }

                if ($obj->registerHook($hook)) {
                    return ['status' => 'done', 'hooks' => $hook];
                } else {
                    return ['status' => 'There was an error during registerHook procces.', 'hooks' => $hook];
                }
            } else {
                $send = ['status' => 'done'];
                $message = [];

                foreach ($hook as $kk => $vv) {
                    if ($obj->isRegisteredInHook($vv)) {
                        unset($hook[$kk]);
                    }
                }

                foreach ($hook as $kk => $vv) {
                    if (\Hook::getIdByName($vv) > 0) {
                        if (!$obj->registerHook($vv)) {
                            $message[] = 'There was an error during registerHook procces. [' . $vv . ']';
                        }
                    } else {
                        file_put_contents(MKTR_APP . 'Storage/install.log', date('Y-m-d H:i:s') . '[NOT_FOUND] ' . $obj->gFile(__FILE__) . ' - Line ' . __LINE__ . ' [' . $vv . "]\n", FILE_APPEND);
                    }
                }
                $send['hooks'] = $hook;
                if (!empty($message)) {
                    $send['error'] = $message;
                }

                return $send;
            }

        } else {
            return file_get_contents(MKTR_APP . 'Storage/install.log');
        }
    }
}
