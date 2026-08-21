<?php

namespace App\Services\DevelopmentRuns;

use App\Models\DevelopmentRun;
use Illuminate\Support\Facades\DB;

class DevelopmentRunStaleExecutionDetector
{
    public function recoverIfStale(DevelopmentRun $run): DevelopmentRun
    {
        if (! in_array($run->status, ['plan_running', 'slices_running', 'build_running', 'qa_running', 'review_running'], true)) {
            return $run;
        }

        $stage = str($run->status)->before('_running')->toString();
        $artifactType = "{$stage}_background_run";
        $artifact = $run->artifacts()->where('type', $artifactType)->first();

        $finalArtifact = $run->artifacts()->where('type', $this->finalArtifactType($stage))->first();
        if ($finalArtifact) {
            return $this->recoverFromFinalArtifact($run, $artifact, $stage, $finalArtifact);
        }

        $pid = (int) data_get($artifact?->metadata, 'pid', 0);

        if (app(DevelopmentRunBackgroundProcess::class)->isRunning($pid)) {
            return $run;
        }

        DB::transaction(function () use ($run, $artifact, $artifactType, $stage, $pid) {
            $metadata = $artifact?->metadata ?? [];

            $run->artifacts()->updateOrCreate(
                ['type' => $artifactType],
                [
                    'title' => $this->stageLabel($stage).' interrumpido',
                    'body' => $this->stageLabel($stage)." quedó marcado como en ejecución, pero el proceso background ya no está activo.\n\nPID detectado: ".($pid > 0 ? $pid : 'no disponible')."\n\nPodés reintentar la etapa de forma segura.",
                    'metadata' => [...$metadata, 'status' => 'interrupted', 'interrupted_at' => now()->toISOString(), 'pid_was_running' => false],
                    'created_by' => 'system',
                ],
            );

            $run->update([
                'active_stage' => $stage,
                'status' => "{$stage}_interrupted",
            ]);
        });

        return $run->fresh();
    }

    private function recoverFromFinalArtifact(DevelopmentRun $run, $artifact, string $stage, $finalArtifact): DevelopmentRun
    {
        DB::transaction(function () use ($run, $artifact, $stage, $finalArtifact) {
            $finalStatus = (string) data_get($finalArtifact->metadata, 'status', $stage === 'qa' ? 'passed' : 'completed');
            $normalizedBackgroundStatus = in_array($finalStatus, ['completed', 'passed'], true) ? 'completed' : $finalStatus;
            $backgroundTitleStatus = match ($normalizedBackgroundStatus) {
                'completed' => 'completado',
                'failed' => 'fallido',
                'blocked' => 'bloqueado',
                default => $normalizedBackgroundStatus,
            };

            if ($artifact) {
                $artifact->update([
                    'title' => $this->stageLabel($stage)." {$backgroundTitleStatus}",
                    'metadata' => [...($artifact->metadata ?? []), 'status' => $normalizedBackgroundStatus, 'finished_at' => data_get($artifact->metadata, 'finished_at') ?: now()->toISOString()],
                ]);
            }

            [$status, $activeStage] = $this->stateFromFinalStatus($stage, $finalStatus);

            $updates = ['active_stage' => $activeStage, 'status' => $status];
            if ($stage === 'review' && $status === 'completed' && $run->completed_at === null) {
                $updates['completed_at'] = now();
            }

            $run->update($updates);
        });

        return $run->fresh();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function stateFromFinalStatus(string $stage, string $status): array
    {
        return match ($stage) {
            'plan' => $status === 'completed' ? ['planning', 'plan'] : ['plan_blocked', 'plan'],
            'slices' => $status === 'completed' ? ['slicing', 'slices'] : ['slices_blocked', 'slices'],
            'build' => $status === 'completed' ? ['build_executed', 'qa'] : [$status === 'blocked' ? 'execution_blocked' : 'execution_failed', 'build'],
            'qa' => match ($status) {
                'passed' => ['qa_passed', 'review'],
                'failed' => ['qa_failed', 'qa'],
                default => ['qa_blocked', 'qa'],
            },
            'review' => $status === 'completed' ? ['completed', 'review'] : ['review_blocked', 'review'],
            default => ["{$stage}_blocked", $stage],
        };
    }

    private function finalArtifactType(string $stage): string
    {
        return match ($stage) {
            'plan' => 'technical_brief',
            'slices' => 'implementation_slices',
            'build' => 'opencode_execution',
            'qa' => 'qa_report',
            'review' => 'review_report',
            default => "{$stage}_result",
        };
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'plan' => 'Plan',
            'slices' => 'Slices',
            'build' => 'Build',
            'qa' => 'QA',
            'review' => 'Revisión',
            default => ucfirst($stage),
        };
    }
}
