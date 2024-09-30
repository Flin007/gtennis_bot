<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhook', WebhookController::class);
