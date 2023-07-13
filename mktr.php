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
    // define('MKTR_APP', __DIR__ . (substr(__DIR__, -1) === '/' ? '' : '/'));
    $d = MKTR_ROOT . 'modules/mktr/';
    define('MKTR_APP', $d . (substr($d, -1) === '/' ? '' : '/'));
}

class Mktr extends Module
{
    private static $i = null;
    private static $included = [];
    private static $vr = [];
    private static $displayLoad = [
        'header' => true,
        'footer' => true,
        'dispatcher' => true,
    ];
    private static $runAction = true;

    public function __construct()
    {
        $this->name = 'mktr';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
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

        // $this->registerHook('actionDispatcher');
    }

    public static function i()
    {
        return self::$i;
    }

    public function install()
    {
        $hook = [
            /* Front */
            'displayHeader',
            'moduleRoutes',
            'actionDispatcher',
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
        Tools::redirectAdmin($this->context->link->getAdminLink('Mktr', true));

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

            $pId = null;
            $pAttr = null;
            $qty = null;

            $email = Mktr\Helper\Valid::getParam('email', null);
            $phone = Mktr\Helper\Valid::getParam('phone', null);
            $phone1 = Mktr\Helper\Valid::getParam('phone_mobile', null);

            if ($email !== null) {
                Mktr\Helper\Session::setEmail($email);
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
            if (Mktr\Helper\Session::get('cartID', null) !== $this->context->cart->id) {
                Mktr\Helper\Session::set('cartID', $this->context->cart->id);
                Mktr\Helper\Session::save();
            }
            $this->context->controller->addJS($this->_path . 'views/js/mktr.js');
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

    public function script()
    {
        if (self::$displayLoad['footer'] === true && Mktr\Model\Config::showJS()) {
            self::$displayLoad['footer'] = false;

            $action = Mktr\Helper\Valid::getParam('controller', null);
            $id_order = null;
            if (in_array($action, ['order-confirmation', 'thank_you_page', 'orderconfirmation', 'confirmare-comanda'])) {
                $id_order = Mktr\Helper\Valid::getParam('id_order', null);
                if ($id_order !== null) {
                    Mktr\Helper\Session::set('save_order', [$id_order]);
                    Mktr\Helper\Session::save();
                }
            }

            $data = null;
            $events = [];
            $action = Mktr\Helper\Valid::getParam('controller', null);
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
                        } else {
                            $reflectedObject = (new ReflectionObject($this->context->controller))->getProperty('checkoutProcess');
                            $reflectedObject->setAccessible(true);
                            $checkoutProcessClass = $reflectedObject->getValue($this->context->controller);
                            $checkoutSteps = $checkoutProcessClass->getSteps();
                        }

                        foreach ($checkoutSteps as $stepObject) {
                            if ($data === 0 && ($stepObject instanceof CheckoutPersonalInformationStep || $stepObject instanceof CheckoutAddressesStep)) {
                                $data = (int) $stepObject->isCurrent();
                            }
                        }
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

            $events[] = '<script type="text/javascript"> window.mktr = window.mktr || {}; ';
            $events[] = 'window.mktr.base = ' . (_PS_VERSION_ >= 1.7 ? "'" . $this->context->link->getBaseLink() . "'" : 'baseUri') . '';
            $events[] = 'window.mktr.base = window.mktr.base.substr(window.mktr.base.length - 1) === "/" ? window.mktr.base : window.mktr.base+"/";';

            $events[] = 'window.mktr.run = function () {';
            if ($action !== null) {
                $events[] = 'window.mktr.buildEvent("' . $action . '", ' . ($data === null ? 'null' : $data) . ');';
            }
            if ($id_order !== null) {
                $events[] = 'window.mktr.buildEvent("save_order", ' . Mktr\Model\Orders::getByID($id_order)->toEvent(true) . ');';
            }

            $events[] = '};';
            $events[] = '(typeof window.mktr.buildEvent != "function") ? document.addEventListener("mktr_loaded", function () { window.mktr.run(); }) : window.mktr.run();';

            $evList = [
                'set_email' => 'setEmail',
                'set_phone' => 'setEmail',
                'save_order' => 'saveOrder',
            ];
            $add = [
                'setEmail' => false,
                'saveOrder' => false,
            ];

            $rewrite = (bool) \Mktr\Model\Config::getConfig('PS_REWRITING_SETTINGS');
            $events[] = ' </script>';

            foreach ($evList as $key => $value) {
                if (!empty(Mktr\Helper\Session::get($key)) && $add[$value] === false) {
                    $add[$value] = true;
                    $events[] = '<script type="text/javascript"> (function(){ let add = document.createElement("script"); add.async = true; add.src = window.mktr.base + "' . ($rewrite ? 'mktr/api/' . $value . '&' : '?fc=module&module=mktr&controller=Api&pg=' . $value . '&') . 'mktr_time="+(new Date()).getTime(); let s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(add,s); })(); </script>';
                    $events[] = '<noscript><iframe src="' . $this->context->link->getBaseLink() . ($rewrite ? 'mktr/api/' . $value . '&' : '?fc=module&module=mktr&controller=Api&pg=' . $value . '&') . 'mktr_time=' . time() . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
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
