<?php
namespace App\Http\Controllers;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Product::query()->where('is_b2b', true)->where('is_active', true)->orderBy('category')->orderBy('sort_order')->orderBy('name')->get());
    }
    public function adminIndex(): JsonResponse
    {
        return response()->json(Product::query()->where('is_b2b', true)->orderBy('category')->orderBy('sort_order')->orderBy('name')->get());
    }
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $tiers = $validated['pricing_tiers'] ?? [];
        unset($validated['pricing_tiers']);
        
        $product = Product::create([...$validated, 'is_b2b' => true, 'is_b2c' => false]);
        foreach ($tiers as $tier) {
            $product->discountTiers()->create([
                'print_side' => $tier['print_side'] ?? 'front',
                'min' => (int)($tier['min'] ?? 0),
                'max' => isset($tier['max']) && $tier['max'] !== '' && $tier['max'] !== null ? (int)$tier['max'] : null,
                'discount' => (float)($tier['discount'] ?? 0),
            ]);
        }
        return response()->json($product->fresh(), 201);
    }
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $this->validated($request);
        $tiers = $validated['pricing_tiers'] ?? [];
        unset($validated['pricing_tiers']);
        
        $product->update($validated);
        $product->discountTiers()->delete();
        foreach ($tiers as $tier) {
            $product->discountTiers()->create([
                'print_side' => $tier['print_side'] ?? 'front',
                'min' => (int)($tier['min'] ?? 0),
                'max' => isset($tier['max']) && $tier['max'] !== '' && $tier['max'] !== null ? (int)$tier['max'] : null,
                'discount' => (float)($tier['discount'] ?? 0),
            ]);
        }
        return response()->json($product->fresh());
    }
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();
        return response()->json(['message'=>'Product deleted successfully.']);
    }
    public function categories(): JsonResponse
    {
        return response()->json(\App\Models\Category::where('is_b2b', true)->orderBy('name')->get());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100']
        ]);

        $name = trim($request->input('name'));

        // Case-insensitive existence check
        $exists = \App\Models\Category::where('is_b2b', true)->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists();
        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'This category name has already been taken (case-insensitive duplicate).'
            ]);
        }

        $category = \App\Models\Category::create(['name' => $name, 'is_b2b' => true, 'is_b2c' => false]);
        return response()->json($category, 201);
    }

    public function destroyCategory(\App\Models\Category $category): JsonResponse
    {
        $hasProducts = Product::where('is_b2b', true)->where('category', $category->name)->exists();
        if ($hasProducts) {
            return response()->json([
                'message' => 'Cannot delete category because it contains products. Please delete or reassign the products first.'
            ], 422);
        }
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully.']);
    }

    public function b2cIndex(): JsonResponse
    {
        return response()->json(Product::query()->where('is_b2c', true)->where('is_active', true)->orderBy('category')->orderBy('sort_order')->orderBy('name')->get());
    }

    public function b2cAdminIndex(): JsonResponse
    {
        return response()->json(Product::query()->where('is_b2c', true)->orderBy('category')->orderBy('sort_order')->orderBy('name')->get());
    }

    public function storeB2CProduct(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $tiers = $validated['pricing_tiers'] ?? [];
        unset($validated['pricing_tiers']);
        
        $product = Product::create([...$validated, 'is_b2b' => false, 'is_b2c' => true]);
        foreach ($tiers as $tier) {
            $product->discountTiers()->create([
                'print_side' => $tier['print_side'] ?? 'front',
                'min' => (int)($tier['min'] ?? 0),
                'max' => isset($tier['max']) && $tier['max'] !== '' && $tier['max'] !== null ? (int)$tier['max'] : null,
                'discount' => (float)($tier['discount'] ?? 0),
            ]);
        }
        return response()->json($product->fresh(), 201);
    }

    public function updateB2CProduct(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->is_b2c, 422, 'Product is not a B2C product.');
        $validated = $this->validated($request);
        $tiers = $validated['pricing_tiers'] ?? [];
        unset($validated['pricing_tiers']);
        
        $product->update($validated);
        $product->discountTiers()->delete();
        foreach ($tiers as $tier) {
            $product->discountTiers()->create([
                'print_side' => $tier['print_side'] ?? 'front',
                'min' => (int)($tier['min'] ?? 0),
                'max' => isset($tier['max']) && $tier['max'] !== '' && $tier['max'] !== null ? (int)$tier['max'] : null,
                'discount' => (float)($tier['discount'] ?? 0),
            ]);
        }
        return response()->json($product->fresh());
    }

    public function destroyB2CProduct(Product $product): JsonResponse
    {
        abort_unless($product->is_b2c, 422, 'Product is not a B2C product.');
        $product->delete();
        return response()->json(['message'=>'B2C Product deleted successfully.']);
    }

    public function b2cCategories(): JsonResponse
    {
        return response()->json(\App\Models\Category::where('is_b2c', true)->orderBy('name')->get());
    }

    public function storeB2CCategory(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100']
        ]);

        $name = trim($request->input('name'));

        // Case-insensitive existence check for B2C
        $exists = \App\Models\Category::where('is_b2c', true)->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists();
        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'This B2C category name has already been taken.'
            ]);
        }

        $category = \App\Models\Category::create(['name' => $name, 'is_b2b' => false, 'is_b2c' => true]);
        return response()->json($category, 201);
    }

    public function destroyB2CCategory(\App\Models\Category $category): JsonResponse
    {
        abort_unless($category->is_b2c, 422, 'Category is not a B2C category.');
        $hasProducts = Product::where('is_b2c', true)->where('category', $category->name)->exists();
        if ($hasProducts) {
            return response()->json([
                'message' => 'Cannot delete B2C category because it contains products.'
            ], 422);
        }
        $category->delete();
        return response()->json(['message' => 'B2C Category deleted successfully.']);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'category'=>['required','string','exists:categories,name'], 'name'=>['required','string','max:255'],
            'print_copy'=>['required','integer','min:1'], 'amount'=>['required','numeric','min:0'],
            'front_back_amount'=>['nullable','numeric','min:0'],
            'gsm_options' => ['nullable', 'array'],
            'gsm_options.*.label' => ['nullable', 'string', 'max:50'],
            'gsm_options.*.extra_price' => ['nullable', 'numeric', 'min:0'],
            'pricing_tiers'=>['nullable','array'],
            'is_active'=>['sometimes','boolean'], 'sort_order'=>['nullable','integer','min:0'],
        ]);

        $validated['gsm_options'] = collect($validated['gsm_options'] ?? [])
            ->map(function ($option) {
                $label = trim((string) ($option['label'] ?? ''));
                if ($label === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'extra_price' => (float) ($option['extra_price'] ?? 0),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $validated;
    }
}
