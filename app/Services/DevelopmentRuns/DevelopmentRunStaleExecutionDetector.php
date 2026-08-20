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
}
