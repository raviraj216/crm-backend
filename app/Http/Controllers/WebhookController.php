<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Business;
use App\Models\WhatsappMessage;
use App\Models\WhatsappConversation;

class WebhookController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // WEBHOOK VERIFICATION
    // ─────────────────────────────────────────────────────────────────────

    public function verify(Request $request)
    {
        if (
            $request->get('hub_mode') === 'subscribe' &&
            $request->get('hub_verify_token') === env('WHATSAPP_VERIFY_TOKEN')
        ) {
            return response($request->get('hub_challenge'), 200);
        }

        return response('Unauthorized', 403);
    }

    // ─────────────────────────────────────────────────────────────────────
    // RECEIVE WEBHOOK
    // ─────────────────────────────────────────────────────────────────────

    public function receive(Request $request)
    {
        try {
            $data  = $request->all();

            $value = $data['entry'][0]['changes'][0]['value'] ?? [];

           // Log::info('Webhook received', $data);

            if (isset($value['statuses'])) {
                return response()->json(['ignored' => 'status']);
            }

            if (!isset($value['messages'])) {
                return response()->json(['ignored' => 'no_messages']);
            }

            $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
             
            $business      = Business::where('phone_number_id', $phoneNumberId)->first();

            if (!$business) {
                return response()->json(['ignored' => 'unknown_business']);
            }

            foreach ($value['messages'] as $msg) {
                if (($msg['type'] ?? '') !== 'text') {
                    continue;
                }

                $mobile = $msg['from'];

                if ($mobile === $business->phone_number) {
                    continue;
                }

                $text = trim(strtolower($msg['text']['body'] ?? ''));

                Log::info('processMessage'.$text, $data);

                $this->processMessage($business, $mobile, $text, $msg['text']['body'] ?? '');
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Webhook error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => true], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // MAIN PROCESSOR — fully database-driven, no hardcoded logic
    // ─────────────────────────────────────────────────────────────────────

    private function processMessage(
        Business $business,
        string $mobile,
        string $text,         // lowercased, for matching
        string $rawText       // original case, for storing in collected_data
    ): void {
        $conversation = WhatsappConversation::firstOrCreate(
            ['business_id' => $business->id, 'mobile' => $mobile],
            ['current_step' => null, 'collected_data' => null]
        );

        $conversation->last_message_at = now();
        $conversation->save();

        // ── 1. Active step takes highest priority ────────────────────────
        if ($conversation->current_step) {
            $stepMessage = WhatsappMessage::where('business_id', $business->id)
                ->where('step', $conversation->current_step)
                ->where('is_active', true)
                ->first();

            if ($stepMessage) {
                // If this step collects data, save the user's raw reply first
                if ($stepMessage->collect_as) {
                    $data = (array) ($conversation->collected_data ?? []);
                    $data[$stepMessage->collect_as] = $rawText;
                    $conversation->collected_data = $data;
                    $conversation->save();
                }

                $this->sendMessage($business, $mobile, $stepMessage, $conversation);
                $this->advanceStep($conversation, $stepMessage->next_step);
                return;
            }
        }

        // ── 2. Keyword matching ──────────────────────────────────────────
        // Load all active, non-fallback messages for this business that have triggers
        $keywordMessages = WhatsappMessage::where('business_id', $business->id)
            ->where('is_active', true)
            ->where('is_fallback', false)
            ->whereNotNull('triggers')
            ->orderByDesc('priority')
            ->get();

        foreach ($keywordMessages as $message) {
            if ($this->matchesTrigger($text, $message)) {
                $this->sendMessage($business, $mobile, $message, $conversation);
                $this->advanceStep($conversation, $message->next_step);
                return;
            }
        }

        // ── 3. Fallback ──────────────────────────────────────────────────
        $fallback = WhatsappMessage::where('business_id', $business->id)
            ->where('is_active', true)
            ->where('is_fallback', true)
            ->first();

        if ($fallback) {
            $this->sendMessage($business, $mobile, $fallback, $conversation);
            $this->advanceStep($conversation, $fallback->next_step);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // TRIGGER MATCHING
    // ─────────────────────────────────────────────────────────────────────

    private function matchesTrigger(string $text, WhatsappMessage $message): bool
    {
        $triggers = (array) ($message->triggers ?? []);

        if (empty($triggers)) {
            return false;
        }

        foreach ($triggers as $trigger) {
            $trigger = strtolower(trim($trigger));

            $matched = match ($message->match_mode) {
                'exact'    => $text === $trigger,
                'contains' => str_contains($text, $trigger),
                'starts'   => str_starts_with($text, $trigger),
                default    => $text === $trigger,
            };

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────
    // SEND (routes to text or template)
    // ─────────────────────────────────────────────────────────────────────

    private function sendMessage(
        Business $business,
        string $mobile,
        WhatsappMessage $message,
        WhatsappConversation $conversation
    ): void {
        if ($message->type === 'template') {
            $this->sendTemplate($business, $mobile, $message, $conversation);
        } else {
            $body = $this->resolvePlaceholders(
                $message->body ?? '',
                $business,
                $conversation
            );
            $this->sendTextMessage($business, $mobile, $body);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PLACEHOLDER RESOLUTION
    // Replaces {key} tokens in body with business fields or collected_data
    // ─────────────────────────────────────────────────────────────────────

    private function resolvePlaceholders(
        string $body,
        Business $business,
        WhatsappConversation $conversation
    ): string {
        // Built-in business placeholders
        $body = str_replace('{business_name}',    $business->name,                             $body);
        $body = str_replace('{business_contact}', $business->contact ?? $business->phone_number, $body);

        // Dynamic placeholders from collected_data
        $collected = (array) ($conversation->collected_data ?? []);

        foreach ($collected as $key => $value) {
            $body = str_replace('{' . $key . '}', $value, $body);
        }

        return $body;
    }

    // ─────────────────────────────────────────────────────────────────────
    // ADVANCE STEP
    // ─────────────────────────────────────────────────────────────────────

    private function advanceStep(WhatsappConversation $conversation, ?string $nextStep): void
    {
        $conversation->current_step = $nextStep;

        // Reset collected_data when conversation ends
        if ($nextStep === null) {
            $conversation->collected_data = null;
        }

        $conversation->save();
    }

    // ─────────────────────────────────────────────────────────────────────
    // SEND TEXT MESSAGE
    // ─────────────────────────────────────────────────────────────────────

    private function sendTextMessage(Business $business, string $mobile, string $body): void
    {
        $url = "https://graph.facebook.com/v23.0/{$business->phone_number_id}/messages";

        $response = Http::withToken($business->access_token)->withOptions([
        'verify' => false // or full path
    ])->post($url, [
            'messaging_product' => 'whatsapp',
            'to'                => $mobile,
            'type'              => 'text',
            'text'              => ['body' => $body],
        ]);

        Log::info('WhatsApp text sent', [
            'to'     => $mobile,
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SEND TEMPLATE MESSAGE
    // template_params is an array of collected_data keys.
    // Each resolves to {{1}}, {{2}}, ... in the Meta template.
    // ─────────────────────────────────────────────────────────────────────

    private function sendTemplate(
        Business $business,
        string $mobile,
        WhatsappMessage $message,
        WhatsappConversation $conversation
    ): void {
        $paramKeys   = (array) ($message->template_params ?? []);
        $collected   = (array) ($conversation->collected_data ?? []);

        $components = [];

        if (!empty($paramKeys)) {
            $parameters = array_map(fn ($key) => [
                'type' => 'text',
                'text' => $collected[$key] ?? '—',
            ], $paramKeys);

            $components[] = [
                'type'       => 'body',
                'parameters' => $parameters,
            ];

            /*just for order template*/
            $components[] = [
                'type' => 'button',
                'sub_type' => 'copy_code',
                'index' => '1',
                'parameters' => [
                    [
                        'type' => 'payload',
                        'payload' => 'TEST50'
                    ]
                ]
            ];
            $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'image',
                                'image' => [
                                'link' => 'https://purple-seal-824227.hostingersite.com/backend/public/logo.png'
                                ]
                            ]
                        ]
                    ];
            /*just for order template*/
        }

        $url = "https://graph.facebook.com/v23.0/{$business->phone_number_id}/messages";

        $response = Http::withToken($business->access_token)->post($url, [
            'messaging_product' => 'whatsapp',
            'to'                => $mobile,
            'type'              => 'template',
            'template'          => [
                'name'       => $message->template_name,
                'language'   => ['code' => 'en'],
                'components' => $components,
            ],
        ]);

        Log::info('WhatsApp template sent', [
            'template' => $message->template_name,
            'to'       => $mobile,
            'params'   => array_combine($paramKeys, array_column($components[0]['parameters'] ?? [], 'text')),
            'status'   => $response->status(),
        ]);
    }



    public function testTemplate()
{
    $business = Business::find(1);

    $url = "https://graph.facebook.com/v23.0/{$business->phone_number_id}/messages";

    $response = Http::withToken($business->access_token)
        ->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => '919977112260',
            'type' => 'template',
            'template' => [
                'name' => 'order_confirmation',
                'language' => [
                    'code' => 'en'
                ],
                'components' => [
                    [
                        'type' => 'button',
                        'sub_type' => 'copy_code',
                        'index' => '1',
                        'parameters' => [
                            [
                                'type' => 'payload',
                                'payload' => 'TEST50'
                            ]
                        ]
                    ],
                    [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'image',
                                'image' => [
                                'link' => 'https://purple-seal-824227.hostingersite.com/backend/public/logo.png'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => 'John'
                            ],
                            [
                                'type' => 'text',
                                'text' => '2'
                            ],
                            [
                                'type' => 'text',
                                'text' => 'LIG'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

    return $response->json();
}


}