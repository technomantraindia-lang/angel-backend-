<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2c_products', function (Blueprint $table) {
            if (!Schema::hasColumn('b2c_products', 'print_side_mode')) {
                $table->string('print_side_mode', 30)->default('front_only')->after('front_back_amount');
            }

            if (!Schema::hasColumn('b2c_products', 'quantity_step')) {
                $table->unsignedInteger('quantity_step')->default(1)->after('print_copy');
            }

            if (!Schema::hasColumn('b2c_products', 'gsm_options')) {
                $table->json('gsm_options')->nullable()->after('print_side_mode');
            }
        });

        DB::table('b2c_products')
            ->select(['id', 'front_back_amount'])
            ->orderBy('id')
            ->get()
            ->each(function ($product) {
                $mode = !is_null($product->front_back_amount) && (float) $product->front_back_amount > 0
                    ? 'both'
                    : 'front_only';

                DB::table('b2c_products')
                    ->where('id', $product->id)
                    ->update([
                        'print_side_mode' => $mode,
                        'quantity_step' => 1,
                    ]);
            });

        Schema::table('b2c_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('b2c_order_items', 'gsm')) {
                $table->string('gsm', 50)->nullable()->after('print_side');
            }
        });

        DB::table('b2c_order_items')
            ->where('print_side', 'single')
            ->update(['print_side' => 'front']);

        DB::table('b2c_order_items')
            ->where('print_side', 'double')
            ->update(['print_side' => 'front_back']);
    }

    public function down(): void
    {
        DB::table('b2c_order_items')
            ->where('print_side', 'front')
            ->update(['print_side' => 'single']);

        DB::table('b2c_order_items')
            ->where('print_side', 'front_back')
            ->update(['print_side' => 'double']);

        Schema::table('b2c_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('b2c_order_items', 'gsm')) {
                $table->dropColumn('gsm');
            }
        });

        Schema::table('b2c_products', function (Blueprint $table) {
            $dropColumns = [];

            foreach (['print_side_mode', 'quantity_step', 'gsm_options'] as $column) {
                if (Schema::hasColumn('b2c_products', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
