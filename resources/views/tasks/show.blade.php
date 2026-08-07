@extends('layouts.app', ['title' => 'Tarea #'.$task->id, 'heading' => 'Revisión de tarea'])

@section('content')
    @php
        $stateClass = fn (?string $value) => 'state-'.str_replace(' ', '_', $value ?: 'empty');
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

    <section class="next-action-card">
        <span class="section-kicker">Próximo paso recomendado</span>
        <strong>{{ $presenter->nextAction($task, 'es') }}</strong>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Contexto de la tarea</h2><p class="panel-copy">Usá estos metadatos para orientar la revisión antes de abrir la evidencia.</p></div></div>
        <dl class="detail">
            <dt>Proyecto</dt><dd>{{ $task->project->name }}</dd>
            <dt>Rama</dt><dd class="mono">{{ $task->branch_name ?? '-' }}</dd>
            <dt>Ruta del worktree</dt><dd class="mono">{{ $task->worktree_path ?? '-' }}</dd>
            <dt>Decisión de revisión</dt><dd>{{ $presenter->label($task->review_decision, '-') }}</dd>
            <dt>Revisada</dt><dd>{{ $task->reviewed_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Notas de revisión</dt><dd>{{ $task->review_notes ?? '-' }}</dd>
            <dt>Artefacto de verificación</dt><dd class="mono">{{ $task->last_verification_path ?? '-' }}</dd>
            <dt>Verificada</dt><dd>{{ $task->last_verified_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Artefacto de aceptación</dt><dd class="mono">{{ $task->last_acceptance_path ?? '-' }}</dd>
            <dt>Aceptación comprobada</dt><dd>{{ $task->last_acceptance_checked_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Artefacto de archivo</dt><dd class="mono">{{ $task->archive_path ?? '-' }}</dd>
            <dt>Archivada</dt><dd>{{ $task->archived_at?->toDateTimeString() ?? '-' }}</dd>
        </dl>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Evidencia de revisión</h2><p class="panel-copy">Abrí primero los artefactos disponibles. Las entradas atenuadas todavía no se generaron para esta tarea.</p></div></div>
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
@endsection
