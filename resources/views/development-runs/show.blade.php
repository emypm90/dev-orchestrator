<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $run->title }} | command.flow</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #030711; color: #e8f1ff; }
        * { box-sizing: border-box; }
        body { min-width: 320px; min-height: 100vh; margin: 0; background: radial-gradient(ellipse 62% 45% at 50% 35%, rgba(8, 93, 127, .21), transparent 70%), radial-gradient(ellipse 34% 28% at 88% 4%, rgba(100, 63, 196, .12), transparent 75%), #030711; }
        body::before { position: fixed; inset: 0; background-image: linear-gradient(rgba(148, 163, 184, .035) 1px, transparent 1px), linear-gradient(90deg, rgba(148, 163, 184, .035) 1px, transparent 1px); background-size: 38px 38px; content: ""; mask-image: linear-gradient(to bottom, black, transparent 82%); pointer-events: none; }
        a { color: inherit; }
        .shell { position: relative; width: min(1080px, 100%); margin: 0 auto; padding: 24px clamp(18px, 4vw, 52px) 50px; }
        header, .head, .meta { display: flex; align-items: center; justify-content: space-between; gap: 18px; }
        .brand { color: #f8fbff; font: 800 .9rem "Cascadia Code", Consolas, monospace; letter-spacing: -.05em; text-decoration: none; }
        .meta, .eyebrow, .kicker { color: #9aeaf5; font: .67rem "Cascadia Code", Consolas, monospace; letter-spacing: .12em; text-transform: uppercase; }
        main { padding: clamp(64px, 13vh, 126px) 0; }
        h1 { max-width: 850px; margin: 0; color: #f8fbff; font-size: clamp(2.2rem, 6vw, 4.8rem); letter-spacing: -.075em; line-height: .98; }
        .lead { max-width: 680px; margin: 20px 0 38px; color: #a8b7ca; font-size: clamp(.96rem, 1.7vw, 1.08rem); line-height: 1.6; white-space: pre-wrap; }
        .flow { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; margin-bottom: 36px; }
        .step { padding-top: 12px; color: #60738a; border-top: 1px solid rgba(148, 163, 184, .24); font: .68rem "Cascadia Code", Consolas, monospace; }
        .step.active, .step.done { color: #d8f8fc; border-color: #38d9ef; text-shadow: 0 0 18px rgba(56, 217, 239, .4); }
        .step.done { color: #75efbd; border-color: rgba(85, 239, 176, .72); }
        .stage { position: relative; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 28px; padding: clamp(8px, 2vw, 18px) 0 clamp(10px, 2vw, 22px) clamp(18px, 3vw, 30px); border-left: 1px solid rgba(34, 211, 238, .46); }
        .stage::before { position: absolute; top: 4px; left: -5px; width: 9px; height: 9px; background: #55efb0; border-radius: 50%; box-shadow: 0 0 18px #55efb0; content: ""; }
        h2 { margin: 0; color: #f8fbff; font-size: clamp(1.55rem, 3vw, 2.55rem); letter-spacing: -.07em; line-height: 1; }
        .state { height: fit-content; padding: 7px 9px; color: #68f5bb; border: 1px solid rgba(86, 239, 178, .28); background: rgba(8, 31, 28, .48); font: .64rem "Cascadia Code", Consolas, monospace; text-transform: uppercase; }
        .artifact-board { display: grid; gap: 14px; max-width: 900px; margin-top: 30px; }
        .artifact-group { border: 1px solid rgba(34, 211, 238, .16); background: linear-gradient(90deg, rgba(5, 12, 23, .72), rgba(8, 18, 32, .28)); }
        .artifact-group > summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 15px 18px; color: #dff7ff; font: 750 .78rem "Cascadia Code", Consolas, monospace; cursor: pointer; list-style: none; }
        .artifact-group > summary::-webkit-details-marker, .artifact > summary::-webkit-details-marker { display: none; }
        .artifact-count { color: #75efbd; font-size: .68rem; }
        .artifact-list { display: grid; gap: 10px; padding: 0 14px 14px; }
        .artifact { padding: 16px 18px; border: 1px solid rgba(34, 211, 238, .12); background: rgba(3, 9, 18, .46); }
        .artifact[open] { background: rgba(5, 14, 26, .78); }
        .artifact summary { color: #dff7ff; font: 650 .78rem "Cascadia Code", Consolas, monospace; letter-spacing: .02em; cursor: pointer; }
        .artifact p { margin: 0; color: #c8e7ff; font: 500 .92rem "Cascadia Code", Consolas, monospace; line-height: 1.72; white-space: pre-wrap; }
        .artifact summary + p { margin-top: 15px; }
        .artifact-meta { display: grid; gap: 4px; margin-top: 14px; padding-top: 12px; color: #7890aa; border-top: 1px solid rgba(122, 146, 173, .16); font: .68rem "Cascadia Code", Consolas, monospace; line-height: 1.55; }
        .stage-note { max-width: 820px; margin-top: 14px; color: #8da3ba; font: .78rem "Cascadia Code", Consolas, monospace; line-height: 1.6; }
        .running-note { max-width: 860px; margin-top: 18px; padding: 12px 14px; color: #d8fbff; border: 1px solid rgba(86, 234, 249, .24); background: rgba(8, 18, 32, .62); font: .78rem "Cascadia Code", Consolas, monospace; line-height: 1.6; }
        .details { margin: 18px 0 0; color: #7890aa; font: .72rem "Cascadia Code", Consolas, monospace; }
        .repo-form { display: grid; gap: 8px; max-width: 860px; margin-top: 22px; padding: 16px 18px; border: 1px solid rgba(34, 211, 238, .14); background: linear-gradient(90deg, rgba(5, 12, 23, .72), rgba(8, 18, 32, .32)); }
        .project-context { max-width: 860px; margin-top: 22px; padding: 16px 18px; border: 1px solid rgba(85, 239, 176, .18); background: rgba(8, 31, 28, .34); }
        .project-context p { margin: 8px 0 0; color: #a8b7ca; font: .78rem "Cascadia Code", Consolas, monospace; line-height: 1.6; white-space: pre-wrap; }
        .repo-form label { color: #9aeaf5; font: .67rem "Cascadia Code", Consolas, monospace; letter-spacing: .08em; text-transform: uppercase; }
        .repo-row { display: flex; flex-wrap: wrap; gap: 10px; }
        .repo-row input { flex: 1 1 360px; min-height: 41px; padding: 10px 12px; color: #e8f1ff; border: 1px solid rgba(122, 146, 173, .34); background: rgba(3, 11, 22, .78); font: 500 .78rem "Cascadia Code", Consolas, monospace; outline: none; }
        .repo-row input:focus { border-color: rgba(86, 234, 249, .68); box-shadow: 0 0 0 3px rgba(34, 211, 238, .08); }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 28px; }
        .actions form { margin: 0; }
        .action { display: inline-flex; align-items: center; min-height: 41px; padding: 10px 13px; color: #d8fbff; border: 1px solid rgba(86, 234, 249, .48); background: rgba(8, 18, 32, .54); font: 750 .78rem Inter, ui-sans-serif, system-ui, sans-serif; letter-spacing: .02em; text-decoration: none; cursor: pointer; }
        .action:hover { border-color: rgba(86, 234, 249, .75); filter: brightness(1.1); }
        @media (max-width: 720px) { .flow { grid-template-columns: 1fr; } .stage { grid-template-columns: 1fr; } .state { width: fit-content; } }
    </style>
</head>
<body>
    <div class="shell" data-run-status="{{ $run->status }}" data-run-stage="{{ $run->active_stage }}" data-status-url="{{ route('development-runs.status', $run) }}">
        <header><a class="brand" href="{{ route('home') }}">command.flow</a><span class="meta">run / {{ str_pad((string) $run->id, 3, '0', STR_PAD_LEFT) }}</span></header>
        <main>
            @php($hasTechnicalBrief = $run->artifacts->contains('type', 'technical_brief'))
            @php($hasImplementationSlices = $run->artifacts->contains('type', 'implementation_slices'))
            @php($hasBuildPlan = $run->artifacts->contains('type', 'build_plan'))
            @php($hasExecutionPrompt = $run->artifacts->contains('type', 'execution_prompt'))
            @php($hasOpenCodeExecution = $run->artifacts->contains('type', 'opencode_execution'))
            @php($hasQaReport = $run->artifacts->contains('type', 'qa_report'))
            @php($hasReviewReport = $run->artifacts->contains('type', 'review_report'))
            @php($runningStatuses = ['plan_running', 'slices_running', 'build_running', 'qa_running', 'review_running'])
            @php($interruptedStatuses = ['plan_interrupted', 'slices_interrupted', 'build_interrupted', 'qa_interrupted', 'review_interrupted'])
            @php($stageLabels = ['plan' => 'Plan', 'slices' => 'Slices', 'build' => 'Build', 'qa' => 'QA', 'review' => 'Revisión'])
            @php($isRunning = in_array($run->status, $runningStatuses, true))
            @php($isInterrupted = in_array($run->status, $interruptedStatuses, true))
            @php($executionStage = str($run->status)->before('_')->toString())
            @php($executionStageLabel = $stageLabels[$executionStage] ?? 'La etapa')
            @php($artifactStage = [
                'context' => 'Contexto',
                'plan_background_run' => 'Plan',
                'technical_brief' => 'Plan',
                'slices_background_run' => 'Slices',
                'implementation_slices' => 'Slices',
                'build_plan' => 'Build',
                'execution_prompt' => 'Build',
                'build_background_run' => 'Build',
                'opencode_execution' => 'Build',
                'qa_background_run' => 'QA',
                'qa_report' => 'QA',
                'review_background_run' => 'Revisión',
                'review_report' => 'Revisión',
                'stage_contract' => 'Contratos técnicos',
            ])
            @php($stageOrder = ['Contexto', 'Plan', 'Slices', 'Build', 'QA', 'Revisión', 'Contratos técnicos'])
            @php($artifactGroups = $run->artifacts->groupBy(fn ($artifact) => $artifactStage[$artifact->type] ?? 'Otros'))
            <p class="eyebrow">Development Run / {{ $run->status }}</p>
            <h1>{{ $run->title }}</h1>
            <p class="lead">{{ $run->initial_context }}</p>
            <section class="flow" aria-label="Progreso del Development Run"><span class="step {{ $run->active_stage === 'contexto' ? 'active' : 'done' }}">Contexto</span><span class="step {{ $run->active_stage === 'plan' ? 'active' : ($hasImplementationSlices ? 'done' : '') }}">Plan</span><span class="step {{ $run->active_stage === 'slices' ? 'active' : ($hasBuildPlan ? 'done' : '') }}">Slices</span><span class="step {{ $run->active_stage === 'build' ? 'active' : ($hasOpenCodeExecution ? 'done' : '') }}">Build</span><span class="step {{ $run->active_stage === 'qa' ? 'active' : ($hasQaReport ? 'done' : '') }}">QA</span><span class="step {{ $run->active_stage === 'review' ? 'active' : ($hasReviewReport ? 'done' : '') }}">Revisión</span></section>
            <section class="stage" aria-labelledby="active-stage-title">
                <div>
                    <p class="kicker">etapa activa / {{ ['contexto' => '01', 'plan' => '02', 'slices' => '03', 'build' => '04', 'qa' => '05', 'review' => '06'][$run->active_stage] ?? '01' }}</p>
                    <h2 id="active-stage-title">{{ ['contexto' => 'Intake / Contexto', 'plan' => 'Plan', 'slices' => 'Slices', 'build' => 'Build', 'qa' => 'QA', 'review' => 'Revisión'][$run->active_stage] ?? 'Intake / Contexto' }}</h2>
                    @error('implementation_slices')<p class="details">{{ $message }}</p>@enderror
                    @error('build_plan')<p class="details">{{ $message }}</p>@enderror
                    @error('execution_prompt')<p class="details">{{ $message }}</p>@enderror
                    @error('opencode_execution')<p class="details">{{ $message }}</p>@enderror
                    @error('qa_report')<p class="details">{{ $message }}</p>@enderror
                    @error('review_report')<p class="details">{{ $message }}</p>@enderror
                    @if ($isRunning)
                        <p class="running-note">{{ $executionStageLabel }} está corriendo en background. Podés dejar esta pantalla abierta; Command Flow va a actualizar cuando cambie el estado.</p>
                    @elseif ($isInterrupted)
                        <p class="running-note">{{ $executionStageLabel }} quedó interrumpido: el proceso background ya no está activo. Podés reintentar la etapa.</p>
                    @endif
                    <p class="stage-note">Artifacts organizados por etapa. Abrí solo lo que necesitás revisar; los contratos técnicos quedan separados para no ensuciar la lectura diaria.</p>
                    <section class="artifact-board" aria-label="Artifacts del Development Run">
                        @foreach ($stageOrder as $stageName)
                            @continue(! $artifactGroups->has($stageName))
                            @php($artifacts = $artifactGroups[$stageName])
                            <details class="artifact-group" {{ $stageName !== 'Contratos técnicos' ? 'open' : '' }}>
                                <summary><span>{{ $stageName }}</span><span class="artifact-count">{{ $artifacts->count() }} artifact{{ $artifacts->count() === 1 ? '' : 's' }}</span></summary>
                                <div class="artifact-list">
                                    @foreach ($artifacts as $artifact)
                                        <details class="artifact" {{ in_array($artifact->type, ['context', 'technical_brief', 'implementation_slices', 'build_plan', 'opencode_execution', 'qa_report', 'review_report'], true) ? 'open' : '' }}>
                                            <summary>{{ $artifact->title }}</summary>
                                            <p>{{ $artifact->body }}</p>
                                            @if (in_array($artifact->type, ['plan_background_run', 'slices_background_run', 'build_background_run', 'qa_background_run', 'review_background_run'], true))
                                                @php($metadata = $artifact->metadata ?? [])
                                                <div class="artifact-meta">
                                                    @if (data_get($metadata, 'pid'))<span>PID: {{ data_get($metadata, 'pid') }}</span>@endif
                                                    @if (data_get($metadata, 'started_at'))<span>Iniciado: {{ \Illuminate\Support\Carbon::parse(data_get($metadata, 'started_at'))->diffForHumans() }}</span>@endif
                                                    @if (data_get($metadata, 'finished_at'))<span>Finalizado: {{ \Illuminate\Support\Carbon::parse(data_get($metadata, 'finished_at'))->diffForHumans() }}</span>@endif
                                                    @if (data_get($metadata, 'interrupted_at'))<span>Interrumpido: {{ \Illuminate\Support\Carbon::parse(data_get($metadata, 'interrupted_at'))->diffForHumans() }}</span>@endif
                                                    @if (data_get($metadata, 'cancelled_at'))<span>Cancelado: {{ \Illuminate\Support\Carbon::parse(data_get($metadata, 'cancelled_at'))->diffForHumans() }}</span>@endif
                                                    @if (data_get($metadata, 'log_path'))<span>Log: {{ data_get($metadata, 'log_path') }}</span>@endif
                                                    @if (data_get($metadata, 'error_log_path'))<span>Error log: {{ data_get($metadata, 'error_log_path') }}</span>@endif
                                                    @if (data_get($metadata, 'php_executable'))<span>PHP: {{ data_get($metadata, 'php_executable') }}</span>@endif
                                                </div>
                                            @endif
                                        </details>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </section>
                    <p class="details">{{ $run->repository ? "Repositorio: {$run->repository}" : 'Repositorio aún no definido' }}{{ $run->project ? " · Proyecto: {$run->project}" : '' }}</p>
                    @if ($run->projectModel)
                        <section class="project-context" aria-label="Contexto de proyecto heredado">
                            <p class="kicker">Contexto heredado / <a href="{{ route('projects.show', $run->projectModel) }}">{{ $run->projectModel->name }}</a></p>
                            <p>{{ $run->projectModel->rules ?: 'Sin contexto reutilizable manual registrado.' }}</p>
                            @forelse ($run->contextAttachments as $attachment)
                                <p>{{ $attachment->original_name }} · {{ $attachment->status }}{{ $attachment->status_reason ? ' · '.$attachment->status_reason : '' }}</p>
                            @empty
                                <p>Sin adjuntos específicos del run.</p>
                            @endforelse
                        </section>
                    @else
                        <section class="project-context" aria-label="Contexto de proyecto heredado">
                            <p class="kicker">Contexto heredado</p>
                            <p>Este run todavía no está asociado a un proyecto durable.</p>
                            <p>Los adjuntos privados aparecen acá con estado uploaded, extracting, ready, failed o blocked.</p>
                        </section>
                    @endif
                    @error('repository')<p class="details">{{ $message }}</p>@enderror
                    <form class="repo-form" method="post" action="{{ route('development-runs.repository.update', $run) }}">
                        @csrf
                        @method('PATCH')
                        <label for="repository">Ruta local del repositorio</label>
                        <div class="repo-row">
                            <input id="repository" name="repository" value="{{ old('repository', $run->repository) }}" placeholder="C:\Users\dev21\Documents\proyecto\personal-dev-orchestrator">
                            <button class="action" type="submit">Guardar ruta</button>
                        </div>
                    </form>
                    <div class="actions">
                        @if ($run->active_stage === 'contexto')
                            <a class="action" href="{{ route('home') }}">Volver</a>
                        @elseif ($run->active_stage === 'plan')
                            <form method="post" action="{{ route('development-runs.context.return', $run) }}">@csrf<button class="action" type="submit">Volver</button></form>
                        @elseif ($run->active_stage === 'slices')
                            <form method="post" action="{{ route('development-runs.plan.return', $run) }}">@csrf<button class="action" type="submit">Volver</button></form>
                        @elseif ($run->active_stage === 'build')
                            <form method="post" action="{{ route('development-runs.slices.return', $run) }}">@csrf<button class="action" type="submit">Volver</button></form>
                        @elseif (in_array($run->active_stage, ['qa', 'review'], true) && ! $run->completed_at)
                            <form method="post" action="{{ route('development-runs.slices.return', $run) }}">@csrf<button class="action" type="submit">Volver</button></form>
                        @endif

                        @if ($run->active_stage === 'contexto' && $hasTechnicalBrief && ! $isRunning)
                            <form method="post" action="{{ route('development-runs.technical-brief.store', $run) }}">@csrf<button class="action" type="submit">Volver a Plan</button></form>
                        @elseif (in_array($run->active_stage, ['contexto', 'plan'], true) && ! $hasTechnicalBrief && ! $isRunning)
                            <form method="post" action="{{ route('development-runs.technical-brief.store', $run) }}">@csrf<button class="action" type="submit">{{ $run->status === 'plan_interrupted' ? 'Reintentar Plan' : 'Generar brief' }}</button></form>
                        @elseif ($run->active_stage === 'plan' && $hasTechnicalBrief && ! $isRunning)
                            <form method="post" action="{{ route('development-runs.implementation-slices.store', $run) }}">@csrf<button class="action" type="submit">Definir slices</button></form>
                        @elseif ($run->active_stage === 'slices' && ! $hasImplementationSlices && ! $isRunning)
                            <form method="post" action="{{ route('development-runs.implementation-slices.store', $run) }}">@csrf<button class="action" type="submit">{{ $run->status === 'slices_interrupted' ? 'Reintentar Slices' : 'Definir slices' }}</button></form>
                        @elseif ($run->active_stage === 'slices' && $hasImplementationSlices)
                            <form method="post" action="{{ route('development-runs.build-plan.store', $run) }}">@csrf<button class="action" type="submit">Preparar Build</button></form>
                        @elseif ($run->active_stage === 'build' && ! $hasExecutionPrompt)
                            <form method="post" action="{{ route('development-runs.execution-prompt.store', $run) }}">@csrf<button class="action" type="submit">Preparar prompt</button></form>
                        @elseif ($run->active_stage === 'build' && ! $hasOpenCodeExecution && ! $isRunning)
                            <form method="post" action="{{ route('development-runs.opencode-execution.store', $run) }}">@csrf<button class="action" type="submit">{{ $run->status === 'build_interrupted' ? 'Reintentar Build' : 'Ejecutar Build' }}</button></form>
                        @elseif ($run->active_stage === 'qa' && ! $isRunning && (! $hasQaReport || in_array($run->status, ['qa_failed', 'qa_blocked', 'qa_interrupted'], true)))
                            <form method="post" action="{{ route('development-runs.qa.store', $run) }}">@csrf<button class="action" type="submit">{{ in_array($run->status, ['qa_failed', 'qa_blocked', 'qa_interrupted'], true) ? 'Reintentar QA' : 'Ejecutar QA' }}</button></form>
                        @elseif ($run->active_stage === 'review' && ! $hasReviewReport && ! $isRunning)
                            <form method="post" action="{{ route('development-runs.review.store', $run) }}">@csrf<button class="action" type="submit">{{ $run->status === 'review_interrupted' ? 'Reintentar Revisión' : 'Cerrar run' }}</button></form>
                        @endif

                        @if ($isRunning)
                            <form method="post" action="{{ route('development-runs.execution.cancel', $run) }}">@csrf<button class="action" type="submit">Cancelar ejecución</button></form>
                        @endif
                    </div>
                </div>
                <span class="state">{{ $isRunning ? 'ejecutando' : ($isInterrupted ? 'interrumpido' : ($run->completed_at ? 'completo' : 'en curso')) }}</span>
            </section>
        </main>
    </div>
    @if ($isRunning)
        <script>
            (() => {
                const shell = document.querySelector('[data-status-url]');
                if (! shell) return;

                const initialStatus = shell.dataset.runStatus;
                const initialStage = shell.dataset.runStage;
                const statusUrl = shell.dataset.statusUrl;

                const poll = async () => {
                    try {
                        const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                        if (! response.ok) return;

                        const payload = await response.json();
                        if (! payload.running || payload.status !== initialStatus || payload.active_stage !== initialStage) {
                            window.location.reload();
                        }
                    } catch (error) {
                        // Keep the UI quiet; the next interval can recover.
                    }
                };

                window.setInterval(poll, 5000);
            })();
        </script>
    @endif
</body>
</html>
