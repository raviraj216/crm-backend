<?php

use Illuminate\Support\Facades\Route;

 

// Route::get('/', function () {
//     return response()->json([
//         'ok' => true,
//         'message' => 'Laravel root route works'
//     ]);
// });

Route::get('/', function () {
    return view('welcome');
});
