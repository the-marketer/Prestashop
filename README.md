# <img style="height:35px;vertical-align: middle;" src="https://github.com/the-marketer/Prestashop/blob/latest/logo.png" alt="TheMarketer"> TheMarketer - Prestashop Module

## Compatible with:
    - Prestashop 9 Module
    - Prestashop 8 Module
    - Prestashop 1.7 Module
    - Prestashop 1.6 Module
    
## Upgrade notice — 1.1.6

The module cron endpoint now requires a `cron_token`, generated during install
or upgrade. Immediately after deploying this version, open the module's Tracker
configuration page and replace the cPanel command for every shop with the one
shown there. Existing commands without the token return HTTP 403; until they
are replaced, the traffic fallback starts only after 24 hours without cron.
