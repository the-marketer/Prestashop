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
$contextObject = Context::getContext();
$id_lang = Context::getContext()->language->id;
$pid = Tools::getValue('product_id');
$atrr = Tools::getValue('comb_id');
$customer = $contextObject->customer->id;

if ($atrr) {
    $product = Db::getInstance()->getValue('SELECT p.id_product,p.active,p.wholesale_price,p.date_add,pl.description, pl.name,pa.id_product_attribute as id_product_attribute,
    p.price, pa.reference, pq.quantity
    FROM ' . _DB_PREFIX_ . 'product p
    LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (p.id_product = pl.id_product)
    LEFT JOIN ' . _DB_PREFIX_ . 'category_product cp ON (p.id_product = cp.id_product)
    LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON (cp.id_category = cl.id_category)
    LEFT JOIN ' . _DB_PREFIX_ . 'category c ON (cp.id_category = c.id_category)
    LEFT JOIN ' . _DB_PREFIX_ . 'product_tag pt ON (p.id_product = pt.id_product)
    LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON (p.id_product = pa.id_product)
    LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute_combination pac ON (pac.id_product_attribute = pa.id_product_attribute)
    LEFT JOIN ' . _DB_PREFIX_ . 'stock_available pq ON (p.id_product = pq.id_product AND pa.id_product_attribute = pq.id_product_attribute)
    LEFT JOIN ' . _DB_PREFIX_ . 'attribute_lang al ON (al.id_attribute = pac.id_attribute)
    LEFT JOIN ' . _DB_PREFIX_ . 'attribute_lang pal ON (pac.id_attribute = pal.id_attribute)
    WHERE pl.id_lang = ' . $id_lang . ' AND p.id_product = ' . $pid . ' AND pa.id_product_attribute = ' . $atrr . ' 
    AND cl.id_lang = ' . $id_lang . ' 
    AND p.id_shop_default = 1
    AND c.id_shop_default = 1
    GROUP by p.id_product, pl.description, pl.name, pa.id_product_attribute, p.price, pa.reference, pq.quantity');
} else {
    $product = Db::getInstance()->getValue('SELECT reference from ' . _DB_PREFIX_ . 'product where id_product = ' . $pid);
}
// get favorite if exists
if ($customer) {
    $wl = Db::getInstance()->getValue('SELECT ' . _DB_PREFIX_ . 'wishlist_product.id_wishlist as wid
	from ' . _DB_PREFIX_ . 'wishlist_product,' . _DB_PREFIX_ . 'wishlist where ' . _DB_PREFIX_ . 'wishlist_product.id_product = ' . $pid . '
	and ' . _DB_PREFIX_ . 'wishlist_product.id_product_attribute = ' . $atrr . ' and ' . _DB_PREFIX_ . 'wishlist.id_wishlist=' . _DB_PREFIX_ . 'wishlist_product.id_wishlist and 
    ' . _DB_PREFIX_ . 'wishlist.id_customer = ' . $customer);
    if (@$wl['wid']) {
        echo 'delete@' . @$product['reference'];
    } else {
        echo 'add@' . @$product['reference'];
    }
} else {
    echo 'no';
}
