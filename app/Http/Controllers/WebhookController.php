<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Business; 
use App\Models\WhatsappProduct; 
use App\Models\WhatsappConversation;

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
        // public function receive(Request $request)
        // {
        //     try {

        //         $payload = $request->all();

        //         Log::info('WhatsApp Webhook', $payload);

        //         $entry = $payload['entry'][0] ?? null;

        //         if ($entry) {

        //             $changes = $entry['changes'][0] ?? [];

        //             $messages = $changes['value']['messages'] ?? [];

        //             foreach ($messages as $message) {

        //                 $from = $message['from'] ?? null;

        //                 $text =
        //                     $message['text']['body']
        //                     ?? '';

        //                 Log::info("Message from {$from}: {$text}");

        //                 // Auto reply
        //                 if ($from) {

        //                     $this->sendWhatsAppMessage(
        //                         $from,
        //                         "Thanks! We received: {$text}"
        //                     );
        //                 }
        //             }
        //         }

        //         return response()->json([
        //             'success' => true
        //         ]);

        //     } catch (\Exception $e) {

        //         Log::error($e->getMessage());

        //         return response()->json([
        //             'success' => false
        //         ], 500);
        //     }
        // }

        public function receive(Request $request)
        {
            try {

                $data = $request->all();

                Log::info('Webhook', $data);

                $value =
                    $data['entry'][0]['changes'][0]['value']
                    ?? [];

                // Ignore status callbacks
                if (isset($value['statuses'])) {
                    return response()->json([
                        'ignored' => 'status'
                    ]);
                }

                // Ignore if no messages
                if (!isset($value['messages'])) {
                    return response()->json([
                        'ignored' => true
                    ]);
                }

                $phoneNumberId =
                    $value['metadata']['phone_number_id']
                    ?? null;

                $business = Business::where(
                    'phone_number_id',
                    $phoneNumberId
                )->first();

                if (!$business) {
                    return response()->json();
                }

                foreach ($value['messages'] as $msg) {

                    // Only text messages
                    if (($msg['type'] ?? '') !== 'text') {
                        continue;
                    }

                    $mobile = $msg['from'];

                    $text = trim(
                        strtolower(
                            $msg['text']['body'] ?? ''
                        )
                    );

                    if (isset($msg['from']) && $msg['from'] == $business->phone_number
                    ) {
                        continue;
                    }

                    $this->processMessage(
                        $business,
                        $mobile,
                        $text
                    );
                }

                return response()->json([
                    'success' => true
                ]);

            } catch (\Exception $e) {

                Log::error($e->getMessage());

                return response()->json([
                    'error' => true
                ], 500);
            }
        }

  
    private function sendMessage($business, $mobile, $message)
    {
        $url = "https://graph.facebook.com/v23.0/{$business->phone_number_id}/messages";

        $response = Http::withToken($business->access_token)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $mobile,
                'type' => 'text',
                'text' => [
                    'body' => $message,
                ],
            ]);

        Log::info('WhatsApp response', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        return $response;
    }
    // private function sendWhatsAppMessage($to, $message)
    // {
    //     try {

    //         $url = "https://graph.facebook.com/v23.0/"
    //             . env('WHATSAPP_PHONE_NUMBER_ID')
    //             . "/messages";

    //         $response = Http::withToken(
    //             env('WHATSAPP_TOKEN')
    //         )->post($url, [

    //             'messaging_product' => 'whatsapp',

    //             'to' => $to,

    //             'type' => 'text',

    //             'text' => [
    //                 'body' => $message
    //             ]

    //         ]);

    //         Log::info('Send Message Response', [
    //             'response' => $response->json()
    //         ]);

    //         return $response->successful();

    //     } catch (\Exception $e) {

    //         Log::error($e->getMessage());

    //         return false;
    //     }
    // }

    private function processMessage($business, $mobile, $text)
    {
        if (in_array($text, ['hi', 'hello', 'menu'])) {
            return $this->sendMenu($business, $mobile);
        }

        if (is_numeric($text)) {
            return $this->sendProduct($business, $mobile, $text);
        }

        if (str_contains($text, 'order')) {
            return $this->sendMessage(
                $business,
                $mobile,
                "Please send:\n\nName\nAddress\nQty"
            );
        }
    }

    private function sendMenu($business, $mobile)
    {
        $products = WhatsappProduct::where(
            'business_id',
            $business->id
        )
            ->orderBy('serial')
            ->get();
 

        $msg = "Welcome to {$business->name}\n\nProducts:\n\n";

        foreach ($products as $product) {
            $msg .= "{$product->serial}. {$product->title}\n";
        }

        $msg .= "\nReply number";
 
        return $this->sendMessage(
            $business,
            $mobile,
            $msg
        );
    }

    private function sendProduct($business, $mobile, $number)
    {
        $product = WhatsappProduct::where('business_id', $business->id)
            ->where('serial', $number)
            ->first();

        if (!$product) {
            return null;
        }

        $msg = "
            {$product->title}

            Price:
            ₹{$product->price}

            Ingredients:
            {$product->ingredients}

            Reply:
            ORDER {$number}

            CONTACT";

        return $this->sendMessage(
            $business,
            $mobile,
            trim($msg)
        );
    }
}