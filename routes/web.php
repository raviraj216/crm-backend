<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;
 

// Route::get('/', function () {
//     return response()->json([
//         'ok' => true,
//         'message' => 'Laravel root route works'
//     ]);
// });

Route::get('/test-template', [WebhookController::class, 'testTemplate']);

Route::get('/', function () {
    return view('welcome');
});


