<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Proyectos | command.flow</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #030711; color: #e8f1ff; }
        body { margin: 0; background: #030711; }
        a { color: inherit; }
        .shell { width: min(980px, 100%); margin: 0 auto; padding: 28px clamp(18px, 4vw, 52px) 60px; }
        .brand, .eyebrow, .meta { color: #9aeaf5; font: .72rem "Cascadia Code", Consolas, monospace; letter-spacing: .1em; text-transform: uppercase; }
        .brand { color: #f8fbff; font-weight: 800; text-decoration: none; text-transform: none; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        h1 { margin: 70px 0 18px; color: #f8fbff; font-size: clamp(2.2rem, 6vw, 4.4rem); letter-spacing: -.075em; }
        .action { display: inline-flex; min-height: 40px; align-items: center; padding: 10px 13px; color: #d8fbff; border: 1px solid rgba(86, 234, 249, .48); background: rgba(8, 18, 32, .54); font: 750 .78rem Inter, sans-serif; text-decoration: none; }
        .grid { display: grid; gap: 14px; margin-top: 28px; }
        .card { padding: 18px; border: 1px solid rgba(34, 211, 238, .18); background: rgba(5, 12, 23, .72); text-decoration: none; }
        .card h2 { margin: 0 0 8px; font-size: 1.3rem; }
        .card p { margin: 4px 0; color: #9fb2c8; font: .82rem "Cascadia Code", Consolas, monospace; }
    </style>
</head>
<body>
    <div class="shell">
        <header><a class="brand" href="{{ route('home') }}">command.flow</a><a class="action" href="{{ route('projects.create') }}">Nuevo proyecto</a></header>
        <main>
            <p class="eyebrow">Project context</p>
            <h1>Proyectos</h1>
            <section class="grid" aria-label="Proyectos registrados">
                @forelse ($projects as $project)
                    <a class="card" href="{{ route('projects.show', $project) }}">
                        <h2>{{ $project->name }}</h2>
                        <p>{{ $project->repo_path }}</p>
                        <p>{{ $project->development_runs_count }} Development Runs</p>
                    </a>
                @empty
                    <p class="meta">Todavía no hay proyectos.</p>
                @endforelse
            </section>
        </main>
    </div>
</body>
</html>
