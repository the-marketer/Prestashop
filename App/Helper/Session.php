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

if (!defined('_PS_VERSION_')) {
    exit;
}

use Mktr\Model\Config;

class Session
{
    const COOKIE = '__sm__uid';

    private static $init;
    private static $uid;
    private static $MKTR_TABLE;

    private $data = [];

    protected $org = [];

    private $isDirty = false;

    public static function init()
    {
        if (self::$init == null) {
            self::$MKTR_TABLE = _DB_PREFIX_ . 'mktr';
            self::$init = new self();
        }

        return self::$init;
    }

    public static function data()
    {
        return self::init()->data;
    }

    public function remove($key)
    {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
        }

        return $this;
    }

    public static function isValidUid($uid)
    {
        return is_string($uid) && preg_match('/^[a-f0-9]{13,40}$/', $uid) === 1;
    }

    public static function getUid($create = false)
    {
        if (self::$uid === null) {
            $cookie = isset($_COOKIE[self::COOKIE]) ? $_COOKIE[self::COOKIE] : null;

            if (self::isValidUid($cookie)) {
                self::$uid = $cookie;
            }
        }

        if (self::$uid === null && $create && !headers_sent()) {
            self::$uid = bin2hex(random_bytes(16));
            setcookie(self::COOKIE, self::$uid, strtotime('+365 days'), '/', '', !empty($_SERVER['HTTPS']), true);
            $_COOKIE[self::COOKIE] = self::$uid;
        }

        return self::$uid;
    }

    public function __construct()
    {
        $uid = self::getUid();

        if ($uid !== null) {
            $row = Config::db()->getValue('SELECT `data` FROM `' . self::$MKTR_TABLE . "` WHERE `uid` = '" . pSQL($uid) . "'", false);

            if (is_string($row) && $row !== '') {
                $decoded = json_decode($row, true);
                $this->org = is_array($decoded) ? $decoded : [];
            }
        }

        $this->data = $this->org;
    }

    public static function set($key, $value = null)
    {
        if ($value === null) {
            self::init()->remove($key);
        } else {
            self::init()->data[$key] = $value;
        }

        self::init()->isDirty = true;
    }

    public static function get($key, $default = null)
    {
        if (isset(self::init()->data[$key])) {
            return self::init()->data[$key];
        }

        return $default;
    }

    public static function save()
    {
        $session = self::init();

        if (!$session->isDirty) {
            return false;
        }

        if (empty($session->data)) {
            $uid = self::getUid();

            if ($uid !== null) {
                Config::db()->delete('mktr', "`uid` = '" . pSQL($uid) . "'", 0, false);
            }
        } else {
            $uid = self::getUid(true);

            if ($uid === null) {
                return false;
            }

            $row = [
                'uid' => pSQL($uid),
                'data' => pSQL(json_encode($session->data), true),
                'expire' => date('Y-m-d H:i:s', strtotime('+2 day')),
            ];

            Config::db()->insert('mktr', $row, false, false, \Db::REPLACE);
        }

        $session->org = $session->data;
        $session->isDirty = false;
        self::clearIfExipire();

        return true;
    }

    public static function clearIfExipire()
    {
        Config::db()->delete('mktr', "`expire` < '" . date('Y-m-d H:i:s') . "'", 0, false);
    }

    public static function clear()
    {
        self::init()->data = [];
        self::init()->isDirty = true;
    }

    public function __destruct()
    {
        if ($this->isDirty) {
            $this->save();
        }
    }

    public static function addToWishlist($pId, $pAttr = 0)
    {
        self::sessionSet('add_to_wish_list', [(int) $pId, (int) $pAttr]);
    }

    public static function removeFromWishlist($pId, $pAttr = 0)
    {
        self::sessionSet('remove_from_wishlist', [(int) $pId, (int) $pAttr]);
    }

    public static function Wishlist($pId, $pAttr = 0)
    {
        self::sessionSet('wishlist', [(int) $pId, (int) $pAttr]);
    }

    public static function addToCart($pId, $pAttr, $qty)
    {
        $qty = (int) $qty <= 0 ? 1 : (int) $qty;
        self::sessionSet('add_to_cart', [(int) $pId, (int) $pAttr, $qty]);
    }

    public static function removeFromCart($pId, $pAttr, $qty)
    {
        $qty = (int) $qty <= 0 ? 1 : (int) $qty;
        self::sessionSet('remove_from_cart', [(int) $pId, (int) $pAttr, $qty]);
    }

    public static function setEmail($email)
    {
        self::set('set_email', [$email]);
    }

    public static function sessionSet($name, $data, $key = null)
    {
        $add = self::get($name, []);

        if ($key === null) {
            $add[time() . random_int(10000, 99999)] = $data;
        } else {
            $add[$key] = $data;
        }

        self::set($name, $add);
    }
}
