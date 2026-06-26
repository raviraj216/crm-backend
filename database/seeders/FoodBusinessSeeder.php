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
            'body'        => "I didn't get that.\n\nReply *hi* to see our menu.",
            'next_step'   => null,
        ]);

        $this->command->info(' FoodBusinessSeeder done.');
    }
}