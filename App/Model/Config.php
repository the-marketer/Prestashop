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

namespace Mktr\Model;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Config
{
    const CONFIG_DATA = [
        'status' => ['key' => 'MKTR_TRACKER_TRACKER_STATUS', 'default' => false, 'type' => 'bool'],
        'tracking_key' => ['key' => 'MKTR_TRACKER_TRACKER_TRACKING_KEY', 'default' => '', 'type' => 'string'],
        'rest_key' => ['key' => 'MKTR_TRACKER_TRACKER_REST_KEY', 'default' => '', 'type' => 'string'],
        'customer_id' => ['key' => 'MKTR_TRACKER_TRACKER_CUSTOMER_ID', 'default' => '', 'type' => 'string'],
        'js_file' => ['key' => 'MKTR_TRACKER_TRACKER_JS_FILe', 'default' => '', 'type' => 'string'],
        'cron_feed' => ['key' => 'MKTR_TRACKER_TRACKER_CRON_FEED', 'default' => true, 'type' => 'bool'],
        'update_feed' => ['key' => 'MKTR_TRACKER_TRACKER_UPDATE_FEED', 'default' => 4, 'type' => 'int'],
        'cron_review' => ['key' => 'MKTR_TRACKER_TRACKER_CRON_REVIEW', 'default' => false, 'type' => 'bool'],
        'update_review' => ['key' => 'MKTR_TRACKER_TRACKER_UPDATE_REVIEW', 'default' => 4, 'type' => 'int'],
        'opt_in' => ['key' => 'MKTR_TRACKER_TRACKER_OPT_IN', 'default' => 0, 'type' => 'int'],
        'push_status' => ['key' => 'MKTR_TRACKER_TRACKER_PUSH_STATUS', 'default' => false, 'type' => 'bool'],
        'default_stock' => ['key' => 'MKTR_TRACKER_TRACKER_DEFAULT_STOCK', 'default' => 0, 'type' => 'int'],
        'allow_export' => ['key' => 'MKTR_TRACKER_TRACKER_ALLOW_EXPORT', 'default' => true, 'type' => 'bool'],
        'selectors' => ['key' => 'MKTR_TRACKER_TRACKER_SELECTORS', 'default' => '', 'type' => 'string'],
        'brand' => ['key' => 'MKTR_TRACKER_ATTRIBUTE_BRAND', 'default' => ['brand'], 'type' => 'array'],
        'color' => ['key' => 'MKTR_TRACKER_ATTRIBUTE_COLOR', 'default' => ['color'], 'type' => 'array'],
        'size' => ['key' => 'MKTR_TRACKER_ATTRIBUTE_SIZE', 'default' => ['size'], 'type' => 'array'],
        'google_status' => ['key' => 'MKTR_GOOGLE_GOOGLE_STATUS', 'default' => false, 'type' => 'bool'],
        'google_tagCode' => ['key' => 'MKTR_GOOGLE_GOOGLE_TAGCODE', 'default' => '', 'type' => 'string'],
    ];

    const CONFIG_DATA_PS15 = [
        'status' => ['key' => 'MKTR_TRACKER_STATUS', 'default' => 0, 'type' => 'int'],
        'tracking_key' => ['key' => 'MKTR_TRACKER_TRACKING_KEY', 'default' => '', 'type' => 'string'],
        'rest_key' => ['key' => 'MKTR_TRACKER_REST_KEY', 'default' => '', 'type' => 'string'],
        'customer_id' => ['key' => 'MKTR_TRACKER_CUSTOMER_ID', 'default' => '', 'type' => 'string'],
        'js_file' => ['key' => 'MKTR_TRACKER_JS_FILe', 'default' => '', 'type' => 'string'],
        'cron_feed' => ['key' => 'MKTR_TRACKER_CRON_FEED', 'default' => 1, 'type' => 'int'],
        'update_feed' => ['key' => 'MKTR_TRACKER_UPDATE_FEED', 'default' => 4, 'type' => 'int'],
        'cron_review' => ['key' => 'MKTR_TRACKER_CRON_REVIEW', 'default' => 0, 'type' => 'int'],
        'update_review' => ['key' => 'MKTR_TRACKER_UPDATE_REVIEW', 'default' => 4, 'type' => 'int'],
        'opt_in' => ['key' => 'MKTR_TRACKER_OPT_IN', 'default' => 0, 'type' => 'int'],
        'push_status' => ['key' => 'MKTR_TRACKER_PUSH_STATUS', 'default' => 0, 'type' => 'int'],
        'default_stock' => ['key' => 'MKTR_TRACKER_DEFAULT_STOCK', 'default' => 0, 'type' => 'int'],
        'allow_export' => ['key' => 'MKTR_TRACKER_ALLOW_EXPORT', 'default' => 1, 'type' => 'int'],
        'selectors' => ['key' => 'MKTR_TRACKER_SELECTORS', 'default' => '', 'type' => 'string'],
        'brand' => ['key' => 'MKTR_TRACKER_ATTRIBUTE_BRAND', 'default' => ['brand'], 'type' => 'array'],
        'color' => ['key' => 'MKTR_TRACKER_ATTRIBUTE_COLOR', 'default' => ['color'], 'type' => 'array'],
        'size' => ['key' => 'MKTR_TRACKER_ATTRIBUTE_SIZE', 'default' => ['size'], 'type' => 'array'],
        'google_status' => ['key' => 'MKTR_GOOGLE_GOOGLE_STATUS', 'default' => 0, 'type' => 'int'],
        'google_tagCode' => ['key' => 'MKTR_GOOGLE_GOOGLE_TAGCODE', 'default' => '', 'type' => 'string'],
    ];

    const DEFAULT_VALUES = [
        [
            'id' => 'active_on',
            'value' => true,
            'label' => 'Enabled',
        ],
        [
            'id' => 'active_off',
            'value' => false,
            'label' => 'Disabled',
        ],
    ];

    protected $attributes = [
        'status' => null,
        'tracking_key' => null,
        'rest_key' => null,
        'customer_id' => null,
        'js_file' => null,
        'cron_feed' => null,
        'update_feed' => null,
        'cron_review' => null,
        'update_review' => null,
        'opt_in' => null,
        'push_status' => null,
        'default_stock' => null,
        'allow_export' => null,
        'selectors' => null,
        'brand' => null,
        'color' => null,
        'size' => null,
        'google_status' => null,
        'google_tagCode' => null,
    ];

    protected $load = [];
    protected $isDirty = false;
    public static $dateFormat = 'Y-m-d H:i';
    public static $dateFormatParam = 'Y-m-d';

    private static $i;
    private static $nws;
    private static $lang_id;
    private static $context;
    private static $shop;
    private static $db;
    private static $CFG_DATA;

    private static $checkData = [
        'showJs' => null,
        'showGoogle' => null,
        'rest' => null,
    ];

    private $hide = [];

    public static function nws()
    {
        if (self::$nws === null) {
            if (_PS_VERSION_ >= '1.7.2.0') {
                self::$nws = [
                    'CONFIRMATION' => 'CONTACTFORM_SEND_CONFIRMATION_EMAIL',
                    // \Contactform::SEND_CONFIRMATION_EMAIL,
                    'NOTIFICATION' => 'CONTACTFORM_SEND_NOTIFICATION_EMAIL',
                    // \Contactform::SEND_NOTIFICATION_EMAIL,
                ];
            } else {
                self::$nws = [
                    'CONFIRMATION' => 'NW_VERIFICATION_EMAIL',
                    'NOTIFICATION' => 'NW_CONFIRMATION_EMAIL',
                ];
            }
        }

        return self::$nws;
    }

    public static function i($new = false)
    {
        if (self::$i === null || $new === true) {
            self::CFG();
            self::$i = new static();
        }

        return self::$i;
    }

    public static function CFG()
    {
        if (!self::$CFG_DATA) {
            if (_PS_VERSION_ >= 1.6) {
                self::$CFG_DATA = self::CONFIG_DATA;
            } else {
                self::$CFG_DATA = self::CONFIG_DATA_PS15;
            }
        }

        return self::$CFG_DATA;
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

    private function toArray()
    {
        $list = [];
        self::CFG();
        foreach (self::$CFG_DATA as $key => $value) {
            if (!in_array($key, $this->hide)) {
                $value = $this->{$key};
                if (null !== self::$CFG_DATA[$key]['type'] && in_array(self::$CFG_DATA[$key]['type'], ['date', 'datetime'])) {
                    $list[$key] = $value->format(self::$dateFormat);
                } else {
                    $list[$key] = $value;
                }
            }
        }

        return $list;
    }

    public function __get($name)
    {
        self::CFG();
        if ($this->attributes[$name] === null) {
            $this->attributes[$name] = \Configuration::get(self::$CFG_DATA[$name]['key']);
            if (!in_array(self::$CFG_DATA[$name]['type'], ['bool', 'boolean']) && $this->attributes[$name] === false) {
                $this->attributes[$name] = self::$CFG_DATA[$name]['default'];
            } else {
                $this->attributes[$name] = $this->cast($name, $this->attributes[$name]);
            }
            $this->load[$name] = true;
        }

        return $this->attributes[$name];
    }

    public function __set($name, $value)
    {
        $this->isDirty = true;
        $this->attributes[$name] = $value;
        $this->load[$name] = true;
    }

    private function getConfig($name)
    {
        if (!array_key_exists($name, $this->attributes) || $this->attributes[$name] === null) {
            $this->attributes[$name] = \Configuration::get($name);
        }

        return $this->attributes[$name];
    }

    private function setConfig($name, $value)
    {
        \Configuration::updateValue($name, $value);
        $this->attributes[$name] = $value;

        return $this->attributes[$name];
    }

    public static function shop()
    {
        if (self::$shop === null) {
            self::$shop = self::getContext()->shop->id;
        }

        return self::$shop;
    }

    public static function getLang()
    {
        if (self::$lang_id === null) {
            self::$lang_id = self::getContext()->language->id;
        }

        return self::$lang_id;
    }

    public static function getContext()
    {
        if (self::$context === null) {
            self::$context = \Context::getContext();
        }

        return self::$context;
    }

    public static function db()
    {
        if (self::$db === null) {
            self::$db = \Db::getInstance(_PS_USE_SQL_SLAVE_);
        }

        return self::$db;
    }

    private function setContext($c)
    {
        self::$context = $c;

        return $this;
    }

    private function setLang($lang)
    {
        self::$lang_id = $lang;

        return $this;
    }

    public function asString($name)
    {
        $value = $this->{$name};

        self::CFG();
        if (self::$CFG_DATA[$name]['type'] === 'array' && $value !== null) {
            $value = implode('|', $value);
        }

        return $value;
    }

    public static function AddDefault()
    {
        $i = self::i();
        self::CFG();
        foreach (self::$CFG_DATA as $key => $v) {
            $i->{$key} = $v['default'];
        }

        $i->save();
    }

    public static function showJs($new = false)
    {
        if ($new === true || self::$checkData['showJs'] === null) {
            $i = self::i();
            self::$checkData['showJs'] = $i->status && $i->tracking_key !== '';
        }

        return self::$checkData['showJs'];
    }

    public static function rest($new = false)
    {
        if ($new === true || self::$checkData['rest'] === null) {
            $i = self::i();
            self::$checkData['rest'] = $i->status && $i->tracking_key !== '' && $i->rest_key !== '' && $i->customer_id !== '';
        }

        return self::$checkData['rest'];
    }

    public static function showGoogle($new = false)
    {
        if ($new === true || self::$checkData['showGoogle'] === null) {
            $i = self::i();
            self::$checkData['showGoogle'] = $i->google_status && $i->google_tagCode;
        }

        return self::$checkData['showGoogle'];
    }

    public static function delete($name = null)
    {
        self::CFG();
        if ($name === null) {
            foreach (self::$CFG_DATA as $key => $v) {
                \Configuration::deleteByName($v['key']);
            }

            \Configuration::deleteByName('MKTR_TRACKER_CONFIRMATION');
            \Configuration::deleteByName('MKTR_TRACKER_NOTIFICATION');
        } else {
            \Configuration::deleteByName(self::$CFG_DATA[$name]['key']);
        }
    }

    public function update($name, $value)
    {
        self::CFG();
        if (self::$CFG_DATA[$name]['type'] === 'array' && $value !== null) {
            if (in_array($name, ['brand', 'color', 'size'])) {
                $value = strtolower($value);
            }
            $value = explode('|', $value);
        } else {
            $value = $this->cast($name, $value);
        }

        $this->{$name} = $value;
    }

    public function save()
    {
        if ($this->isDirty) {
            $this->isDirty = false;
            foreach ($this->load as $key => $value) {
                $value1 = $this->attributes[$key];
                if ($value1 !== null) {
                    \Configuration::updateValue(self::$CFG_DATA[$key]['key'], $this->unCast($key, $value1), true);
                // if (in_array($key, ['brand', 'color', 'size'])) { var_dump($key, $value1,$this->unCast($key, $value1), \Configuration::updateValue(self::$CFG_DATA[$key]['key'], $this->unCast($key, $value1), true));die(); }
                // var_dump(self::$CFG_DATA[$key]['key'], $key, $value1); die();
                } else {
                    \Configuration::updateValue(self::$CFG_DATA[$key]['key'], null);
                }
            }
        }
    }

    protected function cast($key, $value)
    {
        switch (self::$CFG_DATA[$key]['type']) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
            case 'array':
                return call_user_func('unserialize', $value);
            case 'json':
                return json_decode($value, true);
            case 'date':
            case 'datetime':
                return new \DateTime($value);
            case 'timestamp':
                return $value;
            default:
                return $value;
        }
    }

    protected function unCast($key, $value)
    {
        switch (self::$CFG_DATA[$key]['type']) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (int) $value;
            case 'object':
            case 'array':
                return call_user_func('serialize', $value);
            case 'json':
                return json_encode($value, true);
            case 'date':
            case 'datetime':
                return $value->format('c');
            case 'timestamp':
                return $value;
            default:
                return $value;
        }
    }
}
