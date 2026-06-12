<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 30);
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        if (Schema::hasTable('users')) {
            $legacyCustomers = DB::table('users')
                ->select('name', 'email', 'phone', 'password', 'remember_token', 'created_at', 'updated_at')
                ->where('role', 'customer')
                ->get();

            foreach ($legacyCustomers as $customer) {
                DB::table('customers')->updateOrInsert(
                    ['email' => $customer->email],
                    [
                        'name' => $customer->name,
                        'phone' => $customer->phone ?: '',
                        'password' => $customer->password,
                        'is_active' => true,
                        'remember_token' => $customer->remember_token,
                        'created_at' => $customer->created_at ?? now(),
                        'updated_at' => $customer->updated_at ?? now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
