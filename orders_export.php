<?php
/**
* theMarketer V1.0.3 module
* for Prestashop v1.7.X.
*
* @author themarketer.com
* @copyright  2022-2023 theMarketer.com
* @license    http:// opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
include '../../config/config.inc.php';
include '../../init.php';

if (!defined('MKTR_DIR')) {
    define('MKTR_DIR', dirname(__FILE__) . '/');
}
include MKTR_DIR . 'themarketer.php';
include MKTR_DIR . 'Model/Product.php';

$tm_allow = Configuration::get(TheMarketer::ORDERS_FEED_ALLOW);
if (Configuration::get('THEMARKETER_REST_KEY') == Tools::getValue('key') && Configuration::get('THEMARKETER_CUSTOMER_ID') == Tools::getValue('customerId') && !empty(Tools::getValue('start_date')) && $tm_allow == 1) {
    // get orders data
    if (Tools::getIsset('page') && Tools::getValue('page') > 0) {
        $page = Tools::getValue('page');
    } else {
        $page = 1;
    }
    $start = strtotime(Tools::getValue('start_date'));
    $total_entries = $page * 50;

    $sql = 'SELECT *, (
            SELECT osl.`name`
            FROM `' . _DB_PREFIX_ . 'order_state_lang` osl
            WHERE osl.`id_order_state` = o.`current_state`
            AND osl.`id_lang` = ' . (int) Context::getContext()->language->id . '
            LIMIT 1
        ) AS `state_name`, o.`date_add` AS `date_add`, o.`date_upd` AS `date_upd`, o.`invoice_date` AS `invoice_date`
        FROM `' . _DB_PREFIX_ . 'orders` o
        LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (c.`id_customer` = o.`id_customer`)
        WHERE o.`invoice_date` >= \'' . pSQL(date('Y-m-d h:i:sa', $start)) . '\'
        ORDER BY o.`date_add` DESC ' . ((int) $total_entries ? 'LIMIT 0, ' . (int) $total_entries : '');

    $orders_data = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

    $ordersarr = [];
    $states = new OrderState(Context::getContext()->language->id);
    $statesarr = $states->getOrderStates(Context::getContext()->language->id);
    $langId = Context::getContext()->language->id;
    $ce = 1;

    usort($orders_data, function ($a, $b) {
        return $a['id_order'] - $b['id_order'];
    });
    foreach ($orders_data as $k => $order) {
        if ($ce >= ($total_entries - 49) && $ce <= $total_entries) {
            $orderId = $order['id_order'];
            $statusID = $order['current_state'];
            if ($statusID != 0) {
                $statusName = Db::getInstance()->getRow('SELECT name FROM `' . _DB_PREFIX_ . 'order_state_lang` WHERE `id_order_state`=' . $statusID . ' AND `id_lang`=' . $langId);
                $statusName = $statusName['name'];
            } else {
                $statusName = 'unknown';
            }

            $customerId = $order['id_customer'];
            $addressId = $order['id_address_invoice'];
            $discount = round($order['total_discounts_tax_incl'], 2);
            $total = round($order['total_paid'], 2);
            $totalShipping = round($order['total_shipping_tax_incl'], 2);
            $orderinfo = Order::getIdByCartId($order['id_cart']);
            $order_details = new Order($orderinfo);
            $date_created = date(ModelProduct::$dateFormat, strtotime($order_details->date_add));
            $delivery_details = new Address($order_details->id_address_invoice);
            $customer = new Customer($delivery_details->id_customer);
            $products = $order_details->getProducts();

            $firstname = $customer->firstname;
            $lastname = $customer->lastname;
            $email = $customer->email;
            $phone = $delivery_details->phone;
            // get products
            $i = 0;
            $products_data = [];
            foreach ($products as $p) {
                ModelProduct::getProductByID($p['id_product']);
                $products_data[$i]['product_id'] = ModelProduct::getId();
                $products_data[$i]['sku'] = ModelProduct::getSku();
                $products_data[$i]['name'] = ModelProduct::getName();
                $products_data[$i]['url'] = ModelProduct::getUrl();
                $products_data[$i]['main_image'] = ModelProduct::getMainImage();
                $products_data[$i]['category'] = ModelProduct::getCategory();
                $products_data[$i]['brand'] = ModelProduct::getBrand();
                $products_data[$i]['quantity'] = $p['product_quantity'];
                $products_data[$i]['price'] = $p['product_quantity'] * $p['original_product_price'];
                $products_data[$i]['sale_price'] = round($p['total_price_tax_incl'], 2);

                $variant = ModelProduct::getVariant($p['product_attribute_id']);

                $products_data[$i]['variation_id'] = $variant['id'];
                $products_data[$i]['variation_sku'] = $variant['sku'];
            }
            // create order array
            $ordersarr['orders']['order'][$k]['order_no'] = $orderId;
            $ordersarr['orders']['order'][$k]['refund_value'] = 0;
            $ordersarr['orders']['order'][$k]['created_at'] = $date_created;
            $ordersarr['orders']['order'][$k]['first_name'] = $firstname;
            $ordersarr['orders']['order'][$k]['last_name'] = $lastname;
            $ordersarr['orders']['order'][$k]['customer_email'] = $email;
            $ordersarr['orders']['order'][$k]['phone'] = $phone;
            $ordersarr['orders']['order'][$k]['discount_code'] = '';
            $ordersarr['orders']['order'][$k]['discount_value'] = $discount;
            $ordersarr['orders']['order'][$k]['shipping_price'] = $totalShipping;
            $ordersarr['orders']['order'][$k]['total_value'] = $total;
            $ordersarr['orders']['order'][$k]['products'] = $products_data;
        }
        $ce = $ce + 1;
    }
    header('Access-Control-Allow-Origin: *');
    header('Content-type: application/json');

    echo json_encode($ordersarr); /* , JSON_PRETTY_PRINT); */
}
