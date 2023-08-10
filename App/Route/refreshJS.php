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

namespace Mktr\Route;

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

    private static $config = null;

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

window.mktr.debug = function () { if (typeof dataLayer != "undefined") { for (let i of dataLayer) { console.log("Mktr", "Google", i); } } };
window.mktr.ready = false;
window.mktr.pending = [];
window.mktr.retryCount = 0;
window.mktr.loading = true;

';

            if (\Mktr\Model\Config::showGoogle()) {
                $c = $c . "(function(w,d,s,l,i){
w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','" . self::c()->google_tagCode . "');

";
            }

            $rewrite = (bool) \Mktr\Model\Config::getConfig('PS_REWRITING_SETTINGS');
            $c = $c . '(function(d, s, i) {
var f = d.getElementsByTagName(s)[0], j = d.createElement(s);j.async = true;
j.src = "https://t.themarketer.com/t/j/" + i; f.parentNode.insertBefore(j, f);
window.mktr.ready = true;
})(document, "script", "' . self::c()->tracking_key . '");

window.mktr.eventsName = {
    "home_page":"__sm__view_homepage",
    "category":"__sm__view_category",
    "brand":"__sm__view_brand",
    "product":"__sm__view_product",
    "add_to_cart":"__sm__add_to_cart",
    "remove_from_cart":"__sm__remove_from_cart",
    "add_to_wish_list":"__sm__add_to_wishlist",
    "remove_from_wishlist":"__sm__remove_from_wishlist",
    "checkout":"__sm__initiate_checkout",
    "save_order":"__sm__order",
    "search":"__sm__search",
    "set_email":"__sm__set_email",
    "set_phone":"__sm__set_phone"
};

window.mktr.buildEvent = function (name = null, data = {}) {
    if (data === null) { data = {}; }
    if (name !== null && window.mktr.eventsName.hasOwnProperty(name)) { data.event = window.mktr.eventsName[name]; }
    ' . (_PS_MODE_DEV_ ? 'if (!window.mktr.eventsName.hasOwnProperty(name)){ data.event = name; data.type = "notListed"; }' : '') . '
    if (typeof dataLayer != "undefined" && data.event != "undefined" && window.mktr.ready) {
        dataLayer.push(data);' . (_PS_MODE_DEV_ ? ' window.mktr.debug();' : '') . '
    } else {
        window.mktr.pending.push(data); setTimeout(window.mktr.retry, 1000);
    }
}

window.mktr.retry = function () {
    if (typeof dataLayer != "undefined" && window.mktr.ready) {
        for (let data of window.mktr.pending) { if (data.event != "undefined") { dataLayer.push(data);' . (_PS_MODE_DEV_ ? ' window.mktr.debug();' : '') . ' } }        
    } else if (window.mktr.retryCount < 6) {
        window.mktr.retryCount++; setTimeout(window.mktr.retry, 1000);
    }
};
window.mktr.loadEvents = function () { let time = (new Date()).getTime(); window.mktr.loading = true;
    jQuery.get(window.mktr.base + "' . ($rewrite ? 'mktr/api/GetEvents?' : '?fc=module&module=mktr&controller=Api&pg=GetEvents&') . 'mktr_time="+time, {}, function( data ) {
        for (let i of data) { window.mktr.buildEvent(i[0],i[1]); }
    });
};

window.mktr.ajax = $.ajax;
window.mktr.fetch = fetch;

window.mktr.toCheck = function (data, d = null) {
    if (data != null && window.mktr.loading) {
        ' . (_PS_MODE_DEV_ ? ' console.log("mktr_data", data, d);' : '') . '
        if (data.search("cart") != -1 || data.search("cos") != -1 || data.search("wishlist") != -1 &&
            data.search("getAllWishlist") == -1 || d !== null && typeof d == "string" && d.search("cart") != -1) {
            window.mktr.loading = false;
            setTimeout(window.mktr.loadEvents, 1000);
        } else if(data.search("subscription") != -1) {
            window.mktr.loading = false;
            setTimeout(function () {
                window.mktr.loading = true;
                let time = (new Date()).getTime();
                let add = document.createElement("script"); add.async = true;
                add.src = window.mktr.base + "' . ($rewrite ? 'mktr/api/setEmail?' : '?fc=module&module=mktr&controller=Api&pg=setEmail&') . 'mktr_time="+time;
                let s = document.getElementsByTagName("script")[0];
                s.parentNode.insertBefore(add,s);
            }, 1000);
        }
    }
};

if (typeof prestashop === "object") {
    prestashop.on("updateCart", function (event) {
        if(window.mktr.loading && typeof event === "object" && typeof event.reason === "object" && event.reason.hasOwnProperty("linkAction")) {
            if (event.reason.linkAction === "add-to-cart" || event.reason.linkAction === "delete-from-cart") {
                window.mktr.loading = false;
                setTimeout(window.mktr.loadEvents, 1000);
            }
        }
    });
}

$.ajax = function (data) {
    let ret = window.mktr.ajax.apply(this, arguments);
    window.mktr.toCheck(arguments[0].url, arguments[0].data); return ret; };

fetch = function (data) {
    let ret = window.mktr.fetch.apply(this, arguments);
    window.mktr.toCheck(arguments[0]); return ret; };
';

            self::write('views/js/mktr.js', $c);
        } else {
            self::write('views/js/mktr.js', '');
        }
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
