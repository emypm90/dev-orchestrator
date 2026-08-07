<?php

use App\Http\Controllers\TaskDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TaskDashboardController::class, 'index'])->name('tasks.index');
Route::get('/tasks/{task}', [TaskDashboardController::class, 'show'])->name('tasks.show');
Route::post('/tasks/{task}/approve', [TaskDashboardController::class, 'approve'])->name('tasks.approve');
Route::post('/tasks/{task}/revision', [TaskDashboardController::class, 'revision'])->name('tasks.revision');
Route::post('/tasks/{task}/reject', [TaskDashboardController::class, 'reject'])->name('tasks.reject');
Route::get('/tasks/{task}/artifacts/{artifact}', [TaskDashboardController::class, 'showArtifact'])
    ->where('artifact', '[^/]+')
    ->name('tasks.artifacts.show');
