<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class B2CAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $customer = Customer::create([
            ...$data,
            'is_active' => true,
        ]);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $customer
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string']
        ]);

        if (!Auth::guard('customer')->attempt($credentials, (bool) $request->boolean('remember'))) {
            return response()->json(['message' => 'Invalid email or password.'], 422);
        }

        $request->session()->regenerate();
        $customer = Auth::guard('customer')->user();

        if (!$customer || !$customer->is_active) {
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            return response()->json(['message' => 'Your account is currently disabled. Please contact support.'], 403);
        }

        return response()->json(['user' => $customer]);
    }

    public function me(): JsonResponse
    {
        return response()->json(['user' => Auth::guard('customer')->user()]);
    }

    public function adminSession(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, ['admin', 'staff'], true)) {
            return response()->json(['user' => null]);
        }

        return response()->json(['user' => $user]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $customer = Customer::query()
            ->where('email', $data['email'])
            ->where('phone', $data['phone'])
            ->first();

        if (!$customer) {
            return response()->json(['message' => 'No customer account found with this email and phone number.'], 422);
        }

        $customer->password = Hash::make($data['password']);
        $customer->save();

        return response()->json(['message' => 'Password has been reset successfully. You can now login.']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        abort_unless($customer, 401);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
        ]);

        $customer->update($data);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $customer->fresh(),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        abort_unless($customer, 401);

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($data['current_password'], $customer->password)) {
            return response()->json(['message' => 'The current password you entered is incorrect.'], 422);
        }

        $customer->password = Hash::make($data['password']);
        $customer->save();

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }
}
