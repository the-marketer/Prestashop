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

namespace Mktr\Model;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Mktr\Helper\DataBase;

class Orders extends DataBase
{
    protected $attributes = [
        'order_no' => null,
        'order_status' => null,
        'refund_value' => null,
        'created_at' => null,
        'email_address' => null,
        'phone' => null,
        'firstname' => null,
        'lastname' => null,
        'city' => null,
        'county' => null,
        'address' => null,
        'discount_value' => null,
        'discount_code' => null,
        'shipping' => null,
        'tax' => null,
        'total_value' => null,
        'products' => null,
    ];

    protected $ref = [
        'order_no' => 'id',
        'number' => 'id',
        'order_status' => 'getStatus',
        'current_state' => 'current_state',
        'id_customer' => 'id_customer',
        'id_address_invoice' => 'id_address_invoice',
        'refund_value' => 'getRefund',
        'created_at' => 'date_add',
        'email_address' => 'getEmail',
        'phone' => 'getPhone',
        'firstname' => 'getFirstName',
        'lastname' => 'getLastName',
        'city' => 'getCity',
        'county' => 'getCounty',
        'address' => 'getAddress',
        'discount_value' => 'total_discounts',
        'discount_code' => 'getDiscountCode',
        'shipping' => 'total_shipping_tax_incl',
        'total_discounts' => 'total_discounts',
        'total_paid_tax_incl' => 'total_paid_tax_incl',
        'total_paid_tax_excl' => 'total_paid_tax_excl',
        'total_shipping_tax_incl' => 'total_shipping_tax_incl',
        'total_paid' => 'total_paid',
        'tax' => 'getTax',
        'total_value' => 'total_paid',
        'total_paid_real' => 'total_paid_real',
        'products' => 'getProductsData',
        'products_api' => 'getProducts',
    ];

    protected $cast = [
        'tax' => 'double',
        'shipping' => 'double',
        'total_value' => 'double',
        'discount_value' => 'double',
        'created_at' => 'date',
    ];

    protected $functions = [
        'getStatus',
        'getEmail',
        'getRefund',
        'getPhone',
        'getFirstName',
        'getLastName',
        'getCity',
        'getCounty',
        'getAddress',
        'getTax',
        'getProductsData',
        'getProducts',
        'getDiscountCode',
    ];

    protected $vars = [];

    protected $orderBy = 'id_order';

    protected $direction = 'ASC';

    protected $dateFormat = 'Y-m-d H:i';

    protected $refund = 0;

    protected $tmp_names;

    private static $i;

    private static $curent;

    private static $d = [];

    private static $orderState;

    private static $customerData = [];

    private static $adressData = [];

    public static function i()
    {
        if (self::$i === null) {
            $class = get_called_class();
            self::$i = new $class();
            // self::$i = new static();
        }

        return self::$i;
    }

    public static function c()
    {
        return self::$curent;
    }

    public static function orderState($ID)
    {
        if (self::$orderState === null) {
            foreach (\OrderState::getOrderStates(Config::getLang()) as $state) {
                self::$orderState[$state['id_order_state']] = $state;
            }
        }

        return array_key_exists($ID, self::$orderState) ? self::$orderState[$ID]['name'] : 'unknown';
    }

    public static function getPage($num = 1, $limit = null)
    {
        return self::getPageByDate($num, null, null, $limit);
    }

    public static function getPageByDate($num = 1, $start_date = null, $end_date = null, $limit = null)
    {
        $i = self::i();

        if ($limit === null) {
            $limit = $i->limit;
        }

        if ($num === null) {
            $num = 1;
        }

        $start = (($num - 1) * $limit);

        $sql = 'SELECT `id_order`' .
                ' FROM `' . _DB_PREFIX_ . 'orders`';

        $wh = [];

        if (\Shop::isFeatureActive()) {
            $wh[] = ' `id_shop` = ' . (int) Config::shop();
        }

        if ($start_date !== null) {
            $wh[] = " date_add >= '" . \pSQL($start_date) . "'";
        }

        if ($end_date !== null) {
            $wh[] = " date_add <= '" . \pSQL($end_date) . "'";
        }

        if (!empty($wh)) {
            $sql .= ' WHERE' . implode(' AND', $wh);
        }
        $sql .= ' ORDER BY `' . $i->orderBy . '` ' . $i->direction . ' LIMIT ' . $start . ', ' . $limit;

        $i->list = Config::db()->executeS($sql);

        return $i->list;
    }

    /**
     * Sends one order to TheMarketer.
     *
     * Single exit point for every path that pushes an order: the
     * actionValidateOrder hook, the cron sweeper and the session queue.
     *
     * @param int $id_order
     *
     * @return bool true when the API acknowledged the order
     */
    public static function push($id_order)
    {
        $id_order = (int) $id_order;

        if ($id_order <= 0 || !Config::rest()) {
            return false;
        }

        // The hook already sent this one and the browser is now draining the
        // session queue for the same order. Order ids only grow, so anything
        // at or below the watermark has been through here already.
        if (self::alreadySent($id_order)) {
            return true;
        }

        try {
            $order = self::getByID($id_order, true);

            if (empty($order->getProducts())) {
                return false;
            }

            $sOrder = $order->toApi();

            \Mktr\Helper\Api::send('save_order', $sOrder);
            $sent = \Mktr\Helper\Api::getStatus() == 200;

            if (!empty($sOrder['email_address'])) {
                self::pushSubscriber($sOrder['email_address']);
            }

            if ($sent) {
                self::advanceWatermark($id_order);
            }

            return $sent;
        } catch (\Exception $e) {
            self::pushLog($id_order, $e->getMessage());

            return false;
        }
    }

    /**
     * @param int $id_order
     *
     * @return bool whether the watermark already covers this order
     */
    private static function alreadySent($id_order)
    {
        try {
            $last = (int) \Mktr\Helper\Data::init()->last_order_sync;
        } catch (\Exception $e) {
            return false;
        }

        return $last > 0 && $id_order <= $last;
    }

    /**
     * Keeps the sweeper's watermark in step with the orders the hook already
     * sent, so the usual case leaves it nothing to re-send. Only closes the
     * gap by one - anything else is left for the sweeper to work out.
     *
     * @param int $id_order
     */
    private static function advanceWatermark($id_order)
    {
        try {
            $data = \Mktr\Helper\Data::init();
            $last = (int) $data->last_order_sync;

            if ($last > 0 && $id_order === $last + 1) {
                $data->last_order_sync = $id_order;
                \Mktr\Helper\Data::save();
            }
        } catch (\Exception $e) {
            // The watermark is an optimisation, not a guarantee - a shop with
            // an unwritable Storage directory still sends its orders.
        }
    }

    public static function pushSubscriber($email)
    {
        $v = Subscription::getByEmail($email);

        if (!$v->subscribed) {
            return;
        }

        $info = ['email' => $v->email_address];
        $name = [];

        if ($v->firstname !== null) {
            $name[] = $v->firstname;
        }

        if ($v->lastname !== null) {
            $name[] = $v->lastname;
        }

        $info['name'] = implode(' ', $name);

        if ($v->phone !== null) {
            $info['phone'] = $v->phone;
        }

        \Mktr\Helper\Api::send('add_subscriber', $info);
    }

    private static function pushLog($id_order, $message)
    {
        @file_put_contents(
            MKTR_APP . 'Storage/install.log',
            date('Y-m-d H:i:s') . '[SAVE_ORDER] [' . (int) $id_order . '] ' . $message . "\n",
            FILE_APPEND
        );
    }

    public static function getByID($id, $new = false)
    {
        if ($new || !array_key_exists($id, self::$d)) {
            $class = get_called_class();
            self::$d[$id] = new $class();
            // self::$d[$id] = new static();
            if (_PS_VERSION_ >= 1.6) {
                /* @phpstan-ignore-next-line */
                self::$d[$id]->data = new \Order($id, Config::getLang(), Config::shop());
            } else {
                self::$d[$id]->data = new \Order($id);
            }
        }

        self::$curent = self::$d[$id];

        return self::$curent;
    }

    protected function getStatus()
    {
        /* @phpstan-ignore-next-line */
        return self::orderState($this->current_state);
    }

    public static function CustomerData($ID)
    {
        if (!array_key_exists($ID, self::$customerData)) {
            self::$customerData[$ID] = new \Customer($ID);
        }

        return self::$customerData[$ID];
    }

    public static function AdressData($ID)
    {
        if (!array_key_exists($ID, self::$adressData)) {
            self::$adressData[$ID] = new \Address($ID);
        }

        return self::$adressData[$ID];
    }

    protected function getEmail()
    {
        /** @phpstan-ignore-next-line */
        $customer = self::CustomerData($this->id_customer);

        return $customer->email;
    }

    protected function getFirstName()
    {
        $n = $this->getLastNameAndFirstName();

        return $n['firstname'];
    }

    protected function getLastName()
    {
        $n = $this->getLastNameAndFirstName();

        return $n['lastname'];
    }

    protected function getLastNameAndFirstName()
    {
        if (empty($this->tmp_names)) {
            /** @phpstan-ignore-next-line */
            $customer = self::AdressData($this->id_address_invoice);
            $customer1 = null;
            /* @phpstan-ignore-next-line */
            if ($customer->lastname === null || $customer->firstname === null || $customer->firstname == ' ' || $customer->lastname == ' ') {
                /** @phpstan-ignore-next-line */
                $customer1 = self::CustomerData($this->id_customer);
            }
            /* @phpstan-ignore-next-line */
            if (($customer->firstname === null || $customer->firstname == ' ') && $customer1->firstname !== null) {
                /** @phpstan-ignore-next-line */
                $fname = $customer1->firstname;
            } else {
                /** @phpstan-ignore-next-line */
                $fname = $customer->firstname;
            }
            /* @phpstan-ignore-line */
            if (($customer->lastname === null || $customer->lastname == ' ') && $customer1->lastname !== null) {
                /** @phpstan-ignore-next-line */
                $lname = $customer1->lastname;
            } else {
                /** @phpstan-ignore-next-line */
                $lname = $customer->lastname;
            }

            if (!empty($fname) && !empty($lname) && $fname != ' ' && $lname != ' ') {
                $nn = [$fname, $lname];
            } elseif (!empty($fname) && $fname != ' ') {
                $nn = explode(' ', $fname, 2);
            } elseif (!empty($lname) && $lname != ' ') {
                $nn = explode(' ', $lname, 2);
            } else {
                if ($customer1 === null) {
                    /** @phpstan-ignore-next-line */
                    $customer1 = self::CustomerData($this->id_customer);
                }
                /** @phpstan-ignore-next-line */
                $em = explode('@', $customer1->email);
                $nn = explode(' ', str_replace('_', ' ', $em[0]), 2);
            }

            if (!isset($nn[1])) {
                $nn[1] = ' ';
            }

            $this->tmp_names = [
                'firstname' => $nn[0],
                'lastname' => $nn[1],
            ];
        }

        return $this->tmp_names;
    }

    protected function getPhone()
    {
        $phone = null;
        /** @phpstan-ignore-next-line */
        $customer = self::AdressData($this->id_address_invoice);
        if (isset($customer->phone)) {
            if (!empty($customer->phone) && $customer->phone !== ' ') {
                $phone = $customer->phone;
            }
        }
        if ($phone === null && isset($customer->phone_mobile)) {
            if (!empty($customer->phone_mobile) && $customer->phone_mobile !== ' ') {
                $phone = $customer->phone_mobile;
            }
        }
        if ($phone === null) {
            return '';
        }

        return $phone;
    }

    protected function getCity()
    {
        /** @phpstan-ignore-next-line */
        $customer = self::AdressData($this->id_address_invoice);

        return $customer->city;
    }

    protected function getCounty()
    {
        /** @phpstan-ignore-next-line */
        $customer = self::AdressData($this->id_address_invoice);

        return $customer->country;
    }

    protected function getAddress()
    {
        /** @phpstan-ignore-next-line */
        $customer = self::AdressData($this->id_address_invoice);
        $adr = [];
        if (!empty($customer->address1)) {
            $adr[] = $customer->address1;
        }
        if (!empty($customer->address2)) {
            $adr[] = $customer->address2;
        }

        return implode(' ', $adr);
    }

    protected function getTax()
    {
        /* @phpstan-ignore-next-line */
        return $this->total_paid_tax_incl - $this->total_paid_tax_excl;
    }

    protected function getProductsData()
    {
        $i = 0;
        $products = [];
        foreach ($this->data->getProducts() as $p) {
            $pp = Product::getByID($p['id_product'], true);
            if ($p['unit_price_tax_incl'] <= 0) {
                continue;
            }
            $products[$i]['product_id'] = $pp->id;
            $products[$i]['sku'] = $pp->sku;
            $products[$i]['name'] = $pp->name;
            $products[$i]['url'] = $pp->url;
            $products[$i]['main_image'] = $pp->main_image;
            $products[$i]['category'] = $pp->category;
            $products[$i]['brand'] = $pp->brand;
            $products[$i]['quantity'] = $p['product_quantity'];
            // $products[$i]['price'] = $p['product_quantity'] * $p['total_price_tax_incl'];
            $products[$i]['price'] = $pp->price;
            $products[$i]['sale_price'] = round($p['unit_price_tax_incl'], 2);

            $variant = $pp->getVariant($p['product_attribute_id']);

            $products[$i]['variation_id'] = $variant['id'];
            $products[$i]['variation_sku'] = $variant['sku'];
            ++$i;
        }

        return $products;
    }

    protected function getProducts()
    {
        $i = 0;
        $products = [];
        foreach ($this->data->getProducts() as $p) {
            $pp = Product::getByID($p['id_product'], true);
            if ($p['unit_price_tax_incl'] <= 0) {
                continue;
            }
            $products[$i]['product_id'] = $pp->id;
            $products[$i]['quantity'] = $p['product_quantity'];

            // $products[$i]['price'] = $p['product_quantity'] * $p['total_price_tax_incl'];
            $products[$i]['price'] = round($p['unit_price_tax_incl'], 2);
            $variant = $pp->getVariant($p['product_attribute_id']);
            $products[$i]['variation_sku'] = $variant['sku'];
            ++$i;
        }

        return $products;
    }

    protected function getDiscountCode()
    {
        $discounts = $this->data->getCartRules();
        $d = '';
        if (!empty($discounts)) {
            $discountCode = [];

            foreach ($discounts as $discount) {
                $cartRule = new \CartRule((int) $discount['id_cart_rule']);

                $discountCode[] = $cartRule->code;
            }
            $d = implode('|', $discountCode);
        }

        return $d;
    }

    protected function toEvent($json = false)
    {
        $out = [];

        foreach ([
                     'number', 'email_address', 'phone', 'firstname', 'lastname', 'city', 'county', 'address',
                     'discount_value', 'discount_code', 'shipping', 'tax', 'total_value', 'products_api',
                 ] as $v) {
            if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $v)) {
                continue;
            }

            if ($v === 'products_api') {
                /* @phpstan-ignore-next-line */
                $out['products'] = $this->{$v};
            } else {
                /* @phpstan-ignore-next-line */
                $out[$v] = $this->{$v};
            }
        }

        return $json ? \Mktr\Helper\Valid::toJson($out) : $out;
    }

    protected function toApi()
    {
        $out = [];

        foreach ([
            'number', 'email_address', 'phone', 'firstname', 'lastname', 'city', 'county', 'address',
                     'discount_value', 'discount_code', 'shipping', 'tax', 'total_value', 'products_api',
                     ] as $v) {
            if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $v)) {
                continue;
            }

            if ($v === 'products_api') {
                /* @phpstan-ignore-next-line */
                $out['products'] = $this->{$v};
            } else {
                /* @phpstan-ignore-next-line */
                $out[$v] = $this->{$v};
            }
        }

        return $out;
    }
}
