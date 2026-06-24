<?php

namespace App\Http\Controllers;

use App\Models\B2CCategory;
use App\Models\B2CProduct;
use App\Models\B2CProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class B2CProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = B2CProduct::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($products);
    }

    public function adminIndex(): JsonResponse
    {
        $products = B2CProduct::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($products);
    }

    public function categories(): JsonResponse
    {
        return response()->json(
            B2CCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }

    public function adminCategories(): JsonResponse
    {
        return response()->json(
            B2CCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $name = trim($data['name']);

        if (B2CCategory::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists()) {
            throw ValidationException::withMessages([
                'name' => 'This customer category already exists.',
            ]);
        }

        $category = B2CCategory::create([
            'name' => $name,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, B2CCategory $b2cCategory): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $name = trim($data['name']);

        if (strtolower($name) !== strtolower($b2cCategory->name)) {
            if (B2CCategory::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'This customer category already exists.',
                ]);
            }
        }

        $b2cCategory->update([
            'name' => $name,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : $b2cCategory->sort_order,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $b2cCategory->is_active,
        ]);

        return response()->json($b2cCategory);
    }

    public function destroyCategory(B2CCategory $b2cCategory): JsonResponse
    {
        if ($b2cCategory->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a category that still has products.',
            ], 422);
        }

        $b2cCategory->delete();

        return response()->json(['message' => 'Customer category deleted successfully.']);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProduct($request);

        $product = DB::transaction(function () use ($request, $validated) {
            $product = B2CProduct::create($validated);
            $this->syncProductMedia($request, $product);

            return $product->fresh();
        });

        return response()->json($product, 201);
    }

    public function update(Request $request, B2CProduct $b2cProduct): JsonResponse
    {
        $validated = $this->validateProduct($request, $b2cProduct);

        $product = DB::transaction(function () use ($request, $validated, $b2cProduct) {
            $b2cProduct->update($validated);
            $this->syncProductMedia($request, $b2cProduct);

            return $b2cProduct->fresh();
        });

        return response()->json($product);
    }

    public function destroy(B2CProduct $b2cProduct): JsonResponse
    {
        DB::transaction(function () use ($b2cProduct) {
            foreach ($b2cProduct->images as $image) {
                if ($image->getRawOriginal('file_path')) {
                    Storage::disk('public')->delete($image->getRawOriginal('file_path'));
                }
            }

            if ($b2cProduct->getRawOriginal('sample_pdf_path')) {
                Storage::disk('public')->delete($b2cProduct->getRawOriginal('sample_pdf_path'));
            }

            $b2cProduct->delete();
        });

        return response()->json(['message' => 'Customer product deleted successfully.']);
    }

    private function validateProduct(Request $request, ?B2CProduct $product = null): array
    {
        if ($request->has('front_back_amount') && trim((string) $request->input('front_back_amount')) === '') {
            $request->merge(['front_back_amount' => null]);
        }

        if ($request->filled('pricing_tiers_json')) {
            $decodedPricingTiers = json_decode((string) $request->input('pricing_tiers_json'), true);
            $request->merge([
                'pricing_tiers' => is_array($decodedPricingTiers) ? $decodedPricingTiers : null,
            ]);
        }

        $validated = $request->validate([
            'b2c_category_id' => ['required', 'exists:b2c_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'print_copy' => ['nullable', 'integer', 'min:1'],
            'quantity_step' => ['nullable', 'integer', 'min:1'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'front_back_amount' => ['nullable', 'numeric', 'min:0'],
            'print_side_mode' => ['required', 'in:front_only,front_back_only,both'],
            'pricing_tiers' => ['required', 'array', 'min:1'],
            'pricing_tiers.*.quantity' => ['required', 'integer', 'min:1'],
            'pricing_tiers.*.price' => ['required', 'numeric', 'min:0'],
            'pricing_tiers.*.front_back_price' => ['nullable', 'numeric', 'min:0'],
            'warning' => ['nullable', 'string', 'max:1000'],
            'allow_design_serial' => ['sometimes'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sample_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'removed_image_ids' => ['nullable', 'array'],
            'removed_image_ids.*' => ['integer'],
            'remove_sample_pdf' => ['nullable', 'boolean'],
        ]);

        $existingImages = $product ? $product->images()->count() : 0;
        $removedImages = collect($validated['removed_image_ids'] ?? [])->count();
        $newImages = count($request->file('images', []));
        $futureImageCount = ($product ? max(0, $existingImages - $removedImages) : 0) + $newImages;

        if ($futureImageCount < 1) {
            throw ValidationException::withMessages([
                'images' => 'At least one product image is required.',
            ]);
        }

        if ($futureImageCount > 5) {
            throw ValidationException::withMessages([
                'images' => 'You can upload up to 5 images per B2C product.',
            ]);
        }

        $pricingTiers = collect($validated['pricing_tiers'] ?? [])
            ->map(function (array $tier) {
                $frontBackPrice = $tier['front_back_price'] ?? null;
                $normalizedFrontBackPrice = $frontBackPrice === '' || $frontBackPrice === null
                    ? null
                    : round((float) $frontBackPrice, 2);

                return [
                    'quantity' => (int) $tier['quantity'],
                    'price' => round((float) $tier['price'], 2),
                    'front_back_price' => $normalizedFrontBackPrice,
                ];
            })
            ->sortBy('quantity')
            ->values();

        if ($pricingTiers->isEmpty()) {
            throw ValidationException::withMessages([
                'pricing_tiers' => 'Please add at least one quantity price row.',
            ]);
        }

        if ($pricingTiers->pluck('quantity')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'pricing_tiers' => 'Each quantity row must use a unique quantity.',
            ]);
        }

        if (in_array($validated['print_side_mode'], ['front_back_only', 'both'], true)) {
            $missingFrontBackTier = $pricingTiers->first(fn (array $tier) => is_null($tier['front_back_price']) || $tier['front_back_price'] <= 0);
            if ($missingFrontBackTier) {
                throw ValidationException::withMessages([
                    'pricing_tiers' => 'Please enter the Front & Back base price for every quantity row.',
                ]);
            }
        }

        if ($validated['print_side_mode'] === 'front_only') {
            $pricingTiers = $pricingTiers
                ->map(fn (array $tier) => [...$tier, 'front_back_price' => null])
                ->values();
        }

        $baseTier = $pricingTiers->first();
        $baseQuantity = max(1, (int) $baseTier['quantity']);
        $baseAmount = round(((float) $baseTier['price']) / $baseQuantity, 2);
        $baseFrontBackAmount = null;

        if (in_array($validated['print_side_mode'], ['front_back_only', 'both'], true) && !is_null($baseTier['front_back_price'])) {
            $baseFrontBackAmount = round(((float) $baseTier['front_back_price']) / $baseQuantity, 2);
        }

        $allowDesignSerial = filter_var($validated['allow_design_serial'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasExistingSamplePdf = $product && !$request->boolean('remove_sample_pdf') && filled($product->getRawOriginal('sample_pdf_path'));
        $hasIncomingSamplePdf = $request->hasFile('sample_pdf');

        if ($allowDesignSerial && !$hasExistingSamplePdf && !$hasIncomingSamplePdf) {
            throw ValidationException::withMessages([
                'sample_pdf' => 'Please upload a sample PDF before enabling the required design serial number option.',
            ]);
        }

        return [
            'b2c_category_id' => (int) $validated['b2c_category_id'],
            'name' => trim($validated['name']),
            'short_description' => isset($validated['short_description']) ? trim((string) $validated['short_description']) : null,
            'description' => $validated['description'] ?? null,
            'print_copy' => $baseQuantity,
            'quantity_step' => 1,
            'amount' => $baseAmount,
            'front_back_amount' => in_array($validated['print_side_mode'], ['front_back_only', 'both'], true)
                ? $baseFrontBackAmount
                : null,
            'print_side_mode' => $validated['print_side_mode'],
            'gsm_options' => [],
            'pricing_tiers' => $pricingTiers->all(),
            'warning' => isset($validated['warning']) ? trim($validated['warning']) : null,
            'allow_design_serial' => $allowDesignSerial,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => true,
        ];
    }

    private function syncProductMedia(Request $request, B2CProduct $product): void
    {
        $removedImageIds = collect($request->input('removed_image_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($removedImageIds->isNotEmpty()) {
            $images = $product->images()->whereIn('id', $removedImageIds)->get();

            foreach ($images as $image) {
                if ($image->getRawOriginal('file_path')) {
                    Storage::disk('public')->delete($image->getRawOriginal('file_path'));
                }
                $image->delete();
            }
        }

        if ($request->boolean('remove_sample_pdf') && $product->getRawOriginal('sample_pdf_path')) {
            Storage::disk('public')->delete($product->getRawOriginal('sample_pdf_path'));
            $product->update(['sample_pdf_path' => null]);
        }

        if ($request->hasFile('sample_pdf')) {
            if ($product->getRawOriginal('sample_pdf_path')) {
                Storage::disk('public')->delete($product->getRawOriginal('sample_pdf_path'));
            }

            $pdfPath = $request->file('sample_pdf')->store('b2c/products/pdfs', 'public');
            $product->update(['sample_pdf_path' => $pdfPath]);
        }

        if ($request->hasFile('images')) {
            $nextSortOrder = (int) ($product->images()->max('sort_order') ?? -1) + 1;

            foreach ($request->file('images') as $index => $imageFile) {
                $imagePath = $imageFile->store('b2c/products/images', 'public');

                $product->images()->create([
                    'file_path' => $imagePath,
                    'sort_order' => $nextSortOrder + $index,
                ]);
            }
        }

        $this->resequenceImages($product);
    }

    private function resequenceImages(B2CProduct $product): void
    {
        $images = $product->images()->orderBy('sort_order')->orderBy('id')->get();

        foreach ($images as $index => $image) {
            if ((int) $image->sort_order !== $index) {
                $image->update(['sort_order' => $index]);
            }
        }
    }
}
