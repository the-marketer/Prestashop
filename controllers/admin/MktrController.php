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

if (_PS_VERSION_ < 1.7) {
    if (!defined('MKTR_ROOT')) {
        define('MKTR_ROOT', rtrim(_PS_ROOT_DIR_, '/') . '/');
    }

    if (!defined('MKTR_APP')) {
        define('MKTR_APP', rtrim(MKTR_ROOT . 'modules/mktr/', '/') . '/');
    }

    if (!class_exists('Mktr')) {
        require_once MKTR_APP . 'mktr.php';
    }
}

class MktrController extends \AdminController
{
    const Docs = 'https://themarketer.com/resources/api';
    const LogIn = 'https://app.themarketer.com/login';
    const Register = 'https://app.themarketer.com/register';

    private static $page = 'tracker';
    private static $i;
    private static $t;
    private static $config;
    private static $jsRefresh = true;
    private static $baseIndex = null;

    private static $err = [
        'log' => [],
        'msg' => [
            'rest_key' => 'No REST API Key provided.',
            'tracking_key' => 'No Tracking API Key provided.',
            'customer_id' => 'No Customer ID provided.',
        ],
    ];

    public function __construct()
    {
        $this->multishop_context = \Shop::CONTEXT_SHOP | \Shop::CONTEXT_GROUP | \Shop::CONTEXT_ALL;
        $this->multishop_context_group = true;

        parent::__construct();
        self::$i = $this;

        if (!\Mktr::$init) {
            \Module::getInstanceByName('Mktr');
            // new \Mktr();
        }
    }

    public static function i()
    {
        return self::$i;
    }

    public static function FormData()
    {
        return [
            'tracker' => [
                'status' => ['type' => 'switch', 'label' => 'Status'],
                'tracking_key' => ['type' => 'text', 'label' => 'Tracking API Key *'],
                'rest_key' => ['type' => 'text', 'label' => 'REST API Key *'],
                'customer_id' => ['type' => 'text', 'label' => 'Customer ID *'],
                'cron_feed' => ['type' => 'switch', 'label' => 'Activate Cron Feed', 'desc' => '<b>If Enable, Please Add this to your server Cron Jobs</b><br /><code>0 * * * * curl -s https://yourshop.com/index.php?fc=module&module=mktr&controller=cron > /dev/null 2>&1</code>'],
                'update_feed' => ['type' => 'text', 'label' => 'Cron Update feed every (hours)'],
                'cron_review' => ['type' => 'switch', 'label' => 'Activate Cron Review'],
                'update_review' => ['type' => 'text', 'label' => 'Cron Update Review every (hours)'],
                'opt_in' => [
                    'type' => 'select',
                    'label' => 'Double opt-in setting',
                    'multiple' => false,
                    'options' => [
                        'query' => [
                            ['value' => 0, 'label' => 'WebSite'],
                            ['value' => 1, 'label' => 'The Marketer'],
                        ],
                        'id' => 'value',
                        'name' => 'label',
                    ],
                ],
                'push_status' => ['type' => 'switch', 'label' => 'Push Notification'],
                'default_stock' => [
                    'type' => 'select',
                    'label' => 'Default Stock if negative Stock Value',
                    'multiple' => false,
                    'options' => [
                        'query' => [
                            ['value' => 0, 'label' => 'Out of Stock'],
                            ['value' => 1, 'label' => 'In Stock'],
                            ['value' => 2, 'label' => 'In supplier stock'],
                        ],
                        'id' => 'value',
                        'name' => 'label',
                    ],
                ],
                'allow_export' => ['type' => 'switch', 'label' => 'Allow orders export'],
                'selectors' => ['type' => 'text', 'label' => 'Trigger Selectors'],
                'brand' => ['type' => 'text', 'label' => 'Brand Attribute'],
                'color' => ['type' => 'text', 'label' => 'Color Attribute'],
                'size' => ['type' => 'text', 'label' => 'Size Attribute'],
            ],
            'google' => [
                'google_status' => ['type' => 'switch', 'label' => 'Status'],
                'google_tagCode' => ['type' => 'text', 'label' => 'Tag CODE *'],
            ],
        ];
    }

    public function getToolbarBtn()
    {
        $p = (self::$page === 'tracker' ? 'google' : 'tracker');

        $this->page_header_toolbar_btn['settings'] = [
            'href' => $this->getBaseIndex() . '&page=' . $p . '&' . $this->token(),
            'desc' => ucfirst($p) . ' Settings',
            'icon' => 'process-icon-cogs',
        ];

        $this->page_header_toolbar_btn['docs'] = [
            'href' => self::Docs,
            'desc' => 'Docs',
            'target' => true,
            'icon' => 'process-icon-help',
        ];

        if (_PS_VERSION_ < 1.8) {
            $update = $this->context->link->getAdminLink('AdminModules', true) . '&checkAndUpdate=1&module_name=mktr';
        } else {
            $update = $this->get('router')->generate('admin_module_updates');
        }

        $this->page_header_toolbar_btn['update'] = [
            'href' => $update,
            'desc' => 'Check update',
            'icon' => 'process-icon-mktr-up',
        ];

        $this->page_header_toolbar_btn['login'] = [
            'href' => self::LogIn,
            'desc' => 'Login',
            'target' => true,
            'icon' => 'process-icon-mktr-user',
        ];

        if (self::$config->tracking_key === '') {
            $this->page_header_toolbar_btn['register'] = [
                'href' => self::Register,
                'desc' => 'Register',
                'target' => true,
                'icon' => 'process-icon-new',
            ];
        }

        return $this->page_header_toolbar_btn;
    }

    protected function getConfigForm()
    {
        $fields = [];
        $new = [];
        $n = null;
        $form = self::FormData();
        foreach ($form[self::$page] as $key => $value) {
            $n = [
                'name' => $key,
                'type' => $value['type'],
                'label' => '<b>' . $value['label'] . '</b>',
            ];
            if (_PS_VERSION_ >= 1.6) {
                if ($value['type'] === 'switch') {
                    $n['is_bool'] = true;
                    $value['values'] = array_key_exists('values', $value) ? $value['values'] : \Mktr\Model\Config::DEFAULT_VALUES;
                }
            } else {
                if ($value['type'] === 'switch') {
                    $n['type'] = 'radio';
                    $n['class'] = 't';
                    $n['is_bool'] = true;

                    $value['values'] = array_key_exists('values', $value) ? $value['values'] : \Mktr\Model\Config::DEFAULT_VALUES;

                    foreach ($value['values'] as $kkk => $vvv) {
                        if (isset($vvv['value'])) {
                            $value['values'][$kkk]['value'] = (int) $value['values'][$kkk]['value'];
                        }
                        if (isset($vvv['id'])) {
                            $value['values'][$kkk]['id'] = $value['values'][$kkk]['id'] . '_' . $key;
                        }
                    }
                }
            }

            if (array_key_exists('options', $value)) {
                $n['options'] = $value['options'];
            }

            if (array_key_exists('values', $value)) {
                $n['values'] = $value['values'];
                foreach ($value['values'] as $key1 => $value1) {
                    $n['values'][$key1]['label'] = $this->trans($value1['label']);
                }
            }

            if (array_key_exists('desc', $value)) {
                $n['desc'] = '<b>' . $value['desc'] . '</b>';
            }

            if (array_key_exists('multiple', $value)) {
                $n['multiple'] = $value['multiple'];
            }

            if (_PS_VERSION_ >= 1.6) {
                $n['value'] = '';
            }

            $new[] = $n;
        }

        $fields[]['form'] = [
            'legend' => [
                'title' => self::$page === 'google' ? 'Google Tag Settings' : 'Main Settings',
                'icon' => 'icon-cogs',
            ],
            'input' => $new,
            'submit' => [
                'title' => $this->trans('Save'),
            ],
        ];

        return $fields;
    }

    protected function getConfigFormValues()
    {
        $list = [];
        $form = self::FormData();
        foreach ($form[self::$page] as $key => $value) {
            if (_PS_VERSION_ >= 1.6) {
                $list[$key] = self::$config->asString($key);
            } else {
                if ($value['type'] == 'switch') {
                    $list[$key] = (int) self::$config->asString($key);
                } else {
                    $list[$key] = self::$config->asString($key);
                }
            }
        }

        return $list;
    }

    private function post()
    {
        $proccess = [];

        $form = self::FormData();
        foreach ($form[self::$page] as $key => $value) {
            if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $key)) {
                continue;
            }

            $vv = \Tools::getValue($key);

            if (in_array($key, ['rest_key', 'tracking_key', 'customer_id']) && empty($vv)) {
                self::$err['log'][] = self::$err['msg'][$key];
            }

            if (self::$config->{$key} != $vv) {
                self::$config->update($key, \Tools::getValue($key));
                $proccess[] = $key;
            }
        }

        if (self::$config->tracking_key === '') {
            self::$config->status = false;
            $proccess[] = 'status';
        }

        foreach ($proccess as $key) {
            if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $key)) {
                continue;
            }

            switch ($key) {
                case 'opt_in':
                    $this->updateOptIn();
                    break;
                case 'push_status':
                    \Mktr\Route\refreshJS::updatePushStatus();
                    break;
            }
        }

        \Mktr\Route\refreshJS::resetConfig();
        \Mktr\Route\refreshJS::loadJs();

        self::$config->save();
    }

    private function updateOptIn()
    {
        $data = \Mktr\Model\Config::nws();

        if (self::$config->opt_in == 0) {
            /* @phpstan-ignore-next-line */
            \Mktr\Model\Config::setConfig($data['CONFIRMATION'], true);
            /* @phpstan-ignore-next-line */
            \Mktr\Model\Config::setConfig($data['NOTIFICATION'], true);
        } else {
            /* @phpstan-ignore-next-line */
            \Mktr\Model\Config::setConfig($data['CONFIRMATION'], false);
            /* @phpstan-ignore-next-line */
            \Mktr\Model\Config::setConfig($data['NOTIFICATION'], false);
        }
    }

    private function outPut()
    {
        $helper = new \HelperForm();
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->default_form_language = \Mktr\Model\Config::getLang();
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMktrModule';
        $helper->token = $this->token;
        $helper->currentIndex = $this->getBaseIndex() . '&page=' . self::$page;
        $values = $this->getConfigFormValues();
        $values['dni'] = 0;
        $values['first_call'] = false;
        $helper->first_call = false;
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => \Mktr\Model\Config::getLang(),
        ];

        $out = '';

        if (self::$config->tracking_key === '') {
            $out = '<div class="panel">
    <h2><i class="icon icon-info"></i> TheMarketer info</h2>
    To use this module, you must first
    <a href="' . self::Register . '" target="_blank"><strong>Register</strong></a>
    with us and receive unique API credentials.<br />
    After you receive your credentials, input them here.
</div>';
        }

        if (!empty(self::$err['log'])) {
            $out .= \Mktr::i()->displayError(implode('<br />', self::$err['log']));
        }

        $out .= $this->orderSyncStatus();
        $out .= $this->cronWarning();

        $js_status = \Tools::getValue('js_status', null);

        if ($js_status !== null) {
            self::$config->update('js_status', $js_status);
            self::$config->save();
        }

        return $out . $helper->generateForm($this->getConfigForm());
    }

    /**
     * What is still owed to TheMarketer. Silent when everything is delivered.
     *
     * @return string
     */
    private function orderSyncStatus()
    {
        if (self::$page !== 'tracker' || !\Mktr\Model\Config::rest()) {
            return '';
        }

        if (\Tools::isSubmit('mktrRetryStuck')) {
            $reopened = \Mktr\Model\OrderSync::retryStuck();

            return \Mktr::i()->displayConfirmation(
                $reopened . ' order(s) queued for another attempt.'
            );
        }

        $counts = \Mktr\Model\OrderSync::counts();

        if ($counts['pending'] === 0 && $counts['stuck'] === 0) {
            return '';
        }

        $out = '';

        if ($counts['pending'] > 0) {
            $out .= \Mktr::i()->displayWarning(
                $counts['pending'] . ' order(s) waiting to be sent to TheMarketer. ' .
                'They are delivered by the cron, or automatically as the shop receives traffic.'
            );
        }

        if ($counts['stuck'] > 0) {
            $out .= \Mktr::i()->displayError(
                $counts['stuck'] . ' order(s) failed ' . \Mktr\Model\OrderSync::MAX_ATTEMPTS .
                ' times and are no longer retried. Check the last_error column in ' .
                '<code>' . _DB_PREFIX_ . 'mktr_order_sync</code>, then ' .
                '<a href="' . $this->getBaseIndex() . '&page=tracker&mktrRetryStuck=1&' .
                $this->token() . '">retry them</a>.'
            );
        }

        return $out;
    }

    /**
     * The cron endpoint is what recovers orders the API refused or timed out
     * on. It only runs if the merchant added it to their crontab, so say so
     * when it clearly has not run.
     *
     * @return string
     */
    private function cronWarning()
    {
        if (self::$page !== 'tracker' || !\Mktr\Model\Config::rest()) {
            return '';
        }

        $data = \Mktr\Helper\Data::init();
        $lastRun = (int) $data->last_cron_run;
        $feedNext = (int) $data->update_feed;

        if ($lastRun === 0) {
            // Never seen a run since this version - fall back to the feed
            // timestamp, which a working cron keeps in the future.
            $stale = $feedNext <= time();
        } else {
            $stale = $lastRun < (time() - 86400);
        }

        if (!$stale) {
            return '';
        }

        $url = \Tools::getShopDomainSsl(true) . __PS_BASE_URI__ .
            'index.php?fc=module&module=mktr&controller=cron';

        return \Mktr::i()->displayWarning(
            'TheMarketer cron has not run in the last 24 hours. Orders that fail to reach ' .
            'the API are recovered by it, so please add this to your server cron jobs:<br />' .
            '<code>0 * * * * curl -s "' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" > /dev/null 2>&1</code>'
        );
    }

    public function token()
    {
        if (self::$t === null) {
            self::$t = 'token=' . $this->token;
        }

        return self::$t;
    }

    private function getBaseIndex()
    {
        if (self::$baseIndex === null) {
            if (version_compare(_PS_VERSION_, '9.0.0', '>=')) {
                $link = $this->context->link->getAdminLink('Mktr', false);
                if (strpos($link, 'http') !== 0 && strpos($link, '/') !== 0) {
                    $link = __PS_BASE_URI__ . basename(_PS_ADMIN_DIR_) . '/' . $link;
                }
                self::$baseIndex = $link;
            } else {
                self::$baseIndex = self::$currentIndex;
            }
        }

        return self::$baseIndex;
    }

    public function initContent()
    {
        \Mktr\Model\Config::reset();
        /* @phpstan-ignore-next-line */
        self::$config = \Mktr\Model\Config::setLang($this->context->language->id);

        self::$page = \Mktr\Helper\Valid::getParam('page', self::$page);

        if (((bool) \Tools::isSubmit('submitMktrModule')) == true) {
            $this->post();
        }

        if (!in_array(self::$page, ['google', 'tracker'])) {
            self::$page = 'tracker';
        }
        /* @phpstan-ignore-next-line */
        $this->title = 'TheMarketer - ' . ucfirst(self::$page);
        $this->toolbar_btn = $this->getToolbarBtn();
        $this->show_page_header_toolbar = true;

        $multiStoreHeader = '';
        if (\Shop::isFeatureActive()) {
            $shopContext = \Shop::getContext();
            if ($shopContext === \Shop::CONTEXT_ALL) {
                $multiStoreHeader = '<div class="alert alert-info"><i class="icon icon-info-circle"></i> ' .
                    'You are editing settings for <strong>All shops</strong>. Changes will apply to all shops that don\'t have specific values set.' .
                    '</div>';
            } elseif ($shopContext === \Shop::CONTEXT_GROUP) {
                $groupName = '';
                if (method_exists($this->context->shop, 'getGroup')) {
                    $group = $this->context->shop->getGroup();
                    $groupName = is_object($group) ? $group->name : '';
                }
                $multiStoreHeader = '<div class="alert alert-info"><i class="icon icon-info-circle"></i> ' .
                    'You are editing settings for shop group: <strong>' . $groupName . '</strong>.' .
                    '</div>';
            } else {
                $shopName = isset($this->context->shop->name) ? $this->context->shop->name : 'Current shop';
                $multiStoreHeader = '<div class="alert alert-info"><i class="icon icon-info-circle"></i> ' .
                    'You are editing settings for shop: <strong>' . $shopName . '</strong>.' .
                    '</div>';
            }
        }

        $this->context->smarty->assign(
            [
                'toolbar_scroll' => true,
                'show_toolbar' => true,
                'bootstrap' => true,
                'show_page_header_toolbar' => true,
                'help_link' => null,
                'breadcrumbs2' => [
                    'container' => [
                        'name' => 'Modules',
                        'href' => $this->context->link->getAdminLink('AdminModules', true),
                        'icon' => '',
                        'id_parent' => 0,
                    ],
                    'tab' => [
                        'name' => 'TheMarketer',
                        'href' => $this->getBaseIndex() . '&' . $this->token(),
                        'icon' => '',
                        'id_parent' => 0,
                    ],
                    'action' => [
                        'name' => '',
                        'href' => '',
                        'icon' => '',
                        'id_parent' => 0,
                    ],
                ],
                'content' => $multiStoreHeader . $this->outPut(),
                'title' => $this->title,
                'toolbar_btn' => $this->toolbar_btn,
                'page_header_toolbar_btn' => $this->toolbar_btn,
            ]
        );
    }
}
