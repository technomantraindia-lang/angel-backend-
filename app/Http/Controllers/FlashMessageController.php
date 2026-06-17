<?php

namespace App\Http\Controllers;

use App\Models\SiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FlashMessageController extends Controller
{
    public function getAdminSettings(): JsonResponse
    {
        $dealerText = SiteContent::where('key', 'dealer_flash_text')->first()?->content ?? '';
        $dealerImage = SiteContent::where('key', 'dealer_flash_image')->first()?->content ?? '';
        $dealerActive = SiteContent::where('key', 'dealer_flash_active')->first()?->content ?? '0';

        $customerText = SiteContent::where('key', 'customer_flash_text')->first()?->content ?? '';
        $customerImage = SiteContent::where('key', 'customer_flash_image')->first()?->content ?? '';
        $customerActive = SiteContent::where('key', 'customer_flash_active')->first()?->content ?? '0';

        return response()->json([
            'dealer_flash_text' => $dealerText,
            'dealer_flash_image' => $dealerImage ? asset('storage/' . $dealerImage) : null,
            'dealer_flash_image_raw' => $dealerImage,
            'dealer_flash_active' => $dealerActive === '1',

            'customer_flash_text' => $customerText,
            'customer_flash_image' => $customerImage ? asset('storage/' . $customerImage) : null,
            'customer_flash_image_raw' => $customerImage,
            'customer_flash_active' => $customerActive === '1',
        ]);
    }

    public function updateAdminSettings(Request $request): JsonResponse
    {
        $request->validate([
            'dealer_flash_text' => ['nullable', 'string'],
            'dealer_flash_active' => ['required', 'string', 'in:0,1'],
            'customer_flash_text' => ['nullable', 'string'],
            'customer_flash_active' => ['required', 'string', 'in:0,1'],
            'dealer_flash_image_file' => ['nullable', 'image', 'max:5120'],
            'customer_flash_image_file' => ['nullable', 'image', 'max:5120'],
            'clear_dealer_image' => ['nullable', 'string'],
            'clear_customer_image' => ['nullable', 'string'],
        ]);

        // Dealer text & active state
        SiteContent::updateOrCreate(
            ['key' => 'dealer_flash_text'],
            ['content' => $request->input('dealer_flash_text', '')]
        );
        SiteContent::updateOrCreate(
            ['key' => 'dealer_flash_active'],
            ['content' => $request->input('dealer_flash_active')]
        );

        // Customer text & active state
        SiteContent::updateOrCreate(
            ['key' => 'customer_flash_text'],
            ['content' => $request->input('customer_flash_text', '')]
        );
        SiteContent::updateOrCreate(
            ['key' => 'customer_flash_active'],
            ['content' => $request->input('customer_flash_active')]
        );

        // Handle dealer image file upload
        if ($request->hasFile('dealer_flash_image_file')) {
            $file = $request->file('dealer_flash_image_file');
            $path = $file->store('flash-messages', 'public');
            SiteContent::updateOrCreate(
                ['key' => 'dealer_flash_image'],
                ['content' => $path]
            );
        } elseif ($request->input('clear_dealer_image') === '1') {
            $existing = SiteContent::where('key', 'dealer_flash_image')->first();
            if ($existing && $existing->content) {
                Storage::disk('public')->delete($existing->content);
                $existing->update(['content' => '']);
            }
        }

        // Handle customer image file upload
        if ($request->hasFile('customer_flash_image_file')) {
            $file = $request->file('customer_flash_image_file');
            $path = $file->store('flash-messages', 'public');
            SiteContent::updateOrCreate(
                ['key' => 'customer_flash_image'],
                ['content' => $path]
            );
        } elseif ($request->input('clear_customer_image') === '1') {
            $existing = SiteContent::where('key', 'customer_flash_image')->first();
            if ($existing && $existing->content) {
                Storage::disk('public')->delete($existing->content);
                $existing->update(['content' => '']);
            }
        }

        return response()->json([
            'message' => 'Flash message settings updated successfully.',
        ]);
    }

    public function getDealerFlash(): JsonResponse
    {
        $active = SiteContent::where('key', 'dealer_flash_active')->first()?->content ?? '0';
        if ($active !== '1') {
            return response()->json([
                'active' => false,
            ]);
        }

        $text = SiteContent::where('key', 'dealer_flash_text')->first()?->content ?? '';
        $image = SiteContent::where('key', 'dealer_flash_image')->first()?->content ?? '';

        return response()->json([
            'active' => true,
            'text' => $text,
            'image' => $image ? asset('storage/' . $image) : null,
        ]);
    }

    public function getCustomerFlash(): JsonResponse
    {
        $active = SiteContent::where('key', 'customer_flash_active')->first()?->content ?? '0';
        if ($active !== '1') {
            return response()->json([
                'active' => false,
            ]);
        }

        $text = SiteContent::where('key', 'customer_flash_text')->first()?->content ?? '';
        $image = SiteContent::where('key', 'customer_flash_image')->first()?->content ?? '';

        return response()->json([
            'active' => true,
            'text' => $text,
            'image' => $image ? asset('storage/' . $image) : null,
        ]);
    }
}
