<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dev Orchestrator' }}</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #d8e1ee; background: #0b1019; }
        * { box-sizing: border-box; }
        body { min-width: 320px; margin: 0; background: radial-gradient(circle at 8% -10%, rgba(59, 130, 246, .16), transparent 31rem), radial-gradient(circle at 96% 0%, rgba(139, 92, 246, .11), transparent 27rem), #0b1019; }
        body::before { position: fixed; z-index: -1; inset: 0; background-image: linear-gradient(rgba(148, 163, 184, .025) 1px, transparent 1px), linear-gradient(90deg, rgba(148, 163, 184, .025) 1px, transparent 1px); background-size: 32px 32px; content: ""; pointer-events: none; }
        a { color: #7dd3fc; text-underline-offset: 3px; }
        a:hover { color: #bae6fd; }
        code, .mono { font-family: "Cascadia Code", "SFMono-Regular", Consolas, monospace; overflow-wrap: anywhere; }
        .shell { width: min(1440px, 100%); margin: 0 auto; padding: 24px clamp(16px, 3vw, 36px) 52px; }
        .topline { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 15px 18px; margin-bottom: 28px; background: rgba(15, 23, 36, .82); border: 1px solid rgba(100, 116, 139, .32); border-radius: 14px; box-shadow: 0 16px 50px rgba(0, 0, 0, .18); backdrop-filter: blur(14px); }
        .brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .brand-mark { display: grid; width: 34px; height: 34px; color: #67e8f9; background: linear-gradient(135deg, #0e7490, #312e81); border: 1px solid rgba(103, 232, 249, .48); border-radius: 10px; box-shadow: 0 0 22px rgba(34, 211, 238, .2); place-items: center; font-family: "Cascadia Code", monospace; font-size: .9rem; font-weight: 800; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 0; font-size: clamp(1.1rem, 2vw, 1.35rem); letter-spacing: -.025em; }
        h1 a { color: #f1f5f9; text-decoration: none; }
        h2 { margin-bottom: 0; color: #eef6ff; font-size: 1.04rem; letter-spacing: -.01em; }
        h3 { margin-bottom: 0; color: #e2e8f0; font-size: .88rem; }
        .eyebrow { margin-bottom: 3px; color: #7dd3fc; font-size: .67rem; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
        .safety-notice { display: flex; align-items: center; gap: 8px; max-width: 430px; margin: 0; padding: 8px 10px; color: #b6c5d7; background: rgba(30, 41, 59, .64); border: 1px solid rgba(100, 116, 139, .35); border-radius: 8px; font-size: .76rem; line-height: 1.35; }
        .safety-notice strong { color: #e0f2fe; }
        .safety-icon { color: #67e8f9; font-size: .95rem; }
        .flash, .errors { display: flex; gap: 10px; margin: 0 0 18px; padding: 12px 14px; border: 1px solid; border-radius: 10px; font-size: .88rem; line-height: 1.45; }
        .flash { color: #b8f3d1; background: rgba(6, 78, 59, .28); border-color: rgba(52, 211, 153, .45); }
        .errors { color: #fecaca; background: rgba(127, 29, 29, .24); border-color: rgba(248, 113, 113, .46); }
        .errors div + div { margin-top: 3px; }
        .panel { margin-bottom: 18px; padding: clamp(16px, 2vw, 22px); background: linear-gradient(145deg, rgba(23, 32, 48, .94), rgba(14, 21, 32, .94)); border: 1px solid rgba(100, 116, 139, .28); border-radius: 13px; box-shadow: 0 12px 32px rgba(0, 0, 0, .14); }
        .panel-header { display: flex; align-items: start; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        .panel-copy { margin: 5px 0 0; color: #92a3b9; font-size: .84rem; line-height: 1.45; }
        .muted { color: #8393a9; }
        .section-kicker { color: #94a3b8; font-size: .72rem; font-weight: 750; letter-spacing: .08em; text-transform: uppercase; }
        .command-hero { position: relative; overflow: hidden; padding: clamp(22px, 4vw, 34px); background: linear-gradient(115deg, rgba(12, 38, 58, .97), rgba(25, 26, 65, .95) 56%, rgba(17, 24, 39, .96)); border-color: rgba(103, 232, 249, .3); }
        .command-hero::after { position: absolute; top: -70px; right: 7%; width: 210px; height: 210px; border: 1px solid rgba(103, 232, 249, .2); border-radius: 50%; box-shadow: 0 0 0 34px rgba(139, 92, 246, .045), 0 0 80px rgba(34, 211, 238, .11); content: ""; }
        .command-hero h2 { position: relative; max-width: 600px; margin-bottom: 7px; font-size: clamp(1.35rem, 3vw, 2rem); }
        .command-hero p { position: relative; max-width: 660px; margin-bottom: 0; color: #b8cbe0; font-size: .95rem; line-height: 1.5; }
        .hero-signal { position: relative; display: inline-flex; align-items: center; gap: 7px; margin-bottom: 12px; color: #a5f3fc; font-family: "Cascadia Code", monospace; font-size: .76rem; }
        .signal-dot { width: 8px; height: 8px; background: #22d3ee; border-radius: 50%; box-shadow: 0 0 12px #22d3ee; }
        .dashboard-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(290px, .85fr); gap: 18px; }
        .counts { display: grid; grid-template-columns: repeat(auto-fit, minmax(115px, 1fr)); gap: 10px; }
        .count { min-width: 0; padding: 12px; background: rgba(15, 23, 42, .72); border: 1px solid rgba(100, 116, 139, .27); border-radius: 9px; }
        .count strong, .count span { display: block; }
        .count strong { margin-bottom: 3px; color: #f8fafc; font-size: 1.42rem; line-height: 1; }
        .count span { color: #9eafc4; font-size: .76rem; line-height: 1.25; text-transform: capitalize; }
        .attention-count { border-left: 3px solid #f59e0b; }
        .attention-count:nth-child(2), .attention-count:nth-child(3), .attention-count:nth-child(6) { border-left-color: #fb7185; }
        .attention-count:nth-child(4) { border-left-color: #c084fc; }
        .filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; }
        label { display: grid; gap: 5px; color: #a9b8ca; font-size: .76rem; font-weight: 720; letter-spacing: .02em; }
        input, select, textarea, button { min-height: 38px; border: 1px solid rgba(100, 116, 139, .54); border-radius: 7px; background: #0d1624; color: #e2e8f0; padding: 7px 10px; font: inherit; }
        select { min-width: 150px; }
        input:focus, select:focus, textarea:focus { outline: 2px solid rgba(34, 211, 238, .58); outline-offset: 1px; border-color: #22d3ee; }
        .checkbox-label { display: flex; align-items: center; gap: 7px; min-height: 38px; padding: 0 4px; cursor: pointer; }
        .checkbox-label input { min-height: auto; accent-color: #22d3ee; }
        button { color: #e0f7ff; background: linear-gradient(135deg, #0e7490, #3730a3); border-color: rgba(103, 232, 249, .45); box-shadow: 0 5px 16px rgba(8, 145, 178, .17); cursor: pointer; font-weight: 760; }
        button:hover { filter: brightness(1.12); }
        .filter-link { align-self: center; font-size: .84rem; }
        .top-nav { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 6px; font-size: .77rem; }
        .top-nav a { color: #a9c9de; text-decoration: none; }
        .top-nav a:hover { color: #67e8f9; text-decoration: underline; }
        .nav-badge { display: inline-grid; min-width: 18px; height: 18px; margin-left: 4px; padding: 0 5px; color: #fecdd3; background: rgba(159, 18, 57, .52); border: 1px solid rgba(251, 113, 133, .5); border-radius: 999px; place-items: center; font-size: .65rem; font-weight: 800; }
        .button-link { display: inline-flex; align-items: center; min-height: 38px; padding: 7px 10px; color: #e0f7ff; background: linear-gradient(135deg, #0e7490, #3730a3); border: 1px solid rgba(103, 232, 249, .45); border-radius: 7px; box-shadow: 0 5px 16px rgba(8, 145, 178, .17); font-size: .84rem; font-weight: 760; text-decoration: none; white-space: nowrap; }
        .ticket-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .ticket-form textarea { width: 100%; min-height: 110px; resize: vertical; }
        .wide-field { grid-column: 1 / -1; }
        .request-context { padding: 15px; color: #dbeafe; background: rgba(15, 23, 42, .62); border: 1px solid rgba(100, 116, 139, .28); border-radius: 10px; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
        .priority-low { color: #94a3b8; } .priority-normal { color: #67e8f9; } .priority-high { color: #fcd34d; background: rgba(146, 64, 14, .17); border-color: rgba(251, 191, 36, .32); } .priority-urgent, .status-needs_attention { color: #fda4af; background: rgba(159, 18, 57, .16); border-color: rgba(251, 113, 133, .35); }
        .status-inbox, .status-triage, .status-ready_to_report { color: #67e8f9; background: rgba(8, 145, 178, .16); border-color: rgba(34, 211, 238, .34); } .status-ready, .status-testing, .status-reported { color: #fcd34d; background: rgba(146, 64, 14, .17); border-color: rgba(251, 191, 36, .32); } .status-implementing { color: #d8b4fe; background: rgba(107, 33, 168, .2); border-color: rgba(192, 132, 252, .34); } .status-hours_pending { color: #fb923c; } .status-done { color: #86efac; background: rgba(22, 101, 52, .2); border-color: rgba(74, 222, 128, .31); }
        .due-overdue { color: #fda4af; font-weight: 700; }
        .table-wrap { overflow-x: auto; border: 1px solid rgba(100, 116, 139, .24); border-radius: 10px; }
        table { width: 100%; min-width: 1010px; border-collapse: collapse; font-size: .84rem; }
        th, td { padding: 13px 11px; text-align: left; vertical-align: middle; border-bottom: 1px solid rgba(100, 116, 139, .2); }
        tr:last-child td { border-bottom: 0; }
        tbody tr { background: rgba(15, 23, 42, .24); }
        tbody tr:hover { background: rgba(30, 41, 59, .58); }
        th { color: #8ea0b7; background: rgba(8, 15, 27, .68); font-size: .67rem; letter-spacing: .08em; text-transform: uppercase; white-space: nowrap; }
        td.title { min-width: 205px; font-weight: 700; }
        .task-link { color: #d9f5ff; text-decoration: none; }
        .task-link:hover { color: #67e8f9; text-decoration: underline; }
        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; color: #cbd5e1; background: rgba(71, 85, 105, .28); border: 1px solid rgba(148, 163, 184, .28); border-radius: 999px; font-size: .71rem; font-weight: 760; line-height: 1; white-space: nowrap; text-transform: capitalize; }
        .badge::before { width: 6px; height: 6px; background: currentColor; border-radius: 50%; content: ""; }
        .status-completed, .status-approved, .state-passed { color: #86efac; background: rgba(22, 101, 52, .2); border-color: rgba(74, 222, 128, .31); }
        .status-running { color: #67e8f9; background: rgba(8, 145, 178, .16); border-color: rgba(34, 211, 238, .34); }
        .status-blocked, .status-failed, .state-failed, .status-rejected { color: #fda4af; background: rgba(159, 18, 57, .16); border-color: rgba(251, 113, 133, .35); }
        .status-needs_revision, .state-needs_revision { color: #d8b4fe; background: rgba(107, 33, 168, .2); border-color: rgba(192, 132, 252, .34); }
        .status-prepared, .status-draft, .state-skipped { color: #fcd34d; background: rgba(146, 64, 14, .17); border-color: rgba(251, 191, 36, .32); }
        .status-archived { color: #94a3b8; }
        .state-empty { color: #94a3b8; }
        .next-action { max-width: 240px; color: #d5e5f7; font-weight: 650; line-height: 1.38; }
        .back-link { display: inline-flex; gap: 6px; margin: 0 0 14px; font-size: .85rem; text-decoration: none; }
        .task-heading { margin-bottom: 2px; font-size: clamp(1.28rem, 2.8vw, 1.8rem); }
        .state-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 15px; }
        .decision-summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .summary-card { min-width: 0; padding: 15px; background: rgba(15, 23, 42, .62); border: 1px solid rgba(100, 116, 139, .28); border-radius: 10px; }
        .summary-card h3 { margin: 7px 0 6px; font-size: .98rem; }
        .summary-card p { margin: 7px 0 0; color: #bdcad9; font-size: .84rem; line-height: 1.5; overflow-wrap: anywhere; }
        .summary-objective { border-color: rgba(103, 232, 249, .35); }
        .summary-attention { border-color: rgba(251, 191, 36, .38); }
        .attention-reasons p + p { margin-top: 9px; }
        .attention-reasons blockquote { margin: 8px 0 0; padding: 9px 11px; color: #f3e8ff; background: rgba(107, 33, 168, .18); border-left: 3px solid #c084fc; border-radius: 0 6px 6px 0; font-size: .84rem; line-height: 1.45; overflow-wrap: anywhere; }
        .summary-next-action { background: linear-gradient(105deg, rgba(8, 91, 111, .28), rgba(49, 46, 129, .23)); border-color: rgba(103, 232, 249, .35); }
        .summary-next-action strong { display: block; margin-top: 7px; color: #f0f9ff; font-size: 1rem; line-height: 1.4; }
        .summary-evidence ol { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 20px; margin: 9px 0 0; padding-left: 20px; color: #c9d7e7; font-size: .81rem; line-height: 1.45; }
        .technical-details { opacity: .82; }
        .detail { display: grid; grid-template-columns: minmax(150px, .7fr) 2fr; margin: 0; }
        .detail dt, .detail dd { padding: 10px 0; margin: 0; border-bottom: 1px solid rgba(100, 116, 139, .19); }
        .detail dt { color: #91a2b8; font-size: .78rem; font-weight: 720; }
        .detail dd { color: #d7e1ed; overflow-wrap: anywhere; }
        .detail dt:last-of-type, .detail dd:last-child { border-bottom: 0; }
        .artifact-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(205px, 1fr)); gap: 10px; margin: 0; padding: 0; list-style: none; }
        .artifact-link, .artifact-unavailable { display: flex; align-items: center; gap: 9px; min-height: 48px; padding: 10px; border: 1px solid rgba(100, 116, 139, .27); border-radius: 8px; font-size: .8rem; text-decoration: none; }
        .artifact-link { color: #bae6fd; background: rgba(8, 47, 73, .26); border-color: rgba(34, 211, 238, .29); }
        .artifact-link:hover { background: rgba(8, 91, 111, .32); }
        .artifact-unavailable { color: #718198; background: rgba(15, 23, 42, .4); }
        .artifact-icon { color: #67e8f9; font-size: 1rem; }
        .review-safety { margin-bottom: 14px; }
        .review-actions { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .review-actions form { display: grid; gap: 10px; align-content: start; padding: 15px; background: rgba(15, 23, 42, .62); border: 1px solid rgba(100, 116, 139, .3); border-radius: 10px; }
        .review-actions p { margin: 0; color: #8fa0b5; font-size: .78rem; line-height: 1.4; }
        .review-actions textarea { width: 100%; min-height: 94px; resize: vertical; }
        .review-actions .revision-panel { border-color: rgba(192, 132, 252, .34); }
        .review-actions .reject-panel { border-color: rgba(251, 113, 133, .34); }
        .review-actions .reject-button { background: linear-gradient(135deg, #9f1239, #881337); border-color: rgba(251, 113, 133, .45); }
        .review-actions .revision-button { background: linear-gradient(135deg, #6b21a8, #4c1d95); border-color: rgba(216, 180, 254, .45); }
        .expectations { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .expectations section { padding: 14px; background: rgba(15, 23, 42, .57); border: 1px solid rgba(100, 116, 139, .24); border-radius: 9px; }
        .expectations h3 { margin-bottom: 9px; }
        .expectations ul { margin: 0; padding-left: 18px; color: #bac7d8; font-size: .82rem; line-height: 1.5; }
        .expectations li + li { margin-top: 5px; }
        .artifact-meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .meta-card { padding: 12px; background: rgba(15, 23, 42, .62); border: 1px solid rgba(100, 116, 139, .25); border-radius: 8px; }
        .meta-card span, .meta-card strong { display: block; }
        .meta-card span { margin-bottom: 4px; color: #8ea0b7; font-size: .7rem; font-weight: 730; letter-spacing: .06em; text-transform: uppercase; }
        .meta-card strong { color: #dbeafe; font-size: .82rem; font-weight: 620; overflow-wrap: anywhere; }
        .artifact-viewer { padding: 0; overflow: hidden; border-color: rgba(103, 232, 249, .28); }
        .viewer-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 15px; color: #9fb3c8; background: rgba(8, 15, 27, .78); border-bottom: 1px solid rgba(100, 116, 139, .25); font: .76rem "Cascadia Code", monospace; }
        .viewer-lights { display: flex; gap: 6px; }
        .viewer-lights i { display: block; width: 8px; height: 8px; background: #475569; border-radius: 50%; }
        .viewer-lights i:first-child { background: #fb7185; } .viewer-lights i:nth-child(2) { background: #fbbf24; } .viewer-lights i:last-child { background: #34d399; }
        .artifact-content { max-height: min(70vh, 880px); margin: 0; padding: clamp(16px, 3vw, 26px); overflow: auto; color: #dbeafe; background: #09111e; font: .84rem/1.7 "Cascadia Code", "SFMono-Regular", Consolas, monospace; tab-size: 4; white-space: pre-wrap; overflow-wrap: anywhere; }
        .diff-link { margin: 0 0 14px; }
        .diff-warning { margin: 14px 0 0; padding: 10px 12px; color: #fde68a; background: rgba(146, 64, 14, .18); border: 1px solid rgba(251, 191, 36, .32); border-radius: 8px; font-size: .84rem; }
        .diff-file { padding: 0; overflow: hidden; border-color: rgba(103, 232, 249, .28); }
        @media (max-width: 800px) { .topline { align-items: start; flex-direction: column; } .safety-notice { max-width: none; } .dashboard-grid, .decision-summary, .review-actions, .ticket-form { grid-template-columns: 1fr; } .artifact-meta { grid-template-columns: 1fr; } }
        @media (max-width: 600px) { .shell { padding-top: 14px; } .topline { margin-bottom: 18px; } .detail { grid-template-columns: 1fr; } .detail dt { padding-bottom: 2px; border-bottom: 0; } .detail dd { padding-top: 2px; } .expectations, .summary-evidence ol { grid-template-columns: 1fr; } .panel-header { display: block; } .panel-header > * + * { margin-top: 8px; } }
    </style>
</head>
<body>
    <main class="shell">
        <header class="topline">
            <div class="brand">
                <div class="brand-mark">&gt;_</div>
                <div>
                    <p class="eyebrow">Orquestador de desarrollo local</p>
                    <h1><a href="{{ route('tasks.index') }}">{{ $heading ?? 'Panel de tareas' }}</a></h1>
                    <nav class="top-nav" aria-label="Navegación principal"><a href="{{ route('tasks.index', ['attention' => 1]) }}">Tareas de ejecución <span class="nav-badge">{{ $layoutAttentionSummary['executionTasks']['count'] }}</span></a><a href="{{ route('operational-tickets.index', ['attention' => 1]) }}">Tickets operativos <span class="nav-badge">{{ $layoutAttentionSummary['operationalTickets']['count'] }}</span></a></nav>
                </div>
            </div>
            <p class="safety-notice"><span class="safety-icon">&#9672;</span><span><strong>MVP local.</strong> Las decisiones de revisión se registran localmente y nunca modifican el estado de Git.</span></p>
        </header>
        @if (session('success'))
            <div class="flash"><strong>Actualización registrada</strong><span>{{ session('success') }}</span></div>
        @endif
        @if ($errors->any())
            <div class="errors">
                <strong>No se pudo completar la acción.</strong>
                <div>@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
