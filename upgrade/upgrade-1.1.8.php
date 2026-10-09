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
    mktr_remove_stale_controllers();

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
 * Drops front controllers left over from an earlier package.
 *
 * PrestaShop extracts the new package over the existing folder without
 * removing files that disappeared from it, so a shop coming from 1.1.7 keeps
 * `Cron.php` (old code) next to `cron.php` (current) on a case-sensitive
 * filesystem. Because the dispatcher includes the raw `controller` value from
 * the URL, a request for `controller=Cron` would then run the old file.
 *
 * fileinode() is deliberately not used to detect a case-insensitive
 * filesystem: several shared hosts and network mounts report 0 for every file,
 * which would make the two paths look identical and skip the cleanup on
 * exactly the systems that need it. Asking the filesystem for a name we do not
 * ship is conclusive.
 */
function mktr_remove_stale_controllers()
{
    $dir = _PS_MODULE_DIR_ . 'mktr/controllers/front/';

    if (!is_dir($dir)) {
        return;
    }

    $shipped = ['Api.php', 'cron.php', 'index.php'];

    // On a case-insensitive filesystem cron.php answers to this name too, and
    // every spelling is the same file - nothing to clean, and unlinking would
    // delete the controller itself.
    if (is_file($dir . 'cRoN.php')) {
        return;
    }

    $entries = @scandir($dir);

    if ($entries === false) {
        return;
    }

    $shippedLower = array_map('strtolower', $shipped);

    foreach ($entries as $entry) {
        if (in_array($entry, $shipped, true) || !is_file($dir . $entry)) {
            continue;
        }

        // Same controller, different casing: a leftover we no longer ship.
        if (in_array(strtolower($entry), $shippedLower, true)) {
            @unlink($dir . $entry);
        }
    }
}
