<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('doc-title') — {{ $company['name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; color: #111827; margin: 0; background: #f3f4f6; font-size: 13px; }
        .page { max-width: 820px; margin: 1.5rem auto; background: #fff; padding: 2rem 2.25rem; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .head { display: flex; justify-content: space-between; gap: 1rem; border-bottom: 2px solid #111827; padding-bottom: .9rem; margin-bottom: 1rem; }
        .head h1 { margin: 0; font-size: 1.15rem; }
        .head .co { text-align: right; font-size: .8rem; color: #374151; }
        h2 { font-size: 1rem; margin: 0 0 .25rem; text-transform: uppercase; letter-spacing: .04em; }
        .meta { color: #4b5563; margin: .15rem 0; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: .4rem 1.5rem; margin: .8rem 0 1rem; }
        .grid div span { display: block; font-size: .7rem; color: #6b7280; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin: .5rem 0 1rem; }
        th, td { border: 1px solid #d1d5db; padding: .4rem .5rem; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: .72rem; text-transform: uppercase; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        tfoot td { font-weight: 700; }
        .sig { border: 1px dashed #9ca3af; border-radius: 6px; padding: .6rem .8rem; margin: .4rem 0; }
        .sig code { font-size: .85rem; font-weight: 700; }
        .muted { color: #6b7280; font-size: .75rem; }
        .badge { display: inline-block; border: 1px solid #9ca3af; border-radius: 4px; padding: 0 .3rem; font-size: .7rem; }
        .badge-danger { border-color: #dc2626; color: #dc2626; }
        .toolbar { max-width: 820px; margin: 1rem auto 0; display: flex; gap: .5rem; justify-content: flex-end; }
        .toolbar button { border: 0; background: #4f46e5; color: #fff; padding: .55rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .foot { margin-top: 1.5rem; border-top: 1px solid #e5e7eb; padding-top: .5rem; display: flex; justify-content: space-between; color: #6b7280; font-size: .72rem; }
        @media print { body { background: #fff; } .page { box-shadow: none; margin: 0; max-width: none; padding: 0; } .toolbar { display: none; } }
        @media (max-width: 600px) { .page { padding: 1rem; } .grid { grid-template-columns: 1fr; } .head { flex-direction: column; } .head .co { text-align: left; } table { font-size: 11px; } }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
<div class="page">
    <div class="head">
        <div><h1>{{ $company['name'] }}</h1><div class="muted">{{ $company['address'] }}</div></div>
        <div class="co">{{ $company['phone'] }}<br>{{ $company['email'] }}</div>
    </div>
    <h2>@yield('doc-title')</h2>
    @yield('doc-body')
    <div class="foot"><span>Printed by {{ $printedBy }}</span><span>{{ now()->format('d M Y H:i') }}</span></div>
</div>
</body>
</html>
