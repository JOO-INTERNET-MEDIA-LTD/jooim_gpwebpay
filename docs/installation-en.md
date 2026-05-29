# Installation guide - GP webpay / ČSOB module

## 1. Download the module

Download the release ZIP from the GitHub repository:

`https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_gpwebpay`

The ZIP must contain the module folder named exactly:

`jooim_gpwebpay`

Do not rename this folder. PrestaShop uses the folder name as the technical module name.

## 2. Install in PrestaShop Back Office

1. Log in to PrestaShop Back Office.
2. Go to **Modules > Module Manager**.
3. Click **Upload a module**.
4. Upload the ZIP package.
5. After upload, click **Install**.
6. Open **Configure**.

Alternative manual installation:

1. Extract the ZIP locally.
2. Upload the `jooim_gpwebpay` folder into `/modules/` on the server.
3. In Back Office, search for `jooim_gpwebpay` or `GP webpay`.
4. Click **Install**.

## 3. Configure test credentials

Fill in the required GP webpay / ČSOB values:

- Merchant number,
- provider code,
- private key PEM,
- private key password,
- GP webpay public key PEM,
- test mode,
- allowed currencies,
- order states.

The private key and public key can be pasted into the text area or uploaded from a file. If a file is uploaded, the uploaded file has priority over the text area.

## 4. Test before production

Before enabling production mode, test at minimum:

- successful payment,
- failed or cancelled payment,
- return to shop,
- order state change,
- signature verification,
- payment log in the order detail,
- cron reconciliation if you use it,
- refund flow if you enable refunds.

## 5. Switch to production

After successful test certification with the bank/acquirer:

1. Disable **Test mode**.
2. Replace test keys with production keys.
3. Confirm the provider code and merchant number with the bank.
4. Run a low-value production payment test.

## Support

Free GitHub distribution does not include individual support. Paid support is available through Joobox:

`https://joobox.eu/en-gb/support`
