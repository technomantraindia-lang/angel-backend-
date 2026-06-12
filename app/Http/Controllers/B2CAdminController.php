<?php

namespace App\Http\Controllers;

use App\Models\B2CCategory;
use App\Models\B2COrder;
use App\Models\B2CProduct;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Models\User;

class B2CAdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $recentOrders = B2COrder::query()
            ->with(['customer', 'assignedStaff'])
            ->latest()
            ->limit(6)
            ->get();

        return response()->json([
            'categories' => B2CCategory::query()->count(),
            'products' => B2CProduct::query()->count(),
            'active_products' => B2CProduct::query()->where('is_active', true)->count(),
            'customers' => Customer::query()->count(),
            'new_orders' => B2COrder::query()->where('status', 'new')->count(),
            'processing_orders' => B2COrder::query()->whereIn('status', ['reviewed', 'quoted', 'confirmed', 'processing'])->count(),
            'completed_orders' => B2COrder::query()->where('status', 'completed')->count(),
            'total_order_value' => B2COrder::query()->sum('grand_total'),
            'recent_orders' => $recentOrders,
        ]);
    }

    public function customers(): JsonResponse
    {
        return response()->json(
            Customer::query()
                ->withCount('orders')
                ->latest()
                ->get()
        );
    }

    public function destroyCustomer(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(['message' => 'B2C customer deleted successfully.']);
    }

    public function orders(): JsonResponse
    {
        return response()->json(
            B2COrder::query()
                ->with(['customer', 'assignedStaff', 'items'])
                ->latest()
                ->get()
        );
    }

    public function updateOrderStatus(Request $request, B2COrder $b2cOrder): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'reviewed', 'quoted', 'confirmed', 'processing', 'completed', 'cancelled'])],
        ]);

        $b2cOrder->update(['status' => $data['status']]);

        return response()->json([
            'message' => 'B2C order status updated successfully.',
            'order' => $b2cOrder->fresh(['customer', 'assignedStaff', 'items']),
        ]);
    }

    public function assignStaff(Request $request, B2COrder $b2cOrder): JsonResponse
    {
        $this->ensureB2CStaffColumns();

        $data = $request->validate([
            'assigned_staff_id' => ['nullable', 'exists:users,id'],
            'deadline_at' => ['nullable', 'date'],
        ]);

        if (!empty($data['assigned_staff_id'])) {
            abort_unless(
                User::query()->where('id', $data['assigned_staff_id'])->where('role', 'staff')->exists(),
                422,
                'Selected user is not printing staff.'
            );
        }

        $b2cOrder->update([
            'assigned_staff_id' => $data['assigned_staff_id'] ?? null,
            'deadline_at' => $data['deadline_at'] ?? null,
        ]);

        return response()->json([
            'message' => 'B2C work assignment updated successfully.',
            'order' => $b2cOrder->fresh(['customer', 'assignedStaff', 'items']),
        ]);
    }

    private function ensureB2CStaffColumns(): void
    {
        foreach (['assigned_staff_id', 'staff_status', 'deadline_at'] as $column) {
            if (!Schema::hasColumn('b2c_orders', $column)) {
                abort(
                    500,
                    'B2C database update is pending. Please run `php artisan migrate` in the backend folder before using the new B2C staff assignment module.'
                );
            }
        }
    }
}
