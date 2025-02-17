<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiRequestController;


Route::get('/', function () {
    return redirect("/admin");
});


Route::post('/', [ApiRequestController::class, 'captureRequest'])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
Route::put('/', [ApiRequestController::class, 'captureRequest'])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
Route::get('/', [ApiRequestController::class, 'captureRequest']);
Route::delete('/', [ApiRequestController::class, 'captureRequest'])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);