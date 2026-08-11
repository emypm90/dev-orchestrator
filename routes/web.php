<?php

use App\Http\Controllers\OperationalTicketController;
use App\Http\Controllers\TaskDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TaskDashboardController::class, 'index'])->name('tasks.index');
Route::get('/operational-tickets', [OperationalTicketController::class, 'index'])->name('operational-tickets.index');
Route::get('/operational-tickets/create', [OperationalTicketController::class, 'create'])->name('operational-tickets.create');
Route::post('/operational-tickets', [OperationalTicketController::class, 'store'])->name('operational-tickets.store');
Route::get('/operational-tickets/{operationalTicket}', [OperationalTicketController::class, 'show'])->name('operational-tickets.show');
Route::get('/tasks/{task}', [TaskDashboardController::class, 'show'])->name('tasks.show');
Route::post('/tasks/{task}/approve', [TaskDashboardController::class, 'approve'])->name('tasks.approve');
Route::post('/tasks/{task}/revision', [TaskDashboardController::class, 'revision'])->name('tasks.revision');
Route::post('/tasks/{task}/reject', [TaskDashboardController::class, 'reject'])->name('tasks.reject');
Route::post('/tasks/{task}/archive', [TaskDashboardController::class, 'archive'])->name('tasks.archive');
Route::get('/tasks/{task}/artifacts', [TaskDashboardController::class, 'showArtifact'])
    ->name('tasks.artifacts.show');
