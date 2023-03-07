<?php
/**
* theMarketer V1.0.0 module
* for Prestashop v1.7.X.
*
* @author themarketer.com
* @copyright  2022-2023 theMarketer.com
* @license    http:// opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
if (!defined('_PS_VERSION_')) {
    exit;
}

class TheMarketer extends Module
{
    public const TRACKING_KEY = 'THEMARKETER_TRACKING_KEY';
    public const REST_KEY = 'THEMARKETER_REST_KEY';
    public const TM_API_URL = 'https://t.themarketer.com/api/v1';
    public const TM_CUSTOMER_ID = 'THEMARKETER_CUSTOMER_ID';
    public const ORDERS_FEED_ALLOW = 'THEMARKETER_ORDERS_FEED_ALLOW';
    public const ORDERS_FEED_DATE = 'THEMARKETER_ORDERS_FEED_DATE';
    public const ORDERS_FEED_LINK = 'THEMARKETER_ORDERS_FEED_LINK';
    public const PRODUCTS_FEED_LINK = 'THEMARKETER_PRODUCTS_FEED_LINK';
    public const PRODUCTS_FEED_CRON = 'THEMARKETER_PRODUCTS_FEED_CRON';
    public const CATEGORIES_FEED_LINK = 'THEMARKETER_CATEGORIES_FEED_LINK';
    public const BRANDS_FEED_LINK = 'THEMARKETER_BRANDS_FEED_LINK';
    public const REVIEWS_FEED_LINK = 'THEMARKETER_REVIEWS_FEED_LINK';
    public const TM_ENABLE_NOTIFICATIONS = 'THEMARKETER_ENABLE_NOTIFICATIONS';
    public const TM_ENABLE_REVIEWS = 'THEMARKETER_ENABLE_REVIEWS';

    public function __construct()
    {
        $this->name = 'themarketer';
        $this->version = '1.0.0';
        $this->tab = 'advertising_marketing';
        $this->author = 'Themarketer.com';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('TheMarketer Platform');
        $this->description = $this->l('Integrates themarketer.com platform.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    public function install()
    {
        if (parent::install() &&
        $this->registerHook('actionFrontControllerSetMedia') &&
        $this->registerHook('displayHeader') &&
        $this->registerHook('displayFooterAfter') &&
        $this->registerHook('displayAfterBodyOpeningTag') &&
        $this->registerHook('displayAfterBodyClosingTag') &&
        $this->registerHook('actionModuleRegisterHookAfter') &&
        Configuration::updateValue(self::TRACKING_KEY, '') &&
        Configuration::updateValue(self::REST_KEY, '') &&
        Configuration::updateValue(self::TM_CUSTOMER_ID, '') &&
        Configuration::updateValue(self::ORDERS_FEED_ALLOW, '') &&
        Configuration::updateValue(self::ORDERS_FEED_DATE, '') &&
        Configuration::updateValue(self::ORDERS_FEED_LINK, '') &&
        Configuration::updateValue(self::PRODUCTS_FEED_LINK, '') &&
        Configuration::updateValue(self::PRODUCTS_FEED_CRON, '') &&
        Configuration::updateValue(self::CATEGORIES_FEED_LINK, '') &&
        Configuration::updateValue(self::BRANDS_FEED_LINK, '') &&
        Configuration::updateValue(self::REVIEWS_FEED_LINK, '') &&
        Configuration::updateValue(self::TM_ENABLE_NOTIFICATIONS, '') &&
        Configuration::updateValue(self::TM_ENABLE_REVIEWS, '') &&
        $this->registerHook('actionValidateOrder') &&
        $this->registerHook('displayOrderConfirmation') &&
        $this->registerHook('actionAuthentication') &&
        $this->registerHook('actionOrderStatusUpdate') &&
        $this->registerHook('actionCustomerAccountAdd') &&
        $this->registerHook('actionNewsletterRegistrationAfter')
        ) {
            return true;
        }
        $this->_errors[] = $this->trans('There was an error during the installation.');
        return false;
    }

    public function uninstall()
    {
        Configuration::deleteByName(self::TRACKING_KEY);
        Configuration::deleteByName(self::REST_KEY);
        Configuration::deleteByName(self::TM_CUSTOMER_ID);
        Configuration::deleteByName(self::ORDERS_FEED_ALLOW);
        Configuration::deleteByName(self::ORDERS_FEED_DATE);
        Configuration::deleteByName(self::ORDERS_FEED_LINK);
        Configuration::deleteByName(self::PRODUCTS_FEED_LINK);
        Configuration::deleteByName(self::PRODUCTS_FEED_CRON);
        Configuration::deleteByName(self::CATEGORIES_FEED_LINK);
        Configuration::deleteByName(self::BRANDS_FEED_LINK);
        Configuration::deleteByName(self::REVIEWS_FEED_LINK);
        Configuration::deleteByName(self::TM_ENABLE_NOTIFICATIONS);
        Configuration::deleteByName(self::TM_ENABLE_REVIEWS);
        return true;
    }

    public function hookDisplayHeader($params)
    {
        $this->context->controller->registerJavascript('modules-themarketer', 'modules/' . $this->name . '/views/js/cart.js', ['position' => 'bottom', 'priority' => 150]);
        if (Tools::getValue('id_product') > 0 && $this->context->controller->php_self == 'product') {
            $pid = Tools::getValue('id_product');
            $product = new Product(Tools::getValue('id_product'));
            $idarticolo = $product->reference;
            if ($product->hasAttributes()) {
                $id_product_attribute = Tools::getValue('id_product_attribute');
                $comb_id = $id_product_attribute;
            } else {
                $comb_id = 0;
            }
        } else {
            $pid = 0;
            $comb_id = 0;
        }
        if ($this->context->controller->php_self == 'category') {
            $category_id = Tools::getValue('id_category');
            $category = new Category($category_id);
            $array_parent = $category->getParentsCategories();
            krsort($array_parent);
            $link = new Link();
            $link = $link->getCategoryLink($category_id);
            $c = '';
            foreach ($array_parent as $k => $ch) {
                if (isset($ch['name'])) {
                    $c .= $ch['name'] . '|';
                } else {
                }
            }
            $hierarchy = mb_substr($c, 0, -1);
        } else {
            $hierarchy = 0;
        }
        if (Tools::getIsset('add')) {
            $addtocart = 1;
        } else {
            $addtocart = 0;
        }
        if ($this->context->controller->php_self == 'search') {
            $search_term = Tools::getValue('s');
        } else {
            $search_term = '';
        }
        if ($this->context->controller->php_self == 'order-confirmation') {
            $order_id = Tools::getValue('id_order');
            $order_data = OrderDetail::getList($order_id);
            $order_address = new Order($order_id);
            $delivery_details = new Address($order_address->id_address_invoice);
            $id_customer = $order_address->id_customer;
            $customer = new Customer($id_customer);
            $genparams = $order_address;
            if ($genparams->total_discounts > 0) {
                $totaldiscount = round($genparams->total_discounts, 2);
            } else {
                $totaldiscount = 0;
            }
            if ($genparams->total_shipping > 0) {
                $shipping = round($genparams->total_shipping, 2);
            } else {
                $shipping = 0;
            }
            $product_data = '[';
            foreach ($order_data as $product) {
                $comb_id = $product['product_attribute_id'];
                if ($comb_id > 0) {
                    $product['product_id'] = $product['product_id'] . '_' . $comb_id;
                } else {
                    $product['product_id'] = $product['product_id'];
                }
                $product_data .= '{
						product_id: \'' . $product['product_id'] . '\',
						price: ' . round($product['unit_price_tax_incl'], 2) . ',
						quantity: ' . $product['product_quantity'] . ',
						variation_sku: \'' . $product['product_reference'] . '\'
				},';
            }
            $product_data = $product_data . ']';
            $orderData = '
				event:\'__sm__order\',
				number: \'' . $order_id . '\',
				email_address: \'' . $customer->email . '\',
				phone: \'' . $delivery_details->phone . '\',
				firstname: \'' . $delivery_details->firstname . '\',
				lastname: \'' . $delivery_details->lastname . '\',
				city: \'' . $delivery_details->city . '\',
				county: \'' . $delivery_details->country . '\',
				address: \'' . $delivery_details->address1 . '\',
				discount_value: ' . round($totaldiscount, 2) . ',
				discount_code: \'\',
				shipping: ' . $shipping . ',
				tax: ' . round($genparams->total_paid_tax_incl - $genparams->total_paid_tax_excl, 2) . ',
				total_value: ' . round($genparams->total_paid_tax_incl, 2) . ',
				products:  ' . $product_data . '
			';
            $orderdata = $orderData;
        } else {
            $orderdata = '';
        }
        $query = 'SELECT value FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name`=\'THEMARKETER_ENABLE_NOTIFICATIONS\'';
        $enable = Db::getInstance()->getValue($query);
        if (empty($comb_id)) {
            $comb_id = 0;
        } else {
            $comb_id = $comb_id;
        }
        if (isset($this->context->customer->phone)) {
            $phone = $this->context->customer->phone;
        } else {
            $phone = '';
        }
        $this->context->smarty->assign([
            'tm_id' => Configuration::get(self::TRACKING_KEY),
            'tm_page_name' => $this->context->controller->php_self,
            'tm_product_id' => $pid,
            'tm_product_compination' => $comb_id,
            'tm_category' => $hierarchy,
            'tm_addtocart' => $addtocart,
            'tm_search' => $search_term,
            'tm_order' => $orderdata,
            'tm_enable' => $enable,
            'tm_server_root' => Tools::getHttpHost(true) . __PS_BASE_URI__,
            'tm_login' => 'dataLayer.push({event: \'__sm__set_email\',email_address: \'' . $this->context->customer->email . '\',firstname: \'' . $this->context->customer->firstname . '\',lastname: \'' . $this->context->customer->lastname . '\'});',
            'tm_email' => $this->context->customer->email,
            'tm_firstname' => $this->context->customer->firstname,
            'tm_lastname' => $this->context->customer->lastname,
            'tm_phone' => $phone,
            'tm_action' => Tools::getValue('action'),
        ]);
        return $this->display(__FILE__, 'header.tpl');
    }

    public function hookDisplayFooterAfter($params)
    {
        if (Tools::getValue('id_product') > 0 && $this->context->controller->php_self == 'product') {
            $pid = Tools::getValue('id_product');
        } else {
            $pid = 0;
        }
        $this->context->smarty->assign([
            'tm_id' => Configuration::get(self::TRACKING_KEY),
            'tm_page_name' => $this->context->controller->php_self,
            'tm_product_id' => $pid,
        ]);
        return $this->display(__FILE__, 'footer.tpl');
    }

    public function getContent()
    {
        $output = null;
        if (Tools::isSubmit('submit' . $this->name)) {
            $tmId = Tools::getValue(self::TRACKING_KEY);
            $restId = Tools::getValue(self::REST_KEY);
            $tmcustomer = Tools::getValue(self::TM_CUSTOMER_ID);
            $tmordersfeedallow = Tools::getValue(self::ORDERS_FEED_ALLOW);
            $tmordersfeeddate = Tools::getValue(self::ORDERS_FEED_DATE);
            $enablenotifications = Tools::getValue(self::TM_ENABLE_NOTIFICATIONS);
            $enablereviews = Tools::getValue(self::TM_ENABLE_REVIEWS);
            if (!$tmId) {
                $output .= $this->displayError($this->l('No tracking key provided.'));
            } else {
                Configuration::updateValue(self::TRACKING_KEY, $tmId);
                $output .= $this->displayConfirmation($this->l('Tracking KEY updated'));
            }
            if (!$restId) {
                $output .= $this->displayError($this->l('No rest key provided.'));
            } else {
                Configuration::updateValue(self::REST_KEY, $restId);
                $output .= $this->displayConfirmation($this->l('REST KEY updated'));
            }
            if (!$tmcustomer) {
                $output .= $this->displayError($this->l('No CUSTOMER ID provided.'));
            } else {
                Configuration::updateValue(self::TM_CUSTOMER_ID, $tmcustomer);
                $output .= $this->displayConfirmation($this->l('CUSTOMER ID updated'));
            }
            if ($tmordersfeedallow == 1) {
                Configuration::updateValue(self::ORDERS_FEED_ALLOW, 1);
            } elseif ($tmordersfeedallow == 0) {
                Configuration::updateValue(self::ORDERS_FEED_ALLOW, 0);
            }
            if ($enablenotifications == 1) {
                Configuration::updateValue(self::TM_ENABLE_NOTIFICATIONS, 1);
                $serverroot = $_SERVER['DOCUMENT_ROOT'];
                $moduleroot = $this->local_path . 'views/js/';
                copy($moduleroot . 'firebase-config.js', '../firebase-config.js');
                copy($moduleroot . 'firebase-messaging-sw.js', '../firebase-messaging-sw.js');
            } else {
                Configuration::updateValue(self::TM_ENABLE_NOTIFICATIONS, 0);
            }
            if ($enablereviews == 1) {
                Configuration::updateValue(self::TM_ENABLE_REVIEWS, 1);
            } else {
                Configuration::updateValue(self::TM_ENABLE_REVIEWS, 0);
            }
            if (!$tmordersfeeddate) {
            } else {
                Configuration::updateValue(self::ORDERS_FEED_DATE, $tmordersfeeddate);
            }
        }
        $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        $feedsLinks = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/themarketer_feeds/feeds.tpl');
        return $output . $this->displayForm() . $feedsLinks;
    }

    private function generateSwitch(
        $name,
        $label,
        $description,
        $other = [],
        $extraDescription = '',
        $form_group_class = ''
    ) {
        $other['hint'] = $description;
        return array_merge([
            'type' => 'switch',
            'label' => $label,
            'name' => $name,
            'is_bool' => true,
            'desc' => $extraDescription,
            'form_group_class' => $form_group_class,
            'values' => [
                [
                    'id' => $name . '_on',
                    'value' => 1,
                    'label' => $this->l('Enabled'),
                ],
                [
                    'id' => $name . '_off',
                    'value' => 0,
                    'label' => $this->l('Disabled'),
                ],
           ],
        ], $other);
    }

    public function displayForm()
    {
        $defaultLang = Configuration::get('PS_LANG_DEFAULT');
        $enable = Tools::getValue(self::TM_ENABLE_NOTIFICATIONS);
        if ($enable == 0) {
            $enval = 0;
            $enlang = $this->l('No');
            $noenval = 1;
            $noenlang = $this->l('Yes');
        } else {
            $enval = 1;
            $enlang = $this->l('Yes');
            $noenval = 0;
            $noenlang = $this->l('No');
        }
        $options = [
                  [
                    'id_option' => $enval,
                    'name' => $enlang,
                  ],
                  [
                    'id_option' => $noenval,
                    'name' => $noenlang,
                  ],
        ];
        $enabler = Tools::getValue(self::TM_ENABLE_REVIEWS);
        if ($enabler == 0) {
            $envalr = 0;
            $enlangr = $this->l('No');
            $noenvalr = 1;
            $noenlangr = $this->l('Yes');
        } else {
            $envalr = 1;
            $enlangr = $this->l('Yes');
            $noenvalr = 0;
            $noenlangr = $this->l('No');
        }
        $optionsreviews = [
                  [
                    'id_option' => $envalr,
                    'name' => $enlangr,
                  ],
                  [
                    'id_option' => $noenvalr,
                    'name' => $noenlangr,
                  ],
        ];
        $enableo = Tools::getValue(self::ORDERS_FEED_ALLOW);
        if ($enableo == 0) {
            $envalo = 0;
            $enlango = $this->l('No');
            $noenvalo = 1;
            $noenlango = $this->l('Yes');
        } else {
            $envalo = 1;
            $enlango = $this->l('Yes');
            $noenvalo = 0;
            $noenlango = $this->l('No');
        }
        $optionsorders = [
                  [
                    'id_option' => $envalo,
                    'name' => $enlango,
                  ],
                  [
                    'id_option' => $noenvalo,
                    'name' => $noenlango,
                  ],
        ];
        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('TheMarketer Tracking KEY'),
                    'name' => self::TRACKING_KEY,
                    'size' => 5,
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('TheMarketer REST KEY'),
                    'name' => self::REST_KEY,
                    'size' => 5,
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('TheMarketer CUSTOMER ID'),
                    'name' => self::TM_CUSTOMER_ID,
                    'size' => 5,
                    'required' => true,
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Enable Push Notifications'),
                    'name' => self::TM_ENABLE_NOTIFICATIONS,
                    'options' => [
                      'query' => $options,
                      'id' => 'id_option',
                      'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Enable Product Reviews Imports'),
                    'name' => self::TM_ENABLE_REVIEWS,
                    'options' => [
                      'query' => $optionsreviews,
                      'id' => 'id_option',
                      'name' => 'name',
                    ],
                ],
                [
                    'type' => 'header',
                    'name' => $this->l('Orders Feed'),
                    'label' => '<strong>' . $this->l('Orders Feed') . '</strong>',
                ],
                [
                    'type' => 'header',
                    'name' => $this->l('Orders Feed'),
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Enable Orders feed'),
                    'name' => self::ORDERS_FEED_ALLOW,
                    'options' => [
                      'query' => $optionsorders,
                      'id' => 'id_option',
                      'name' => 'name',
                    ],
                ],
                [
                    'type' => 'date',
                    'label' => $this->l('Select Orders feed date from'),
                    'name' => self::ORDERS_FEED_DATE,
                    'desc' => $this->l('Select the date who start get orders for feed'),
                    'size' => 10,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('ORDERS FEED LINK') . ';',
                    'name' => self::ORDERS_FEED_LINK,
                    'class' => 'tm-orders-feed-link',
                    'desc' => '<span style=\'cursor:pointer;padding:5px 10px; background:#fff;border:1px solid #666;position:absolute;right:10px;top:5px;\' onclick=\'copyLink(),alert(alertordersfeed )\' id=\'spanCopy\'><i class=\'material-icons\' style=\'font-size:0.9em;\'>collections</i> ' . $this->l('Copy') . '</span></p>',
                ],
                [
                    'type' => 'header',
                    'name' => $this->l('Products XML Feed'),
                    'label' => '<strong>' . $this->l('Products XML Feed') . '</strong>',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('PRODUCTS XML FEED LINK') . ';',
                    'name' => self::PRODUCTS_FEED_LINK,
                    'class' => 'tm-products-feed-link',
                    'desc' => '<span style=\'cursor:pointer;padding:5px 10px; background:#fff;border:1px solid #666;position:absolute;right:10px;top:5px;\' onclick=\'copyLinkProducts(),alert(alertordersfeed )\' id=\'spanCopyProducts\'><i class=\'material-icons\' style=\'font-size:0.9em;\'>collections</i> ' . $this->l('Copy') . '</span></p>',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('PRODUCTS XML FEED CRON JOB URL') . ';',
                    'name' => self::PRODUCTS_FEED_CRON,
                    'class' => 'tm-products-feed-cron',
                    'desc' => '<span style=\'cursor:pointer;padding:5px 10px; background:#fff;border:1px solid #666;position:absolute;right:10px;top:5px;\' onclick=\'copyLinkProducts(),alert(alertordersfeed )\' id=\'spanCopyProductsCron\'><i class=\'material-icons\' style=\'font-size:0.9em;\'>collections</i> ' . $this->l('Copy') . '</span></p>',
                ],
                [
                    'type' => 'header',
                    'name' => $this->l('Categories XML Feed'),
                    'label' => '<strong>' . $this->l('Categories XML Feed') . '</strong>',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('CATEGORIES XML FEED LINK') . ';',
                    'name' => self::CATEGORIES_FEED_LINK,
                    'class' => 'tm-cats-feed-link',
                    'desc' => '<span style=\'cursor:pointer;padding:5px 10px; background:#fff;border:1px solid #666;position:absolute;right:10px;top:5px;\' onclick=\'copyLinkCats(),alert(alertordersfeed )\' id=\'spanCopyCats\'><i class=\'material-icons\' style=\'font-size:0.9em;\'>collections</i> ' . $this->l('Copy') . '</span></p>',
                ],
                [
                    'type' => 'header',
                    'name' => $this->l('Brands XML Feed'),
                    'label' => '<strong>' . $this->l('Brands XML Feed') . '</strong>',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('BRANDS XML FEED LINK') . ';',
                    'name' => self::BRANDS_FEED_LINK,
                    'class' => 'tm-brands-feed-link',
                    'desc' => '<span style=\'cursor:pointer;padding:5px 10px; background:#fff;border:1px solid #666;position:absolute;right:10px;top:5px;\' onclick=\'copyLinkBrands(),alert(alertordersfeed )\' id=\'spanCopyBrands\'><i class=\'material-icons\' style=\'font-size:0.9em;\'>collections</i> ' . $this->l('Copy') . '</span></p>',
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
            ],
        ];
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list'),
            ],
        ];
        $helper->fields_value[self::TRACKING_KEY] = Configuration::get(self::TRACKING_KEY);
        $helper->fields_value[self::REST_KEY] = Configuration::get(self::REST_KEY);
        $helper->fields_value[self::TM_CUSTOMER_ID] = Configuration::get(self::TM_CUSTOMER_ID);
        $helper->fields_value[self::TM_ENABLE_NOTIFICATIONS] = Tools::getValue(self::TM_ENABLE_NOTIFICATIONS, Configuration::get(self::TM_ENABLE_NOTIFICATIONS));
        $helper->fields_value[self::TM_ENABLE_REVIEWS] = Tools::getValue(self::TM_ENABLE_REVIEWS, Configuration::get(self::TM_ENABLE_REVIEWS));
        $helper->fields_value[self::TM_API_URL] = 'https://t.themarketer.com/api/v1';
        $helper->fields_value[self::ORDERS_FEED_ALLOW] = Configuration::get(self::ORDERS_FEED_ALLOW);
        $helper->fields_value[self::ORDERS_FEED_DATE] = Configuration::get(self::ORDERS_FEED_DATE);
        $helper->fields_value[self::ORDERS_FEED_LINK] = str_replace('http:// ', 'https://', _PS_BASE_URL_) . __PS_BASE_URI__ . 'modules/themarketer/orders_export.php?key=' . Configuration::get(self::REST_KEY) . '&customerId=' . Configuration::get(self::TM_CUSTOMER_ID) . '&start_date=' . Configuration::get(self::ORDERS_FEED_DATE) . '&page=1';
        $helper->fields_value[self::PRODUCTS_FEED_LINK] = str_replace('http:// ', 'https://', _PS_BASE_URL_) . __PS_BASE_URI__ . 'modules/themarketer/products_feed_' . Configuration::get(self::REST_KEY) . '.xml';
        $helper->fields_value[self::PRODUCTS_FEED_CRON] = str_replace('http:// ', 'https://', _PS_BASE_URL_) . __PS_BASE_URI__ . 'modules/themarketer/products_feed.php?key=' . Configuration::get(self::REST_KEY);
        $helper->fields_value[self::CATEGORIES_FEED_LINK] = str_replace('http:// ', 'https://', _PS_BASE_URL_) . __PS_BASE_URI__ . 'modules/themarketer/categories_feed.php?key=' . Configuration::get(self::REST_KEY);
        $helper->fields_value[self::BRANDS_FEED_LINK] = str_replace('http:// ', 'https://', _PS_BASE_URL_) . __PS_BASE_URI__ . 'modules/themarketer/brands_feed.php?key=' . Configuration::get(self::REST_KEY);
        $helper->fields_value[self::REVIEWS_FEED_LINK] = str_replace('http:// ', 'https://', _PS_BASE_URL_) . __PS_BASE_URI__ . 'modules/themarketer/reviews_feed.php?key=' . Configuration::get(self::REST_KEY) . '&start_date=' . Configuration::get(self::ORDERS_FEED_DATE);
        return $helper->generateForm($fieldsForm);
    }

    public function getConfigFieldsValues()
    {
        return [
            'TRACKING_KEY' => Tools::getValue('TRACKING_KEY', Configuration::get('TRACKING_KEY')),
            'REST_KEY' => Tools::getValue('REST_KEY', Configuration::get('REST_KEY')),
            'TM_CUSTOMER_ID' => Tools::getValue('TM_CUSTOMER_ID', Configuration::get('TM_CUSTOMER_ID')),
            'TM_ENABLE_NOTIFICATIONS' => Tools::getValue('TM_ENABLE_NOTIFICATIONS', Configuration::get('TM_ENABLE_NOTIFICATIONS')),
            'TM_ENABLE_REVIEWS' => Tools::getValue('TM_ENABLE_REVIEWS', Configuration::get('TM_ENABLE_REVIEWS')),
            'ORDERS_FEED_ALLOW' => Tools::getValue('ORDERS_FEED_ALLOW', Configuration::get('ORDERS_FEED_ALLOW')),
            'ORDERS_FEED_DATE' => Tools::getValue('ORDERS_FEED_DATE', Configuration::get('ORDERS_FEED_DATE')),
            'ORDERS_FEED_LINK' => Tools::getValue('ORDERS_FEED_LINK', str_replace('http:// ', 'https://', _PS_BASE_URL_) . __PS_BASE_URI__ . 'modules/themarketer/orders_export.php?key=' . Configuration::get(self::REST_KEY) . '&customerId=' . Configuration::get(self::TM_CUSTOMER_ID) . '&start_date=' . Configuration::get(self::ORDERS_FEED_DATE) . '&page=1'),
        ];
    }

    public function hookActionValidateOrder($params)
    {
        $address = $params['cart']->id_address_delivery;
        $query = 'SELECT phone FROM `' . _DB_PREFIX_ . 'address` WHERE `id_address`=' . $address;
        $phone = Db::getInstance()->getValue($query);
        $enablesql = 'SELECT value FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name`= \'THEMARKETER_ENABLE_NOTIFICATIONS\'';
        $enable = Db::getInstance()->getValue($enablesql);
        $newletter = $params['customer']->newsletter;
        if ($newletter == 1) {
            $email = $params['customer']->email;
            $sqlnewsletter = 'SELECT `newsletter`  FROM ' . _DB_PREFIX_ . 'customer WHERE `email` = \'' . pSQL($email) . '\'';
            $registered = Db::getInstance()->getValue($sqlnewsletter);
            $apiURL = 'https://t.themarketer.com/api/v1/add_subscriber';
            $apiKey = Configuration::get('THEMARKETER_REST_KEY');
            $customerId = Configuration::get('THEMARKETER_CUSTOMER_ID');
            $registerData = [
                'k' => $apiKey,
                'u' => $customerId,
                'email' => $email,
                'phone' => $phone,
                'name' => '',
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiURL);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_TIMEOUT, '30');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($registerData));
            $response = curl_exec($ch);
            curl_close($ch);
        } else {
        }
        if ($phone) {
            $phone = $phone;
        } else {
            $phone = '';
        }
        $this->context->smarty->assign([
            'tm_login_email' => $params['customer']->email,
            'tm_login_firstname' => $params['customer']->firstname,
            'tm_login_lastname' => $params['customer']->lastname,
            'tm_login_phone' => $phone,
            'tm_enable' => $enable,
            'tm_product_compination' => 0,
        ]);
        return $this->display(__FILE__, 'footer.tpl');
    }

    public function hookDisplayOrderConfirmation($params)
    {
        $api_url = 'https://t.themarketer.com/api/v1/save_order';
        $rest_key = Configuration::get('THEMARKETER_REST_KEY');
        $customerId = Configuration::get(self::TM_CUSTOMER_ID);
        $order_id = $params['order']->id;
        $order_data = OrderDetail::getList($order_id);
        $order_address = new Order($order_id);
        $delivery_details = new Address($order_address->id_address_invoice);
        $id_customer = $params['order']->id_customer;
        $customer = new Customer($id_customer);
        $lockerdata = json_encode([
            'gen' => $params['order'],
            'customer' => $order_address,
            'address' => $delivery_details,
            'params' => $order_data,
            'apiurl' => $api_url,
            'rest_key' => $rest_key,
        ]);
        $genparams = $params['order'];
        if ($genparams->total_discounts > 0) {
            $totaldiscount = round($genparams->total_discounts, 2);
        } else {
            $totaldiscount = 0;
        }
        if ($genparams->total_shipping > 0) {
            $shipping = round($genparams->total_shipping, 2);
        } else {
            $shipping = 0;
        }
        $product_data = [];
        foreach ($order_data as $product) {
            $comb_id = $product['product_attribute_id'];
            if ($comb_id > 0) {
                $product['product_id'] = $product['product_id'] . '_' . $comb_id;
            } else {
                $product['product_id'] = $product['product_id'];
            }
            $product_data[] = [
                    'product_id' => $product['product_id'],
                    'price' => round($product['unit_price_tax_incl'], 2),
                    'quantity' => $product['product_quantity'],
                    'variation_sku' => $product['product_reference'],
                    ];
        }
        $orderData = [
            'k' => $rest_key,
            'u' => $customerId,
            'number' => $order_id,
            'email_address' => $customer->email,
            'phone' => $delivery_details->phone,
            'firstname' => $delivery_details->firstname,
            'lastname' => $delivery_details->lastname,
            'city' => $delivery_details->city,
            'county' => $delivery_details->country,
            'address' => $delivery_details->address1,
            'discount_value' => round($totaldiscount, 2),
            'discount_code' => '',
            'shipping' => $shipping,
            'tax' => round($genparams->total_paid_tax_incl, 2) - round($genparams->total_paid_tax_excl, 2),
            'total_value' => round($genparams->total_paid_tax_incl, 2),
            'products' => $product_data,
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_TIMEOUT, '30');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($orderData));
        $response = curl_exec($ch);
        curl_close($ch);
        if (isset($_COOKIE['tm_initate_checkout'])) {
            unset($_COOKIE['tm_initate_checkout']);
            setcookie('tm_initate_checkout', null, -1, '/');
        } else {
        }
        $query = 'SELECT value FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name`= \'THEMARKETER_ENABLE_NOTIFICATIONS\'';
        $enable = Db::getInstance()->getValue($query);
        $email = $customer->email;
        $fullname = $delivery_details->firstname . ' ' . $delivery_details->lastname;
        $apiURL = 'https://t.themarketer.com/api/v1/add_subscriber';
        $apiKey = Configuration::get('THEMARKETER_REST_KEY');
        $customerId = Configuration::get(self::TM_CUSTOMER_ID);
        $this->context->smarty->assign([
            'tm_login_email_order' => $email,
            'tm_login_firstname_order' => $delivery_details->firstname,
            'tm_login_lastname_order' => $delivery_details->lastname,
            'tm_login_phone_order' => $delivery_details->phone,
            'tm_product_compination' => 0,
            'tm_enable' => $enable,
        ]);
        return $this->display(__FILE__, 'order-confirmation.tpl');
    }

    public function hookActionOrderStatusUpdate($params)
    {
        $apiURL = 'https://t.themarketer.com/api/v1/update_order_status';
        $apiKey = Configuration::get('THEMARKETER_REST_KEY');
        $customerId = Configuration::get('THEMARKETER_CUSTOMER_ID');
        $order_id = $params['id_order'];
        $new_status = $params['newOrderStatus']->id;
        $new_status_name = $params['newOrderStatus']->name;
        $orderStatus = [
            'k' => $apiKey,
            'u' => $customerId,
            'order_number' => $order_id,
            'order_status' => $new_status_name,
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiURL . '?' . http_build_query($orderStatus));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_TIMEOUT, '30');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
    }

    public function hookActionCustomerAccountAdd($params)
    {
        $query = 'SELECT value FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name`= \'THEMARKETER_ENABLE_NOTIFICATIONS\'';
        $enable = Db::getInstance()->getValue($query);
        $email = $params['newCustomer']->email;
        $fullname = $params['newCustomer']->firstname . ' ' . $params['newCustomer']->lastname;
        if ($params['newCustomer']->newsletter == 1) {
            $apiURL = 'https://t.themarketer.com/api/v1/add_subscriber';
            $apiKey = Configuration::get('THEMARKETER_REST_KEY');
            $customerId = Configuration::get(self::TM_CUSTOMER_ID);
            $registerData = [
                'k' => $apiKey,
                'u' => $customerId,
                'email' => $email,
                'phone' => '',
                'name' => $fullname,
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiURL);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_TIMEOUT, '30');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($registerData));
            $response = curl_exec($ch);
            curl_close($ch);
        }
        $this->context->smarty->assign([
            'tm_login_email' => $params['newCustomer']->email,
            'tm_login_firstname' => $params['newCustomer']->firstname,
            'tm_login_lastname' => $params['newCustomer']->lastname,
            'tm_enable' => $enable,
            'tm_product_compination' => 0,
        ]);
        return $this->display(__FILE__, 'footer.tpl');
    }
}
