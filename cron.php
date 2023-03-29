<?php
/**
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @docs        https://themarketer.com/resources/api
 */
if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
}

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/themarketer.php';

require_once MKTR_DIR . 'Model/Product.php';
require_once MKTR_DIR . 'Help/Array2XML.php';
require_once MKTR_DIR . 'Help/Data.php';

$module = new TheMarketer();

$data = Data::init();
$upFeed = $data->update_feed;
$upReview = $data->update_review;

if (Configuration::get(TheMarketer::CRON_FEED) != 0 && $upFeed < time()) {
    Array2XML::setCDataValues(['name', 'description', 'category', 'brand', 'size', 'color', 'hierarchy']);
    Array2XML::$noNull = true;
    $run = ModelProduct::getNewFeed();
    $XML = Array2XML::cXML('products', ['product' => $run])->saveXML();

    Data::writeFile('feed.xml', $XML);
    $add = Configuration::get(TheMarketer::UPDATE_FEED);
    $data->update_feed = strtotime('+' . (empty($add) ? 4 : $add) . ' hour');
}

$data->save();

echo '{"status":"DONE","time":"' . time() . '"}';

header('Content-type: application/json; charset=UTF-8');
header('HTTP/1.1 200 OK');
http_response_code(201);
header('Status: 200 All rosy');
