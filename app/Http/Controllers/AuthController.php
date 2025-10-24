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
        $name = trim($request->string('name')->toString());
        $password = (string)$request->input('password', '');

        if ($name === '') {
            return response()->json([
                'message' => 'Bitte gib einen Namen ein.',
                'errors' => ['name' => ['Bitte gib einen Namen ein.']],
            ], 422);
        }

        if (mb_strlen($name) > 255) {
            return response()->json([
                'message' => 'Der Name ist zu lang.',
                'errors' => ['name' => ['Der Name darf höchstens 255 Zeichen haben.']],
            ], 422);
        }

        if (User::where('name', $name)->exists()) {
            return response()->json([
                'message' => 'Der Name ist bereits vergeben.',
                'errors' => ['name' => ['Dieser Name ist bereits vergeben.']],
            ], 422);
        }

        if (mb_strlen($password) < 6) {
            return response()->json([
                'message' => 'Das Passwort ist zu kurz.',
                'errors' => ['password' => ['Das Passwort muss mindestens 6 Zeichen lang sein.']],
            ], 422);
        }

        /** @var User $user */
        $user = User::create([
            'name' => $name,
            'email' => $this->placeholderEmail($name),
            'password' => $password,
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
        $name = trim($request->string('name')->toString());
        $password = (string)$request->input('password', '');

        if ($name === '' || $password === '') {
            return response()->json([
                'message' => 'Bitte gib Name und Passwort ein.',
            ], 422);
        }

        if (!Auth::attempt(['name' => $name, 'password' => $password], $request->boolean('remember'))) {
            return response()->json([
                'message' => 'Name oder Passwort ist falsch.',
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
