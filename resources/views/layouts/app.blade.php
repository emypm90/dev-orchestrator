<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dev Orchestrator' }}</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #172033; background: #f5f7fb; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        a { color: #1457c0; }
        code, .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; overflow-wrap: anywhere; }
        .shell { max-width: 1240px; margin: 0 auto; padding: 28px 20px 48px; }
        .topline { display: flex; justify-content: space-between; align-items: start; gap: 20px; margin-bottom: 24px; }
        h1 { margin: 0; font-size: clamp(1.6rem, 4vw, 2.25rem); letter-spacing: -.04em; }
        h2 { margin: 0 0 12px; font-size: 1.05rem; }
        .eyebrow { color: #526078; font-size: .78rem; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; margin: 0 0 5px; }
        .notice { margin: 0; padding: 10px 13px; color: #654600; background: #fff6d9; border: 1px solid #f0d88c; border-radius: 8px; font-size: .9rem; }
        .success, .errors { margin: 0 0 18px; padding: 10px 13px; border-radius: 8px; font-size: .9rem; }
        .success { color: #14532d; background: #edf9f0; border: 1px solid #9cddad; }
        .errors { color: #8a1c1c; background: #fff0f0; border: 1px solid #efb1b1; }
        .panel { background: #fff; border: 1px solid #dce2ed; border-radius: 10px; padding: 18px; margin-bottom: 18px; box-shadow: 0 1px 2px rgba(24, 39, 75, .04); }
        .counts { display: flex; flex-wrap: wrap; gap: 10px; }
        .count { min-width: 104px; padding: 10px 12px; background: #f4f7fc; border-radius: 7px; }
        .count strong, .count span { display: block; }
        .count strong { font-size: 1.3rem; }
        .count span { color: #58657b; font-size: .82rem; }
        .filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; }
        label { display: grid; gap: 4px; color: #526078; font-size: .82rem; font-weight: 650; }
        input, select, textarea, button { min-height: 34px; border: 1px solid #bbc6d8; border-radius: 6px; background: white; color: #172033; padding: 5px 8px; font: inherit; }
        button { background: #172e5b; border-color: #172e5b; color: #fff; cursor: pointer; font-weight: 650; }
        .filter-link { font-size: .9rem; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { padding: 11px 9px; text-align: left; vertical-align: top; border-bottom: 1px solid #e5e9f1; }
        th { color: #526078; font-size: .75rem; letter-spacing: .04em; text-transform: uppercase; white-space: nowrap; }
        td.title { min-width: 190px; font-weight: 650; }
        .muted { color: #65718a; }
        .badge { display: inline-block; padding: 2px 7px; border-radius: 99px; background: #eaf0fc; color: #294a80; font-size: .78rem; font-weight: 700; white-space: nowrap; }
        .detail { display: grid; grid-template-columns: minmax(130px, .7fr) 2fr; gap: 0; margin: 0; }
        .detail dt, .detail dd { border-bottom: 1px solid #e5e9f1; padding: 10px 0; margin: 0; }
        .detail dt { color: #526078; font-weight: 650; }
        .detail dd { overflow-wrap: anywhere; }
        .expectations { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .expectations section { background: #f7f9fd; border-radius: 7px; padding: 12px; }
        .expectations h3 { font-size: .9rem; margin: 0 0 8px; }
        .expectations ul { margin: 0; padding-left: 18px; font-size: .88rem; }
        .expectations li + li { margin-top: 5px; }
        .artifacts { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 8px 18px; margin: 0; padding-left: 18px; font-size: .9rem; }
        .review-actions { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .review-actions form { display: grid; gap: 9px; align-content: start; padding: 12px; background: #f7f9fd; border-radius: 7px; }
        .review-actions h3 { margin: 0; font-size: .95rem; }
        .review-actions textarea { width: 100%; min-height: 92px; resize: vertical; }
        .artifact-content { margin: 0; padding: 14px; overflow: auto; color: #dce7ff; background: #14213d; border-radius: 7px; font: .84rem/1.55 ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre-wrap; overflow-wrap: anywhere; }
        @media (max-width: 700px) { .topline { display: block; } .notice { margin-top: 14px; } .detail { grid-template-columns: 1fr; } .detail dt { border-bottom: 0; padding-bottom: 2px; } .detail dd { padding-top: 2px; } .expectations, .review-actions { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <main class="shell">
        <header class="topline">
            <div>
                <p class="eyebrow">Local development orchestrator</p>
                <h1><a href="{{ route('tasks.index') }}" style="color: inherit; text-decoration: none;">{{ $heading ?? 'Task dashboard' }}</a></h1>
            </div>
            <p class="notice">Local-only review decisions. These actions do not change Git state.</p>
        </header>
        @if (session('success'))
            <p class="success">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
