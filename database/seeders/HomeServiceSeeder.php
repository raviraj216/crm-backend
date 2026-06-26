<?php

 

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
                "Welcome to *{business_name}*!\n\n" .
                "We provide:\n\n" .
                "AC service & repair\n" .
                "Plumbing\n" .
                "Electrician\n" .
                "Carpenter\n\n" .
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
                "*AC Service & Repair*\n\n" .
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
            'body'        => "Let's log your request.\n\nPlease send your *full name*:",
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
