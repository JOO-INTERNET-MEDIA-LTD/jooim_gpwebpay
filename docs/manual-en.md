# User manual - GP webpay / ČSOB module

## Purpose of the module

The module adds a card payment method to PrestaShop and redirects the customer to GP webpay / ČSOB. After payment, the customer returns to the shop and the module verifies the gateway response signature.

## Main settings

### Test mode

Use test mode during initial configuration and bank/acquirer testing. Production payments must be processed only after the complete test flow is verified.

### Merchant number

Merchant identifier assigned by GP webpay / ČSOB. Use the value provided by the bank or acquirer.

### Provider code

Provider code used by WS operations. According to the GP webpay WS documentation, `0300` is Československá obchodní banka, a.s. and `7500` is Československá obchodná banka, a.s. Verify the exact value with the bank.

### Private key PEM

Private key used to sign payment requests. You can paste the key into the text area or upload the key file.

Do not publish this key, do not send it through GitHub Issues and do not commit it into the repository.

### Private key password

Password for the private key. Use the password assigned to your GP webpay key.

### GP webpay public key PEM

Public key used to verify `DIGEST` and `DIGEST1` in gateway return responses. You can paste the key into the text area or upload the public key file.

### Immediate capture / deposit

Controls whether the payment should be captured immediately. Use the value agreed with your payment setup and bank.

### Allowed currencies

Comma-separated ISO currency codes, for example:

`EUR,CZK`

Leave empty to allow all shop currencies.

### Allowed carriers

Comma-separated carrier IDs. Leave empty to allow the payment method for all carriers.

### Test IP whitelist

Comma-separated IP addresses allowed to see the payment method in test mode. Leave empty to show the method to everyone while test mode is active.

### Allowed GP webpay methods

Comma-separated payment method codes, for example:

`CRD,APAY,GPAY`

Leave empty to use the gateway default.

### Order states

Map gateway outcomes to PrestaShop order states:

- pending,
- paid,
- failed,
- authorized,
- refunded.

Use states that make operational sense for your shop. Do not map failed payments to a paid order state.

## Payment logs

The module stores payment information and logs. In the Back Office order detail, you can review payment status and gateway response data relevant for troubleshooting.

## Cron URL

The configuration page shows a cron URL. Run it every 5-15 minutes if you want the module to reconcile unfinished payments through the status check mechanism.

## Refunds

Refund support depends on correct GP webpay configuration and bank/acquirer permissions. Test refunds before relying on them in production.

## Joobox support and attribution

The Back Office configuration contains a Joobox support panel. The module also displays a small Joobox footer attribution. The module does not include a Back Office switch to disable this attribution.

## Update from GitHub

For updates, use GitHub Releases. Upload the new ZIP in PrestaShop Back Office or replace the `/modules/jooim_gpwebpay` folder manually. Keep the technical folder name unchanged.

### Refund note

GP webpay distinguishes payment-management operations by payment state. For a payment created with immediate capture (`DEPOSITFLAG=1`), a standard refund may not be available immediately after payment until the transaction batch is closed. If GP webpay WS rejects the refund, the module now shows an error and does not switch the order to refunded. For a freshly paid transaction before batch settlement, use the payment-reversal action in the GP webpay portal if the portal offers it.
Since testfix12, the module stores the accepted refunded total locally in `refunded_amount`. Before sending another refund request, it checks how much has already been refunded and blocks any amount above the remaining original payment balance. The order detail shows both the already refunded amount and the remaining refundable amount.
