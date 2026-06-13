<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PortalNotificationService
{
    public static function notifyAdminsAndStaff(array $payload): void
    {
        $recipients = User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->get(['id']);

        self::notifyUsers($recipients, $payload);
    }

    public static function notifyUsers(iterable $users, array $payload): void
    {
        $userIds = collect($users)
            ->map(fn ($user) => $user instanceof User ? $user->id : $user)
            ->filter()
            ->unique()
            ->values();

        self::insertRows($userIds, null, $payload);
    }

    public static function notifyCustomers(iterable $customers, array $payload): void
    {
        $customerIds = collect($customers)
            ->map(fn ($customer) => $customer instanceof Customer ? $customer->id : $customer)
            ->filter()
            ->unique()
            ->values();

        self::insertRows(null, $customerIds, $payload);
    }

    private static function insertRows(?Collection $userIds, ?Collection $customerIds, array $payload): void
    {
        if (!Schema::hasTable('portal_notifications')) {
            return;
        }

        $rows = [];
        $baseRow = [
            'type' => $payload['type'] ?? 'info',
            'module' => $payload['module'] ?? 'general',
            'title' => $payload['title'] ?? 'Notification',
            'message' => $payload['message'] ?? '',
            'related_model' => $payload['related_model'] ?? null,
            'related_id' => $payload['related_id'] ?? null,
            'related_order_number' => $payload['related_order_number'] ?? null,
            'is_read' => false,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($userIds ?? collect() as $userId) {
            $rows[] = [...$baseRow, 'user_id' => $userId, 'customer_id' => null];
        }

        foreach ($customerIds ?? collect() as $customerId) {
            $rows[] = [...$baseRow, 'user_id' => null, 'customer_id' => $customerId];
        }

        if (!empty($rows)) {
            PortalNotification::query()->insert($rows);
        }
    }
}
