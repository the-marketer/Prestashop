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

namespace Mktr\Helper;

use Mktr\Model\Config;

class Valid
{
    const mime = [
        'xml' => 'application/xhtml+xml',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'csv' => 'text/csv',
    ];
    const def_mime = 'xml';

    private static $init = null;
    private static $params = [];
    private static $error = null;
    private static $getOut = null;

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }

        return self::$init;
    }

    public static function getParam($name = null, $def = null, $toLow = false)
    {
        if (!array_key_exists($name, self::$params)) {
            self::$params[$name] = $toLow === true ?
                strtolower(\Tools::getValue($name, $def)) : \Tools::getValue($name, $def);
        }

        return self::$params[$name];
    }

    /** @noinspection PhpUnused */
    public static function setParam($name, $value)
    {
        self::$params[$name] = $value;

        return self::init();
    }

    /** @noinspection PhpUnused */
    public static function validateTelephone($phone)
    {
        return preg_replace("/\D/", '', $phone);
    }

    public static function validateDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) === $date;
    }

    /** @noinspection PhpUnused */
    public static function correctDate($date = null, $format = 'Y-m-d H:i')
    {
        return $date !== null ? date($format, strtotime($date)) : $date;
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpRedundantOptionalArgumentInspection
     */
    public static function toDigit($num = null, $digit = 2)
    {
        if ($num !== null) {
            $num = str_replace(',', '.', $num);
            $num = preg_replace('/\.(?=.*\.)/', '', $num);
            $num = number_format((float) $num, $digit, '.', '');
        }

        return $num;
    }

    public static function check($checkParam = null)
    {
        if ($checkParam === null) {
            return null;
        }

        self::$error = null;

        foreach ($checkParam as $k => $v) {
            if ($v !== null) {
                $check = explode('|', $v);
                foreach ($check as $do) {
                    if (self::$error === null) {
                        switch ($do) {
                            case 'Required':
                                if (self::getParam($k) === null) {
                                    self::$error = 'Missing Parameter ' . $k;
                                }
                                break;
                            case 'DateCheck':
                                if (self::getParam($k) !== null && !self::validateDate(self::getParam($k))) {
                                    self::$error = 'Incorrect Date ' .
                                        $k . ' - ' .
                                        self::getParam($k) . ' - ' .
                                        Config::$dateFormatParam;
                                }
                                break;

                            case 'DateCheckDiscount':
                                if (self::getParam($k) !== null && !self::validateDate(self::getParam($k), Config::$dateFormatParam)) {
                                    self::$error = 'Incorrect Date ' .
                                        $k . ' - ' .
                                        self::getParam($k) . ' - ' .
                                        Config::$dateFormatParam;
                                }
                                break;
                            case 'StartDate':
                                if (self::getParam($k) !== null && strtotime(self::getParam($k)) > \time()) {
                                    self::$error = 'Incorrect Start Date ' .
                                        $k . ' - ' .
                                        self::getParam($k) . ' - Today is ' .
                                        date(Config::$dateFormatParam, \time());
                                }
                                break;
                            case 'Key':
                                if (self::getParam($k) !== null && self::getParam($k) !== Config::i()->rest_key) {
                                    self::$error = 'Incorrect REST API Key ' . self::getParam($k);
                                }
                                break;
                            case 'RuleCheck':
                                if (self::getParam($k) !== null && !in_array(self::getParam($k), [0, 1, 2])) {
                                    self::$error = 'Incorrect Rule Type ' . self::getParam($k);
                                }
                                break;
                            case 'Int':
                                if (self::getParam($k) !== null && !is_numeric(self::getParam($k))) {
                                    self::$error = 'Incorrect Value ' . self::getParam($k);
                                }
                                break;
                            case 'allow_export':
                                if (Config::i()->allow_export === 0) {
                                    self::$error = 'Export not Allow';
                                }
                                break;
                            default:
                        }
                    }
                }
            }
        }

        return self::init();
    }

    public static function status()
    {
        return self::$error == null;
    }

    public static function error()
    {
        return self::$error;
    }

    public static function Output($data, $data1 = null, $name = null, $fromFile = false)
    {
        $mi = self::getParam('mime-type', self::def_mime);

        if (!array_key_exists($mi, self::mime)) {
            $mi = self::def_mime;
        }

        header('Content-type: ' . self::mime[$mi] . '; charset=utf-8');
        header('HTTP/1.1 200 OK');
        http_response_code(201);
        header('Status: 200 All rosy');

        if ($fromFile && !is_array($data)) {
            self::$getOut = $data;
        } else {
            self::$getOut = '';
            switch ($mi) {
                case 'xml':
                    Array2XML::setCDataValues(['name', 'description', 'category', 'brand', 'size', 'color', 'hierarchy']);
                    Array2XML::$noNull = true;
                    try {
                        if ($data1 == null) {
                            foreach ($data as $key => $val) {
                                $data = $key;
                                $data1 = $val;
                            }
                        }

                        self::$getOut = Array2XML::cXML($data, $data1)->saveXML();
                    } catch (\DOMException $e) {
                        self::$getOut = Array2XML::errors();
                    }
                break;
                case 'json':
                    if ($data1 !== null) {
                        $data = [$data => $data1];
                    }
                    self::$getOut = self::toJson($data);
                    break;
                default:
                    self::$getOut = $data;
            }
        }

        echo self::$getOut;
    }

    public static function getOutPut()
    {
        return self::$getOut;
    }

    public static function toJson($data = null)
    {
        return json_encode($data === null ? [] : $data, JSON_UNESCAPED_SLASHES);
    }
}
