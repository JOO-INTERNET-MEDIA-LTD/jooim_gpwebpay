# GP webpay Payment Gateway

Integrates GP webpay / ČSOB card checkout, signed payment return verification, repeat payment, reconciliation and configured refund operations.

| | |
| --- | --- |
| Module | `jooim_gpwebpay` |
| Source version | `1.00.02` |
| Target platforms | PrestaShop 8.x and 9.x; staging validation required |
| Author | JOO INTERNET MEDIA LTD |

## Features and scope

Payment controller creates a pending native order/payment request. GpwebpayClient signs provider requests and verifies signed results; validation controller updates stored payment/order status. GpwebpayRepository records transactions/logs. Cron reconciles unfinished payments through web services. Existing SOAP WSDL/XSD files are runtime assets.

## Requirements and compatibility

Use a PHP version supported by your selected PrestaShop release. The full PHP/core version matrix has not been certified. Merchant number, GP webpay public key, merchant private key and its password are required as configured by your provider. Test mode must be verified end to end before production.

## Installation

1. Back up shop files/database and use staging first.
2. Download an approved installation ZIP and its SHA256 from the release assets.
3. Verify the checksum and upload the ZIP through Module Manager.
4. Install and select the intended shop before configuration.
5. Test the module’s actual workflow and theme hooks before production.

Do not install GitHub’s automatic **Source code ZIP**. The archive must contain one `jooim_gpwebpay/` root with its module entry point.

## Configuration and usage

DM_GPWEBPAY_* stores merchant/provider, private/public PEM keys, private-key password, mode, currency/carrier/test-IP restrictions, payment-method codes, deposit flag, pending/paid/failed/authorized/refunded state mappings and cron token. Defaults: TEST_MODE=1, PROVIDER=0300, DEPOSIT_FLAG=1, ALLOWED_CURRENCIES empty, ALLOWED_CARRIERS empty, PAYMETHODS=CRD,APAY,GPAY; order states derive from core PS_OS_* and cron token is generated. Empty password/key replacement retains stored secrets. Actual provider-enabled payment methods must be confirmed with the acquirer, not inferred from codes.

## Languages

English source uses the module’s existing PrestaShop translation mechanism. Bundled language coverage varies by module and installed shop languages. Belgian locales use French/Dutch/German variants; Canadian locales use English/French variants. New wording may use English fallback where a locale is incomplete. Native-speaker review is not claimed.

## Troubleshooting and known limitations

Gateway sandbox confirmation, signature/amount validation, callback retries, duplicate payment/refund protection and concurrent reconciliation are mandatory unresolved integration gates. Existing configuration validation is not fully transactional and needs a separate focused security review; this audit does not redesign payment processing.

Check the selected shop, configured credentials/options, assigned hooks, PHP extensions and PrestaShop cache first. Redact access URLs, secrets and customer data before sharing diagnostic output.

## Updates, uninstall and recovery

Apply reviewed installation packages on staging, retain a backup and recheck hooks/translations afterward. Source versions and existing releases are independent for each module. No automatic release or remote installation is performed.

Read the existing repository uninstall/schema handling before removal. Preserve payment/reconciliation evidence and export merchant settings securely; do not reset a payment module to repair footer hook placement.

For rollback, restore matching module files and the corresponding configuration/database backup when necessary. For GP webpay, verify `displayFooter` placement after updating older installations; do not reset the payment module to change a hook association.

## License and support

See [LICENSE](LICENSE) for the actual terms.

This free GitHub module is provided without individual technical support. Installation, configuration, production testing, updates and troubleshooting are available to customers of Joobox PrestaShop Cloud or another paid service expressly agreed with JOO INTERNET MEDIA LTD.

[Get Joobox support](https://support.joobox.eu) · [GitHub repository](https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_gpwebpay)
