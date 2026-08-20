<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nuevo Development Run | command.flow</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #030711; color: #e8f1ff; }
        * { box-sizing: border-box; }
        body { min-width: 320px; min-height: 100vh; margin: 0; background: radial-gradient(ellipse 60% 45% at 46% 35%, rgba(8, 93, 127, .22), transparent 70%), radial-gradient(ellipse 34% 28% at 86% 3%, rgba(100, 63, 196, .13), transparent 75%), #030711; }
        body::before { position: fixed; inset: 0; background-image: linear-gradient(rgba(148, 163, 184, .035) 1px, transparent 1px), linear-gradient(90deg, rgba(148, 163, 184, .035) 1px, transparent 1px); background-size: 38px 38px; content: ""; mask-image: linear-gradient(to bottom, black, transparent 82%); pointer-events: none; }
        a { color: inherit; }
        .shell { position: relative; width: min(860px, 100%); margin: 0 auto; padding: 24px clamp(18px, 4vw, 52px) 50px; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 18px; }
        .brand { color: #f8fbff; font: 800 .9rem "Cascadia Code", Consolas, monospace; letter-spacing: -.05em; text-decoration: none; }
        .meta, .eyebrow { color: #9aeaf5; font: .67rem "Cascadia Code", Consolas, monospace; letter-spacing: .12em; text-transform: uppercase; }
        main { padding-top: clamp(66px, 14vh, 130px); }
        h1 { max-width: 700px; margin: 0; color: #f8fbff; font-size: clamp(2.25rem, 6vw, 4.6rem); letter-spacing: -.075em; line-height: .96; }
        h1 span { color: #37d9ee; text-shadow: 0 0 28px rgba(55, 217, 238, .24); }
        .lead { max-width: 620px; margin: 21px 0 36px; color: #a8b7ca; font-size: clamp(.96rem, 1.7vw, 1.08rem); line-height: 1.6; }
        form { padding: clamp(21px, 4vw, 36px); border: 1px solid rgba(34, 211, 238, .22); background: linear-gradient(120deg, rgba(5, 12, 23, .88), rgba(8, 18, 32, .62)); box-shadow: 0 24px 80px rgba(0, 0, 0, .35); }
        label { display: block; margin-bottom: 22px; color: #8ea3ba; font: 600 .72rem "Cascadia Code", Consolas, monospace; letter-spacing: .005em; }
        label span:first-child { display: inline-block; margin-bottom: 9px; color: #dff7ff; }
        input, textarea { display: block; width: 100%; margin-top: 9px; padding: 12px 13px; color: #eef9ff; border: 1px solid rgba(122, 146, 173, .34); background: rgba(3, 11, 22, .78); font: 500 .88rem "Cascadia Code", Consolas, monospace; outline: none; transition: border-color .16s ease, box-shadow .16s ease, background .16s ease; }
        input:focus, textarea:focus { border-color: rgba(86, 234, 249, .68); background: rgba(3, 11, 22, .92); box-shadow: 0 0 0 3px rgba(34, 211, 238, .08); }
        textarea { min-height: 180px; resize: vertical; line-height: 1.55; }
        .hint, .error { display: block; margin-top: 7px; font-size: .71rem; font-weight: 500; line-height: 1.45; }
        .hint { color: #71849b; }
        .error { color: #ff9ca8; }
        .optional { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        button { min-height: 43px; padding: 12px 15px; color: #d8fbff; border: 1px solid rgba(86, 234, 249, .58); background: linear-gradient(135deg, #0d6e83, #293a8b); box-shadow: 0 10px 28px rgba(21, 173, 201, .2); font: 750 .8rem Inter, ui-sans-serif, system-ui, sans-serif; letter-spacing: .02em; cursor: pointer; }
        button:hover { filter: brightness(1.12); }
        @media (max-width: 680px) { .optional { grid-template-columns: 1fr; gap: 0; } }
    </style>
</head>
<body>
    <div class="shell">
        <header><a class="brand" href="{{ route('home') }}">command.flow</a><span class="meta">nuevo / intake</span></header>
        <main>
            <p class="eyebrow">Development Run / etapa 01</p>
            <h1>Arranquemos por el<br><span>contexto.</span></h1>
            <p class="lead">Describí el cambio con tus palabras. Este primer registro no ejecuta agentes, pruebas ni acciones sobre Git.</p>
            <form method="post" action="{{ route('development-runs.store') }}">
                @csrf
                <label><span>Título del cambio</span><input name="title" value="{{ old('title') }}" required autofocus></label>
                @error('title')<span class="error">{{ $message }}</span>@enderror
                <label><span>Contexto inicial</span><textarea name="initial_context" required>{{ old('initial_context') }}</textarea><span class="hint">Qué necesitás resolver, por qué importa y cualquier restricción conocida.</span></label>
                @error('initial_context')<span class="error">{{ $message }}</span>@enderror
                <div class="optional">
                    <label><span>Repositorio (opcional)</span><input name="repository" value="{{ old('repository') }}"></label>
                    <label><span>Proyecto (opcional)</span><input name="project" value="{{ old('project') }}"></label>
                </div>
                <label><span>Prioridad (opcional)</span><input name="priority" value="{{ old('priority') }}"></label>
                <button type="submit">Crear Development Run</button>
            </form>
        </main>
    </div>
</body>
</html>
