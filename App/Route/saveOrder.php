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

namespace Mktr\Route;

class saveOrder
{
    private static $try = 0;

    public static function run()
    {
        $events = [''];
        $Order = \Mktr\Helper\Session::get('save_order');
        $allGood = true;

        if (!empty($Order)) {
            foreach ($Order as $sOrderData) {
                if (array_key_exists('is_order', $sOrderData) && $sOrderData['is_order'] == false) {
                    if (is_array($value1) && method_exists('\Order', 'getIdByCartId')) {
                        $sOrderData['id'] = \Order::getIdByCartId($sOrderData['id']);
                    } elseif (method_exists('\Order', 'getOrderByCartId')) {
                        $sOrderData['id'] = \Order::getOrderByCartId($sOrderData['id']);
                    }
                    if ($sOrderData['id'] == false) {
                        \Mktr\Helper\Session::set('save_order', []);
                        \Mktr\Helper\Session::save();

                        return 'console.log("Clean");';
                    }
                } elseif (!array_key_exists('is_order', $sOrderData)) {
                    \Mktr\Helper\Session::set('save_order', []);
                    \Mktr\Helper\Session::save();

                    return 'console.log("OLD VERSION");';
                }

                $temp = \Mktr\Model\Orders::getByID($sOrderData['id']);
                $sOrder = $temp->toApi();

                if (empty($temp->getProducts())) {
                    ++self::$try;
                    sleep(2);
                    if (self::$try < 5) {
                        return self::run();
                    }

                    return 'console.log("Empty Products");';
                }

                \Mktr\Helper\Api::send('save_order', $sOrder);
                if (\Mktr\Helper\Api::getStatus() != 200) {
                    $allGood = false;
                }

                if (!empty($sOrder['email_address'])) {
                    $v = \Mktr\Model\Subscription::getByEmail($sOrder['email_address']);
                    if ($v->subscribed) {
                        $info = [
                            'email' => $v->email_address,
                        ];
                        $name = [];

                        if ($v->firstname !== null) {
                            $name[] = $v->firstname;
                        }

                        if ($v->lastname !== null) {
                            $name[] = $v->lastname;
                        }

                        $info['name'] = implode(' ', $name);

                        if ($v->phone !== null) {
                            $info['phone'] = $v->phone;
                        }

                        \Mktr\Helper\Api::send('add_subscriber', $info);

                        if (\Mktr\Helper\Api::getStatus() != 200) {
                            $allGood = false;
                        }
                    }
                }
            }

            if ($allGood) {
                \Mktr\Helper\Session::set('save_order', []);
                \Mktr\Helper\Session::save();
            }
        }

        return 'console.log(' . (int) $allGood . ',' . json_encode(\Mktr\Helper\Api::getInfo(), true) . ');' . implode('
', $events);
    }
}
