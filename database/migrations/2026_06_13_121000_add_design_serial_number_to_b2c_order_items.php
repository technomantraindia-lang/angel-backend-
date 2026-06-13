<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2c_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('b2c_order_items', 'design_serial_number')) {
                $table->string('design_serial_number', 120)->nullable()->after('custom_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('b2c_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('b2c_order_items', 'design_serial_number')) {
                $table->dropColumn('design_serial_number');
            }
        });
    }
};
