<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserType
{
    public function handle(Request $request, Closure $next, $type)
    {
        if (Auth::check() && Auth::user()->user_type_id == $type) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized.'], 403);
    }
}

