<?php

namespace App\Http\Controllers;

use App\Models\FlashMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FlashMessageController extends Controller
{
    public function getAdminSettings(): JsonResponse
    {
        $dealer = FlashMessage::firstOrCreate(['type' => 'dealer']);
        $customer = FlashMessage::firstOrCreate(['type' => 'customer']);

        return response()->json([
            'dealer_flash_text' => $dealer->text ?? '',
            'dealer_flash_image' => $dealer->image ? asset('storage/' . $dealer->image) : null,
            'dealer_flash_image_raw' => $dealer->image ?? '',
            'dealer_flash_active' => (bool)$dealer->active,

            'customer_flash_text' => $customer->text ?? '',
            'customer_flash_image' => $customer->image ? asset('storage/' . $customer->image) : null,
            'customer_flash_image_raw' => $customer->image ?? '',
            'customer_flash_active' => (bool)$customer->active,
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

        $dealer = FlashMessage::firstOrCreate(['type' => 'dealer']);
        $customer = FlashMessage::firstOrCreate(['type' => 'customer']);

        // Update dealer text & state
        $dealer->text = $request->input('dealer_flash_text', '');
        $dealer->active = $request->input('dealer_flash_active') === '1';

        // Update customer text & state
        $customer->text = $request->input('customer_flash_text', '');
        $customer->active = $request->input('customer_flash_active') === '1';

        // Handle dealer image file upload
        if ($request->hasFile('dealer_flash_image_file')) {
            // Delete old file if present
            if ($dealer->image) {
                Storage::disk('public')->delete($dealer->image);
            }
            $file = $request->file('dealer_flash_image_file');
            $dealer->image = $file->store('flash-messages', 'public');
        } elseif ($request->input('clear_dealer_image') === '1') {
            if ($dealer->image) {
                Storage::disk('public')->delete($dealer->image);
                $dealer->image = null;
            }
        }

        // Handle customer image file upload
        if ($request->hasFile('customer_flash_image_file')) {
            // Delete old file if present
            if ($customer->image) {
                Storage::disk('public')->delete($customer->image);
            }
            $file = $request->file('customer_flash_image_file');
            $customer->image = $file->store('flash-messages', 'public');
        } elseif ($request->input('clear_customer_image') === '1') {
            if ($customer->image) {
                Storage::disk('public')->delete($customer->image);
                $customer->image = null;
            }
        }

        $dealer->save();
        $customer->save();

        return response()->json([
            'message' => 'Flash message settings updated successfully.',
        ]);
    }

    public function getDealerFlash(): JsonResponse
    {
        $dealer = FlashMessage::where('type', 'dealer')->first();
        if (!$dealer || !$dealer->active) {
            return response()->json([
                'active' => false,
            ]);
        }

        return response()->json([
            'active' => true,
            'text' => $dealer->text ?? '',
            'image' => $dealer->image ? asset('storage/' . $dealer->image) : null,
        ]);
    }

    public function getCustomerFlash(): JsonResponse
    {
        $customer = FlashMessage::where('type', 'customer')->first();
        if (!$customer || !$customer->active) {
            return response()->json([
                'active' => false,
            ]);
        }

        return response()->json([
            'active' => true,
            'text' => $customer->text ?? '',
            'image' => $customer->image ? asset('storage/' . $customer->image) : null,
        ]);
    }
}
