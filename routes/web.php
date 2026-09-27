<?php

use App\Http\Controllers\Admin\AuthController as AdminAuth;
use App\Http\Controllers\Admin\ClinicController as AdminClinics;
use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\Api\FileController;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('landing', ['plans' => Plan::where('is_active', true)->orderBy('sort')->get()]));

// The PWA shell lives in public/app (static, cached by its service worker).
Route::redirect('/app', '/app/');

Route::get('/files/{file}', [FileController::class, 'show'])
    ->whereNumber('file')->middleware('signed')->name('files.show');

Route::get('/rx-template/{clinic}/{name}', [ClinicController::class, 'showRxTemplate'])
    ->whereNumber('clinic')->middleware('signed')->name('rx-template.show');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuth::class, 'show'])->name('login');
    Route::post('/login', [AdminAuth::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [AdminAuth::class, 'logout'])->name('logout');

    Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
        Route::get('/', [AdminClinics::class, 'index'])->name('clinics');
        Route::patch('/clinics/{clinic}', [AdminClinics::class, 'update'])->name('clinics.update');
    });
});
