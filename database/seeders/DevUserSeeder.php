<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DevUserSeeder extends Seeder
{
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'dev@ccpja.test'],
            [
                'name' => 'Dev CCPJA',
                'password' => Hash::make('senha123'),
                'email_verified_at' => now(),
            ]
        );
    }
}
