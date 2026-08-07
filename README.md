# Dev Orchestrator

Local-first Laravel MVP for preparing independent developer tasks in Git worktrees. It stores project and task metadata in SQLite, generates OpenCode prompts, and retains run and review artifacts. It never commits, stages, pushes, or changes Git configuration.

## Requirements

- Git available on `PATH`.
- A local Git repository and its base branch already available locally.
- PHP is invoked through `bin\artisan.ps1`. This wrapper uses the existing MAMP PHP installation with an app-local `php.ini`; it does not change MAMP or Windows PATH.
- `opencode` is optional. `orchestrator:task-run` marks a task `blocked` and retains artifacts if it cannot find the CLI.

## Quick start

From this project directory in PowerShell:

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

Use the smallest command that matches what you need. The web dashboard is optimized for a developer review flow: start with the attention queue, see why a task needs attention, open its available artifacts in a low-strain read-only viewer, then record a safe local human decision. It shows task status and attention counts, task details, available task artifacts, and configured acceptance expectations. Task detail pages support only approve, request revision, or reject. The UI does not run, rerun, archive, modify Git state, commit, push, or merge.

### Dashboard only

Use this when you want to open the local dashboard. It starts the Laravel HTTP server and nothing else:

```powershell
.\bin\artisan.ps1 serve
```

Then open `http://127.0.0.1:8000`.

This is enough for the current dashboard because the Blade views use server-rendered HTML and do not require a frontend dev server.

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

Rule of thumb: use `.\bin\artisan.ps1 serve` to inspect the dashboard quickly; use `composer dev` when changing the application or frontend. Use the project, status, and attention filters to narrow the task list. Select a task title to view its detail page. The web UI only records approve, needs-revision, and reject decisions; use the CLI for every other task action.

Task detail pages list the task-local artifacts that are available. Select an artifact to read it in the browser; the viewer only serves the documented artifact names and numbered `revision-{n}.md` files from that task's private storage directory. It renders escaped, read-only text and cannot browse arbitrary local paths.

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
- The web UI cannot run, rerun, archive, delete, commit, push, merge, or otherwise modify Git state.
