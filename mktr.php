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
if (!defined('_PS_VERSION_')) {
    exit;
}

if (!defined('MKTR_ROOT')) {
    define('MKTR_ROOT', rtrim(_PS_ROOT_DIR_, '/') . '/');
}

if (!defined('MKTR_APP')) {
    $d = MKTR_ROOT . 'modules/mktr/';
    define('MKTR_APP', rtrim($d, '/') . '/');
}

class Mktr extends \Module
{
    public static $expire = 172800; // seconds

    public static $init = false;

    private static $i;

    private static $update = true;

    private static $included = [];

    private static $displayLoad = [
        'header' => true,
        'footer' => true,
        'dispatcher' => true,
    ];

    public static $checkList = [
        'update' => false,
        'isAdd' => false,
        'isDel' => false,
    ];

    public function __construct()
    {
        $this->name = 'mktr';
        $this->tab = 'advertising_marketing';
        $this->version = '1.1.6';
        $this->author = 'TheMarketer.com';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->module_key = '4d03ed6733df2cd9460531dbf772b2d2';

        parent::__construct();

        $this->displayName = 'TheMarketer';
        $this->description = 'TheMarketer - PrestaShop Version';
        $this->confirmUninstall = 'Are you sure you want to uninstall this module?';
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
        self::$i = $this;
        self::$init = true;

        spl_autoload_register([$this, 'load'], true, true);

        /* @phpstan-ignore-next-line */
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
        $realFilePath = realpath($filePath);
        $realBase = realpath(MKTR_APP);

        if ($realFilePath === false || $realBase === false) {
            throw new \Exception('Invalid file path.');
        }

        if (strpos($realFilePath, $realBase) !== 0) {
            throw new \Exception('File path outside module directory.');
        }

        $allowedFiles = [
            'mktr.php',
            'controllers/admin/MktrController.php',
        ];

        $relativePath = str_replace($realBase . '/', '', $realFilePath);
        if (!in_array($relativePath, $allowedFiles)) {
            throw new \Exception('File not in allowed list.');
        }

        if (!file_exists($realFilePath) || !is_readable($realFilePath)) {
            throw new \Exception('File does not exist or is not readable.');
        }

        $content = \Tools::file_get_contents($realFilePath, true);
        if ($content === false) {
            throw new \Exception('Failed to read file.');
        }

        $newContent = str_replace($from, $to, $content);

        if (!is_writable($realFilePath)) {
            throw new \Exception('File is not writable.');
        }

        $file = fopen($realFilePath, 'w+');
        if ($file === false) {
            throw new \Exception('Failed to open file for writing.');
        }

        fwrite($file, $newContent);
        fclose($file);
    }

    public static function viewAccess()
    {
        return true;
    }

    public static function preConfig()
    {
        if (self::$update) {
            if (file_exists(MKTR_APP . 'mktr.php')) {
                self::$update = false;
                \Mktr\Route\refreshJS::resetConfig();
                \Mktr\Route\refreshJS::loadJs();

                // Only the flag is rewritten, and only in this file - the
                // admin controller has no such flag, so rewriting it was a
                // no-op that still wrote the file back to disk.
                //
                // The path patterns that used to live here matched their own
                // source line: str_replace runs over the whole file, the array
                // of patterns included, so the first run baked the machine's
                // absolute path into the module. The flag survives that only
                // because its pattern is assembled at runtime and never
                // appears whole in the file.
                self::correctUpdate(
                    MKTR_APP . 'mktr.php',
                    [implode('', ['private static $update ', '= true;'])],
                    ['private static $update = false;']
                );
            }
        }
    }

    public function gFile($fn)
    {
        return 'mktr/' . explode('mktr/', $fn)[1];
    }

    public function install()
    {
        if (\Shop::isFeatureActive()) {
            \Shop::setContext(\Shop::CONTEXT_ALL);
        }

        if (_PS_VERSION_ >= 1.6) {
            $hook = [
                /* Front */
                'Header',
                'displayHeader',
                'moduleRoutes',
                'displayFooterAfter',
                'displayFooterBefore',
                'actionDispatcher',
                'actionDispatcherBefore',
                'actionControllerInitBefore',
                /* Orders */
                'actionValidateOrder',
                /* Admin */
                'displayBackOfficeHeader',
                'actionOrderStatusUpdate',
            ];
        } else {
            $hook = [
                /* Front */
                'displayHeader',
                'moduleRoutes',
                'actionDispatcher',
                'actionDispatcherBefore',
                'actionControllerInitBefore',
                /* Orders */
                'actionValidateOrder',
                /* Admin */
                'displayBackOfficeHeader',
                'actionOrderStatusUpdate',
            ];
        }

        if (_PS_VERSION_ >= 1.7) {
            $hook[] = 'displayBeforeBodyClosingTag';
        } else {
            $hook[] = 'displayFooter';
        }

        if (!\Mktr\Helper\Setup::install()) {
            return false;
        }

        if (_PS_VERSION_ >= 1.6) {
            foreach ($hook as $kk => $vv) {
                if (!(\Hook::getIdByName($vv) > 0)) {
                    unset($hook[$kk]);
                    file_put_contents(MKTR_APP . 'Storage/install.log', date('Y-m-d H:i:s') . '[NOT_FOUND] ' . $this->gFile(__FILE__) . ' - Line ' . __LINE__ . ' [' . $vv . "]\n", FILE_APPEND);
                }
            }

            $exist = [];

            foreach ($hook as $kk => $vv) {
                if ($this->isRegisteredInHook($vv)) {
                    $exist[] = $vv;
                    unset($hook[$kk]);
                    file_put_contents(MKTR_APP . 'Storage/install.log', date('Y-m-d H:i:s') . '[EXIST] ' . $this->gFile(__FILE__) . ' - Line ' . __LINE__ . ' [' . $vv . "]\n", FILE_APPEND);
                }
            }

            if (in_array('displayHeader', $hook) && in_array('Header', $exist)) {
                $key = array_search('displayHeader', $hook);

                if ($key !== false) {
                    unset($hook[$key]);
                }
            }

            if (parent::install() && $this->registerHook($hook)) {
                return true;
            } else {
                $this->_errors[] = 'There was an error during registerHook procces.';

                return false;
            }
        } else {
            if (!parent::install()) {
                $this->_errors[] = 'There was an error during the Install procces.';
                file_put_contents(MKTR_APP . 'Storage/install.log', date('Y-m-d H:i:s') . '[NOT_FOUND] parent::install() ' . $this->gFile(__FILE__) . ' - Line ' . __LINE__ . "\n", FILE_APPEND);

                return false;
            }

            foreach ($hook as $kk => $vv) {
                if (\Hook::getIdByName($vv) > 0) {
                    if (!$this->registerHook($vv)) {
                        $this->_errors[] = 'There was an error during registerHook procces. [' . $vv . ']';
                    }
                } else {
                    file_put_contents(MKTR_APP . 'Storage/install.log', date('Y-m-d H:i:s') . '[NOT_FOUND] ' . $this->gFile(__FILE__) . ' - Line ' . __LINE__ . ' [' . $vv . "]\n", FILE_APPEND);
                }
            }

            return true;
        }
    }

    public function uninstall()
    {
        if (\Shop::isFeatureActive()) {
            \Shop::setContext(\Shop::CONTEXT_ALL);
        }

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
        $mboInstaller = new \Prestashop\ModuleLibMboInstaller\DependencyBuilder($this);
        if (!$mboInstaller->areDependenciesMet()) {
            $dependencies = $mboInstaller->handleDependencies();
            $this->smarty->assign('dependencies', $dependencies);
            return $this->display(__FILE__, 'views/templates/admin/dependency_builder.tpl');
        }

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
        } elseif (strtolower($className) == 'mktrapimodulefrontcontroller') {
            $className = 'MktrApiModuleFrontController';

            if (!array_key_exists($className, self::$included)) {
                self::$included[$className] = true;
                $file = MKTR_APP . 'controllers/front/Api.php';

                if (!file_exists($file)) {
                    $file = MKTR_APP . 'controllers/front/api.php';
                }

                if (file_exists($file)) {
                    require_once $file;
                }
            }
        }
    }

    public static function getExpire()
    {
        return time() + self::$expire;
    }

    public function hookactionDispatcher()
    {
        $this->initDispatcher();
    }

    public function hookactionDispatcherBefore()
    {
        $this->initDispatcher();
    }

    public function hookactionControllerInitBefore()
    {
        $this->initDispatcher();
    }

    public function initDispatcher()
    {
        if (self::$displayLoad['dispatcher'] === true && \Mktr\Model\Config::showJS()) {
            self::$displayLoad['dispatcher'] = false;

            $cont = \Mktr\Helper\Valid::getParam('controller', null);

            if (_PS_VERSION_ < 1.7 && $cont !== null && strpos($cont, 'Admin') !== false && strpos($cont, 'admin') !== false) {
                return true;
            }

            // \Mktr\Helper\Session::init();

            $pId = null;
            $pAttr = null;
            $qty = null;

            $email = \Mktr\Helper\Valid::getParam('email', null);
            $phone = \Mktr\Helper\Valid::getParam('phone', null);
            $phone1 = \Mktr\Helper\Valid::getParam('phone_mobile', null);

            if ($email !== null) {
                $remove = false;

                if (in_array(\Mktr\Helper\Valid::getParam('controller', null), ['identity'])) {
                    $remove = true;
                }

                $toAdd = [$email, $remove];

                if ($phone !== null && !empty($phone) || $phone1 !== null && !empty($phone1)) {
                    $toAdd[] = ($phone !== null ? $phone : $phone1);
                }

                \Mktr\Helper\Session::setEmail($toAdd);
                \Mktr\Helper\Session::save();
            }

            self::$checkList['update'] = in_array(\Mktr\Helper\Valid::getParam('action', null), ['update', 'cos', 'cart']) || in_array(\Mktr\Helper\Valid::getParam('controller', null), ['cart']);
            self::$checkList['isAdd'] = self::$checkList['update'] && \Mktr\Helper\Valid::getParam('add', null) !== null;
            self::$checkList['isDel'] = self::$checkList['update'] && \Mktr\Helper\Valid::getParam('delete', null) !== null;
            $CheckIsAdd = self::$checkList['update'] && self::$checkList['isAdd'];
            $CheckIsDel = self::$checkList['update'] && self::$checkList['isDel'];
            $action = \Mktr\Helper\Valid::getParam('action', null);

            if (_PS_VERSION_ >= 1.7) {
                if ($CheckIsAdd) {
                    $pId = \Mktr\Helper\Valid::getParam('id_product', null);
                    $pGrup = \Mktr\Helper\Valid::getParam('group', null);

                    if ($pGrup !== null) {
                        $pAttr = (int) \Product::getIdProductAttributeByIdAttributes($pId, $pGrup, true);
                    }

                    $qty = \Mktr\Helper\Valid::getParam('qty', null);
                }

                if ($CheckIsDel) {
                    $pId = \Mktr\Helper\Valid::getParam('id_product', null);
                    $pAttr = \Mktr\Helper\Valid::getParam('id_product_attribute', null);
                }
            } else {
                if ($CheckIsAdd) {
                    $pId = \Mktr\Helper\Valid::getParam('id_product', null);
                    $pAttr = \Mktr\Helper\Valid::getParam('ipa', null);
                    $qty = \Mktr\Helper\Valid::getParam('qty', null);
                }

                if ($CheckIsDel) {
                    $pId = \Mktr\Helper\Valid::getParam('id_product', null);
                    $pAttr = \Mktr\Helper\Valid::getParam('ipa', null);
                }
            }

            if ($action == 'toggleProductWishlist') {
                $p = \Mktr\Helper\Valid::getParam('id_product', null);

                if ($p !== null) {
                    \Mktr\Helper\Session::Wishlist($p, 0);
                    \Mktr\Helper\Session::save();
                }
            } elseif (self::$checkList['update'] && self::$checkList['isAdd']) {
                \Mktr\Helper\Session::addToCart($pId, $pAttr, $qty);
                \Mktr\Helper\Session::save();
            } elseif (self::$checkList['update'] && self::$checkList['isDel']) {
                $cartId = \Mktr\Helper\Session::get('cartID', null);
                $list = \Mktr\Model\Product::getQty($pId, $pAttr, $cartId);

                foreach ($list as $value) {
                    if ($value['id_product_attribute'] === $pAttr && $value['id_product'] === $pId) {
                        $qty = $value['quantity'];
                        break;
                    }
                }

                \Mktr\Helper\Session::removeFromCart($pId, $pAttr, $qty);
                \Mktr\Helper\Session::save();
            } elseif (_PS_VERSION_ >= 1.7 && $action !== null) {
                if ($action === 'addProductToWishlist') {
                    $p = \Mktr\Helper\Valid::getParam('params', null);
                    \Mktr\Helper\Session::addToWishlist($p['id_product'], $p['id_product_attribute']);
                    \Mktr\Helper\Session::save();
                } elseif ($action === 'deleteProductFromWishlist') {
                    $p = \Mktr\Helper\Valid::getParam('params', null);
                    \Mktr\Helper\Session::removeFromWishlist($p['id_product'], $p['id_product_attribute']);
                    \Mktr\Helper\Session::save();
                } elseif (in_array($action, ['addFavoriteProduct', 'removeFavoriteProduct'])) {
                    $id_product = \Mktr\Helper\Valid::getParam('id_product', null);
                    $id_product_attribute = \Mktr\Helper\Valid::getParam('id_product_attribute', null);

                    if ($action === 'addFavoriteProduct') {
                        \Mktr\Helper\Session::addToWishlist($id_product, $id_product_attribute);
                        \Mktr\Helper\Session::save();
                    } else {
                        \Mktr\Helper\Session::removeFromWishlist($id_product, $id_product_attribute);
                        \Mktr\Helper\Session::save();
                    }
                }
            } else {
                if (\Mktr\Helper\Valid::getParam('process', null) === 'add') {
                    $p = \Mktr\Helper\Valid::getParam('id_product', null);

                    if ($p !== null) {
                        \Mktr\Helper\Session::addToWishlist($p, 0);
                        \Mktr\Helper\Session::save();
                    }
                } elseif (\Mktr\Helper\Valid::getParam('process', null) === 'remove') {
                    $p = \Mktr\Helper\Valid::getParam('id_product', null);

                    if ($p !== null) {
                        \Mktr\Helper\Session::removeFromWishlist($p, 0);
                        \Mktr\Helper\Session::save();
                    }
                }
            }

            $cont = \Mktr\Helper\Valid::getParam('controller', null);

            if (in_array($cont, ['order-confirmation', 'thank_you_page', 'orderconfirmation', 'confirmare-comanda'])) {
                $svOrder = \Mktr\Helper\Session::get('save_order');
                $id_order = \Mktr\Helper\Valid::getParam('id_order', null);
                $expire = self::getExpire();

                if ($id_order === null) {
                    $cartId = \Mktr\Helper\Valid::getParam('id_cart', null);

                    if ($cartId === null) {
                        $cartId = \Mktr\Helper\Valid::getParam('orderId', null);
                        $cartId = explode('%', $cartId);
                        $cartId = $cartId[0];
                    }

                    if ($cartId !== null) {
                        $svOrder[$cartId] = ['id' => $cartId, 'is_order' => false, 'expire' => $expire];
                        \Mktr\Helper\Session::set('save_order', $svOrder);
                        \Mktr\Helper\Session::save();
                    }
                } else {
                    $svOrder[$id_order] = ['id' => $id_order, 'is_order' => true, 'expire' => $expire];
                    \Mktr\Helper\Session::set('save_order', $svOrder);
                    \Mktr\Helper\Session::save();
                }
            }
            /*
            $vivaController = _PS_MODULE_DIR_ . 'vivawallet/controllers/front/smartcheckout/success.php';
            if (file_exists($vivaController)) {
                require_once $vivaController;
            }

            if (class_exists('VivaWalletSmartCheckoutSuccessModuleFrontController')) {
                if (is_callable(['VivaWalletSmartCheckoutSuccessModuleFrontController', 'getOrderId'])) {
                    $svOrder = \Mktr\Helper\Session::get('save_order');
                    $vivaWallet = \Mktr\Helper\Valid::getParam('s', null);
                    $expire = self::getExpire();
                    $id_order = null;
                    $cartId = null;

                    if (method_exists('VivaWalletSmartCheckoutSuccessModuleFrontController', 'getOrderId')) {
                        $id_order = (int) \VivaWalletSmartCheckoutSuccessModuleFrontController::getOrderId($vivaWallet, false);

                        if (empty($id_order)) {
                            $cartId = (int) \VivaWalletSmartCheckoutSuccessModuleFrontController::getOrderId($vivaWallet, true);
                            $id_order = null;
                        }
                    }

                    if ($id_order === null) {
                        if ($cartId !== null) {
                            $svOrder[$cartId] = ['id' => $cartId, 'is_order' => false, 'expire' => $expire];
                            \Mktr\Helper\Session::set('save_order', $svOrder);
                            \Mktr\Helper\Session::save();
                        }
                    } else {
                        $svOrder[$id_order] = ['id' => $id_order, 'is_order' => true, 'expire' => $expire];
                        \Mktr\Helper\Session::set('save_order', $svOrder);
                        \Mktr\Helper\Session::save();
                    }
                }
            }
            */

            if (isset($_COOKIE['EAX'])) {
                if (\Mktr\Helper\Valid::getParam('orders') !== null) {
                    $orders = explode(',', \Mktr\Helper\Valid::getParam('orders'));
                    $list = [];

                    foreach ($orders as $order) {
                        $list[] = ['id' => $order, 'is_order' => true];
                    }

                    \Mktr\Helper\Session::set('save_order', $list);
                    \Mktr\Helper\Session::save();
                } elseif (\Mktr\Helper\Valid::getParam('update_orders') !== null) {
                    $orders = explode(',', \Mktr\Helper\Valid::getParam('update_orders'));
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

            self::scheduleOrderSync();
        }
    }

    /**
     * Fires inside PaymentModule::validateOrder(), so it catches every payment
     * method - including the ones that create the order in a server-to-server
     * callback and never bring the customer back to the confirmation page.
     *
     * It only records the order. Delivering it from here would put an HTTP
     * call on the checkout path, and on the payment provider's callback path,
     * where a slow response is retried or treated as a failed payment. The
     * confirmation page pushes it moments later, and the cron picks up
     * whatever never got a confirmation page.
     */
    public function hookactionValidateOrder($params = null)
    {
        if (empty($params['order']) || !\Mktr\Model\Config::rest()) {
            return;
        }

        try {
            \Mktr\Model\OrderSync::enroll((int) $params['order']->id);
        } catch (\Exception $e) {
            // Never let tracking break order creation - the sweeper retries.
            self::orderLog('VALIDATE_ORDER', $e->getMessage());
        } catch (\Throwable $e) {
            self::orderLog('VALIDATE_ORDER', $e->getMessage());
        }
    }

    private static function orderLog($tag, $message)
    {
        @file_put_contents(
            MKTR_APP . 'Storage/install.log',
            date('Y-m-d H:i:s') . '[' . $tag . '] ' . $message . "\n",
            FILE_APPEND
        );
    }

    /**
     * Emergency delivery for shops with no working cron.
     *
     * The cron endpoint is the supported way to deliver orders. This exists
     * only for the shop that never added it, where orders created by a payment
     * callback would otherwise never leave. As long as the cron has run in the
     * last day it costs one cached lookup and does nothing else.
     */
    public static function scheduleOrderSync()
    {
        if (!\Mktr\Model\Config::rest()) {
            return;
        }

        $cont = \Mktr\Helper\Valid::getParam('controller', null);

        if (in_array(strtolower((string) $cont), ['api', 'cron'])) {
            return;
        }

        try {
            if (!\Mktr\Route\SyncOrders::cronIsStale()) {
                return;
            }

            $data = \Mktr\Helper\Data::init();

            if ((int) $data->next_order_sync > time()) {
                return;
            }

            // Reserved before the sweep runs, so parallel requests do not pile up.
            $data->next_order_sync = time() + 900;
            \Mktr\Helper\Data::save();
        } catch (\Exception $e) {
            return;
        }

        register_shutdown_function(['Mktr', 'runOrderSync']);
    }

    public static function runOrderSync()
    {
        // Hand the response back before spending a second per order on HTTP.
        // Without this the visitor's browser, and the php-fpm worker serving
        // it, wait for the whole sweep.
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        try {
            // Deliberately tiny. This is a stopgap for a missing cron, not a
            // second delivery system.
            \Mktr\Route\SyncOrders::run(2, 8);
        } catch (\Exception $e) {
            self::orderLog('SYNC_ORDERS', $e->getMessage());
        } catch (\Throwable $e) {
            self::orderLog('SYNC_ORDERS', $e->getMessage());
        }
    }

    public function hookactionOrderStatusUpdate($newStatus = null)
    {
        if ($newStatus !== null && \Mktr\Model\Config::rest()) {
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

    public function hScript()
    {
        if (self::$displayLoad['header'] === true && \Mktr\Model\Config::showJS()) {
            self::$displayLoad['header'] = false;
            $js = \Mktr\Model\Config::i()->js_file;

            if ($js !== '') {
                if (\Mktr\Helper\Session::get('cartID', null) !== $this->context->cart->id) {
                    \Mktr\Helper\Session::set('cartID', $this->context->cart->id);
                    \Mktr\Helper\Session::save();
                }

                $jsPrefix = \Mktr\Model\Config::getJsPrefix();
                $this->context->controller->addJS($this->_path . $jsPrefix . $js . '.js');
            }
        }
    }

    public function hookHeader($params = null)
    {
        return $this->hScript();
    }

    public function hookDisplayHeader($params = null)
    {
        return $this->hScript();
    }

    public function hookDisplayBeforeBodyClosingTag($params = null)
    {
        return $this->script();
    }

    public function hookDisplayFooter()
    {
        return $this->script();
    }

    public function hookDisplayFooterBefore($params = null)
    {
        return $this->script();
    }

    public function hookDisplayFooterAfter()
    {
        return $this->script();
    }

    public function script()
    {
        if (\Mktr\Model\Config::showJsOut()) {
            self::$displayLoad['footer'] = false;
        }

        if (self::$displayLoad['footer'] === true && \Mktr\Model\Config::showJS()) {
            self::$displayLoad['footer'] = false;

            $data = null;
            $events = [];
            $action = \Mktr\Helper\Valid::getParam('controller', null);

            // $listCheck = [];
            switch ($action) {
                case '':
                case 'index':
                    $action = 'home_page';
                    break;
                case 'category':
                    $action = 'category';
                    $data = \Mktr\Helper\Valid::toJson(['category' => \Mktr\Model\Category::getByID(\Mktr\Helper\Valid::getParam('id_category'))->hierarchy]);
                    break;
                case 'manufacturer':
                    $action = 'brand';
                    $data = ['name' => \Mktr\Model\Brand::getByID(\Mktr\Helper\Valid::getParam('id_manufacturer'))->name];
                    break;
                case 'search':
                    $action = 'search';
                    $data = ['search_term' => \Mktr\Helper\Valid::getParam(_PS_VERSION_ >= 1.7 ? 's' : 'search_query')];
                    break;
                case 'product':
                    $action = 'product';
                    $data = ['product_id' => \Mktr\Helper\Valid::getParam('id_product')];
                    break;
                case 'orderopc':
                    $action = 'checkout';
                    $data = null;
                    break;
                case 'order':
                    // case 'cart':
                    $data = 0;
                    $action = 'checkout';

                    if ($this->context->controller instanceof \OrderController) {
                        if (method_exists($this->context->controller, 'getCheckoutProcess')) {
                            $checkoutSteps = $this->context->controller->getCheckoutProcess()->getSteps();
                        } elseif (_PS_VERSION_ >= 1.7) {
                            $reflectedObject = (new \ReflectionObject($this->context->controller))->getProperty('checkoutProcess');
                            $reflectedObject->setAccessible(true);
                            $checkoutProcessClass = $reflectedObject->getValue($this->context->controller);
                            $checkoutSteps = $checkoutProcessClass->getSteps();
                        } else {
                            $checkOUT = \Mktr\Helper\Valid::getParam('checkout');

                            if ($checkOUT !== null && $checkOUT == 1) {
                                $checkoutSteps = [];
                                $action = 'checkout';
                                $data = 1;
                            } elseif (property_exists($this->context->controller, 'step') && $this->context->controller->step == 1) {
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
                                if ($data === 0 && ($stepObject instanceof \CheckoutPersonalInformationStep || $stepObject instanceof \CheckoutAddressesStep)) {
                                    $data = (int) $stepObject->isCurrent();
                                }
                                // $listCheck[] = $stepObject->getTitle();
                            }
                        }

                        if ($data == 0) {
                            $checkOUT = \Mktr\Helper\Valid::getParam('checkout');

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
                $data = \Mktr\Helper\Valid::toJson($data);
            }

            $main = '';
            $events[] = html_entity_decode('&lt;script type=&quot;text/javascript&quot;&gt;');
            $events[] = '(function(window) {';
            $events[] = 'window.mktr = window.mktr || {}; ';
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
                'save_order' => 'saveOrder',
            ];

            $add = [
                'setEmail' => false,
                'saveOrder' => false,
            ];

            $events[] = '})(window);';
            $events[] = ' </script>';

            $allowedPgValues = ['setEmail', 'saveOrder'];
            /*
                        // $rewrite = (bool) \Mktr\Model\Config::getConfig('PS_REWRITING_SETTINGS');

                        //$linkPath = \Tools::getShopDomainSsl(true);
                        //$linkPath = $linkPath . (substr($linkPath, -1) === '/' ? '' : '/');
            */
            foreach ($evList as $key => $value) {
                if (!empty(\Mktr\Helper\Session::get($key)) && $add[$value] === false) {
                    if (!in_array($value, $allowedPgValues, true)) {
                        continue;
                    }

                    if (!preg_match('/^[a-zA-Z]+$/', $value)) {
                        continue;
                    }

                    $add[$value] = true;

                    $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    $timestamp = (int) time();

                    $events[] = '<noscript><iframe src="/?fc=module&module=mktr&controller=Api&pg=' . $escapedValue . '&mktr_time=' . $timestamp . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
                }
            }

            return PHP_EOL . implode(PHP_EOL, $events);
            /*
            if (_PS_VERSION_ > 1.6) {
            } else {
                echo PHP_EOL . implode(PHP_EOL, $events);
            }*/
        }
    }

    public function hookModuleRoutes()
    {
        return [
            'mktr-api-new' => [
                'rule' => 'mktr/{api}/{pg}',
                'keywords' => [
                    'pg' => [
                        'regexp' => '.*',
                        'param' => 'pg',
                    ],
                    'api' => [
                        'regexp' => 'Api|api',
                        'param' => 'Api',
                    ],
                ],
                'controller' => 'Api',
                'params' => [
                    'fc' => 'module',
                    'module' => 'mktr',
                    'controller' => 'Api',
                ],
            ],
        ];
    }

    public function __call($name, $arguments)
    {
        if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $name)) {
            if (_PS_MODE_DEV_) {
                throw new \Exception('Invalid method name.');
            }
            return null;
        }

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
        if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $name)) {
            if (_PS_MODE_DEV_) {
                throw new \Exception('Invalid static method name.');
            }
            return null;
        }

        if (self::$i === null) {
            $class = get_called_class();
            self::$i = new $class();
            // self::$i = new static();
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
