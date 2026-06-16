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
        Schema::table('b2c_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('b2c_orders', 'receipt_shared')) {
                $table->boolean('receipt_shared')->default(false)->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('b2c_orders', function (Blueprint $table) {
            if (Schema::hasColumn('b2c_orders', 'receipt_shared')) {
                $table->dropColumn('receipt_shared');
            }
        });
    }
};
