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

class setEmail
{
    public const MAX_ATTEMPTS = 3;

    public static function run()
    {
        $events = [];

        foreach (self::drain() as $event) {
            $events[] = "window.mktr.buildEvent('set_email', " . \Mktr\Helper\Valid::toJson($event) . ');';
        }

        return implode(PHP_EOL, $events);
    }

    public static function drain()
    {
        $list = \Mktr\Helper\Session::get('set_email');

        if (empty($list) || !is_array($list)) {
            return [];
        }

        $events = [];

        foreach ($list as $key => $entry) {
            $result = self::process($entry);

            if ($result['event'] !== null) {
                $events[] = $result['event'];
            }

            if ($result['entry'] === null) {
                unset($list[$key]);
            } else {
                $list[$key] = $result['entry'];
            }
        }

        \Mktr\Helper\Session::set('set_email', empty($list) ? null : $list);
        \Mktr\Helper\Session::save();

        return $events;
    }

    private static function process($entry)
    {
        $entry = is_array($entry) ? array_values($entry) : [$entry];
        $email = isset($entry[0]) && is_string($entry[0]) ? trim($entry[0]) : '';
        $remove = !empty($entry[1]);
        $phone = isset($entry[2]) && is_string($entry[2]) && $entry[2] !== '' ? $entry[2] : null;
        $attempts = isset($entry[3]) ? (int) $entry[3] : 0;

        if ($email === '' || !\Validate::isEmail($email)) {
            return ['event' => null, 'entry' => null];
        }

        $v = \Mktr\Model\Subscription::getByEmail($email);
        $isOwner = self::isCurrentCustomer($email);
        $event = null;

        if ($attempts === 0) {
            $event = ['email_address' => $email];

            if ($isOwner) {
                if ($v->firstname !== null) {
                    $event['firstname'] = $v->firstname;
                }

                if ($v->lastname !== null) {
                    $event['lastname'] = $v->lastname;
                }
            }

            if ($phone !== null) {
                $validPhone = \Mktr\Helper\Valid::validateTelephone($phone);

                if (!empty($validPhone)) {
                    $event['phone'] = $validPhone;
                }
            }
        }

        $action = null;
        $info = ['email' => $email];

        if ($v->subscribed) {
            $action = 'add_subscriber';
            $name = array_filter([$v->firstname, $v->lastname], function ($part) {
                return $part !== null && $part !== '';
            });
            $info['name'] = implode(' ', $name);
            $subscriberPhone = $v->phone !== null ? $v->phone : $phone;

            if (!empty($subscriberPhone)) {
                $info['phone'] = $subscriberPhone;
            }
        } elseif ($remove && $isOwner) {
            $action = 'remove_subscriber';
        }

        if ($action === null || !\Mktr\Model\Config::rest()) {
            return ['event' => $event, 'entry' => null];
        }

        \Mktr\Helper\Api::send($action, $info);

        if (\Mktr\Helper\Api::getStatus() >= 200 && \Mktr\Helper\Api::getStatus() < 300) {
            return ['event' => $event, 'entry' => null];
        }

        ++$attempts;

        return [
            'event' => $event,
            'entry' => $attempts >= self::MAX_ATTEMPTS ? null : [$email, $remove, $phone, $attempts],
        ];
    }

    private static function isCurrentCustomer($email)
    {
        $customer = \Mktr\Model\Config::getContext()->customer;

        return $customer !== null
            && \Validate::isLoadedObject($customer)
            && $customer->isLogged(true)
            && strcasecmp((string) $customer->email, $email) === 0;
    }
}
