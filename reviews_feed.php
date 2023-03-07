<?php
/**
* theMarketer V1.0.0 module
* for Prestashop v1.7.X.
*
* @author themarketer.com
* @copyright  2022-2023 theMarketer.com
* @license    http:// opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
include '../../config/config.inc.php';
include '../../init.php';
if (Configuration::get('THEMARKETER_REST_KEY') == Tools::getValue('key')) {
    header('Content-type: text/xml');
    $apiKey = Configuration::get('THEMARKETER_REST_KEY');
    $customerId = Configuration::get('THEMARKETER_CUSTOMER_ID');
    $start_date = strtotime(Tools::getValue('start_date'));
    $apiURL = 'https://t.themarketer.com/api/v1/product_reviews?k=' . $apiKey . '&u=' . $customerId . '&t=' . $start_date;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiURL);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_TIMEOUT, '30');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    echo $response;
} else {
}
