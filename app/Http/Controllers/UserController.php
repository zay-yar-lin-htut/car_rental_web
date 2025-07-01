<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\FileService;
use Illuminate\Support\Facades\Auth;
use App\Helpers\Helper;

class UserController extends Controller
{
    protected $userService;
    protected $fileService;
    protected $helper;

    public function __construct(UserService $userService, FileService $fileService, Helper $helper)
    {
        $this->userService = $userService;
        $this->fileService = $fileService;
        $this->helper = $helper;
    }

    public function register(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^\d{9,14}$/',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ];

        $validate = $this->helper->Validate($request, $rules);
        if(is_null($validate))
        {
            $data = $request->all();
            $response = $this->userService->register($data);
                
            if(is_null($response))
            {
                return $this->helper->PostMan(null, 201, "User Account Successfully Created");
            }
            else
            {
                return $this->helper->PostMan(null, 500, $response);
            }
        }
        else
        {
            return $this->helper->PostMan(null, 422, $validate);
        }
    }

    public function login(Request $request)
    {
        $rules=[
            'email' => 'required|email',
            'password' => 'required'
        ];
        $validate = $this->helper->Validate($request, $rules);
        if (is_null($validate))
        {
            $result = $this->userService->login($request->only('email', 'password'));

            if (!$result) {
                return $this->helper->PostMan(null, 401, "Invlid Credential");
            }

            return $this->helper->PostMan($result, 200, "Successfully Logined");
        }
        else
        {
            return $this->helper->PostMan(null, 422, $validate);
        }
    }

    public function currentUser()
    {
        $user_id=Auth::user()->user_id;
        return $this->userService->currentUser($user_id);
    }

    public function profile()
    {
        return $this->helper->PostMan($this->currentUser(), 200, "Successfully Updated User");
    }

    public function logout()
    {
        Auth::user()->currentAccessToken()->delete();
        return $this->helper->PostMan(null, 200, "Logged out successfully");
    }

    public function updateUser(Request $request)
    {
        $rules = [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|regex:/^\d{9,14}$/',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . Auth::user()->user_id . ',user_id',
            'address' => 'sometimes|string|max:255',
        ];

        $validate = $this->helper->Validate($request, $rules);
        if (is_null($validate))
        {
            try {
                $data = $request->all();
                $this->userService->updateUser($data);

                return $this->helper->PostMan($this->currentUser(), 200, "Successfully Updated User");
            } catch (\Exception $e) {
                return $this->helper->PostMan(null, 400, $e->getMessage());
            }
        }
        else
        {
            return $this->helper->PostMan(null, 422, $validate);
        }
    }

    public function profileImageRequest(Request $request)
    {
        $rules = [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        $validate = $this->helper->Validate($request, $rules);
        if (is_null($validate)) {
            $file_path = $this->userService->profileImageUpload($request->file('image'));
            if ($file_path['success']) {
                return $this->helper->PostMan(env('R2_URL')."/".$file_path['message'], 200, "Profile image uploaded successfully");
            } else {
                return $this->helper->PostMan(null, 500, $file_path['message']);
            }
        } else {
            return $this->helper->PostMan(null, 422, $validate);
        }
    }
}