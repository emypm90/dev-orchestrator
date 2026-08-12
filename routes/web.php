<?php

use App\Http\Controllers\EmailThreadImportController;
use App\Http\Controllers\GmailIntegrationController;
use App\Http\Controllers\OperationalTicketController;
use App\Http\Controllers\TaskDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TaskDashboardController::class, 'index'])->name('tasks.index');
Route::get('/integrations/gmail', [GmailIntegrationController::class, 'index'])->name('integrations.gmail.index');
Route::post('/integrations/gmail/connect', [GmailIntegrationController::class, 'connect'])->name('integrations.gmail.connect');
Route::get('/integrations/gmail/oauth/callback', [GmailIntegrationController::class, 'callback'])->name('integrations.gmail.callback');
Route::post('/integrations/gmail/disconnect', [GmailIntegrationController::class, 'disconnect'])->name('integrations.gmail.disconnect');
Route::get('/email-thread-imports/create', [EmailThreadImportController::class, 'create'])->name('email-thread-imports.create');
Route::post('/email-thread-imports', [EmailThreadImportController::class, 'store'])->name('email-thread-imports.store');
Route::get('/email-thread-imports/{emailThreadImport}', [EmailThreadImportController::class, 'show'])->name('email-thread-imports.show');
Route::post('/email-thread-imports/{emailThreadImport}/create-ticket', [EmailThreadImportController::class, 'createTicket'])->name('email-thread-imports.create-ticket');
Route::get('/operational-tickets', [OperationalTicketController::class, 'index'])->name('operational-tickets.index');
Route::get('/operational-tickets/create', [OperationalTicketController::class, 'create'])->name('operational-tickets.create');
Route::post('/operational-tickets', [OperationalTicketController::class, 'store'])->name('operational-tickets.store');
Route::get('/operational-tickets/{operationalTicket}', [OperationalTicketController::class, 'show'])->name('operational-tickets.show');
Route::patch('/operational-tickets/{operationalTicket}', [OperationalTicketController::class, 'update'])->name('operational-tickets.update');
Route::post('/operational-tickets/{operationalTicket}/convert', [OperationalTicketController::class, 'convert'])->name('operational-tickets.convert');
Route::patch('/operational-tickets/{operationalTicket}/report', [OperationalTicketController::class, 'updateReport'])->name('operational-tickets.report.update');
Route::post('/operational-tickets/{operationalTicket}/report/mark-reported', [OperationalTicketController::class, 'markReported'])->name('operational-tickets.report.mark-reported');
Route::patch('/operational-tickets/{operationalTicket}/hours', [OperationalTicketController::class, 'updateHours'])->name('operational-tickets.hours.update');
Route::post('/operational-tickets/{operationalTicket}/hours/mark-recorded', [OperationalTicketController::class, 'markHoursRecorded'])->name('operational-tickets.hours.mark-recorded');
Route::get('/tasks/{task}', [TaskDashboardController::class, 'show'])->name('tasks.show');
Route::get('/tasks/{task}/diff', [TaskDashboardController::class, 'diff'])->name('tasks.diff');
Route::post('/tasks/{task}/approve', [TaskDashboardController::class, 'approve'])->name('tasks.approve');
Route::post('/tasks/{task}/revision', [TaskDashboardController::class, 'revision'])->name('tasks.revision');
Route::post('/tasks/{task}/reject', [TaskDashboardController::class, 'reject'])->name('tasks.reject');
Route::post('/tasks/{task}/archive', [TaskDashboardController::class, 'archive'])->name('tasks.archive');
Route::get('/tasks/{task}/artifacts', [TaskDashboardController::class, 'showArtifact'])
    ->name('tasks.artifacts.show');
