<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ReviewService;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    protected $reviewService;
    protected $helper;

    public function __construct(ReviewService $reviewService, Helper $helper)
    {
        $this->reviewService = $reviewService;
        $this->helper = $helper;
    }

    public function submitReview(Request $request)
    {
        $rules = [
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'booking_id' => 'required|integer|exists:bookings,booking_id'
        ];

        $validate = $this->helper->validate($request, $rules);
        if (is_null($validate)) {
            $data = $request->only(['rating', 'comment', 'booking_id']);
            $data['user_id'] = Auth::user()->user_id;

            $response = $this->reviewService->submitReview($data);
            if (is_null($response)) {
                return $this->helper->PostMan(null, 201, "Review submitted successfully");
            } else {
                return $this->helper->PostMan(null, 400, $response);
            }
        } else {
            return $this->helper->PostMan(null, 422, $validate);
        }
    }

    public function getAdminReviews(Request $request)
    {
        $rules = [
            'booking_id' => 'nullable|integer|exists:bookings,booking_id',
            'search_by'  => 'nullable|string|max:255',
            'first'      => 'required|integer|min:1',
            'max'        => 'required|integer|min:1',
            'filter_by'  => 'nullable|string|in:1,2,3,4,5', 
            'sort_by'    => 'nullable|string|in:created_at,rating,reviewer_name',
            'sort'       => 'nullable|string|in:asc,desc'
        ];

        $validate = $this->helper->validate($request, $rules);
        if (is_null($validate)) {
            $user = $request->user();
            if ($user->user_type_id != 3) {
                return $this->helper->PostMan(null, 403, "Admin only");
            }

            $data = $request->all();
            $response = $this->reviewService->getAdminReviews($data);
            return $this->helper->PostMan($response, 200, "Reviews retrieved successfully");
        } else {
            return $this->helper->PostMan(null, 422, $validate);
        }
    }
}