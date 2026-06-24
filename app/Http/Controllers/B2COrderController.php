<?php

namespace App\Http\Controllers;

use App\Models\B2COrder;
use App\Models\B2CProduct;
use App\Services\PortalNotificationService;
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

        $this->ensureB2COrderItemColumns(['file_path', 'original_filename', 'gsm', 'gsm_price', 'design_serial_number']);

        $validated = $this->validateOrderPayload($request);

        $b2cProductIds = [];
        $colorPrintProductIds = [];
        foreach ($validated['items'] as $item) {
            if (!empty($item['is_color_print'])) {
                $colorPrintProductIds[] = (int) $item['product_id'];
            } else {
                $b2cProductIds[] = (int) $item['product_id'];
            }
        }

        $b2cProducts = B2CProduct::query()->whereIn('id', $b2cProductIds)->where('is_active', true)->get()->keyBy('id');
        $colorPrintProducts = \App\Models\Product::query()->whereIn('id', $colorPrintProductIds)->where('is_active', true)->where('is_b2c', true)->get()->keyBy('id');

        // Verify all products exist and are available
        foreach ($validated['items'] as $item) {
            $pid = (int) $item['product_id'];
            if (!empty($item['is_color_print'])) {
                if (!$colorPrintProducts->has($pid)) {
                    throw ValidationException::withMessages([
                        'items' => "Color print product ID {$pid} is unavailable.",
                    ]);
                }
            } else {
                if (!$b2cProducts->has($pid)) {
                    throw ValidationException::withMessages([
                        'items' => "Product ID {$pid} is unavailable.",
                    ]);
                }
            }
        }

        $order = DB::transaction(function () use ($customer, $validated, $b2cProducts, $colorPrintProducts, $request) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($validated['items'] as $item) {
                $pid = (int) $item['product_id'];
                $isColorPrint = !empty($item['is_color_print']);

                if ($isColorPrint) {
                    $product = $colorPrintProducts->get($pid);
                    $printCopy = max(1, (int) ($item['print_copy'] ?? $product->print_copy));
                    $printSide = $item['print_side'] ?? 'front';
                    
                    $effectiveSide = ($printSide === 'both' || $printSide === 'front_back') ? 'both' : 'front';
                    $hasBoth = $product->front_back_amount !== null && $product->front_back_amount !== '' && (float) $product->front_back_amount > 0;
                    $actualSide = ($effectiveSide === 'both' && $hasBoth) ? 'both' : 'front';

                    // Total job price is based on the selected copy count for this color-print item.
                    $productPrice = ($actualSide === 'both') ? $product->front_back_amount : $product->amount;
                    $baseCost = (float) $productPrice * $printCopy;

                    $discountPercent = 0.0;
                    if (!empty($product->pricing_tiers) && is_iterable($product->pricing_tiers)) {
                        foreach ($product->pricing_tiers as $tier) {
                            $tierSide = $tier['print_side'] ?? 'front';
                            if ($tierSide !== $actualSide) continue;

                            $min = (int) ($tier['min'] ?? 0);
                            $max = isset($tier['max']) && $tier['max'] !== '' && $tier['max'] !== null ? (int) $tier['max'] : null;
                            if ($printCopy >= $min && ($max === null || $printCopy <= $max)) {
                                $discountPercent = (float) ($tier['discount'] ?? 0);
                                break;
                            }
                        }
                    }

                    $lineTotal = round($baseCost * (1 - $discountPercent / 100), 2);
                    $unitPrice = round($lineTotal / max(1, $printCopy), 2);
                    $subtotal += $lineTotal;

                    $copiesLabel = "Copies: {$printCopy}, Side: " . ($actualSide === 'both' ? 'Double Side' : 'Single Side');
                    $customText = empty($item['custom_text']) ? $copiesLabel : $item['custom_text'] . " | " . $copiesLabel;

                    $lineItems[] = [
                        'is_color_print' => true,
                        'product' => $product,
                        'quantity' => $printCopy,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                        'print_side' => $actualSide === 'both' ? 'front_back' : 'front',
                        'gsm' => null,
                        'gsm_price' => 0,
                        'finish' => $item['finish'] ?? 'none',
                        'custom_text' => $customText,
                        'design_serial_number' => $item['design_serial_number'] ?? null,
                    ];
                } else {
                    $product = $b2cProducts->get($pid);
                    $printSide = $item['print_side'] ?? 'front';
                    $finish = $item['finish'] ?? 'none';
                    $finishCharge = self::FINISH_SURCHARGES[$finish] ?? 0;
                    $quantity = (int) $item['quantity'];
                    $designSerialNumber = isset($item['design_serial_number']) ? trim((string) $item['design_serial_number']) : null;
                    $this->assertValidPrintSide($product, $printSide);
                    $this->assertRequiredDesignSerial($product, $designSerialNumber);
                    $pricing = $this->resolveStandardProductPricing($product, $quantity, $printSide);

                    $lineTotal = round($pricing['tier_total'] + ($finishCharge * $quantity), 2);
                    $unitPrice = round($lineTotal / max(1, $quantity), 2);
                    $subtotal += $lineTotal;

                    $lineItems[] = [
                        'is_color_print' => false,
                        'product' => $product,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                        'print_side' => $printSide,
                        'gsm' => null,
                        'gsm_price' => 0,
                        'finish' => $finish,
                        'custom_text' => $item['custom_text'] ?? null,
                        'design_serial_number' => $designSerialNumber,
                    ];
                }
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
                    'b2c_product_id' => $item['is_color_print'] ? null : $item['product']->id,
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
                    'design_serial_number' => $item['design_serial_number'],
                    'file_path' => $path,
                    'original_filename' => $file?->getClientOriginalName(),
                ]);
            }

            return $order->fresh(['customer', 'assignedStaff', 'items']);
        });

        PortalNotificationService::notifyAdminsAndStaff([
            'type' => 'order_placed',
            'module' => 'b2c',
            'title' => 'New customer order placed',
            'message' => "{$order->order_number} was placed by {$order->contact_name}.",
            'related_model' => B2COrder::class,
            'related_id' => $order->id,
            'related_order_number' => $order->order_number,
        ]);

        return response()->json([
            'message' => 'Customer order submitted successfully.',
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
            abort(403, 'You are not assigned to this customer order.');
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
            'message' => 'Customer job status updated successfully.',
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
            'items.*.product_id' => ['required', 'integer'],
            'items.*.is_color_print' => ['nullable', 'boolean'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.packs' => ['nullable', 'integer', 'min:1'],
            'items.*.print_copy' => ['nullable', 'integer', 'min:1'],
            'items.*.print_side' => ['nullable', 'string', 'in:front,front_back,both'],
            'items.*.finish' => ['nullable', 'in:none,foil,textured,wax_seal'],
            'items.*.custom_text' => ['nullable', 'string', 'max:2000'],
            'items.*.design_serial_number' => ['nullable', 'string', 'max:120'],
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
        $year = now()->format('Y');
        $prefix = "B2C-{$year}-";

        $lastOrder = B2COrder::query()
            ->where('order_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextSeq = 1;
        if ($lastOrder) {
            $parts = explode('-', $lastOrder->order_number);
            $lastSeq = (int) end($parts);
            $nextSeq = $lastSeq + 1;
        }

        do {
            $paddedSeq = str_pad((string) $nextSeq, 2, '0', STR_PAD_LEFT);
            $number = "{$prefix}{$paddedSeq}";
            $nextSeq++;
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

    private function assertRequiredDesignSerial(B2CProduct $product, ?string $designSerialNumber): void
    {
        if (!$product->allow_design_serial) {
            return;
        }

        if (!filled($designSerialNumber)) {
            throw ValidationException::withMessages([
                'items' => "Design serial number is required for {$product->name}.",
            ]);
        }
    }

    private function resolveStandardProductPricing(B2CProduct $product, int $quantity, string $printSide): array
    {
        $pricingTiers = collect($product->pricing_tiers ?? [])
            ->map(function (array $tier) {
                $frontBackPrice = $tier['front_back_price'] ?? null;

                return [
                    'quantity' => max(1, (int) ($tier['quantity'] ?? 0)),
                    'price' => round((float) ($tier['price'] ?? 0), 2),
                    'front_back_price' => $frontBackPrice === null || $frontBackPrice === ''
                        ? null
                        : round((float) $frontBackPrice, 2),
                ];
            })
            ->filter(fn (array $tier) => $tier['quantity'] > 0)
            ->values();

        if ($pricingTiers->isNotEmpty()) {
            $matchedTier = $pricingTiers->firstWhere('quantity', $quantity);

            if (!$matchedTier) {
                throw ValidationException::withMessages([
                    'items' => "{$product->name} quantity is not available. Please select one of the listed quantity options.",
                ]);
            }

            $tierTotal = $printSide === 'front_back'
                ? $matchedTier['front_back_price']
                : $matchedTier['price'];

            if ($printSide === 'front_back' && ($tierTotal === null || $tierTotal <= 0)) {
                throw ValidationException::withMessages([
                    'items' => "Front & Back pricing is not available for {$product->name} at {$quantity} quantity.",
                ]);
            }

            return [
                'tier_total' => round((float) $tierTotal, 2),
            ];
        }

        $this->assertValidQuantity($product, $quantity);

        $baseUnitPrice = $printSide === 'front_back'
            ? (float) ($product->front_back_amount ?? $product->amount)
            : (float) $product->amount;

        return [
            'tier_total' => round($baseUnitPrice * $quantity, 2),
        ];
    }

    private function resolveGsmOption(B2CProduct $product, ?string $gsm): ?array
    {
        return null;
    }

    public function receipt(Request $request, B2COrder $b2cOrder)
    {
        $customer = $request->user('customer');
        $user = $request->user();

        if (!$customer && (!$user || !in_array($user->role, ['admin', 'staff'], true))) {
            abort(401, 'Unauthenticated');
        }

        if ($customer) {
            if ($b2cOrder->customer_id !== $customer->id || !$b2cOrder->receipt_shared) {
                abort(403, 'Unauthorized. This receipt is not shared yet.');
            }
        }

        $b2cOrder->load(['customer', 'items']);
        return view('b2c_receipt', ['order' => $b2cOrder]);
    }

    public function download(Request $request, \App\Models\B2COrderItem $item)
    {
        $user = $request->user();
        $customer = $request->user('customer');
        $order = $item->order;

        if ($customer) {
            if ($order->customer_id !== $customer->id) {
                abort(403, 'Unauthorized.');
            }
        } elseif ($user) {
            if (!in_array($user->role, ['admin', 'staff'], true)) {
                abort(403, 'Unauthorized.');
            }
        } else {
            abort(401, 'Unauthenticated');
        }

        if (empty($item->file_path) || !\Illuminate\Support\Facades\Storage::disk('public')->exists($item->file_path)) {
            abort(404, 'File not found.');
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->download($item->file_path, $item->original_filename);
    }
}
