<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('/user')->group(function (){
        Route::get('/profile', [UserController::class, 'currentUser']);
        Route::get('/logout', [UserController::class, 'logout']);
    });

    // Route::post('/email/verification-notification', [UserController::class, 'sendVerificationEmail']);
    // Route::get('/verify-email/{id}/{hash}', [UserController::class, 'verify'])->name('verification.verify');
});