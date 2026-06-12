<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2c_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('b2c_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('b2c_category_id')->constrained('b2c_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('print_copy')->default(100);
            $table->decimal('amount', 12, 2);
            $table->decimal('front_back_amount', 12, 2)->nullable();
            $table->string('sample_pdf_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('b2c_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('b2c_product_id')->constrained('b2c_products')->cascadeOnDelete();
            $table->string('file_path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('b2c_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone', 30);
            $table->enum('status', ['new', 'reviewed', 'quoted', 'confirmed', 'processing', 'completed', 'cancelled'])->default('new');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('grand_total', 12, 2);
            $table->text('customer_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('b2c_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('b2c_order_id')->constrained('b2c_orders')->cascadeOnDelete();
            $table->foreignId('b2c_product_id')->nullable()->constrained('b2c_products')->nullOnDelete();
            $table->string('product_name');
            $table->string('category_name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->string('print_side', 20)->default('single');
            $table->string('finish', 30)->default('none');
            $table->date('event_date')->nullable();
            $table->text('custom_text')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('categories')) {
            $legacyCategories = DB::table('categories')
                ->where('is_b2c', true)
                ->orderBy('name')
                ->get();

            foreach ($legacyCategories as $category) {
                DB::table('b2c_categories')->updateOrInsert(
                    ['name' => $category->name],
                    [
                        'is_active' => true,
                        'sort_order' => 0,
                        'created_at' => $category->created_at ?? now(),
                        'updated_at' => $category->updated_at ?? now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('products')) {
            $legacyProducts = DB::table('products')
                ->where('is_b2c', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            foreach ($legacyProducts as $product) {
                $categoryId = DB::table('b2c_categories')->where('name', $product->category)->value('id');

                if (!$categoryId) {
                    $categoryId = DB::table('b2c_categories')->insertGetId([
                        'name' => $product->category,
                        'is_active' => true,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('b2c_products')->insert([
                    'b2c_category_id' => $categoryId,
                    'name' => $product->name,
                    'short_description' => null,
                    'description' => null,
                    'print_copy' => $product->print_copy ?? 100,
                    'amount' => $product->amount,
                    'front_back_amount' => $product->front_back_amount,
                    'sample_pdf_path' => null,
                    'is_active' => (bool) ($product->is_active ?? true),
                    'sort_order' => $product->sort_order ?? 0,
                    'created_at' => $product->created_at ?? now(),
                    'updated_at' => $product->updated_at ?? now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('b2c_order_items');
        Schema::dropIfExists('b2c_orders');
        Schema::dropIfExists('b2c_product_images');
        Schema::dropIfExists('b2c_products');
        Schema::dropIfExists('b2c_categories');
    }
};
