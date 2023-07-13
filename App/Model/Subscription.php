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

namespace Mktr\Model;

use Mktr\Helper\DataBase;

class Subscription extends DataBase
{
    protected $attributes = [
        'email_address' => null,
        'firstname' => null,
        'lastname' => null,
        'phone' => null,
        'subscribed' => null,
    ];
    protected $ref = [
        'email_address' => 'email',
        'firstname' => 'getFirstName',
        'lastname' => 'getLastName',
        'phone' => 'getPhone',
        'subscribed' => 'getSubscribed',
    ];

    protected $functions = [
        'getFirstName',
        'getLastName',
        'getPhone',
        'getSubscribed',
    ];

    protected $vars = [];

    protected $cast = [
        'subscribed' => 'bool',
    ];

    protected $is = null; /* newsletter | customer */
    protected $name = null;
    protected $adressData = null;
    protected $tmp = null;

    protected $orderBy = 'id_manufacturer';
    protected $direction = 'ASC';
    protected $dateFormat = 'Y-m-d H:i';

    private static $i = null;
    private static $curent = null;
    private static $d = [];

    public static function i()
    {
        if (self::$i === null) {
            self::$i = new static();
        }

        return self::$i;
    }

    public static function c()
    {
        return self::$curent;
    }

    public static function getByEmail($email, $new = false)
    {
        if ($new || !array_key_exists($email, self::$d)) {
            self::$d[$email] = new static();
            self::$d[$email]->tmp = $email;
            $retrun = \Customer::getCustomersByEmail($email);
            if (empty($retrun)) {
                if (_PS_VERSION_ >= 1.7) {
                    $table = 'emailsubscription';
                } else {
                    $table = 'newsletter';
                }
                $sql = 'SELECT * FROM `' . _DB_PREFIX_ . $table . '` WHERE `id_shop` = ' . Config::shop() . ' AND `email` = "' . $email . '" LIMIT 1';

                $retrun = Config::db()->executeS($sql);
                if (!empty($retrun)) {
                    self::$d[$email]->is = 'newsletter';
                    self::$d[$email]->data = (object) $retrun[0];
                }
            } else {
                self::$d[$email]->is = 'customer';
                self::$d[$email]->data = (object) $retrun[0];
            }
        }

        self::$curent = self::$d[$email];

        return self::$curent;
    }

    protected function getName($w = null)
    {
        if ($this->name === null) {
            $split = explode('@', $this->email);
            if (!array_key_exists(1, $split)) {
                $split[1] = null;
            }
            $this->name = [
                'firstname' => $split[1],
                'lastname' => $split[1],
            ];
        }

        return $w === null ? $this->name : $this->name[$w];
    }

    protected function getFirstName()
    {
        if ($this->is === 'customer') {
            return $this->data->firstname;
        } else {
            return $this->getName('firstname');
        }
    }

    protected function getLastName()
    {
        if ($this->is === 'customer') {
            return $this->data->lastname;
        } else {
            return $this->getName('lastname');
        }
    }

    protected function AdressData()
    {
        if ($this->is === 'customer' && $this->adressData === null) {
            $id = \Address::getFirstCustomerAddressId($this->data->id_customer, $this->data->active);
            $this->adressData = new \Address($id);
        }

        return $this->adressData;
    }

    protected function getPhone()
    {
        if ($this->is === 'customer') {
            return \Mktr\Helper\Valid::validateTelephone($this->AdressData()->phone);
        } else {
            return null;
        }
    }

    protected function getSubscribed()
    {
        if ($this->is === 'customer') {
            return (bool) $this->data->newsletter;
        } else {
            return $this->data !== null ? (bool) $this->data->active : false;
        }
    }
}
