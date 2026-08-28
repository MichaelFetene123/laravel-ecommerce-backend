<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Store Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        Address::firstOrCreate(
            ['user_id' => $admin->id, 'line1' => 'Bole Sub-City, Road 20'],
            [
                'full_name' => 'Store Administrator',
                'city' => 'Addis Ababa',
                'region' => 'Addis Ababa',
                'country' => 'Ethiopia',
                'postal_code' => '1000',
                'phone' => '+251911000001',
            ]
        );

        $users = [
            [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'addresses' => [
                    [
                        'full_name' => 'John Doe',
                        'line1' => 'Cameroon Street, House 102',
                        'line2' => 'Apartment 4B',
                        'city' => 'Addis Ababa',
                        'region' => 'Bole',
                        'country' => 'Ethiopia',
                        'postal_code' => '1000',
                        'phone' => '+251911223344',
                    ],
                ],
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane.smith@example.com',
                'addresses' => [
                    [
                        'full_name' => 'Jane Smith',
                        'line1' => 'Churchill Avenue, Plaza Bldg 3F',
                        'line2' => null,
                        'city' => 'Addis Ababa',
                        'region' => 'Kirkos',
                        'country' => 'Ethiopia',
                        'postal_code' => '1000',
                        'phone' => '+251922334455',
                    ],
                ],
            ],
            [
                'name' => 'Abebe Kebede',
                'email' => 'abebe.kebede@example.com',
                'addresses' => [
                    [
                        'full_name' => 'Abebe Kebede',
                        'line1' => 'Kazanchis Business Park',
                        'line2' => 'Tower A, Office 501',
                        'city' => 'Addis Ababa',
                        'region' => 'Yeka',
                        'country' => 'Ethiopia',
                        'postal_code' => '1000',
                        'phone' => '+251933445566',
                    ],
                ],
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            foreach ($userData['addresses'] as $addrData) {
                Address::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'line1' => $addrData['line1'],
                    ],
                    $addrData
                );
            }
        }
    }
}
