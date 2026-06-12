<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Change status to string so we can support 'working' and 'done' statuses
            $table->string('status')->default('new')->change();

            if (!Schema::hasColumn('orders', 'staff_status')) {
                $table->string('staff_status')->default('pending')->after('assigned_staff_id');
            }

            if (!Schema::hasColumn('orders', 'receipt_shared')) {
                $table->boolean('receipt_shared')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'staff_status')) {
                $table->dropColumn('staff_status');
            }

            if (Schema::hasColumn('orders', 'receipt_shared')) {
                $table->dropColumn('receipt_shared');
            }
            
            // Revert status type back to enum if necessary
            $table->enum('status', ['new','in_progress','ready','customer_called','picked_up','cancelled'])->default('new')->change();
        });
    }
};
