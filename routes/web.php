<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('generate-member', [UserController::class, 'generateMember']);
Route::get('member-card-pdf', [UserController::class, 'memberCardPdf']);
// Serve storage files with CORS headers to support cross-origin image requests
Route::get('storage/{path}', function ($path) {
    $full = storage_path('app/public/' . $path);
    if (!file_exists($full)) {
        abort(404);
    }

    return response()->file($full, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
    ]);
})->where('path', '.*');

// Alternative files route to bypass static file serving and ensure CORS headers
Route::get('files/{path}', function ($path) {
    $full = storage_path('app/public/' . $path);
    if (!file_exists($full)) {
        abort(404);
    }

    return response()->file($full, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
    ]);
})->where('path', '.*');
