<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2c_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('b2c_order_items', 'gsm_price')) {
                $table->decimal('gsm_price', 12, 2)->default(0)->after('gsm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('b2c_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('b2c_order_items', 'gsm_price')) {
                $table->dropColumn('gsm_price');
            }
        });
    }
};
