@extends('layouts.app', ['title' => $artifact.' - Tarea #'.$task->id, 'heading' => 'Visor de artefactos'])

@section('content')
    <a class="back-link" href="{{ route('tasks.show', $task) }}">&larr; Volver a la revisión de la tarea</a>

    <section class="panel">
        <div class="panel-header"><div><p class="eyebrow">Tarea #{{ $task->id }} / {{ $task->project->name }}</p><h2>{{ $task->title }}</h2><p class="panel-copy">Evidencia de artefacto de solo lectura. El contenido se escapa y solo se sirve desde los archivos locales permitidos de esta tarea.</p></div></div>
        <div class="artifact-meta">
            <div class="meta-card"><span>Artefacto</span><strong class="mono">{{ $artifact }}</strong></div>
            <div class="meta-card"><span>Tamaño</span><strong>{{ number_format($size) }} bytes</strong></div>
            <div class="meta-card"><span>Última modificación</span><strong>{{ $lastModified }}</strong></div>
        </div>
    </section>

    <section class="panel artifact-viewer">
        <div class="viewer-toolbar"><span>CONTENIDO DE SOLO LECTURA / {{ $artifact }}</span><span class="viewer-lights"><i></i><i></i><i></i></span></div>
        <pre class="artifact-content">{{ $content }}</pre>
    </section>
@endsection
