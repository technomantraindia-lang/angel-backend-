<?php

namespace App\Http\Controllers;

use App\Models\B2COrder;
use App\Models\B2CProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class B2COrderController extends Controller
{
    private const FINISH_SURCHARGES = [
        'none' => 0,
        'foil' => 15,
        'textured' => 10,
        'wax_seal' => 25,
    ];

    public function store(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        abort_unless($customer, 401);

        $this->ensureB2COrderItemColumns(['file_path', 'original_filename', 'gsm', 'gsm_price']);

        $validated = $this->validateOrderPayload($request);

        $productIds = collect($validated['items'])->pluck('product_id')->unique()->values();
        $products = B2CProduct::query()->whereIn('id', $productIds)->where('is_active', true)->get()->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'One or more selected products are unavailable right now.',
            ]);
        }

        $order = DB::transaction(function () use ($customer, $validated, $products, $request) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($validated['items'] as $item) {
                $product = $products->get((int) $item['product_id']);
                $printSide = $item['print_side'] ?? 'front';
                $finish = $item['finish'] ?? 'none';
                $finishCharge = self::FINISH_SURCHARGES[$finish] ?? 0;
                $this->assertValidPrintSide($product, $printSide);
                $this->assertValidQuantity($product, (int) $item['quantity']);
                $gsmOption = $this->resolveGsmOption($product, $item['gsm'] ?? null);

                $baseUnitPrice = $printSide === 'front_back'
                    ? (float) ($product->front_back_amount ?? $product->amount)
                    : (float) $product->amount;

                $gsmCharge = (float) ($gsmOption['extra_price'] ?? 0);
                $unitPrice = $baseUnitPrice + $finishCharge + $gsmCharge;
                $lineTotal = $unitPrice * (int) $item['quantity'];

                $subtotal += $lineTotal;
                $lineItems[] = [
                    'product' => $product,
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'print_side' => $printSide,
                    'gsm' => $gsmOption['label'] ?? null,
                    'gsm_price' => $gsmCharge,
                    'finish' => $finish,
                    'custom_text' => $item['custom_text'] ?? null,
                ];
            }

            $order = B2COrder::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer->id,
                'assigned_staff_id' => null,
                'staff_status' => 'pending',
                'contact_name' => $customer->name,
                'contact_email' => $customer->email,
                'contact_phone' => $customer->phone,
                'status' => 'new',
                'subtotal' => $subtotal,
                'grand_total' => $subtotal,
                'customer_note' => $validated['customer_note'] ?? null,
            ]);

            foreach ($lineItems as $index => $item) {
                $file = $request->file('files.' . $index);
                $path = $file ? $file->store('b2c/design-files/' . $order->id, 'public') : null;

                $order->items()->create([
                    'b2c_product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'category_name' => $item['product']->category,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'print_side' => $item['print_side'],
                    'gsm' => $item['gsm'],
                    'gsm_price' => $item['gsm_price'],
                    'finish' => $item['finish'],
                    'custom_text' => $item['custom_text'],
                    'file_path' => $path,
                    'original_filename' => $file?->getClientOriginalName(),
                ]);
            }

            return $order->fresh(['customer', 'assignedStaff', 'items']);
        });

        return response()->json([
            'message' => 'B2C order submitted successfully.',
            'order' => $order,
        ], 201);
    }

    public function myOrders(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        abort_unless($customer, 401);

        $orders = B2COrder::query()
            ->where('customer_id', $customer->id)
            ->with(['assignedStaff', 'items'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function queue(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'staff'], true), 403);

        $this->ensureB2COrderColumns([
            'assigned_staff_id',
            'staff_status',
            'deadline_at',
            'pickup_note',
            'completed_at',
            'picked_up_at',
        ]);

        $orders = B2COrder::query()
            ->with(['customer', 'assignedStaff', 'items'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotIn('staff_status', ['picked_up'])
            ->when($user->role === 'staff', function ($query) use ($user) {
                $query->where(function ($nested) use ($user) {
                    $nested->whereNull('assigned_staff_id')
                        ->orWhere('assigned_staff_id', $user->id);
                });
            })
            ->latest('created_at')
            ->get();

        return response()->json($orders);
    }

    public function updateStaffStatus(Request $request, B2COrder $b2cOrder): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'staff'], true), 403);

        $this->ensureB2COrderColumns([
            'assigned_staff_id',
            'staff_status',
            'pickup_note',
            'completed_at',
            'picked_up_at',
        ]);

        if ($user->role === 'staff' && $b2cOrder->assigned_staff_id !== null && $b2cOrder->assigned_staff_id !== $user->id) {
            abort(403, 'You are not assigned to this B2C order.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:pending,started,ready,picked_up'],
            'pickup_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $updates = [
            'staff_status' => $data['status'],
        ];

        if (array_key_exists('pickup_note', $data)) {
            $updates['pickup_note'] = $data['pickup_note'];
        }

        if ($data['status'] === 'started') {
            $updates['status'] = 'processing';
            $updates['completed_at'] = null;
            $updates['picked_up_at'] = null;
        }

        if ($data['status'] === 'ready') {
            $updates['status'] = 'processing';
            $updates['completed_at'] = now();
            $updates['picked_up_at'] = null;
        }

        if ($data['status'] === 'picked_up') {
            $updates['status'] = 'completed';
            $updates['picked_up_at'] = now();
            if (!$b2cOrder->completed_at) {
                $updates['completed_at'] = now();
            }
        }

        if ($data['status'] === 'pending') {
            $updates['completed_at'] = null;
            $updates['picked_up_at'] = null;
        }

        $b2cOrder->update($updates);

        return response()->json([
            'message' => 'B2C job status updated successfully.',
            'order' => $b2cOrder->fresh(['customer', 'assignedStaff', 'items']),
        ]);
    }

    private function validateOrderPayload(Request $request): array
    {
        $items = $request->input('items');

        if ($request->filled('items_json')) {
            $items = json_decode((string) $request->input('items_json'), true);
        }

        $payload = [
            'customer_note' => $request->input('customer_note'),
            'items' => is_array($items) ? $items : null,
        ];

        $validated = validator($payload, [
            'customer_note' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:b2c_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.print_side' => ['nullable', 'in:front,front_back'],
            'items.*.gsm' => ['nullable', 'string', 'max:50'],
            'items.*.finish' => ['nullable', 'in:none,foil,textured,wax_seal'],
            'items.*.custom_text' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        foreach ($validated['items'] as $index => $unused) {
            if ($request->hasFile('files.' . $index)) {
                $request->validate([
                    'files.' . $index => ['file', 'max:51200', 'extensions:cdr,zip,png,jpg,jpeg'],
                ]);
            }
        }

        return $validated;
    }

    private function ensureB2COrderColumns(array $columns): void
    {
        $missing = array_values(array_filter($columns, fn ($column) => !Schema::hasColumn('b2c_orders', $column)));

        if (empty($missing)) {
            return;
        }

        abort(
            500,
            'B2C database update is pending. Please run `php artisan migrate` in the backend folder before using the new B2C staff/upload features.'
        );
    }

    private function ensureB2COrderItemColumns(array $columns): void
    {
        $missing = array_values(array_filter($columns, fn ($column) => !Schema::hasColumn('b2c_order_items', $column)));

        if (empty($missing)) {
            return;
        }

        abort(
            500,
            'B2C database update is pending. Please run `php artisan migrate` in the backend folder before using uploaded sample files in B2C orders.'
        );
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'B2C-' . now()->format('Ymd') . '-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (B2COrder::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function assertValidPrintSide(B2CProduct $product, string $printSide): void
    {
        $allowed = match ($product->print_side_mode) {
            'front_back_only' => ['front_back'],
            'both' => ['front', 'front_back'],
            default => ['front'],
        };

        if (!in_array($printSide, $allowed, true)) {
            throw ValidationException::withMessages([
                'items' => "Selected print side is not available for {$product->name}.",
            ]);
        }
    }

    private function assertValidQuantity(B2CProduct $product, int $quantity): void
    {
        $minimum = max(1, (int) $product->print_copy);
        $step = max(1, (int) ($product->quantity_step ?? 1));

        if ($quantity < $minimum || (($quantity - $minimum) % $step) !== 0) {
            throw ValidationException::withMessages([
                'items' => "{$product->name} quantity must start at {$minimum} and increase by {$step}.",
            ]);
        }
    }

    private function resolveGsmOption(B2CProduct $product, ?string $gsm): ?array
    {
        $options = collect($product->gsm_options ?? [])
            ->map(function ($option) {
                if (is_string($option)) {
                    $label = trim($option);

                    return $label !== ''
                        ? ['label' => $label, 'extra_price' => 0.0]
                        : null;
                }

                if (!is_array($option)) {
                    return null;
                }

                $label = trim((string) ($option['label'] ?? ''));

                if ($label === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'extra_price' => round((float) ($option['extra_price'] ?? 0), 2),
                ];
            })
            ->filter()
            ->values();

        if ($options->isEmpty()) {
            return null;
        }

        $selected = $options->first(fn ($option) => ($option['label'] ?? null) === $gsm);

        if (!$gsm || !$selected) {
            throw ValidationException::withMessages([
                'items' => "Please select a valid GSM option for {$product->name}.",
            ]);
        }

        return $selected;
    }
}
