<?php

namespace App\Http\Controllers;

use App\Models\PortalNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $notifications = PortalNotification::query()
            ->forUser($user->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => PortalNotification::query()->forUser($user->id)->where('is_read', false)->count(),
        ]);
    }

    public function customerIndex(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        abort_unless($customer, 401);

        $notifications = PortalNotification::query()
            ->forCustomer($customer->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => PortalNotification::query()->forCustomer($customer->id)->where('is_read', false)->count(),
        ]);
    }

    public function markRead(Request $request, PortalNotification $notification): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $notification->user_id === $user->id, 403);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function customerMarkRead(Request $request, PortalNotification $notification): JsonResponse
    {
        $customer = $request->user('customer');
        abort_unless($customer && $notification->customer_id === $customer->id, 403);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        PortalNotification::query()
            ->forUser($user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function customerMarkAllRead(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        abort_unless($customer, 401);

        PortalNotification::query()
            ->forCustomer($customer->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
