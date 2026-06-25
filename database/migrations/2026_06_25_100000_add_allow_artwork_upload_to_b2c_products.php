<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2c_products', function (Blueprint $table) {
            $table->boolean('allow_artwork_upload')->default(false)->after('allow_design_serial');
        });
    }

    public function down(): void
    {
        Schema::table('b2c_products', function (Blueprint $table) {
            $table->dropColumn('allow_artwork_upload');
        });
    }
};
