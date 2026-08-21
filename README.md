# Dev Orchestrator

Local-first Laravel MVP for preparing independent developer tasks in Git worktrees. It also includes a manual operational-ticket intake board for the daily 18dev workflow. It stores metadata in SQLite, generates OpenCode prompts, and retains run and review artifacts. It never commits, stages, pushes, or changes Git configuration.

## Requirements

- Git available on `PATH`.
- A local Git repository and its base branch already available locally.
- PHP is invoked through `bin\artisan.ps1`. This wrapper uses the existing MAMP PHP installation with an app-local `php.ini`; it does not change MAMP or Windows PATH.
- `opencode` is optional. `orchestrator:task-run` marks a task `blocked` and retains artifacts if it cannot find the CLI.

## Quick start

From this project directory in PowerShell:

```powershell
.\bin\dev-orchestrator.ps1 -Port 8001
```

This single command configures the project-local MAMP PHP environment, checks the SQLite extensions, creates `.env` and the SQLite database when missing, installs dependencies when needed, generates an application key, runs migrations, and serves Command Flow at `http://127.0.0.1:8001`. Press Ctrl+C to stop it.

Use `-SetupOnly` to prepare the environment without starting the server. Use `-NoInstall` when dependencies are already present and you want to skip Composer and npm installation.

After Command Flow is running, use the CLI commands below for task management:

```powershell
.\bin\artisan.ps1 migrate
.\bin\artisan.ps1 orchestrator:project-add my-app C:\code\my-app --default-branch=main --test="php artisan test" --lint="vendor\bin\pint" --rules="Keep changes local. Do not modify deployment files."
.\bin\artisan.ps1 orchestrator:task-create my-app "Add task export" --description="Export task records as CSV." --acceptance="CSV download contains the selected task fields." --expected-file=docs\task-export.md --autonomy=medium
.\bin\artisan.ps1 orchestrator:task-prepare 1
.\bin\artisan.ps1 orchestrator:task-run 1
.\bin\artisan.ps1 orchestrator:task-verify 1
.\bin\artisan.ps1 orchestrator:task-review 1
.\bin\artisan.ps1 orchestrator:task-revision 1 --reason="Create the requested documentation file."
.\bin\artisan.ps1 orchestrator:task-rerun 1 --instructions="Create the requested files; do not edit README instead." --verify --acceptance --review
.\bin\artisan.ps1 orchestrator:task-acceptance-check 1
.\bin\artisan.ps1 orchestrator:task-approve 1 --notes="Verified the requested files and checks."
.\bin\artisan.ps1 orchestrator:task-open 1
.\bin\artisan.ps1 orchestrator:task-archive 1
.\bin\artisan.ps1 orchestrator:task-status
.\bin\artisan.ps1 orchestrator:weekly-report --save
```

To run independent tasks together, prepare and then run a controlled batch:

```powershell
.\bin\artisan.ps1 orchestrator:task-batch-run 2 3 4 --concurrency=2 --prepare --verify --acceptance --review
```

The concurrency cap is deliberately limited to `1` through `4` (default `2`). Each task must have its own Git worktree. The batch artifact flags file paths modified by more than one task as a human-review warning; it never merges work or fails solely because of a conflict.

`completed` means the agent execution finished. It does not mean a human accepted the work. Review the artifacts, then explicitly approve, reject, or request revision before archiving or integrating changes elsewhere.

## Running the local environment

Use the smallest command that matches what you need. The web dashboard is optimized for a developer review flow: start with the attention queue, see why a task needs attention, inspect its worktree diff and available artifacts in read-only viewers, then record a safe local human decision. The diff viewer includes tracked and untracked files when available and does not stage, reset, checkout, commit, push, or otherwise modify Git. It shows task status and attention counts, task details, available task artifacts, and configured acceptance expectations. After approving or rejecting a task, its detail page can archive the final history and artifacts. Web archiving never removes the worktree or modifies Git state; the UI does not run, rerun, commit, push, or merge.

### Dashboard only

Use the launcher for the local dashboard. It prepares the required PHP, SQLite, Laravel, and optional frontend dependencies before it starts the server:

```powershell
.\bin\dev-orchestrator.ps1 -Port 8001
```

Then open `http://127.0.0.1:8001`. Command Flow is the initial experience; the task dashboard remains available at `http://127.0.0.1:8001/tasks`. Add `-SetupOnly` to run preparation without serving, or `-NoInstall` to skip dependency installation when `vendor` and `node_modules` already exist.

This is enough for the current dashboard because the Blade views use server-rendered HTML and do not require a frontend dev server.

### Tickets operativos

Abrí **Tickets operativos** desde la navegación del dashboard para cargar pedidos manuales y hacerles triage antes de convertirlos en trabajo técnico. El flujo inicial y sus límites están documentados en [docs/operational-workflow.md](docs/operational-workflow.md).

### Integración con Gmail

Abrí **Integraciones** y elegí **Conectar con Google** para conectar una cuenta Gmail o Google Workspace mediante OAuth. La conexión guarda tokens cifrados localmente, usa permisos de solo lectura y permite importar cadenas para borradores revisables. Solo la primera vez, o al cambiar la aplicación de Google Cloud, completá las credenciales en **Configuración > Configuración avanzada de Google OAuth**; los secretos se cifran en SQLite con `APP_KEY` y el `.env` continúa como fallback opcional. La configuración de Google Cloud, OpenAI y los límites de seguridad están en [docs/gmail-oauth.md](docs/gmail-oauth.md).

### Command Flow y Development Run experimental

`http://127.0.0.1:8001` abre Command Flow, la entrada local para convertir una necesidad concreta en un Development Run con evidencia por etapa.

Flujo principal:

1. **Contexto** — guarda título, contexto inicial, repositorio/proyecto y artifact de contexto.
2. **Plan** — intenta generar el brief técnico con el agente OpenCode `plan`; si OpenCode no está disponible o falla, usa fallback determinístico.
3. **Slices** — intenta dividir el brief con el agente OpenCode `slices`; si OpenCode no está disponible o falla, usa fallback determinístico.
4. **Build** — prepara un prompt controlado y ejecuta OpenCode en background con el worker `build`.
5. **QA** — corre verificaciones locales seguras (`php artisan test` o `npm test`, salvo override) y luego intenta analizar la evidencia con el agente OpenCode `qa`; si OpenCode no está disponible o falla, usa fallback determinístico.
6. **Revisión** — intenta generar el cierre con el agente OpenCode `review`; si OpenCode no está disponible o falla, usa fallback determinístico.

Ejecución background:

- Plan, Slices, Build, QA y Revisión pueden correr en background con polling, cancelación, retry o recuperación de estados stale según corresponda a cada etapa.
- Build y QA marcan `build_running`/`qa_running`, guardan PID, PHP usado, log path y error log path.
- La pantalla consulta `GET /development-runs/{run}/status` y se actualiza cuando cambia el estado.
- **Cancelar ejecución** detiene el árbol de procesos y marca `build_cancelled` o `qa_cancelled`.
- Si el proceso muere solo, al abrir el run o consultar status se marca `build_interrupted` o `qa_interrupted` y queda habilitado el retry.
- Al terminar, el artifact background deja `finished_at` y cambia de `running` a `completed`, `failed` o `blocked`.

`gentle-orchestrator` queda como coordinador conceptual del Development Run; no ejecuta Build directamente. La ejecución sigue prohibiendo stage, commit, push y cambios de remotos. El mock visual original está en `http://127.0.0.1:8001/development-runs/demo` y la referencia visual en [docs/development-run-cockpit-mock.svg](docs/development-run-cockpit-mock.svg).

Variables opcionales para ajustar el perfil de ejecución de Development Runs:

- `DEVELOPMENT_RUN_OPENCODE_ORCHESTRATOR_AGENT` — coordinador conceptual; default `gentle-orchestrator`.
- `DEVELOPMENT_RUN_OPENCODE_PLAN_AGENT` — worker que genera el brief de Plan; default `plan`.
- `DEVELOPMENT_RUN_OPENCODE_SLICES_AGENT` — worker que define slices de implementación; default `slices`.
- `DEVELOPMENT_RUN_OPENCODE_BUILD_AGENT` — worker que ejecuta Build; default `build`.
- `DEVELOPMENT_RUN_OPENCODE_REVIEW_AGENT` — worker que genera el cierre de Revisión; default `review`.
- `DEVELOPMENT_RUN_OPENCODE_QA_AGENT` — worker que interpreta la evidencia del runner QA; default `qa`.
- `DEVELOPMENT_RUN_OPENCODE_MODEL` — modelo del worker; default `openai/gpt-5.5`.
- `DEVELOPMENT_RUN_OPENCODE_VARIANT` — esfuerzo/variant; default `high`.
- `DEVELOPMENT_RUN_QA_COMMAND` — comando QA opcional; si no está, se autodetecta `php artisan test` o `npm test`.

### Full development environment

Use this when you are actively developing the app and want Laravel, queues, logs, and Vite running together:

```powershell
composer dev
```

That Composer script starts:

| Process | Purpose |
| --- | --- |
| `php artisan serve` | Serves the Laravel app at `http://127.0.0.1:8000`. |
| `php artisan queue:listen --tries=1 --timeout=0` | Runs queued jobs while developing. |
| `php artisan pail --timeout=0` | Streams Laravel logs in the terminal. |
| `npm run dev` | Starts Vite for frontend assets and hot reload. |

Rule of thumb: use `.\bin\dev-orchestrator.ps1 -Port 8001` to inspect the dashboard quickly; use `composer dev` when changing the application or frontend. The launcher avoids the generic PHP configuration conflict, while `composer dev` still depends on the PHP available on `PATH`. Use the project, status, and attention filters to narrow the task list. Select a task title to view its detail page. The web UI records approve, needs-revision, and reject decisions, and archives final approved or rejected tasks without removing their worktrees; use the CLI for every other task action.

Task detail pages list the task-local artifacts that are available. Select **Ver diff del worktree** to inspect current changes before deciding; missing, removed, or non-Git worktrees show a safe explanatory message. Select an artifact to read it in the browser; the viewer only serves the documented artifact names and numbered `revision-{n}.md` files from that task's private storage directory. It renders escaped, read-only text and cannot browse arbitrary local paths.

## Commands

| Command | Behavior |
| --- | --- |
| `orchestrator:project-add {name} {repo_path}` | Validates and registers a local Git worktree. Options: `--default-branch`, `--test`, `--lint`, `--rules`. |
| `orchestrator:task-create {project} {title}` | Creates a `draft` task. Options: `--description`, `--acceptance`, repeatable `--expected-file=relative/path`, repeatable `--forbid-file=relative/path`, `--autonomy=low|medium|high`. File paths are normalized and reject absolute or `..` traversal paths. Content expectations are added after creation so file/text and file/pattern values remain unambiguous. |
| `orchestrator:task-expect-file {task} {file}` | Adds one relative expected file after task creation. Duplicate paths are ignored. |
| `orchestrator:task-forbid-file {task} {file}` | Adds one relative file that must remain untouched after task creation. Duplicate paths are ignored. |
| `orchestrator:task-expect-text {task} {file} {text}` | Adds literal text that must appear in a relative file. Duplicate file/text pairs are ignored. |
| `orchestrator:task-expect-regex {task} {file} {pattern}` | Adds a PCRE pattern that must match a relative file. Duplicate file/pattern pairs are ignored; invalid patterns are reported by the acceptance check. |
| `orchestrator:task-prepare {task}` | Creates `ai/task-{id}-{slug}` and a sibling worktree `{repo}-task-{id}`. Saves `prompt.md`. It refuses existing worktree paths. |
| `orchestrator:task-run {task}` | Regenerates the prompt and invokes `opencode run --dir <worktree> <prompt>` when available. No commit or push is performed. |
| `orchestrator:task-batch-run {tasks*}` | Runs independent `prepared`, `blocked`, or `failed` tasks with `--concurrency=1..4` (default `2`). `draft` tasks require `--prepare`; `completed` tasks are skipped with a warning; `running` and `archived` tasks are refused. Options: `--prepare`, `--verify`, `--acceptance`, `--review`. Saves a batch artifact and never archives tasks. |
| `orchestrator:task-verify {task}` | Runs configured project test and lint commands with PowerShell in the task worktree, or the project repository when no worktree exists. Use `--test` or `--lint` to run one command only. The command never changes Git state. |
| `orchestrator:task-acceptance-check {task}` | Checks configured expected files and forbidden-file changes in the task worktree, falling back to the project repository only when no worktree exists. Saves an acceptance artifact and exits non-zero for `failed` or `skipped`. |
| `orchestrator:task-review {task}` | Captures current Git status, diff stat, modified files, and `TASK_SUMMARY.md` if the agent wrote it. |
| `orchestrator:task-approve {task}` | Records the human decision as `approved` and changes task status to `approved`. Option: `--notes`. It never commits, pushes, or merges. |
| `orchestrator:task-reject {task}` | Records the human decision as `rejected` and changes task status to `rejected`. Option: `--reason`. It never commits, pushes, or merges. |
| `orchestrator:task-revision {task}` | Records the human decision as `needs_revision` and changes task status to `needs_revision`. Option: `--reason`. It never commits, pushes, or merges. |
| `orchestrator:task-rerun {task}` | Reruns a `needs_revision`, `failed`, or `blocked` task in its existing worktree. Options: `--instructions`, `--verify`, `--acceptance`, `--review`. It clears the previous human decision because a new review is required; it refuses `completed`, `running`, `approved`, and `archived` tasks. |
| `orchestrator:task-archive {task}` | Saves the task's final Git status, diff stat, changed-file list, latest commit, and tracked patch before marking it archived. `--remove-worktree` safely removes the Git worktree only after those artifacts are saved. |
| `orchestrator:task-status {task?}` | Shows a compact task dashboard with status summary, attention counts, review/verification/acceptance state, updated age, and recommended next action. A task ID shows full task, artifact, check-count, branch, and worktree details. Options: `--project=name`, `--status=status`, `--attention`, `--limit=25`. |
| `orchestrator:task-open {task}` | Opens the task worktree in VS Code. |
| `orchestrator:weekly-report` | Prints a Monday-meeting report. Options: `--since=YYYY-MM-DD`, `--until=YYYY-MM-DD`, `--project=name`, and `--save`. |

## Task dashboard

Use the default dashboard to focus work without reading artifact files first:

```powershell
.\bin\artisan.ps1 orchestrator:task-status --attention
.\bin\artisan.ps1 orchestrator:task-status --project=my-app --status=completed --limit=10
.\bin\artisan.ps1 orchestrator:task-status 2
```

`--attention` limits the table to tasks awaiting human review, with failed verification or acceptance, needing revision, running, or blocked. The normal table deliberately omits branch and worktree paths; request one task by ID for those paths, review notes, artifact locations, archive information, and expected/forbidden/content-check counts.

## Artifacts and review

Each task's local artifacts are stored under `storage/app/private/orchestrator/tasks/{task_id}/`:

- `prompt.md`: structured OpenCode task prompt, including rules and safety constraints.
- `run.log`: OpenCode output or the missing-CLI explanation.
- `verification.md`: test and lint commands, output, exit codes, durations, directories, and timestamps. The latest result is linked from reviews and retained in archives and weekly reports.
- `acceptance.md`: expected files, found and missing files, forbidden files, clean and violated forbidden files, content checks (configured, passed, failed, and invalid regexes), all touched files, directory used, timestamps, status, and the next action. `passed` means only that the configured checks passed.
- `review.md`: Git status, diff stat, modified files, and the agent summary.
- `decision.md`: the human approval, rejection, or revision request with notes, review link, verification status, and worktree location.
- `revision-{n}.md`: the rerun prompt with the original task, latest decision, review and verification references, and additional instructions.
- `rerun.md`: an append-only log of revision attempts, previous decisions, exit statuses, artifact paths, and next actions.
- `archive.md`: final task metadata and Git snapshot retained for history.
- `final.patch`: tracked final diff when one exists.

Archiving never deletes the database task record. `--remove-worktree` is optional and runs only after `archive.md` and any `final.patch` have been written, so Monday's history remains available after cleanup.

Saved weekly reports are stored under `storage/app/private/orchestrator/reports/weekly-{date}.md`. Batch summaries are stored under `storage/app/private/orchestrator/batches/{timestamp}/batch.md` and include task outcomes, artifact paths, next actions, and potential overlapping file changes.

Open the worktree folder in VS Code to review and test when the task is ready. Use GitHub Desktop only after that review; this application does not perform Git commits or pushes.

## File acceptance

Declare objective file outputs when creating a task, or add one later:

```powershell
.\bin\artisan.ps1 orchestrator:task-create my-app "Document batch runs" --expected-file=docs\batch-run-happy-path.md --forbid-file=README.md
.\bin\artisan.ps1 orchestrator:task-expect-file 2 docs\batch-run-troubleshooting.md
.\bin\artisan.ps1 orchestrator:task-forbid-file 2 composer.lock
.\bin\artisan.ps1 orchestrator:task-expect-text 2 docs\batch-run-happy-path.md "The command never archives tasks."
.\bin\artisan.ps1 orchestrator:task-expect-regex 2 docs\batch-run-happy-path.md '/orchestrator:task-batch-run.*--concurrency/'
.\bin\artisan.ps1 orchestrator:task-acceptance-check 2
```

The check uses the existing task worktree when available; otherwise it uses the registered project repository. It reports `passed` when every expected file exists, every forbidden file is untouched, every literal text is present, and every regex matches. It reports `failed` when a content-check file is missing or unreadable, literal text is absent, a regex does not match or is invalid, an expected file is missing, or a forbidden path appears in `git diff --name-only`, `git diff --cached --name-only`, or `git ls-files --others --exclude-standard`. It reports `skipped` only when no expected files, forbidden files, text checks, or regex checks are configured. A failed or skipped standalone check returns non-zero for scripts and CI-style automation.

Forbidden files catch unwanted edits such as changing `README.md` while creating `docs\batch-run-happy-path.md`. They are not a substitute for human review: a passing result does not evaluate content, tests, design, or whether the task should be approved.

## Revision loop

When human review finds a correctable issue, follow this loop:

```text
revision -> rerun -> verify/review -> approve/reject/revision
```

`orchestrator:task-revision` records the feedback. `orchestrator:task-rerun` uses the same worktree, retains good changes, and clears the old decision so the rerun cannot be accepted without a new human review. A `completed` rerun means OpenCode finished; it does not mean the work was accepted.

## Parallel tasks

Prepare multiple task IDs. Every task has its own branch and sibling worktree, so separate OpenCode runs can work independently without sharing a working directory. `orchestrator:task-batch-run` starts no more than the selected concurrency cap at once and retains results even if one task fails. It lists potential file conflicts after the runs, including failed runs, so a human can reconcile overlapping changes before accepting either task.

## Limits of this MVP

- It assumes the configured default branch exists locally; it does not fetch remotes.
- The dashboard only records safe human review decisions and does not replace review of artifacts.
- The web UI can archive only finally reviewed tasks and never removes their worktrees or modifies Git state; it cannot run, rerun, delete, commit, push, or merge.
