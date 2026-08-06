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
 * The actionValidateOrder hook is the primary path; whatever it loses - API
 * timeouts, downtime, a shop where the hook was never registered - stays
 * recorded as pending and is picked up here.
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

    public static function run($limit = self::BATCH, $maxSeconds = self::MAX_SECONDS)
    {
        if (!\Mktr\Model\Config::rest()) {
            return ['status' => 'disabled'];
        }

        // Orders nobody recorded - created before this version, or while the
        // hook was missing.
        $found = \Mktr\Model\OrderSync::discover($limit * 2);

        $pending = \Mktr\Model\OrderSync::pending($limit);

        if (empty($pending)) {
            return ['status' => 'done', 'found' => $found, 'sent' => 0, 'failed' => 0];
        }

        $sent = 0;
        $failed = 0;
        $startedAt = time();

        foreach ($pending as $row) {
            if (\Mktr\Model\Orders::push((int) $row['id_order'])) {
                ++$sent;
            } else {
                ++$failed;
            }

            if ((time() - $startedAt) >= $maxSeconds) {
                break;
            }
        }

        \Mktr\Model\OrderSync::prune();

        return [
            'status' => 'done',
            'found' => $found,
            'sent' => $sent,
            'failed' => $failed,
        ];
    }
}
