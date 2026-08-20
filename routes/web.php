<?php

use App\Http\Controllers\EmailThreadImportController;
use App\Http\Controllers\DevelopmentRunController;
use App\Http\Controllers\GmailIntegrationController;
use App\Http\Controllers\IntegrationSettingsController;
use App\Http\Controllers\OperationalTicketController;
use App\Http\Controllers\TaskDashboardController;
use App\Models\DevelopmentRun;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $activeRun = DevelopmentRun::query()
        ->whereNull('completed_at')
        ->latest('started_at')
        ->first();

    return view('home', compact('activeRun'));
})->name('home');
Route::get('/tasks', [TaskDashboardController::class, 'index'])->name('tasks.index');
Route::get('/development-runs/create', [DevelopmentRunController::class, 'create'])->name('development-runs.create');
Route::post('/development-runs', [DevelopmentRunController::class, 'store'])->name('development-runs.store');
Route::view('/development-runs/demo', 'development-runs.demo')->name('development-runs.demo');
Route::post('/development-runs/{developmentRun}/technical-brief', [DevelopmentRunController::class, 'storeTechnicalBrief'])->name('development-runs.technical-brief.store');
Route::post('/development-runs/{developmentRun}/stage/contexto', [DevelopmentRunController::class, 'returnToContext'])->name('development-runs.context.return');
Route::post('/development-runs/{developmentRun}/implementation-slices', [DevelopmentRunController::class, 'storeImplementationSlices'])->name('development-runs.implementation-slices.store');
Route::post('/development-runs/{developmentRun}/stage/plan', [DevelopmentRunController::class, 'returnToPlan'])->name('development-runs.plan.return');
Route::post('/development-runs/{developmentRun}/build-plan', [DevelopmentRunController::class, 'storeBuildPlan'])->name('development-runs.build-plan.store');
Route::post('/development-runs/{developmentRun}/stage/slices', [DevelopmentRunController::class, 'returnToSlices'])->name('development-runs.slices.return');
Route::post('/development-runs/{developmentRun}/execution-prompt', [DevelopmentRunController::class, 'storeExecutionPrompt'])->name('development-runs.execution-prompt.store');
Route::post('/development-runs/{developmentRun}/opencode-execution', [DevelopmentRunController::class, 'runOpenCode'])->name('development-runs.opencode-execution.store');
Route::post('/development-runs/{developmentRun}/qa', [DevelopmentRunController::class, 'runQa'])->name('development-runs.qa.store');
Route::post('/development-runs/{developmentRun}/cancel-execution', [DevelopmentRunController::class, 'cancelExecution'])->name('development-runs.execution.cancel');
Route::post('/development-runs/{developmentRun}/review', [DevelopmentRunController::class, 'storeReview'])->name('development-runs.review.store');
Route::patch('/development-runs/{developmentRun}/repository', [DevelopmentRunController::class, 'updateRepository'])->name('development-runs.repository.update');
Route::get('/development-runs/{developmentRun}/status', [DevelopmentRunController::class, 'status'])->name('development-runs.status');
Route::get('/development-runs/{developmentRun}', [DevelopmentRunController::class, 'show'])->name('development-runs.show');
Route::get('/integrations/gmail', [GmailIntegrationController::class, 'index'])->name('integrations.gmail.index');
Route::get('/configuracion/integraciones', [IntegrationSettingsController::class, 'edit'])->name('settings.integrations.edit');
Route::put('/configuracion/integraciones', [IntegrationSettingsController::class, 'update'])->name('settings.integrations.update');
Route::post('/integrations/gmail/connect', [GmailIntegrationController::class, 'connect'])->name('integrations.gmail.connect');
Route::get('/integrations/gmail/oauth/callback', [GmailIntegrationController::class, 'callback'])->name('integrations.gmail.callback');
Route::get('/integrations/gmail/callback', [GmailIntegrationController::class, 'callback']);
Route::post('/integrations/gmail/disconnect', [GmailIntegrationController::class, 'disconnect'])->name('integrations.gmail.disconnect');
Route::get('/integrations/gmail/threads', [GmailIntegrationController::class, 'threads'])->name('integrations.gmail.threads');
Route::post('/integrations/gmail/threads/{threadId}/import', [GmailIntegrationController::class, 'importThread'])->name('integrations.gmail.threads.import');
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
