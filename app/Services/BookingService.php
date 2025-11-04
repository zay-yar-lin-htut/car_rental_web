<?php
namespace App\Services;

use App\Services\CommonService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\OfficeLocationService;

class BookingService
{
    protected $commonService;
    protected $carService;
    protected $officeLocationService;

    public function __construct(CommonService $commonService, CarService $carService, OfficeLocationService $officeLocationService)
    {
        $this->commonService = $commonService;
        $this->carService = $carService;
        $this->officeLocationService = $officeLocationService;
    }

    public function getAllBookings($data, $user)
    {
        $query = DB::table('bookings as b')
            ->leftJoin('users as u', 'b.user_id', '=', 'u.user_id')
            ->leftJoin('cars as c', 'b.car_id', '=', 'c.car_id')
            ->leftJoin('car_type as ct', 'c.car_type_id', '=', 'ct.car_type_id')
            ->leftJoin('office_locations as ol', 'c.office_location_id', '=', 'ol.office_location_id')
            ->select(
                'b.booking_id',
                'b.ticket_number',
                'b.user_id',
                'u.name as customer_name',
                'u.phone as customer_phone',
                'b.car_id',
                'c.model as car_model',
                'c.license_plate',
                'ct.type_name as car_type',
                'ol.location_name as office',
                'b.pickup_datetime',
                'b.dropoff_datetime',
                'b.pickup_latitude',
                'b.pickup_longitude',
                'b.dropoff_latitude',
                'b.dropoff_longitude',
                'b.total_amount',
                'b.cancellation_fine',
                'b.no_show_fine',
                'b.booking_status',
                'b.deliver_need',
                'b.take_back_need',
                'b.created_at',
                'b.updated_at'
            );

        if ($user->user_type_id != 3) {
            $query->where(function ($q) use ($user) {
                $q->where('b.deliver_need', 1)
                ->orWhere('b.take_back_need', 1)
                ->orWhere('b.complete_by', $user->user_id);
            });
        }

        $baseQuery = clone $query;

        if (!empty($data['search_by'])) {
            $searchTerm = '%' . $data['search_by'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('b.ticket_number', 'LIKE', $searchTerm)
                ->orWhere('u.name', 'LIKE', $searchTerm)
                ->orWhere('c.model', 'LIKE', $searchTerm);
            });
            $baseQuery->where(function ($q) use ($searchTerm) {
                $q->where('b.ticket_number', 'LIKE', $searchTerm)
                ->orWhere('u.name', 'LIKE', $searchTerm)
                ->orWhere('c.model', 'LIKE', $searchTerm);
            });
        }

        if (!empty($data['filter_by'])) {
            if (in_array($data['filter_by'], ['pending', 'confirmed', 'cancelled', 'completed'])) {
                $query->where('b.booking_status', $data['filter_by']);
                $baseQuery->where('b.booking_status', $data['filter_by']);
            } elseif ($data['filter_by'] == 'needs_delivery') {
                $query->where('b.deliver_need', 1);
                $baseQuery->where('b.deliver_need', 1);
            } elseif ($data['filter_by'] == 'needs_takeback') {
                $query->where('b.take_back_need', 1);
                $baseQuery->where('b.take_back_need', 1);
            }
        }

        $totalBookings = $baseQuery->count();
        $totalAmount = $baseQuery->sum('b.total_amount');

        $page = max(1, (int)$data['first']);
        $max = max(1, (int)$data['max']);
        $offset = ($page - 1) * $max;

        $bookings = $query->offset($offset)->limit($max)->orderByDesc('b.created_at')->get();

        $bookings->transform(function ($booking) {
            $booking->total_amount = (float)$booking->total_amount;
            $booking->cancellation_fine = (float)$booking->cancellation_fine;
            $booking->no_show_fine = (float)$booking->no_show_fine;
            return $booking;
        });

        return [
            'bookings' => $bookings,
            'total_bookings' => $totalBookings,
            'total_amount' => (float)$totalAmount,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $max,
                'from' => $offset + 1,
                'to' => min($offset + $max, $totalBookings),
                'last_page' => ceil($totalBookings / $max)
            ]
        ];
    }

    public function getBookingsByUser()
    {
        $id = Auth::user()->user_id;
        $bookings = DB::table('bookings')->where('user_id', $id)->get();
        if (!$bookings) {
            return "No bookings found.";
        }
        return $bookings;
    }

    public function createBooking($data)
    {
        $pickupLocationData = [
            'latitude' => $data['pickup_latitude'],
            'longitude' => $data['pickup_longitude']
        ];
        $dropoffLocationData = [
            'latitude' => $data['dropoff_latitude'],
            'longitude' => $data['dropoff_longitude']
        ];
        $data['deliver_need'] = $this->officeLocationService->isOfficeLocation($pickupLocationData);
        $data['take_back_need'] = $this->officeLocationService->isOfficeLocation($dropoffLocationData);
        $data['ticket_number'] = $this->commonService->getTicketNumber();
        $data['user_id'] = Auth::user()->user_id;
        $data['booking_status'] = 'pending';
        $response1 = DB::table('cars')->where('car_id', $data['car_id'])->update(['availability' => false]);
        if (!$response1) {
            return "This car is not available. Please select another car.";
        }
        else {
            $response = DB::table('bookings')->insert($data);
            if (!$response) {
                return "Failed to create booking.";
            } else {
                return null;
            }
        }
    }

    public function updateBooking($data)
    {
        (isset($data['car_id'])) ? $data['car_id'] : $data['car_id'] = null;
        $response = DB::table('bookings')->where('booking_id', $data['booking_id'])->update($data);
        if (!$response) {
            return "Failed to update booking.";
        } else {
            if (isset($data['car_id'])) {
                $response = DB::table('cars')->where('car_id', $data['car_id'])->update(['availability' => true]);
                if (!$response) {
                    return "Failed to update car availability.";
                }
            }
            return null;
        }
    }

    public function getCustomerPickupBookings()
    {
        $bookings = DB::table('bookings')
            ->where('deliver_need', true)
            ->where('booking_status', 'pending')
            ->get();
        if (!$bookings) {
            return "No customer pickup bookings found.";
        }
        return $bookings;
    }

    public function cancelBooking($id, $user_id)
    {
        $booking = DB::table('bookings')
            ->where('booking_id', $id)
            ->where('user_id', $user_id)
            ->select('booking_status', 'car_id', 'user_id')
            ->first();

        if (!$booking) {
            return "Booking not found";
        }

        $status = $booking->booking_status;

        if ($status == 'pending' || $status == 'confirmed') {

            $response = DB::table('bookings')
                ->where('booking_id', $id)
                ->where('user_id', $user_id) 
                ->update(['booking_status' => 'cancelled']);

            if (!$response) {
                return "Failed to cancel booking.";
            }

            $car_id = $booking->car_id;
            $car_update_response = DB::table('cars')
                ->where('car_id', $car_id)
                ->update(['availability' => true]);

            if (!$car_update_response) {
                return "Booking cancelled, but failed to update car availability.";
            }
            
            if ($status == 'confirmed') { 
                $user_update_response = DB::table('users')
                    ->where('user_id', $booking->user_id)
                    ->increment('cancellation_count');

                if (!$user_update_response) {
                    return "Booking cancelled, but failed to update user's cancellation count.";
                }
            }
            
            return null;
        } 
        else {
            return "Only pending or confirmed bookings can be cancelled.";
        }
    }
}