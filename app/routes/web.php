<?php

use App\Http\Controllers\TargetController;
use App\Http\Controllers\RepositoryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Index');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/repositories', [RepositoryController::class, 'index'])->name('repositories.index');
    Route::get('/targets', [TargetController::class, 'index'])->name('targets.index');
    Route::post('/targets', [TargetController::class, 'store'])->name('targets.store');
    Route::post('/targets/{target}/sync', [TargetController::class, 'sync'])->name('targets.sync');
    Route::delete('/targets/{target}', [TargetController::class, 'destroy'])->name('targets.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
