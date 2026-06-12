<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_b2b')->default(true)->after('is_active');
            $table->boolean('is_b2c')->default(false)->after('is_b2b');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_b2b')->default(true)->after('name');
            $table->boolean('is_b2c')->default(false)->after('is_b2b');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_b2b', 'is_b2c']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['is_b2b', 'is_b2c']);
        });
    }
};
