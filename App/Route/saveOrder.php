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

class saveOrder
{
    public static function run()
    {
        $events = [''];
        $Order = \Mktr\Helper\Session::get('save_order');
        $allGood = true;
        $toClean = [];

        if (!empty($Order)) {
            foreach ($Order as $key => $sOrderData) {
                if (!is_array($sOrderData) || !array_key_exists('is_order', $sOrderData)) {
                    $toClean[] = $key;
                    continue;
                }

                if ($sOrderData['is_order'] == false) {
                    if (method_exists('\Order', 'getIdByCartId')) {
                        $sOrderData['id'] = \Order::getIdByCartId($sOrderData['id']);
                    }

                    if (empty($sOrderData['id'])) {
                        // The order does not exist yet - an async payment callback
                        // may still be on its way. Keep it queued until it expires
                        // instead of dropping the whole queue.
                        if (GetEvents::isExpired($sOrderData)) {
                            $toClean[] = $key;
                        }

                        $allGood = false;
                        continue;
                    }
                }

                if (\Mktr\Model\Orders::push($sOrderData['id'])) {
                    $toClean[] = $key;
                } else {
                    $allGood = false;

                    if (GetEvents::isExpired($sOrderData)) {
                        $toClean[] = $key;
                    }
                }
            }

            if (!empty($toClean)) {
                foreach ($toClean as $key) {
                    unset($Order[$key]);
                }

                \Mktr\Helper\Session::set('save_order', $Order);
                \Mktr\Helper\Session::save();
            }
        }

        return 'console.log(' . (int) $allGood . ',' . json_encode(\Mktr\Helper\Api::getInfo(), JSON_PRETTY_PRINT) . ');' . implode('
', $events);
    }
}
