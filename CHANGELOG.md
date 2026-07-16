# Changelog

## 1.00.01

- Replaced raw internal payment states such as `paid` with customer-friendly translated labels on the order confirmation page.
- Stopped displaying the raw GP webpay `RESULTTEXT` value (for example `OK`) to customers; it remains stored for diagnostics.
- Added translated customer messages for successful, authorized, pending, failed, verification-failed and refunded payment states.
- Improved the payment-return block with semantic alert styling.

## 1.00.00 - testfix12

- Added local tracking of accepted GP webpay refund totals.
- Added database column `refunded_amount` with automatic schema migration when the module is opened or refund is submitted.
- Refund form now displays already refunded and remaining refundable amount.
- Refund requests above the remaining refundable amount are blocked before calling GP webpay WS.
- For older partial refunds accepted before this fix, the module attempts to infer the refunded amount from GP webpay `getPaymentDetail` when possible.

## 1.00.00-testfix9

- Changed GP webpay WS calls to prefer PHP SoapClient with the official WSDL files bundled in `lib/gpwebpay_ws`.
- Kept the existing cURL SOAP fallback for servers without the PHP SOAP extension.
- Fixed WS signature serialization for WSDL `base64Binary` fields by passing binary signature data to SoapClient.
- Improved WS fault parsing so refund errors show GP webpay return codes or SOAP fault text instead of only HTTP 500 when available.


## 1.00.00-testfix8
- Fixed missing `getPaymentDetail()` method in the WS client used by smart refund logic.
- Prevents fatal BO error when submitting a refund from the order detail.


## 1.00.00-testfix7
- Added smarter GP webpay refund handling for freshly captured payments in open batch state.
- The module now checks `getPaymentDetail` before refunding.
- If GP webpay reports state 7 / open capture batch, the module tries `processCaptureReverse` and then captures the remaining amount for partial refunds instead of calling `processRefund` directly.
- Added refund operation diagnostics to the GP webpay order log context.


### Testfix 6
- Refund form now posts back to the current order detail instead of the module configuration page.
- Refund success/error messages are displayed directly inside the GP webpay order panel.

# Changelog

## 1.00.00-testfix9

- Changed GP webpay WS calls to prefer PHP SoapClient with the official WSDL files bundled in `lib/gpwebpay_ws`.
- Kept the existing cURL SOAP fallback for servers without the PHP SOAP extension.
- Fixed WS signature serialization for WSDL `base64Binary` fields by passing binary signature data to SoapClient.
- Improved WS fault parsing so refund errors show GP webpay return codes or SOAP fault text instead of only HTTP 500 when available.


## 1.00.00-testfix8
- Fixed missing `getPaymentDetail()` method in the WS client used by smart refund logic.
- Prevents fatal BO error when submitting a refund from the order detail.


## 1.00.00-testfix3
- Fixed GP webpay return signature verification for DIGEST1. DIGEST1 is verified over the returned fields plus MERCHANTNUMBER, not over DIGEST plus MERCHANTNUMBER.
- Response signature verification now includes returned empty fields when they are present, matching the GP webpay signing rules.
- Added SHA1 verification fallback for older/test key responses while keeping SHA3-512 as the preferred algorithm.

## 1.00.00-testfix2
- Disabled outgoing ADDINFO in the initial HTTP payment request to avoid GP webpay PRCODE=3/SRCODE=83 during MVP testing.
- Improved the Back Office order payment block styling with a white bordered card layout.


## 1.00.00

- Initial stable GitHub release.
- Added GP webpay / ČSOB payment flow.
- Added Back Office settings for merchant, provider, private key, public key, currencies, carriers and order states.
- Added file upload for Private key PEM and GP webpay public key PEM while preserving the textarea fallback.
- Added English, Slovak and Czech classic module translations.
- Added Joobox support panel with language-specific support URL routing.
- Added Joobox footer attribution with duplicate-output prevention across JOOIM modules. The Back Office does not include an enable/disable switch for this attribution.
- Added GitHub support files and documentation.

## 1.00.00 testfix5
- Improved GP webpay WS refund validation.
- The module no longer marks an order as refunded when the WS response is a SOAP fault or a rejected request.
- Added clearer BO refund note for transactions before batch settlement.
