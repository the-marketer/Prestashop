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

use Mktr\Helper\Valid;

class CodeGenerator
{
    private static $init;

    private static $map = [];

    private static $ruleType;
    private static $code;

    const PREFIX = 'MKTR-';
    const LENGTH = 10;
    const DESCRIPTION = 'Discount Code Generated through TheMarketer API';

    const SYMBOLS_COLLECTION = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }

        return self::$init;
    }

    public static function newCode()
    {
        self::$code = self::PREFIX;
        for ($i = 0, $indexMax = strlen(self::SYMBOLS_COLLECTION) - 1; $i < self::LENGTH; ++$i) {
            self::$code .= substr(self::SYMBOLS_COLLECTION, \rand(0, $indexMax), 1);
        }

        if (\CartRule::getIdByCode(self::$code) !== false) {
            return self::newCode();
        }

        return self::$code;
    }

    public static function getNewCode()
    {
        $coupon = new \CartRule();
        $type = Valid::getParam('type', null);
        $value = Valid::getParam('value', null);
        $expiration = Valid::getParam('expiration_date', null);
        $c = (int) Config::getContext()->cart->id_currency;

        $rules = [
            0 => 'fixed_cart',
            1 => 'percent',
            2 => 'free_shipping',
        ];

        switch ($type) {
            case 0: /* "fixed_cart" */
                $coupon->reduction_amount = $value;
                $coupon->reduction_percent = 0;
                break;
            case 1: /* "percent" */
                $coupon->reduction_percent = $value;
                $coupon->reduction_amount = 0;
                break;
            case 2: /* "free_shipping" */
                $coupon->free_shipping = true;
                break;
        }

        if ($expiration !== null) {
            $coupon->date_to = (new \DateTime($expiration))->format('Y-m-d H:i:s');
        } else {
            /* 1 year */
            $coupon->date_to = date('Y-m-d H:i:s', time() + 31536000);
        }

        $coupon->code = self::newCode();
        $coupon->date_from = date('Y-m-d H:i:s', time());

        $coupon->name = [
            Config::getConfig('PS_LANG_DEFAULT') => 'Themarketer - ' . $rules[$type] . '-' . $value . ($expiration === null ? '' : '-' . $expiration),
        ];
        $coupon->description = self::DESCRIPTION . ' (' . $rules[$type] . '-' . $value . ($expiration === null ? '' : '-' . $expiration) . ')';
        $coupon->id_customer = 0;
        $coupon->quantity = 1;
        $coupon->quantity_per_user = 1;
        $coupon->reduction_tax = 1;
        $coupon->active = 1;
        /*
        if cumulated need to be 0,
        if can't use multiple discount codes need to be 1
        */
        $coupon->cart_rule_restriction = 0;
        $coupon->minimum_amount_currency = $c;
        $coupon->reduction_currency = $c;
        // save the coupon
        $coupon->add();

        return self::$code;
    }
}
