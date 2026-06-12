<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('dealer', 'staff', 'admin', 'customer') NOT NULL DEFAULT 'dealer'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First reset any customer users back to dealer role to prevent MySQL error during column modification
        DB::table('users')->where('role', 'customer')->update(['role' => 'dealer']);
        
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('dealer', 'staff', 'admin') NOT NULL DEFAULT 'dealer'");
    }
};
