<?php

namespace App\Services;

use App\Services\FileService;
use App\Services\CommonService;
use Illuminate\Support\Facades\DB;

class CarService
{
    protected $fileService;
    protected $commonService;

    public function __construct(FileService $fileService, CommonService $commonService)
    {
        $this->fileService = $fileService;
        $this->commonService = $commonService;
    }

    // ==================== CAR TYPE ====================

    public function carTypes()
    {
        return DB::table('car_type as ct')
            ->leftJoin('photo_paths as pp', 'ct.photo_path_id', '=', 'pp.photo_path_id')
            ->select(
                'ct.car_type_id',
                'ct.type_name',
                'ct.description',
                DB::raw("CONCAT('" . env('R2_URL') . "/', pp.photo_path) as car_type_image_url")
            )
            ->get();
    }

    public function createCarType(array $data)
    {
        $photoPath = $this->fileService->uploadFile($data['car_type_image'], 'Car_Types/');
        if (!$photoPath) {
            return "Failed to upload car type image.";
        }

        $photoPathId = DB::table('photo_paths')->insertGetId(['photo_path' => $photoPath]);

        $carTypeId = DB::table('car_type')->insertGetId([
            'type_name' => $data['type_name'],
            'description' => $data['description'],
            'photo_path_id' => $photoPathId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $carTypeId ? null : "Failed to create car type.";
    }

    public function getCarTypeById($id)
    {
        return DB::table('car_type as ct')
            ->leftJoin('photo_paths as pp', 'ct.photo_path_id', '=', 'pp.photo_path_id')
            ->select(
                'ct.car_type_id',
                'ct.type_name',
                'ct.description',
                DB::raw("CONCAT('" . env('R2_URL') . "/', pp.photo_path) as car_type_image_url")
            )
            ->where('ct.car_type_id', $id)
            ->first();
    }

    public function alreadyExistsImagePath($id)
    {
        return DB::table('photo_paths')
            ->where('photo_path_id', $id)
            ->value('photo_path');
    }

    public function updateCarType($data)
    {
        $carType = DB::table('car_type')->where('car_type_id', $data['id'])->first();
        if (!$carType) {
            return "Car type not found.";
        }

        if (isset($data['car_type_image'])) {
            $oldPath = $this->alreadyExistsImagePath($carType->photo_path_id);
            if ($oldPath) {
                $this->fileService->deleteFile($oldPath);
            }

            $newPath = $this->fileService->uploadFile($data['car_type_image'], 'Car_Types/');
            if (!$newPath) {
                return "Failed to upload new image.";
            }

            DB::table('photo_paths')
                ->where('photo_path_id', $carType->photo_path_id)
                ->update(['photo_path' => $newPath, 'updated_at' => now()]);
        }

        DB::table('car_type')
            ->where('car_type_id', $data['id'])
            ->update([
                'type_name' => $data['type_name'] ?? $carType->type_name,
                'description' => $data['description'] ?? $carType->description,
                'updated_at' => now(),
            ]);

        return null;
    }

    public function deleteCarType($id)
    {
        $carType = DB::table('car_type')->where('car_type_id', $id)->first();
        if (!$carType) {
            return "Car type not found.";
        }

        if ($carType->photo_path_id) {
            $photoPath = $this->alreadyExistsImagePath($carType->photo_path_id);
            if ($photoPath) {
                $this->fileService->deleteFile($photoPath);
            }
            DB::table('photo_paths')->where('photo_path_id', $carType->photo_path_id)->delete();
        }

        DB::table('car_type')->where('car_type_id', $id)->delete();

        return null;
    }

    // ==================== CARS ====================

    public function getCars($data)
    {
        $query = DB::table('cars as c')
            ->leftJoin('car_type as ct', 'c.car_type_id', '=', 'ct.car_type_id')
            ->leftJoin('photo_paths as pp', 'c.photo_path_id', '=', 'pp.photo_path_id')
            ->select(
                'c.car_id',
                'ct.type_name as car_type',
                'c.model',
                'c.description',
                'c.license_plate',
                'c.price_per_hour',
                'c.price_per_day',
                'c.availability',
                'c.number_of_seats',
                'c.luggage_capacity',
                'c.color',
                'c.transmission',
                'c.fuel_type',
                'c.created_at',
                'c.updated_at',
                DB::raw("CONCAT('" . env('R2_URL') . "/', pp.photo_path) as car_image_url")
            )
            ->where('c.availability', true);

        if (!empty($data['car_type_id'])) {
            $query->where('c.car_type_id', $data['car_type_id']);
        }
        if (!empty($data['fuel_type'])) {
            $query->where('c.fuel_type', $data['fuel_type']);
        }

        $totalCars = DB::table('cars')->where('availability', true)->count();

        $page = max(1, (int)($data['first'] ?? 1));
        $max = max(1, (int)($data['max'] ?? 10));
        $offset = ($page - 1) * $max;

        $cars = $query->offset($offset)->limit($max)->get();

        if (!empty($data['total_hours'])) {
            $hours = (float)$data['total_hours'];
            $cars = $cars->map(function ($car) use ($hours) {
                if ($hours < 24) {
                    $car->total_price = round($hours * $car->price_per_hour, 2);
                } else {
                    $days = floor($hours / 24);
                    $remainingHours = $hours - ($days * 24);
                    $car->total_price = round(($days * $car->price_per_day) + ($remainingHours * $car->price_per_hour), 2);
                }
                return $car;
            });
        }

        if (!empty($data['asc_total'])) {
            $asc = $data['asc_total'] === 'true';
            $cars = $cars->sortBy('total_price', SORT_REGULAR, !$asc)->values();
        } elseif (!empty($data['asc_hour'])) {
            $asc = $data['asc_hour'] === 'true';
            $cars = $cars->sortBy('price_per_hour', SORT_REGULAR, !$asc)->values();
        } elseif (!empty($data['asc_day'])) {
            $asc = $data['asc_day'] === 'true';
            $cars = $cars->sortBy('price_per_day', SORT_REGULAR, !$asc)->values();
        }

        return [
            'cars' => $cars,
            'totalCars' => $totalCars
        ];
    }

    public function addCar(array $data)
    {
        $photoPath = $this->fileService->uploadFile($data['car_image'], 'Cars/');
        if (!$photoPath) {
            return "Failed to upload car image.";
        }

        $photoPathId = DB::table('photo_paths')->insertGetId([
            'photo_path' => $photoPath,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carId = DB::table('cars')->insertGetId([
            'car_type_id' => $data['car_type_id'],
            'model' => $data['model'],
            'license_plate' => $data['license_plate'],
            'price_per_hour' => $data['price_per_hour'],
            'price_per_day' => $data['price_per_day'],
            'availability' => true,
            'number_of_seats' => $data['number_of_seats'],
            'luggage_capacity' => $data['luggage_capacity'],
            'color' => $data['color'],
            'transmission' => $data['transmission'],
            'fuel_type' => $data['fuel_type'],
            'office_location_id' => $data['office_location_id'],
            'photo_path_id' => $photoPathId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $carId ? null : "Failed to create car.";
    }

    public function updateCar($data)
    {
        $car = DB::table('cars')->where('car_id', $data['id'])->first();
        if (!$car) {
            return "Car not found.";
        }

        if (isset($data['car_image'])) {
            $oldPath = $this->alreadyExistsImagePath($car->photo_path_id);
            if ($oldPath) {
                $this->fileService->deleteFile($oldPath);
            }

            $newPath = $this->fileService->uploadFile($data['car_image'], 'Cars/');
            if (!$newPath) {
                return "Failed to upload new image.";
            }

            DB::table('photo_paths')
                ->where('photo_path_id', $car->photo_path_id)
                ->update(['photo_path' => $newPath, 'updated_at' => now()]);
        }

        DB::table('cars')->where('car_id', $data['id'])->update([
            'car_type_id' => $data['car_type_id'] ?? $car->car_type_id,
            'model' => $data['car_model'] ?? $car->model,
            'license_plate' => $data['license_plate'] ?? $car->license_plate,
            'price_per_hour' => $data['price_per_hour'] ?? $car->price_per_hour,
            'price_per_day' => $data['price_per_day'] ?? $car->price_per_day,
            'availability' => isset($data['availability']) ? (bool)$data['availability'] : $car->availability,
            'number_of_seats' => $data['number_of_seats'] ?? $car->number_of_seats,
            'luggage_capacity' => $data['luggage_capacity'] ?? $car->luggage_capacity,
            'color' => $data['color'] ?? $car->color,
            'transmission' => $data['transmission'] ?? $car->transmission,
            'fuel_type' => $data['fuel_type'] ?? $car->fuel_type,
            'office_location_id' => $data['office_location_id'] ?? $car->office_location_id,
            'updated_at' => now(),
        ]);

        return null;
    }

    public function deleteCar($id)
    {
        $car = DB::table('cars')->where('car_id', $id)->first();
        if (!$car) {
            return "Car not found.";
        }

        if ($car->photo_path_id) {
            $photoPath = $this->alreadyExistsImagePath($car->photo_path_id);
            if ($photoPath) {
                $this->fileService->deleteFile($photoPath);
            }
            DB::table('photo_paths')->where('photo_path_id', $car->photo_path_id)->delete();
        }

        DB::table('cars')->where('car_id', $id)->delete();

        return null;
    }

    public function isCarAvailable($id)
    {
        $car = DB::table('cars')->where('car_id', $id)->first();
        if (!$car) {
            return "Car not found.";
        }
        return $car->availability ? null : "Selected car is not available. Please select another car.";
    }
}