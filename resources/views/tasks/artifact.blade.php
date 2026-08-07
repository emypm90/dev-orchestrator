@extends('layouts.app', ['title' => $artifact.' - Task #'.$task->id, 'heading' => 'Artifact viewer'])

@section('content')
    <a class="back-link" href="{{ route('tasks.show', $task) }}">&larr; Return to task review</a>

    <section class="panel">
        <div class="panel-header"><div><p class="eyebrow">Task #{{ $task->id }} / {{ $task->project->name }}</p><h2>{{ $task->title }}</h2><p class="panel-copy">Read-only artifact evidence. Content is escaped and served only from this task's allowed local files.</p></div></div>
        <div class="artifact-meta">
            <div class="meta-card"><span>Artifact</span><strong class="mono">{{ $artifact }}</strong></div>
            <div class="meta-card"><span>Size</span><strong>{{ number_format($size) }} bytes</strong></div>
            <div class="meta-card"><span>Last modified</span><strong>{{ $lastModified }}</strong></div>
        </div>
    </section>

    <section class="panel artifact-viewer">
        <div class="viewer-toolbar"><span>READ-ONLY CONTENT / {{ $artifact }}</span><span class="viewer-lights"><i></i><i></i><i></i></span></div>
        <pre class="artifact-content">{{ $content }}</pre>
    </section>
@endsection
