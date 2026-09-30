<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MailCampaignController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PersonRecordController;
use App\Http\Controllers\PersonRecordPhotoController;
use App\Http\Controllers\PersonRequestController;
use App\Http\Controllers\PersonRequestPhotoController;
use App\Http\Controllers\PhotoSearchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StateStatisticsController;
use App\Http\Controllers\StatisticsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/base-de-datos', [PersonRecordController::class, 'index'])
    ->middleware('throttle:records')
    ->name('records');
Route::get('/base-de-datos/{personRecord}/foto', [PersonRecordPhotoController::class, 'show'])
    ->middleware('throttle:photos')
    ->name('records.photo');
Route::get('/estadisticas', StatisticsController::class)
    ->middleware('throttle:statistics')
    ->name('statistics');
Route::get('/estadisticas/{state}', StateStatisticsController::class)
    ->middleware('throttle:statistics')
    ->name('statistics.state');
Route::get('/busqueda-por-fotografia', [PhotoSearchController::class, 'show'])->name('photo-search');
Route::post('/busqueda-por-fotografia', [PhotoSearchController::class, 'store'])
    ->middleware('throttle:photo-search')
    ->name('photo-search.store');
Route::get('/solicitudes', [PersonRequestController::class, 'index'])->name('requests');
Route::post('/solicitudes', [PersonRequestController::class, 'store'])
    ->middleware('throttle:person-requests')
    ->name('requests.store');
Route::get('/solicitudes/{personRequest}/foto', [PersonRequestPhotoController::class, 'show'])
    ->middleware('throttle:photos')
    ->name('requests.photo');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/usuarios', [AdminUserController::class, 'index'])->name('users');
    Route::patch('/usuarios/{user}/admin', [AdminUserController::class, 'toggleAdmin'])->name('users.admin');
    Route::post('/usuarios/{user}/verificar', [AdminUserController::class, 'verify'])->name('users.verify');
    Route::post('/usuarios/{user}/reenviar', [AdminUserController::class, 'resendVerification'])
        ->middleware('throttle:6,1')
        ->name('users.resend');
    Route::get('/correos', [MailCampaignController::class, 'index'])->name('mail');
    Route::post('/correos/vista-previa', [MailCampaignController::class, 'preview'])->name('mail.preview');
    Route::post('/correos/prueba', [MailCampaignController::class, 'test'])
        ->middleware('throttle:10,1')
        ->name('mail.test');
    Route::post('/correos', [MailCampaignController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('mail.store');
});

require __DIR__.'/auth.php';
