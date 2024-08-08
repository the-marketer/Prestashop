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

if (!defined('MKTR_PS_COMMENTS')) {
    define('MKTR_PS_COMMENTS', \Module::isInstalled('productcomments'));
}

if (MKTR_PS_COMMENTS) {
    include_once MKTR_ROOT . 'modules/productcomments/ProductComment.php';
}

class Reviews
{
    private static $init;

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }

        return self::$init;
    }

    public static function addFromApi($value)
    {
        if (!MKTR_PS_COMMENTS) {
            return null;
        }

        $customer = \Customer::getCustomersByEmail($value->review_email);

        if (empty($customer)) {
            $name = explode(' ', $value->review_author, 2);

            if (!array_key_exists(1, $name)) {
                $name[1] = null;
            }

            $customer = (object) [
                'id_customer' => 0,
                'firstname' => $name[1],
                'lastname' => $name[0],
            ];
            $is_guest = 1;
        } else {
            $customer = (object) (array_key_exists('id_customer', $customer) ? $customer : $customer[0]);
            $is_guest = 0;
        }

        $cName = [];

        if (!empty($customer->lastname)) {
            $cName[] = $customer->lastname;
        }

        if (!empty($customer->firstname)) {
            $cName[] = $customer->firstname;
        }

        $comment = new \ProductComment();
        $comment->id_product = (int) $value->product_id;
        $comment->id_customer = $customer->id_customer;
        $comment->id_guest = $is_guest;
        $comment->customer_name = implode(' ', $cName);
        $comment->title = $value->review_author;
        $comment->content = $value->review_text;
        $comment->grade = round((int) $value->rating / 2);
        $comment->validate = 1;

        if ($comment->add()) {
            $comment->date_add = (new \DateTime($value->review_date))->format('Y-m-d H:i:s');
            $comment->save();

            return $comment;
        } else {
            return null;
        }
    }
}
