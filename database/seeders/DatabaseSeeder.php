<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@gmail.com',
            'is_admin' => true,
            'password' => 'admin123',
            'created_at' => now(),
            'updated_at' => now(),
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        User::factory()->create([
            'name' => 'hieu',
            'email' => 'hieu@gmail.com',
            'user_type_id' => 1,
            'password' => 'hieu123',
            'shop_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        User::factory()->create([
            'name' => 'dat',
            'email' => 'dat@gmail.com',
            'user_type_id' => 2,
            'password' => 'dat123',
            'shop_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        DB::table('user_types')->insert(
            [
                'name' => 'seller',
                'description' => 'seller',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 1,
                'updated_by' => 1,
            ],
        );

        DB::table('user_types')->insert(
            [
                'name' => 'support',
                'description' => 'support',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 1,
                'updated_by' => 1,
            ],
        );

        DB::table('shops')->insert([
            'name' => 'Shop Hieu',
            'token' => 'hieu123',
            'created_at' => now(),
            'updated_at' => now(),
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        DB::table('shops')->insert([
            'name' => 'Shop Dat',
            'token' => 'dat123',
            'created_at' => now(),
            'updated_at' => now(),
            'created_by' => 1,
            'updated_by' => 1,
        ]);


    }
}   
