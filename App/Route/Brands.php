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

use Mktr\Helper\Valid;

class Brands
{
    public static function run()
    {
        $data = [];

        $stop = false;
        $page = Valid::getParam('page', null);
        $limit = Valid::getParam('limit', null);

        if ($page !== null) {
            $stop = true;
        }

        $currentPage = $page === null ? 1 : $page;

        do {
            $cPage = \Mktr\Model\Brand::getPage($currentPage, $limit);
            $pages = $stop ? 0 : count($cPage);
            foreach ($cPage as $val) {
                $data[] = \Mktr\Model\Brand::getByID($val['id_manufacturer'])->toArray();
            }

            ++$currentPage;
        } while (0 < $pages);

        return $data;
    }
}
