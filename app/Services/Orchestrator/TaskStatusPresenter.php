<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;

class TaskStatusPresenter
{
    public function needsHumanReview(OrchestratorTask $task): bool
    {
        return $task->status === 'completed' && $task->review_decision === null;
    }

    public function needsAttention(OrchestratorTask $task): bool
    {
        return $this->needsHumanReview($task)
            || in_array($task->status, ['running', 'blocked', 'needs_revision'], true)
            || $task->last_verification_status === 'failed'
            || $task->last_acceptance_status === 'failed';
    }

    public function nextAction(OrchestratorTask $task, string $locale = 'en'): string
    {
        $key = match ($task->status) {
            'archived' => 'no_action',
            'approved' => 'archive_when_ready',
            'needs_revision' => 'rerun_revision',
            'draft' => 'prepare_task',
            'prepared' => 'run_task',
            'blocked' => 'resolve_blocker',
            'failed' => 'review_run_log',
            'running' => 'wait_or_check_log',
            'completed' => $this->completedNextActionKey($task),
            default => 'check_status_and_artifacts',
        };

        return $locale === 'es'
            ? $this->spanishNextAction($key)
            : $this->englishNextAction($key);
    }

    public function label(?string $value, string $emptyLabel = 'Sin datos'): string
    {
        if ($value === null) {
            return $emptyLabel;
        }

        return match ($value) {
            'draft' => 'Borrador',
            'prepared' => 'Preparada',
            'running' => 'En ejecución',
            'blocked' => 'Bloqueada',
            'failed' => 'Fallida',
            'completed' => 'Completada',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'needs_revision' => 'Requiere revisión',
            'archived' => 'Archivada',
            'passed' => 'Superada',
            'skipped' => 'Omitida',
            default => $value,
        };
    }

    private function completedNextActionKey(OrchestratorTask $task): string
    {
        if ($task->last_verification_status === 'failed') {
            return 'fix_verification';
        }

        if ($task->last_acceptance_status === 'failed') {
            return 'fix_acceptance';
        }

        if ($task->last_verification_status === null) {
            return 'run_verification';
        }

        if ($task->last_acceptance_status === null) {
            return 'run_acceptance';
        }

        if ($task->review_decision === null) {
            return 'review_artifacts';
        }

        return 'review_recorded_decision';
    }

    private function englishNextAction(string $key): string
    {
        return match ($key) {
            'no_action' => 'No action.',
            'archive_when_ready' => 'Archive when ready.',
            'rerun_revision' => 'Rerun with the recorded revision request.',
            'prepare_task' => 'Prepare task.',
            'run_task' => 'Run task.',
            'resolve_blocker' => 'Resolve blocker, then rerun task.',
            'review_run_log' => 'Review run log, then rerun task.',
            'wait_or_check_log' => 'Wait or check run log.',
            'fix_verification' => 'Fix verification failure, then rerun.',
            'fix_acceptance' => 'Fix acceptance failure, then rerun.',
            'run_verification' => 'Run verification before review.',
            'run_acceptance' => 'Run acceptance check before review.',
            'review_artifacts' => 'Review artifacts, then approve, reject, or request revision.',
            'review_recorded_decision' => 'Review recorded decision.',
            default => 'Check task status and artifacts.',
        };
    }

    private function spanishNextAction(string $key): string
    {
        return match ($key) {
            'no_action' => 'No requiere acciones.',
            'archive_when_ready' => 'Archivá la tarea cuando esté lista.',
            'rerun_revision' => 'Volvé a ejecutar con la solicitud de revisión registrada.',
            'prepare_task' => 'Prepará la tarea.',
            'run_task' => 'Ejecutá la tarea.',
            'resolve_blocker' => 'Resolvé el bloqueo y volvé a ejecutar la tarea.',
            'review_run_log' => 'Revisá el registro de ejecución y volvé a ejecutar la tarea.',
            'wait_or_check_log' => 'Esperá o revisá el registro de ejecución.',
            'fix_verification' => 'Corregí la falla de verificación y volvé a ejecutar.',
            'fix_acceptance' => 'Corregí la falla de aceptación y volvé a ejecutar.',
            'run_verification' => 'Ejecutá la verificación antes de revisar.',
            'run_acceptance' => 'Ejecutá la comprobación de aceptación antes de revisar.',
            'review_artifacts' => 'Revisá los artefactos; después aprobá, rechazá o pedí una revisión.',
            'review_recorded_decision' => 'Revisá la decisión registrada.',
            default => 'Revisá el estado y los artefactos de la tarea.',
        };
    }
}
