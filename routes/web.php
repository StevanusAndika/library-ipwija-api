<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('generate-member', [UserController::class, 'generateMember']);
Route::get('member-card-pdf', [UserController::class, 'memberCardPdf']);