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

namespace Mktr\Route;

if (!defined('_PS_VERSION_')) {
    exit;
}

class refreshJS
{
    const FIREBASE_CONFIG = 'const firebaseConfig = {
    apiKey: "AIzaSyA3c9lHIzPIvUciUjp1U2sxoTuaahnXuHw",
    projectId: "themarketer-e5579",
    messagingSenderId: "125832801949",
    appId: "1:125832801949:web:0b14cfa2fd7ace8064ae74"
};

firebase.initializeApp(firebaseConfig);';

    const FIREBASE_MESSAGING_SW = 'importScripts("https://www.gstatic.com/firebasejs/9.4.0/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/9.4.0/firebase-messaging-compat.js");
importScripts("./firebase-config.js");
importScripts("https://t.themarketer.com/firebase.js");';

    private static $config;

    public static function run()
    {
        self::loadJs();
        self::updatePushStatus();

        return ['status' => 'done'];
    }

    private static function c()
    {
        if (self::$config === null) {
            self::$config = \Mktr\Model\Config::i();
        }

        return self::$config;
    }

    public static function loadJs()
    {
        if (\Mktr\Model\Config::showJs(true)) {
            $c = 'window.mktr = window.mktr || {};
if (typeof window.mktr.PS_VERSION == "undefined") {
    window.dataLayer = window.dataLayer || [];
    window.mktr.PS_VERSION = "' . _PS_VERSION_ . '";
    window.mktr.MKTR_VERSION = "' . \Mktr::i()->version . '";
    window.mktr.debug = function () { if (typeof dataLayer != "undefined") { for (let i of dataLayer) { console.log("Mktr", "Google", i); } } };
    window.mktr.ready = false;
    window.mktr.page = null;
    window.mktr.params = window.mktr.params || new URLSearchParams(window.location.search);
    window.mktr.pending = window.mktr.pending || [];
    window.mktr.toLoad = window.mktr.toLoad || [];
    window.mktr.retryCount = 0;
    window.mktr.loading = true;
    window.mktr.original = window.mktr.original || {};
';

            if (\Mktr\Model\Config::showGoogle()) {
                $c = $c . "(function(w,d,s,l,i){
w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','" . self::c()->google_tagCode . "');

";
            }

            if (defined('__PS_BASE_URI__')) {
                $base = '"' . __PS_BASE_URI__ . '"';
            } elseif (_PS_VERSION_ >= 1.7) {
                $base = '"' . \Tools::getShopDomainSsl(true) . '"';
            } else {
                $base = 'baseUri';
            }
            if (self::c()->js_status) {
                $js_status = '
    if (typeof window.ajaxCart.add == "function") {
        window.mktr.original.add = window.ajaxCart.add;
        window.ajaxCart.add = function () {
            setTimeout(window.mktr.loadEvents, 2000);
            window.mktr.original.add(...arguments);
        };
    }
    if (typeof window.ajaxCart.remove == "function") {
        window.mktr.original.remove = window.ajaxCart.remove;
        window.ajaxCart.remove = function () {
            setTimeout(window.mktr.loadEvents, 2000);
            window.mktr.original.remove(...arguments);
        };
    }
    /* northfinder */
    if (typeof window.send_add_to_wishlist_event == "function") {
        window.mktr.original.send_add_to_wishlist_event = window.send_add_to_wishlist_event;
        window.send_add_to_wishlist_event = function () {
            window.mktr.wishList("__sm__add_to_wishlist", arguments[0]);
            return window.mktr.original.send_add_to_wishlist_event(...arguments);
        };
    }
    if (typeof window.send_remove_from_wishlist_event == "function") {
        window.mktr.original.send_remove_from_wishlist_event = window.send_remove_from_wishlist_event;
        window.send_remove_from_wishlist_event = function () {
            window.mktr.wishList("__sm__remove_from_wishlist", arguments[0]);
            window.mktr.original.send_remove_from_wishlist_event(...arguments);
        };
    }
    if (typeof window.send_newsletter_subscribe_event == "function") {
        window.mktr.original.send_newsletter_subscribe_event = window.send_newsletter_subscribe_event;
        window.send_newsletter_subscribe_event = function () {
            setTimeout(window.mktr.loadEvents, 2000);
            window.mktr.original.send_newsletter_subscribe_event(...arguments);
        };
    }
    if (typeof window.send_create_account_event == "function") {
        window.mktr.original.send_create_account_event = window.send_create_account_event;
        window.send_create_account_event = function () {
            setTimeout(window.mktr.loadEvents, 2000);
            window.mktr.original.send_create_account_event(...arguments);
        };
    }
    /* END northfinder */

    setTimeout(function() {
        switch ($("body").prop("id")) {
            case "":
            case "index":
                window.mktr.page = [ "home_page", null];
            break;
            case "category":
                window.mktr.loadData("category", window.location.pathname.match(/(\d+)-/)[1]);
            break;
            case "manufacturer":
                window.mktr.loadData("brand", window.location.pathname.match(/(\d+)_/)[1]);
            break;
            case "search":
                window.mktr.page = [ "search", { search_term: (window.mktr.params.get("search_query") ?? window.mktr.params.get("s")) } ];
            break;
            case "product":
                if (typeof window.id_product != "undefined") {
                    window.mktr.page = ["product", {product_id: window.id_product}];
                }
            break;
            case "order-opc":
                window.mktr.page = [ "checkout", null ];
            break;
            case "order":
                if (window.mktr.params.get("step")) {
                    window.mktr.page = [ "checkout", null];
                }          
            break;
            default:
        }
        if (Array.isArray(window.mktr.page) && window.mktr.page[0]) {
            window.mktr.buildEvent( window.mktr.page[0],  window.mktr.page[1]);
        }
    }, 1000);
';
            } else {
                $js_status = '
    window.mktr.setAjax();
    window.mktr.setFetch();
';
            }
            /** @phpstan-ignore-next-line */
            $rewrite = (bool) \Mktr\Model\Config::getConfig('PS_REWRITING_SETTINGS');
            $c = $c . '(function(d, s, i) {
var f = d.getElementsByTagName(s)[0], j = d.createElement(s);j.async = true;
j.src = "https://t.themarketer.com/t/j/" + i; f.parentNode.insertBefore(j, f);
window.mktr.ready = true;
})(document, "script", "' . self::c()->tracking_key . '");

    window.mktr.base = ' . $base . '
    window.mktr.base = window.mktr.base.substr(window.mktr.base.length - 1) === "/" ? window.mktr.base : window.mktr.base+"/";

    window.mktr.setEmail = true;
    window.mktr.saveOrder = true;
    window.mktr.selectors = "' . addslashes(self::c()->selectors) . '";
    window.mktr.apiScript = { set_email : "setEmail", save_order : "saveOrder" };

    window.mktr.sProductWishlist = window.mktr.sProductWishlist || [];
    window.mktr.eProductWishlist = window.mktr.eProductWishlist || [];

    window.mktr.eventsName = {
        "home_page":"__sm__view_homepage",
        "category":"__sm__view_category",
        "brand":"__sm__view_brand",
        "product":"__sm__view_product",
        "add_to_cart":"__sm__add_to_cart",
        "remove_from_cart":"__sm__remove_from_cart",
        "add_to_wish_list":"__sm__add_to_wishlist",
        "remove_from_wishlist":"__sm__remove_from_wishlist",
        "wishlist": "standBy",
        "checkout":"__sm__initiate_checkout",
        "supercheckout":"__sm__initiate_checkout",
        /* "default":"__sm__initiate_checkout", */
        "save_order":"__sm__order",
        "search":"__sm__search",
        "set_email":"__sm__set_email"
    };

    window.mktr.buildEvent = function (name = null, data = {}) {
        if (data === null) { data = {}; }
        if (name !== null && window.mktr.eventsName.hasOwnProperty(name)) { data.event = window.mktr.eventsName[name]; }
        ' . (_PS_MODE_DEV_ ? 'if (!window.mktr.eventsName.hasOwnProperty(name)){ data.event = name; data.type = "notListed"; }' : '') . '

        if (typeof dataLayer !== "undefined" && data.event === "standBy") {
            window.mktr.eProductWishlist = window.mktr.eProductWishlist.filter(val => {
                if (val.product_id == data.product_id || val.product_id == data.variation.id) {
                    data.event = val.event; dataLayer.push(data); return false;
                }
                return true;
            });
            if (data.event === "standBy") {
                window.mktr.sProductWishlist.push(data);
            }
        } else if(typeof dataLayer != "undefined" && data.event != "undefined" && window.mktr.ready) {
            dataLayer.push(data);' . (_PS_MODE_DEV_ ? ' window.mktr.debug();' : '') . '
            /*if (window.mktr.apiScript.hasOwnProperty(name) && window.mktr[window.mktr.apiScript[name]]) {
                window.mktr[window.mktr.apiScript[name]] = false; window.mktr.loadScript(window.mktr.apiScript[name]);
            }*/
        } else {
            window.mktr.pending.push(data); setTimeout(window.mktr.retry, 2000);
        }
    }

    window.mktr.wishList = function (event, product_id) {
        window.mktr.sProductWishlist = window.mktr.sProductWishlist.filter(val => {
            if (event !== "found" && (val.product_id == product_id || val.variation.id == product_id)) {
                val.event = event; dataLayer.push(val); event = "found"; return false;
            }
            return true;
        });
        if (event !== "found") {
            window.mktr.eProductWishlist.push({ event, product_id });
        }
    };

    window.mktr.retry = function () {
        if (typeof dataLayer != "undefined" && window.mktr.ready) {
            for (let data of window.mktr.pending) { if (data.event != "undefined") { dataLayer.push(data);' . (_PS_MODE_DEV_ ? ' window.mktr.debug();' : '') . ' } }        
        } else if (window.mktr.retryCount < 6) {
            window.mktr.retryCount++; setTimeout(window.mktr.retry, 2000);
        }
    };

    window.mktr.loadEvents = function () { let time = (new Date()).getTime(); window.mktr.loading = true;
        /*
        jQuery.get(window.mktr.base + "' . ($rewrite ? 'mktr/Api/GetEvents?' : '?fc=module&module=mktr&controller=Api&pg=GetEvents&') . 'mktr_time="+time, {}, function( data ) {
            for (let i of data) { window.mktr.buildEvent(i[0],i[1]); }
        });
        */
        jQuery.get(window.mktr.base + "?fc=module&module=mktr&controller=Api&pg=GetEvents&mktr_time="+time, {}, function( data ) {
            if (Array.isArray(data)) { for (let i of data) { window.mktr.buildEvent(i[0],i[1]); } }
        });
    };

    window.mktr.loadData = function (event, id) { let time = (new Date()).getTime(); window.mktr.loading = true;
        jQuery.get(window.mktr.base + "?fc=module&module=mktr&controller=Api&pg=loadData&event="+event+"&id="+id+"&mktr_time="+time, {}, function( data ) {
            if (Array.isArray(data)) { for (let i of data) { window.mktr.buildEvent(i[0],i[1]); } }
        });
    };

    window.mktr.loadScript = function (scriptName = null) {
        if (scriptName !== null) {
            (function(d, s, i) { var f = d.getElementsByTagName(s)[0], j = d.createElement(s);j.async = true;
            /* j.src = window.mktr.base + "' . ($rewrite ? 'mktr/Api/"+i+"?' : '?fc=module&module=mktr&controller=Api&pg="+i+"&') . 'mktr_time="+(new Date()).getTime(); */
            j.src = window.mktr.base + "?fc=module&module=mktr&controller=Api&pg="+i+"&mktr_time="+(new Date()).getTime();
            f.parentNode.insertBefore(j, f); })(document, "script", scriptName);
        }
    };

    window.mktr.LoadMktr = window.mktr.retry;
    window.mktr.toCheck = function (data = null, d = null) {
        if (data != null && window.mktr.loading) {
            if (typeof data === "string") {
                ' . (_PS_MODE_DEV_ ? ' console.log("mktr_data", data, d);' : '') . '
                if (data.search("cart") != -1 || data.search("cos") != -1 || data.search("wishlist") != -1 || data.search("addFavoriteProduct") != -1 || data.search("removeFavoriteProduct") != -1 &&
                    data.search("getAllWishlist") == -1 || d !== null &&
                    typeof d == "string" && (d.search("cart") != -1 || d.search("addFavoriteProduct") != -1 || d.search("removeFavoriteProduct") != -1 )) {
                    window.mktr.loading = false;
                    setTimeout(window.mktr.loadEvents, 2000);
                } else if(data.search("subscription") != -1) {
                    window.mktr.loading = false;
                    setTimeout(function () {
                        window.mktr.loading = true; let time = (new Date()).getTime(); let add = document.createElement("script"); add.async = true;
                        /* add.src = window.mktr.base + "' . ($rewrite ? 'mktr/Api/setEmail?' : '?fc=module&module=mktr&controller=Api&pg=setEmail&') . 'mktr_time="+time; */
                        add.src = window.mktr.base + "?fc=module&module=mktr&controller=Api&pg=setEmail&mktr_time="+time;
                        let s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(add,s);
                    }, 2000);
                }
            }
        }
    };

    if (typeof prestashop === "object" && typeof prestashop.on === "function") {
        prestashop.on("updateCart", function (event) {
            if(window.mktr.loading && typeof event === "object" && typeof event.reason === "object" && event.reason.hasOwnProperty("linkAction")) {
                if (event.reason.linkAction === "add-to-cart" || event.reason.linkAction === "delete-from-cart") {
                    window.mktr.loading = false;
                    setTimeout(window.mktr.loadEvents, 2000);
                }
            }
        });
    }

    document.addEventListener("click", function(event){ if (window.mktr.selectors.length !== 0 && (event.target.matches(window.mktr.selectors) || event.target.closest(window.mktr.selectors))) { setTimeout(window.mktr.loadEvents, 2000); } });

    if (typeof window.mktr.setStatus == "undefined") {
        window.mktr.setStatus = {
            Ajax: false,
            Fetch: false
        };
    }

    window.mktr.setAjax = function () {
        if (window.mktr.setStatus.Ajax || typeof window.$ !== "function" || typeof $.ajax !== "function") {
            if (!window.mktr.setStatus.Ajax) { setTimeout(window.mktr.setAjax, 1000); }
            return;
        }

        window.mktr.ajax = $.ajax;
        window.mktr.setStatus.Ajax = true;
        $.ajax = function (...args) {
            const config = (typeof args[0] === "object") ? args[0] : {};
            if (window.mktr.toCheck && typeof window.mktr.toCheck === "function") {
                window.mktr.toCheck(config.url, config.data);
            }
            return window.mktr.ajax.apply(this, args);
        };
    };
    
    window.mktr.setFetch = function () {
        if (window.mktr.setStatus.Fetch || typeof window.fetch !== "function") {
            if (!window.mktr.setStatus.Fetch) { setTimeout(window.mktr.setFetch, 1000); }
            return;
        }
        window.mktr.originalFetch = window.fetch.bind(window);
        window.mktr.setStatus.Fetch = true;
        window.fetch = function (...args) {
            if (typeof args[0] === "string") {
                let url = args[0]; let currentProtocol = window.location.protocol;
                if (currentProtocol === "https:" && url.startsWith("http:")) {
                    args[0] = url.replace(/^http:/, "https:");
                } else if (currentProtocol === "http:" && url.startsWith("https:")) {
                    args[0] = url.replace(/^https:/, "http:");
                }
            }
            if (window.mktr.toCheck && typeof window.mktr.toCheck === "function") { window.mktr.toCheck(args[0]); }
            return window.mktr.originalFetch(...args);
        };
    };
    ' . $js_status . '
}
';

            if (self::c()->js_file !== '' && file_exists(MKTR_APP . 'mktr.' . self::c()->js_file . '.js')) {
                unlink(MKTR_APP . 'mktr.' . self::c()->js_file . '.js');
            }
            self::c()->js_file = time();
            self::write('mktr.' . self::c()->js_file . '.js', $c);
        } else {
            if (self::c()->js_file !== '' && file_exists(MKTR_APP . 'mktr.' . self::c()->js_file . '.js')) {
                unlink(MKTR_APP . 'mktr.' . self::c()->js_file . '.js');
            }
            self::c()->js_file = '';
        }
        self::c()->save();
    }

    public static function updatePushStatus()
    {
        if (self::c()->push_status === true) {
            self::write('firebase-config.js', self::FIREBASE_CONFIG, true);
            self::write('firebase-messaging-sw.js', self::FIREBASE_MESSAGING_SW, true);
        } else {
            if (file_exists(MKTR_ROOT . '/firebase-config.js')) {
                unlink(MKTR_ROOT . '/firebase-config.js');
            }
            if (file_exists(MKTR_ROOT . '/firebase-messaging-sw.js')) {
                unlink(MKTR_ROOT . '/firebase-messaging-sw.js');
            }
        }
    }

    private static function write($f, $c, $root = false)
    {
        $file = fopen(($root ? MKTR_ROOT : MKTR_APP) . $f, 'w+');
        fwrite($file, '/**
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

' . $c);
        fclose($file);
    }
}
