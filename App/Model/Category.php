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

namespace Mktr\Model;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Mktr\Helper\DataBase;

class Category extends DataBase
{
    protected $attributes = [
        'id' => null,
        'name' => null,
        'url' => null,
        'hierarchy' => null,
    ];
    protected $ref = [
        'id' => 'id_category',
        'name' => 'name',
        'url' => 'getUrl',
        'hierarchy' => 'getParent',
    ];

    protected $functions = [
        'getUrl',
        'getParent',
    ];

    protected $vars = [];
    protected $cast = [];

    protected $orderBy = 'id_category';
    protected $direction = 'ASC';
    protected $dateFormat = 'Y-m-d H:i';

    private static $i;
    private static $curent;
    private static $d = [];

    private static $shop;

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

    public static function getPage($num = 1, $limit = null)
    {
        $i = self::i();

        if ($limit === null) {
            $limit = $i->limit;
        }

        if ($num === null) {
            $num = 1;
        }

        $start = (($num - 1) * $limit);

        $sql = 'SELECT  c.`id_category`, c.`id_parent`, c.`active` FROM `' . _DB_PREFIX_ . 'category` c LEFT JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON (c.`id_category` = cs.`id_category` AND cs.`id_shop` = ' . Config::shop() . ')' .
        ' WHERE  c.`active` = 1 ORDER BY c.`' . $i->orderBy . '` ' . $i->direction . ' LIMIT ' . $start . ', ' . $limit;

        $i->list = Config::db()->executeS($sql);

        return $i->list;
    }

    public static function getByID($id, $new = false)
    {
        if ($new || !array_key_exists($id, self::$d)) {
            self::$d[$id] = new static();
            self::$d[$id]->data = new \Category($id, Config::getLang(), Config::shop());
        }

        self::$curent = self::$d[$id];

        return self::$curent;
    }

    protected function getUrl()
    {
        return Config::getContext()->link->getCategoryLink($this->data->id);
    }

    protected function getParent()
    {
        $parents = $this->data->getParentsCategories(Config::getLang());

        $p = [];

        foreach ($parents as $ch) {
            if (array_key_exists('name', $ch)) {
                $p[] = $ch['name'];
            }
        }
        if (empty($p)) {
            $p = [$this->name];
        } else {
            krsort($p);
        }

        return implode('|', $p);
    }
}
