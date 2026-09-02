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

/**
 * Registers actionValidateOrder so orders are sent when they are created,
 * instead of relying on the customer landing on the confirmation page, and
 * adds the table that records what actually reached TheMarketer.
 *
 * Numbered 1.1.8 rather than 1.1.6 on purpose: the package published to
 * Addons carried version 1.1.7, so anything below that would never run on the
 * shops that need it most.
 *
 * @param Mktr $module
 *
 * @return bool
 */
function upgrade_module_1_1_8($module)
{
    mktr_remove_stale_cron_controller();

    if (!Mktr\Model\Config::db()->execute(Mktr\Helper\Setup::orderSyncTable())) {
        return false;
    }

    // A shop upgraded from an earlier build already has the table, so
    // CREATE TABLE IF NOT EXISTS above left it untouched.
    if (!Mktr\Helper\Setup::orderSyncColumns()) {
        return false;
    }

    // The cron endpoint is not public: generate its installation-wide secret
    // while the module is being upgraded, before the admin shows the command.
    Mktr\Model\Config::cronToken();

    if (!(Hook::getIdByName('actionValidateOrder') > 0)) {
        return true;
    }

    if ($module->isRegisteredInHook('actionValidateOrder')) {
        return true;
    }

    return (bool) $module->registerHook('actionValidateOrder');
}

/**
 * Drops the pre-1.1.8 `Cron.php`.
 *
 * PrestaShop extracts the new package over the existing folder without
 * removing files that disappeared, so a shop coming from 1.1.7 ends up with
 * both `Cron.php` (old code) and `cron.php` (current) on a case-sensitive
 * filesystem. A request for `controller=Cron` would then run the old file.
 *
 * The inode comparison is what makes this safe on case-insensitive
 * filesystems, where the two names are one and the same file.
 */
function mktr_remove_stale_cron_controller()
{
    $dir = _PS_MODULE_DIR_ . 'mktr/controllers/front/';
    $stale = $dir . 'Cron.php';
    $current = $dir . 'cron.php';

    if (!is_file($stale) || !is_file($current)) {
        return;
    }

    $staleNode = @fileinode($stale);
    $currentNode = @fileinode($current);

    if ($staleNode === false || $currentNode === false || $staleNode === $currentNode) {
        return;
    }

    @unlink($stale);
}
