<?php

namespace App\Services\DevelopmentRuns;

use App\Models\DevelopmentRun;
use Illuminate\Support\Facades\DB;

class DevelopmentRunStaleExecutionDetector
{
    public function recoverIfStale(DevelopmentRun $run): DevelopmentRun
    {
        if (! in_array($run->status, ['build_running', 'qa_running'], true)) {
            return $run;
        }

        $stage = $run->status === 'build_running' ? 'build' : 'qa';
        $artifactType = $stage === 'build' ? 'build_background_run' : 'qa_background_run';
        $artifact = $run->artifacts()->where('type', $artifactType)->first();

        $finalArtifact = $run->artifacts()->where('type', $stage === 'build' ? 'opencode_execution' : 'qa_report')->first();
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
                    'title' => $stage === 'build' ? 'Build interrumpido' : 'QA interrumpido',
                    'body' => ($stage === 'build' ? 'Build' : 'QA')." quedó marcado como en ejecución, pero el proceso background ya no está activo.\n\nPID detectado: ".($pid > 0 ? $pid : 'no disponible')."\n\nPodés reintentar la etapa de forma segura.",
                    'metadata' => [...$metadata, 'status' => 'interrupted', 'interrupted_at' => now()->toISOString(), 'pid_was_running' => false],
                    'created_by' => 'system',
                ],
            );

            $run->update([
                'active_stage' => $stage,
                'status' => $stage === 'build' ? 'build_interrupted' : 'qa_interrupted',
            ]);
        });

        return $run->fresh();
    }

    private function recoverFromFinalArtifact(DevelopmentRun $run, $artifact, string $stage, $finalArtifact): DevelopmentRun
    {
        DB::transaction(function () use ($run, $artifact, $stage, $finalArtifact) {
            $finalStatus = (string) data_get($finalArtifact->metadata, 'status', $stage === 'build' ? 'completed' : 'passed');
            $normalizedBackgroundStatus = in_array($finalStatus, ['completed', 'passed'], true) ? 'completed' : $finalStatus;
            $backgroundTitleStatus = match ($normalizedBackgroundStatus) {
                'completed' => 'completado',
                'failed' => 'fallido',
                'blocked' => 'bloqueado',
                default => $normalizedBackgroundStatus,
            };

            if ($artifact) {
                $artifact->update([
                    'title' => ($stage === 'build' ? 'Build' : 'QA')." {$backgroundTitleStatus}",
                    'metadata' => [...($artifact->metadata ?? []), 'status' => $normalizedBackgroundStatus, 'finished_at' => data_get($artifact->metadata, 'finished_at') ?: now()->toISOString()],
                ]);
            }

            [$status, $activeStage] = $stage === 'build'
                ? $this->buildStateFromFinalStatus($finalStatus)
                : $this->qaStateFromFinalStatus($finalStatus);

            $run->update(['active_stage' => $activeStage, 'status' => $status]);
        });

        return $run->fresh();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function buildStateFromFinalStatus(string $status): array
    {
        return $status === 'completed'
            ? ['build_executed', 'qa']
            : [$status === 'blocked' ? 'execution_blocked' : 'execution_failed', 'build'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function qaStateFromFinalStatus(string $status): array
    {
        return match ($status) {
            'passed' => ['qa_passed', 'review'],
            'failed' => ['qa_failed', 'qa'],
            default => ['qa_blocked', 'qa'],
        };
    }
}
