<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$admin = User::where('email', 'admin@angelprintshop.com')->first();
if ($admin) {
    $admin->password = Hash::make('Admin@123');
    $admin->save();
    echo "Password successfully reset for admin@angelprintshop.com to Admin@123\n";
} else {
    echo "Admin user not found.\n";
}
