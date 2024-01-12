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

class Product extends DataBase
{
    protected $attributes = [
        'id' => null,
        'sku' => null,
        'name' => null,
        'description' => null,
        'url' => null,
        'main_image' => null,
        'category' => null,
        'brand' => null,
        'acquisition_price' => null,
        'price' => null,
        'regular_price' => null,
        'sale_price' => null,
        'special_price' => null,
        'sale_price_start_date' => null,
        'sale_price_end_date' => null,
        'availability' => null,
        'stock' => null,
        'media_gallery' => null,
        'variations' => null,
        'created_at' => null,
    ];

    protected $ref = [
        'id' => 'id',
        'product_id' => 'id',
        'sku' => 'getSku',
        'reference' => 'reference',
        'name' => 'name',
        'description' => 'description',
        'url' => 'getUrl',
        'main_image' => 'getMainImage',
        'category' => 'getCategory',
        'brand' => 'getBrand',
        'acquisition_price' => 'wholesale_price',
        'price' => 'getPrice',
        'regular_price' => 'price',
        'sale_price' => 'getSalePrice',
        'sale_price_start_date' => 'getSalePriceStartDate',
        'sale_price_end_date' => 'getSalePriceEndDate',
        'availability' => 'getAvailability',
        'stock' => 'getStock',
        'media_gallery' => 'getMediaGallery',
        'variations' => 'variation',
        'variation' => 'getVariation',
        'created_at' => 'date_upd',
    ];

    protected $functions = [
        'getSku',
        'getUrl',
        'getMainImage',
        'getCategory',
        'getBrand',
        'getPrice',
        'getSalePrice',
        'getSalePriceStartDate',
        'getSalePriceEndDate',
        'getAvailability',
        'getStock',
        'getMediaGallery',
        'getVariation',
    ];

    protected $vars = [
        'variation',
    ];

    protected $cast = [
        'sale_price_start_date' => 'date',
        'sale_price_end_date' => 'date',
        'created_at' => 'date',
        'acquisition_price' => 'double',
    ];

    protected $orderBy = 'id_product';
    protected $direction = 'ASC';
    protected $dateFormat = 'Y-m-d H:i';
    protected $hide = ['variation', 'regular_price'];

    private static $i = null;
    private static $curent = null;
    private static $d = [];

    protected $realStock = 0;
    protected $isCombination = null;
    protected $img = null;
    protected $prices = null;
    protected $pricesDate = null;
    protected $reference = null;
    protected $var = null;
    protected $variant = [];

    const TYPE_COMBINATION = 'combinations';
    private static $defStock = null;
    private static $att = null;

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

    public static function getDefaultStock()
    {
        if (self::$defStock === null) {
            self::$defStock = Config::i()->default_stock;
        }

        return self::$defStock;
    }

    public static function getPage($num = 1, $limit = null)
    {
        $i = self::i();

        if ($limit === null) {
            $limit = $i->limit;
        }

        if ($num === null) {
            $num = 1;
        }
        $start = (($num - 1) * $limit);

        $sql = 'SELECT p.`id_product` AS id, product_shop.visibility, product_shop.active , pl.`id_lang` FROM ' .
        '`' . _DB_PREFIX_ . 'product` p ' . \Shop::addSqlAssociation('product', 'p') .
        ' LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` ' . \Shop::addSqlRestrictionOnLang('pl') . ')' .
        ' WHERE pl.`id_lang` = ' . Config::getLang() . ' AND product_shop.`visibility` IN ("both", "catalog", "search")' .
        ' AND product_shop.`active` = 1 ORDER BY p.`' . $i->orderBy . '` ' . $i->direction . ' LIMIT ' . $start . ', ' . $limit;

        $i->list = Config::db()->executeS($sql);

        return $i->list;
    }

    public static function getByID($id, $full = false, $new = false)
    {
        if ($new || !array_key_exists($id, self::$d)) {
            self::$d[$id] = new static();

            self::$d[$id]->data = new \Product($id, $full, Config::getLang(), Config::shop(), Config::getContext());
        }

        self::$curent = self::$d[$id];

        return self::$curent;
    }

    public static function getQty($id_product, $id_product_attribute, $cart_id)
    {
        $i = self::i();

        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'cart_product`' .
        ' WHERE `id_cart` = "' . $cart_id . '" AND `id_product_attribute` = "' . (int) $id_product_attribute . '" AND `id_product` = "' . $id_product . '"';

        return Config::db()->executeS($sql);
    }

    protected function getSku()
    {
        return $this->reference ? $this->reference : $this->id;
    }

    protected function getUrl()
    {
        return Config::getContext()->link->getProductLink(
            $this->data, null, null, null, Config::getLang(), null, null, false, false, true
        );
    }

    protected function getMainImage()
    {
        return $this->getImagesNow('main');
    }

    protected function getMediaGallery()
    {
        return $this->getImagesNow('media_gallery');
    }

    protected function getPrice()
    {
        return $this->getPricesNow('price');
    }

    protected function getSalePrice()
    {
        return $this->getPricesNow('sale_price');
    }

    protected function getSalePriceStartDate()
    {
        return $this->getSalePriceDateNow('sale_price_start_date');
    }

    protected function getSalePriceEndDate()
    {
        return $this->getSalePriceDateNow('sale_price_end_date');
    }

    protected function isSize($toCheck)
    {
        return $this->checkNow('size', $toCheck);
    }

    protected function isColor($toCheck)
    {
        return $this->checkNow('color', $toCheck);
    }

    protected function getCategory()
    {
        $cat = new \Category($this->data->id_category_default, Config::getLang());
        $parents = $cat->getParentsCategories(Config::getLang());
        $p = [];
        foreach ($parents as $ch) {
            if (isset($ch['name'])) {
                $p[] = $ch['name'];
            }
        }
        krsort($p);

        return empty($p) ? 'N/A' : implode('|', $p);
    }

    protected function getBrand()
    {
        $brand = new \Manufacturer($this->data->id_manufacturer, Config::getLang());

        return isset($brand->name) ? $brand->name : 'N/A';
    }

    protected function isCombination()
    {
        if ($this->isCombination === null) {
            if (_PS_VERSION_ < '1.7.8.0') {
                $this->isCombination = $this->data->hasAttributes();
            } else {
                $this->isCombination = $this->data->product_type === 'combinations';
            }
        }

        return $this->isCombination;
    }

    protected function getImagesNow($witch = null)
    {
        if ($this->img === null) {
            $i['main'] = '';

            $coverImageId = $this->data->getCoverWs();
            $mainImgID = null;
            $aImages = null;
            $i['media_gallery'] = [];

            if ((int) $coverImageId > 0) {
                $mainImgID = $coverImageId;
            }

            if ($mainImgID === null && $this->isCombination()) {
                $Id = $this->data->getDefaultIdProductAttribute();
                if ($Id) {
                    $aImages = Product::_getAttributeImageAssociations($Id);
                }
            }

            if (!$mainImgID === null && $aImages !== null) {
                foreach ($aImages as $attrImageId) {
                    if ((int) $attrImageId > 0) {
                        $mainImgID = $attrImageId;
                        break;
                    }
                }
            }

            $pImages = $this->data->getImages(Config::getLang());

            if ($pImages) {
                foreach ($pImages as $pImage) {
                    $pImageId = (int) $pImage['id_image'];

                    if ($pImageId > 0) {
                        if ($mainImgID === null) {
                            $mainImgID = $pImageId;
                        } elseif ($mainImgID != $pImageId) {
                            $i['media_gallery']['image'][] = $pImageId;
                        }
                    }
                }
            }
            $name = $this->data->link_rewrite;
            $name = is_array($name) ? $name[1] : $name;

            $i['main'] = Config::getContext()->link->getImageLink($name, $this->data->id . '-' . $mainImgID, null);

            if (!empty($i['media_gallery']['image'])) {
                foreach ($i['media_gallery']['image'] as $key => $imgId) {
                    $i['media_gallery']['image'][$key] = Config::getContext()->link->getImageLink($name, $this->data->id . '-' . $imgId, null);
                }
            } else {
                $i['media_gallery']['image'][] = $i['main'];
            }

            $this->img = $i;
        }

        return $witch === null ? null : $this->img[$witch];
    }

    protected function getPricesNow($witch = null)
    {
        if ($this->prices === null) {
            // $tax = (self::$asset->getTaxesRate() != 0);

            $p['price'] = $this->toDigit($this->data->getPriceWithoutReduct(false, null, 2));
            $p['sale_price'] = $this->toDigit($this->data->getPrice(true, null, 2));

            $p['price'] = empty($p['price']) && !empty($p['sale_price']) ?
                $p['sale_price'] : $p['price'];

            $p['sale_price'] = empty($p['sale_price']) ?
                $p['price'] : $p['sale_price'];

            $p['price'] = max($p['sale_price'], $p['price']);
            $this->prices = $p;
        }

        return $witch === null ? null : $this->prices[$witch];
    }

    protected function getSalePriceDateNow($witch = null)
    {
        if ($this->pricesDate === null) {
            $pricesDate['sale_price_start_date'] = 0;
            $pricesDate['sale_price_end_date'] = 0;

            if (!empty($this->data->specificPrice)) {
                $v = $this->data->specificPrice;
                $from = strtotime($v['from']);
                $to = strtotime($v['to']);
                if ($pricesDate['sale_price_start_date'] <= $from) {
                    $pricesDate['sale_price_start_date'] = $from;
                }

                if ($pricesDate['sale_price_end_date'] <= $to) {
                    $pricesDate['sale_price_end_date'] = $to;
                }
            }

            if ($pricesDate['sale_price_end_date'] != 0) {
                $pricesDate['sale_price_start_date'] = \DateTime::createFromFormat('U', $pricesDate['sale_price_start_date']);
                $pricesDate['sale_price_end_date'] = \DateTime::createFromFormat('U', $pricesDate['sale_price_end_date']);
            } else {
                $pricesDate['sale_price_start_date'] = null;
                $pricesDate['sale_price_end_date'] = null;
            }
            $this->pricesDate = $pricesDate;
        }

        return $witch === null ? null : $this->pricesDate[$witch];
    }

    protected function checkNow($type, $toCheck)
    {
        if (self::$att === null) {
            self::$att = [
                'color' => Config::i()->color,
                'size' => Config::i()->size,
            ];
        }

        return in_array(strtolower($toCheck), self::$att[$type]);
    }

    protected function getAvailability($qty = null)
    {
        $qty = isset($qty) ? $qty : $this->data->quantity;
        if ($qty < 0) {
            $availability = self::getDefaultStock();
        } elseif ($qty == 0) {
            /** @noinspection PhpUnnecessaryBoolCastInspection */
            $availability = (bool) $this->data->available_for_order ? 2 : 0;
        } else {
            $availability = 1;
        }

        return $availability;
    }

    protected function getStock()
    {
        if ($this->isCombination()) {
            $this->getVariation();
        }

        return max($this->realStock, $this->data->quantity);
    }

    protected function getVariation()
    {
        if ($this->var === null && $this->isCombination()) {
            $combinations = [];
            $allCombinationsIds = $this->data->getAttributeCombinations(Config::getLang());

            foreach ($allCombinationsIds as $combination) {
                if (!isset($combinations[$combination['id_product_attribute']])) {
                    $price = self::getPrice();
                    $sale_price = empty((float) $combination['price']) ? $this->getSalePrice() : $this->toDigit($combination['price']);

                    if (0 >= $price || 0 >= $sale_price) {
                        continue;
                    }

                    if (0 >= $combination['quantity']) {
                        $combination['quantity'] = self::getDefaultStock();
                    }

                    $combinations[$combination['id_product_attribute']] = [
                        'id' => [
                            $this->data->id,
                        ],
                        'sku' => [
                            $this->data->reference,
                        ],
                        'acquisition_price' => $this->toDigit($combination['wholesale_price']),
                        'price' => max($price, $sale_price),
                        'sale_price' => $sale_price,
                        'availability' => $this->getAvailability($combination['quantity']),
                        'stock' => $combination['quantity'],
                        'size' => null,
                        'color' => null,
                    ];
                    $this->realStock += $combination['quantity'];
                }

                $combinations[$combination['id_product_attribute']]['id'][] = $combination['id_attribute'];
                $combinations[$combination['id_product_attribute']]['sku'][] = $combination['attribute_name'];

                if ($this->isSize($combination['group_name'])) {
                    $combinations[$combination['id_product_attribute']]['size'] = $combination['attribute_name'];
                }

                if ($this->isColor($combination['group_name'])) {
                    $combinations[$combination['id_product_attribute']]['color'] = $combination['attribute_name'];
                }
            }
            foreach ($combinations as $key => $val) {
                $combinations[$key]['id'] = implode('_', $val['id']);
                $combinations[$key]['sku'] = implode('_', $val['sku']);
            }

            $this->var = ['variation' => $combinations];
        }

        return $this->var;
    }

    protected function getVariant($id = null)
    {
        if (!array_key_exists($id, $this->variant)) {
            $combinations = [
                'id' => $this->id,
                'sku' => $this->sku,
            ];

            if ($this->isCombination()) {
                $combination = $this->data->getAttributeCombinationsById($id, Config::getLang());

                foreach ($combination as $val) {
                    $combinations['id'] .= '_' . $val['id_attribute'];
                    $combinations['sku'] .= '_' . $val['attribute_name'];
                }
            }

            $this->variant[$id] = $combinations;
        }

        return $this->variant[$id];
    }

    protected function toFeed()
    {
        return $this->toArray(['variations', 'media_gallery']);
    }
}
