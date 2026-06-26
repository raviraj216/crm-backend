<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Business;

/**
 * Seeds three demo businesses — one per type used in ExampleSeeders.php.
 *
 * IMPORTANT: Replace every ACCESS_TOKEN and PHONE_NUMBER_ID with real
 * values from your Meta for Developers app before running in production.
 *
 * Run:
 *   php artisan db:seed --class=BusinessSeeder
 *
 * Then run the matching message seeder for each business:
 *   php artisan db:seed --class=FoodBusinessSeeder
 *   php artisan db:seed --class=SalonSeeder
 *   php artisan db:seed --class=HomeServiceSeeder
 */
class BusinessSeeder extends Seeder
{
    public function run(): void
    {
        $businesses = [

            // ── 1. Food / Premix ─────────────────────────────────────────
            [
                'name'            => 'Indori Premix',
                'phone_number_id' => '111111111111111',      // ← replace
                'phone_number'    => '919876540001',          // ← replace (country code + number, no +)
                'access_token'    => 'EAAxxxxFOOD_TOKEN',     // ← replace
                'contact'         => '919876540001',
                'category'        => 'food',
                'city'            => 'Indore',
                'address'         => 'MG Road, Indore, MP 452001',
                'website'         => 'https://indoripremix.example.com',
                'is_active'       => true,
            ],

            // ── 2. Salon ─────────────────────────────────────────────────
            [
                'name'            => 'Glamour Studio',
                'phone_number_id' => '222222222222222',       // ← replace
                'phone_number'    => '919876540002',           // ← replace
                'access_token'    => 'EAAxxxxSALON_TOKEN',    // ← replace
                'contact'         => '919876540002',
                'category'        => 'salon',
                'city'            => 'Jabalpur',
                'address'         => 'Wright Town, Jabalpur, MP 482002',
                'website'         => null,
                'is_active'       => true,
            ],

            // ── 3. Home service ──────────────────────────────────────────
            [
                'name'            => 'FixIt Home Services',
                'phone_number_id' => '333333333333333',       // ← replace
                'phone_number'    => '919876540003',           // ← replace
                'access_token'    => 'EAAxxxxFIXIT_TOKEN',    // ← replace
                'contact'         => '919876540003',
                'category'        => 'home_service',
                'city'            => 'Bhopal',
                'address'         => 'MP Nagar, Bhopal, MP 462011',
                'website'         => 'https://fixit.example.com',
                'is_active'       => true,
            ],

        ];

        foreach ($businesses as $data) {
            Business::updateOrCreate(
                ['phone_number_id' => $data['phone_number_id']],
                $data
            );

            $this->command->info("✅ Business seeded: {$data['name']}");
        }
    }
}
