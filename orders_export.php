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
$tm_allow = Configuration::get('THEMARKETER_ORDERS_FEED_ALLOW');
if (Configuration::get('THEMARKETER_REST_KEY') == Tools::getValue('key') && Configuration::get('THEMARKETER_CUSTOMER_ID') == Tools::getValue('customerId') && !empty(Tools::getValue('start_date')) && $tm_allow == 1) {
    function getCategoryTree($id_product, $id_lang)
    {
        $root = Category::getRootCategory();
        $selected_cat = Product::getProductCategoriesFull($id_product, $id_lang);
        $tab_root = [
            'id_category' => $root->id,
            'name' => $root->name,
        ];
        $helper = new Helper();
        $category_tree = $helper->renderCategoryTree($tab_root, $selected_cat, 'categoryBox', false, true, [], false, true);
        return $selected_cat;
    }
    // get orders data
    if (Tools::getIsset('page') && Tools::getValue('page') > 0) {
        $page = Tools::getValue('page');
    } else {
        $page = 1;
    }
    $total_entries = $page * 50;
    $orders_data = Order::getOrdersWithInformations();
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
            $statusName = Db::getInstance()->getRow('SELECT name FROM `' . _DB_PREFIX_ . 'order_state_lang` WHERE `id_order_state`=' . $statusID . ' AND `id_lang`=' . $langId);
            $statusName = $statusName['name'];
            $customerId = $order['id_customer'];
            $addressId = $order['id_address_invoice'];
            $discount = round($order['total_discounts_tax_incl'], 2);
            $total = round($order['total_paid'], 2);
            $totalShipping = round($order['total_shipping_tax_incl'], 2);
            $orderinfo = Order::getOrderByCartId($order['id_cart']);
            $order_details = new Order($orderinfo);
            $date_created = $order_details->date_add;
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
                $product = new Product($p['id_product'], false, Context::getContext()->language->id);
                $link = new Link();
                $url = $url = $link->getProductLink($product);
                $image = Image::getCover($p['id_product']);
                $imagePath = 'https://' . $link->getImageLink($product->link_rewrite, $image['id_image'], ImageType::getFormattedName('home'));
                $cats = getCategoryTree($p['id_product'], Context::getContext()->language->id);
                $cat = '';
                foreach ($cats as $c) {
                    $cat .= $c['name'] . '|';
                }
                $cat = mb_substr($cat, 0, -1);
                $manufacturer = new Manufacturer($p['id_manufacturer'], Context::getContext()->language->id);
                $products_data[$i]['product_id'] = $p['id_product'];
                $products_data[$i]['sku'] = $p['reference'];
                $products_data[$i]['name'] = $p['product_name'];
                $products_data[$i]['url'] = $url;
                $products_data[$i]['main_image'] = $imagePath;
                $products_data[$i]['category'] = $cat;
                $products_data[$i]['brand'] = $manufacturer->name;
                $products_data[$i]['quantity'] = $p['product_quantity'];
                $attriduteId = $p['product_attribute_id'];
                $combination = new Combination($attriduteId);
                $products_data[$i]['variation_id'] = $attriduteId;
                $products_data[$i]['variation_sku'] = $combination->reference;
                $products_data[$i]['price'] = $p['product_quantity'] * $p['original_product_price'];
                $products_data[$i]['sale_price'] = round($p['total_price_tax_incl'], 2);
                $i = $i + 1;
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

    echo json_encode($ordersarr);
} else {
}
