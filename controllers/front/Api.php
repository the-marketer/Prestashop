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
if (!defined('_PS_VERSION_')) {
    exit;
}

use Mktr\Helper\FileSystem;
use Mktr\Helper\Valid;

class MktrApiModuleFrontController extends \FrontController
{
    private static $page;
    private static $check = [
        'orders' => [
            'key' => 'Required|Key|allow_export',
            'start_date' => 'Required|DateCheck|StartDate',
            'page' => null,
            'customerId' => null,
        ],
        'codegenerator' => [
            'key' => 'Required|Key',
            'expiration_date' => 'DateCheckDiscount',
            'value' => 'Required|Int',
            'type' => 'Required|RuleCheck',
        ],
        'reviews' => [
            'key' => 'Required|Key',
            // 'start_date' => 'Required|DateCheck|StartDate'
        ],
        'feed' => [
            'key' => 'Required|Key',
        ],
        'brands' => [
            'key' => 'Required|Key',
        ],
        'category' => [
            'key' => 'Required|Key',
        ],
        'refreshjs' => [
            'key' => 'Required|Key',
        ],
        'checkhook' => [
            'key' => 'Required|Key',
        ],
    ];

    private static $page_mime = [
        'feed' => 'xml',
        'brands' => 'xml',
        'category' => 'xml',
        'codegenerator' => 'json',
        'reviews' => 'json',
        'orders' => 'json',
        'refreshjs' => 'json',
        'loadevents' => 'js',
        'getevents' => 'json',
        'loaddata' => 'json',
        'clearevents' => 'js',
        'setemail' => 'js',
        'saveorder' => 'js',
        'checkhook' => 'json',
    ];

    private static $Route = [
        'feed' => 'Feed',
        'brands' => 'Brands',
        'category' => 'Category',
        'codegenerator' => 'CodeGenerator',
        'reviews' => 'Reviews',
        'orders' => 'Orders',
        'refreshjs' => 'refreshJS',
        'getevents' => 'GetEvents',
        'loaddata' => 'LoadData',
        'loadevents' => 'LoadEvents', // ToDo
        'clearevents' => 'ClearEvents', // ToDo
        'setemail' => 'setEmail', // ToDo
        'saveorder' => 'saveOrder', // ToDo
        'checkhook' => 'CheckHook', // ToDo
    ];

    public static $page_tree = [
        'feed' => ['products', 'product'],
        'category' => ['categories', 'category'],
        'brands' => ['brands', 'brand'],
        'orders' => ['orders', 'order'],
        'codegenerator' => [null, 'code'],
    ];

    private static $isStatic = ['orders', 'feed', 'brands', 'category'];

    public function __construct()
    {
        parent::__construct();
        self::$page = Valid::getParam('pg', false, true);
    }

    public function __call($name, $arguments)
    {
        if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $name)) {
            if (_PS_MODE_DEV_) {
                throw new \Exception('Invalid method name.');
            }
            return null;
        }

        if (array_key_exists($name, self::$Route)) {
            return call_user_func_array(['Mktr\\Route\\' . self::$Route[$name], 'run'], $arguments);
        } elseif (method_exists($this, $name)) {
            return call_user_func_array([$this, $name], $arguments);
        } else {
            if (_PS_MODE_DEV_) {
                throw new \Exception("Method {$name} does not exist.");
            }

            return null;
        }
    }

    public function initContent()
    {
        /* @phpstan-ignore-next-line */
        \Mktr\Model\Config::setLang($this->context->language->id)->setContext($this->context);
        $name = self::$page;

        if (array_key_exists($name, self::$page_mime)) {
            if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $name)) {
                Valid::Output('status', 'Invalid method name');
                exit(0);
            }

            if (!array_key_exists($name, self::$Route)) {
                Valid::Output('status', 'Invalid Page');
                exit(0);
            }

            $mime = Valid::getParam('mime-type', self::$page_mime[$name]);

            if (array_key_exists($name, self::$check)) {
                Valid::check(self::$check[$name])->status();
            }

            if (Valid::status()) {
                $isStatic = in_array($name, self::$isStatic);
                $file = Valid::getParam('file');
                $read = null;

                if ($isStatic) {
                    $script = '';
                    $read = Valid::getParam('read');
                    $start_date = Valid::getParam('start_date');
                    if ($start_date !== null) {
                        $script = '.' . base64_encode($start_date);
                    }
                    $fileName = $name . $script . '.' . $mime;
                } else {
                    $fileName = $name . '.' . $mime;
                }

                if ($file !== null) {
                    $safeFileName = str_replace(["\r", "\n"], '', $fileName);
                    $safeFileName = preg_replace('/[^A-Za-z0-9._-]/', '_', $safeFileName);
                    $safeFileName = substr($safeFileName, 0, 150);
                    if ($safeFileName === '') {
                        $safeFileName = 'download.' . $mime;
                    }
                    header(
                        'Content-Disposition: attachment; filename="' . $safeFileName .
                        '"; filename*=UTF-8\'\'' . rawurlencode($safeFileName)
                    );
                }

                FileSystem::setWorkDirectory('Storage/');

                if ($read !== null && $isStatic && FileSystem::fileExists($fileName)) {
                    Valid::Output(FileSystem::readFile($fileName), null, null, true);
                } else {
                    $out = $this->{$name}();
                    if (array_key_exists($name, self::$page_tree)) {
                        $tree = self::$page_tree[$name];
                    } else {
                        $tree = [null, null];
                    }

                    if ($tree[0] !== null) {
                        Valid::Output($tree[0], [$tree[1] => $out]);
                    } elseif ($tree[1] !== null) {
                        Valid::Output($tree[1], $out);
                    } else {
                        Valid::Output($out, null, null, true);
                    }

                    if ($isStatic) {
                        FileSystem::writeFile($fileName, Valid::getOutPut());
                    }
                }
            } else {
                Valid::Output('status', Valid::error());
            }
        } else {
            Valid::Output('status', 'Invalid Page');
        }
        exit(0);
    }
}
