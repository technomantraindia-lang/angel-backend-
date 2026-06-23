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
            if (!Schema::hasColumn('b2c_products', 'pricing_tiers')) {
                $table->json('pricing_tiers')->nullable()->after('gsm_options');
            }
        });

        DB::table('b2c_products')
            ->select(['id', 'print_copy', 'amount', 'front_back_amount', 'pricing_tiers'])
            ->orderBy('id')
            ->get()
            ->each(function ($product) {
                $existingTiers = json_decode((string) ($product->pricing_tiers ?? ''), true);
                if (is_array($existingTiers) && !empty($existingTiers)) {
                    return;
                }

                $quantity = max(1, (int) ($product->print_copy ?? 1));
                $tiers = [[
                    'quantity' => $quantity,
                    'price' => round((float) $product->amount * $quantity, 2),
                ]];

                if (!is_null($product->front_back_amount) && (float) $product->front_back_amount > 0) {
                    $tiers[0]['front_back_price'] = round((float) $product->front_back_amount * $quantity, 2);
                }

                DB::table('b2c_products')
                    ->where('id', $product->id)
                    ->update([
                        'pricing_tiers' => json_encode($tiers),
                        'quantity_step' => 1,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('b2c_products', function (Blueprint $table) {
            if (Schema::hasColumn('b2c_products', 'pricing_tiers')) {
                $table->dropColumn('pricing_tiers');
            }
        });
    }
};
