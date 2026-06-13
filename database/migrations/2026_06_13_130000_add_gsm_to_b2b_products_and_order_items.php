<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'gsm_options')) {
                $table->json('gsm_options')->nullable()->after('front_back_amount');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'gsm')) {
                $table->string('gsm', 50)->nullable()->after('print_side');
            }

            if (!Schema::hasColumn('order_items', 'gsm_price')) {
                $table->decimal('gsm_price', 12, 2)->default(0)->after('gsm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            foreach (['gsm', 'gsm_price'] as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'gsm_options')) {
                $table->dropColumn('gsm_options');
            }
        });
    }
};
