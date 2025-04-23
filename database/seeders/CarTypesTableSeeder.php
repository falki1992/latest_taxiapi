<?php

namespace Database\Seeders;

use App\Models\CarType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CarTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'name' => 'Economy',
                'status' => 1
            ],
            [
                'name' => 'Comfort',
                'status' => 1
            ],
            [
                'name' => 'Mini',
                'status' => 1
            ],
            [
                'name' => 'Rikshaw',
                'status' => 1
            ],
            [
                'name' => 'Bike',
                'status' => 1
            ],

            // Add more users as needed
        ];
        foreach ($data as $d) {
            CarType::create([
                'name' => $d['name'],
                'status' => $d['status'],

            ]);
        }
    }
}
