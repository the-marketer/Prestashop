<?php
/**
* theMarketer V1.0.0 module
* for Prestashop v1.7.X.
*
* @author themarketer.com
* @copyright  2022-2023 theMarketer.com
* @license    http:// opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
$link = '';
$sum_price = '';
$normal_price = '';
$color = '';
include '../../config/config.inc.php';
include '../../init.php';
$id_lang = Context::getContext()->language->id;
$start = 0;
$limit = 10000000000;
$order_by = 'id_product';
$order_way = 'ASC';
$xml_schema_arr = [];
$prosnew = Db::getInstance()->executeS('SELECT p.id_product as id_product, p.active as active, p.wholesale_price as wholesale_price,
p.date_add as date_add, pl.description as description, pl.name as name, pa.id_product_attribute as id_product_attribute, GROUP_CONCAT(DISTINCT(pal.name) SEPARATOR \',\') as combination,
p.price as normprice, pa.reference as reference, pq.quantity as quantity,  pai.id_image AS attribute_color_image
FROM ' . _DB_PREFIX_ . 'product p
LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (p.id_product = pl.id_product)
LEFT JOIN ' . _DB_PREFIX_ . 'category_product cp ON (p.id_product = cp.id_product)
LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON (cp.id_category = cl.id_category)
LEFT JOIN ' . _DB_PREFIX_ . 'category c ON (cp.id_category = c.id_category)
LEFT JOIN ' . _DB_PREFIX_ . 'product_tag pt ON (p.id_product = pt.id_product)
LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON (p.id_product = pa.id_product)
LEFT JOIN `' . _DB_PREFIX_ .
    'product_attribute_image` AS pai ON pai.`id_product_attribute` = pa.`id_product_attribute`
LEFT JOIN ' . _DB_PREFIX_ .
    'product_attribute_combination pac ON (pac.id_product_attribute = pa.id_product_attribute)
LEFT JOIN ' . _DB_PREFIX_ .
    'stock_available pq ON (p.id_product = pq.id_product AND pa.id_product_attribute = pq.id_product_attribute)
LEFT JOIN ' . _DB_PREFIX_ .
    'attribute_lang al ON (al.id_attribute = pac.id_attribute)
LEFT JOIN ' . _DB_PREFIX_ .
    'attribute_lang pal ON (pac.id_attribute = pal.id_attribute)
WHERE pl.id_lang = ' . $id_lang . '
AND cl.id_lang = ' . $id_lang . '
AND p.id_shop_default = 1
AND c.id_shop_default = 1
GROUP by p.id_product, pl.description, pl.name, pa.id_product_attribute, p.price, pa.reference, pq.quantity,  pai.id_image');
// check auth
if (Configuration::get('THEMARKETER_REST_KEY') == Tools::getValue('key')) {
    $all_products = Product::getProducts(
        $id_lang,
        $start,
        $limit,
        $order_by,
        $order_way,
        $id_category = false,
        $only_active = false,
        $context = null
    );
    // start xml schema
    $xml_schema = '<?xml version=\'1.0\' encoding=\'UTF-8\'?><products>';
    // get products data
    foreach ($prosnew as $product_key => $product_val) {
        if ($product_val['active'] == 1) {
            $combinations = 0;
            $product_id = $product_val['id_product'];
            $product = new Product($product_id, true, $id_lang);

            $combinations = $product->getAttributeCombinations($id_lang, true);
            $product_sku = $product_val['reference'];
            $product_name = $product_val['name'];
            $product_description = htmlentities($product_val['description']);
            $product_url = Context::getContext()->link->getProductLink($product_id);
            // main image
            $img = $product->getCover($product->id);
            $image_type = ImageType::getFormattedName('large');
            $product_main_image = $link->getImageLink(isset($product->link_rewrite) ? $product->link_rewrite : $product->name, (int) $img['id_image'], $image_type);
            $cats = Product::getProductCategoriesFull($product_id, $id_lang);
            $cat = '';
            foreach ($cats as $c) {
                $cat .= $c['name'] . '|';
            }
            $product_category = mb_substr($cat, 0, -1);
            $manufacturer = new Manufacturer($product_id, $id_lang);
            if (!empty($manufacturer->name)) {
                $product_brand = $manufacturer->name;
            } else {
                $product_brand = 'N/A';
            }
            $product_acquisition_price = round($product_val['wholesale_price'], 2);
            $specific_price_output = true;
            $price = $product->getPrice(true);

            $normprice = $price;
            $product_price = round($product_val['normprice'], 2);
            // get discount
            $discount_price_val = 0;
            $discount_price_per = 0;
            $id_customer = (isset($context->customer) ? $context->customer->id : 0);
            $id_group = (isset($tcontext->customer) ? $tcontext->customer->id_default_group :
                1);
            $id_country = $id_customer ? Customer::getCurrentCountry($id_customer) :
                Configuration::get('PS_COUNTRY_DEFAULT');
            $id_currency = Configuration::get('PS_CURRENCY_DEFAULT');
            $id_shop = Context::getContext()->shop->id;
            $discount = SpecificPrice::getSpecificPrice(
                $product_id,
                $id_shop,
                $id_currency,
                $id_country,
                null,
                null,
                true,
                $id_customer
            );
            if (isset($discount['id_specific_price']) && $discount['id_specific_price'] > 0) {
                if ($discount['reduction_type'] == 'amount') {
                    $product_price_discount = $product_price - $discount['reduction'];
                    $reduction = $discount['reduction'];
                    $discount_price_val = $discount['reduction'];
                } elseif ($discount['reduction_type'] == 'percentage') {
                    $discount_price_per = $discount['reduction'];
                    $discount_price = round($product_price, 2);
                    $discount_price_val = $discount_price * $discount['reduction'];
                    $product_price_discount = round($product_price - $discount_price_val, 2);
                }
                $product_sale_price = round($price, 2);
                if ($discount['from'] != '0000-00-00 00:00:00') {
                    $product_sale_price_start_date = date('Y-m-d H:i', strtotime($discount['from']));
                } else {
                    $product_sale_price_start_date = '2020-01-01 00:00';
                }
                if ($discount['to'] != '0000-00-00 00:00:00') {
                    $product_sale_price_end_date = date('Y-m-d H:i', strtotime($discount['to']));
                } else {
                    $product_sale_price_end_date = '2100-01-01 00:00';
                }
            } else {
                $reduction = 0;
                $product_sale_price = $price;
                $product_sale_price_start_date = '2020-01-01 00:00';
                $product_sale_price_end_date = '2100-01-01 00:00';
            }
            // get stock
            if ((Product::getQuantity($product_id) / 10) > 0) {
                $availability = 1;
                $availability_supplier = 2;
            } else {
                $availability = 0;
                $availability_supplier = 0;
            }
            $product_quantity = Product::getQuantity($product_id);
            $product_availability = $availability;
            $product_availability_supplier = $availability_supplier;
            // images list
            $images = $product->getImages($id_lang);
            $media = '';
            foreach ($images as $img) {
                $image_type = ImageType::getFormattedName('large');
                $media .= '<image>' . $link->getImageLink(isset($product->link_rewrite) ? $product->link_rewrite : $product->name, (int) $img['id_image'], $image_type) . '</image>';
            }
            // compinations
            $compination = '';
            $qty = 0;
            if (count($combinations) > 0) {
                $extra_attr = [];
                $combarr = [];
                $qty = 0;

                foreach ($combinations as $key => $item) {
                    $combarr[$item['id_product_attribute']][$key] = $item;
                }

                ksort($combarr, SORT_NUMERIC);
                $colorsize = [];
                foreach ($combarr as $k => $cv) {
                    foreach ($cv as $c) {
                        $colorsize[$k]['quantity'] = $c['quantity'];
                        $colorsize[$k]['add_price'] = $c['price'];
                        $colorsize[$k]['id_product_attribute'] = $c['id_product_attribute'];
                        $colorsize[$k]['reference'] = $c['reference'];
                        if ($c['id_attribute'] == 1) {
                            $colorsize[$k]['size'] = $c['attribute_name'];
                        }
                        if ($c['is_color_group'] == 1) {
                            $colorsize[$k]['color'] = $c['attribute_name'];
                        }
                    }
                }
                $unique_array = [];
                foreach ($combinations as $element) {
                    $hash = $element['id_product_attribute'];
                    $unique_array[$hash] = $element;
                }
                $result = array_values($unique_array);
                $result = Db::getInstance()->executeS('SELECT pa.`id_product_attribute`, pa.`price`, pa.`reference`, ag.`id_attribute_group`, pai.`id_image`, agl.`name` AS group_name, al.`name` AS attribute_name,
                                a.`id_attribute`
                            FROM `' . _DB_PREFIX_ . 'product_attribute` pa
                            ' . Shop::addSqlAssociation(
                    'product_attribute',
                    'pa'
                ) . '
                            LEFT JOIN `' . _DB_PREFIX_ .
                    'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
                            LEFT JOIN `' . _DB_PREFIX_ .
                    'attribute` a ON a.`id_attribute` = pac.`id_attribute`
                            LEFT JOIN `' . _DB_PREFIX_ .
                    'attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
                            LEFT JOIN `' . _DB_PREFIX_ .
                    'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = ' .
                    $id_lang . ')
                            LEFT JOIN `' . _DB_PREFIX_ .
                    'attribute_group_lang` agl ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = ' .
                    $id_lang . ')
                            LEFT JOIN `' . _DB_PREFIX_ .
                    'product_attribute_image` pai ON pai.`id_product_attribute` = pa.`id_product_attribute`
                            WHERE pa.`id_product` = ' . $product_id . '
							GROUP BY pa.`id_product_attribute`, pai.`id_image`,a.`id_attribute`
                            ORDER BY pa.`id_product_attribute`');
                $data = [];
                foreach ($result as $cs) {
                    $attr = $cs['id_attribute'];
                    $iscolor = Db::getInstance()->executeS('
						SELECT `group_type`
						FROM `' . _DB_PREFIX_ . 'attribute_group`
						WHERE `id_attribute_group` = (
							SELECT `id_attribute_group`
							FROM `' . _DB_PREFIX_ . 'attribute`
							WHERE `id_attribute` = ' . $attr . ')
						AND group_type = \'color\'');
                    if ($iscolor) {
                        $cs['color'] = 1;
                    } else {
                        $cs['color'] = '';
                    }
                    if ($cs['id_attribute_group'] == 1) {
                        $cs['size'] = 1;
                    } else {
                        $cs['size'] = '';
                    }
                    $itemName = $cs['id_product_attribute'];
                    if (!array_key_exists($itemName, $data)) {
                        $data[$itemName] = [];
                    }
                    $data[$itemName][] = $cs;
                }
                foreach ($combarr as $ck => $combval) {
                    $attr_product = $data[$ck];
                    $color = '';
                    $size = '';
                    foreach ($attr_product as $atrname) {
                        if ($atrname['color'] == 1) {
                            $color = $atrname['attribute_name'];
                        }
                        if ($atrname['size'] == 1) {
                            $size = $atrname['attribute_name'];
                        }
                        // extra attr
                        if (empty($atrname['size']) && empty($atrname['color'])) {
                            $groupname = strtolower(str_replace(' ', '_', $atrname['group_name']));
                            $attr_value = $atrname['attribute_name'];
                            $extra_attr[$atrname['id_attribute']] = [
                                    'name' => $groupname,
                                    'value' => $attr_value,
                                    'extra_price' => $atrname['price'],
                                ];
                        } else {
                            $extra_attr = '';
                        }
                    }
                    if ($colorsize[$ck]['quantity'] > 0) {
                        $avail = 1;
                    } else {
                        $avail = 0;
                    }
                    if ($size || $color && $avail > 0) {
                        $net_price = round($product_price + $colorsize[$ck]['add_price'], 2);
                        $normal_price = ($net_price / 100) * 24 + $net_price;
                        if ($discount_price_per > 0) {
                            $net_price = round($product_price + $colorsize[$ck]['add_price'], 2);
                            $disc_value = ($net_price / 100) * (100 * $discount_price_per);
                            $sum_price = (($net_price - $disc_value) / 100) * 24 + ($net_price - $disc_value);
                            $normal_price = ($net_price / 100) * 24 + $net_price;
                        }
                        if ($discount_price_val > 0) {
                            $net_price = round($product_price + $colorsize[$ck]['add_price'], 2);
                            $sum_price = ((($net_price / 100) * 24) + $net_price) - $discount_price_val;
                            $normal_price = ((($net_price / 100) * 24) + $net_price);
                        }
                        if (empty($colorsize[$ck]['reference'])) {
                            $colorsize[$ck]['reference'] = $product_sku . '_' . $ck;
                        } else {
                            $colorsize[$ck]['reference'] = $colorsize[$ck]['reference'];
                        }
                        $varref = $product_sku;
                        if ($sum_price > $normal_price) {
                            $sum_price = $normal_price;
                        } else {
                            $sum_price = $sum_price;
                        }
                        $compination .= '<variation>';
                        $compination .= '<id>' . $ck . '</id>';
                        $compination .= '<sku>' . $colorsize[$ck]['reference'] . '</sku>';
                        $compination .= '<acquisition_price>' . $product_acquisition_price .
                            '</acquisition_price>';
                        $compination .= '<price>' . round($normal_price, 2) . '</price>';
                        $compination .= '<sale_price>' . round($sum_price, 2) . '</sale_price>';
                        $color = $color ?? null;
                        if ($size) {
                            $compination .= '<size><![CDATA[' . $size . ']]></size>';
                        } else {
                        }
                        if ($color) {
                            $compination .= '<color><![CDATA[' . $color . ']]></color>';
                        } else {
                        }
                        $compination .= '<availability>' . $avail . '</availability>';
                        $compination .= '<stock>' . $colorsize[$ck]['quantity'] . '</stock>';
                        $compination .= '</variation>';
                        $qty = $colorsize[$ck]['quantity'] + $qty;
                    } else {
                        $compination .= '';
                        $qty = 0 + $qty;
                    }
                }
            }
            if ($qty > 0) {
                $product_quantity = $qty;
            } else {
                $product_quantity = $product_quantity;
            }
            if ($product_quantity < 0) {
                $product_quantity = 0;
            } else {
                $product_quantity = $product_quantity;
            }

            if (!empty($media)) {
                $media_tag = ' <media_gallery>' . $media . '</media_gallery>';
            } else {
                $media_tag = '';
            }
            if (!empty($compination)) {
                $variations = '<variations>' . $compination . '</variations>';
            } else {
                $variations = '';
            }
            if (count($combinations) == 0) {
                if ($normal_price < $normprice) {
                    $normal_price = $normprice;
                } else {
                    $normal_price = $normal_price;
                }
                if (empty($product_sku)) {
                    $product_sku = 'sku_' . $product_id;
                } else {
                    $product_sku = $product_sku;
                }
                $image_type = ImageType::getFormattedName('large');
                $xml_schema_arr[] = '
					<product>
						  <id>' . $product_id . '</id>
						  <sku>' . $product_sku . '</sku>
						  <name><![CDATA[' . $product_name . ']]></name>
						  <description><![CDATA[' . $product_description . ']]></description>
						  <url><![CDATA[' . $product_url . ']]></url>
						  <main_image>' . $product_main_image . '</main_image>
						  <category><![CDATA[' . $product_category . ']]></category>
						  <brand><![CDATA[' . $product_brand . ']]></brand>
						  <acquisition_price>' . $product_acquisition_price .
                    '</acquisition_price>
						  <price>' . round($normal_price, 2) . '</price>
						  <sale_price>' . round($normprice, 2) . '</sale_price>
						  <sale_price_start_date>' . $product_sale_price_start_date .
                    '</sale_price_start_date>
						  <sale_price_end_date>' . $product_sale_price_end_date .
                    '</sale_price_end_date>
						  <availability>' . $product_availability . '</availability>
						  <stock>' . $product_quantity . '</stock>		
						  ' . $media_tag . '
						  ' . $variations . '
						  <created_at>' . date('Y-m-d H:i', strtotime($product_val['date_add'])) .
                    '</created_at>
						  <extra_attributes></extra_attributes>
					</product>';
            } else {
                $prodattr = Db::getInstance()->executeS('SELECT pa.*, ag.id_attribute_group, ag.is_color_group, agl.name AS group_name, al.name AS attribute_name, a.id_attribute, pa.price as price,  pai.id_image AS attribute_color_image
					FROM ' . _DB_PREFIX_ . 'product_attribute pa
					LEFT JOIN ' . _DB_PREFIX_ .
                                'product_attribute_combination pac ON pac.id_product_attribute = pa.id_product_attribute
					LEFT JOIN `' . _DB_PREFIX_ .
                                'product_attribute_image` AS pai ON pai.`id_product_attribute` = pa.`id_product_attribute`
					LEFT JOIN ' . _DB_PREFIX_ . 'attribute a ON a.id_attribute = pac.id_attribute
					LEFT JOIN ' . _DB_PREFIX_ .
                                'attribute_group ag ON ag.id_attribute_group = a.id_attribute_group
					LEFT JOIN ' . _DB_PREFIX_ .
                                'attribute_lang al ON (a.id_attribute = al.id_attribute AND al.id_lang = ' . $id_lang .
                                ')
					LEFT JOIN ' . _DB_PREFIX_ .
                                'attribute_group_lang agl ON (ag.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ' .
                                $id_lang . ')
					WHERE pa.id_product = ' . $product_id . '
					ORDER BY pa.id_product_attribute');

                foreach ($prodattr as $extra_key => $extra) {
                    $sqlid_product_attribute = $extra['id_product_attribute'];
                    $extravalue = $extra['attribute_name'];
                    $extraextra_price = $extra['price'];
                    $extraname = str_replace(' ', '_', $extra['group_name']);

                    $product_price = (($product_price / 100) * $product->tax_rate) + $product_price;
                    if (empty($product_sku)) {
                        $product_sku = 'sku_' . $product_id;
                    } else {
                        $product_sku = $product_sku;
                    }
                    $img = $product->getCover($product->id);
                    $image_type = ImageType::getFormattedName('large');
                    $main_img = $link->getImageLink(isset($product->link_rewrite) ? $product->link_rewrite : $product->name, (int) $img['id_image'], $image_type);
                    if (count($images) > 1) {
                        $main_img = $main_img;
                    } else {
                        $main_img = $product_main_image;
                    }
                    $xml_schema_arr[] = '
						<product>
								  <id>' . $product_id . '_' . $sqlid_product_attribute . '</id>
								  <sku>' . $product_sku . '</sku>
								  <name><![CDATA[' . $product_name . ' - ' . $extravalue . ']]></name>
								  <description><![CDATA[' . $product_description . ']]></description>
								  <url><![CDATA[' . $product_url . ']]></url>
								  <main_image>' . $main_img . '</main_image>
								  <category><![CDATA[' . $product_category . ']]></category>
								  <brand><![CDATA[' . $product_brand . ']]></brand>
								  <acquisition_price>' . $product_acquisition_price .
                        '</acquisition_price>
								  <price>' . round($product_price, 2) . '</price>
								  <sale_price>' . round($price, 2) . '</sale_price>
								  <sale_price_start_date>' . $product_sale_price_start_date .
                        '</sale_price_start_date>
								  <sale_price_end_date>' . $product_sale_price_end_date .
                        '</sale_price_end_date>
								  <availability>' . $product_availability . '</availability>
								  <stock>' . $product_quantity . '</stock>		
								  ' . $media_tag . '
								  <created_at>' . date('Y-m-d H:i', strtotime($product_val['date_add'])) .
                        '</created_at>
								  <extra_attributes><' . $extraname . '><![CDATA[' . $extravalue .
                        ']]></' . $extraname . '></extra_attributes>
						</product>';
                }
            }
        } else {
        }
    }
    $xml_schema_arr = array_unique($xml_schema_arr);

    foreach ($xml_schema_arr as $xmlitem) {
        $xml_schema .= $xmlitem;
    }
    // end xml schema
    $xml_schema .= '</products>';
    // create xml file
    $filename = 'products_feed_' . Tools::getValue('key') . '.xml';
    $xmlfile = fopen($filename, 'w');
    fwrite($xmlfile, $xml_schema);
    fclose($xmlfile);
    echo 'Products Feed URL saved at: ' . Tools::getHttpHost(true) . __PS_BASE_URI__ .
        'modules/themarketer/products_feed_' . Tools::getValue('key') . '.xml';
} else {
}
