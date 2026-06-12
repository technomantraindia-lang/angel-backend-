<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create categories table
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 2. Insert default categories
        DB::table('categories')->insert([
            ['name' => 'Color', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Black & White', 'created_at' => now(), 'updated_at' => now()]
        ]);

        // 3. Add pincode to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('pincode', 20)->nullable()->after('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pincode');
        });
        Schema::dropIfExists('categories');
    }
};
