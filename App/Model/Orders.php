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
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @docs        https://themarketer.com/resources/api
 **/

namespace Mktr\Model;

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

    private static $i = null;
    private static $curent = null;
    private static $d = [];
    private static $shop = null;
    private static $orderState = null;
    private static $customerData = [];
    private static $adressData = [];

    public static function i()
    {
        if (self::$i === null) {
            self::$i = new static();
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
            foreach (\OrderState::getOrderStates(\Mktr\Model\Config::getLang()) as $state) {
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

        if ($start_date !== null || $end_date !== null) {
            $wh = [];
            if ($start_date !== null) {
                $wh[] = " date_add >= '" . \pSQL($start_date) . "'";
            }

            if ($end_date !== null) {
                $wh[] = " date_add <= '" . \pSQL($end_date) . "'";
            }
            $sql .= ' WHERE' . implode('AND', $wh);
        }
        $sql .= ' ORDER BY `' . $i->orderBy . '` ' . $i->direction . ' LIMIT ' . $start . ', ' . $limit;

        $i->list = Config::db()->executeS($sql);

        return $i->list;
    }

    public static function getByID($id, $new = false)
    {
        if ($new || !array_key_exists($id, self::$d)) {
            self::$d[$id] = new static();
            self::$d[$id]->data = new \Order($id, Config::getLang(), Config::shop());
        }

        self::$curent = self::$d[$id];

        return self::$curent;
    }

    protected function getStatus()
    {
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
        $customer = self::CustomerData($this->id_customer);

        return $customer->email;
    }

    protected function getFirstName()
    {
        $customer = self::AdressData($this->id_address_invoice);
        if ($customer->firstname === null) {
            $customer = self::CustomerData($this->id_customer);
        }

        return $customer->firstname;
    }

    protected function getLastName()
    {
        $customer = self::AdressData($this->id_address_invoice);
        if ($customer->lastname === null) {
            $customer = self::CustomerData($this->id_customer);
        }

        return $customer->lastname;
    }

    protected function getPhone()
    {
        $customer = self::AdressData($this->id_address_invoice);

        return $customer->phone;
    }

    protected function getCity()
    {
        $customer = self::AdressData($this->id_address_invoice);

        return $customer->city;
    }

    protected function getCounty()
    {
        $customer = self::AdressData($this->id_address_invoice);

        return $customer->country;
    }

    protected function getAddress()
    {
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

    protected function getRefund()
    {
        foreach ($this->data->getOrderDetailList() as $v) {
            if (_PS_VERSION_ < 1.7) {
                $resume = \OrderSlip::getProductSlipResume($v['id_order_detail']);
                $v['total_refunded_tax_incl'] = $resume['amount_tax_incl'];
            }
            $this->refund = $this->refund + $v['total_refunded_tax_incl'];
        }

        return $this->refund;
    }

    protected function getTax()
    {
        return $this->total_paid_tax_incl - $this->total_paid_tax_excl;
    }

    protected function getProductsData()
    {
        $i = 0;
        $products = [];
        foreach ($this->data->getProducts() as $p) {
            $pp = Product::getByID($p['id_product'], true);
            $products[$i]['product_id'] = $pp->id;
            $products[$i]['sku'] = $pp->sku;
            $products[$i]['name'] = $pp->name;
            $products[$i]['url'] = $pp->url;
            $products[$i]['main_image'] = $pp->main_image;
            $products[$i]['category'] = $pp->category;
            $products[$i]['brand'] = $pp->brand;
            $products[$i]['quantity'] = $p['product_quantity'];
            $products[$i]['price'] = $p['product_quantity'] * $p['total_price_tax_incl'];
            $products[$i]['sale_price'] = round($p['total_price_tax_incl'], 2);

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
            $products[$i]['product_id'] = $pp->id;
            $products[$i]['quantity'] = $p['product_quantity'];
            $products[$i]['price'] = $p['product_quantity'] * $p['total_price_tax_incl'];
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
            'discount_value', 'discount_code', 'shipping', 'tax', 'total_value', 'products',
        ] as $v) {
            $out[$v] = $this->{$v};
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
            if ($v === 'products_api') {
                $out['products'] = $this->{$v};
            } else {
                $out[$v] = $this->{$v};
            }
        }

        return $out;
    }
}
