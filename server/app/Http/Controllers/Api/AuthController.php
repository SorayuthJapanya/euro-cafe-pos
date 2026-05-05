<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Login Controller
    public function login(LoginRequest $request): JsonResponse
    {
        // Search User
        $user = User::where('email', $request->email)->first();


        // If didn't have user or invalid credentails
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentails'
            ], 401);
        }

        // Create token
        $token = $user->createToken('pos-terminal')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successfully',
            'data' => [
                'token' => $token,
                'user' => $user->only('id', 'name', 'email', 'role')
            ]
        ], 200);
    }

    // Logout Controller
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }

    // Get me function
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }
}
