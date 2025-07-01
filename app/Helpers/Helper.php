<?php

namespace App\Helpers;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Validator;

class Helper
{
    public function PostMan($data, int $status, $message) {
        if ($status>=400)
        {
            return $this->Error($status, $message);
        }
        else 
        {
            return $this->Success($data, $status, $message);
        }
    }

    public function Success($data, $status, $message) {
        return response()->json([
            'sucess' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    public function Error($status, $message) {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null
        ], $status);
    }

    public function validate($request, $rules) {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        return null;
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            $this->PostMan(null, 401, 'Invalid or missing token');
        }

        return redirect()->guest(route('login'));
    }
}