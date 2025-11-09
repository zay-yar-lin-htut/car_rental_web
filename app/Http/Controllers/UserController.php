<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\FileService;
use Illuminate\Support\Facades\Auth;
use App\Helpers\Helper;
use App\Services\CommonService;

class UserController extends Controller
{
    protected $userService;
    protected $fileService;
    protected $helper;
    protected $commonService;

    public function __construct(UserService $userService, FileService $fileService, Helper $helper, CommonService $commonService)
    {
        $this->userService = $userService;
        $this->fileService = $fileService;
        $this->helper = $helper;
        $this->commonService = $commonService;
    }

    public function register(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];

        $validate = $this->helper->Validate($request, $rules);
        if(is_null($validate))
        {
            $data = $request->all();
            $data['user_type_id'] = 1;
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

    public function registerAdmin(Request $request)
    {
        $rules = [
            'user_type_id' => 'required|integer|exists:user_type,user_type_id',
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'email' => 'required|string|email|max:255|unique:users,email',
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

            if ($result['success'] === false) {
                return $this->helper->PostMan(null, 400, $result['message']);
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
            'phone' => 'sometimes|string|regex:/^\+?\d{9,14}$/',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . Auth::user()->user_id . ',user_id',
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
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
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

    public function deleteProfileImage()
    {
        $is_delete = $this->userService->deleteProfileImage();
        if ($is_delete['success']) {
            return $this->helper->PostMan(null, 200, $is_delete['message']);
        } else {
            return $this->helper->PostMan(null, 500, $is_delete['message']);
        }
    }

    public function userList(Request $request) 
    {
        $rule = [
            'search_by' => 'nullable|string|max:255',
            'first' => 'required|integer|min:1',
            'max' => 'required|integer|min:1',
            'filter_by' => 'nullable|string|in:user,banned_user,active_user,admin,staff',
        ];

        $validate = $this->helper->validate($request, $rule); 
        if (is_null($validate)) {
            $data = $request->all();
            $response = $this->userService->userList($data);
            return $this->helper->PostMan($response, 200, "Users Retrieved Successfully");
        } else {
            return $this->helper->PostMan(null, 422, $validate);
        }
    }

    public function banAndUnbanUser($id)
    {
        $user = $this->userService->banAndUnUser($id);
        if(is_null($user))
        {
            return $this->helper->PostMan(null, 404, "User Not Found");
        }
        else{
            if(!$user)
            {
                return $this->helper->PostMan(null, 200, "User Unbanned Successfully");
            }
            else
            {
                return $this->helper->PostMan(null, 200, "User Banned Successfully");
            }
        }
    }

    public function passwordReset($id)
    {
        $user = $this->userService->passwordReset($id);
        if(is_null($user))
        {            
            return $this->helper->PostMan(null, 200, "Password Reset Successfully");
        }
        else
        {
            return $this->helper->PostMan(null, 500, $user);
        }
    }

    public function isHaveFines()
    {   
        $user_id = Auth::user()->user_id;
        $hasFines = $this->userService->isHaveFines($user_id);

        return $this->helper->PostMan($hasFines, 200, "Fines Status Retrieved Successfully");
    }
}