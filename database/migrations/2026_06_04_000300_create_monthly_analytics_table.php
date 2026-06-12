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
        Schema::create('monthly_analytics', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->integer('month'); // 1 to 12
            $table->integer('total_orders')->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->decimal('estimated_profit', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_analytics');
    }
};
