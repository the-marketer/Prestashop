<?php
/**
*  theMarketer module
*  for Prestashop v1.7.X.
*
*  @author themarketer.com
*  @copyright  2022-2023 theMarketer.com
*  @license    http:// opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
include '../../config/config.inc.php';

include '../../init.php';
$email = Tools::getValue('email');
if (!empty($email)) {
    $sql = 'SELECT `newsletter`  FROM ' . _DB_PREFIX_ . 'customer WHERE `email` = \'' . pSQL($email) . '\'';
    $registered = Db::getInstance()->getRow($sql);
    $apiURL = 'https://t.themarketer.com/api/v1/remove_subscriber';
    $apiKey = Configuration::get('THEMARKETER_REST_KEY');
    $customerId = Configuration::get('THEMARKETER_CUSTOMER_ID');
    $registerData = [
                'k' => $apiKey,
                'u' => $customerId,
                'email' => $email,
                'phone' => '',
                'name' => '',
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiURL);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_TIMEOUT, '30');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($registerData));
    $response = curl_exec($ch);
    curl_close($ch);
    print_r($response);
    print_r($registerData);
} else {
}
