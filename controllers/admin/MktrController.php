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

class MktrController extends AdminController
{
    const Docs = 'https://themarketer.com/resources/api';
    const LogIn = 'https://app.themarketer.com/login';
    const Register = 'https://app.themarketer.com/register';

    private static $page = 'tracker';
    private static $i;
    private static $t;
    private static $config;
    private static $jsRefresh = true;

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
        parent::__construct();
        self::$i = $this;
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
                'cron_feed' => ['type' => 'switch', 'label' => 'Activate Cron Feed', 'desc' => implode('', ['<b>If Enable, Please Add this to your server Cron Jobs</b>', '<br /><code>0 * * * * /usr/bin/php ' . MKTR_APP . 'cron.php > /dev/null 2>&1</code>'])],
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
            'href' => self::$currentIndex . '&page=' . $p . '&' . $this->token(),
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

            if ($value['type'] === 'switch') {
                $n['is_bool'] = true;
                $value['values'] = array_key_exists('values', $value) ? $value['values'] : Mktr\Model\Config::DEFAULT_VALUES;
            }
            if (array_key_exists('options', $value)) {
                $n['options'] = $value['options'];
            }
            if (array_key_exists('values', $value)) {
                $n['values'] = $value['values'];
                foreach ($value['values'] as $key1 => $value1) {
                    $n['values'][$key1]['label'] = $this->l($value1['label']);
                }
            }

            if (array_key_exists('desc', $value)) {
                $n['desc'] = '<b>' . $value['desc'] . '</b>';
            }

            if (array_key_exists('multiple', $value)) {
                $n['multiple'] = $value['multiple'];
            }
            $n['value'] = '';

            $new[] = $n;
        }

        $fields[]['form'] = [
            'legend' => [
                'title' => self::$page === 'google' ? 'Google Tag Settings' : 'Main Settings',
                'icon' => 'icon-cogs',
            ],
            'input' => $new,
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];

        return $fields;
    }

    protected function getConfigFormValues()
    {
        $list = [];
        $form = self::FormData();
        foreach ($form[self::$page] as $key => $value) {
            $list[$key] = self::$config->asString($key);
        }

        return $list;
    }

    private function post()
    {
        $proccess = [];

        $form = self::FormData();
        foreach ($form[self::$page] as $key => $value) {
            $vv = Tools::getValue($key);

            if (in_array($key, ['rest_key', 'tracking_key', 'customer_id']) && empty($vv)) {
                self::$err['log'][] = self::$err['msg'][$key];
            }

            if (self::$config->{$key} != $vv) {
                self::$config->update($key, Tools::getValue($key));
                $proccess[] = $key;
            }
        }

        if (self::$config->tracking_key === '') {
            self::$config->status = false;
            $proccess[] = 'status';
        }

        foreach ($proccess as $key) {
            switch ($key) {
                case 'opt_in':
                    $this->updateOptIn();
                    break;
                case 'push_status':
                    Mktr\Route\refreshJS::updatePushStatus();
                    break;
            }
        }

        Mktr\Route\refreshJS::loadJs();

        self::$config->save();
    }

    private function updateOptIn()
    {
        $data = Mktr\Model\Config::nws();

        if (self::$config->opt_in == 0) {
            Mktr\Model\Config::setConfig($data['CONFIRMATION'], true);
            Mktr\Model\Config::setConfig($data['NOTIFICATION'], true);
        } else {
            Mktr\Model\Config::setConfig($data['CONFIRMATION'], false);
            Mktr\Model\Config::setConfig($data['NOTIFICATION'], false);
        }
    }

    private function outPut()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->default_form_language = Mktr\Model\Config::getLang();
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMktrModule';
        $helper->token = $this->token;

        $helper->currentIndex = self::$currentIndex . '&page=' . self::$page;

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => Mktr\Model\Config::getLang(),
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
            $out .= Mktr::i()->displayError(implode('<br />', self::$err['log']));
        }

        return $out . $helper->generateForm($this->getConfigForm());
    }

    public function token()
    {
        if (self::$t === null) {
            self::$t = 'token=' . $this->token;
        }

        return self::$t;
    }

    public function initContent()
    {
        self::$config = Mktr\Model\Config::setLang($this->context->language->id);

        self::$page = Mktr\Helper\Valid::getParam('page', self::$page);

        if (((bool) Tools::isSubmit('submitMktrModule')) == true) {
            $this->post();
        }

        if (!in_array(self::$page, ['google', 'tracker'])) {
            self::$page = 'tracker';
        }

        $this->title = 'TheMarketer - ' . ucfirst(self::$page);
        $this->toolbar_btn = $this->getToolbarBtn();
        $this->show_page_header_toolbar = true;
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
                        'href' => self::$currentIndex . '&' . $this->token(),
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
                'content' => $this->outPut(),
                'title' => $this->title,
                'toolbar_btn' => $this->toolbar_btn,
                'page_header_toolbar_btn' => $this->toolbar_btn,
            ]
        );
    }
}
