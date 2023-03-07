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

// check auth
if (Configuration::get('THEMARKETER_REST_KEY') == Tools::getValue('key') && Tools::getIsset('type') && Tools::getIsset('value')) {
    $type = Tools::getValue('type');
    $disvalue = Tools::getValue('value');
    if ($type == 0) {
        $fixed_value = $disvalue;
        $percentage = 0;
        $free_shipping = 0;
    } elseif ($type == 1) {
        $fixed_value = 0;
        $percentage = $disvalue;
        $free_shipping = 0;
    } else {
        $fixed_value = 0;
        $percentage = 0;
        $free_shipping = 1;
    }
    if (Tools::getIsset('expiration_date')) {
        $expdate = Tools::getValue('expiration_date');
    } else {
        $expdate = '2100-12-31 23:59:59';
    }
    $unigcode = strtoupper(uniqid());
    $cart_rule = new CartRule();
    $cart_rule->name = [1 => 'Themarketer Discount Coupon'];
    $cart_rule->id_customer = 0;
    $cart_rule->date_from = date('Y-m-d H:i:s');
    $cart_rule->date_to = $expdate;
    $cart_rule->description = 'Discount Coupon';
    $cart_rule->quantity = 1;
    $cart_rule->quantity_per_user = 1;
    $cart_rule->priority = 1;
    $cart_rule->partial_use = 1;
    $cart_rule->code = 'TMD-' . $unigcode;
    $cart_rule->minimum_amount = 0;
    $cart_rule->minimum_amount_tax = 0;
    $cart_rule->minimum_amount_currency = 1;
    $cart_rule->minimum_amount_shipping = 0;
    $cart_rule->country_restriction = 0;
    $cart_rule->carrier_restriction = 0;
    $cart_rule->group_restriction = 0;
    $cart_rule->cart_rule_restriction = 0;
    $cart_rule->product_restriction = 0;
    $cart_rule->shop_restriction = 0;
    $cart_rule->free_shipping = $free_shipping;
    $cart_rule->reduction_percent = $percentage;
    $cart_rule->reduction_amount = $fixed_value;
    $cart_rule->reduction_tax = 1;
    $cart_rule->reduction_currency = 1;
    $cart_rule->reduction_product = 0;
    $cart_rule->gift_product = 0;
    $cart_rule->gift_product_attribute = 0;
    $cart_rule->highlight = 0;
    $cart_rule->active = 1;
    $cart_rule->date_add = date('Y-m-d H:i:s');
    $cart_rule->date_upd = date('Y-m-d H:i:s');

    $cart_rule->add();

    $discoundcode = 'TMD-' . $unigcode;
    echo '{"code": "' . $discoundcode . '"}';
} else {
    exit('no entry');
}
