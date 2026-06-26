<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // $this->call([
        //     UserSeeder::class,
        // ]);

                // 1. Businesses must exist before messages
        $this->call(BusinessSeeder::class);

        // 2. Message flows — one per business type
        $this->call(FoodBusinessSeeder::class);
        $this->call(SalonSeeder::class);
        $this->call(HomeServiceSeeder::class);
    }
}
