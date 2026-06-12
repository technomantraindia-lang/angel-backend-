<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->timestamps();
        });

        DB::table('site_contents')->updateOrInsert(
            ['key' => 'b2c_printing_policy'],
            [
                'title' => 'Printing Policy',
                'content' => "Please review this printing policy before placing your order.\n\nAll print jobs move into production only after artwork, quantity, and order details are confirmed.\n\nColor, texture, and finish may vary slightly between screen preview and final printed material.\n\nCustomers should upload clear artwork files and verify names, phone numbers, address details, spellings, and other custom text before submitting an order.\n\nOnce production starts, major content or design changes may require extra time or extra charges.\n\nFor help with your order, please contact our team before final approval.",
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('site_contents');
    }
};
