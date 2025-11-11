<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CarService;
use App\Helpers\Helper;
use App\Services\CommonService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CarController extends Controller
{
    protected $carService;
    protected $helper;
    protected $commonService;

    public function __construct(CarService $carService, Helper $helper, CommonService $commonService)
    {
        $this->carService = $carService;
        $this->helper = $helper;
        $this->commonService = $commonService;
    }

    // ==================== CAR TYPE ====================

    public function carTypes()
    {
        $carTypes = $this->carService->carTypes();
        return $this->helper->PostMan($carTypes, 200, "Car Types Retrieved Successfully");
    }

    public function createCarType(Request $request)
    {
        $rules = [
            'type_name' => 'required|string|max:255|unique:car_type,type_name',
            'description' => 'required|string|max:500',
            'car_type_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
        ];

        $validate = $this->helper->validate($request, $rules);
        if (is_null($validate)) {
            $response = $this->carService->createCarType($request->all());
            return is_null($response)
                ? $this->helper->PostMan(null, 201, "Car Type Successfully Created")
                : $this->helper->PostMan(null, 500, $response);
        }
        return $this->helper->PostMan(null, 422, $validate);
    }

    public function updateCarType(Request $request, $id)
    {
        $rules = [
            'type_name' => "nullable|string|max:255|unique:car_type,type_name,{$id},car_type_id",
            'description' => 'nullable|string|max:500',
            'car_type_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ];

        $validate = $this->helper->validate($request, $rules);
        if (is_null($validate)) {
            $data = $request->all();
            $data['id'] = $id;
            $response = $this->carService->updateCarType($data);

            return is_null($response)
                ? $this->helper->PostMan($this->carService->getCarTypeById($id), 200, "Car Type Updated Successfully")
                : $this->helper->PostMan(null, 500, $response);
        }
        return $this->helper->PostMan(null, 422, $validate);
    }

    public function deleteCarType($id)
    {
        if ($this->commonService->ForeignKeyIsExit("cars", "car_type_id", $id)) {
            return $this->helper->PostMan(null, 400, "Car type is in use. Cannot delete.");
        }

        $response = $this->carService->deleteCarType($id);
        return is_null($response)
            ? $this->helper->PostMan(null, 200, "Car type deleted successfully")
            : $this->helper->PostMan(null, 404, $response);
    }

    public function getCars(Request $request)
    {
        $rules = [
            'first' => 'required|integer|min:1',
            'max' => 'required|integer|min:1',
            'pickup_datetime' => 'nullable|date|required_with:dropoff_datetime',
            'dropoff_datetime' => 'nullable|date|required_with:pickup_datetime',
            'asc_total' => 'nullable|in:true,false',
            'asc_hour' => 'nullable|in:true,false',
            'asc_day' => 'nullable|in:true,false',
            'car_type_id' => 'nullable|integer|exists:car_type,car_type_id',
            'fuel_type' => 'nullable|in:petrol,diesel,electric',
        ];

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request) {
            $ascCount = collect(['asc_total', 'asc_hour', 'asc_day'])->filter(fn($f) => $request->filled($f))->count();
            if ($ascCount > 1) {
                $validator->errors()->add('sort', 'Only one sorting option allowed.');
            }
            if ($request->filled('asc_total') && !$request->filled('pickup_datetime')) {
                $validator->errors()->add('date', 'pickup_datetime and dropoff_datetime required for total price sorting.');
            }
        });

        if ($validator->fails()) {
            return $this->helper->PostMan(null, 422, $validator->errors()->first());
        }

        $totalHours = null;
        if ($request->filled('pickup_datetime') && $request->filled('dropoff_datetime')) {
            $totalHours = Carbon::parse($request->pickup_datetime)
                ->floatDiffInHours($request->dropoff_datetime);
        }

        $data = $request->all();
        $data['total_hours'] = $totalHours;

        $response = $this->carService->getCars($data);

        return $this->helper->PostMan($response, 200, "Cars Retrieved Successfully");
    }

    public function addCar(Request $request)
    {
        $rules = [
            'model' => 'required|string|max:255',
            'license_plate' => 'required|string|unique:cars,license_plate',
            'car_type_id' => 'required|integer|exists:car_type,car_type_id',
            'price_per_hour' => 'required|numeric|min:0',
            'price_per_day' => 'required|numeric|min:0',
            'number_of_seats' => 'required|integer|min:1',
            'luggage_capacity' => 'required|integer|min:0',
            'color' => 'required|string|max:50',
            'transmission' => 'required|in:auto,manual',
            'fuel_type' => 'required|in:petrol,diesel,electric',
            'car_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
            'office_location_id' => 'required|integer|exists:office_locations,office_location_id',
        ];

        $validate = $this->helper->validate($request, $rules);
        if (is_null($validate)) {
            $response = $this->carService->addCar($request->all());
            return is_null($response)
                ? $this->helper->PostMan(null, 201, "Car Successfully Added")
                : $this->helper->PostMan(null, 500, $response);
        }
        return $this->helper->PostMan(null, 422, $validate);
    }

    public function updateCar(Request $request, $id)
    {
        $rules = [
            'model' => 'nullable|string|max:255',
            'car_model' => 'nullable|string|max:255',
            'license_plate' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('cars', 'license_plate')->ignore($id),
            ],
            'car_type_id' => 'nullable|integer|exists:car_type,car_type_id',
            'price_per_hour' => 'nullable|numeric|min:0',
            'price_per_day' => 'nullable|numeric|min:0',
            'number_of_seats' => 'nullable|integer|min:1',
            'luggage_capacity' => 'nullable|integer|min:0',
            'color' => 'nullable|string|max:50',
            'transmission' => 'nullable|in:auto,manual',
            'fuel_type' => 'nullable|in:petrol,diesel,electric',
            'car_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'office_location_id' => 'nullable|integer|exists:office_locations,office_location_id',
            'availability' => 'nullable|boolean',
        ];

        $validate = $this->helper->validate($request, $rules);
        if (is_null($validate)) {
            $data = $request->all();
            $data['id'] = $id;
            $response = $this->carService->updateCar($data);
            return is_null($response)
                ? $this->helper->PostMan(null, 200, "Car Updated Successfully")
                : $this->helper->PostMan(null, 500, $response);
        }
        return $this->helper->PostMan(null, 422, $validate);
    }

    public function deleteCar($id)
    {
        $inUse = $this->commonService->ForeignKeyIsExit("bookings", "car_id", $id) ||
                 $this->commonService->ForeignKeyIsExit("maintenance", "car_id", $id);

        if ($inUse) {
            return $this->helper->PostMan(null, 400, "Car is in use. Cannot delete.");
        }

        $response = $this->carService->deleteCar($id);
        return is_null($response)
            ? $this->helper->PostMan(null, 200, "Car deleted successfully")
            : $this->helper->PostMan(null, 404, $response);
    }
}