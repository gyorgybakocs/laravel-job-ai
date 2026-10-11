<?php

use App\Http\Controllers\TargetController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Index');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/targets', [TargetController::class, 'index'])->name('targets.index');
    Route::post('/targets', [TargetController::class, 'store'])->name('targets.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
