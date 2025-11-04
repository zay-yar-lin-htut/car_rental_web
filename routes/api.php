<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\OfficeLocationController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\TestingController;

Route::get('/proxy-image', [ImageController::class, 'proxyImage']);
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

// Car Types routes
Route::get('/car-types', [CarController::class, 'carTypes']);

// Office Location routes
Route::get('/office-locations', [OfficeLocationController::class, 'getOfficeLocations']);

// Contact Us routes
Route::post('/contact-us-create', [ContactUsController::class, 'createContactUs']);

Route::middleware('auth:sanctum')->group(function ()
{
    // User routes
    Route::middleware('user_type:1')->prefix('/user')->group(function (){
        
    });

    // Staff routes
    Route::middleware('user_type:2')->prefix('/staff')->group(function (){
        // Route::get('/users', [UserController::class, 'getAllUsers']);
    });
    
    // Admin routes
    Route::middleware('user_type:3')->prefix('/admin')->group(function (){
        // User routes
        Route::get('/user-list', [UserController::class, 'userList']);
        Route::get('/ban-user/{id}', [UserController::class, 'banAndUnbanUser']);
        Route::post('/admin-register', [UserController::class, 'registerAdmin']);
        Route::get('/password-reset/{id}', [UserController::class, 'passwordReset']);

        // Car routes
        Route::post('/car-create', [CarController::class, 'addCar']);
        Route::post('/car-update/{id}', [CarController::class, 'updateCar']);
        Route::delete('/car-delete/{id}', [CarController::class, 'deleteCar']);

        // Car Type routes
        Route::post('/car-type-create', [CarController::class, 'createCarType']);
        Route::post('/car-type-update/{id}', [CarController::class, 'updateCarType']);
        Route::delete('/car-type-delete/{id}', [CarController::class, 'deleteCarType']);

        // Booking routes
        Route::get('/bookings', [BookingController::class, 'getBookings']);

        // Office Location routes
        Route::post('/office-location-create', [OfficeLocationController::class, 'createOfficeLocation']);
        Route::post('/office-location-update/{id}', [OfficeLocationController::class, 'updateOfficeLocation']);
        Route::delete('/office-location-delete/{id}', [OfficeLocationController::class, 'deleteOfficeLocation']);

        // Owner routes
        Route::get('/owners', [OwnerController::class, 'getOwners']);
        Route::post('/owner-create', [OwnerController::class, 'createOwner']);
        Route::post('/owner-update/{id}', [OwnerController::class, 'updateOwner']);
        Route::delete('/owner-delete/{id}', [OwnerController::class, 'deleteOwner']);

        // Contact Us routes
        Route::get('/contact-us', [ContactUsController::class, 'getContactUs']);
    });
    
    // User routes
    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/logout', [UserController::class, 'logout']);
    Route::post('/upload&update-profile-image', [UserController::class, 'profileImageRequest']);
    Route::delete('/delete-profile-image', [UserController::class, 'deleteProfileImage']);
    Route::put('/update-profile', [UserController::class, 'updateUser']);
    Route::get('/is-have-fines', [UserController::class, 'isHaveFines']);

    // Car routes 
    Route::get('/cars', [CarController::class, 'getCars']);

    // Car type routes

    // Booking routes
    Route::get('/bookings/user', [BookingController::class, 'getBookingByUser']);
    Route::post('/booking-create', [BookingController::class, 'createBooking']);
    Route::get('/booking-cancel/{id}', [BookingController::class, 'cancelBooking']);
        
    // Office Location routes
});

Route::get('/mail', [TestingController::class, 'mail']);


//// testing route
