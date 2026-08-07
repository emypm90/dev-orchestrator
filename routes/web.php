<?php

use App\Http\Controllers\TaskDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TaskDashboardController::class, 'index'])->name('tasks.index');
Route::get('/tasks/{task}', [TaskDashboardController::class, 'show'])->name('tasks.show');
Route::get('/tasks/{task}/artifacts/{artifact}', [TaskDashboardController::class, 'showArtifact'])
    ->where('artifact', '[^/]+')
    ->name('tasks.artifacts.show');
