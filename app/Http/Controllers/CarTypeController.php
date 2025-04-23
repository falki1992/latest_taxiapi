<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarProductionYear;
use App\Models\CarType;
use App\Models\DriverCarType;
use App\Models\DriverVehicleType;
use Illuminate\Http\Request;

class CarTypeController extends Controller
{
    public function index()
    {
        $carTypes = CarType::where('variables', 'local')->paginate(10);
        $response = [
            'status_code' => '200',
            'message' => 'Success',
            'result' => $carTypes
        ];

        return response($response, 200);
    }

    public function cityToCityCarTypes()
    {
        $carTypes = CarType::where('variables', 'city_to_city')->paginate(10);
        $response = [
            'status_code' => '200',
            'message' => 'Success',
            'result' => $carTypes
        ];
        return response($response, 200);

    }

    public function freightCarTypes()
    {
        $carTypes = CarType::where('variables', 'freight')->paginate(10);
        $response = [
            'status_code' => '200',
            'message' => 'Success',
            'result' => $carTypes
        ];
        return response($response, 200);

    }

    public function getDriverCarType()
    {
        $carTypes = CarType::paginate(10);
        $response = [
            'status_code' => '200',
            'message' => 'Success',
            'result' => $carTypes
        ];
        return response($response, 200);
    }

    public function getDriverVehicleType()
    {
        $vehicleTypes = CarType::select('id', 'name', 'car_type')->where('variables', 'local')

            ->paginate(10);
        $response = [
            'status_code' => '200',
            'message' => 'Success',
            'result' => $vehicleTypes

        ];
        return response($response, 200);

    }

    public function getCars(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $cars = Car::paginate($perPage);
        $response = [
            'status_code' => '200',
            'message' => 'Success',
            'result' => $cars

        ];
        return response($response, 200);

    }
    public function getProductionYear()
    {
        $cars = CarProductionYear::paginate(10);
        $response = [
            'status_code' => '200',
            'message' => 'Success',
            'result' => $cars

        ];
        return response($response, 200);

    }
}
