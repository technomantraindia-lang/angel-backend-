<?php

namespace App\Http\Controllers;

use App\Models\SiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class B2CPolicyController extends Controller
{
    private const POLICY_KEY = 'b2c_printing_policy';

    private const DEFAULT_TITLE = 'Printing Policy';

    private const DEFAULT_CONTENT = "Please review this printing policy before placing your order.\n\nAll print jobs move into production only after artwork, quantity, and order details are confirmed.\n\nColor, texture, and finish may vary slightly between screen preview and final printed material.\n\nCustomers should upload clear artwork files and verify names, phone numbers, address details, spellings, and other custom text before submitting an order.\n\nOnce production starts, major content or design changes may require extra time or extra charges.\n\nFor help with your order, please contact our team before final approval.";

    public function show(): JsonResponse
    {
        return response()->json($this->policyRecord());
    }

    public function adminShow(): JsonResponse
    {
        return response()->json($this->policyRecord());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:50000'],
        ]);

        $policy = $this->policyRecord();
        $policy->update($data);

        return response()->json([
            'message' => 'B2C printing policy updated successfully.',
            'policy' => $policy->fresh(),
        ]);
    }

    private function policyRecord(): SiteContent
    {
        return SiteContent::query()->firstOrCreate(
            ['key' => self::POLICY_KEY],
            [
                'title' => self::DEFAULT_TITLE,
                'content' => self::DEFAULT_CONTENT,
            ]
        );
    }
}
