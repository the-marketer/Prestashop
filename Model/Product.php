<?php
/**
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @docs        https://themarketer.com/resources/api
 */

/**
 * Class ModelProduct.
 *
 * @method static id()
 * @method static sku()
 * @method static name()
 * @method static description()
 * @method static created_at()
 */
class ModelProduct
{
    const TYPE_COMBINATION = 'combinations';

    public static $dateFormat = 'Y-m-d H:i';
    public static $configs = [
        'idLang' => null,
        'context' => null,
        'limit' => 250,
        'order_by' => 'id_product',
        'order_way' => 'ASC',
    ];

    public static $attributes = null;

    public static $defStock = null;
    public static $realStock = 0;

    private static $init = null;
    private static $asset = null;
    private static $data = [];
    private static $products = [];
    private static $images = null;
    private static $prices = null;
    private static $pricesDate = null;
    private static $variation = null;

    private static $valueNames = [
        'id' => 'id',
        'sku' => 'reference',
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
        'special_price' => 'special',
        'sale_price_start_date' => 'getSalePriceStartDate',
        'sale_price_end_date' => 'getSalePriceEndDate',
        'availability' => 'getAvailability',
        'stock' => 'quantity',
        'media_gallery' => 'getMediaGallery',
        'variation' => 'getVariation',
        'created_at' => 'date_upd',
        'tax_class_id' => 'tax_class_id',
    ];

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }

        return self::$init;
    }

    public static function getIdLang()
    {
        if (self::$configs['idLang'] === null) {
            self::$configs['idLang'] = (int) Context::getContext()->language->id;
        }

        return self::$configs['idLang'];
    }

    public static function getContext()
    {
        if (self::$configs['context'] === null) {
            self::$configs['context'] = Context::getContext();
        }

        return self::$configs['context'];
    }

    public static function getCreatedAt()
    {
        return date(self::$dateFormat, strtotime(self::getValue('created_at')));
    }

    public static function getDescription()
    {
        return self::getValue('description');
    }

    public static function getName()
    {
        return self::getValue('name');
    }

    public static function getSku()
    {
        return self::getValue('sku');
    }

    public static function getId()
    {
        return self::getValue('id');
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getValue($name);
    }

    public function __call($name, $arguments)
    {
        return self::getValue($name);
    }

    public static function getValue($name)
    {
        if (isset(self::$data[$name])) {
            return self::$data[$name];
        }

        if (isset(self::$valueNames[$name])) {
            $v = self::$valueNames[$name];
            self::$data[$name] = self::$asset->{$v};

            if ($name === 'sku') {
                self::$data[$name] = empty(self::$data[$name]) ? self::getId() : self::$data[$name];
            }

            return self::$data[$name];
        }

        return null;
    }

    public static function toDigit($num = null, $digit = 2)
    {
        return $num === null ? null : number_format((float) $num, $digit, '.', '');
    }

    public static function getPrice()
    {
        return self::getPricesNow('price');
    }

    public static function getSalePrice()
    {
        return self::getPricesNow('sale_price');
    }

    private static function checkNow($type, $toCheck)
    {
        if (self::$attributes === null) {
            self::$attributes = [
                'color' => explode(',', Configuration::get(TheMarketer::COLOR_ATTRIBUTE)),
                'size' => explode(',', Configuration::get(TheMarketer::SIZE_ATTRIBUTE)),
            ];
        }

        return in_array($toCheck, self::$attributes[$type]);
    }

    private static function isSize($toCheck)
    {
        return self::checkNow('size', $toCheck);
    }

    private static function isColor($toCheck)
    {
        return self::checkNow('color', $toCheck);
    }

    public static function getSalePriceStartDate()
    {
        return self::getSalePriceDateNow('sale_price_start_date');
    }

    public static function getSalePriceEndDate()
    {
        return self::getSalePriceDateNow('sale_price_end_date');
    }

    public static function getMainImage()
    {
        return self::getImagesNow('main');
    }

    public static function getMediaGallery()
    {
        return self::getImagesNow('media_gallery');
    }

    public static function getAcquisitionPrice()
    {
        return self::toDigit(self::$asset->wholesale_price);
    }

    public static function getUrl()
    {
        return Context::getContext()->link->getProductLink(
            self::$asset,
            null,
            null,
            null,
            self::getIdLang(),
            null,
            null,
            false,
            false,
            true
        );
    }

    public static function getVariant($id = null)
    {
        if (self::$variation === null) {
            $combinations = [
                'id' => self::$asset->id,
                'sku' => self::$asset->reference,
            ];

            if (self::$asset->product_type === self::TYPE_COMBINATION) {
                $combination = self::$asset->getAttributeCombinationsById($id, self::getIdLang());

                foreach ($combination as $val) {
                    $combinations['id'] .= '_' . $val['id_attribute'];
                    $combinations['sku'] .= '_' . $val['attribute_name'];
                }
            }
            self::$variation = $combinations;
        }

        return self::$variation;
    }

    public static function getVariation()
    {
        if (self::$variation === null && self::$asset->product_type === self::TYPE_COMBINATION) {
            $combinations = [];
            $allCombinationsIds = self::$asset->getAttributeCombinations(self::getIdLang());

            foreach ($allCombinationsIds as $combination) {
                if (!isset($combinations[$combination['id_product_attribute']])) {
                    $price = self::getPrice();
                    $sale_price = empty((float) $combination['price']) ? ModelProduct::getSalePrice() : self::toDigit($combination['price']);
                    $combinations[$combination['id_product_attribute']] = [
                        'id' => [
                            self::$asset->id,
                        ],
                        'sku' => [
                            self::$asset->reference,
                        ],
                        'acquisition_price' => self::toDigit($combination['wholesale_price']),
                        'price' => max($price, $sale_price),
                        'sale_price' => $sale_price,
                        'availability' => self::getAvailability($combination['quantity']),
                        'stock' => $combination['quantity'],
                        'size' => null,
                        'color' => null,
                    ];
                    self::$realStock = self::$realStock + $combination['quantity'];
                }

                $combinations[$combination['id_product_attribute']]['id'][] = $combination['id_attribute'];
                $combinations[$combination['id_product_attribute']]['sku'][] = $combination['attribute_name'];

                if (self::isSize($combination['group_name'])) {
                    $combinations[$combination['id_product_attribute']]['size'] = $combination['attribute_name'];
                }

                if (self::isColor($combination['group_name'])) {
                    $combinations[$combination['id_product_attribute']]['color'] = $combination['attribute_name'];
                }
            }
            foreach ($combinations as $key => $val) {
                $combinations[$key]['id'] = implode('_', $val['id']);
                $combinations[$key]['sku'] = implode('_', $val['sku']);
            }

            self::$variation = $combinations;
        }

        return self::$variation;
    }

    public static function getDefaultStock()
    {
        if (self::$defStock === null) {
            self::$defStock = Configuration::get(TheMarketer::DEFAULT_STOCK);
        }

        return self::$defStock;
    }

    public static function getAvailability($qty = null)
    {
        $qty = isset($qty) ? $qty : self::$asset->quantity;
        if ($qty < 0) {
            $availability = self::getDefaultStock();
        } elseif ($qty == 0) {
            /** @noinspection PhpUnnecessaryBoolCastInspection */
            $availability = (bool) self::$asset->available_for_order ? 2 : 0;
        } else {
            $availability = 1;
        }

        return $availability;
    }

    public static function getStock()
    {
        return max(self::$realStock, self::$asset->quantity);
    }

    private static function getSalePriceDateNow($witch = null)
    {
        if (self::$pricesDate === null) {
            self::$pricesDate['sale_price_start_date'] = 0;
            self::$pricesDate['sale_price_end_date'] = 0;
            if (!empty(self::$asset->specificPrice)) {
                $v = self::$asset->specificPrice;
                $from = strtotime($v['from']);
                $to = strtotime($v['to']);

                if (self::$pricesDate['sale_price_start_date'] <= $from) {
                    self::$pricesDate['sale_price_start_date'] = $from;
                }

                if (self::$pricesDate['sale_price_end_date'] <= $to) {
                    self::$pricesDate['sale_price_end_date'] = $to;
                }
            }
            if (self::$pricesDate['sale_price_end_date'] != 0) {
                self::$pricesDate['sale_price_start_date'] = date(self::$dateFormat, self::$pricesDate['sale_price_start_date']);
                self::$pricesDate['sale_price_end_date'] = date(self::$dateFormat, self::$pricesDate['sale_price_end_date']);
            } else {
                self::$pricesDate['sale_price_start_date'] = null;
                self::$pricesDate['sale_price_end_date'] = null;
            }
        }

        return $witch === null ? null : self::$pricesDate[$witch];
    }

    private static function getPricesNow($witch = null)
    {
        if (self::$prices === null) {
            // $tax = (self::$asset->getTaxesRate() != 0);

            self::$prices['price'] = self::toDigit(self::$asset->getPriceWithoutReduct(false, null, 2));
            self::$prices['sale_price'] = self::toDigit(self::$asset->getPrice(true, null, 2));

            self::$prices['price'] = empty(self::$prices['price']) && !empty(self::$prices['sale_price']) ?
                self::$prices['sale_price'] : self::$prices['price'];

            self::$prices['sale_price'] = empty(self::$prices['sale_price']) ?
                self::$prices['price'] : self::$prices['sale_price'];

            self::$prices['price'] = max(self::$prices['sale_price'], self::$prices['price']);
        }

        return $witch === null ? null : self::$prices[$witch];
    }

    private static function getImagesNow($witch = null)
    {
        if (self::$images === null) {
            self::$images['main'] = '';

            $coverImageId = self::$asset->getCoverWs();
            $mainImgID = null;
            $aImages = null;
            self::$images['media_gallery']['image'] = [];

            if ((int) $coverImageId > 0) {
                $mainImgID = $coverImageId;
            }

            if ($mainImgID === null && self::$asset->product_type === 'combinations') {
                $Id = self::$asset->getDefaultIdProductAttribute();
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

            $pImages = self::$asset->getImages(self::getIdLang());

            if ($pImages) {
                foreach ($pImages as $pImage) {
                    $pImageId = (int) $pImage['id_image'];

                    if ($pImageId > 0) {
                        if ($mainImgID === null) {
                            $mainImgID = $pImageId;
                        } elseif ($mainImgID != $pImageId) {
                            self::$images['media_gallery']['image'][] = $pImageId;
                        }
                    }
                }
            }
            $name = self::$asset->link_rewrite;
            $name = is_array($name) ? $name[1] : $name;

            self::$images['main'] = Context::getContext()->link->getImageLink($name, self::$asset->id . '-' . $mainImgID, null);

            if (!empty(self::$images['media_gallery']['image'])) {
                foreach (self::$images['media_gallery']['image'] as $key => $imgId) {
                    self::$images['media_gallery']['image'][$key] = Context::getContext()->link->getImageLink($name, self::$asset->id . '-' . $imgId, null);
                }
            }
        }

        return $witch === null ? null : self::$images[$witch];
    }

    public static function getBrand()
    {
        $brand = new Manufacturer(self::$asset->id_manufacturer, self::getIdLang());

        return isset($brand->name) ? $brand->name : 'N/A';
    }

    public static function getCategory()
    {
        $cat = new Category(self::$asset->id_category_default, self::getIdLang());
        $parents = $cat->getParentsCategories(self::getIdLang());
        $p = [];
        foreach ($parents as $ch) {
            if (isset($ch['name'])) {
                $p[] = $ch['name'];
            }
        }
        krsort($p);

        return implode('|', $p);
    }

    public static function getProducts($page = 1)
    {
        $start = (($page - 1) * self::$configs['limit']);

        $sql = 'SELECT p.`id_product` AS id, product_shop.visibility, product_shop.active , pl.`id_lang` FROM `' . _DB_PREFIX_ . 'product` p
        ' . Shop::addSqlAssociation('product', 'p') .
        ' LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` ' . Shop::addSqlRestrictionOnLang('pl') . ')' .
        ' WHERE pl.`id_lang` = ' . self::getIdLang() .
        ' AND product_shop.`visibility` IN ("both", "catalog") ' .
        ' AND product_shop.`active` = 1 ' .
        ' ORDER BY p.`' . self::$configs['order_by'] . '` ' . self::$configs['order_way'] .
        ' LIMIT ' . $start . ',' . self::$configs['limit'];

        self::$products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        return self::$products;
    }

    public static function getProductByID($id, $full = false)
    {
        self::resetAll();
        self::$asset = new Product($id, $full, self::getIdLang());

        return self::init();
    }

    public static function getForFeed($id)
    {
        ModelProduct::getProductByID($id, true);

        $variation = null;

        if (ModelProduct::$asset->product_type === ModelProduct::TYPE_COMBINATION) {
            $variation = ModelProduct::getVariation();
        }

        $data = [
            'id' => ModelProduct::getId(),
            'sku' => ModelProduct::getSku(),
            'name' => ModelProduct::getName(),
            'description' => ModelProduct::getDescription(),
            'url' => ModelProduct::getUrl(),
            'main_image' => ModelProduct::getMainImage(),
            'category' => ModelProduct::getCategory(),
            'brand' => ModelProduct::getBrand(),
            'acquisition_price' => ModelProduct::getAcquisitionPrice(),
            'price' => (string) ModelProduct::getPrice(),
            'sale_price' => (string) ModelProduct::getSalePrice(),
            'sale_price_start_date' => ModelProduct::getSalePriceStartDate(),
            'sale_price_end_date' => ModelProduct::getSalePriceEndDate(),
            'availability' => ModelProduct::getAvailability(),
            'stock' => ModelProduct::getStock(),
            'media_gallery' => ModelProduct::getMediaGallery(),
            'variations' => [],
            'created_at' => ModelProduct::getCreatedAt(),
        ];

        if ($variation === null) {
            unset($data['variations']);
        } else {
            $data['variations']['variation'] = $variation;
        }

        if (empty($data['media_gallery']['image'])) {
            unset($data['media_gallery']);
        }

        return $data;
    }

    public static function getNewFeed($page = null)
    {
        $pro = [];

        $stop = false;

        if ($page !== null) {
            $stop = true;
        }

        $currentPage = $page === null ? 1 : $page;
        do {
            $products = ModelProduct::getProducts($currentPage);

            if ($stop) {
                $pages = 0;
            } else {
                $pages = count($products);
            }

            foreach ($products as $val) {
                $pro[] = ModelProduct::getForFeed($val['id']);
            }

            ++$currentPage;
        } while (0 < $pages);

        return $pro;
    }

    private static function resetAll()
    {
        self::$data = [];
        self::$images = null;
        self::$prices = null;
        self::$pricesDate = null;
        self::$variation = null;
    }
}
