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
.\bin\artisan.ps1 orchestrator:task-review 1
.\bin\artisan.ps1 orchestrator:task-status
```

## Commands

| Command | Behavior |
| --- | --- |
| `orchestrator:project-add {name} {repo_path}` | Validates and registers a local Git worktree. Options: `--default-branch`, `--test`, `--lint`, `--rules`. |
| `orchestrator:task-create {project} {title}` | Creates a `draft` task. Options: `--description`, `--acceptance`, `--autonomy=low|medium|high`. |
| `orchestrator:task-prepare {task}` | Creates `ai/task-{id}-{slug}` and a sibling worktree `{repo}-task-{id}`. Saves `prompt.md`. It refuses existing worktree paths. |
| `orchestrator:task-run {task}` | Regenerates the prompt and invokes `opencode run --dir <worktree> <prompt>` when available. No commit or push is performed. |
| `orchestrator:task-review {task}` | Captures current Git status, diff stat, modified files, and `TASK_SUMMARY.md` if the agent wrote it. |
| `orchestrator:task-status {task?}` | Lists task statuses, branches, and worktree paths. |

## Artifacts and review

Each task's local artifacts are stored under `storage/app/private/orchestrator/tasks/{task_id}/`:

- `prompt.md`: structured OpenCode task prompt, including rules and safety constraints.
- `run.log`: OpenCode output or the missing-CLI explanation.
- `review.md`: Git status, diff stat, modified files, and the agent summary.

Open the worktree folder in VS Code to review and test when the task is ready. Use GitHub Desktop only after that review; this application does not perform Git commits or pushes.

## Parallel tasks

Prepare multiple task IDs. Every task has its own branch and sibling worktree, so separate OpenCode runs can work independently without sharing a working directory. Avoid creating two tasks that deliberately edit the same feature until you are ready to reconcile their changes.

## Limits of this MVP

- It assumes the configured default branch exists locally; it does not fetch remotes.
- Test and lint commands are recorded with the project for prompt context, but are not automatically executed by the orchestrator yet.
- The dashboard is intentionally deferred: this first slice is CLI-first.
