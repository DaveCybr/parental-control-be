<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:parents,email',
            'password' => 'required|min:6',
        ]);

        $parent = ParentModel::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'family_code' => strtoupper(Str::random(6)),
        ]);

        $token = $parent->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'parent' => $parent,
                'token' => $token,
            ],
            'message' => 'Registration successful',
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $parent = ParentModel::where('email', $request->email)->first();

        if (!$parent || !Hash::check($request->password, $parent->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $parent->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'parent' => $parent,
                'token' => $token,
            ],
            'message' => 'Login successful',
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ], 200);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
        ], 200);
    }

    /**
     * Update parent FCM token - BARU
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string|min:50',
        ]);

        $parent = $request->user();
        $parent->updateFcmToken($request->fcm_token);

        return response()->json([
            'success' => true,
            'message' => 'FCM token updated successfully',
        ]);
    }
}
