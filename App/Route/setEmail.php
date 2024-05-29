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

class setEmail
{
    public static function run()
    {
        $evList = [
            'set_email' => '__sm__set_email',
            'set_phone' => '__sm__set_phone',
        ];
        $events = [];
        $allGood = true;
        $toClean = [];
        foreach ($evList as $event => $value) {
            $list = \Mktr\Helper\Session::get($event);
            if (!empty($list)) {
                foreach ($list as $ey => $value1) {
                    $remove = false;

                    if (is_array($value1)) {
                        $remove = $value1[1];
                        $value1 = $value1[0];
                    }

                    $v = null;
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
                    }

                    $events[] = "window.mktr.buildEvent('" . $event . "', " . \Mktr\Helper\Valid::toJson($value1) . ');';
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

                        if (\Mktr\Helper\Api::getStatus() != 200) {
                            $allGood = false;
                        }
                    }
                    $toClean[$event][] = $ey;
                }
            }
        }

        if ($allGood) {
            foreach ($toClean as $event => $value) {
                $vv = \Mktr\Helper\Session::get($event);

                foreach ($value as $vd) {
                    unset($vv[$vd]);
                }

                \Mktr\Helper\Session::set($event, $vv);
            }

            \Mktr\Helper\Session::save();
        }

        return implode(PHP_EOL, $events);
    }
}
