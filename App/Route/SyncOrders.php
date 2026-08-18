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
 * Delivers the orders sitting in the sync table.
 *
 * The actionValidateOrder hook records every order as it is created; this is
 * what actually sends them. It normally runs from the shop's cron job, and
 * from the emergency sweep on shops where that was never set up.
 */
class SyncOrders
{
    /** Orders delivered per run. */
    const BATCH = 25;

    /**
     * Wall clock budget for one run. Api::REST() sleeps a second after every
     * call, so a full batch can outlive max_execution_time - stop early and
     * leave the rest pending for the next run.
     */
    const MAX_SECONDS = 20;

    /** How long the cron may be silent before it counts as not running. */
    const CRON_SILENCE = 86400;

    public static function run($limit = self::BATCH, $maxSeconds = self::MAX_SECONDS)
    {
        if (!\Mktr\Model\Config::rest()) {
            return ['status' => 'disabled'];
        }

        // Orders nobody recorded - created before this version, or while the
        // hook was missing.
        $found = \Mktr\Model\OrderSync::discover($limit * 2);

        $pending = \Mktr\Model\OrderSync::pending($limit);

        $settled = 0;
        $failed = 0;
        $startedAt = time();

        foreach ($pending as $row) {
            if ($settled + $failed > 0 && (time() - $startedAt) >= $maxSeconds) {
                break;
            }

            if (\Mktr\Model\Orders::push((int) $row['id_order'])) {
                ++$settled;
            } else {
                ++$failed;
            }
        }

        \Mktr\Model\OrderSync::prune();

        self::recordRun();

        return [
            'status' => 'done',
            'found' => $found,
            'sent' => $settled,
            'failed' => $failed,
        ];
    }

    /**
     * Whether the shop's cron has gone quiet. Both the back office warning and
     * the emergency sweep ask this, so they can never disagree about it.
     *
     * @return bool
     */
    public static function cronIsStale()
    {
        $data = \Mktr\Helper\Data::init();
        $lastRun = (int) $data->last_cron_run;

        if ($lastRun > 0) {
            return $lastRun < (time() - self::CRON_SILENCE);
        }

        // Never seen a run since this version - fall back to the feed
        // timestamp, which a working cron keeps in the future.
        return (int) $data->update_feed <= time();
    }

    /**
     * When orders were last actually delivered, whichever path did it. The
     * back office reports this separately from the cron's own timestamp, so a
     * shop being covered by the emergency sweep is not told nothing is
     * happening.
     */
    private static function recordRun()
    {
        try {
            $data = \Mktr\Helper\Data::init();
            $data->last_order_sync_run = time();
            \Mktr\Helper\Data::save();
        } catch (\Exception $e) {
            // A read-only storage directory must not stop delivery.
        }
    }
}
