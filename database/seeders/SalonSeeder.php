<?php

/**
 * Three example seeders showing the same system used for
 * completely different business types.
 *
 * Run any one of them:
 *   php artisan db:seed --class=FoodBusinessSeeder
 *   php artisan db:seed --class=SalonSeeder
 *   php artisan db:seed --class=HomeServiceSeeder
 */

// ═══════════════════════════════════════════════════════════════════════
// EXAMPLE 1 — Food / Premix business (Indori Premix)
//
// Flow:
//   hi       → welcome + menu list (text)
//   1/2/3    → item detail with price (text)
//   order    → ask for Name / Address / Qty (text, starts collecting)
//   <name>   → ask for address (collect name, text)
//   <addr>   → ask for qty (collect address, text)
//   <qty>    → send order confirmation (collect qty, TEMPLATE)
//   anything → fallback
// ═══════════════════════════════════════════════════════════════════════

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WhatsappMessage;
use App\Models\Business;

 

// ═══════════════════════════════════════════════════════════════════════
// EXAMPLE 2 — Salon / beauty service
//
// Flow:
//   hi/book  → show services list (text)
//   haircut / facial / etc. → service details + ask to book (text)
//   book     → ask for name (text, starts collecting)
//   <name>   → ask for date/time (collect name, text)
//   <date>   → send appointment template (collect date, TEMPLATE)
//   anything → fallback
// ═══════════════════════════════════════════════════════════════════════

class SalonSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::where('name', 'Glamour Studio')->firstOrFail();
        $bid = $business->id;

        WhatsappMessage::where('business_id', $bid)->delete();

        // 1. Greeting → service list
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Greeting',
            'triggers'    => ['hi', 'hello', 'services', 'menu'],
            'match_mode'  => 'exact',
            'priority'    => 10,
            'type'        => 'text',
            'body'        =>
                "Welcome to *{business_name}*!\n\n" .
                "Our services:\n\n" .
                "Haircut & styling\n" .
                "Facial & cleanup\n" .
                "Head massage\n" .
                "Hair colour\n\n" .
                "Reply a service name or *BOOK* to book an appointment.",
            'next_step'   => null,
        ]);

        // 2. Service detail — haircut
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Service — Haircut',
            'triggers'    => ['haircut', 'cut', 'styling'],
            'match_mode'  => 'contains',
            'priority'    => 9,
            'type'        => 'text',
            'body'        =>
                "*Haircut & Styling*\n\n" .
                "Starting ₹299 | ~45 min\n\n" .
                "Includes wash, cut, and blow-dry.\n\n" .
                "Reply *BOOK* to schedule.",
            'next_step'   => null,
        ]);

        // 3. Booking trigger → ask name
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Book — ask name',
            'triggers'    => ['book', 'appointment', 'slot'],
            'match_mode'  => 'contains',
            'priority'    => 8,
            'type'        => 'text',
            'body'        => "Let's book you in!\n\nPlease send your *name*:",
            'next_step'   => 'book_name',
        ]);

        // 4. step=book_name → save name, ask service
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Book — ask service',
            'step'        => 'book_name',
            'collect_as'  => 'name',
            'type'        => 'text',
            'body'        => "Hi *{name}*! Which service would you like?\n\n(e.g. Haircut, Facial, Massage)",
            'next_step'   => 'book_service',
        ]);

        // 5. step=book_service → save service, ask date
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Book — ask date',
            'step'        => 'book_service',
            'collect_as'  => 'service',
            'type'        => 'text',
            'body'        => "Great choice! When would you like your *{service}*?\n\nSend preferred date and time (e.g. Mon 3pm):",
            'next_step'   => 'book_datetime',
        ]);

        // 6. step=book_datetime → save datetime, send template
        //    Meta template "appointment_confirmation":
        //    "Hi {{1}}, your {{2}} appointment is confirmed for {{3}} at {business_name}! See you soon."
        WhatsappMessage::create([
            'business_id'     => $bid,
            'label'           => 'Book — confirmation template',
            'step'            => 'book_datetime',
            'collect_as'      => 'datetime',
            'type'            => 'template',
            'template_name'   => 'appointment_confirmation',
            'template_params' => ['name', 'service', 'datetime'],
            'next_step'       => null,
        ]);

        // 7. Fallback
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Fallback',
            'is_fallback' => true,
            'type'        => 'text',
            'body'        => "I didn't understand that \n\nReply *hi* to see our services.",
            'next_step'   => null,
        ]);

        $this->command->info('✅ SalonSeeder done.');
    }
}
