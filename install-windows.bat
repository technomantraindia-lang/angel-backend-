@echo off
IF NOT EXIST .env copy .env.example .env
composer install
php artisan key:generate
php artisan storage:link
npm install
npm run build
echo.
echo INSTALL DONE. Now import database\angelprintshop.sql into your MySQL database and confirm DB details in .env.
echo Run: php artisan serve
pause
