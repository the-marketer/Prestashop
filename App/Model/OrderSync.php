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
    /** Attempts before an order stops being retried and is left for inspection. */
    const MAX_ATTEMPTS = 5;

    /** How far back enrolment looks for orders nobody recorded. */
    const BACKFILL_HOURS = 48;

    /** Sent rows are kept this long, for support questions. */
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
            ' VALUES (' . (int) $id_order . ", 0, 0, '" . date('Y-m-d H:i:s') . "')"
        );
    }

    /**
     * @param int $id_order
     *
     * @return bool
     */
    public static function isSent($id_order)
    {
        $row = Config::db()->getRow(
            'SELECT `sent` FROM `' . self::table() . '` WHERE `id_order` = ' . (int) $id_order
        );

        return !empty($row) && (int) $row['sent'] === 1;
    }

    /**
     * @param int $id_order
     */
    public static function markSent($id_order)
    {
        self::enroll($id_order);

        Config::db()->execute(
            'UPDATE `' . self::table() . '` SET `sent` = 1, `attempts` = `attempts` + 1,' .
            " `last_error` = NULL, `date_sent` = '" . date('Y-m-d H:i:s') . "'" .
            ' WHERE `id_order` = ' . (int) $id_order
        );
    }

    /**
     * @param int $id_order
     * @param string $error
     */
    public static function markFailed($id_order, $error = '')
    {
        self::enroll($id_order);

        $error = \Tools::substr((string) $error, 0, 255);

        Config::db()->execute(
            'UPDATE `' . self::table() . '` SET `attempts` = `attempts` + 1,' .
            " `last_error` = '" . \pSQL($error) . "'" .
            ' WHERE `id_order` = ' . (int) $id_order
        );
    }

    /**
     * Orders still waiting to be delivered, oldest first, excluding the ones
     * that have exhausted their attempts.
     *
     * @param int $limit
     *
     * @return array
     */
    public static function pending($limit)
    {
        $list = Config::db()->executeS(
            'SELECT s.`id_order` FROM `' . self::table() . '` s' . self::shopJoin() .
            ' WHERE s.`sent` = 0 AND s.`attempts` < ' . (int) self::MAX_ATTEMPTS .
            ' ORDER BY s.`id_order` ASC LIMIT ' . (int) $limit
        );

        return empty($list) ? [] : $list;
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
     * Drops delivered rows once they are too old to be asked about.
     */
    public static function prune()
    {
        Config::db()->execute(
            'DELETE FROM `' . self::table() . '`' .
            " WHERE `sent` = 1 AND `date_sent` < '" .
            date('Y-m-d H:i:s', strtotime('-' . (int) self::KEEP_DAYS . ' day')) . "'"
        );
    }

    /**
     * Counts for the back office: what is waiting, and what gave up.
     *
     * @return array{pending: int, stuck: int}
     */
    public static function counts()
    {
        $row = Config::db()->getRow(
            'SELECT' .
            ' SUM(CASE WHEN s.`sent` = 0 AND s.`attempts` < ' . (int) self::MAX_ATTEMPTS . ' THEN 1 ELSE 0 END) AS `pending`,' .
            ' SUM(CASE WHEN s.`sent` = 0 AND s.`attempts` >= ' . (int) self::MAX_ATTEMPTS . ' THEN 1 ELSE 0 END) AS `stuck`' .
            ' FROM `' . self::table() . '` s' . self::shopJoin()
        );

        return [
            'pending' => empty($row['pending']) ? 0 : (int) $row['pending'],
            'stuck' => empty($row['stuck']) ? 0 : (int) $row['stuck'],
        ];
    }

    /**
     * Clears the attempt counter so exhausted orders are picked up again.
     *
     * @return int rows reopened
     */
    public static function retryStuck()
    {
        Config::db()->execute(
            'UPDATE `' . self::table() . '` s' . self::shopJoin() . ' SET s.`attempts` = 0' .
            ' WHERE s.`sent` = 0 AND s.`attempts` >= ' . (int) self::MAX_ATTEMPTS
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
