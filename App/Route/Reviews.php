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

use Mktr\Helper\Valid;
use Mktr\Model\Config;

class Reviews
{
    public static function run()
    {
        $t = Valid::getParam('start_date', date('Y-m-d'));
        $xml = null;

        \Mktr\Model\Reviews::init();

        if (defined('MKTR_PS_COMMENTS') && MKTR_PS_COMMENTS) {
            $o = \Mktr\Helper\Api::send('product_reviews', ['t' => strtotime($t)], false);

            $xml = simplexml_load_string($o->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
            $added = [];
            $data = \Mktr\Helper\Data::init();
            $revStore = $data->{'reviewStore' . Config::i()->rest_key};

            foreach ($xml->reviews as $value) {
                if (isset($value->review_date)) {
                    if (!isset($revStore[(string) $value->review_id])) {
                        $rev = \Mktr\Model\Reviews::addFromApi($value);

                        if ($rev !== null) {
                            $added[(string) $value->review_id] = $rev->id;
                        }
                    } else {
                        $added[(string) $value->review_id] = $data->reviewStore[(string) $value->review_id];
                    }
                }
            }

            $data->{'reviewStore' . Config::i()->rest_key} = $added;
            $data->save();
        }

        return $xml;
    }
}
