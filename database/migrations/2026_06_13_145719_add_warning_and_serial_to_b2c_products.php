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
        Schema::table('b2c_products', function (Blueprint $table) {
            $table->text('warning')->nullable()->after('gsm_options');
            $table->boolean('allow_design_serial')->default(false)->after('warning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('b2c_products', function (Blueprint $table) {
            $table->dropColumn(['warning', 'allow_design_serial']);
        });
    }
};
