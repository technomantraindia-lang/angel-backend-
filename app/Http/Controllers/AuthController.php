<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'company_name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'phone' => ['required','string','max:30'],
            'address' => ['required','string','max:1000'],
            'pincode' => ['required','string','regex:/^[0-9]{6}$/'],
            'gst_number' => ['nullable','string','max:30'],
            'password' => ['required','confirmed', Password::min(8)],
        ]);
        $user = User::create([...$data, 'role' => 'dealer', 'approval_status' => 'pending', 'wallet_balance' => 0]);
        return response()->json(['message' => 'Registration submitted. Admin approval is required before login.', 'dealer_id' => $user->id], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email'=>['required','email'], 'password'=>['required','string']]);
        if (!Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            return response()->json(['message'=>'Invalid email or password.'], 422);
        }
        $request->session()->regenerate();
        $user = $request->user();
        if ($user->role === 'dealer' && $user->approval_status !== 'approved') {
            Auth::logout(); $request->session()->invalidate();
            if (in_array($user->approval_status, ['hold', 'rejected', 'banned'])) {
                $message = 'Your account is on hold. You cannot login. Please contact administrator.';
            } else {
                $message = 'Your dealer registration is pending approval.';
            }
            return response()->json(['message'=>$message], 403);
        }
        return response()->json(['user'=>$user]);
    }

    public function me(Request $request): JsonResponse { return response()->json(['user'=>$request->user()]); }
    public function logout(Request $request): JsonResponse
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return response()->json(['message'=>'Logged out.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'The current password you entered is incorrect.'], 422);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        return response()->json(['message' => 'Password reset successful.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'phone' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::where('email', $data['email'])->where('phone', $data['phone'])->first();

        if (!$user) {
            return response()->json(['message' => 'No account found matching this email and phone number.'], 422);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        return response()->json(['message' => 'Password has been successfully reset. You can now login.']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'pincode' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'gst_number' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Profile details updated successfully.',
            'user' => $user
        ]);
    }
}
