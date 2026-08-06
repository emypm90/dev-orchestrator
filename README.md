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
.\bin\artisan.ps1 orchestrator:task-create my-app "Add task export" --description="Export task records as CSV." --acceptance="CSV download contains the selected task fields." --autonomy=medium
.\bin\artisan.ps1 orchestrator:task-prepare 1
.\bin\artisan.ps1 orchestrator:task-run 1
.\bin\artisan.ps1 orchestrator:task-verify 1
.\bin\artisan.ps1 orchestrator:task-review 1
.\bin\artisan.ps1 orchestrator:task-approve 1 --notes="Verified the requested files and checks."
.\bin\artisan.ps1 orchestrator:task-open 1
.\bin\artisan.ps1 orchestrator:task-archive 1
.\bin\artisan.ps1 orchestrator:task-status
.\bin\artisan.ps1 orchestrator:weekly-report --save
```

To run independent tasks together, prepare and then run a controlled batch:

```powershell
.\bin\artisan.ps1 orchestrator:task-batch-run 2 3 4 --concurrency=2 --prepare --verify --review
```

The concurrency cap is deliberately limited to `1` through `4` (default `2`). Each task must have its own Git worktree. The batch artifact flags file paths modified by more than one task as a human-review warning; it never merges work or fails solely because of a conflict.

`completed` means the agent execution finished. It does not mean a human accepted the work. Review the artifacts, then explicitly approve, reject, or request revision before archiving or integrating changes elsewhere.

## Commands

| Command | Behavior |
| --- | --- |
| `orchestrator:project-add {name} {repo_path}` | Validates and registers a local Git worktree. Options: `--default-branch`, `--test`, `--lint`, `--rules`. |
| `orchestrator:task-create {project} {title}` | Creates a `draft` task. Options: `--description`, `--acceptance`, `--autonomy=low|medium|high`. |
| `orchestrator:task-prepare {task}` | Creates `ai/task-{id}-{slug}` and a sibling worktree `{repo}-task-{id}`. Saves `prompt.md`. It refuses existing worktree paths. |
| `orchestrator:task-run {task}` | Regenerates the prompt and invokes `opencode run --dir <worktree> <prompt>` when available. No commit or push is performed. |
| `orchestrator:task-batch-run {tasks*}` | Runs independent `prepared`, `blocked`, or `failed` tasks with `--concurrency=1..4` (default `2`). `draft` tasks require `--prepare`; `completed` tasks are skipped with a warning; `running` and `archived` tasks are refused. Options: `--prepare`, `--verify`, `--review`. Saves a batch artifact and never archives tasks. |
| `orchestrator:task-verify {task}` | Runs configured project test and lint commands with PowerShell in the task worktree, or the project repository when no worktree exists. Use `--test` or `--lint` to run one command only. The command never changes Git state. |
| `orchestrator:task-review {task}` | Captures current Git status, diff stat, modified files, and `TASK_SUMMARY.md` if the agent wrote it. |
| `orchestrator:task-approve {task}` | Records the human decision as `approved` and changes task status to `approved`. Option: `--notes`. It never commits, pushes, or merges. |
| `orchestrator:task-reject {task}` | Records the human decision as `rejected` and changes task status to `rejected`. Option: `--reason`. It never commits, pushes, or merges. |
| `orchestrator:task-revision {task}` | Records the human decision as `needs_revision` and changes task status to `needs_revision`. Option: `--reason`. It never commits, pushes, or merges. |
| `orchestrator:task-archive {task}` | Saves the task's final Git status, diff stat, changed-file list, latest commit, and tracked patch before marking it archived. `--remove-worktree` safely removes the Git worktree only after those artifacts are saved. |
| `orchestrator:task-status {task?}` | Lists task statuses, branches, and worktree paths. |
| `orchestrator:task-open {task}` | Opens the task worktree in VS Code. |
| `orchestrator:weekly-report` | Prints a Monday-meeting report. Options: `--since=YYYY-MM-DD`, `--until=YYYY-MM-DD`, `--project=name`, and `--save`. |

## Artifacts and review

Each task's local artifacts are stored under `storage/app/private/orchestrator/tasks/{task_id}/`:

- `prompt.md`: structured OpenCode task prompt, including rules and safety constraints.
- `run.log`: OpenCode output or the missing-CLI explanation.
- `verification.md`: test and lint commands, output, exit codes, durations, directories, and timestamps. The latest result is linked from reviews and retained in archives and weekly reports.
- `review.md`: Git status, diff stat, modified files, and the agent summary.
- `decision.md`: the human approval, rejection, or revision request with notes, review link, verification status, and worktree location.
- `archive.md`: final task metadata and Git snapshot retained for history.
- `final.patch`: tracked final diff when one exists.

Archiving never deletes the database task record. `--remove-worktree` is optional and runs only after `archive.md` and any `final.patch` have been written, so Monday's history remains available after cleanup.

Saved weekly reports are stored under `storage/app/private/orchestrator/reports/weekly-{date}.md`. Batch summaries are stored under `storage/app/private/orchestrator/batches/{timestamp}/batch.md` and include task outcomes, artifact paths, next actions, and potential overlapping file changes.

Open the worktree folder in VS Code to review and test when the task is ready. Use GitHub Desktop only after that review; this application does not perform Git commits or pushes.

## Parallel tasks

Prepare multiple task IDs. Every task has its own branch and sibling worktree, so separate OpenCode runs can work independently without sharing a working directory. `orchestrator:task-batch-run` starts no more than the selected concurrency cap at once and retains results even if one task fails. It lists potential file conflicts after the runs, including failed runs, so a human can reconcile overlapping changes before accepting either task.

## Limits of this MVP

- It assumes the configured default branch exists locally; it does not fetch remotes.
- The dashboard is intentionally deferred: this first slice is CLI-first.
