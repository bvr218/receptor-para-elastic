<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiRequestController;




Route::any('/', [ApiRequestController::class, 'captureRequest'])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
