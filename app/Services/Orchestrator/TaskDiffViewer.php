<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Symfony\Component\Process\Process;

class TaskDiffViewer
{
    private const MAX_UNTRACKED_BYTES = 200000;

    /**
     * @return array{files: array<int, array{path: string, status: string, diff: string}>, warning: ?string}
     */
    public function collect(OrchestratorTask $task): array
    {
        $worktree = trim((string) $task->worktree_path);

        if ($worktree === '') {
            return ['files' => [], 'warning' => 'Esta tarea no tiene una ruta de worktree para inspeccionar.'];
        }

        if (! is_dir($worktree)) {
            return ['files' => [], 'warning' => 'La ruta del worktree ya no existe o no está disponible.'];
        }

        $repository = $this->run(['git', '-C', $worktree, 'rev-parse', '--is-inside-work-tree']);
        if (! $repository['successful'] || trim($repository['output']) !== 'true') {
            return ['files' => [], 'warning' => 'No se pudo leer el worktree porque la ruta no es un repositorio Git disponible.'];
        }

        $status = $this->run(['git', '-C', $worktree, 'status', '--short', '-z', '--untracked-files=all']);
        if (! $status['successful']) {
            return ['files' => [], 'warning' => 'Git no pudo leer los cambios del worktree.'];
        }

        $files = [];
        foreach ($this->statusEntries($status['output']) as $entry) {
            $path = $entry['path'];
            $files[] = [
                'path' => $path,
                'status' => $entry['status'],
                'diff' => $entry['untracked']
                    ? $this->untrackedDiff($worktree, $path)
                    : $this->trackedDiff($worktree, $path),
            ];
        }

        return ['files' => $files, 'warning' => $files === [] ? 'No hay cambios sin confirmar en este worktree.' : null];
    }

    /**
     * @return array<int, array{path: string, status: string, untracked: bool}>
     */
    private function statusEntries(string $output): array
    {
        $entries = [];
        $records = array_values(array_filter(explode("\0", $output), fn (string $entry): bool => $entry !== ''));
        for ($index = 0; $index < count($records); $index++) {
            $entry = $records[$index];
            if (strlen($entry) < 4) {
                continue;
            }

            $code = substr($entry, 0, 2);
            $path = substr($entry, 3);
            if ($path === '') {
                continue;
            }

            $entries[] = [
                'path' => $path,
                'status' => $code,
                'untracked' => $code === '??',
            ];

            if (str_contains($code, 'R') || str_contains($code, 'C')) {
                $index++;
            }
        }

        return $entries;
    }

    private function trackedDiff(string $worktree, string $path): string
    {
        $diff = $this->run(['git', '-C', $worktree, 'diff', 'HEAD', '--no-ext-diff', '--binary', '--', $path]);

        return $diff['successful'] && trim($diff['output']) !== ''
            ? $diff['output']
            : "No se pudo generar un diff para este archivo.\n";
    }

    private function untrackedDiff(string $worktree, string $path): string
    {
        $file = $worktree.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (! is_file($file) || is_link($file)) {
            return 'Archivo no rastreado; ya no está disponible para leer.'."\n";
        }

        $size = filesize($file);
        if ($size === false || $size > self::MAX_UNTRACKED_BYTES) {
            return 'Archivo no rastreado. El contenido no se muestra porque supera el límite de '.self::MAX_UNTRACKED_BYTES." bytes.\n";
        }

        $content = file_get_contents($file);
        if ($content === false || str_contains($content, "\0")) {
            return 'Archivo no rastreado binario o no legible.'."\n";
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lineCount = count($lines);
        if ($content !== '' && str_ends_with($content, "\n")) {
            array_pop($lines);
            $lineCount--;
        }

        $added = implode("\n", array_map(fn (string $line): string => '+'.$line, $lines));

        return "diff --git a/{$path} b/{$path}\n"
            ."new file mode 100644\n"
            ."--- /dev/null\n"
            ."+++ b/{$path}\n"
            ."@@ -0,0 +1,{$lineCount} @@\n"
            .$added.($added === '' ? '' : "\n");
    }

    /**
     * @param  array<int, string>  $command
     * @return array{successful: bool, output: string}
     */
    private function run(array $command): array
    {
        $process = new Process($command, null, ['GIT_OPTIONAL_LOCKS' => '0']);
        $process->run();

        return [
            'successful' => $process->isSuccessful(),
            'output' => $process->getOutput(),
        ];
    }
}
