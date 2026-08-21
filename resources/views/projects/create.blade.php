<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nuevo proyecto | command.flow</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #030711; color: #e8f1ff; }
        body { margin: 0; background: #030711; }
        a { color: inherit; }
        .shell { width: min(860px, 100%); margin: 0 auto; padding: 28px clamp(18px, 4vw, 52px) 60px; }
        .brand, .eyebrow { color: #9aeaf5; font: .72rem "Cascadia Code", Consolas, monospace; letter-spacing: .1em; text-transform: uppercase; }
        .brand { color: #f8fbff; font-weight: 800; text-decoration: none; text-transform: none; }
        h1 { margin: 70px 0 24px; color: #f8fbff; font-size: clamp(2.2rem, 6vw, 4.4rem); letter-spacing: -.075em; }
        form { display: grid; gap: 18px; padding: 24px; border: 1px solid rgba(34, 211, 238, .22); background: rgba(5, 12, 23, .72); }
        label { color: #dff7ff; font: 600 .75rem "Cascadia Code", Consolas, monospace; }
        input, textarea { display: block; width: 100%; margin-top: 8px; padding: 12px; color: #eef9ff; border: 1px solid rgba(122, 146, 173, .34); background: rgba(3, 11, 22, .78); }
        textarea { min-height: 180px; }
        .error { color: #ff9ca8; font-size: .75rem; }
        button { min-height: 43px; color: #d8fbff; border: 1px solid rgba(86, 234, 249, .58); background: linear-gradient(135deg, #0d6e83, #293a8b); font-weight: 750; }
    </style>
</head>
<body>
    <div class="shell">
        <header><a class="brand" href="{{ route('projects.index') }}">command.flow</a></header>
        <main>
            <p class="eyebrow">Project context</p>
            <h1>Nuevo proyecto</h1>
            <form method="post" action="{{ route('projects.store') }}">
                @csrf
                <label>Nombre del proyecto<input name="name" value="{{ old('name') }}" required autofocus></label>
                @error('name')<span class="error">{{ $message }}</span>@enderror
                <label>Ruta del repositorio<input name="repo_path" value="{{ old('repo_path') }}" required></label>
                @error('repo_path')<span class="error">{{ $message }}</span>@enderror
                <label>Contexto inicial del proyecto<textarea name="rules">{{ old('rules') }}</textarea></label>
                @error('rules')<span class="error">{{ $message }}</span>@enderror
                <button type="submit">Crear proyecto</button>
            </form>
        </main>
    </div>
</body>
</html>
