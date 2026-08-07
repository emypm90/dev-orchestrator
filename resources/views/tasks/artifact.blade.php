@extends('layouts.app', ['title' => $artifact.' - Task #'.$task->id, 'heading' => 'Task #'.$task->id.' artifact'])

@section('content')
    <p><a href="{{ route('tasks.show', $task) }}">Back to task detail</a></p>
    <section class="panel">
        <h2>{{ $task->title }}</h2>
        <dl class="detail">
            <dt>Artifact</dt><dd class="mono">{{ $artifact }}</dd>
            <dt>Size</dt><dd>{{ number_format($size) }} bytes</dd>
            <dt>Last modified</dt><dd>{{ $lastModified }}</dd>
        </dl>
    </section>

    <section class="panel">
        <h2>Read-only content</h2>
        <pre class="artifact-content">{{ $content }}</pre>
    </section>
@endsection
