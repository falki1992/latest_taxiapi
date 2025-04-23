<?php

namespace Database\Seeders;

use App\Models\UserType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'name' => 'User',
                'status' => 1
            ],
            [
                'name' => 'Customer',
                'status' => 1
            ],

            // Add more users as needed
        ];
        foreach ($data as $d) {
            UserType::create([
                'name' => $d['name'],
                'status' => $d['status'],

            ]);
        }
    }
}
