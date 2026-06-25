<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WebhookController extends Controller
{
    /**
     * WhatsApp webhook verification
     */
    public function verify(Request $request)
    {
        $verifyToken = env('WHATSAPP_VERIFY_TOKEN');

        if (
            $request->get('hub_mode') === 'subscribe' &&
            $request->get('hub_verify_token') === $verifyToken
        ) {
            return response(
                $request->get('hub_challenge'),
                200
            );
        }

        return response('Unauthorized', 403);
    }

    /**
     * Receive webhook data
     */
        public function receive(Request $request)
        {
            try {

                $payload = $request->all();

                Log::info('WhatsApp Webhook', $payload);

                $entry = $payload['entry'][0] ?? null;

                if ($entry) {

                    $changes = $entry['changes'][0] ?? [];

                    $messages = $changes['value']['messages'] ?? [];

                    foreach ($messages as $message) {

                        $from = $message['from'] ?? null;

                        $text =
                            $message['text']['body']
                            ?? '';

                        Log::info("Message from {$from}: {$text}");

                        // Auto reply
                        if ($from) {

                            $this->sendWhatsAppMessage(
                                $from,
                                "Thanks! We received: {$text}"
                            );
                        }
                    }
                }

                return response()->json([
                    'success' => true
                ]);

            } catch (\Exception $e) {

                Log::error($e->getMessage());

                return response()->json([
                    'success' => false
                ], 500);
            }
        }


  
    private function sendWhatsAppMessage($to, $message)
    {
        try {

            $url = "https://graph.facebook.com/v23.0/"
                . env('WHATSAPP_PHONE_NUMBER_ID')
                . "/messages";

            $response = Http::withToken(
                env('WHATSAPP_TOKEN')
            )->post($url, [

                'messaging_product' => 'whatsapp',

                'to' => $to,

                'type' => 'text',

                'text' => [
                    'body' => $message
                ]

            ]);

            Log::info('Send Message Response', [
                'response' => $response->json()
            ]);

            return $response->successful();

        } catch (\Exception $e) {

            Log::error($e->getMessage());

            return false;
        }
    }
}