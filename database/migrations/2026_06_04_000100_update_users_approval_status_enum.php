<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN approval_status ENUM('pending', 'approved', 'rejected', 'hold', 'banned') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update any rows with 'hold' or 'banned' back to 'pending' or 'rejected' to avoid MySQL errors on rollback
        DB::table('users')->where('approval_status', 'hold')->update(['approval_status' => 'pending']);
        DB::table('users')->where('approval_status', 'banned')->update(['approval_status' => 'rejected']);
        
        DB::statement("ALTER TABLE users MODIFY COLUMN approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
    }
};
