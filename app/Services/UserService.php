<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\FileService;
use Illuminate\Support\Facades\Mail;
use App\Mail\Welcome;
use App\Mail\PasswordReset;

class UserService
{
    protected $fileService;
    protected $commonService;
    
    public function __construct(FileService $fileService, CommonService $commonService)
    {
        $this->fileService = $fileService;
        $this->commonService = $commonService;
    }

    public function register(array $data)
    {
        $response = User::create([
            'user_id' => (string) Str::uuid(), 
            'user_type_id' => $data['user_type_id'], 
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        // event(new Registered($user));
        if ($response)
        {
            // Mail::to($data['email'])->send(new Welcome($response));
            return null;
        }
        else
        {
            return "Failed to create user.";
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
                'u.no_show_count',
                'u.cancellation_count',
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

    public function changePassword($data)
    {
        $userId = Auth::user()->user_id;
        $user = User::where('user_id', $userId)->first();

        if (!$user) {
            return 'User not found.';
        }

        if (!Hash::check($data['current_password'], $user->password)) {
            return 'Current password is incorrect.';
        }

        $user->password = Hash::make($data['new_password']);
        $user->save();

        return null;
    }

    public function userList($data)
    {
        $query = DB::table('users as u')
            ->leftJoin('photo_paths as pp', 'u.photo_path_id', '=', 'pp.photo_path_id')
            ->leftJoin('user_type as ut', 'u.user_type_id', '=', 'ut.user_type_id')
            ->select(
                'u.user_id',
                'u.user_type_id',
                'ut.type_name',
                'u.name',
                'u.phone',
                'u.email',
                'u.no_show_count',
                'u.cancellation_count',
                'u.is_banned',
                'u.created_at',
                'u.updated_at',
                DB::raw("CONCAT('" . env('R2_URL') . "/', pp.photo_path) as profile_image_url")
            );

        // Search
        if (!empty($data['search_by'])) {
            $searchTerm = '%' . $data['search_by'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('u.name', 'LIKE', $searchTerm)
                ->orWhere('u.email', 'LIKE', $searchTerm)
                ->orWhere('u.phone', 'LIKE', $searchTerm);
            });
        }

        // Filter by role or ban status
        if (!empty($data['filter_by'])) {
            switch ($data['filter_by']) {
                case 'user':
                    $query->where('ut.type_name', 'user');
                    break;
                case 'admin':
                    $query->where('ut.type_name', 'admin');
                    break;
                case 'staff':
                    $query->where('ut.type_name', 'staff');
                    break;
                case 'banned_user':
                    $query->where('u.is_banned', true);
                    break;
                case 'active_user':
                    $query->where('u.is_banned', false);
                    break;
            }
        }

        // Total count (before pagination)
        $totalUsers = (clone $query)->count();

        // Pagination — safe defaults if null
        $page = max(1, (int)($data['first'] ?? 1));
        $perPage = min(100, max(1, (int)($data['max'] ?? 20))); // default 20
        $offset = ($page - 1) * $perPage;

        $users = $query->offset($offset)->limit($perPage)->get();

        return [
            'users'        => $users,
            'total_users'  => $totalUsers,
            'current_page' => $page,
            'per_page'     => $perPage,
            'total_pages'  => ceil($totalUsers / $perPage)
        ];
    }

    public function banAndUnUser($id)
    {
        $user = DB::table('users')
            ->where('user_id', $id)
            ->first();

        $is_banned = $this->is_banned($user->email);
        if (!$user) {
            return null; 
        }

        else {
            if ($is_banned) {
                DB::table('users')
                    ->where('user_id', $id)
                    ->update(['is_banned' => false, 'updated_at' => now()]);
                return false;
            }
            else {
                DB::table('users')
                    ->where('user_id', $id)
                    ->update(['is_banned' => true, 'updated_at' => now()]);
                return true;
            }
        }
    }

    public function getUserByID($id) {
        $user = DB::table('users as u')
            ->leftJoin('photo_paths as pp', 'u.photo_path_id', '=', 'pp.photo_path_id')
            ->where('u.user_id', $id)
            ->select(
                'u.user_id',
                'u.user_type_id',
                'u.name',
                'u.phone',
                'u.email',
                'u.no_show_count',
                'u.cancellation_count',
                'u.is_banned',
                'u.created_at',
                'u.updated_at',
                DB::raw("CONCAT('" . env('R2_URL') . "/', pp.photo_path) as profile_image_url")
            )
            ->first();
        if(!$user) {
            return null;
        }
        return $user;
    }

    public function passwordReset($id) {
        $password = $this->commonService->passwordGenerate();

        $user = $this->getUserByID($id);

        if(!$user) {
            return "User not found";
        }

        $update = DB::table('users')
            ->where('user_id', $id)
            ->update(['password' => Hash::make($password), 'updated_at' => now()]);
        
        if(!$update) {
            return "Failed to update password";
        }
        Mail::to($user->email)->send(new PasswordReset($user->name, $password));
        return null;
    }

    public function isHaveFines($user_id)
    {
        $data = DB::table('users')
            ->where('user_id', $user_id)
            ->first();

        $no_show = $data->no_show_count * 10000;
        $cancellation = $data->cancellation_count * 3000;

        return $data = [
            'No-show Fine' => $no_show,
            'Cancellation Fine' => $cancellation,
            'Total Fine' => $no_show + $cancellation
        ];
    }
}
