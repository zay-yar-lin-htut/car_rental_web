<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\FileService;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    protected $userService;
    protected $fileService;

    public function __construct(UserService $userService, FileService $fileService)
    {
        $this->userService = $userService;
        $this->fileService = $fileService;
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^\+?[1-9]\d{9,14}$/',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'user_profile_img' => 'nullable|image|max:2048', // 2MB max
        ]);

        try {
            // if ($request->hasFile('user_profile_img')) {
            //     $response_2 = $this->fileService->uploadToCloudflareR2(
            //         $request->file('user_profile_img'),
            //         'Images'
            //     );
            // }

            $response = $this->userService->register($data);

            return response()->json([
                $response,
                // 'profile_image_url' => $response_2 ?? null,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $result = $this->userService->login($request->only('email', 'password'));

        if (!$result) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([$result], 200);
    }

    public function currentUser()
    {
        return response()->json(Auth::user(), 200);
    }

    public function logout()
    {
        Auth::user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}
