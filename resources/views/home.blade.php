<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>command.flow | Orquestador local</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #030711; color: #e8f1ff; }
        * { box-sizing: border-box; }
        body { min-width: 320px; min-height: 100vh; margin: 0; background: radial-gradient(ellipse 63% 47% at 48% 43%, rgba(8, 93, 127, .21), transparent 70%), radial-gradient(ellipse 37% 30% at 87% 4%, rgba(100, 63, 196, .14), transparent 76%), #030711; }
        body::before { position: fixed; inset: 0; background-image: linear-gradient(rgba(148, 163, 184, .04) 1px, transparent 1px), linear-gradient(90deg, rgba(148, 163, 184, .04) 1px, transparent 1px); background-size: 38px 38px; content: ""; mask-image: linear-gradient(to bottom, black, transparent 82%); pointer-events: none; }
        a { color: inherit; }
        .shell { position: relative; display: grid; grid-template-rows: auto 1fr auto; min-height: 100vh; width: min(1180px, 100%); margin: 0 auto; padding: 24px clamp(18px, 4vw, 52px) 28px; }
        header, .meta, .actions, .nav, .signal { display: flex; align-items: center; }
        header { justify-content: space-between; gap: 18px; }
        .brand { color: #f8fbff; font: 800 .9rem "Cascadia Code", Consolas, monospace; letter-spacing: -.05em; text-decoration: none; }
        .meta { gap: 8px; color: #71829c; font: .68rem "Cascadia Code", Consolas, monospace; letter-spacing: .08em; text-transform: uppercase; }
        .dot { width: 7px; height: 7px; background: #55efb0; border-radius: 50%; box-shadow: 0 0 13px #55efb0; }
        main { display: grid; align-content: center; padding: clamp(76px, 16vh, 164px) 0 clamp(54px, 10vh, 110px); }
        .eyebrow { margin: 0 0 18px; color: #9aeaf5; font: .68rem "Cascadia Code", Consolas, monospace; letter-spacing: .13em; text-transform: uppercase; }
        h1 { max-width: 830px; margin: 0; color: #f8fbff; font-size: clamp(2.35rem, 7vw, 5.8rem); letter-spacing: -.08em; line-height: .94; }
        h1 span { color: #37d9ee; text-shadow: 0 0 28px rgba(55, 217, 238, .27); }
        .lead { max-width: 620px; margin: 24px 0 0; color: #a8b7ca; font-size: clamp(.96rem, 1.7vw, 1.08rem); line-height: 1.6; }
        .actions { flex-wrap: wrap; gap: 12px; margin-top: 34px; }
        .primary, .secondary { display: inline-flex; align-items: center; min-height: 43px; padding: 11px 14px; font-size: .8rem; font-weight: 750; letter-spacing: .02em; text-decoration: none; }
        .primary { color: #d8fbff; background: linear-gradient(135deg, #0d6e83, #293a8b); border: 1px solid rgba(86, 234, 249, .58); box-shadow: 0 10px 28px rgba(21, 173, 201, .2); }
        .secondary { color: #aebed0; border: 1px solid rgba(122, 146, 173, .34); background: rgba(10, 21, 37, .54); }
        .primary:hover { filter: brightness(1.12); } .secondary:hover { border-color: rgba(86, 234, 249, .55); color: #d8fbff; }
        .signal { width: fit-content; max-width: 100%; gap: 12px; margin-top: clamp(52px, 9vw, 95px); padding: 12px 15px; color: #8fa2ba; border: 1px solid rgba(34, 211, 238, .18); background: linear-gradient(90deg, rgba(5, 12, 23, .88), rgba(8, 18, 32, .54)); font: 500 .74rem "Cascadia Code", Consolas, monospace; letter-spacing: -.015em; line-height: 1.55; }
        .signal strong { color: #dff7ff; font-weight: 650; } .signal b { color: #6cf3bb; }
        footer { display: flex; justify-content: space-between; gap: 18px; color: #53647a; font: .65rem "Cascadia Code", Consolas, monospace; letter-spacing: .04em; }
        .nav { flex-wrap: wrap; gap: 13px; } .nav a { color: #7890aa; text-decoration: none; } .nav a:hover { color: #5ce8f5; }
        @media (max-width: 620px) { .shell { padding-top: 18px; } .meta { font-size: .58rem; } .actions a { width: 100%; justify-content: center; } footer { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
    <div class="shell">
        <header>
            <a class="brand" href="{{ route('home') }}">command.flow</a>
            <div class="meta"><i class="dot"></i> local / listo</div>
        </header>
        <main>
            <p class="eyebrow">Orquestador de desarrollo local</p>
            <h1>¿Qué vamos a<br><span>resolver hoy?</span></h1>
            <p class="lead">Convertí una necesidad concreta en un Development Run enfocado. Una pregunta, una etapa activa y evidencia suficiente para decidir con seguridad.</p>
            <div class="actions">
                <a class="primary" href="{{ route('development-runs.create') }}">Iniciar Development Run</a>
                <a class="secondary" href="{{ route('tasks.index') }}">Ver tareas técnicas</a>
            </div>
            <div class="signal" aria-label="Estado actual"><span class="dot"></span><span>@if ($activeRun)<strong>Run activo: <a href="{{ route('development-runs.show', $activeRun) }}">{{ $activeRun->title }}</a>.</strong> Etapa actual: {{ $activeRun->active_stage }}. @else<strong>No hay Development Runs activos.</strong> Empezá uno para definir el próximo cambio.@endif</span></div>
        </main>
        <footer>
            <span>Command Flow registra el contexto inicial localmente.</span>
            <nav class="nav" aria-label="Navegación secundaria"><a href="{{ route('development-runs.create') }}">Nuevo run</a><a href="{{ route('development-runs.demo') }}">Demo</a><a href="{{ route('tasks.index') }}">Tareas</a><a href="{{ route('integrations.gmail.index') }}">Integraciones</a><a href="{{ route('settings.integrations.edit') }}">Configuración</a></nav>
        </footer>
    </div>
</body>
</html>
