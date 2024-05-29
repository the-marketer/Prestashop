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
if (!defined('_PS_VERSION_')) {
    exit;
}

if (!defined('MKTR_ROOT')) {
    define('MKTR_ROOT', _PS_ROOT_DIR_ . (substr(_PS_ROOT_DIR_, -1) === '/' ? '' : '/'));
}

if (!defined('MKTR_APP')) {
    // define("MKTR_APP", __DIR__ . (substr(__DIR__, -1) === "/" ? "" : "/"));
    $d = MKTR_ROOT . 'modules/mktr/';
    define('MKTR_APP', $d . (substr($d, -1) === '/' ? '' : '/'));
}

class Mktr extends Module
{
    private static $i = null;
    private static $update = true;
    private static $included = [];
    private static $displayLoad = [
        'header' => true,
        'footer' => true,
        'dispatcher' => true,
    ];

    private static $vr = [];
    private static $runAction = true;

    public function __construct()
    {
        $this->name = 'mktr';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.6';
        $this->author = 'TheMarketer.com';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'TheMarketer';
        $this->description = 'TheMarketer - PrestaShop Version';
        $this->confirmUninstall = 'Are you sure you want to uninstall this module?';
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
        self::$i = $this;

        spl_autoload_register([$this, 'load'], true, true);

        \Mktr\Model\Config::setLang($this->context->language->id)->setContext($this->context);

        if (self::$update) {
            self::preConfig();
        } else {
            \Mktr\Helper\Session::getUid();
        }

        // $this->registerHook('actionDispatcher');
    }

    public static function i()
    {
        return self::$i;
    }

    public static function correctUpdate($filePath, $from, $to)
    {
        $content = \Tools::file_get_contents($filePath, true);
        $newContent = str_replace($from, $to, $content);

        $file = fopen($filePath, 'w+');
        fwrite($file, $newContent);
        fclose($file);
    }

    public static function preConfig()
    {
        if (self::$update) {
            if (file_exists(MKTR_APP . 'mktr.php')) {
                self::$update = false;
                \Mktr\Route\refreshJS::loadJs();

                self::correctUpdate(
                    MKTR_APP . 'mktr.php',
                    [
                        implode('', ['private static $update ', '= true;']),
                        "define('MKTR_ROOT', _PS_ROOT_DIR_ . (substr(_PS_ROOT_DIR_, -1) === '/' ? '' : '/'));",
                        "define('MKTR_APP', \$d . (substr(\$d, -1) === '/' ? '' : '/'));",
                        "
        \$d = MKTR_ROOT . 'modules/mktr/';",
                    ],
                    [
                        'private static $update = false;',
                        "define('MKTR_ROOT', '" . MKTR_ROOT . "');",
                        "define('MKTR_APP', '" . MKTR_APP . "');",
                        '',
                    ]
                );
            }
        }
    }

    public function install()
    {
        $hook = [
            /* Front */
            'displayHeader',
            'moduleRoutes',
            'actionDispatcher',
            'displayFooterAfter',
            'displayFooterBefore',
            /* Admin */
            'displayBackOfficeHeader',
            'actionOrderStatusUpdate',
        ];

        if (_PS_VERSION_ >= 1.7) {
            $hook[] = 'displayBeforeBodyClosingTag';
        } else {
            $hook[] = 'displayFooter';
        }

        \Mktr\Helper\Setup::install();

        if (parent::install() && $this->registerHook($hook)) {
            return true;
        } else {
            $this->_errors[] = 'There was an error during the Install procces.';

            return false;
        }
    }

    public function uninstall()
    {
        \Mktr\Helper\Setup::uninstall();

        if (parent::uninstall()) {
            return true;
        } else {
            $this->_errors[] = 'There was an error during the Uninstall procces.';

            return false;
        }
    }

    public function getContent()
    {
        \Tools::redirectAdmin($this->context->link->getAdminLink('Mktr', true));

        return null;
    }

    private static function load($className, $ext = '.php')
    {
        if (strpos($className, 'Mktr\\') !== false) {
            if (!array_key_exists($className, self::$included)) {
                $file = MKTR_APP . str_replace(['Mktr\\', '\\'], ['App/', '/'], $className) . $ext;

                if (file_exists($file)) {
                    self::$included[$className] = true;
                    require_once $file;
                } else {
                    self::$included[$className] = false;
                }
            }
        }
    }

    public static $checkList = [
        'update' => false,
        'isAdd' => false,
        'isDel' => false,
    ];

    public function hookactionDispatcher()
    {
        $cont = Mktr\Helper\Valid::getParam('controller', null);

        if (_PS_VERSION_ < 1.7 && $cont !== null && strpos($cont, 'Admin') !== false && strpos($cont, 'admin') !== false) {
            return true;
        }

        if (self::$displayLoad['dispatcher'] === true && Mktr\Model\Config::showJS()) {
            self::$displayLoad['dispatcher'] = false;

            // \Mktr\Helper\Session::init();

            $pId = null;
            $pAttr = null;
            $qty = null;

            $email = Mktr\Helper\Valid::getParam('email', null);
            $phone = Mktr\Helper\Valid::getParam('phone', null);
            $phone1 = Mktr\Helper\Valid::getParam('phone_mobile', null);

            if ($email !== null) {
                $remove = false;
                /*
                $newsletter = Mktr\Helper\Valid::getParam('newsletter', null);
                $newsletter !== null ||
                 || Mktr\Helper\Valid::getParam('create_account', null) !== null
                 , 'order'
                */
                if (in_array(Mktr\Helper\Valid::getParam('controller', null), ['identity'])) {
                    $remove = true;
                    // var_dump($newsletter);
                    // die();
                }
                Mktr\Helper\Session::setEmail([$email, $remove]);
                Mktr\Helper\Session::save();
            }

            if ($phone !== null && !empty($phone) || $phone1 !== null && !empty($phone1)) {
                Mktr\Helper\Session::setPhone($phone !== null ? $phone : $phone1);
                Mktr\Helper\Session::save();
            }

            if (_PS_VERSION_ >= 1.7) {
                self::$checkList['update'] = Mktr\Helper\Valid::getParam('action', null) === 'update';
                self::$checkList['isAdd'] = self::$checkList['update'] && Mktr\Helper\Valid::getParam('add', null) !== null;
                self::$checkList['isDel'] = self::$checkList['update'] && Mktr\Helper\Valid::getParam('delete', null) !== null;

                if (self::$checkList['update'] && self::$checkList['isAdd']) {
                    $pId = Mktr\Helper\Valid::getParam('id_product', null);
                    $pGrup = Mktr\Helper\Valid::getParam('group', null);
                    if ($pGrup !== null) {
                        $pAttr = (int) \Product::getIdProductAttributeByIdAttributes($pId, $pGrup, true);
                    }
                    $qty = Mktr\Helper\Valid::getParam('qty', null);
                }

                if (self::$checkList['update'] && self::$checkList['isDel']) {
                    $pId = Mktr\Helper\Valid::getParam('id_product', null);
                    $pAttr = Mktr\Helper\Valid::getParam('id_product_attribute', null);
                }
            } else {
                self::$checkList['update'] = Mktr\Helper\Valid::getParam('controller', null) === 'cart';
                self::$checkList['isAdd'] = self::$checkList['update'] && Mktr\Helper\Valid::getParam('add', null) !== null;
                self::$checkList['isDel'] = self::$checkList['update'] && Mktr\Helper\Valid::getParam('delete', null) !== null;

                if (self::$checkList['update'] && self::$checkList['isAdd']) {
                    $pId = Mktr\Helper\Valid::getParam('id_product', null);
                    $pAttr = Mktr\Helper\Valid::getParam('ipa', null);
                    $qty = Mktr\Helper\Valid::getParam('qty', null);
                }

                if (self::$checkList['update'] && self::$checkList['isDel']) {
                    $pId = Mktr\Helper\Valid::getParam('id_product', null);
                    $pAttr = Mktr\Helper\Valid::getParam('ipa', null);
                }
            }

            if (self::$checkList['update'] && self::$checkList['isAdd']) {
                Mktr\Helper\Session::addToCart($pId, $pAttr, $qty);
                Mktr\Helper\Session::save();
            } elseif (self::$checkList['update'] && self::$checkList['isDel']) {
                $cartId = Mktr\Helper\Session::get('cartID', null);
                $list = Mktr\Model\Product::getQty($pId, $pAttr, $cartId);

                foreach ($list as $value) {
                    if ($value['id_product_attribute'] === $pAttr && $value['id_product'] === $pId) {
                        $qty = $value['quantity'];
                        break;
                    }
                }
                Mktr\Helper\Session::removeFromCart($pId, $pAttr, (int) $qty);
                Mktr\Helper\Session::save();
            } elseif (_PS_VERSION_ >= 1.7 && Mktr\Helper\Valid::getParam('action', null) !== null) {
                if (Mktr\Helper\Valid::getParam('action', null) === 'addProductToWishlist') {
                    $p = Mktr\Helper\Valid::getParam('params', null);
                    Mktr\Helper\Session::addToWishlist($p['id_product'], $p['id_product_attribute']);
                    Mktr\Helper\Session::save();
                } elseif (Mktr\Helper\Valid::getParam('action', null) === 'deleteProductFromWishlist') {
                    $p = Mktr\Helper\Valid::getParam('params', null);
                    Mktr\Helper\Session::removeFromWishlist($p['id_product'], $p['id_product_attribute']);
                    Mktr\Helper\Session::save();
                }
            } else {
                if (Mktr\Helper\Valid::getParam('process', null) === 'add') {
                    $p = Mktr\Helper\Valid::getParam('id_product', null);
                    if ($p !== null) {
                        Mktr\Helper\Session::addToWishlist($p, 0);
                        Mktr\Helper\Session::save();
                    }
                } elseif (Mktr\Helper\Valid::getParam('process', null) === 'remove') {
                    $p = Mktr\Helper\Valid::getParam('id_product', null);
                    if ($p !== null) {
                        Mktr\Helper\Session::removeFromWishlist($p, 0);
                        Mktr\Helper\Session::save();
                    }
                }
            }

            $cont = Mktr\Helper\Valid::getParam('controller', null);
            if (in_array($cont, ['order-confirmation', 'thank_you_page', 'orderconfirmation', 'confirmare-comanda'])) {
                $id_order = Mktr\Helper\Valid::getParam('id_order', null);

                if ($id_order === null) {
                    $cartId = Mktr\Helper\Valid::getParam('id_cart', null);

                    if ($cartId === null) {
                        $cartId = Mktr\Helper\Valid::getParam('orderId', null);
                        $cartId = explode('%', $cartId);
                        $cartId = $cartId[0];
                    }

                    if ($cartId !== null) {
                        Mktr\Helper\Session::set('save_order', [['id' => $cartId, 'is_order' => false]]);
                        Mktr\Helper\Session::save();
                    }
                } else {
                    Mktr\Helper\Session::set('save_order', [['id' => $id_order, 'is_order' => true]]);
                    Mktr\Helper\Session::save();
                }
            }
            if (isset($_COOKIE['EAX'])) {
                if (Mktr\Helper\Valid::getParam('orders') !== null) {
                    $orders = explode(',', Mktr\Helper\Valid::getParam('orders'));
                    $list = [];

                    foreach ($orders as $order) {
                        $list[] = ['id' => $order, 'is_order' => true];
                    }

                    \Mktr\Helper\Session::set('save_order', $list);
                    \Mktr\Helper\Session::save();
                } elseif (Mktr\Helper\Valid::getParam('update_orders') !== null) {
                    $orders = explode(',', Mktr\Helper\Valid::getParam('update_orders'));
                    $list = [];
                    foreach ($orders as $order) {
                        $temp = \Mktr\Model\Orders::getByID($order);
                        $send = [
                            'order_number' => $temp->number,
                            'order_status' => $temp->order_status,
                        ];

                        \Mktr\Helper\Api::send('update_order_status', $send, false);
                    }
                }
            }
        }
    }

    public function hookactionOrderStatusUpdate($newStatus = null)
    {
        if ($newStatus !== null && Mktr\Model\Config::rest()) {
            $send = [
                'order_number' => $newStatus['id_order'],
                'order_status' => $newStatus['newOrderStatus']->name,
            ];

            \Mktr\Helper\Api::send('update_order_status', $send, false);
        }
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/back.css');
    }

    public function hookDisplayHeader($params)
    {
        if (self::$displayLoad['header'] === true && Mktr\Model\Config::showJS()) {
            self::$displayLoad['header'] = false;
            $js = Mktr\Model\Config::i()->js_file;
            if ($js !== '') {
                if (Mktr\Helper\Session::get('cartID', null) !== $this->context->cart->id) {
                    Mktr\Helper\Session::set('cartID', $this->context->cart->id);
                    Mktr\Helper\Session::save();
                }
                $this->context->controller->addJS($this->_path . 'mktr.' . $js . '.js');
            }
        }
    }

    public function hookDisplayBeforeBodyClosingTag($params)
    {
        return $this->script();
    }

    public function hookDisplayFooter()
    {
        return $this->script();
    }

    public function hookDisplayFooterBefore($params)
    {
        return $this->script();
    }

    public function hookDisplayFooterAfter()
    {
        return $this->script();
    }

    public function script()
    {
        if (self::$displayLoad['footer'] === true && Mktr\Model\Config::showJS()) {
            self::$displayLoad['footer'] = false;

            $data = null;
            $events = [];
            $action = Mktr\Helper\Valid::getParam('controller', null);
            // $listCheck = [];
            switch ($action) {
                case '':
                case 'index':
                    $action = 'home_page';
                    break;
                case 'category':
                    $action = 'category';
                    $data = Mktr\Helper\Valid::toJson(['category' => Mktr\Model\Category::getByID(Mktr\Helper\Valid::getParam('id_category'))->hierarchy]);
                    break;
                case 'manufacturer':
                    $action = 'brand';
                    $data = ['name' => Mktr\Model\Brand::getByID(Mktr\Helper\Valid::getParam('id_manufacturer'))->name];
                    break;
                case 'search':
                    $action = 'search';
                    $data = ['search_term' => Mktr\Helper\Valid::getParam(_PS_VERSION_ >= 1.7 ? 's' : 'search_query')];
                    break;
                case 'product':
                    $action = 'product';
                    $data = ['product_id' => Mktr\Helper\Valid::getParam('id_product')];
                    break;
                case 'order':
                    // case 'cart':
                    $data = 0;
                    $action = 'checkout';
                    if ($this->context->controller instanceof OrderController) {
                        if (method_exists($this->context->controller, 'getCheckoutProcess')) {
                            $checkoutSteps = $this->context->controller->getCheckoutProcess()->getSteps();
                        } elseif (_PS_VERSION_ >= 1.7) {
                            $reflectedObject = (new ReflectionObject($this->context->controller))->getProperty('checkoutProcess');
                            $reflectedObject->setAccessible(true);
                            $checkoutProcessClass = $reflectedObject->getValue($this->context->controller);
                            $checkoutSteps = $checkoutProcessClass->getSteps();
                        } else {
                            $checkOUT = Mktr\Helper\Valid::getParam('checkout');

                            if ($checkOUT !== null && $checkOUT == 1) {
                                $checkoutSteps = [];
                                $action = 'checkout';
                                $data = 1;
                            } elseif ($this->context->controller->step == 1) {
                                $checkoutSteps = [];
                                $action = 'checkout';
                                $data = $this->context->controller->step;
                            }
                        }
                        if (empty($checkoutSteps)) {
                            $data = 1;
                            $action = 'checkout';
                        } else {
                            $data = 0;
                            foreach ($checkoutSteps as $stepObject) {
                                if ($data === 0 && ($stepObject instanceof CheckoutPersonalInformationStep || $stepObject instanceof CheckoutAddressesStep)) {
                                    $data = (int) $stepObject->isCurrent();
                                }
                                // $listCheck[] = $stepObject->getTitle();
                            }
                        }

                        if ($data == 0) {
                            $checkOUT = Mktr\Helper\Valid::getParam('checkout');
                            if ($checkOUT !== null && $checkOUT == 1) {
                                $checkoutSteps = [];
                                $action = 'checkout';
                                $data = 1;
                            }
                        } else {
                            $action = 'checkout';
                        }
                    } else {
                        $data = 1;
                        $action = 'checkout';
                    }

                    if ($data === 0) {
                        $action = null;
                    }

                    $data = null;
                    break;
                default:
            }

            if ($data === null) {
                $data = 'null';
            } elseif (is_array($data)) {
                $data = Mktr\Helper\Valid::toJson($data);
            }
            $main = '';
            $events[] = '<script type="text/javascript"> window.mktr = window.mktr || {}; ';
            $events[] = 'window.mktr.toLoad = window.mktr.toLoad || [];';
            /*
            $events[] = 'window.mktr.action = "' . Mktr\Helper\Valid::getParam('controller', null) . '";';
            $events[] = 'window.mktr.listDataCheck = "' . json_encode($listCheck) . '";';
            */

            if ($action !== null) {
                $main = 'window.mktr.buildEvent("' . $action . '", ' . ($data === null ? 'null' : $data) . ');';
            }
            $events[] = 'window.mktr.runEvents = function () {
                if (typeof window.mktr.tryLoad == "undefined") { window.mktr.tryLoad = 0; }
                if (window.mktr.tryLoad <= 5 && typeof window.mktr.buildEvent == "function") { ' . $main . ' window.mktr.loadEvents(); } else if(window.mktr.tryLoad <= 5) { window.mktr.tryLoad++; setTimeout(window.mktr.runEvents, 1500); }
            }';
            $events[] = 'window.mktr.runEvents();';

            $evList = [
                'set_email' => 'setEmail',
                'set_phone' => 'setEmail',
                'save_order' => 'saveOrder',
            ];
            $add = [
                'setEmail' => false,
                'saveOrder' => false,
            ];
            $events[] = ' </script>';
            /*
                        // $rewrite = (bool) \Mktr\Model\Config::getConfig('PS_REWRITING_SETTINGS');

                        //$linkPath = \Tools::getShopDomainSsl(true);
                        //$linkPath = $linkPath . (substr($linkPath, -1) === '/' ? '' : '/');
            */
            foreach ($evList as $key => $value) {
                if (!empty(Mktr\Helper\Session::get($key)) && $add[$value] === false) {
                    $add[$value] = true;
                    $events[] = '<noscript><iframe src="/?fc=module&module=mktr&controller=Api&pg=' . $value . '&mktr_time=' . time() . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
                    /* $events[] = '<noscript><iframe src="' . $linkPath . ($rewrite ? 'mktr/api/' . $value . '?' : '?fc=module&module=mktr&controller=Api&pg=' . $value . '&') . 'mktr_time=' . time() . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>'; */
                }
            }

            return PHP_EOL . implode(PHP_EOL, $events);
        }
    }

    public function hookModuleRoutes()
    {
        return [
            'mktr-api' => [
                'rule' => 'mktr/api/{pg}',
                'keywords' => [
                    'pg' => [
                        'regexp' => '.*',
                        'param' => 'pg',
                    ],
                ],
                'controller' => 'Api',
                'params' => [
                    'fc' => 'module',
                    'module' => 'mktr',
                ],
            ],
        ];
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this, $name)) {
            return call_user_func_array([$this, $name], $arguments);
        } else {
            if (_PS_MODE_DEV_) {
                throw new \Exception("Method {$name} does not exist.");
            }

            return null;
        }
    }

    public static function __callStatic($name, $arguments)
    {
        if (self::$i === null) {
            self::$i = new static();
        }

        if (method_exists(self::$i, $name)) {
            return call_user_func_array([self::$i, $name], $arguments);
        } else {
            if (_PS_MODE_DEV_) {
                throw new \Exception("Static method {$name} does not exist.");
            }

            return null;
        }
    }

    public static function finLoad()
    {
        spl_autoload_unregister([self::i(), 'load']);
    }
}
