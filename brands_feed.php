<?php
/**
* theMarketer module
* for Prestashop v1.7.X.
*
* @author themarketer.com
* @copyright  2022-2023 theMarketer.com
* @license    http:// opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
include '../../config/config.inc.php';
include '../../init.php';

header('Content-type: text/xml');
$id_lang = Context::getContext()->language->id;
//  check auth
if (Configuration::get('THEMARKETER_REST_KEY') == Tools::getValue('key')) {
    //  get all brands
    $all_brands = Manufacturer::getManufacturers(
        $getNbProducts = false,
        $id_lang,
        $active = true,
        $p = false,
        $n = false,
        $all_group = false
    );

    //  start xml schema
    $xml_schema = '<?xml version="1.0" encoding="UTF-8"?><brands>';
    foreach ($all_brands as $brand) {
        $link = new Link();
        $link = $link->getManufacturerLink($brand['id_manufacturer'], $alias = null, $id_lang);
        $logo = Context::getContext()->shop->getBaseURL(true) . 'img/m/' . $brand['id_manufacturer'] . '.jpg';
        $xml_schema .= '<brand><name><![CDATA[' . $brand['name'] . ']]></name><url><![CDATA[' . $link . ']]></url><id><![CDATA[' . $brand['id_manufacturer'] . ']]></id><image_url><![CDATA[' . $logo . ']]></image_url></brand>';
    }
    //  end xml schema
    $xml_schema .= '</brands>';
    echo $xml_schema;
} else {
    exit('no entry');
}
