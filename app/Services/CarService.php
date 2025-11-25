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

        // Wrap in transaction for safety
        DB::transaction(function () use ($carType) {
            // 1. Delete the car_type FIRST → removes FK reference
            DB::table('car_type')->where('car_type_id', $carType->car_type_id)->delete();

            // 2. Now safe to delete the photo
            if ($carType->photo_path_id) {
                $photoPath = $this->alreadyExistsImagePath($carType->photo_path_id);
                if ($photoPath) {
                    $this->fileService->deleteFile($photoPath);
                }

                // Now no foreign key blocking this
                DB::table('photo_paths')
                    ->where('photo_path_id', $carType->photo_path_id)
                    ->delete();
            }
        });

        return null; // success
    }

    // ==================== CARS ====================

    public function getCars($data)
    {
        $perPage = max(1, min(100, (int)($data['max'] ?? 10)));
        $page    = max(1, (int)($data['first'] ?? 1));
        $offset  = ($page - 1) * $perPage;

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
            );

        // === FILTERS ===
        $query->where('c.availability', $data['availability'] ?? true);

        if (!empty($data['car_type_id'])) {
            $query->where('c.car_type_id', $data['car_type_id']);
        }
        if (!empty($data['fuel_type'])) {
            $query->where('c.fuel_type', $data['fuel_type']);
        }
        if (!empty($data['search_by'])) {
            $search = '%' . trim($data['search_by']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('c.model', 'like', $search)
                ->orWhere('ct.type_name', 'like', $search)
                ->orWhere('c.license_plate', 'like', $search);
            });
        }

        // === TOTAL COUNT (before sorting) ===
        $totalCars = (clone $query)->count();

        // === CALCULATE TOTAL PRICE IN SQL (for correct global sorting) ===
        $hours = !empty($data['total_hours']) ? (float)$data['total_hours'] : null;

        if ($hours !== null) {
            // This is the MAGIC: calculate total_price in SQL → can sort globally
            $query->selectRaw(
                "IF(? < 24,
                    ROUND(? * c.price_per_hour, 2),
                    ROUND(
                        FLOOR(? / 24) * c.price_per_day +
                        (? - FLOOR(? / 24) * 24) * c.price_per_hour,
                        2
                    )
                ) AS total_price",
                [$hours, $hours, $hours, $hours, $hours]
            );
        }

        // === SORTING (GLOBAL & CORRECT) ===
        if (!empty($data['asc_hour'])) {
            $query->orderBy('c.price_per_hour', $data['asc_hour'] === 'true' ? 'asc' : 'desc');
        } elseif (!empty($data['asc_day'])) {
            $query->orderBy('c.price_per_day', $data['asc_day'] === 'true' ? 'asc' : 'desc');
        } elseif (!empty($data['asc_total']) && $hours !== null) {
            $query->orderBy('total_price', $data['asc_total'] === 'true' ? 'asc' : 'desc');
        }

        // === PAGINATION (AFTER sorting) ===
        $cars = $query->offset($offset)->limit($perPage)->get();

        // === FINAL: Add total_price to object (if not already from SQL) ===
        if ($hours !== null) {
            $cars = $cars->map(function ($car) use ($hours) {
                // total_price already calculated in SQL → just cast
                $car->total_price = (float)$car->total_price;
                return $car;
            });
        }

        return [
            'data'       => $cars,
            'first'      => $page,
            'max'        => $perPage,
            'total'      => $totalCars,
            'total_page' => $totalCars > 0 ? ceil($totalCars / $perPage) : 1
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
        $car = DB::table('cars')->where('car_id', $data['car_id'])->first();
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

        DB::table('cars')->where('car_id', $data['car_id'])->update([
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

        DB::transaction(function () use ($car) {
            // 1. Delete the CAR first (removes FK reference)
            DB::table('cars')->where('car_id', $car->car_id)->delete();

            // 2. Now safe to delete the photo
            if ($car->photo_path_id) {
                $photoPath = $this->alreadyExistsImagePath($car->photo_path_id);
                if ($photoPath) {
                    $this->fileService->deleteFile($photoPath);
                }

                // Now safe — no car references this photo_path_id anymore
                DB::table('photo_paths')
                    ->where('photo_path_id', $car->photo_path_id)
                    ->delete();
            }
        });

        return null; // success
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