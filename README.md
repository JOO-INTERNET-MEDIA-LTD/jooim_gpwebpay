# Payment gateway GP webpay / ČSOB for PrestaShop

Free GP webpay / ČSOB payment module for PrestaShop by **JOO INTERNET MEDIA LTD**.

- Module folder: `jooim_gpwebpay`
- Internal configuration prefix: `DM_GPWEBPAY`
- Version: `1.00.01`
- Supported PrestaShop versions: 1.7, 8.x, 9.x
- GitHub profile: https://github.com/jooim12
- GitHub repository: https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_gpwebpay
- Support: https://joobox.eu/en-gb/support

## Support policy

This module is available for free on GitHub, but the free GitHub distribution does **not** include individual technical support.

Technical support, installation, configuration, production testing, updates and troubleshooting are available only for customers using **Joobox PrestaShop Cloud**.

See [SUPPORT.md](SUPPORT.md).

## Support URLs

- SK: https://joobox.eu/sk-sk/podpora
- CS: https://joobox.eu/cs-cz/podpora
- EN-GB: https://joobox.eu/en-gb/support
- DE-DE: https://joobox.eu/de-de/unterstutzung
- DE-AT: https://joobox.eu/de-at/unterstutzung
- DE-CH: https://joobox.eu/de-ch/unterstutzung
- FR-FR: https://joobox.eu/fr-fr/soutien
- IT-IT: https://joobox.eu/it-it/supporto
- EN-CA: https://joobox.eu/en-ca/support
- FR-CA: https://joobox.eu/fr-ca/support
- PL-PL: https://joobox.eu/pl-pl/wsparcie

## What is implemented

- PrestaShop payment method for GP webpay / ČSOB.
- HTTP API payment creation with OpenSSL signing.
- Return controller accepting GET and POST responses.
- `DIGEST` and `DIGEST1` verification using GP webpay public key.
- Order state mapping for pending, paid, failed, authorized and refunded payments.
- Payment log table and admin order panel.
- Repeat-payment link for unfinished/failed payments.
- Currency and carrier restrictions.
- Test mode, production mode and optional test IP whitelist.
- WS client scaffold for `getPaymentStatus` and `processRefund`.
- Cron endpoint for reconciliation of unfinished payments.
- Joobox PrestaShop Cloud support panel above module settings.
- Joobox footer attribution text in displayFooterAfter when available, with duplicate-output prevention across JOOIM modules.

## Joobox attribution link

The module installs with a small Joobox footer attribution text in displayFooterAfter when available. It is intended as a non-intrusive source/support attribution and is not configurable from the Back Office.

## Server requirements

- PHP OpenSSL functions: `openssl_get_privatekey`, `openssl_sign`, `base64_encode`, `openssl_free_key`, `openssl_get_publickey`, `openssl_verify`.
- PHP cURL for WS refund/status calls.

## Installation

Upload the `jooim_gpwebpay` folder into `/modules/`, install the module in PrestaShop admin, configure GP webpay credentials, test the full payment flow, then switch to production.

Detailed notes are in the installation guides and manuals:

- English: [`docs/installation-en.md`](docs/installation-en.md), [`docs/manual-en.md`](docs/manual-en.md)
- Slovak: [`docs/installation-sk.md`](docs/installation-sk.md), [`docs/manual-sk.md`](docs/manual-sk.md)
- Czech: [`docs/installation-cs.md`](docs/installation-cs.md), [`docs/manual-cs.md`](docs/manual-cs.md)

General configuration notes are in [`docs/configuration.md`](docs/configuration.md).


## Updates from GitHub

PrestaShop does not update arbitrary GitHub modules automatically from a repository URL. The safe update path is:

1. download the latest ZIP from GitHub Releases,
2. upload the new module ZIP in PrestaShop Back Office,
3. keep the same module folder name `jooim_gpwebpay`,
4. increase the module version only in a future release,
5. add PrestaShop upgrade scripts in `/upgrade/` when database or configuration changes are required.

For developers, the repository root should contain the `jooim_gpwebpay` module folder. You can update a development installation by replacing that folder or by using Git in `/modules/jooim_gpwebpay`.

## GitHub Issues

GitHub Issues are only for reproducible bugs in the module. They are not a free helpdesk for installation, bank configuration, custom shop debugging or production payment troubleshooting.

## Important production warning

Before production use, run real GP webpay certification tests with your bank/acquirer, especially:

- successful payment,
- failed payment,
- return signature verification,
- request parameter acceptance by GP webpay,
- refund flow,
- order state mapping,
- cron reconciliation.

The module is provided as-is. Use in production only after proper testing.
