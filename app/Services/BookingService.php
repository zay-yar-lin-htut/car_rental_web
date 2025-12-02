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
                'b.booking_status',
                'b.deliver_need', 'b.take_back_need', 'b.created_at', 'b.updated_at',
                DB::raw('COALESCE(AVG(r.rating), 0) as average_rating'),
                DB::raw('COUNT(r.review_id) as review_count')
            )
            ->groupBy('b.booking_id');

        $countQuery = clone $query;

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

        $sortBy = $data['sort_by'] ?? 'created_at';
        $sort   = in_array($data['sort'] ?? '', ['asc', 'desc']) ? $data['sort'] : 'desc';

        if ($sortBy === 'average_rating') {
            $query->orderByRaw("COALESCE(AVG(r.rating), 0) $sort");
        } else {
            $query->orderBy("b.$sortBy", $sort);
        }

        $totalBookings = $countQuery->count();
        $totalAmount   = $countQuery->sum('b.total_amount');

        $page    = max(1, (int)($data['first'] ?? 1));
        $perPage = max(1, (int)($data['max'] ?? 10));
        $offset  = ($page - 1) * $perPage;

        $bookings = $query->offset($offset)->limit($perPage)->get();

        $bookings->transform(function ($b) {
            $b->total_amount = (float)$b->total_amount;
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

    public function getBookingsByUser($data)
    {
        $userId = Auth::id();

        $perPage = max(1, min(100, (int)($data['max'] ?? 10)));
        $page    = max(1, (int)($data['first'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $query = DB::table('bookings as b')
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
                'ct.type_name as car_type',
                DB::raw('IF(r.review_id IS NOT NULL, true, false) as has_reviewed'),
                DB::raw("CONCAT('" . env('R2_URL') . "/', pp.photo_path) as car_image_url")
            )
            ->where('b.user_id', $userId);

        $totalBookings = (clone $query)->count();

        $bookings = $query
            ->orderByDesc('b.created_at')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'data'       => $bookings,
            'first'      => $page,
            'max'        => $perPage,
            'total'      => $totalBookings,
            'total_page' => $totalBookings > 0 ? ceil($totalBookings / $perPage) : 1
        ];
    }

    public function createBooking($data)
    {
        $data['ticket_number'] = $this->commonService->getTicketNumber();
        $data['user_id'] = Auth::user()->user_id;
        $data['booking_status'] = 'pending';

        $carUpdated = DB::table('cars')
            ->where('car_id', $data['car_id'])
            ->where('availability', true)
            ->update(['availability' => false]);

        if (!$carUpdated) {
            return "This car is not available. Please select another car.";
        }
        
        $data['delivery_office_id'] = $this->getResponsibleOffice(
            $data['pickup_latitude'], $data['pickup_longitude']
        );
        $data['deliver_need'] = !is_null($data['delivery_office_id']) ? 1 : 0;

        $data['takeback_office_id'] = $this->getResponsibleOffice(
            $data['dropoff_latitude'], $data['dropoff_longitude']
        );
        $data['take_back_need'] = !is_null($data['takeback_office_id']) ? 1 : 0;

        $inserted = DB::table('bookings')->insert($data);
        return $inserted ? null : "Failed to create booking.";
    }

    public function getResponsibleOffice($lat, $lng)
    {
        $result = DB::table('office_locations')
            ->select('office_location_id')
            ->selectRaw(
                '6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                ) AS distance_km',
                [$lat, $lng, $lat]
            )
            ->orderBy('distance_km')
            ->first(); // nearest office

        if (!$result) {
            return null;
        }

        $distance = $result->distance_km;

        // Only allow delivery if between 1 km and 100 km
        if ($distance >= 1 && $distance <= 100) {
            return $result->office_location_id;
        }

        return null; // Too close (<1km) or too far (>100km) → no delivery
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

        DB::table('cars')
            ->where('car_id', $booking->car_id)
            ->update(['availability' => true]);

        if ($booking->booking_status === 'confirmed') {
            DB::table('users')
                ->where('user_id', $booking->user_id)
                ->increment('cancellation_count');
        }

        return null;
    }

    public function getCustomerPickupBookings($officeId)
    {
        $todayStart = today();        // 2025-11-27 00:00:00 (local)
        $now        = now();          // 2025-11-27 14:30:00 (local)

        $bookings = DB::table('bookings as b')
            ->join('cars as c', 'b.car_id', '=', 'c.car_id')
            ->where('b.deliver_need', 1)
            ->whereIn('b.booking_status', ['pending', 'confirmed'])
            // ->where(function ($q) use ($todayStart, $now) {
            //     $q->where('b.pickup_datetime', '<=', $now)                    // overdue (past)
            //     ->orWhereBetween('b.pickup_datetime', [$todayStart, $todayStart->copy()->endOfDay()]); // today
            // })
            ->where('b.delivery_office_id', $officeId)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('tasks')
                    ->whereColumn('tasks.booking_id', 'b.booking_id')
                    ->where('tasks.status', 'in_progress');
            })
            ->select(
                'b.booking_id',
                'b.ticket_number',
                'b.pickup_datetime',
                'b.pickup_latitude',
                'b.pickup_longitude',
                'c.model',
                'c.license_plate'
            )
            ->orderBy('b.pickup_datetime')
            ->get();

        if ($bookings->isEmpty()) return [];

        return $bookings->map(function ($b) {
            $minutesUntil = now()->diffInMinutes($b->pickup_datetime, false);
            $isOverdue    = $minutesUntil < 0;

            return [
                'booking_id'       => $b->booking_id,
                'ticket_number'    => $b->ticket_number,
                'pickup_datetime'  => Carbon::parse($b->pickup_datetime)->format('Y-m-d H:i'),
                'pickup_latitude'  => (float)$b->pickup_latitude,
                'pickup_longitude' => (float)$b->pickup_longitude,
                'model'            => $b->model,
                'license_plate'    => $b->license_plate,
                'minutes_until'    => (int)$minutesUntil,
                'is_overdue'       => $isOverdue
            ];
        })
        ->sortByDesc('is_overdue')                    // overdue first
        ->sortBy('pickup_datetime')                   // then by time
        ->values()
        ->toArray();
    }

    public function getCustomerTakebackBookings($officeId)
    {
        $todayStart = today();
        $now        = now();

        $bookings = DB::table('bookings as b')
            ->join('cars as c', 'b.car_id', '=', 'c.car_id')
            ->where('b.take_back_need', 1)
            ->where('b.booking_status', 'on_rent')
            // ->where(function ($q) use ($todayStart, $now) {
            //     $q->where('b.dropoff_datetime', '<=', $now)
            //     ->orWhereBetween('b.dropoff_datetime', [$todayStart, $todayStart->copy()->endOfDay()]);
            // })
            ->where('b.takeback_office_id', $officeId)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('tasks')
                    ->whereColumn('tasks.booking_id', 'b.booking_id')
                    ->where('tasks.status', 'in_progress');
            })
            ->select(
                'b.booking_id',
                'b.ticket_number',
                'b.dropoff_datetime',
                'b.dropoff_latitude',
                'b.dropoff_longitude',
                'c.model',
                'c.license_plate'
            )
            ->orderBy('b.dropoff_datetime')
            ->get();

        if ($bookings->isEmpty()) return [];

        return $bookings->map(function ($b) {
            $minutesUntil = now()->diffInMinutes($b->dropoff_datetime, false);
            $isOverdue    = $minutesUntil < 0;

            return [
                'booking_id'        => $b->booking_id,
                'ticket_number'     => $b->ticket_number,
                'return_datetime'   => Carbon::parse($b->dropoff_datetime)->format('Y-m-d H:i'),
                'dropoff_latitude'  => (float)$b->dropoff_latitude,
                'dropoff_longitude' => (float)$b->dropoff_longitude,
                'model'             => $b->model,
                'license_plate'     => $b->license_plate,
                'minutes_until'     => (int)$minutesUntil,
                'is_overdue'        => $isOverdue
            ];
        })
        ->sortByDesc('is_overdue')
        ->sortBy('dropoff_datetime')
        ->values()
        ->toArray();
    }

    public function claimDeliveryTask($bookingId, $staffId)
    {
        $booking = DB::table('bookings')
            ->where('booking_id', $bookingId)
            ->where('deliver_need', 1)
            ->whereIn('booking_status', ['pending', 'confirmed'])
            ->first();

        if (!$booking) return "Delivery not available.";

        $exists = DB::table('tasks')
            ->where('booking_id', $bookingId)
            ->where('task_type', 'delivery')
            ->exists();

        if ($exists) return "Already claimed.";

        DB::table('tasks')->insert([
            'task_type'         => 'delivery',
            'description'       => "Deliver car - Ticket: {$booking->ticket_number}",
            'status'            => 'in_progress',
            'booking_id'        => $bookingId,
            'assigned_staff_id' => $staffId,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return null;
    }

    public function claimTakebackTask($bookingId, $staffId)
    {
        $booking = DB::table('bookings')
            ->where('booking_id', $bookingId)
            ->where('take_back_need', 1)
            ->whereIn('booking_status', ['on_rent'])
            ->first();

        if (!$booking) return "Take-back not available.";

        $exists = DB::table('tasks')
            ->where('booking_id', $bookingId)
            ->where('task_type', 'take_back')
            ->exists();

        if ($exists) return "Already claimed.";

        DB::table('tasks')->insert([
            'task_type'         => 'take_back',
            'description'       => "Take back car - Ticket: {$booking->ticket_number}",
            'status'            => 'in_progress',
            'booking_id'        => $bookingId,
            'assigned_staff_id' => $staffId,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return null;
    }

    public function getMyActiveTasks($staffId)
    {
        return DB::table('tasks as t')
            ->join('bookings as b', 't.booking_id', '=', 'b.booking_id')
            ->join('users as u', 'u.user_id', '=', 'b.user_id')
            ->join('cars as c', 'b.car_id', '=', 'c.car_id')
            ->where('t.assigned_staff_id', $staffId)
            ->where('t.status', 'in_progress')
            ->select('b.booking_id','t.task_id','u.phone','t.task_type','t.status','b.ticket_number','b.pickup_datetime','b.dropoff_datetime','c.model','c.license_plate','b.pickup_latitude','b.pickup_longitude','b.dropoff_latitude','b.dropoff_longitude')
            ->get();
    }

    public function getStaffTaskHistory($staffId)
    {
        return DB::table('tasks as t')
            ->join('bookings as b', 't.booking_id', '=', 'b.booking_id')
            ->join('cars as c', 'b.car_id', '=', 'c.car_id')
            ->where('t.assigned_staff_id', $staffId)
            ->where('t.created_at', '>=', now()->subDays(30))
            ->select('t.*','b.ticket_number','c.model','c.license_plate','t.created_at','t.updated_at')
            ->orderByDesc('t.created_at')
            ->get();
    }

    public function getMaintenanceTaskHistory($staffId)
    {
        return DB::table('maintenance as m')
            ->join('cars as c', 'm.car_id', '=', 'c.car_id')
            ->where('m.staff_id', $staffId)
            ->where('m.created_at', '>=', now()->subDays(30))
            ->select('m.*','c.model','c.license_plate','m.created_at','m.updated_at')
            ->orderByDesc('m.created_at')
            ->get();
    }

    public function completeDelivery($data)
    {
        $taskId     = $data['task_id'];
        $staffId    = $data['staff_id'];
        $amountPaid = $data['amount_paid'];     // total booking amount
        $fineAmount = $data['fine_amount'] ?? 0;

        $task = DB::table('tasks')
            ->where('task_id', $taskId)
            ->where('task_type', 'delivery')
            ->where('assigned_staff_id', $staffId)
            ->where('status', 'in_progress')
            ->first();

        if (!$task) return "Invalid task or not assigned to you.";

        $booking = DB::table('bookings')->where('booking_id', $task->booking_id)->first();
        if (!$booking) return "Booking not found.";

        DB::beginTransaction();
        try {
            // 1. Record booking payment
            DB::table('payments')->insert([
                'user_id'      => $booking->user_id,
                'staff_id'     => $staffId,
                'booking_id'   => $booking->booking_id,
                'payment_type' => 'booking',
                'amount'       => $amountPaid,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // 2. Record fine if any
            if ($fineAmount > 0) {
                DB::table('payments')->insert([
                    'user_id'      => $booking->user_id,
                    'staff_id'     => $staffId,
                    'booking_id'   => $booking->booking_id,
                    'payment_type' => 'fine',
                    'amount'       => $fineAmount,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                // RESET counters when fine is paid
                DB::table('users')
                    ->where('user_id', $booking->user_id)
                    ->update([
                        'no_show_count'      => 0,
                        'cancellation_count' => 0
                    ]);
            }

            // 3. Mark task & booking done
            DB::table('tasks')
                ->where('task_id', $taskId)
                ->update(['status' => 'completed', 'updated_at' => now()]);

            DB::table('bookings')
                ->where('booking_id', $booking->booking_id)
                ->update(['booking_status' => 'on_rent']);

            DB::commit();
            return null;
        } catch (\Exception $e) {
            DB::rollBack();
            return "Failed to complete delivery.";
        }
    }

    public function completeTakeback($taskId, $staffId)
    {
        $task = DB::table('tasks')
            ->where('task_id', $taskId)
            ->where('task_type', 'take_back')
            ->where('assigned_staff_id', $staffId)
            ->where('status', 'in_progress')
            ->first();

        if (!$task) return "Invalid task or not assigned to you.";

        $booking = DB::table('bookings')->where('booking_id', $task->booking_id)->first();
        if (!$booking) return "Booking not found.";

        DB::beginTransaction();
        try {
            DB::table('tasks')
                ->where('task_id', $taskId)
                ->update(['status' => 'completed', 'updated_at' => now()]);

            DB::table('bookings')
                ->where('booking_id', $booking->booking_id)
                ->update(['booking_status' => 'completed']);

            // Always return car to available (no maintenance option = you said it's useless)
            DB::table('cars')
                ->where('car_id', $booking->car_id)
                ->update(['availability' => 1]);

            DB::commit();
            return null;
        } catch (\Exception $e) {
            DB::rollBack();
            return "Failed to complete takeback.";
        }
    }

    public function reportDamage($request, $staffId)
    {
        $carId       = $request->car_id;
        $description = $request->description;
        $cost        = $request->cost;

        $exists = DB::table('cars')
            ->where('car_id', $carId)
            ->where('availability', 1)
            ->exists();

        if (!$exists) return "Car not found.";

        DB::beginTransaction();
        try {
            // Create maintenance record
            $maintenanceId = DB::table('maintenance')->insertGetId([
                'car_id'       => $carId,
                'staff_id'     => $staffId,
                'description'  => $description,
                'cost'         => $cost,
                'status'       => 'pending',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Create maintenance task
            // DB::table('tasks')->insert([
            //     'task_type'        => 'maintenance',
            //     'description'      => "Fix car #{$carId} - {$description}",
            //     'status'           => 'pending',
            //     'assigned_staff_id'=> $staffId,
            //     'created_at'       => now(),
            //     'updated_at'       => now(),
            // ]);

            // Car unavailable
            DB::table('cars')
                ->where('car_id', $carId)
                ->update(['availability' => 0]);

            DB::commit();
            return null;
        } catch (\Exception $e) {
            DB::rollBack();
            return "Failed to report damage. error: " . $e->getMessage();
        }
    }

    public function completeMaintenance($maintenanceId, $staffId)
    {
        $maintenance = DB::table('maintenance')
            ->where('maintenance_id', $maintenanceId)
            ->where('status', 'pending')
            ->first();

        if (!$maintenance) return "Maintenance not found or already completed.";

        DB::beginTransaction();
        try {
            DB::table('maintenance')
                ->where('maintenance_id', $maintenanceId)
                ->update([
                    'status'     => 'completed',
                    'updated_at' => now(),
                ]);

            // Complete related task
            // DB::table('tasks')
            //     ->where('task_type', 'maintenance')
            //     ->where('description', 'LIKE', "%car #{$maintenance->car_id}%")
            //     ->update([
            //         'status'            => 'completed',
            //         'assigned_staff_id' => $staffId,
            //         'updated_at'        => now(),
            //     ]);

            DB::table('cars')
                ->where('car_id', $maintenance->car_id)
                ->update(['availability' => 1]);

            DB::commit();
            return null;
        } catch (\Exception $e) {
            DB::rollBack();
            return "Failed to complete maintenance.";
        }
    }

    public function getAdminDashboardData()
    {
        $today = today();
        $startOfMonth = now()->startOfMonth();
        $sevenDaysAgo = now()->subDays(6)->startOfDay();

        // 1. Revenue Today + This Month
        $todayRevenue = DB::table('payments')
            ->whereDate('created_at', $today)
            ->sum('amount');

        $monthRevenue = DB::table('payments')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('amount');

        // New: Bookings Today + This Month
        $todayBookings = DB::table('bookings')
            ->whereDate('created_at', $today)
            ->count();

        $monthBookings = DB::table('bookings')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        // 2. Maintenance count (for car status)
        $maintenanceCount = DB::table('maintenance')
            ->where('status', 'pending')
            ->count();

        // New: Car Status
        $totalCars = DB::table('cars')->count();
        $availableCars = DB::table('cars')
            ->where('availability', 1)
            ->count();
        $rentedCars = DB::table('bookings')
            ->whereIn('booking_status', ['confirmed', 'on_rent','pending'])
            ->distinct()
            ->count('car_id');

        // New: Staff Status
        $totalStaff = DB::table('users')
            ->where('user_type_id', 2) // Assuming 2 is staff
            ->count();
        $deliveryStaff = DB::table('tasks')
            ->where('task_type', 'delivery')
            ->where('status', 'in_progress')
            ->distinct()
            ->count('assigned_staff_id');
        $takebackStaff = DB::table('tasks')
            ->where('task_type', 'take_back')
            ->where('status', 'in_progress')
            ->distinct()
            ->count('assigned_staff_id');
        $maintenanceStaff = DB::table('tasks')
            ->where('task_type', 'maintenance')
            ->where('status', 'in_progress')
            ->distinct()
            ->count('assigned_staff_id');
        $freeStaff = max(0, $totalStaff - ($deliveryStaff + $takebackStaff + $maintenanceStaff)); // No overlap assumed

        // 3. Pending deliveries & takebacks today (kept but optional)
        $pendingDeliveries = DB::table('bookings')
            ->where('deliver_need', 1)
            ->whereIn('booking_status', ['pending', 'confirmed'])
            ->whereDate('pickup_datetime', $today)
            ->count();

        $pendingTakebacks = DB::table('bookings')
            ->where('take_back_need', 1)
            ->where('booking_status', 'on_rent')
            ->whereDate('dropoff_datetime', $today)
            ->count();

        // 4. Revenue chart last 7 days
        $chart = DB::table('payments')
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('d M');
            $data[] = $chart->get($date, 0);
        }

        // New: Bookings chart last 7 days (similar to revenue)
        $bookingsChart = DB::table('bookings')
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $bookingsLabels = [];
        $bookingsData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $bookingsLabels[] = now()->subDays($i)->format('d M');
            $bookingsData[] = $bookingsChart->get($date, 0);
        }

        // New: Payments by staff (table data, e.g., collected amounts per staff)
        $paymentsByStaff = DB::table('payments as p')
            ->join('users as u', 'p.staff_id', '=', 'u.user_id')
            ->select('u.name as staff_name', DB::raw('SUM(p.amount) as total_collected'))
            ->groupBy('u.name')
            ->get();

        // 5. Maintenance queue
        $maintenanceQueue = DB::table('maintenance as m')
            ->join('cars as c', 'm.car_id', '=', 'c.car_id')
            ->where('m.status', 'pending')
            ->select('m.*', 'c.model', 'c.license_plate')
            ->orderBy('m.created_at', 'desc')
            ->get();

        // 6. Recent bookings
        $recentBookings = DB::table('bookings as b')
            ->join('users as u', 'b.user_id', '=', 'u.user_id')
            ->join('cars as c', 'b.car_id', '=', 'c.car_id')
            ->select('b.*', 'u.name as customer_name', 'c.model as car_model', 'c.license_plate')
            ->orderBy('b.created_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'today_revenue'       => (float)$todayRevenue,
            'month_revenue'       => (float)$monthRevenue,
            'today_bookings'      => (int)$todayBookings,
            'month_bookings'      => (int)$monthBookings,
            'total_cars'          => (int)$totalCars,
            'available_cars'      => (int)$availableCars,
            'rented_cars'         => (int)$rentedCars,
            'maintenance_cars'    => (int)$maintenanceCount, // Reuse
            'total_staff'         => (int)$totalStaff,
            'delivery_staff'      => (int)$deliveryStaff,
            'takeback_staff'      => (int)$takebackStaff,
            'maintenance_staff'   => (int)$maintenanceStaff,
            'free_staff'          => (int)$freeStaff,
            'pending_deliveries'  => (int)$pendingDeliveries,
            'pending_takebacks'   => (int)$pendingTakebacks,
            'revenue_chart'       => [
                'labels' => $labels,
                'data'   => $data
            ],
            'bookings_chart'      => [
                'labels' => $bookingsLabels,
                'data'   => $bookingsData
            ],
            'payments_by_staff'   => $paymentsByStaff,
            'maintenance_queue'   => $maintenanceQueue,
            'recent_bookings'     => $recentBookings,
            'generated_at'        => now()->format('Y-m-d H:i:s')
        ];
    }

    public function doTheStaffEarly($id)
    {
        $isHaveActiveTask = DB::table('tasks')
            ->where('assigned_staff_id', $id)
            ->whereIn('task_type', ['delivery', 'take_back', 'maintenance'])
            ->where('status', 'in_progress')
            ->exists();

        return $isHaveActiveTask;
    }

    public function costByTicket($ticketNumber)
    {
        // Fetch a single record containing the calculated components
        $result = DB::table('bookings as b')
            ->join('users as u', 'b.user_id', '=', 'u.user_id')
            ->where('b.ticket_number', $ticketNumber)
            // Select the three required calculated components
            ->selectRaw('
                (u.no_show_count * 10000) AS no_show_fine,
                (u.cancellation_count * 3000) AS cancellation_fine,
                b.total_amount AS booking_cost
            ')
            ->first(); // Get the first matching record
        if (!$result) {
            return null; // or handle the case where no record is found
        }
 
        return [
            'no_show_fine' => (float)$result->no_show_fine,
            'cancellation_fine' => (float)$result->cancellation_fine,
            'booking_cost' => (float)$result->booking_cost,
        ];
    }

    // GLOBAL: Today's Self Pickups (customer comes to ANY office)
    public function getTodaySelfPickups()
    {
        $todayStart = today();
        $now        = now();

        $bookings = DB::table('bookings as b')
            ->join('cars as c', 'b.car_id', '=', 'c.car_id')
            ->where('b.deliver_need', 0)                                      // self pickup only
            ->whereIn('b.booking_status', ['pending', 'confirmed'])
            // ->where(function ($q) use ($todayStart, $now) {
            //     $q->where('b.pickup_datetime', '<=', $now)                 // overdue
            //     ->orWhereBetween('b.pickup_datetime', [$todayStart, $todayStart->copy()->endOfDay()]);
            // })
            ->whereNull('b.delivery_office_id')                               // key: no delivery office
            ->select(
                'b.booking_id',
                'b.ticket_number',
                'b.pickup_datetime',
                'b.pickup_latitude',
                'b.pickup_longitude',
                'c.model',
                'c.license_plate'
            )
            ->orderBy('b.pickup_datetime')
            ->get();

        if ($bookings->isEmpty()) return [];

        return $bookings->map(function ($b) {
            $minutesUntil = now()->diffInMinutes($b->pickup_datetime, false);
            $isOverdue    = $minutesUntil < 0;

            return [
                'booking_id'      => $b->booking_id,
                'ticket_number'   => $b->ticket_number,
                'pickup_datetime' => Carbon::parse($b->pickup_datetime)->format('Y-m-d H:i'),
                'pickup_latitude' => $b->pickup_latitude,
                'pickup_longitude'=> $b->pickup_longitude,
                'model'           => $b->model,
                'license_plate'   => $b->license_plate,
                'minutes_until'   => (int)$minutesUntil,
                'is_overdue'      => $isOverdue
            ];
        })
        ->sortByDesc('is_overdue')
        ->sortBy('pickup_datetime')
        ->values()
        ->toArray();
    }

    // GLOBAL: Today's Self Dropoffs (customer returns to ANY office)
    public function getTodaySelfDropoffs()
    {
        $todayStart = today();
        $now        = now();

        $bookings = DB::table('bookings as b')
            ->join('cars as c', 'b.car_id', '=', 'c.car_id')
            ->where('b.take_back_need', 0)                                    // self dropoff only
            ->where('b.booking_status', 'on_rent')
            // ->where(function ($q) use ($todayStart, $now) {
            //     $q->where('b.dropoff_datetime', '<=', $now)
            //     ->orWhereBetween('b.dropoff_datetime', [$todayStart, $todayStart->copy()->endOfDay()]);
            // })
            ->whereNull('b.takeback_office_id')                               // key: no takeback office
            ->select(
                'b.booking_id',
                'b.ticket_number',
                'b.dropoff_datetime',
                'b.dropoff_latitude',
                'b.dropoff_longitude',
                'c.model',
                'c.license_plate'
            )
            ->orderBy('b.dropoff_datetime')
            ->get();

        if ($bookings->isEmpty()) return [];

        return $bookings->map(function ($b) {
            $minutesUntil = now()->diffInMinutes($b->dropoff_datetime, false);
            $isOverdue    = $minutesUntil < 0;

            return [
                'booking_id'       => $b->booking_id,
                'ticket_number'    => $b->ticket_number,
                'return_datetime'  => Carbon::parse($b->dropoff_datetime)->format('Y-m-d H:i'),
                'dropoff_latitude' => $b->dropoff_latitude,
                'dropoff_longitude'=> $b->dropoff_longitude,
                'model'            => $b->model,
                'license_plate'    => $b->license_plate,
                'minutes_until'    => (int)$minutesUntil,
                'is_overdue'       => $isOverdue
            ];
        })
        ->sortByDesc('is_overdue')
        ->sortBy('dropoff_datetime')
        ->values()
        ->toArray();
    }

    // Customer came to office → complete self pickup + record payment
    public function completeSelfPickup($bookingId, $staffId, $amountPaid, $fineAmount = 0)
    {
        $booking = DB::table('bookings')
            ->where('booking_id', $bookingId)
            ->where('deliver_need', 0)
            ->whereNull('delivery_office_id')
            ->whereIn('booking_status', ['pending', 'confirmed'])
            ->first();

        if (!$booking) {
            return "Invalid booking or not eligible for self pickup.";
        }

        DB::beginTransaction();
        try {
            // 1. Record main booking payment
            DB::table('payments')->insert([
                'user_id'       => $booking->user_id,
                'staff_id'       => $staffId,
                'booking_id'     => $bookingId,
                'payment_type'   => 'booking',
                'amount'         => $amountPaid,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // 2. Record fine if any (and reset counters)
            if ($fineAmount > 0) {
                DB::table('payments')->insert([
                    'user_id'      => $booking->user_id,
                    'staff_id'     => $staffId,
                    'booking_id'   => $bookingId,
                    'payment_type' => 'fine',
                    'amount'       => $fineAmount,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                DB::table('users')
                    ->where('user_id', $booking->user_id)
                    ->update([
                        'no_show_count'      => 0,
                        'cancellation_count' => 0
                    ]);
            }

            // 3. Update booking status + timestamps + staff
            DB::table('bookings')
                ->where('booking_id', $bookingId)
                ->update([
                    'booking_status' => 'on_rent',
                    'updated_at'     => now()
                ]);

            // 4. Mark car as rented
            DB::table('cars')
                ->where('car_id', $booking->car_id)
                ->update(['availability' => 0]);

            DB::commit();
            return null;
        } catch (\Exception $e) {
            DB::rollBack();
            return "Failed to complete self pickup. Error: " . $e->getMessage();
        }
    }

    public function completeSelfDropoff($bookingId, $staffId)
    {
        $booking = DB::table('bookings')
            ->where('booking_id', $bookingId)
            ->where('take_back_need', 0)
            ->whereNull('takeback_office_id')
            ->where('booking_status', 'on_rent')
            ->first();

        if (!$booking) {
            return "Invalid booking or not eligible for self dropoff.";
        }

        DB::beginTransaction();
        try {
            // Update booking
            DB::table('bookings')
                ->where('booking_id', $bookingId)
                ->update([
                    'booking_status' => 'completed',
                    'updated_at'     => now()
                ]);

            // Return car to pool
            DB::table('cars')
                ->where('car_id', $booking->car_id)
                ->update(['availability' => 1]);

            DB::commit();
            return null;
        } catch (\Exception $e) {
            DB::rollBack();
            return "Failed to complete self dropoff. Error: " . $e->getMessage();
        }
    }

    // 1. For DELIVERY bookings — staff went but customer not there
    public function markNoShowDelivery($bookingId, $staffId)
    {
        $booking = DB::table('bookings as b')
            ->join('tasks as t', 'b.booking_id', '=', 't.booking_id')
            ->where('b.booking_id', $bookingId)
            ->where('b.deliver_need', 1)
            ->whereIn('b.booking_status', ['pending', 'confirmed'])
            ->where('t.task_type', 'delivery')
            ->where('t.assigned_staff_id', $staffId)
            ->where('t.status', 'in_progress')  // staff already accepted task
            ->select('b.*', 't.task_id')
            ->first();

        if (!$booking) {
            return "Invalid delivery booking or task not in progress";
        }

        DB::transaction(function () use ($booking, $staffId) {
            // Cancel booking
            DB::table('bookings')
                ->where('booking_id', $booking->booking_id)
                ->update([
                    'booking_status'      => 'cancelled',
                    'cancellation_reason' => 'No-show: Customer not at pickup location',
                    'cancellation_date'   => now(),
                    'complete_by'         => $staffId,
                    'updated_at'          => now()
                ]);

            // Complete task as "failed" or just close it
            DB::table('tasks')
                ->where('task_id', $booking->task_id)
                ->update(['status' => 'completed', 'updated_at' => now()]);

            // Return car to pool
            DB::table('cars')
                ->where('car_id', $booking->car_id)
                ->update(['availability' => 1]);

            // +1 no-show count
            DB::table('users')
                ->where('user_id', $booking->user_id)
                ->increment('no_show_count');
        });

        return null;
    }

    // 2. For SELF-PICKUP bookings — customer didn’t come to office
    public function markNoShowSelfPickup($bookingId, $staffId)
    {
        $booking = DB::table('bookings')
            ->where('booking_id', $bookingId)
            ->where('deliver_need', 0)
            ->whereNull('delivery_office_id')
            ->whereIn('booking_status', ['pending', 'confirmed'])
            ->where('pickup_datetime', '<=', now())  // past pickup time
            ->first();

        if (!$booking) {
            return "Invalid self-pickup booking or not past pickup time yet";
        }

        DB::transaction(function () use ($booking, $staffId) {
            DB::table('bookings')
                ->where('booking_id', $booking->booking_id)
                ->update([
                    'booking_status'      => 'cancelled',
                    'cancellation_reason' => 'No-show: Customer did not come to office',
                    'cancellation_date'   => now(),
                    'complete_by'         => $staffId,
                    'updated_at'          => now()
                ]);

            // Return car
            DB::table('cars')
                ->where('car_id', $booking->car_id)
                ->update(['availability' => 1]);

            // +1 no-show
            DB::table('users')
                ->where('user_id', $booking->user_id)
                ->increment('no_show_count');
        });

        return null;
    }
}