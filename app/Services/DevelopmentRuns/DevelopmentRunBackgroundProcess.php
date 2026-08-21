<?php

namespace App\Services\DevelopmentRuns;

use App\Models\DevelopmentRun;
use Symfony\Component\Process\Process;

class DevelopmentRunBackgroundProcess
{
    /**
     * @var array<string, mixed>
     */
    private array $lastStartMetadata = [];

    public function startBuild(DevelopmentRun $run): ?int
    {
        return $this->start('development-run:execute-build', $run);
    }

    public function startPlan(DevelopmentRun $run): ?int
    {
        return $this->start('development-run:execute-plan', $run);
    }

    public function startSlices(DevelopmentRun $run): ?int
    {
        return $this->start('development-run:execute-slices', $run);
    }

    public function startQa(DevelopmentRun $run): ?int
    {
        return $this->start('development-run:execute-qa', $run);
    }

    public function startReview(DevelopmentRun $run): ?int
    {
        return $this->start('development-run:execute-review', $run);
    }

    public function cancel(DevelopmentRun $run): bool
    {
        $artifactType = match ($run->status) {
            'plan_running' => 'plan_background_run',
            'slices_running' => 'slices_background_run',
            'build_running' => 'build_background_run',
            'qa_running' => 'qa_background_run',
            'review_running' => 'review_background_run',
            default => null,
        };
        if ($artifactType === null) {
            return false;
        }

        $artifact = $run->artifacts()->where('type', $artifactType)->first();
        $pid = (int) data_get($artifact?->metadata, 'pid', 0);
        if ($pid <= 0) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $process = new Process(['powershell', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command', $this->windowsStopTreeCommand($pid)], timeout: 30);
            $process->run();

            return $process->isSuccessful();
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 15);
        }

        return false;
    }

    public function isRunning(?int $pid): bool
    {
        if (! $pid || $pid <= 0) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $process = new Process(['powershell', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command', "if (Get-Process -Id {$pid} -ErrorAction SilentlyContinue) { 'RUNNING' }"], timeout: 10);
            $process->run();

            return trim($process->getOutput()) === 'RUNNING';
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function lastStartMetadata(): array
    {
        return $this->lastStartMetadata;
    }

    private function start(string $command, DevelopmentRun $run): ?int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $details = $this->windowsStartDetails($command, $run);
            $process = new Process([
                'powershell',
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-Command',
                $details['command'],
            ], base_path(), timeout: 30);
            $process->run();

            $pid = trim($process->getOutput());

            $this->lastStartMetadata = [
                'pid' => is_numeric($pid) ? (int) $pid : null,
                'process_command' => $command,
                'php_executable' => $details['php_executable'],
                'log_path' => $details['log_path'],
                'error_log_path' => $details['error_log_path'],
                'php_ini_scan_dir' => $details['php_ini_scan_dir'],
                'started_at' => now()->toISOString(),
            ];

            return is_numeric($pid) ? (int) $pid : null;
        }

        $process = new Process([$this->phpExecutable(), base_path('artisan'), $command, (string) $run->id], base_path(), $this->phpEnvironment(), timeout: 1800);
        $process->disableOutput();
        $process->start();

        $this->lastStartMetadata = [
            'pid' => $process->getPid(),
            'process_command' => $command,
            'php_executable' => $this->phpExecutable(),
            'started_at' => now()->toISOString(),
        ];

        return $process->getPid();
    }

    /**
     * @return array{command: string, php_executable: string, log_path: string, error_log_path: string, php_ini_scan_dir: string}
     */
    private function windowsStartDetails(string $command, DevelopmentRun $run): array
    {
        $php = $this->phpExecutable();
        $arguments = [base_path('artisan'), $command, (string) $run->id];
        $quotedArguments = collect($arguments)
            ->map(fn (string $argument) => "'".str_replace("'", "''", $argument)."'")
            ->implode(',');
        $workingDirectory = str_replace("'", "''", base_path());
        $php = str_replace("'", "''", $php);
        $attempt = now()->format('YmdHis');
        $logPath = storage_path('logs'.DIRECTORY_SEPARATOR."development-run-{$run->id}-{$command}-{$attempt}.log");
        $errorPath = storage_path('logs'.DIRECTORY_SEPARATOR."development-run-{$run->id}-{$command}-{$attempt}.err.log");
        $scanDir = $this->scoopIniScanDir() ?: '';
        $escapedLogPath = str_replace("'", "''", $logPath);
        $escapedErrorPath = str_replace("'", "''", $errorPath);
        $escapedScanDir = str_replace("'", "''", $scanDir);

        return [
            'command' => "\$env:PHPRC=\$null; \$env:PHP_INI_SCAN_DIR='{$escapedScanDir}'; \$p = Start-Process -WindowStyle Hidden -FilePath '{$php}' -ArgumentList @({$quotedArguments}) -WorkingDirectory '{$workingDirectory}' -RedirectStandardOutput '{$escapedLogPath}' -RedirectStandardError '{$escapedErrorPath}' -PassThru; \$p.Id",
            'php_executable' => $this->phpExecutable(),
            'log_path' => $logPath,
            'error_log_path' => $errorPath,
            'php_ini_scan_dir' => $scanDir,
        ];
    }

    private function windowsStopTreeCommand(int $pid): string
    {
        return "function Stop-Tree([int]\$Id) { Get-CimInstance Win32_Process -Filter \"ParentProcessId=\$Id\" | ForEach-Object { Stop-Tree ([int]\$_.ProcessId) }; Stop-Process -Id \$Id -Force -ErrorAction SilentlyContinue }; Stop-Tree {$pid}";
    }

    private function phpExecutable(): string
    {
        $userProfile = getenv('USERPROFILE') ?: getenv('HOME') ?: '';
        $scoopPhp = $userProfile !== '' ? $userProfile.DIRECTORY_SEPARATOR.'scoop'.DIRECTORY_SEPARATOR.'shims'.DIRECTORY_SEPARATOR.'php.exe' : '';

        if ($scoopPhp !== '' && is_file($scoopPhp)) {
            return $scoopPhp;
        }

        return 'php';
    }

    /**
     * @return array<string, string|false>
     */
    private function phpEnvironment(): array
    {
        return [
            'PHPRC' => false,
            'PHP_INI_SCAN_DIR' => $this->scoopIniScanDir() ?: false,
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
