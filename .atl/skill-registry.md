# Skill Registry — personal-dev-orchestrator

Generated: 2026-08-21
Mode: SDD init, Engram artifact store
Sources scanned: `C:\Users\dev21\.config\opencode\skills`, project skill directories, project convention files.

## Resolution Notes

- Project-specific skill registry did not exist before this initialization.
- No project-local skills were found in `.claude/skills`, `.gemini/skills`, `.agent/skills`, or `skills`.
- No project convention files were found for `AGENTS.md`, `CLAUDE.md`, `.cursorrules`, `GEMINI.md`, or `copilot-instructions.md`.
- SDD skills, `_shared`, and `skill-registry` are intentionally excluded from this registry per the sdd-init contract.
- `.opencode/agent/*.md` files are project agents, not registry skills; they were inspected for project context but not registered as skills.

## Project Context Rules Detected

- Laravel/PHP code should follow existing service/controller/model separation under `app/`.
- Local orchestration safety is core: do not stage, commit, push, merge, reset, checkout, or modify Git remotes from app workflows.
- Use project wrappers on Windows when executing PHP/Laravel commands: `bin\php.ps1` and `bin\artisan.ps1`.
- PHP formatting is Laravel Pint; current Pint check reports existing formatting drift and should be addressed before treating formatting as green.
- Tests run through Laravel/PHPUnit with Unit and Feature suites; Strict TDD defaults to enabled because a runner is present and no contrary marker was found.

## Registered User Skills

### branch-pr

- Trigger: creating, opening, or preparing PRs for review.
- Source: `C:\Users\dev21\.config\opencode\skills\branch-pr\SKILL.md`
- Every PR must link an issue with `status:approved`.
- Every PR must have exactly one `type:*` label.
- Branch names must match `^(feat|fix|chore|docs|style|refactor|perf|test|build|ci|revert)\/[a-z0-9._-]+$`.
- PR body must include issue link, type, summary, changed-file table, test plan, and checklist.
- Conventional commits are required; never add `Co-Authored-By` trailers.
- Run relevant checks before PR creation; shell scripts require shellcheck when modified.

### chained-pr

- Trigger: PRs over 400 changed lines, stacked PRs, review slices.
- Source: `C:\Users\dev21\.config\opencode\skills\chained-pr\SKILL.md`
- Split PRs over 400 changed lines unless a maintainer explicitly accepts `size:exception`.
- Keep each PR reviewable in about 60 minutes or less.
- Use one deliverable work unit per PR and keep tests/docs with the unit they verify.
- State chain start, end, dependencies, follow-up, and out-of-scope items in each PR.
- Retarget or rebase polluted diffs until only the current work unit appears.
- Do not mix chain strategies after one is chosen.

### cognitive-doc-design

- Trigger: guides, READMEs, RFCs, onboarding, architecture, review-facing docs.
- Source: `C:\Users\dev21\.config\opencode\skills\cognitive-doc-design\SKILL.md`
- Lead with the answer; put decisions/actions before background context.
- Use progressive disclosure: happy path first, details and edge cases later.
- Chunk content into small sections with clear headings and signposts.
- Prefer tables, checklists, examples, and templates over recall-heavy prose.
- For review docs, say what to review first and what is intentionally out of scope.
- Keep chained-work docs linked to previous and next PRs when applicable.

### comment-writer

- Trigger: PR feedback, issue replies, reviews, Slack/GitHub comments.
- Source: `C:\Users\dev21\.config\opencode\skills\comment-writer\SKILL.md`
- Start with the actionable point; do not recap everything first.
- Be warm, direct, and concise, usually 1 to 3 short paragraphs or tight bullets.
- Explain the technical reason when asking for a change.
- Avoid pile-ons; comment on the highest-value issue.
- Match the thread language; Spanish should use natural Rioplatense voseo.
- Do not use em dashes.

### go-testing

- Trigger: Go tests, go test coverage, Bubbletea `teatest`, golden files.
- Source: `C:\Users\dev21\.config\opencode\skills\go-testing\SKILL.md`
- Prefer table-driven tests with `t.Run` for multiple cases.
- Test behavior and state transitions, not implementation trivia.
- Use `t.TempDir()` for filesystem tests; never rely on a real home directory.
- Keep external-command or slow integration tests skippable with `testing.Short()`.
- Test Bubbletea `Model.Update()` directly for state; reserve `teatest` for interactive flows.
- Update golden files only through the repo's update path and rerun without update.

### issue-creation

- Trigger: creating GitHub issues, bug reports, feature requests.
- Source: `C:\Users\dev21\.config\opencode\skills\issue-creation\SKILL.md`
- Search existing issues before creating a new one.
- Use the required bug or feature template; blank issues are disabled.
- New issues get `status:needs-review`; a maintainer must add `status:approved` before PR work.
- Questions belong in Discussions, not issues.
- Fill all required fields and pre-flight checkboxes.
- Use approved issue linkage before opening related PRs.

### judgment-day

- Trigger: judgment day, dual review, adversarial review, `juzgar`.
- Source: `C:\Users\dev21\.config\opencode\skills\judgment-day\SKILL.md`
- Resolve project skills before launching judges and inject the same standards into all judge/fix prompts.
- Launch two blind judges in parallel for a specific target; do not self-review instead.
- Wait for both judges before synthesis.
- Treat warnings as real only if normal intended use can trigger them; otherwise downgrade to theoretical INFO.
- Ask before fixing Round 1 confirmed issues.
- After fixes, re-run both judges before terminal approval/escalation.

### skill-creator

- Trigger: new skills, agent instructions, documenting AI usage patterns.
- Source: `C:\Users\dev21\.config\opencode\skills\skill-creator\SKILL.md`
- Create skills only for reusable AI guidance, not one-off or trivial patterns.
- Apply `docs/skill-style-guide.md` when present; otherwise use the inline fallback rules.
- Skills are runtime instruction contracts, not human docs.
- Keep `description` quoted, YAML-safe, one physical line, and under the hard 250-character limit.
- Use sections: Activation Contract, Hard Rules, Decision Gates, Execution Steps, Output Contract, References.
- Move templates/schemas to `assets/` and detail/edge cases to `references/`.

### work-unit-commits

- Trigger: implementation, commit splitting, chained PRs, keeping tests/docs with code.
- Source: `C:\Users\dev21\.config\opencode\skills\work-unit-commits\SKILL.md`
- Commit by deliverable work unit, not by file type.
- Keep tests with the code they verify and docs with the behavior they explain.
- Each commit should tell a reviewable story and have a reasonable rollback boundary.
- Treat each work unit as a potential chained PR if the change grows.
- If SDD forecasts over 400 changed lines, group work into reviewable PR slices before implementation.
- Use conventional commit messages focused on outcome, not file lists.
