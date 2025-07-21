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

    public function is_banned($email)
    {
        $user = DB::table('users')
            ->where('email', $email)
            ->where('is_banned', true)
            ->first();

        return $user ? true : false;
    }

    public function login(array $credentials)
    {

        $is_banned = $this->is_banned($credentials['email']);
        if ($is_banned) {
            return [
                'success' => false,
                'message' => 'Your account is banned. Please contact for support from Contact Us Page.'
            ];
        }

        $user = User::where('email', $credentials['email'])->first();
        $password = DB::table('users')
            ->where('email', $credentials['email'])
            ->value('password');
    
        if (!$user || !Hash::check($credentials['password'], $password)) {
            return [
                'success' => false,
                'message' => 'Invalid credentials.'
            ];
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $tokenValue = strpos($token, '|') !== false ? explode('|', $token, 2)[1] : $token;

        $userInfo = $this->currentUser($user->user_id);

        return [
            'success' => true,
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
                'u.is_banned',
                'u.created_at',
                'u.updated_at',
                DB::raw("CONCAT('" . env('R2_URL') . "/', pp.photo_path) as profile_image_url")
            )
            ->first();

        return $user;
    }
    
    public function alreadyExistsProfilePhoto(string $id)
    {
        $photoPath = DB::table('users as u')
            ->leftJoin('photo_paths as pp', 'u.photo_path_id', '=', 'pp.photo_path_id')
            ->where('u.user_id', $id)
            ->whereNotNull('pp.photo_path')
            ->value('pp.photo_path'); 

        return $photoPath ? $photoPath : null;
    }

    public function profileImageUpload($file)
    {
        $id = Auth::user()->user_id;

        $exists = $this->alreadyExistsProfilePhoto($id);
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

    public function deleteProfileImage()
    {
        $id = Auth::user()->user_id;
        $exists = $this->alreadyExistsProfilePhoto($id);
        if ($exists) {
            $delete = $this->fileService->deleteFile($exists);
            if ($delete) {
                $currentPathId = DB::table('users')
                    ->where('user_id', $id)
                    ->value('photo_path_id');

                if ($currentPathId) {
                    DB::table('photo_paths')
                        ->where('photo_path_id', $currentPathId)
                        ->delete();
                }

                DB::table('users')
                    ->where('user_id', $id)
                    ->update(['photo_path_id' => null, 'updated_at' => now()]);

                return [
                    'success' => true,
                    'message' => 'Profile image deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to delete profile image'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'No profile image found to delete'
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

    public function getAllUsers($type)
    {
        $users = DB::table('users as u')
            ->where('u.user_type_id', '=', $type) 
            ->leftJoin('photo_paths as pp', 'u.photo_path_id', '=', 'pp.photo_path_id')
            ->select(
                'u.user_id',
                'u.user_type_id',
                'u.name',
                'u.phone',
                'u.email',
                'u.address',
                'u.is_banned',
                'u.created_at',
                'u.updated_at',
                DB::raw("CONCAT('" . env('R2_URL') . "/', pp.photo_path) as profile_image_url")
            )
            ->get();

        return $users;
    }

    public function banUser($id)
    {
        $user = DB::table('users')
            ->where('user_id', $id)
            ->first();

        if (!$user) {
            return null; 
        }
        else {
            DB::table('users')
                ->where('user_id', $id)
                ->update(['is_banned' => true, 'updated_at' => now()]);

            return $this->currentUser($id);
        }
    }
}
