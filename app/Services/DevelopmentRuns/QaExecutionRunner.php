<?php

namespace App\Services\DevelopmentRuns;

use Symfony\Component\Process\Process;

class QaExecutionRunner
{
    /**
     * @return array{status: 'passed'|'failed'|'blocked', exit_code: int|null, command: string|null, output: string}
     */
    public function run(string $workingDirectory): array
    {
        $command = $this->commandFor($workingDirectory);

        if ($command === null) {
            return [
                'status' => 'blocked',
                'exit_code' => null,
                'command' => null,
                'output' => 'No se detectó un comando de QA seguro para este repositorio.',
            ];
        }

        $process = Process::fromShellCommandline($command, $workingDirectory, $this->cleanPhpEnvironment(), timeout: 1800);
        $process->run();

        $exitCode = $process->getExitCode() ?? 1;
        $output = trim($process->getOutput().$process->getErrorOutput());
        $plainOutput = preg_replace('/\e\[[\d;]*m/', '', $output) ?? $output;
        $failedByOutput = (bool) preg_match('/\b\d+\s+failed\b|\bFAILURES!\b|\bERRORS!\b/i', $plainOutput);
        $passedByOutput = (bool) preg_match('/\b\d+\s+passed\b/i', $plainOutput);

        return [
            'status' => ($exitCode === 0 || $passedByOutput) && ! $failedByOutput ? 'passed' : 'failed',
            'exit_code' => $exitCode,
            'command' => $command,
            'output' => $output,
        ];
    }

    public function agent(): string
    {
        return env('DEVELOPMENT_RUN_QA_AGENT', 'local-qa-runner');
    }

    private function commandFor(string $workingDirectory): ?string
    {
        $override = trim((string) env('DEVELOPMENT_RUN_QA_COMMAND', ''));
        if ($override !== '') {
            return $override;
        }

        $userProfile = getenv('USERPROFILE') ?: getenv('HOME') ?: '';
        $scoopPhp = $userProfile !== '' ? $userProfile.DIRECTORY_SEPARATOR.'scoop'.DIRECTORY_SEPARATOR.'shims'.DIRECTORY_SEPARATOR.'php.exe' : '';
        if ($scoopPhp !== '' && is_file($scoopPhp) && is_file($workingDirectory.DIRECTORY_SEPARATOR.'artisan')) {
            return '"'.$scoopPhp.'" artisan test';
        }

        if (is_file($workingDirectory.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'artisan.ps1')) {
            return "powershell -NoProfile -ExecutionPolicy Bypass -Command \"& '.\\bin\\artisan.ps1' test; exit \$LASTEXITCODE\"";
        }

        if (is_file($workingDirectory.DIRECTORY_SEPARATOR.'artisan')) {
            return 'php artisan test';
        }

        if (is_file($workingDirectory.DIRECTORY_SEPARATOR.'package.json')) {
            return 'npm test';
        }

        return null;
    }

    /**
     * @return array<string, string|false>
     */
    private function cleanPhpEnvironment(): array
    {
        return [
            'PHPRC' => false,
            'PHP_INI_SCAN_DIR' => $this->scoopIniScanDir() ?: false,
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
        ];
    }

    private function scoopIniScanDir(): ?string
    {
        $userProfile = getenv('USERPROFILE') ?: getenv('HOME') ?: '';
        if ($userProfile === '') {
            return null;
        }

        $persistMatches = glob($userProfile.DIRECTORY_SEPARATOR.'scoop'.DIRECTORY_SEPARATOR.'persist'.DIRECTORY_SEPARATOR.'php*', GLOB_ONLYDIR) ?: [];
        $appMatches = glob($userProfile.DIRECTORY_SEPARATOR.'scoop'.DIRECTORY_SEPARATOR.'apps'.DIRECTORY_SEPARATOR.'php*'.DIRECTORY_SEPARATOR.'current'.DIRECTORY_SEPARATOR.'conf.d', GLOB_ONLYDIR) ?: [];

        rsort($persistMatches);
        rsort($appMatches);

        if ($persistMatches === [] && $appMatches === []) {
            return null;
        }

        return collect([reset($persistMatches) ?: null, reset($appMatches) ?: null])
            ->filter()
            ->implode(PATH_SEPARATOR);
    }
}
