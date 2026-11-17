# <img style="height:35px;vertical-align: middle;" src="https://github.com/the-marketer/Prestashop/blob/latest/logo.png" alt="TheMarketer"> TheMarketer - Prestashop Module

## Compatible with:
    - Prestashop 9 Module
    - Prestashop 8 Module
    - Prestashop 1.7 Module
    - Prestashop 1.6 Module
    
## Upgrade notice — 1.1.10

New setting: **Allow several TheMarketer codes in the same cart**. It is off on
new installs. Shops upgrading from an earlier version keep it on, so their codes
stay combinable as before. It only affects codes generated after the change.

With the setting off, generated codes are saved as non-combinable
(`cart_rule_restriction = 1`). PrestaShop then adds a row to
`cart_rule_combination` for each such code every time a combinable voucher is
saved in the back office, so the table grows with the number of codes. With
the setting on, saving a non-combinable voucher makes PrestaShop restrict all
existing codes and add roughly one row per pair of codes. Shops with many
generated codes should clean up used or expired ones.

## Upgrade notice — 1.1.6

The module cron endpoint now requires a `cron_token`, generated during install
or upgrade. Immediately after deploying this version, open the module's Tracker
configuration page and replace the cPanel command for every shop with the one
shown there. Existing commands without the token return HTTP 403; until they
are replaced, the traffic fallback starts only after 24 hours without cron.
