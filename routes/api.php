<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\OfficeLocationController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TestingController;
use App\Services\BookingService;
use App\Http\Controllers\UserPreferenceLocationController;
use App\Services\FileService;

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
        // Review routes
        Route::post('/review-create', [ReviewController::class, 'submitReview']);
        Route::get('/my-bookings', [BookingController::class, 'getBookingByUser']);

        // User Preference Location routes
        Route::post('/preference-location', [UserPreferenceLocationController::class, 'store']);
        Route::get('/preference-locations', [UserPreferenceLocationController::class, 'index']);
    });

    // Staff routes
    Route::middleware('user_type:2')->prefix('/staff')->group(function (){
        // Booking routes
        Route::get('/today-deliveries', [BookingController::class, 'getTodayDeliveries']);
        Route::get('/today-takebacks', [BookingController::class, 'getTodayTakeBacks']);

        Route::get('/today-self-pickups', [BookingController::class, 'getTodaySelfPickups']);
        Route::get('/today-self-dropoffs', [BookingController::class, 'getTodaySelfDropoffs']);

        // Complete Self Pickup/Dropoff
        Route::post('/complete-self-pickup', [BookingController::class, 'completeSelfPickup']);
        Route::post('/complete-self-dropoff', [BookingController::class, 'completeSelfDropoff']);

        // No-show Booking routes
        Route::post('/no-show-delivery', [BookingController::class, 'noShowDelivery']);
        Route::post('/no-show-pickup', [BookingController::class, 'noShowSelfPickup']);

        // Task routes
        Route::get('/claim-delivery/{booking_id}', [BookingController::class, 'claimDelivery']);
        Route::get('/claim-takeback/{booking_id}', [BookingController::class, 'claimTakeback']);
        Route::get('/my-active-tasks', [BookingController::class, 'myActiveTasks']);
        Route::get('/complete-takeback/{booking_id}', [BookingController::class, 'completeTakeback']);
        Route::post('/complete-delivery/{task_id}', [BookingController::class, 'completeDelivery']);
        Route::get('/task-history', [BookingController::class, 'staffTaskHistory']);

        // Maintenance routes
        Route::get('/maintenance-tasks', [BookingController::class, 'getMaintenanceTasks']);
        Route::get('/complete-maintenance/{maintenance_id}', [BookingController::class, 'completeMaintenance']);
        Route::post('/report-damage', [BookingController::class, 'reportDamage']);

        // Is Staff have task
        Route::get('/is-have-task', [BookingController::class, 'doTheStaffEarly']);

        // Cost By Ticket Number
        Route::get('/cost-by-ticket/{ticket_number}', [BookingController::class, 'costByTicketNumber']);

        // Contact Us routes
        Route::get('/contact-us', [ContactUsController::class, 'getContactUsStaff']);
        Route::get('/resolve-contact-us/{contactId}', [ContactUsController::class, 'resolveContactUs']);
    });
    
    // Admin routes
    Route::middleware('user_type:3')->prefix('/admin')->group(function (){
        // Dashboard routes
        Route::get('/revenue-dashboard', [BookingController::class, 'adminRevenueDashboard']);

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
        Route::get('/contact-us', [ContactUsController::class, 'getContactUsAdmin']);
        Route::get('/resolve-contact-us/{contactId}', [ContactUsController::class, 'resolveContactUs']);
        Route::post('/assign-contact-us', [ContactUsController::class, 'assignContactUs']);

        // Review routes
        Route::get('/reviews', [ReviewController::class, 'getAdminReviews']);
    });
    
    // User routes
    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/logout', [UserController::class, 'logout']);
    Route::post('/upload&update-profile-image', [UserController::class, 'profileImageRequest']);
    Route::delete('/delete-profile-image', [UserController::class, 'deleteProfileImage']);
    Route::put('/update-profile', [UserController::class, 'updateUser']);
    Route::post('/change-password', [UserController::class, 'changePassword']);
    Route::get('/is-have-fines', [UserController::class, 'isHaveFines']);

    // Car routes 
    Route::get('/cars', [CarController::class, 'getCars']);

    // Car type routes

    // Booking routes
    Route::post('/booking-create', [BookingController::class, 'createBooking']);
    Route::get('/booking-cancel/{id}', [BookingController::class, 'cancelBooking']);
        
    // Office Location routes
});

Route::get('/mail', [TestingController::class, 'mail']);
Route::get('/test', [BookingService::class, 'getResponsibleOffice']);


Route::post('/social-media-file-upload', [FileService::class, 'social_media_file_upload']);
Route::post('/social-media-file-delete', [FileService::class, 'social_media_file_delete']);
//// testing route
