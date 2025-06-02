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
        ]);

        try {
            $response = $this->userService->register($data);

            return response()->json([
                $response,
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
        $user_id=Auth::user()->user_id;
        return response()->json([$this->userService->currentUser($user_id)], 200);
    }

    public function logout()
    {
        Auth::user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    // public function uploadFile($photo)
    // {
    //     $file = $photo->file('image');
    //     $path = 'ProfileImages/';
    //     if (!$file->isValid()) {
    //         return response()->json(['message' => 'Invalid file upload'], 400);
    //     }

    //     $result = $this->fileService->uploadImage($file, $path);    

    //     return response()->json(['url' => $result], 200);
    // }

    public function updateUser(Request $request)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|regex:/^\+?[1-9]\d{9,14}$/',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . Auth::user()->user_id . ',user_id',
            'address' => 'sometimes|string|max:255',
            'image' => 'sometimes|file|max:10240',
        ]);

        try {
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $photoPath = $this->userService->profileImageUpload($file); // ✅ directly call service
                $data['photo_path'] = $photoPath;
            }

            // Update the user
            $this->userService->updateUser($data);

            // Just return updated user info using currentUser()
            return $this->currentUser();
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function profileImageUpload(Request $request)
    {
        $file_path = $this->userService->profileImageUpload($request->file('image'));
        return $file_path;
    }
}