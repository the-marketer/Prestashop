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

use Mktr\Model\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Api
{
    private static $init;

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }

        return self::$init;
    }

    private static $mURL = 'https://t.themarketer.com/api/v1/';
    // private static $mURL = "https://eaxdev.ga/mktr/EventsTrap/";
    private static $bURL = 'https://eaxdev.ga/mktr/BugTrap/';

    private static $timeOut;

    private static $cURL;

    private static $params;
    private static $lastUrl;

    private static $info;
    private static $exec;
    private static $requestType;

    /** @noinspection PhpUnused */
    public static function send($name, $data = [], $post = true)
    {
        return self::REST(self::$mURL . $name, $data, $post);
    }

    /** @noinspection PhpUnused */
    public static function debug($data = [], $post = true)
    {
        return self::REST(self::$bURL, $data, $post);
    }

    /** @noinspection PhpUnused */
    public static function getParam()
    {
        return self::$params;
    }

    /** @noinspection PhpUnused */
    public static function getUrl()
    {
        return self::$lastUrl;
    }

    /** @noinspection PhpUnused */
    public static function getStatus()
    {
        return self::$info['http_code'];
    }

    /** @noinspection PhpUnused */
    public static function getInfo()
    {
        return self::$info;
    }

    /** @noinspection PhpUnused */
    public static function getContent()
    {
        return self::$exec;
    }

    public static function getBody()
    {
        return self::$exec;
    }

    public static function REST($url, $data = [], $post = true)
    {
        try {
            if (!Config::rest()) {
                return false;
            }

            if (self::$timeOut == null) {
                self::$timeOut = 1;
            }

            self::$params = array_merge([
                'k' => Config::i()->rest_key,
                'u' => Config::i()->customer_id,
            ], $data);

            self::$requestType = $post;

            if (self::$requestType) {
                self::$lastUrl = $url;
            } else {
                self::$lastUrl = $url . '?' . http_build_query(self::$params);
            }

            self::$cURL = \curl_init();

            \curl_setopt(self::$cURL, CURLOPT_CONNECTTIMEOUT, self::$timeOut);
            \curl_setopt(self::$cURL, CURLOPT_TIMEOUT, self::$timeOut);
            \curl_setopt(self::$cURL, CURLOPT_URL, self::$lastUrl);
            \curl_setopt(self::$cURL, CURLOPT_POST, self::$requestType);

            if (self::$requestType) {
                \curl_setopt(self::$cURL, CURLOPT_POSTFIELDS, http_build_query(self::$params));
            }

            \curl_setopt(self::$cURL, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt(self::$cURL, CURLOPT_SSL_VERIFYPEER, false);

            self::$exec = \curl_exec(self::$cURL);

            self::$info = \curl_getinfo(self::$cURL);

            \curl_close(self::$cURL);

            sleep(1);
        } catch (\Exception $e) {
        }

        return self::init();
    }
}
