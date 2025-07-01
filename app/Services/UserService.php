<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\FileService;

class UserService
{
    protected $fileService;
    
    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    public function register(array $data)
    {
        try
        {
            User::create([
                'user_id' => (string) Str::uuid(), 
                'user_type_id' => 1, 
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // event(new Registered($user));

            return null;
        }
        catch (\Exception $e) 
        {
            return $e->getMessage();
        }
    }

    public function login(array $credentials)
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $tokenValue = strpos($token, '|') !== false ? explode('|', $token, 2)[1] : $token;

        $userInfo = $this->currentUser($user->user_id);

        return [
            'token' => $tokenValue,
            'user' => $userInfo
        ];
    }

    public function currentUser($id)
    {
        $user = DB::table('users as u')
            ->leftJoin('photo_paths as pp', 'u.photo_path_id', '=', 'pp.photo_path_id')
            ->where('u.user_id', $id)
            ->select(
                'u.user_id',
                'u.user_type_id',
                'u.name',
                'u.phone',
                'u.email',
                'u.address',
                'u.email_verified_at',
                'u.remember_token',
                'u.created_at',
                'u.updated_at',
                DB::raw("CONCAT('" . env('R2_URL') . "/', pp.photo_path) as profile_image_url")
            )
            ->first();

        return $user;
    }
    
    public function alreadyExistsPhoto(string $id)
    {
        $photoPath = DB::table('users as u')
            ->leftJoin('photo_paths as pp', 'u.photo_path_id', '=', 'pp.photo_path_id')
            ->where('u.user_id', $id)
            ->whereNotNull('pp.photo_path')
            ->value('pp.photo_path'); // select only one value

        return $photoPath ? $photoPath : null;
    }

    public function profileImageUpload($file)
    {
        $id = Auth::user()->user_id;

        $exists = $this->alreadyExistsPhoto($id);
        if ($exists) {
            $delete = $this->fileService->deleteFile($exists);
            if (!$delete) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete existing photo'
                ];
            }
            $upload = $this->fileService->uploadFile($file, 'ProfileImages/');
            if (!$upload) {
                return [
                    'success' => false,
                    'message' => 'Failed to upload photo'
                ];
            }
            $this->profileImageDBupdate($upload);
            return [
                    'success' => true,
                    'message' => $upload
                ];
        }
        else{
            $upload = $this->fileService->uploadFile($file, 'ProfileImages/');
            if (!$upload) {
                return [
                    'success' => false,
                    'message' => 'Failed to upload photo'
                ];
            }
            $this->profileImageDBupdate($upload);
            return [
                    'success' => true,
                    'message' => $upload
                ];
        }
    }

    public function profileImageDBupdate($key)
    {
        $userId = Auth::user()->user_id;
        $currentPathId = DB::table('users')
            ->where('user_id', $userId)
            ->value('photo_path_id');

        if ($currentPathId) {
            DB::table('photo_paths')
                ->where('photo_path_id', $currentPathId)
                ->update(['photo_path' => $key, 'updated_at' => now()]);
        } else {
            $newPathId = DB::table('photo_paths')
                ->insertGetId([
                'photo_path' => $key,
                'created_at' => now(),
                'updated_at' => now()]);

            DB::table('users')
                ->where('user_id', $userId)
                ->update(['photo_path_id' => $newPathId]);
        }
    }

    public function updateUser($data)
    {
        $userId = Auth::user()->user_id;
        $user = User::where('user_id', $userId)->first();

        if (!$user) {
            throw new \Exception('User not found.');
        }

        // Update fields
        $user->fill($data);
        $user->save();

        $userInfo = $this->currentUser($userId);
        return $userInfo;
    }
}
