<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiRequestController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::post('/capture', [ApiRequestController::class, 'captureRequest']);
Route::put('/capture', [ApiRequestController::class, 'captureRequest']);
Route::get('/capture', [ApiRequestController::class, 'captureRequest']);
Route::delete('/capture', [ApiRequestController::class, 'captureRequest']);
