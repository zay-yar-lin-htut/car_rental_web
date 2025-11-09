<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ReviewService
{
    public function submitReview($data)
    {
        $booking_id = $data['booking_id'];
        $user_id = $data['user_id'];

        $booking = DB::table('bookings')
            ->where('booking_id', $booking_id)
            ->where('user_id', $user_id)
            ->select('booking_status')
            ->first();

        if (!$booking) {
            return "Booking not found";
        }

        if ($booking->booking_status !== 'completed') {
            return "Only completed bookings can be reviewed.";
        }

        $exists = DB::table('reviews')
            ->where('booking_id', $booking_id)
            ->where('user_id', $user_id)
            ->exists();

        if ($exists) {
            return "You have already reviewed this booking.";
        }

        $inserted = DB::table('reviews')->insert([
            'booking_id' => $booking_id,
            'user_id'    => $user_id,
            'rating'     => $data['rating'],
            'comment'    => $data['comment'] ?? null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return $inserted ? null : "Failed to submit review.";
    }

    public function getAdminReviews($data)
    {
        $query = DB::table('reviews as r')
            ->join('users as u', 'r.user_id', '=', 'u.user_id')
            ->join('bookings as b', 'r.booking_id', '=', 'b.booking_id')
            ->leftJoin('cars as c', 'b.car_id', '=', 'c.car_id')
            ->select(
                'r.review_id',
                'r.booking_id',
                'b.ticket_number',
                'r.rating',
                'r.comment',
                'u.name as reviewer_name',
                'u.phone as reviewer_phone',
                'c.model as car_model',
                'c.license_plate',
                'r.created_at'
            );

        $countQuery = clone $query;

        // Filter by booking
        if (!empty($data['booking_id'])) {
            $query->where('r.booking_id', $data['booking_id']);
            $countQuery->where('r.booking_id', $data['booking_id']);
        }

        // Search
        if (!empty($data['search_by'])) {
            $term = '%' . $data['search_by'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('b.ticket_number', 'LIKE', $term)
                ->orWhere('u.name', 'LIKE', $term)
                ->orWhere('c.model', 'LIKE', $term);
            });
            $countQuery->where(function ($q) use ($term) {
                $q->where('b.ticket_number', 'LIKE', $term)
                ->orWhere('u.name', 'LIKE', $term)
                ->orWhere('c.model', 'LIKE', $term);
            });
        }

        // Filter by rating
        if (!empty($data['filter_by'])) {
            $query->where('r.rating', $data['filter_by']);
            $countQuery->where('r.rating', $data['filter_by']);
        }

        // Sorting
        $sortBy = $data['sort_by'] ?? 'created_at';
        $sort   = in_array($data['sort'] ?? '', ['asc', 'desc']) ? $data['sort'] : 'desc';

        $allowedSort = [
            'created_at'      => 'r.created_at',
            'rating'          => 'r.rating',
            'reviewer_name'   => 'u.name'
        ];

        $sortColumn = $allowedSort[$sortBy] ?? 'r.created_at';
        $query->orderBy($sortColumn, $sort);

        // Pagination
        $page    = max(1, (int)($data['first'] ?? 1));
        $perPage = max(1, (int)($data['max'] ?? 10));
        $offset  = ($page - 1) * $perPage;

        $totalReviews = $countQuery->count();

        $reviews = $query->offset($offset)->limit($perPage)->get();

        // Overall stats
        $overallAvg = DB::table('reviews')->avg('rating');
        $totalAll   = DB::table('reviews')->count();

        return [
            'reviews' => $reviews,
            'total_reviews' => $totalReviews,
            'overall_average_rating' => $overallAvg ? round((float)$overallAvg, 1) : null,
            'total_all_reviews' => $totalAll,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'from'         => $offset + 1,
                'to'           => min($offset + $perPage, $totalReviews),
                'last_page'    => ceil($totalReviews / $perPage)
            ]
        ];
    }
}