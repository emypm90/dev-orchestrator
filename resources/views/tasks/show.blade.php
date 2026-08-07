@extends('layouts.app', ['title' => 'Tarea #'.$task->id, 'heading' => 'Revisión de tarea'])

@section('content')
    @php
        $stateClass = fn (?string $value) => 'state-'.str_replace(' ', '_', $value ?: 'empty');
        $artifactByName = collect($artifacts)->keyBy('name');
        $verificationArtifact = $artifactByName->get('verification.md');
        $acceptanceArtifact = $artifactByName->get('acceptance.md');
        $requiresAttention = $task->last_verification_status === 'failed'
            || $task->last_acceptance_status === 'failed'
            || $task->review_decision === 'needs_revision'
            || ($task->status === 'completed' && $task->review_decision === null);
        $recommendedArtifactNames = match (true) {
            $task->last_acceptance_status === 'failed' => ['acceptance.md', 'verification.md', 'review.md', 'prompt.md'],
            $task->last_verification_status === 'failed' => ['verification.md', 'acceptance.md', 'review.md', 'prompt.md'],
            $task->review_decision !== null => ['decision.md', 'review.md', 'prompt.md', 'acceptance.md'],
            default => ['review.md', 'prompt.md', 'verification.md', 'acceptance.md'],
        };
    @endphp

    <a class="back-link" href="{{ route('tasks.index') }}">&larr; Volver al centro de control</a>

    <section class="panel">
        <p class="eyebrow">Tarea #{{ $task->id }} / {{ $task->project->name }}</p>
        <h2 class="task-heading">{{ $task->title }}</h2>
        <div class="state-row">
            <span class="badge status-{{ str_replace(' ', '_', $task->status) }}">{{ $presenter->label($task->status) }}</span>
            <span class="badge {{ $stateClass($task->review_decision) }}">Revisión: {{ $presenter->label($task->review_decision, 'Sin revisar') }}</span>
            <span class="badge {{ $stateClass($task->last_verification_status) }}">Verificación: {{ $presenter->label($task->last_verification_status, 'Sin ejecutar') }}</span>
            <span class="badge {{ $stateClass($task->last_acceptance_status) }}">Aceptación: {{ $presenter->label($task->last_acceptance_status, 'Sin ejecutar') }}</span>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Resumen para decidir</h2><p class="panel-copy">Lo necesario para decidir antes de entrar al detalle técnico.</p></div></div>
        <div class="decision-summary">
            <section class="summary-card summary-objective">
                <span class="section-kicker">Objetivo de la tarea</span>
                <h3>{{ $task->title }}</h3>
                <p>{{ $task->description ?: 'No hay descripción cargada.' }}</p>
            </section>
            <section class="summary-card">
                <span class="section-kicker">Criterios de aceptación</span>
                <p>{{ $task->acceptance_criteria ?: 'No hay criterios cargados.' }}</p>
            </section>
            <section class="summary-card summary-attention">
                <span class="section-kicker">Resultado actual</span>
                <h3>{{ $requiresAttention ? 'Por qué requiere atención' : 'Estado de la decisión' }}</h3>
                <div class="attention-reasons">
                    @if ($task->last_verification_status === 'failed')
                        <p>La verificación falló. Revisá
                            @if ($verificationArtifact['exists'])
                                <a href="{{ route('tasks.artifacts.show', ['task' => $task, 'artifact' => 'verification.md']) }}">verification.md</a>
                            @else
                                <span class="mono">verification.md (no disponible)</span>
                            @endif
                            para ver el resultado.
                        </p>
                    @endif
                    @if ($task->last_acceptance_status === 'failed')
                        <p>La comprobación de aceptación falló. Revisá
                            @if ($acceptanceArtifact['exists'])
                                <a href="{{ route('tasks.artifacts.show', ['task' => $task, 'artifact' => 'acceptance.md']) }}">acceptance.md</a>
                            @else
                                <span class="mono">acceptance.md (no disponible)</span>
                            @endif
                            para ver qué expectativa no se cumplió.
                        </p>
                    @endif
                    @if ($task->status === 'archived')
                        <p>La tarea está archivada; no requiere una nueva decisión.</p>
                    @elseif ($task->review_decision === 'needs_revision')
                        <p>Se solicitó una revisión. Motivo registrado:</p>
                        <blockquote>{{ $task->review_notes ?: 'No se registró un motivo.' }}</blockquote>
                    @elseif ($task->status === 'completed' && $task->review_decision === null)
                        <p>La tarea se completó y espera una decisión de revisión humana.</p>
                    @elseif ($task->review_decision === 'approved')
                        <p>La tarea fue aprobada en la revisión humana.</p>
                    @elseif ($task->review_decision === 'rejected')
                        <p>La tarea fue rechazada en la revisión humana.</p>
                    @elseif ($task->last_verification_status !== 'failed' && $task->last_acceptance_status !== 'failed')
                        <p>Estado actual: {{ $presenter->label($task->status) }}.</p>
                    @endif
                </div>
            </section>
            <section class="summary-card summary-next-action">
                <span class="section-kicker">Próximo paso recomendado</span>
                <strong>{{ $presenter->nextAction($task, 'es') }}</strong>
            </section>
            <section class="summary-card summary-evidence">
                <span class="section-kicker">Evidencia recomendada</span>
                <p>Abrí estos artefactos primero, en este orden:</p>
                <ol>
                    @foreach ($recommendedArtifactNames as $artifactName)
                        @php($artifact = $artifactByName->get($artifactName))
                        <li>
                            @if ($artifact['exists'])
                                <a class="mono" href="{{ route('tasks.artifacts.show', ['task' => $task, 'artifact' => $artifactName]) }}">{{ $artifactName }}</a>
                            @else
                                <span class="muted mono">{{ $artifactName }} (no disponible)</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Evidencia de revisión</h2><p class="panel-copy">Seguí la evidencia recomendada de arriba. Las entradas atenuadas todavía no se generaron para esta tarea.</p></div></div>
        <ul class="artifact-grid">
            @foreach ($artifacts as $artifact)
                <li>
                    @if ($artifact['exists'])
                        <a class="artifact-link mono" href="{{ route('tasks.artifacts.show', ['task' => $task, 'artifact' => $artifact['name']]) }}"><span class="artifact-icon">&#9635;</span>{{ $artifact['name'] }}</a>
                    @else
                        <span class="artifact-unavailable mono"><span>&#8211;</span>{{ $artifact['name'] }} (no disponible)</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Registrar una decisión de revisión</h2><p class="panel-copy">Elegí un resultado después de leer la evidencia. La decisión es un registro humano, no un comando de tarea.</p></div></div>
        <p class="safety-notice review-safety"><span class="safety-icon">&#9672;</span><span>Estas acciones solo registran una decisión humana. No ejecutan, archivan ni modifican el estado de Git.</span></p>
        <div class="review-actions">
            <form method="POST" action="{{ route('tasks.approve', $task) }}">
                @csrf
                <h3>Aprobar</h3><p>Confirmá que el trabajo revisado es aceptable. Esto no integra ni modifica Git.</p>
                <label for="notes">Notas opcionales<textarea id="notes" name="notes" maxlength="2000">{{ old('notes') }}</textarea></label>
                <button type="submit">Registrar aprobación</button>
            </form>
            <form class="revision-panel" method="POST" action="{{ route('tasks.revision', $task) }}">
                @csrf
                <h3>Requiere revisión</h3><p>Registrá comentarios concretos para un ciclo de revisión posterior ejecutado desde la CLI.</p>
                <label for="revision-reason">Motivo<textarea id="revision-reason" name="reason" maxlength="2000">{{ old('reason') }}</textarea></label>
                <button class="revision-button" type="submit">Solicitar revisión</button>
            </form>
            <form class="reject-panel" method="POST" action="{{ route('tasks.reject', $task) }}">
                @csrf
                <h3>Rechazar</h3><p>Registrá que esta tarea no debe aceptarse en su estado actual.</p>
                <label for="reject-reason">Motivo<textarea id="reject-reason" name="reason" maxlength="2000">{{ old('reason') }}</textarea></label>
                <button class="reject-button" type="submit">Registrar rechazo</button>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Expectativas de aceptación</h2><p class="panel-copy">Las comprobaciones configuradas aportan evidencia, pero no reemplazan la revisión humana.</p></div></div>
        <div class="expectations">
            <section><h3>Archivos esperados ({{ count($task->expected_files ?? []) }})</h3><ul>@forelse ($task->expected_files ?? [] as $file)<li class="mono">{{ $file }}</li>@empty<li class="muted">No hay ninguno configurado.</li>@endforelse</ul></section>
            <section><h3>Archivos prohibidos ({{ count($task->forbidden_files ?? []) }})</h3><ul>@forelse ($task->forbidden_files ?? [] as $file)<li class="mono">{{ $file }}</li>@empty<li class="muted">No hay ninguno configurado.</li>@endforelse</ul></section>
            <section><h3>Textos esperados ({{ count($task->expected_texts ?? []) }})</h3><ul>@forelse ($task->expected_texts ?? [] as $expectation)<li><span class="mono">{{ $expectation['file'] }}</span>: {{ $expectation['text'] }}</li>@empty<li class="muted">No hay ninguno configurado.</li>@endforelse</ul></section>
            <section><h3>Expresiones regulares esperadas ({{ count($task->expected_regexes ?? []) }})</h3><ul>@forelse ($task->expected_regexes ?? [] as $expectation)<li><span class="mono">{{ $expectation['file'] }}</span>: <code>{{ $expectation['pattern'] }}</code></li>@empty<li class="muted">No hay ninguna configurada.</li>@endforelse</ul></section>
        </div>
    </section>

    <section class="panel technical-details">
        <div class="panel-header"><div><h2>Detalles técnicos</h2><p class="panel-copy">Metadatos de soporte para consultar después de revisar el resumen y la evidencia.</p></div></div>
        <dl class="detail">
            <dt>Proyecto</dt><dd>{{ $task->project->name }}</dd>
            <dt>Rama</dt><dd class="mono">{{ $task->branch_name ?? '-' }}</dd>
            <dt>Ruta del worktree</dt><dd class="mono">{{ $task->worktree_path ?? '-' }}</dd>
            <dt>Decisión de revisión</dt><dd>{{ $presenter->label($task->review_decision, '-') }}</dd>
            <dt>Revisada</dt><dd>{{ $task->reviewed_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Artefacto de verificación</dt><dd class="mono">{{ $task->last_verification_path ?? '-' }}</dd>
            <dt>Verificada</dt><dd>{{ $task->last_verified_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Artefacto de aceptación</dt><dd class="mono">{{ $task->last_acceptance_path ?? '-' }}</dd>
            <dt>Aceptación comprobada</dt><dd>{{ $task->last_acceptance_checked_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Artefacto de archivo</dt><dd class="mono">{{ $task->archive_path ?? '-' }}</dd>
            <dt>Archivada</dt><dd>{{ $task->archived_at?->toDateTimeString() ?? '-' }}</dd>
        </dl>
    </section>
@endsection
