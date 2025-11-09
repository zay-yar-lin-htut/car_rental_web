<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BookingService;
use App\Helpers\Helper;
use App\Services\CommonService;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    protected $bookingService;
    protected $helper;
    protected $commonService;

    public function __construct(BookingService $bookingService, Helper $helper, CommonService $commonService)
    {
        $this->bookingService = $bookingService;
        $this->helper = $helper;
        $this->commonService = $commonService;
    }

    public function getBookings(Request $request)
    {
        $rules = [
            'search_by'     => 'nullable|string|max:255',
            'first'         => 'required|integer|min:1',
            'max'           => 'required|integer|min:1',
            'filter_by'     => 'nullable|string|in:pending,confirmed,cancelled,completed,needs_delivery,needs_takeback',
            'status'        => 'nullable|string|in:pending,confirmed,cancelled,completed',
            'delivery'      => 'nullable|in:0,1',
            'takeback'      => 'nullable|in:0,1',
            'office_id'     => 'nullable|integer|exists:office_locations,office_location_id',
            'car_type_id'   => 'nullable|integer|exists:car_type,car_type_id',
            'date_from'     => 'nullable|date',
            'date_to'       => 'nullable|date|after_or_equal:date_from',
            'sort_by'       => 'nullable|string|in:created_at,pickup_datetime,total_amount,average_rating',
            'sort'          => 'nullable|string|in:asc,desc'
        ];

        $validate = $this->helper->validate($request, $rules);
        if (is_null($validate)) {
            $data = $request->all();
            $user = $request->user();

            if (!in_array($user->user_type_id, [2, 3])) {
                return $this->helper->PostMan(null, 403, "Forbidden");
            }

            $response = $this->bookingService->getAllBookings($data, $user);
            return $this->helper->PostMan($response, 200, "Bookings retrieved successfully");
        } else {
            return $this->helper->PostMan(null, 422, $validate);
        }
    }

    public function getBookingByUser()
    {
        $bookings = $this->bookingService->getBookingsByUser();
        return $this->helper->PostMan($bookings, 200, "User Bookings Retrieved Successfully");
    }

    public function createBooking(Request $request)
    {
        $pickup_datetime = $this->commonService->timeStampTypeCaster($request->pickup_date, $request->pickup_time);
        $dropoff_datetime = $this->commonService->timeStampTypeCaster($request->dropoff_date, $request->dropoff_time);
        $request->merge(['pickup_datetime' => $pickup_datetime, 'dropoff_datetime' => $dropoff_datetime]);

        $rules = [
            'car_id' => 'required|integer|exists:cars,car_id',
            'pickup_datetime' => 'required|date|after_or_equal:' . now()->addHours(24)->format('Y-m-d H:i:s'),
            'dropoff_datetime' => 'required|date|after:pickup_datetime',
            'pickup_latitude' => 'required|numeric',
            'pickup_longitude' => 'required|numeric',
            'dropoff_latitude' => 'required|numeric',
            'dropoff_longitude' => 'required|numeric',
            'total_amount' => 'required|numeric|min:0',
            'cancellation_fine' => 'nullable|numeric|min:0',
            'no_show_fine' => 'nullable|numeric|min:0'
        ];

        $validate = $this->helper->validate($request, $rules);
        if (is_null($validate)) {
            $data = $request->only([
                'car_id', 'pickup_datetime', 'dropoff_datetime',
                'pickup_latitude', 'pickup_longitude',
                'dropoff_latitude', 'dropoff_longitude',
                'total_amount', 'cancellation_fine', 'no_show_fine'
            ]);

            $response = $this->bookingService->createBooking($data);
            if (is_null($response)) {
                return $this->helper->PostMan(null, 201, "Booking Successfully Created");
            } else {
                return $this->helper->PostMan(null, 400, $response);
            }
        } else {
            return $this->helper->PostMan(null, 422, $validate);
        }
    }

    public function getCustomerPickupBookings()
    {
        $bookings = $this->bookingService->getCustomerPickupBookings();
        return $this->helper->PostMan($bookings, 200, "Customer Pickup Bookings Retrieved Successfully");
    }

    public function cancelBooking($id)
    {
        $user_id = Auth::user()->user_id;
        $response = $this->bookingService->cancelBooking($id, $user_id);
        if (is_null($response)) {
            return $this->helper->PostMan(null, 200, "Booking Successfully Cancelled");
        } else {
            return $this->helper->PostMan(null, 400, $response);
        }
    }

    public function getTodayDeliveries(Request $request)
    {
        $rules = [
            'office_id' => 'required|integer|exists:office_locations,office_location_id'
        ];
        $validate = $this->helper->validate($request, $rules);

        if (!is_null($validate)) {
            return $this->helper->PostMan(null, 422, $validate);
        }

        $deliveries = $this->bookingService->getCustomerPickupBookings($request->office_id);
        return $this->helper->PostMan($deliveries, 200, "Today's deliveries retrieved");
    }

    public function getTodayTakeBack(Request $request)
    {
        $rules = [
            'office_id' => 'required|integer|exists:office_locations,office_location_id'
        ];
        $validate = $this->helper->validate($request, $rules);

        if (!is_null($validate)) {
            return $this->helper->PostMan(null, 422, $validate);
        }

        $takebacks = $this->bookingService->getCustomerTakebackBookings($request->office_id);
        return $this->helper->PostMan($takebacks, 200, "Today's take-backs retrieved");
    }
}