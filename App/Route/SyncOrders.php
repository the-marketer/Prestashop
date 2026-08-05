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

/**
 * Safety net for orders that never reached TheMarketer.
 *
 * The actionValidateOrder hook is the primary path; this sweeper catches what
 * that path loses - API timeouts, downtime, or a shop where the hook was not
 * registered. It walks the orders table forward from a watermark, so each
 * order is normally touched once.
 */
class SyncOrders
{
    /** Orders processed per run. */
    const BATCH = 25;

    /**
     * Wall clock budget for one run. Api::REST() sleeps a second after every
     * call, so a full batch can outlive max_execution_time - stop early and
     * bank the progress instead of being killed mid-batch.
     */
    const MAX_SECONDS = 20;

    /** How far back the very first run reaches. */
    const BACKFILL_HOURS = 48;

    /** Runs a failing batch is retried before it is skipped. */
    const MAX_RETRY = 3;

    public static function run($limit = self::BATCH, $maxSeconds = self::MAX_SECONDS)
    {
        if (!\Mktr\Model\Config::rest()) {
            return ['status' => 'disabled'];
        }

        $data = \Mktr\Helper\Data::init();
        $last = (int) $data->last_order_sync;

        if ($last <= 0) {
            $last = self::firstWatermark();
            $data->last_order_sync = $last;
        }

        $pending = self::pending($last, (int) $limit);

        if (empty($pending)) {
            \Mktr\Helper\Data::save();

            return ['status' => 'done', 'sent' => 0, 'watermark' => $last];
        }

        $sent = 0;
        $failed = 0;
        $head = $last;
        $batchEnd = $last;
        $startedAt = time();

        foreach ($pending as $row) {
            $id_order = (int) $row['id_order'];
            $batchEnd = $id_order;

            if (\Mktr\Model\Orders::push($id_order)) {
                ++$sent;

                // Only bank progress while nothing has failed yet, otherwise
                // the watermark would jump over the order that failed.
                if ($failed === 0) {
                    $head = $id_order;
                }
            } else {
                ++$failed;
            }

            if ((time() - $startedAt) >= $maxSeconds) {
                break;
            }
        }

        if ($failed === 0) {
            // Everything went through, jump to the end of the batch.
            $data->last_order_sync = $batchEnd;
            $data->last_order_sync_retry = 0;
        } else {
            $retry = (int) $data->last_order_sync_retry + 1;

            if ($retry >= self::MAX_RETRY) {
                // Something in here is permanently unsendable - step over it
                // instead of blocking every later order.
                $data->last_order_sync = $batchEnd;
                $data->last_order_sync_retry = 0;
            } else {
                // Keep the failures in range for the next run, but bank the
                // orders that already succeeded ahead of them.
                $data->last_order_sync = $head;
                $data->last_order_sync_retry = $retry;
            }
        }

        \Mktr\Helper\Data::save();

        return [
            'status' => 'done',
            'sent' => $sent,
            'failed' => $failed,
            'watermark' => (int) $data->last_order_sync,
        ];
    }

    /**
     * Where a shop that never ran the sweeper starts from: far enough back to
     * recover recently lost orders, not far enough to replay all history.
     *
     * @return int
     */
    private static function firstWatermark()
    {
        $sql = 'SELECT MIN(`id_order`) AS `id_order` FROM `' . _DB_PREFIX_ . 'orders`' .
            ' WHERE `date_add` >= \'' . \pSQL(date('Y-m-d H:i:s', strtotime('-' . self::BACKFILL_HOURS . ' hour'))) . '\'' .
            self::shopFilter();

        $row = \Mktr\Model\Config::db()->getRow($sql);

        if (!empty($row['id_order'])) {
            return ((int) $row['id_order']) - 1;
        }

        // No recent orders at all - start from the current head.
        $row = \Mktr\Model\Config::db()->getRow(
            'SELECT MAX(`id_order`) AS `id_order` FROM `' . _DB_PREFIX_ . 'orders`' .
            ' WHERE 1' . self::shopFilter()
        );

        return empty($row['id_order']) ? 0 : (int) $row['id_order'];
    }

    /**
     * @param int $last
     * @param int $limit
     *
     * @return array
     */
    private static function pending($last, $limit)
    {
        if ($limit <= 0) {
            $limit = self::BATCH;
        }

        $sql = 'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders`' .
            ' WHERE `id_order` > ' . (int) $last . self::shopFilter() .
            ' ORDER BY `id_order` ASC LIMIT ' . (int) $limit;

        $list = \Mktr\Model\Config::db()->executeS($sql);

        return empty($list) ? [] : $list;
    }

    /**
     * @return string
     */
    private static function shopFilter()
    {
        if (!\Shop::isFeatureActive()) {
            return '';
        }

        return ' AND `id_shop` = ' . (int) \Mktr\Model\Config::shop();
    }
}
