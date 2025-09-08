<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $data = $r->validate([
            'email' => ['required','email'],
            'password' => ['required'],
        ]);

        $user = User::where('email',$data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas'], 422);
        }

        $token = $user->createToken('web')->plainTextToken;
        return response()->json(['token' => $token, 'user' => $user]);
    }

    public function register(Request $r)
    {
        $data = $r->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','unique:users,email'],
            'password' => ['required','confirmed', Password::min(6)],
            'role' => ['nullable','string'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $r->input('role'),
        ]);

        $token = $user->createToken('web')->plainTextToken;
        return response()->json(['token' => $token, 'user' => $user], 201);
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }
}
