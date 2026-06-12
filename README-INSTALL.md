# AngelPrintShop.com — B2B Printing Portal

Laravel + React portal built for a printing shop where approved dealers directly add individual products to cart, upload design files, and pay using wallet balance.

## Included Features

- Dealer registration form with admin approval or rejection.
- Login for dealer, printing staff and admin.
- Individual product price chart exactly in B2B style: category rows, product, fixed print-copy quantity, amount and **Add to Cart**.
- Right-side cart that accepts multiple products, packs and mandatory artwork file upload per item.
- Wallet checkout; server calculates totals and deducts balance securely.
- Admin dashboard to add products, quick-edit prices, approve dealers, credit/debit wallet and add charges such as courier or cutting.
- Printing staff queue sorted by customer deadline; staff marks jobs as Start, Ready, Customer Called and Picked Up.
- Uploaded artwork download link in the staff queue.

## Technology

- Laravel 12 application backend with session authentication.
- React frontend inside Laravel using Vite.
- MySQL database; ready SQL file is included.

## Easy Installation on Local Computer

### Requirements

Install PHP 8.2 or later, Composer, Node.js/NPM and MySQL.

### Option A — Windows quick install

1. Extract this project ZIP.
2. Open terminal inside the extracted folder.
3. Run:

```bat
install-windows.bat
```

4. Create a MySQL database named `angelprintshop`.
5. Import `database/angelprintshop.sql` through phpMyAdmin or MySQL command line.
6. Open `.env` and fill your MySQL username/password.
7. Start the application:

```bat
php artisan serve
```

Open `http://127.0.0.1:8000`.

### Option B — Manual commands

```bash
copy .env.example .env
composer install
php artisan key:generate
php artisan storage:link
npm install
npm run build
php artisan serve
```

For Linux/macOS, replace `copy` with `cp` or run `bash install-linux.sh`.

## Database Setup: Use SQL Import

Update the database block in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=angelprintshop
DB_USERNAME=root
DB_PASSWORD=
```

Then import:

```bash
mysql -u root -p angelprintshop < database/angelprintshop.sql
```

Do **not** run `php artisan migrate --seed` right after a fresh SQL import, because the schema and sample data are already created.

If you already have an older installed database and you pulled newer code updates, run this instead from the `backend` folder:

```bash
php artisan migrate
```

This is required for newer B2C changes such as customer sample-file uploads and B2C staff assignment fields.

Alternatively, for a fresh blank database without importing SQL:

```bash
php artisan migrate --seed
```

## Login Accounts Included in SQL / Seeder

| Role | Email | Password |
|---|---|---|
| Admin | admin@angelprintshop.com | Admin@123 |
| Printing Staff | staff@angelprintshop.com | Staff@123 |
| Demo Approved Dealer | dealer@example.com | Dealer@123 |

Change these passwords before using on a live website.

## Order Process

1. Dealer registers and waits for approval.
2. Admin approves dealer and adds money to dealer wallet.
3. Dealer logs in, chooses products from the price chart and adds multiple products to the cart.
4. Dealer uploads design file for every selected item, chooses a deadline, and pays by wallet.
5. Printing staff sees jobs arranged by deadline and downloads artwork.
6. Staff updates the job: In Progress → Ready → Customer Called → Picked Up.
7. Admin can add extra charges such as Courier or Cutting; charges are deducted from wallet and shown on the order.

## Uploaded Design Files

Artwork files are saved in `storage/app/public/design-files`. The install command creates a public link using:

```bash
php artisan storage:link
```

Allowed file extensions: PDF, CDR, AI, JPG, JPEG, PNG and ZIP; maximum size is 50 MB per item.

## Production Notes

Before going live, turn off debug in `.env`, change demo passwords, configure backups, validate maximum upload size in PHP/server settings, use HTTPS and restrict staff/admin accounts.
