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

class GetEvents
{
    public static function run()
    {
        $evList = [
            'add_to_cart' => '__sm__add_to_cart',
            'remove_from_cart' => '__sm__remove_from_cart',
            'add_to_wish_list' => '__sm__add_to_wishlist',
            'remove_from_wishlist' => '__sm__remove_from_wishlist',
            'save_order' => '__sm__order',
            'set_email' => '__sm__set_email',
            'set_phone' => '__sm__set_phone',
        ];
        $events = [];

        $data = \Mktr\Helper\Session::data();

        $toClean = [];
        foreach ($evList as $event => $value) {
            $list = \Mktr\Helper\Session::get($event);
            if (!empty($list)) {
                foreach ($list as $key => $value1) {
                    if ($event === 'save_order') {
                        if (is_array($value1) && array_key_exists('is_order', $value1) && $value1['is_order'] == false) {
                            if (method_exists('\Order', 'getIdByCartId')) {
                                $value1['id'] = \Order::getIdByCartId($value1['id']);
                            } elseif (method_exists('\Order', 'getOrderByCartId')) {
                                $value1['id'] = \Order::getOrderByCartId($value1['id']);
                            }
                            if ($value1['id'] == false) {
                                $toClean[] = $key;
                                continue;
                            }
                        } elseif (!is_array($value1) || !array_key_exists('is_order', $value1)) {
                            $toClean[] = $key;
                            continue;
                        }

                        $temp = \Mktr\Model\Orders::getByID($value1['id']);
                        $sOrder = $temp->toApi();

                        if (!empty($temp->getProducts())) {
                            $events[] = [$event, $temp->toEvent()];
                            \Mktr\Helper\Api::send('save_order', $sOrder);

                            if (\Mktr\Helper\Api::getStatus() == 200) {
                                $toClean[] = $key;
                            }

                            if (!empty($sOrder['email_address'])) {
                                $v = \Mktr\Model\Subscription::getByEmail($sOrder['email_address']);
                                if ($v->subscribed) {
                                    $info = ['email' => $v->email_address];
                                    $name = [];
                                    if ($v->firstname !== null) {
                                        $name[] = $v->firstname;
                                    }
                                    if ($v->lastname !== null) {
                                        $name[] = $v->lastname;
                                    }
                                    if ($v->phone !== null) {
                                        $info['phone'] = $v->phone;
                                    }
                                    $info['name'] = implode(' ', $name);
                                    \Mktr\Helper\Api::send('add_subscriber', $info);
                                }
                            }
                        }
                    } elseif (in_array($event, ['set_email', 'set_phone'])) {
                        $v = null;
                        $remove = false;

                        if (is_array($value1)) {
                            $remove = $value1[1];
                            $value1 = $value1[0];
                        }

                        if ($event === 'set_email') {
                            $v = \Mktr\Model\Subscription::getByEmail($value1);
                            $value1 = [
                                'email_address' => $value1,
                            ];

                            if ($v !== null) {
                                if ($v->firstname !== null) {
                                    $value1['firstname'] = $v->firstname;
                                }

                                if ($v->lastname !== null) {
                                    $value1['lastname'] = $v->lastname;
                                }
                            }
                        } elseif ($event === 'set_phone') {
                            $value1 = [
                                'phone' => \Mktr\Helper\Valid::validateTelephone($value1),
                            ];
                            $toClean[] = $key;
                        }

                        $events[] = [$event, $value1];

                        if ($event === 'set_email') {
                            $info = [
                                'email' => $v->email_address,
                            ];

                            if ($v->subscribed) {
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
                            } elseif ($remove) {
                                \Mktr\Helper\Api::send('remove_subscriber', $info);
                            }

                            if (\Mktr\Helper\Api::getStatus() == 200) {
                                $toClean[] = $key;
                            }
                        }
                    } else {
                        $pId = $value1[0];
                        $pAttr = $value1[1];

                        $pp = \Mktr\Model\Product::getByID($pId, true);
                        $variant = $pp->getVariant($pAttr);

                        $add = [
                            'product_id' => $pId,
                            'variation' => $variant,
                        ];

                        if (in_array($event, ['add_to_cart', 'remove_from_cart'])) {
                            $add['qty'] = $value1[2];
                        }

                        $value1 = $add;

                        $events[] = [$event, $value1];
                        $toClean[] = $key;
                    }
                }

                $vv = \Mktr\Helper\Session::get($event);

                foreach ($toClean as $vd) {
                    unset($vv[$vd]);
                }

                \Mktr\Helper\Session::set($event, $vv);
            }
        }

        \Mktr\Helper\Session::save();

        return $events;
    }
}
