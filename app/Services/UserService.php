<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function register(array $data)
    {
        $user = User::create([
            'user_id' => (string) Str::uuid(), // Generate a UUID
            'user_type_id' => 1, // Assuming a default user type ID
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // event(new Registered($user));

        return [
            'message' => 'Account created successfully.',
            'user' => $user
        ];
    }

    public function login(array $credentials)
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $tokenValue = strpos($token, '|') !== false ? explode('|', $token, 2)[1] : $token;

        return [
            'token' => $tokenValue,
            'user' => $user
        ];
    }
}
