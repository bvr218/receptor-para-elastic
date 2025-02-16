<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiRequestController;


Route::get('/', function () {
    return redirect("/admin");
});


Route::post('/', [ApiRequestController::class, 'Request'])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
Route::put('/', [ApiRequestController::class, 'Request'])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
Route::get('/', [ApiRequestController::class, 'Request']);
Route::delete('/', [ApiRequestController::class, 'Request'])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);