<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PortalNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function myOrders(Request $request): JsonResponse
    {
        return response()->json(Order::with(['items','extraCharges'])->where('dealer_id', $request->user()->id)->latest()->get());
    }

    public function checkout(Request $request): JsonResponse
    {
        $request->validate(['items_json'=>['required','string'], 'customer_note'=>['nullable','string','max:1000']]);
        $items = json_decode($request->string('items_json')->toString(), true);
        if (!is_array($items) || count($items) < 1) throw ValidationException::withMessages(['items'=>'Cart is empty.']);
        foreach ($items as $index => $unused) {
            if (!$request->hasFile('files.'.$index)) {
                throw ValidationException::withMessages(['files' => 'Please upload artwork file for every product in the cart.']);
            }
            $request->validate(['files.'.$index => ['file', 'max:51200', 'extensions:cdr,jpg,jpeg,png,zip']]);
        }
        $result = DB::transaction(function () use ($request, $items) {
            /** @var User $dealer */
            $dealer = User::lockForUpdate()->findOrFail($request->user()->id);
            $lines = []; $subtotal = 0;
            foreach ($items as $index => $item) {
                $product = Product::where('is_active', true)->findOrFail((int)($item['product_id'] ?? 0));
                $packs = max(1, (int)($item['packs'] ?? 1));
                $printCopy = max(1, (int)($item['print_copy'] ?? $product->print_copy));
                
                $printSide = $item['print_side'] ?? 'front';
                $hasBoth = $product->front_back_amount !== null && $product->front_back_amount !== '' && (float)$product->front_back_amount > 0;
                $effectiveSide = ($printSide === 'both' && $hasBoth) ? 'both' : 'front';
                $selectedGsm = $this->resolveGsmOption($product, $item['gsm'] ?? null);
                $gsmCharge = (float) ($selectedGsm['extra_price'] ?? 0);

                // Base Cost per pack = Product Base Price (amount or front_back_amount) * printCopy
                $productPrice = ($effectiveSide === 'both') ? $product->front_back_amount : $product->amount;
                $baseCost = ((float)$productPrice + $gsmCharge) * $printCopy;
                
                $discountPercent = 0.0;
                if (!empty($product->pricing_tiers) && is_iterable($product->pricing_tiers)) {
                    foreach ($product->pricing_tiers as $tier) {
                        $tierSide = $tier['print_side'] ?? 'front';
                        if ($tierSide !== $effectiveSide) continue;

                        $min = (int)($tier['min'] ?? 0);
                        $max = isset($tier['max']) && $tier['max'] !== '' && $tier['max'] !== null ? (int)$tier['max'] : null;
                        if ($printCopy >= $min && ($max === null || $printCopy <= $max)) {
                            $discountPercent = (float)($tier['discount'] ?? 0);
                            break;
                        }
                    }
                }
                
                // Apply discount to base cost to get unit price per set/pack
                $unitPrice = round($baseCost * (1 - $discountPercent / 100));
                
                $lineTotal = $unitPrice * $packs;
                $subtotal += $lineTotal;
                $lines[] = [
                    'product' => $product,
                    'packs' => $packs,
                    'printCopy' => $printCopy,
                    'printSide' => $effectiveSide,
                    'gsm' => $selectedGsm['label'] ?? null,
                    'gsmPrice' => $gsmCharge,
                    'unitPrice' => $unitPrice,
                    'lineTotal' => $lineTotal,
                    'index' => $index
                ];
            }
            if ((float)$dealer->wallet_balance < $subtotal) {
                throw ValidationException::withMessages(['wallet'=>'Insufficient wallet balance. Please contact admin to refill wallet.']);
            }
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'dealer_id' => $dealer->id, 'status'=>'new',
                'subtotal'=>$subtotal, 'grand_total'=>$subtotal, 'customer_note'=>$request->input('customer_note'),
            ]);
            foreach ($lines as $line) {
                $file = $request->file('files.'.$line['index']);
                $path = $file ? $file->store('design-files/'.$order->id, 'public') : null;
                OrderItem::create([
                    'order_id'=>$order->id,'product_id'=>$line['product']->id,'product_name'=>$line['product']->name,'category'=>$line['product']->category,
                    'print_copy'=>$line['printCopy'],'print_side'=>$line['printSide'],'gsm'=>$line['gsm'],'gsm_price'=>$line['gsmPrice'],'packs'=>$line['packs'],'unit_price'=>$line['unitPrice'],'line_total'=>$line['lineTotal'],
                    'file_path'=>$path,'original_filename'=>$file?->getClientOriginalName(),
                ]);
            }
            $dealer->wallet_balance = (float)$dealer->wallet_balance - $subtotal; $dealer->save();
            WalletTransaction::create(['user_id'=>$dealer->id,'order_id'=>$order->id,'type'=>'debit','amount'=>$subtotal,'balance_after'=>$dealer->wallet_balance,'description'=>'Wallet payment for order '.$order->order_number]);
            return $order->load('items');
        });

        PortalNotificationService::notifyAdminsAndStaff([
            'type' => 'order_placed',
            'module' => 'b2b',
            'title' => 'New dealer order placed',
            'message' => "{$result->order_number} was placed by {$request->user()->company_name}.",
            'related_model' => Order::class,
            'related_id' => $result->id,
            'related_order_number' => $result->order_number,
        ]);

        return response()->json(['message'=>'Order placed successfully using wallet.', 'order'=>$result], 201);
    }

    public function receipt(Request $request, Order $order)
    {
        $user = $request->user();
        if ($user->role === 'dealer') {
            if ($order->dealer_id !== $user->id || !$order->receipt_shared) {
                abort(403, 'Unauthorized. This receipt is not shared yet.');
            }
        }
        
        $order->load(['dealer', 'items', 'extraCharges']);
        return view('receipt', compact('order'));
    }

    public function myWalletTransactions(Request $request): JsonResponse
    {
        return response()->json(
            WalletTransaction::where('user_id', $request->user()->id)->with('order.items')->latest()->get()
        );
    }

    public function download(Request $request, OrderItem $item)
    {
        $user = $request->user();
        $order = $item->order;
        
        if ($user->role === 'dealer') {
            if ($order->dealer_id !== $user->id) {
                abort(403, 'Unauthorized.');
            }
        } elseif ($user->role === 'staff') {
            if ($order->assigned_staff_id !== null && $order->assigned_staff_id !== $user->id) {
                abort(403, 'Unauthorized.');
            }
        }
        
        if (empty($item->file_path) || !Storage::disk('public')->exists($item->file_path)) {
            abort(404, 'File not found.');
        }
        
        return Storage::disk('public')->download($item->file_path, $item->original_filename);
    }

    private function resolveGsmOption(Product $product, ?string $gsm): ?array
    {
        if (empty($gsm) || empty($product->gsm_options) || !is_array($product->gsm_options)) {
            return null;
        }
        foreach ($product->gsm_options as $option) {
            if (is_string($option)) {
                $label = trim($option);
                $extraPrice = 0.0;
            } elseif (is_array($option)) {
                $label = trim((string)($option['label'] ?? ''));
                $extraPrice = (float)($option['extra_price'] ?? 0.0);
            } else {
                continue;
            }
            if ($label === trim($gsm)) {
                return [
                    'label' => $label,
                    'extra_price' => $extraPrice
                ];
            }
        }
        return null;
    }

    private function generateOrderNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "B2B-{$year}-";

        $lastOrder = Order::query()
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
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
