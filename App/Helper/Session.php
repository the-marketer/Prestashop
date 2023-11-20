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

class Session
{
    private static $init = null;
    private static $uid = null;
    private static $MKTR_TABLE = null;

    private $data = [];
    private $org = [];

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
        if ($this->data[$key]) {
            unset($this->data[$key]);
        }

        return $this;
    }

    public static function getUid()
    {
        if (self::$uid === null) {
            $cookie = \Context::getContext()->cookie;
            if (!isset($cookie->__sm__uid) || $cookie->__sm__uid === false) {
                // setcookie('__sm__uid', self::$uid, strtotime('+365 days'), '/');
                self::$uid = uniqid();
                $cookie->__sm__uid = self::$uid;
            } else {
                // self::$uid = $cookie['__sm__uid'];
                self::$uid = $cookie->__sm__uid;
            }
        }

        return self::$uid;
    }

    public function __construct()
    {
        $uid = self::getUid();
        $data = Config::db()->executeS('SELECT `data` FROM `' . self::$MKTR_TABLE . "` WHERE `uid` = '$uid'");

        $this->org = array_key_exists(0, $data) ? unserialize($data[0]['data']) : [];
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
        } else {
            return $default;
        }
    }

    public static function save()
    {
        if (self::init()->isDirty) {
            $uid = self::getUid();
            $table_name = self::$MKTR_TABLE;
            if (!empty(self::init()->data)) {
                $data = [
                    'data' => serialize(self::init()->data),
                    'expire' => date('Y-m-d H:i:s', strtotime('+2 day')),
                ];

                if (count(self::init()->org) > 0) {
                    $sql = 'UPDATE `' . self::$MKTR_TABLE . '` SET ';
                    $updates = [];

                    foreach ($data as $key => $value) {
                        if ($value !== null) {
                            $updates[] = "`$key` = '" . Config::db()->escape($value) . "'";
                        } else {
                            $updates[] = "`$key` = null";
                        }
                    }

                    $sql .= implode(', ', $updates);
                    $sql .= " WHERE `uid` = '" . $uid . "';";

                    Config::db()->query($sql);
                } else {
                    $columns[] = '`uid`';
                    $values[] = "'$uid'";
                    foreach ($data as $key => $value) {
                        $columns[] = "`$key`";
                        if ($value !== null) {
                            $values[] = "'" . Config::db()->escape($value) . "'";
                        } else {
                            $values[] = 'null';
                        }
                    }

                    $columns = implode(', ', $columns);
                    $values = implode(',', $values);

                    $data['uid'] = $uid;
                    Config::db()->query('INSERT INTO `' . self::$MKTR_TABLE . '` (' . $columns . ') VALUES (' . $values . ')');
                    $range_id = (int) Config::db()->Insert_ID();
                }
                self::init()->org = self::init()->data;
            } else {
                Config::db()->query('DELETE FROM `' . self::$MKTR_TABLE . "` WHERE `uid` = '$uid'");
                self::init()->org = [];
                self::init()->data = [];
            }

            self::clearIfExipire();

            self::init()->isDirty = false;

            return true;
        }

        return false;
    }

    public static function clearIfExipire()
    {
        $expire_at = date('Y-m-d H:i:s', time());
        Config::db()->execute('DELETE FROM `' . self::$MKTR_TABLE . "` WHERE `expire` < '$expire_at'");
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

    public static function addToWishlist($pId, $pAttr)
    {
        self::sessionSet('add_to_wish_list', [$pId, $pAttr]);
    }

    public static function removeFromWishlist($pId, $pAttr)
    {
        self::sessionSet('remove_from_wishlist', [$pId, $pAttr]);
    }

    public static function addToCart($pId, $pAttr, $qty)
    {
        $qty = $qty <= 0 ? 1 : $qty;
        self::sessionSet('add_to_cart', [$pId, $pAttr, $qty]);
    }

    public static function removeFromCart($pId, $pAttr, $qty)
    {
        $qty = $qty <= 0 ? 1 : $qty;
        self::sessionSet('remove_from_cart', [$pId, $pAttr, $qty]);
    }

    public static function setEmail($email)
    {
        self::set('set_email', [$email]);
    }

    public static function setPhone($phone)
    {
        self::set('set_phone', [$phone]);
    }

    public static function sessionSet($name, $data, $key = null)
    {
        $add = self::get($name);

        if ($key === null) {
            $n = '';

            for ($i = 0; $i < 5; ++$i) {
                $n .= \rand(0, 9);
            }

            $add[time() . $n] = $data;
        } else {
            $add[$key] = $data;
        }

        self::set($name, $add);
    }
}
