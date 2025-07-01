<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Helpers\Helper;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () 
{
    // User routes
    Route::middleware('user_type:1')->prefix('/user')->group(function (){
        // Route::get('/current', [UserController::class, 'currentUser']);
    });

    // Staff routes
    Route::middleware('user_type:2')->prefix('/staff')->group(function (){
        // Route::get('/users', [UserController::class, 'getAllUsers']);
    });
    
    // Admin routes
    Route::middleware('user_type:3')->prefix('/admin')->group(function (){
        // Route::get('/users', [UserController::class, 'getAllUsers']);
    });

    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/logout', [UserController::class, 'logout']);
    Route::post('/upload&update-profile-image', [UserController::class, 'profileImageRequest']);
    Route::put('/update-profile', [UserController::class, 'updateUser']);

    Route::post('/email/verification-notification', [UserController::class, 'sendVerificationEmail']);
    Route::get('/verify-email/{id}/{hash}', [UserController::class, 'verify'])->name('verification.verify');
});

Route::get('/testing', [Helper::class, 'test']);
Route::get('/list-file', [UserController::class, 'listFiles']);

//// testing route
