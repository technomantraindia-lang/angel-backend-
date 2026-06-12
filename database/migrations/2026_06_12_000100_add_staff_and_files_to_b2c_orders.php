<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2c_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('b2c_orders', 'assigned_staff_id')) {
                $table->foreignId('assigned_staff_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('b2c_orders', 'staff_status')) {
                $table->string('staff_status')->default('pending')->after('assigned_staff_id');
            }

            if (!Schema::hasColumn('b2c_orders', 'deadline_at')) {
                $table->timestamp('deadline_at')->nullable()->after('staff_status');
            }

            if (!Schema::hasColumn('b2c_orders', 'pickup_note')) {
                $table->text('pickup_note')->nullable()->after('customer_note');
            }

            if (!Schema::hasColumn('b2c_orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('pickup_note');
            }

            if (!Schema::hasColumn('b2c_orders', 'picked_up_at')) {
                $table->timestamp('picked_up_at')->nullable()->after('completed_at');
            }
        });

        Schema::table('b2c_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('b2c_order_items', 'file_path')) {
                $table->string('file_path')->nullable()->after('custom_text');
            }

            if (!Schema::hasColumn('b2c_order_items', 'original_filename')) {
                $table->string('original_filename')->nullable()->after('file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('b2c_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('b2c_order_items', 'original_filename')) {
                $table->dropColumn('original_filename');
            }

            if (Schema::hasColumn('b2c_order_items', 'file_path')) {
                $table->dropColumn('file_path');
            }
        });

        Schema::table('b2c_orders', function (Blueprint $table) {
            if (Schema::hasColumn('b2c_orders', 'picked_up_at')) {
                $table->dropColumn('picked_up_at');
            }

            if (Schema::hasColumn('b2c_orders', 'completed_at')) {
                $table->dropColumn('completed_at');
            }

            if (Schema::hasColumn('b2c_orders', 'pickup_note')) {
                $table->dropColumn('pickup_note');
            }

            if (Schema::hasColumn('b2c_orders', 'deadline_at')) {
                $table->dropColumn('deadline_at');
            }

            if (Schema::hasColumn('b2c_orders', 'staff_status')) {
                $table->dropColumn('staff_status');
            }

            if (Schema::hasColumn('b2c_orders', 'assigned_staff_id')) {
                $table->dropConstrainedForeignId('assigned_staff_id');
            }
        });
    }
};
