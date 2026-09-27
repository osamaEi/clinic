<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureClinicMember;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::get('/plans', fn () => Plan::where('is_active', true)->orderBy('sort')->get()
    ->map(fn ($p) => ['slug' => $p->slug, 'name' => $p->name, 'price' => $p->price_monthly + 0, 'limits' => $p->limits()]));

Route::middleware(['auth:sanctum', EnsureClinicMember::class])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/sync', [SyncController::class, 'pull']);
    Route::post('/sync', [SyncController::class, 'push']);
    Route::post('/files', [FileController::class, 'store']);

    Route::put('/clinic', [ClinicController::class, 'update']);
    Route::put('/clinic/rx', [ClinicController::class, 'updateRx']);
    Route::post('/clinic/rx-template', [ClinicController::class, 'uploadRxTemplate']);
    Route::delete('/clinic/rx-template', [ClinicController::class, 'deleteRxTemplate']);
    Route::apiResource('users', UserController::class)->except('show');
});
