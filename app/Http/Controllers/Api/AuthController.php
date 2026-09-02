<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Handle an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'], // Sent by mobile apps (e.g. Flutter)
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        // Mobile Handshake: Issue PlainText Token
        if ($request->filled('device_name')) {
            $token = $user->createToken((string) $request->input('device_name'))->plainTextToken;

            return response()->json([
                'data' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]);
        }

        // Web Handshake: Stateful Session Login
        Auth::login($user, $request->boolean('remember'));

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'data' => $user,
            'message' => 'Authenticated successfully.',
        ]);
    }

    /**
     * Destroy an authenticated session or revoke Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } else {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Mobile Handshake: Issue PlainText Token
        if ($request->filled('device_name')) {
            $token = $user->createToken((string) $request->input('device_name'))->plainTextToken;

            return response()->json([
                'data' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]);
        }

        // Web Handshake: Stateful Session Login
        Auth::login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'data' => $user,
            'message' => 'Registered successfully.',
        ], 201);
    }
}
