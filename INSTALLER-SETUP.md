# Angel Print Shop Portal — Installer Edition

This edition includes a browser-based installer at `public/installer/index.php`.

## What the Web Installer Does

- Checks PHP extensions, writable folders and availability of Laravel/React compiled dependencies.
- Takes MySQL database details from a simple form.
- Imports the portal SQL tables and sample product price-list data.
- Creates your own Admin and Printing Staff login credentials.
- Generates the production `.env` configuration and application key.
- Tries to create the public storage link for uploaded artwork files.
- Locks itself after installation.

## Before Uploading to the Live Server

The complete deployable application must contain:

- `vendor/` — Laravel PHP dependencies.
- `public/build/` — compiled React frontend files.

To produce these folders on a computer with PHP, Composer and Node.js installed:

### Windows

```bat
scripts\build-production-windows.bat
```

### Linux / macOS

```bash
bash scripts/build-production-linux.sh
```

Do not upload `node_modules`. The build script removes it after compiling.

## Server Upload Method

Upload the complete project folder to hosting and point the domain document root to:

```text
angelprintshop-b2b-portal/public
```

Then open:

```text
https://your-domain.com/installer/
```

Fill database, admin login and printing staff login details, then click **Install Portal**.

## After Installation

1. Open and test dealer registration, admin approval, wallet addition, product order and staff queue.
2. Delete the `public/installer` folder from hosting.
3. Confirm `APP_DEBUG=false` in `.env`.
4. Ensure `storage` and `bootstrap/cache` stay writable.
5. If this is an update to an older installed copy instead of a fresh install, run `php artisan migrate` once after uploading the new backend files.

## Hosting Requirements

- PHP 8.2 or newer
- PDO MySQL, OpenSSL, Mbstring and Fileinfo PHP extensions
- MySQL database
- Domain document root configured to Laravel `public/`
- File upload size configured for artwork files (recommend at least 50 MB)
