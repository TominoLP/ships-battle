<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:users,name'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $name = trim($data['name']);

        /** @var User $user */
        $user = User::create([
            'name' => $name,
            'email' => $this->placeholderEmail($name),
            'password' => $data['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => $user,
            'csrfToken' => $request->session()->token(),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'name' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $name = trim($credentials['name']);

        if (!Auth::attempt(['name' => $name, 'password' => $credentials['password']], $request->boolean('remember'))) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => $request->user(),
            'csrfToken' => $request->session()->token(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'csrfToken' => $request->session()->token(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    private function placeholderEmail(string $name): string
    {
        $slug = Str::slug($name) ?: 'player';
        $base = $slug . '@players.local';
        $email = $base;
        $suffix = 1;

        while (User::where('email', $email)->exists()) {
            $email = $slug . '+' . $suffix . '@players.local';
            $suffix++;
        }

        return $email;
    }
}
