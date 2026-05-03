<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::Create([
            'nama_lengkap' => 'admin',
            'username' => 'admin',
            'role' => 'admin',
            'email' => 'admin@ryaze.my.id',
            'password' => Hash::make('admin123'),
        ]);

        User::Create([
            'nama_lengkap' => 'bima',
            'username' => 'bima',
            'role' => 'warga',
            'email' => 'bima@ryaze.my.id',
            'password' => Hash::make('123456'),
        ]);
    }
}
