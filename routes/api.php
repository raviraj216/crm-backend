<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WebhookController;

Route::get('/webhook', [WebhookController::class, 'verify']);
Route::post('/webhook', [WebhookController::class, 'receive']);

Route::post('/login',[AuthController::class,'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user',[
        AuthController::class,
        'user'
    ]);

    Route::post('/logout',[
        AuthController::class,
        'logout'
    ]);

});