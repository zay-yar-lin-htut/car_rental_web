<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\CommonService;
use Carbon\Carbon;

class BookingService
{
    protected $commonService;
    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }

    public function getAllBookings($data, $user)
    {
        $query = DB::table('bookings as b')
            ->leftJoin('users as u', 'b.user_id', '=', 'u.user_id')
            ->leftJoin('cars as c', 'b.car_id', '=', 'c.car_id')
            ->leftJoin('car_type as ct', 'c.car_type_id', '=', 'ct.car_type_id')
            ->leftJoin('office_locations as ol', 'c.office_location_id', '=', 'ol.office_location_id')
            ->leftJoin('reviews as r', 'b.booking_id', '=', 'r.booking_id')
            ->select(
                'b.booking_id', 'b.ticket_number', 'b.user_id', 'u.name as customer_name',
                'u.phone as customer_phone', 'b.car_id', 'c.model as car_model',
                'c.license_plate', 'ct.type_name as car_type', 'ol.location_name as office',
                'b.pickup_datetime', 'b.dropoff_datetime', 'b.pickup_latitude', 'b.pickup_longitude',
                'b.dropoff_latitude', 'b.dropoff_longitude', 'b.total_amount',
                'b.cancellation_fine', 'b.no_show_fine', 'b.booking_status',
                'b.deliver_need', 'b.take_back_need', 'b.created_at', 'b.updated_at',
                DB::raw('COALESCE(AVG(r.rating), 0) as average_rating'),
                DB::raw('COUNT(r.review_id) as review_count')
            )
            ->groupBy('b.booking_id');

        $countQuery = clone $query;

        // === STAFF FILTER: Only their office ===
        if ($user->user_type_id != 3) {
            $query->where(function ($q) use ($user) {
                $q->where('b.delivery_office_id', $user->office_location_id)
                ->orWhere('b.takeback_office_id', $user->office_location_id)
                ->orWhere('b.complete_by', $user->user_id);
            });
            $countQuery->where(function ($q) use ($user) {
                $q->where('b.delivery_office_id', $user->office_location_id)
                ->orWhere('b.takeback_office_id', $user->office_location_id)
                ->orWhere('b.complete_by', $user->user_id);
            });
        }

        // === SEARCH ===
        if (!empty($data['search_by'])) {
            $term = '%' . $data['search_by'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('b.ticket_number', 'LIKE', $term)
                ->orWhere('u.name', 'LIKE', $term)
                ->orWhere('c.model', 'LIKE', $term)
                ->orWhere('c.license_plate', 'LIKE', $term);
            });
            $countQuery->where(function ($q) use ($term) {
                $q->where('b.ticket_number', 'LIKE', $term)
                ->orWhere('u.name', 'LIKE', $term)
                ->orWhere('c.model', 'LIKE', $term)
                ->orWhere('c.license_plate', 'LIKE', $term);
            });
        }

        // === FILTERS ===
        if (!empty($data['status'])) {
            $query->where('b.booking_status', $data['status']);
            $countQuery->where('b.booking_status', $data['status']);
        }

        if (isset($data['delivery'])) {
            $query->where('b.deliver_need', $data['delivery']);
            $countQuery->where('b.deliver_need', $data['delivery']);
        }

        if (isset($data['takeback'])) {
            $query->where('b.take_back_need', $data['takeback']);
            $countQuery->where('b.take_back_need', $data['takeback']);
        }

        if (!empty($data['office_id'])) {
            $query->where(function ($q) use ($data) {
                $q->where('b.delivery_office_id', $data['office_id'])
                ->orWhere('b.takeback_office_id', $data['office_id']);
            });
            $countQuery->where(function ($q) use ($data) {
                $q->where('b.delivery_office_id', $data['office_id'])
                ->orWhere('b.takeback_office_id', $data['office_id']);
            });
        }

        if (!empty($data['car_type_id'])) {
            $query->where('c.car_type_id', $data['car_type_id']);
            $countQuery->where('c.car_type_id', $data['car_type_id']);
        }

        if (!empty($data['date_from'])) {
            $query->whereDate('b.pickup_datetime', '>=', $data['date_from']);
            $countQuery->whereDate('b.pickup_datetime', '>=', $data['date_from']);
        }

        if (!empty($data['date_to'])) {
            $query->whereDate('b.pickup_datetime', '<=', $data['date_to']);
            $countQuery->whereDate('b.pickup_datetime', '<=', $data['date_to']);
        }

        // === SORTING ===
        $sortBy = $data['sort_by'] ?? 'created_at';
        $sort   = in_array($data['sort'] ?? '', ['asc', 'desc']) ? $data['sort'] : 'desc';

        if ($sortBy === 'average_rating') {
            $query->orderByRaw("COALESCE(AVG(r.rating), 0) $sort");
        } else {
            $query->orderBy("b.$sortBy", $sort);
        }

        // === COUNTS ===
        $totalBookings = $countQuery->count();
        $totalAmount   = $countQuery->sum('b.total_amount');

        // === PAGINATION ===
        $page    = max(1, (int)($data['first'] ?? 1));
        $perPage = max(1, (int)($data['max'] ?? 10));
        $offset  = ($page - 1) * $perPage;

        $bookings = $query->offset($offset)->limit($perPage)->get();

        $bookings->transform(function ($b) {
            $b->total_amount = (float)$b->total_amount;
            $b->cancellation_fine = (float)$b->cancellation_fine;
            $b->no_show_fine = (float)$b->no_show_fine;
            $b->average_rating = round((float)$b->average_rating, 1);
            $b->review_count = (int)$b->review_count;
            return $b;
        });

        return [
            'bookings' => $bookings,
            'total_bookings' => $totalBookings,
            'total_amount' => (float)$totalAmount,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $totalBookings),
                'last_page' => ceil($totalBookings / $perPage)
            ]
        ];
    }

    public function getBookingsByUser()
    {
        $userId = Auth::id();

        $bookings = DB::table('bookings as b')
            ->leftJoin('reviews as r', function ($join) use ($userId) {
                $join->on('r.booking_id', '=', 'b.booking_id')
                    ->where('r.user_id', '=', $userId);
            })
            ->leftJoin('cars as c', 'b.car_id', '=', 'c.car_id')
            ->leftJoin('car_type as ct', 'c.car_type_id', '=', 'ct.car_type_id')
            ->leftJoin('photo_paths as pp', 'c.photo_path_id', '=', 'pp.photo_path_id')
            ->select(
                'b.*',
                'c.model',
                'c.license_plate',
                'ct.type_name',
                DB::raw('r.review_id'),                     // keep for has_reviewed
                DB::raw('IF(r.review_id IS NOT NULL, 1, 0) as has_reviewed'),
                DB::raw('COALESCE(AVG(r.rating), 0) as average_rating'),
                DB::raw("CONCAT('" . env('R2_URL') . "/', pp.photo_path) as car_image_url")
            )
            ->where('b.user_id', $userId)
            ->groupBy(
                'b.booking_id',
                'b.ticket_number',
                'b.user_id',
                'b.car_id',
                'b.pickup_datetime',
                'b.dropoff_datetime',
                'b.pickup_latitude',
                'b.pickup_longitude',
                'b.dropoff_latitude',
                'b.dropoff_longitude',
                'b.total_amount',
                'b.booking_status',
                'b.deliver_need',
                'b.take_back_need',
                'b.delivery_office_id',
                'b.takeback_office_id',
                'b.cancellation_fine',
                'b.no_show_fine',
                'b.created_at',
                'b.updated_at',
                'c.model',
                'c.license_plate',
                'ct.type_name',
                'r.review_id'
            )
            ->orderByDesc('b.created_at')
            ->get();

        return $bookings->isEmpty() ? [] : $bookings->toArray();
    }

    public function createBooking($data)
    {
        $data['ticket_number'] = $this->commonService->getTicketNumber();
        $data['user_id'] = Auth::user()->user_id;
        $data['booking_status'] = 'pending';

        // Lock car
        $carUpdated = DB::table('cars')
            ->where('car_id', $data['car_id'])
            ->where('availability', true)
            ->update(['availability' => false]);

        if (!$carUpdated) {
            return "This car is not available. Please select another car.";
        }

        // Assign delivery office
        $data['delivery_office_id'] = $this->getResponsibleOffice(
            $data['pickup_latitude'], $data['pickup_longitude'], 'can_deliver'
        );
        $data['deliver_need'] = !is_null($data['delivery_office_id']) ? 1 : 0;

        // Assign takeback office
        $data['takeback_office_id'] = $this->getResponsibleOffice(
            $data['dropoff_latitude'], $data['dropoff_longitude'], 'can_takeback'
        );
        $data['take_back_need'] = !is_null($data['takeback_office_id']) ? 1 : 0;

        $inserted = DB::table('bookings')->insert($data);
        return $inserted ? null : "Failed to create booking.";
    }

    private function getResponsibleOffice($lat, $lng, $capability = 'can_deliver')
    {
        return DB::table('office_service_areas as osa')
            ->select('osa.office_location_id')
            ->where($capability, 1)
            ->whereRaw(
                '(6371 * acos(
                    cos(radians(?)) * cos(radians(osa.latitude)) *
                    cos(radians(osa.longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(osa.latitude))
                )) <= osa.radius_km',
                [$lat, $lng, $lat]
            )
            ->orderByRaw(
                '(6371 * acos(
                    cos(radians(?)) * cos(radians(osa.latitude)) *
                    cos(radians(osa.longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(osa.latitude))
                ))',
                [$lat, $lng, $lat]
            )
            ->value('office_location_id');
    }

    public function cancelBooking($id, $user_id)
    {
        $booking = DB::table('bookings')
            ->where('booking_id', $id)
            ->where('user_id', $user_id)
            ->select('booking_status', 'car_id', 'user_id')
            ->first();

        if (!$booking) {
            return "Booking not found.";
        }

        if (!in_array($booking->booking_status, ['pending', 'confirmed'])) {
            return "Only pending or confirmed bookings can be cancelled.";
        }

        $updated = DB::table('bookings')
            ->where('booking_id', $id)
            ->update(['booking_status' => 'cancelled']);

        if (!$updated) {
            return "Failed to cancel booking.";
        }

        // Free car
        DB::table('cars')
            ->where('car_id', $booking->car_id)
            ->update(['availability' => true]);

        // Increment cancellation count if confirmed
        if ($booking->booking_status === 'confirmed') {
            DB::table('users')
                ->where('user_id', $booking->user_id)
                ->increment('cancellation_count');
        }

        return null;
    }

// ===================================================================
    // 1. TODAY'S DELIVERIES (PICKUP) — UTC in DB → Local Display
    // ===================================================================
    public function getCustomerPickupBookings($officeId)
    {
        // 1. Get office timezone
        $officeTimezone = $this->commonService->getOfficeTimezone($officeId);
        if (str_starts_with($officeTimezone, 'Error:')) {
            return ['error' => $officeTimezone];
        }

        // 2. Use UTC for DB query
        $nowUtc = Carbon::now('UTC');
        $todayUtc = $nowUtc->format('Y-m-d');

        // 3. Get office lat/lng
        $office = DB::table('office_locations')
            ->where('office_location_id', $officeId)
            ->select('latitude', 'longitude')
            ->first();

        if (!$office) {
            return ['error' => "Office not found (ID: {$officeId})"];
        }

        // 4. Query: today + pending + delivery
        $bookings = DB::table('bookings as b')
            ->join('cars as c', 'b.car_id', '=', 'c.car_id')
            ->where('b.deliver_need', 1)
            ->where('b.booking_status', 'pending')
            ->whereDate('b.pickup_datetime', $todayUtc) // UTC
            ->where('b.delivery_office_id', $officeId)
            ->select(
                'b.booking_id',
                'b.ticket_number',
                'b.pickup_datetime',      // UTC
                'b.pickup_latitude',
                'b.pickup_longitude',
                'c.model',
                'c.license_plate'
            )
            ->get();

        if ($bookings->isEmpty()) {
            return [];
        }

        $result = collect();
        $nowLocal = $nowUtc->copy()->setTimezone($officeTimezone);

        foreach ($bookings as $b) {
            // UTC → LOCAL
            $pickupLocal = Carbon::parse($b->pickup_datetime, 'UTC')
                                 ->setTimezone($officeTimezone);

            $distance = $this->commonService->haversine(
                $office->latitude,
                $office->longitude,
                (float)$b->pickup_latitude,
                (float)$b->pickup_longitude
            );

            $minutesUntil = $nowLocal->diffInMinutes($pickupLocal, false);
            $isOverdue = $minutesUntil < 0;

            $result->push([
                'booking_id'       => $b->booking_id,
                'ticket_number'    => $b->ticket_number,
                'pickup_datetime'  => $pickupLocal->format('Y-m-d H:i:s'), // LOCAL
                'pickup_latitude'  => (float)$b->pickup_latitude,
                'pickup_longitude' => (float)$b->pickup_longitude,
                'model'            => $b->model,
                'license_plate'    => $b->license_plate,
                'distance_km'      => round($distance, 2),
                'minutes_until'    => (int)$minutesUntil,
                'is_overdue'       => $isOverdue
            ]);
        }

        // Sort: overdue first → then closest + soonest
        return $result
            ->sortBy(function ($item) {
                return $item['is_overdue']
                    ? -1000 + abs($item['minutes_until'])
                    : $item['distance_km'] + max(0, $item['minutes_until'] / 60);
            })
            ->values()
            ->toArray();
    }

    // ===================================================================
    // 2. TODAY'S TAKE-BACKS (RETURN) — UTC in DB → Local Display
    // ===================================================================
    public function getCustomerTakebackBookings($officeId)
    {
        $officeTimezone = $this->commonService->getOfficeTimezone($officeId);
        if (str_starts_with($officeTimezone, 'Error:')) {
            return ['error' => $officeTimezone];
        }

        $nowUtc = Carbon::now('UTC');
        $todayUtc = $nowUtc->format('Y-m-d');

        $office = DB::table('office_locations')
            ->where('office_location_id', $officeId)
            ->select('latitude', 'longitude')
            ->first();

        if (!$office) {
            return ['error' => "Office not found (ID: {$officeId})"];
        }

        $bookings = DB::table('bookings as b')
            ->join('cars as c', 'b.car_id', '=', 'c.car_id')
            ->where('b.take_back_need', 1)
            ->where('b.booking_status', 'pending')
            ->whereDate('b.dropoff_datetime', $todayUtc) // UTC
            ->where('b.takeback_office_id', $officeId)
            ->select(
                'b.booking_id',
                'b.ticket_number',
                'b.dropoff_datetime',     // UTC
                'b.dropoff_latitude',
                'b.dropoff_longitude',
                'c.model',
                'c.license_plate'
            )
            ->get();

        if ($bookings->isEmpty()) {
            return [];
        }

        $result = collect();
        $nowLocal = $nowUtc->copy()->setTimezone($officeTimezone);

        foreach ($bookings as $b) {
            $returnLocal = Carbon::parse($b->dropoff_datetime, 'UTC')
                                 ->setTimezone($officeTimezone);

            $distance = $this->commonService->haversine(
                $office->latitude,
                $office->longitude,
                (float)$b->dropoff_latitude,
                (float)$b->dropoff_longitude
            );

            $minutesUntil = $nowLocal->diffInMinutes($returnLocal, false);
            $isOverdue = $minutesUntil < 0;

            $result->push([
                'booking_id'       => $b->booking_id,
                'ticket_number'    => $b->ticket_number,
                'pickup_datetime'  => $returnLocal->format('Y-m-d H:i:s'), // LOCAL
                'pickup_latitude'  => (float)$b->dropoff_latitude,
                'pickup_longitude' => (float)$b->dropoff_longitude,
                'model'            => $b->model,
                'license_plate'    => $b->license_plate,
                'distance_km'      => round($distance, 2),
                'minutes_until'    => (int)$minutesUntil,
                'is_overdue'       => $isOverdue
            ]);
        }

        return $result
            ->sortBy(function ($item) {
                return $item['is_overdue']
                    ? -1000 + abs($item['minutes_until'])
                    : $item['distance_km'] + max(0, $item['minutes_until'] / 60);
            })
            ->values()
            ->toArray();
    }
}