# AngelPrintShop B2B Portal — Hostinger Easy Install

This package includes the compiled React frontend inside `public/build`.

## Upload
1. Delete your earlier incomplete portal folder or replace it with this latest package.
2. Upload and extract this ZIP in the same Hostinger folder used for your current test.
3. Your current installer URL format is: `https://yourdomain.com/public/installer/`.

## Step 1: Prepare Laravel Files
Open:

`https://yourdomain.com/public/installer/dependencies.php`

Click **Install Laravel Dependencies**. This creates the `vendor` folder on Hostinger.

If Hostinger blocks the browser action, use Terminal in the folder containing `composer.json`:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

You do not need to run `npm` on Hostinger because `public/build` is included in the package.

## Step 2: Install Database and Create Logins
Open:

`https://yourdomain.com/public/installer/`

All server checks should show green. Fill your MySQL details, admin login and printing staff login, then click **Install Portal**.

## Step 3: Security After Installation
Delete this folder immediately after successful installation:

`public/installer/`

## Portal Features Included
- Dealer registration and admin approval/rejection
- Dealer login and wallet checkout
- Product table with multiple product cart selection
- Artwork upload for each ordered item
- Admin product and price management
- Wallet credit/debit adjustment
- Extra charges such as courier, cutting and finishing
- Printing staff queue with job status update and pickup workflow
