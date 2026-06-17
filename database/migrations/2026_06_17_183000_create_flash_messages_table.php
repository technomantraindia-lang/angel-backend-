<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_messages', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique(); // 'dealer' or 'customer'
            $table->longText('text')->nullable();
            $table->string('image')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
        });

        // Migrate existing settings if they exist in site_contents table
        $dealerText = '';
        $dealerImage = '';
        $dealerActive = '0';
        $customerText = '';
        $customerImage = '';
        $customerActive = '0';

        if (Schema::hasTable('site_contents')) {
            $dealerText = DB::table('site_contents')->where('key', 'dealer_flash_text')->value('content') ?? '';
            $dealerImage = DB::table('site_contents')->where('key', 'dealer_flash_image')->value('content') ?? '';
            $dealerActive = DB::table('site_contents')->where('key', 'dealer_flash_active')->value('content') ?? '0';

            $customerText = DB::table('site_contents')->where('key', 'customer_flash_text')->value('content') ?? '';
            $customerImage = DB::table('site_contents')->where('key', 'customer_flash_image')->value('content') ?? '';
            $customerActive = DB::table('site_contents')->where('key', 'customer_flash_active')->value('content') ?? '0';
        }

        DB::table('flash_messages')->updateOrInsert(
            ['type' => 'dealer'],
            [
                'text' => $dealerText,
                'image' => $dealerImage,
                'active' => $dealerActive === '1' || $dealerActive === 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('flash_messages')->updateOrInsert(
            ['type' => 'customer'],
            [
                'text' => $customerText,
                'image' => $customerImage,
                'active' => $customerActive === '1' || $customerActive === 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_messages');
    }
};
