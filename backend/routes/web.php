<?php

use Illuminate\Support\Facades\Route;

// The frontend (hosted verification SPA) is served by nginx from frontend/dist;
// Laravel only serves the JSON API under /api and this health root.
Route::get('/', function () {
    return response()->json([
        'service' => 'verify.pollus.tech',
        'status' => 'ok',
    ]);
});
