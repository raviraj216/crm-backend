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

class FoodBusinessSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::where('name', 'Indori Premix')->firstOrFail();
        $bid = $business->id;

        WhatsappMessage::where('business_id', $bid)->delete();

        // 1. Greeting → menu
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Greeting / Menu',
            'triggers'    => ['hi', 'hello', 'menu', 'start'],
            'match_mode'  => 'exact',
            'priority'    => 10,
            'type'        => 'text',
            'body'        =>
                "Welcome to *{business_name}*!\n\n" .
                "Our Instant Cook range:\n\n" .
                "1. Ragi Dosa Mix\n" .
                "2. Badam Khaskas Halwa Mix\n" .
                "3. Puran Poli Mix\n" .
                "4. Mung Dal Halwa Mix\n" .
                "5. Sorghum Dosa Mix\n\n" .
                "Reply a *number* to know more.",
            'next_step'   => null,
        ]);

        // 2. Item detail (contains match so "1", "2" … all route here)
        //    In a real setup you'd have one message per item;
        //    here we show a generic "reply number" handler.
        //    Use match_mode=exact with triggers=["1"] per product, or
        //    handle dynamically in a separate lookup message per serial.
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Item 1 — Ragi Dosa Mix',
            'triggers'    => ['1'],
            'match_mode'  => 'exact',
            'priority'    => 9,
            'type'        => 'text',
            'body'        =>
                "*Ragi Dosa Mix — Instant Cook*\n\n" .
                "Price: ₹180 / 500g\n\n" .
                "Just add water and cook in 5 minutes.\n\n" .
                "To order reply: *ORDER RAGI*\n" .
                "Contact: {business_contact}",
            'next_step'   => null,
        ]);

        // (Add messages for items 2-5 the same way)

        // 3. Order trigger → ask for name (step 1 of 3)
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Order — ask name',
            'triggers'    => ['order'],
            'match_mode'  => 'starts',
            'priority'    => 8,
            'type'        => 'text',
            'body'        =>
                "Great! Let's place your order.\n\n" .
                "Please send your *full name*:",
            'next_step'   => 'order_name',
        ]);

        // 4. step=order_name → save name, ask for address
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Order — ask address',
            'step'        => 'order_name',
            'collect_as'  => 'name',
            'type'        => 'text',
            'body'        => "Thanks, {name}!\n\nNow send your *delivery address*:",
            'next_step'   => 'order_address',
        ]);

        // 5. step=order_address → save address, ask for qty
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Order — ask quantity',
            'step'        => 'order_address',
            'collect_as'  => 'address',
            'type'        => 'text',
            'body'        => "How many packs would you like?\n\nSend the *quantity*:",
            'next_step'   => 'order_qty',
        ]);

        // 6. step=order_qty → save qty, send template confirmation
        //    Meta template "order_confirmation":
        //    "Hi {{1}}, your order for {{2}} pack(s) has been placed!
        //     Delivery to: {{3}}. We'll call you to confirm."
        WhatsappMessage::create([
            'business_id'     => $bid,
            'label'           => 'Order — confirmation template',
            'step'            => 'order_qty',
            'collect_as'      => 'quantity',
            'type'            => 'template',
            'template_name'   => 'order_confirmation',
            'template_params' => ['name', 'quantity', 'address'],
            'next_step'       => null,   // resets conversation
        ]);

        // 7. Fallback
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Fallback',
            'is_fallback' => true,
            'type'        => 'text',
            'body'        => "🤔 I didn't get that.\n\nReply *hi* to see our menu.",
            'next_step'   => null,
        ]);

        $this->command->info('✅ FoodBusinessSeeder done.');
    }
}


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
                "💅 Welcome to *{business_name}*!\n\n" .
                "Our services:\n\n" .
                "✂️ Haircut & styling\n" .
                "🧖 Facial & cleanup\n" .
                "💆 Head massage\n" .
                "💇 Hair colour\n\n" .
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
                "✂️ *Haircut & Styling*\n\n" .
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
            'body'        => "📅 Let's book you in!\n\nPlease send your *name*:",
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
            'body'        => "I didn't understand that 😊\n\nReply *hi* to see our services.",
            'next_step'   => null,
        ]);

        $this->command->info('✅ SalonSeeder done.');
    }
}


// ═══════════════════════════════════════════════════════════════════════
// EXAMPLE 3 — Home repair / service business
//
// Flow:
//   hi        → show service types (text)
//   ac / plumbing / electric → service info + ask to raise request (text)
//   request   → ask for name
//   <name>    → ask for issue description
//   <issue>   → ask for address
//   <address> → send request raised template (TEMPLATE)
//   anything  → fallback
// ═══════════════════════════════════════════════════════════════════════

class HomeServiceSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::where('name', 'FixIt Home Services')->firstOrFail();
        $bid = $business->id;

        WhatsappMessage::where('business_id', $bid)->delete();

        // 1. Greeting
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Greeting',
            'triggers'    => ['hi', 'hello', 'help', 'services'],
            'match_mode'  => 'exact',
            'priority'    => 10,
            'type'        => 'text',
            'body'        =>
                "🔧 Welcome to *{business_name}*!\n\n" .
                "We provide:\n\n" .
                "❄️ AC service & repair\n" .
                "🚿 Plumbing\n" .
                "⚡ Electrician\n" .
                "🪟 Carpenter\n\n" .
                "Reply service name or *REQUEST* to raise a complaint.",
            'next_step'   => null,
        ]);

        // 2. AC service info
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Service — AC',
            'triggers'    => ['ac', 'air condition', 'cooling'],
            'match_mode'  => 'contains',
            'priority'    => 9,
            'type'        => 'text',
            'body'        =>
                "❄️ *AC Service & Repair*\n\n" .
                "Service charge: ₹399 (visit fee)\n" .
                "Gas refill, motor repair quoted on-site.\n" .
                "Same-day slots available.\n\n" .
                "Reply *REQUEST* to book a technician.",
            'next_step'   => null,
        ]);

        // 3. Request trigger → ask name
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Request — ask name',
            'triggers'    => ['request', 'complaint', 'book', 'technician'],
            'match_mode'  => 'contains',
            'priority'    => 8,
            'type'        => 'text',
            'body'        => "📋 Let's log your request.\n\nPlease send your *full name*:",
            'next_step'   => 'req_name',
        ]);

        // 4. step=req_name → save name, ask issue
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Request — ask issue',
            'step'        => 'req_name',
            'collect_as'  => 'name',
            'type'        => 'text',
            'body'        => "Thanks *{name}*!\n\nBriefly describe the *issue*:",
            'next_step'   => 'req_issue',
        ]);

        // 5. step=req_issue → save issue, ask address
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Request — ask address',
            'step'        => 'req_issue',
            'collect_as'  => 'issue',
            'type'        => 'text',
            'body'        => "Got it. Now send your *full address* (with flat/house number):",
            'next_step'   => 'req_address',
        ]);

        // 6. step=req_address → save address, send template
        //    Meta template "service_request_raised":
        //    "Hi {{1}}, your service request for '{{2}}' has been raised.
        //     Our technician will visit {{3}} within 24 hours. Ref: {{4}}"
        WhatsappMessage::create([
            'business_id'     => $bid,
            'label'           => 'Request — confirmation template',
            'step'            => 'req_address',
            'collect_as'      => 'address',
            'type'            => 'template',
            'template_name'   => 'service_request_raised',
            'template_params' => ['name', 'issue', 'address'],
            'next_step'       => null,
        ]);

        // 7. Fallback
        WhatsappMessage::create([
            'business_id' => $bid,
            'label'       => 'Fallback',
            'is_fallback' => true,
            'type'        => 'text',
            'body'        => "Sorry, I didn't understand that.\n\nReply *hi* to see our services.",
            'next_step'   => null,
        ]);

        $this->command->info('✅ HomeServiceSeeder done.');
    }
}
