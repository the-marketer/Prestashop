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
 * @author      TheMarketer.com
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
 * Keeps generated discount codes combinable on shops that are upgrading.
 *
 * The "Allow several TheMarketer codes in the same cart" setting is off on new
 * installs, but every code generated before 1.1.10 was combinable. Writing the
 * value only where the key is missing keeps upgraded shops working as before
 * and never overrides a value someone already saved.
 *
 * @param Mktr $module
 *
 * @return bool
 */
function upgrade_module_1_1_10($module)
{
    $cfg = Mktr\Model\Config::CFG();
    $key = $cfg['code_cumulative']['key'];

    if (Shop::isFeatureActive()) {
        foreach (Shop::getShops(true, null, true) as $shopId) {
            if (!Configuration::hasKey($key, null, null, (int) $shopId)) {
                Configuration::updateValue($key, 1, false, null, (int) $shopId);
            }
        }

        return true;
    }

    if (!Configuration::hasKey($key)) {
        Configuration::updateValue($key, 1);
    }

    return true;
}
