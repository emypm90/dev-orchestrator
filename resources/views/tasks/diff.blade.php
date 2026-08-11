@extends('layouts.app', ['title' => 'Diff - Tarea #'.$task->id, 'heading' => 'Visor de diff'])

@section('content')
    <a class="back-link" href="{{ route('tasks.show', $task) }}">&larr; Volver a la revisión de la tarea</a>

    <section class="panel">
        <div class="panel-header"><div><p class="eyebrow">Tarea #{{ $task->id }} / {{ $task->project->name }}</p><h2>{{ $task->title }}</h2><p class="panel-copy">Cambios actuales del worktree, solo para inspección. Esta vista no modifica Git.</p></div></div>
        <div class="artifact-meta">
            <div class="meta-card"><span>Estado</span><strong>{{ $task->status }}</strong></div>
            <div class="meta-card"><span>Ruta del worktree</span><strong class="mono">{{ $task->worktree_path ?: '-' }}</strong></div>
            <div class="meta-card"><span>Archivos cambiados</span><strong>{{ count($files) }}</strong></div>
        </div>
        @if ($warning)
            <p class="diff-warning">{{ $warning }}</p>
        @endif
    </section>

    @foreach ($files as $file)
        <section class="panel diff-file">
            <div class="viewer-toolbar"><span>{{ $file['status'] }} / {{ $file['path'] }}</span><span>SOLO LECTURA</span></div>
            <pre class="artifact-content">{{ $file['diff'] }}</pre>
        </section>
    @endforeach
@endsection
