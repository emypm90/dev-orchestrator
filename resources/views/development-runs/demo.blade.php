<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Development Run | command.flow</title>
    <style>
        :root { color-scheme: dark; font-family: "Cascadia Code", "SFMono-Regular", Consolas, monospace; background: #030711; color: #e8f1ff; }
        * { box-sizing: border-box; }
        body { min-width: 320px; min-height: 100vh; margin: 0; overflow-x: hidden; background: radial-gradient(ellipse 65% 45% at 50% 42%, rgba(8, 93, 127, .2), transparent 70%), radial-gradient(ellipse 35% 28% at 84% 2%, rgba(100, 63, 196, .13), transparent 76%), #030711; }
        body::before { position: fixed; z-index: 0; inset: 0; background-image: linear-gradient(rgba(148, 163, 184, .04) 1px, transparent 1px), linear-gradient(90deg, rgba(148, 163, 184, .04) 1px, transparent 1px); background-size: 38px 38px; content: ""; mask-image: linear-gradient(to bottom, black, transparent 82%); pointer-events: none; }
        a { color: inherit; }
        .run { position: relative; z-index: 1; display: grid; min-height: 100vh; grid-template-rows: auto 1fr auto; width: min(1240px, 100%); margin: 0 auto; padding: 24px clamp(18px, 4vw, 52px) 28px; }
        .masthead, .run-meta, .stage-head, .stage-foot, .signals { display: flex; align-items: center; justify-content: space-between; gap: 18px; }
        .brand { color: #f8fbff; font-size: .86rem; font-weight: 800; letter-spacing: -.04em; text-decoration: none; }
        .brand b { color: #35d7ee; font-weight: inherit; }
        .run-meta { color: #71829c; font-size: .68rem; letter-spacing: .08em; text-transform: uppercase; }
        .run-meta span { display: inline-flex; align-items: center; gap: 8px; }
        .live-dot { width: 7px; height: 7px; background: #55efb0; border-radius: 50%; box-shadow: 0 0 13px #55efb0; animation: pulse 1.8s ease-in-out infinite; }
        main { display: grid; align-content: center; padding: clamp(50px, 12vh, 132px) 0 clamp(42px, 8vh, 88px); }
        .run-label { margin: 0 0 18px; color: #6d7d95; font-size: .67rem; letter-spacing: .13em; text-transform: uppercase; }
        .run-label strong { color: #9aeaf5; font-weight: 600; }
        h1 { max-width: 900px; margin: 0; color: #f8fbff; font-size: clamp(2rem, 6vw, 5rem); letter-spacing: -.075em; line-height: .98; }
        h1 span { color: #37d9ee; text-shadow: 0 0 28px rgba(55, 217, 238, .27); }
        .lead { max-width: 630px; margin: 23px 0 0; color: #a8b7ca; font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: clamp(.92rem, 1.7vw, 1.06rem); line-height: 1.6; }
        .flow { position: relative; display: grid; grid-template-columns: repeat(5, 1fr); gap: 0; margin: clamp(44px, 8vw, 78px) 0 31px; }
        .flow::before { position: absolute; top: 9px; right: 9%; left: 9%; height: 1px; background: linear-gradient(90deg, #54edb2 0%, #36d8ef 56%, #334257 56%); content: ""; }
        .step { position: relative; min-width: 0; color: #64748b; font-size: .68rem; line-height: 1.35; }
        .step:not(:last-child) { padding-right: 12px; }
        .node { display: block; width: 19px; height: 19px; margin: 0 0 13px; background: #07101d; border: 1px solid #40516a; border-radius: 50%; }
        .step.complete { color: #a0b6c7; }
        .step.complete .node { background: #55efb0; border-color: #55efb0; box-shadow: 0 0 15px rgba(85, 239, 176, .55); }
        .step.active { color: #d8f8fc; }
        .step.active .node { background: #38d9ef; border-color: #d9fdff; box-shadow: 0 0 0 6px rgba(56, 217, 239, .12), 0 0 26px rgba(56, 217, 239, .85); animation: active-node 2.2s ease-in-out infinite; }
        .step small { display: block; margin-top: 4px; color: #596b83; font-size: .59rem; }
        .active-stage { position: relative; overflow: hidden; padding: clamp(22px, 4vw, 37px); border: 1px solid rgba(91, 217, 240, .36); background: linear-gradient(120deg, rgba(8, 29, 46, .92), rgba(9, 20, 37, .86)); box-shadow: 0 24px 80px rgba(0, 0, 0, .42), inset 0 1px 0 rgba(192, 250, 255, .08); }
        .active-stage::after { position: absolute; top: -90px; right: -50px; width: 260px; height: 260px; border: 1px solid rgba(55, 217, 238, .17); border-radius: 50%; box-shadow: 0 0 0 35px rgba(55, 217, 238, .035), 0 0 70px rgba(55, 217, 238, .1); content: ""; pointer-events: none; }
        .stage-head { position: relative; z-index: 1; align-items: flex-start; }
        .stage-kicker { margin: 0 0 10px; color: #53e9f4; font-size: .67rem; letter-spacing: .12em; text-transform: uppercase; }
        h2 { margin: 0; color: #f7fbff; font-size: clamp(1.3rem, 3vw, 2.1rem); letter-spacing: -.055em; }
        .stage-state { flex: 0 0 auto; padding: 7px 9px; color: #68f5bb; border: 1px solid rgba(86, 239, 178, .35); background: rgba(28, 113, 84, .12); font-size: .64rem; letter-spacing: .09em; text-transform: uppercase; }
        .command { position: relative; z-index: 1; margin: 27px 0 29px; padding: 14px 0; color: #c4d3e4; border-top: 1px solid rgba(127, 156, 181, .16); border-bottom: 1px solid rgba(127, 156, 181, .16); font-size: clamp(.76rem, 1.8vw, .95rem); line-height: 1.7; }
        .prompt { color: #53e9f4; }
        .caret { display: inline-block; width: 8px; height: 1.05em; margin-left: 4px; vertical-align: -.18em; background: #69f6c0; animation: blink 1s steps(1) infinite; }
        .stage-foot { position: relative; z-index: 1; align-items: flex-end; }
        .stage-foot p { max-width: 510px; margin: 0; color: #8598af; font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: .83rem; line-height: 1.55; }
        .action { padding: 10px 13px; color: #c7f8fc; border: 1px solid rgba(55, 217, 238, .45); background: rgba(13, 73, 91, .25); font-size: .71rem; letter-spacing: .04em; text-decoration: none; transition: background .2s ease, box-shadow .2s ease, transform .2s ease; }
        .action:hover { background: rgba(20, 109, 133, .35); box-shadow: 0 0 24px rgba(55, 217, 238, .16); transform: translateY(-2px); }
        .signals { flex-wrap: wrap; justify-content: flex-start; gap: 9px; }
        .chip { padding: 7px 9px; color: #8fa2ba; border: 1px solid rgba(102, 125, 151, .27); background: rgba(12, 23, 39, .6); font-size: .64rem; }
        .chip b { color: #d3e2ed; font-weight: 600; }
        .chip.ok b { color: #6cf3bb; }
        .chip.active-chip b { color: #5ce8f5; }
        footer { color: #53647a; font-size: .62rem; letter-spacing: .05em; }
        @keyframes blink { 50% { opacity: 0; } }
        @keyframes pulse { 50% { box-shadow: 0 0 3px #55efb0; opacity: .55; } }
        @keyframes active-node { 50% { box-shadow: 0 0 0 9px rgba(56, 217, 239, .04), 0 0 15px rgba(56, 217, 239, .45); } }
        @media (max-width: 620px) { .run { padding-top: 18px; } .run-meta { font-size: .58rem; } .flow { margin-top: 39px; } .step { font-size: .56rem; } .step small { display: none; } .stage-head, .stage-foot { align-items: flex-start; flex-direction: column; } .stage-state { margin-top: 3px; } .action { width: 100%; text-align: center; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; } }
    </style>
</head>
<body>
    <div class="run">
        <header class="masthead">
            <a class="brand" href="{{ route('home') }}">command.flow</a>
            <div class="run-meta"><span><i class="live-dot"></i> ejecución local</span><span>demo / 001</span></div>
        </header>

        <main>
            <p class="run-label">Development Run <strong>/ worktree: task-42</strong></p>
            <h1>Un cambio a la vez,<br><span>con la señal intacta.</span></h1>
            <p class="lead">Una superficie enfocada para lo que importa: una etapa activa, evidencia clara y la próxima decisión humana.</p>

            <section class="flow" aria-label="Progreso del Development Run">
                <div class="step complete"><i class="node"></i>brief<small>cargado</small></div>
                <div class="step complete"><i class="node"></i>plan<small>aceptado</small></div>
                <div class="step complete"><i class="node"></i>build<small>completo</small></div>
                <div class="step active"><i class="node"></i>QA<small>validando</small></div>
                <div class="step"><i class="node"></i>revisión<small>en espera</small></div>
            </section>

            <section class="active-stage" aria-labelledby="active-stage-title">
                <div class="stage-head">
                    <div><p class="stage-kicker">etapa activa / 04</p><h2 id="active-stage-title">QA está validando</h2></div>
                    <span class="stage-state">en curso</span>
                </div>
                <p class="command"><span class="prompt">$</span> php artisan test --filter=DevelopmentRun <span class="caret" aria-hidden="true"></span></p>
                <div class="stage-foot">
                    <p>Las comprobaciones enfocadas corren sobre el worktree preparado. Esta vista no modifica el estado del repositorio.</p>
                    <a class="action" href="{{ route('tasks.index') }}">ver tareas técnicas</a>
                </div>
            </section>

            <div class="signals" aria-label="Run insights">
                <span class="chip ok"><b>03</b> comprobaciones superadas</span>
                <span class="chip active-chip"><b>01</b> comprobación en curso</span>
                <span class="chip"><b>02m 14s</b> transcurridos</span>
                <span class="chip"><b>sigue</b> revisión humana</span>
            </div>
        </main>

        <footer>Interfaz experimental de Development Run. Solo datos de ejemplo.</footer>
    </div>
</body>
</html>
