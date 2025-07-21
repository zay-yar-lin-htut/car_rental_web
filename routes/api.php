<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CarController;
use App\Helpers\Helper;

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
        Route::get('/user-list/{type}', [UserController::class, 'userList']);
        Route::patch('/ban-user/{id}', [UserController::class, 'banAndUnbanUser']);

        // Car routes
        Route::get('/cars-add', [CarController::class, 'addCar']);
        Route::get('/cars-list', [CarController::class, 'listCars']);
        Route::get('/cars-edit/{id}', [CarController::class, 'editCar']);
        Route::post('/cars-create', [CarController::class, 'createCar']);
        Route::put('/cars-update/{id}', [CarController::class, 'updateCar']);
        Route::delete('/cars-delete/{id}', [CarController::class, 'deleteCar']);
        Route::get('/cars-search', [CarController::class, 'searchCars']);
        Route::get('/cars-filter', [CarController::class, 'filterCars']);

        // Car Type routes
        Route::get('/car-types', [CarController::class, 'carTypes']);
        Route::get('/car-type/{id}', [CarController::class, 'carTypeById']);   
        Route::post('/car-type-create', [CarController::class, 'createCarType']);
        Route::post('/car-type-update/{id}', [CarController::class, 'updateCarType']);
        Route::delete('/car-type-delete/{id}', [CarController::class, 'deleteCarType']);
    });

    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/logout', [UserController::class, 'logout']);
    Route::post('/upload&update-profile-image', [UserController::class, 'profileImageRequest']);
    Route::delete('/delete-profile-image', [UserController::class, 'deleteProfileImage']);
    Route::put('/update-profile', [UserController::class, 'updateUser']);

    // Route::post('/email/verification-notification', [UserController::class, 'sendVerificationEmail']);
    // Route::get('/verify-email/{id}/{hash}', [UserController::class, 'verify'])->name('verification.verify');
});

Route::get('/testing', [CarController::class, 'deleteCarType']);
Route::get('/list-file', [UserController::class, 'listFiles']);

//// testing route
