{{-- Shared styles for pages added in the Oct 2026 update. Uses only the
     layout's CSS variables, so it works without rebuilding Tailwind. --}}
@once
<style>
    .fm-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    .fm-grid-2 { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
    .fm-stack > * + * { margin-top: .75rem; }
    .fm-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .fm-label { display: block; font-size: .72rem; font-weight: 600; margin-bottom: .3rem; color: var(--text-secondary); }
    .fm-input, .fm-select, .fm-textarea { width: 100%; border-radius: .6rem; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-primary); padding: .55rem .75rem; font-size: .85rem; }
    .fm-textarea { min-height: 6rem; }
    .fm-input:focus, .fm-select:focus, .fm-textarea:focus { outline: 2px solid var(--accent); outline-offset: 0; }
    .fm-btn { display: inline-flex; align-items: center; gap: .4rem; border: 0; border-radius: .6rem; padding: .55rem 1rem; font-size: .82rem; font-weight: 600; cursor: pointer; color: #fff; background: var(--accent); text-decoration: none; }
    .fm-btn:hover { opacity: .92; }
    .fm-btn-sm { padding: .35rem .7rem; font-size: .75rem; }
    .fm-btn-ghost { background: transparent; color: var(--text-primary); border: 1px solid var(--border-color); }
    .fm-btn-success { background: #059669; }
    .fm-btn-danger { background: #dc2626; }
    .fm-btn-warn { background: #d97706; }
    .fm-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .fm-table th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); padding: .6rem .75rem; border-bottom: 1px solid var(--border-color); }
    .fm-table td { padding: .6rem .75rem; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: top; }
    .fm-table td.num, .fm-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .fm-table tr.fm-overdue td { background: rgba(239,68,68,0.06); }
    .fm-muted { color: var(--text-muted); font-size: .75rem; }
    .fm-kpi { border: 1px solid var(--border-color); border-radius: .9rem; padding: 1rem; background: var(--bg-card); }
    .fm-kpi .v { font-size: 1.35rem; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }
    .fm-kpi .l { font-size: .72rem; color: var(--text-muted); }
    .fm-panel { border: 1px solid var(--border-color); border-radius: .9rem; padding: 1rem; background: var(--bg-card); }
    .fm-actions details { position: relative; }
    .fm-actions summary { list-style: none; cursor: pointer; }
    .fm-actions summary::-webkit-details-marker { display: none; }
    .fm-pop { position: absolute; right: 0; z-index: 20; margin-top: .4rem; width: min(20rem, 85vw); padding: .9rem; border-radius: .8rem; border: 1px solid var(--border-color); background: var(--bg-card-solid); box-shadow: var(--shadow-lg); text-align: left; }
    .fm-sig { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: .72rem; color: var(--accent); }
    .fm-terms { white-space: pre-wrap; line-height: 1.6; font-size: .86rem; color: var(--text-primary); max-height: 26rem; overflow-y: auto; padding: 1rem; border-radius: .8rem; border: 1px solid var(--border-color); background: var(--bg-input); }
    .fm-check { display: flex; gap: .6rem; align-items: flex-start; font-size: .85rem; color: var(--text-primary); }
    .fm-check input { margin-top: .2rem; width: 1rem; height: 1rem; }
    .fm-err { color: var(--danger, #dc2626); font-size: .75rem; margin-top: .25rem; }
</style>
@endonce
