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
if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
}

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/mktr.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

$module = new Mktr();
if (Mktr\Model\Config::showJS()) {
    $data = Mktr\Helper\Data::init();

    $upFeed = $data->update_feed;
    $upReview = $data->update_review;
    if (Mktr\Model\Config::i()->cron_feed && $upFeed < time()) {
        $currentPage = 1;
        $limit = null;
        $d = [];
        do {
            $cPage = Mktr\Model\Product::getPage($currentPage, $limit);
            $pages = count($cPage);

            foreach ($cPage as $val) {
                $d[] = Mktr\Model\Product::getByID($val['id'], true)->toArray();
            }

            ++$currentPage;
        } while (0 < $pages);

        Mktr\Helper\Array2XML::setCDataValues(['name', 'description', 'category', 'brand', 'size', 'color', 'hierarchy']);
        Mktr\Helper\Array2XML::$noNull = true;

        $XML = Mktr\Helper\Array2XML::cXML('products', ['product' => $d])->saveXML();

        Mktr\Helper\Data::writeFile('feed.xml', $XML);

        $add = Mktr\Model\Config::i()->update_feed;

        $data->update_feed = strtotime('+' . (empty($add) ? 4 : $add) . ' hour');
    }

    $data->save();
}

echo '{"status":"DONE","time":"' . time() . '"}';

header('Content-type: application/json; charset=UTF-8');
header('HTTP/1.1 200 OK');
http_response_code(201);
header('Status: 200 All rosy');
