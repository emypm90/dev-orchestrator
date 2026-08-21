<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $project->name }} | command.flow</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #030711; color: #e8f1ff; }
        body { margin: 0; background: #030711; }
        a { color: inherit; }
        .shell { width: min(980px, 100%); margin: 0 auto; padding: 28px clamp(18px, 4vw, 52px) 60px; }
        .brand, .eyebrow, .meta { color: #9aeaf5; font: .72rem "Cascadia Code", Consolas, monospace; letter-spacing: .1em; text-transform: uppercase; }
        .brand { color: #f8fbff; font-weight: 800; text-decoration: none; text-transform: none; }
        h1 { margin: 70px 0 14px; color: #f8fbff; font-size: clamp(2.2rem, 6vw, 4.4rem); letter-spacing: -.075em; }
        .lead, pre { color: #a8b7ca; line-height: 1.65; white-space: pre-wrap; }
        .panel { margin-top: 24px; padding: 18px; border: 1px solid rgba(34, 211, 238, .18); background: rgba(5, 12, 23, .72); }
        .action { display: inline-flex; min-height: 40px; align-items: center; margin-top: 18px; padding: 10px 13px; color: #d8fbff; border: 1px solid rgba(86, 234, 249, .48); background: rgba(8, 18, 32, .54); font: 750 .78rem Inter, sans-serif; text-decoration: none; }
        input { display: block; width: 100%; margin-top: 9px; padding: 10px; color: #eef9ff; border: 1px solid rgba(122, 146, 173, .34); background: rgba(3, 11, 22, .78); }
        button { min-height: 40px; margin-top: 12px; padding: 10px 13px; color: #d8fbff; border: 1px solid rgba(86, 234, 249, .48); background: rgba(8, 18, 32, .54); font-weight: 750; cursor: pointer; }
        .status { color: #75efbd; font: .72rem "Cascadia Code", Consolas, monospace; text-transform: uppercase; }
        .error { color: #ff9ca8; }
    </style>
</head>
<body>
    <div class="shell">
        <header><a class="brand" href="{{ route('projects.index') }}">command.flow</a><span class="meta">project / {{ str_pad((string) $project->id, 3, '0', STR_PAD_LEFT) }}</span></header>
        <main>
            <p class="eyebrow">Project context</p>
            <h1>{{ $project->name }}</h1>
            <p class="lead">Repositorio: {{ $project->repo_path }}</p>
            <a class="action" href="{{ route('projects.edit', $project) }}">Editar proyecto</a>
            <a class="action" href="{{ route('projects.development-runs.create', $project) }}">Crear Development Run</a>
            <section class="panel" aria-label="Contexto reutilizable del proyecto">
                <p class="eyebrow">Contexto reutilizable</p>
                <pre>{{ $project->rules ?: 'Sin contexto inicial registrado.' }}</pre>
            </section>
            <section class="panel" aria-label="Adjuntos de contexto del proyecto">
                <p class="eyebrow">Adjuntos privados</p>
                <form method="post" action="{{ route('projects.context-attachments.store', $project) }}" enctype="multipart/form-data">
                    @csrf
                    <label>Subir TXT, Markdown, CSV, XLSX, PPTX, audio o video<input name="context_attachment" type="file" accept=".txt,.md,.markdown,.csv,.xlsx,.pptx,.mp3,.wav,.m4a,.mp4,.mov,.webm,text/plain,text/markdown,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.openxmlformats-officedocument.presentationml.presentation,audio/mpeg,audio/wav,audio/mp4,video/mp4,video/quicktime,video/webm" required></label>
                    @error('context_attachment')<p class="error">{{ $message }}</p>@enderror
                    <button type="submit">Adjuntar contexto</button>
                </form>
                @forelse ($project->contextAttachments as $attachment)
                    <p><span class="status">{{ $attachment->status }}</span> · {{ $attachment->original_name }}{{ $attachment->status_reason ? ' · '.$attachment->status_reason : '' }}</p>
                @empty
                    <p class="lead">Todavía no hay adjuntos privados para este proyecto.</p>
                @endforelse
            </section>
            <section class="panel" aria-label="Development Runs del proyecto">
                <p class="eyebrow">Development Runs</p>
                @forelse ($project->developmentRuns as $run)
                    <p><a href="{{ route('development-runs.show', $run) }}">{{ $run->title }}</a></p>
                @empty
                    <p class="lead">Todavía no hay runs para este proyecto.</p>
                @endforelse
            </section>
        </main>
    </div>
</body>
</html>
