<?php

use App\Http\Controllers\IngestController;
use App\Http\Middleware\AuthenticateIngest;
use Illuminate\Support\Facades\Route;

Route::post('/v1/logs', IngestController::class)->middleware(AuthenticateIngest::class);
