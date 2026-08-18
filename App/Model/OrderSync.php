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

/**
 * Delivery state of every order, one row each.
 *
 * Replaces the single "how far did we get" counter: this can answer whether a
 * given order reached TheMarketer, when, and why it did not.
 */
class OrderSync
{
    /** Waiting to be delivered. */
    const STATE_PENDING = 0;

    /** The API acknowledged it. */
    const STATE_SENT = 1;

    /**
     * Nothing left to deliver, and no amount of retrying would change that -
     * an order with no priced products, or one that no longer exists. Settled
     * like a delivery, so it never shows up as a failure the merchant has to
     * act on.
     */
    const STATE_SKIPPED = 2;

    /** A worker has claimed the order and is currently calling the API. */
    const STATE_PROCESSING = 3;

    /** Attempts before an order stops being retried and is left for inspection. */
    const MAX_ATTEMPTS = 5;

    /**
     * Delay before each retry, indexed by the number of attempts already made.
     * Without this the attempt budget is spent on whatever happens to hit the
     * order next - a customer refreshing the confirmation page can burn all
     * five in a few seconds, and a two minute API outage becomes permanent.
     * Spread out, the five attempts cover a bit over five hours.
     */
    const RETRY_DELAYS = [300, 900, 3600, 14400];

    /** Retry delay for an order that has not finished writing its lines yet. */
    const WAIT_FOR_DATA_DELAY = 300;

    /** A claim expires if its PHP worker dies before it can settle the order. */
    const CLAIM_TTL = 120;

    /** How far back enrolment looks for orders nobody recorded. */
    const BACKFILL_HOURS = 48;

    /** Settled rows are kept this long, for support questions. */
    const KEEP_DAYS = 90;

    /**
     * @return string
     */
    private static function table()
    {
        return _DB_PREFIX_ . 'mktr_order_sync';
    }

    /**
     * Records an order as awaiting delivery. Existing rows are left alone, so
     * this is safe to call from every path that touches an order.
     *
     * @param int $id_order
     */
    public static function enroll($id_order)
    {
        Config::db()->execute(
            'INSERT IGNORE INTO `' . self::table() . '` (`id_order`, `sent`, `attempts`, `date_add`)' .
            ' VALUES (' . (int) $id_order . ', ' . (int) self::STATE_PENDING . ", 0, '" . date('Y-m-d H:i:s') . "')"
        );
    }

    /**
     * @param int $id_order
     *
     * @return array|null
     */
    private static function row($id_order)
    {
        $row = Config::db()->getRow(
            'SELECT `sent`, `attempts`, `date_next_try` FROM `' . self::table() . '`' .
            ' WHERE `id_order` = ' . (int) $id_order
        );

        return empty($row) ? null : $row;
    }

    /**
     * @param int $id_order
     *
     * @return bool whether this order is settled - delivered or deliberately skipped
     */
    public static function isSettled($id_order)
    {
        $row = self::row($id_order);

        if ($row === null) {
            return false;
        }

        return (int) $row['sent'] === self::STATE_SENT || (int) $row['sent'] === self::STATE_SKIPPED;
    }

    /**
     * Atomically reserves an order for the worker that will send it. The
     * lease avoids duplicate save_order calls when cron, a browser request,
     * and the traffic fallback overlap. A dead PHP worker is recovered after
     * CLAIM_TTL seconds.
     *
     * @param int $id_order
     *
     * @return bool
     */
    public static function claim($id_order)
    {
        self::enroll($id_order);
        self::recoverExpiredClaim($id_order);

        $now = date('Y-m-d H:i:s');
        $until = date('Y-m-d H:i:s', time() + self::CLAIM_TTL);

        Config::db()->execute(
            'UPDATE `' . self::table() . '` s' . self::shopJoin() .
            ' SET s.`sent` = ' . (int) self::STATE_PROCESSING . ", s.`date_next_try` = '" . $until . "'" .
            ' WHERE s.`id_order` = ' . (int) $id_order .
            ' AND s.`sent` = ' . (int) self::STATE_PENDING .
            ' AND s.`attempts` < ' . (int) self::MAX_ATTEMPTS .
            " AND (s.`date_next_try` IS NULL OR s.`date_next_try` <= '" . $now . "')"
        );

        return (int) Config::db()->Affected_Rows() === 1;
    }

    /**
     * @param int $id_order
     *
     * @return bool
     */
    public static function isProcessing($id_order)
    {
        $row = self::row($id_order);

        return $row !== null && (int) $row['sent'] === self::STATE_PROCESSING &&
            !empty($row['date_next_try']) && strtotime($row['date_next_try']) > time();
    }

    /**
     * @param int $id_order
     *
     * @return bool
     */
    public static function markSent($id_order)
    {
        self::enroll($id_order);

        Config::db()->execute(
            'UPDATE `' . self::table() . '` SET `sent` = ' . (int) self::STATE_SENT . ', `attempts` = `attempts` + 1,' .
            " `last_error` = NULL, `date_next_try` = NULL, `date_sent` = '" . date('Y-m-d H:i:s') . "'" .
            ' WHERE `id_order` = ' . (int) $id_order . ' AND `sent` = ' . (int) self::STATE_PROCESSING
        );

        return (int) Config::db()->Affected_Rows() === 1;
    }

    /**
     * Closes an order that has nothing to deliver. Not a failure: retrying it
     * would produce the same empty payload for ever.
     *
     * @param int $id_order
     * @param string $reason
     *
     * @return bool
     */
    public static function markSkipped($id_order, $reason = '')
    {
        self::enroll($id_order);

        Config::db()->execute(
            'UPDATE `' . self::table() . '` SET `sent` = ' . (int) self::STATE_SKIPPED . ',' .
            " `last_error` = '" . \pSQL(\Tools::substr((string) $reason, 0, 255)) . "'," .
            " `date_next_try` = NULL, `date_sent` = '" . date('Y-m-d H:i:s') . "'" .
            ' WHERE `id_order` = ' . (int) $id_order . ' AND `sent` = ' . (int) self::STATE_PROCESSING
        );

        return (int) Config::db()->Affected_Rows() === 1;
    }

    /**
     * Keeps an incomplete order pending without spending an API retry. Some
     * payment modules create the order before writing its order_detail rows.
     *
     * @param int $id_order
     * @param string $reason
     * @param int $delay
     *
     * @return bool
     */
    public static function defer($id_order, $reason = '', $delay = self::WAIT_FOR_DATA_DELAY)
    {
        self::enroll($id_order);

        $error = \Tools::substr((string) $reason, 0, 255);
        $next = date('Y-m-d H:i:s', time() + max(1, (int) $delay));

        Config::db()->execute(
            'UPDATE `' . self::table() . '` SET `sent` = ' . (int) self::STATE_PENDING . ',' .
            " `last_error` = '" . \pSQL($error) . "'," .
            " `date_next_try` = '" . $next . "'" .
            ' WHERE `id_order` = ' . (int) $id_order . ' AND `sent` = ' . (int) self::STATE_PROCESSING
        );

        return (int) Config::db()->Affected_Rows() === 1;
    }

    /**
     * @param int $id_order
     * @param string $error
     *
     * @return bool
     */
    public static function markFailed($id_order, $error = '')
    {
        self::enroll($id_order);

        $row = self::row($id_order);
        $made = $row === null ? 0 : (int) $row['attempts'];
        $error = \Tools::substr((string) $error, 0, 255);

        Config::db()->execute(
            'UPDATE `' . self::table() . '` SET `sent` = ' . (int) self::STATE_PENDING . ', `attempts` = `attempts` + 1,' .
            " `last_error` = '" . \pSQL($error) . "'," .
            " `date_next_try` = '" . date('Y-m-d H:i:s', time() + self::retryDelay($made)) . "'" .
            ' WHERE `id_order` = ' . (int) $id_order . ' AND `sent` = ' . (int) self::STATE_PROCESSING
        );

        return (int) Config::db()->Affected_Rows() === 1;
    }

    /**
     * How long to wait after a failure, given how many attempts the order has
     * already had.
     *
     * Worked out here rather than in the UPDATE: MySQL evaluates assignments
     * left to right, so a CASE over `attempts` alongside `attempts` + 1 reads
     * whichever value the column order happens to produce. One extra indexed
     * read on a path that just spent a second waiting on HTTP is a fair price
     * for not depending on that.
     *
     * @param int $made attempts already made
     *
     * @return int seconds
     */
    private static function retryDelay($made)
    {
        $delays = self::RETRY_DELAYS;
        $last = count($delays) - 1;

        if ($made < 0) {
            $made = 0;
        }

        return (int) ($made > $last ? $delays[$last] : $delays[$made]);
    }

    /**
     * Orders still waiting to be delivered, oldest first, excluding the ones
     * that have exhausted their attempts or are still inside their backoff.
     *
     * @param int $limit
     *
     * @return array
     */
    public static function pending($limit)
    {
        self::recoverExpiredClaim();

        $list = Config::db()->executeS(
            'SELECT s.`id_order` FROM `' . self::table() . '` s' . self::shopJoin() .
            ' WHERE s.`sent` = ' . (int) self::STATE_PENDING . ' AND s.`attempts` < ' . (int) self::MAX_ATTEMPTS .
            " AND (s.`date_next_try` IS NULL OR s.`date_next_try` <= '" . date('Y-m-d H:i:s') . "')" .
            ' ORDER BY s.`id_order` ASC LIMIT ' . (int) $limit
        );

        return empty($list) ? [] : $list;
    }

    /**
     * Reopens delivery claims whose process died before recording its result.
     *
     * @param int|null $id_order
     */
    private static function recoverExpiredClaim($id_order = null)
    {
        $where = ' WHERE s.`sent` = ' . (int) self::STATE_PROCESSING .
            " AND s.`date_next_try` <= '" . date('Y-m-d H:i:s') . "'";

        if ($id_order !== null) {
            $where .= ' AND s.`id_order` = ' . (int) $id_order;
        }

        Config::db()->execute(
            'UPDATE `' . self::table() . '` s' . self::shopJoin() .
            ' SET s.`sent` = ' . (int) self::STATE_PENDING . ', s.`date_next_try` = NULL' .
            $where
        );
    }

    /**
     * Adds recent orders that have no row yet - orders created while the hook
     * was missing, or before this version was installed.
     *
     * @param int $limit
     *
     * @return int rows added
     */
    public static function discover($limit)
    {
        $since = date('Y-m-d H:i:s', strtotime('-' . (int) self::BACKFILL_HOURS . ' hour'));

        $list = Config::db()->executeS(
            'SELECT o.`id_order` FROM `' . _DB_PREFIX_ . 'orders` o' .
            ' LEFT JOIN `' . self::table() . '` s ON s.`id_order` = o.`id_order`' .
            " WHERE s.`id_order` IS NULL AND o.`date_add` >= '" . \pSQL($since) . "'" . self::shopFilter('o') .
            ' ORDER BY o.`id_order` ASC LIMIT ' . (int) $limit
        );

        if (empty($list)) {
            return 0;
        }

        foreach ($list as $row) {
            self::enroll($row['id_order']);
        }

        return count($list);
    }

    /**
     * Drops settled rows once they are too old to be asked about, and rows
     * whose order has since been deleted from the shop - those would otherwise
     * stay pending for ever and be reported as failures nobody can fix.
     */
    public static function prune()
    {
        Config::db()->execute(
            'DELETE FROM `' . self::table() . '`' .
            ' WHERE `sent` IN (' . (int) self::STATE_SENT . ', ' . (int) self::STATE_SKIPPED . ")" .
            " AND `date_sent` < '" .
            date('Y-m-d H:i:s', strtotime('-' . (int) self::KEEP_DAYS . ' day')) . "'"
        );

        Config::db()->execute(
            'DELETE s FROM `' . self::table() . '` s' .
            ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = s.`id_order`' .
            ' WHERE o.`id_order` IS NULL'
        );
    }

    /**
     * Counts for the back office: what is waiting, and what gave up. Skipped
     * orders are deliberately not reported - there is nothing the merchant can
     * do about an order with no priced products, and a banner that cannot be
     * cleared for ninety days only teaches people to ignore banners. The
     * reason is on the row itself for anyone who goes looking.
     *
     * @return array{pending: int, stuck: int}
     */
    public static function counts()
    {
        $row = Config::db()->getRow(
            'SELECT' .
            ' SUM(CASE WHEN s.`sent` = ' . (int) self::STATE_PENDING . ' AND s.`attempts` < ' . (int) self::MAX_ATTEMPTS . ' THEN 1 ELSE 0 END) AS `pending`,' .
            ' SUM(CASE WHEN s.`sent` = ' . (int) self::STATE_PENDING . ' AND s.`attempts` >= ' . (int) self::MAX_ATTEMPTS . ' THEN 1 ELSE 0 END) AS `stuck`' .
            ' FROM `' . self::table() . '` s' . self::shopJoin()
        );

        return [
            'pending' => empty($row['pending']) ? 0 : (int) $row['pending'],
            'stuck' => empty($row['stuck']) ? 0 : (int) $row['stuck'],
        ];
    }

    /**
     * Clears the attempt counter and the backoff so exhausted orders are
     * picked up by the next run.
     *
     * @return int rows reopened
     */
    public static function retryStuck()
    {
        Config::db()->execute(
            'UPDATE `' . self::table() . '` s' . self::shopJoin() .
            ' SET s.`attempts` = 0, s.`date_next_try` = NULL' .
            ' WHERE s.`sent` = ' . (int) self::STATE_PENDING . ' AND s.`attempts` >= ' . (int) self::MAX_ATTEMPTS
        );

        return (int) Config::db()->Affected_Rows();
    }

    /**
     * Restricts a query to the current shop by going through the orders table,
     * which already knows where each order belongs. Costs nothing on a single
     * shop install, where it produces no join at all.
     *
     * @return string
     */
    private static function shopJoin()
    {
        if (!\Shop::isFeatureActive()) {
            return '';
        }

        return ' JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = s.`id_order`' .
            ' AND o.`id_shop` = ' . (int) Config::shop();
    }

    /**
     * @param string $alias
     *
     * @return string
     */
    private static function shopFilter($alias = '')
    {
        if (!\Shop::isFeatureActive()) {
            return '';
        }

        return ' AND ' . $alias . '.`id_shop` = ' . (int) Config::shop();
    }
}
