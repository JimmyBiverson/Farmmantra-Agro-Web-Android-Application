import Chart from 'chart.js/auto';

export function initializeWorkspace() {
    const wrapTables = () => document.querySelectorAll('main table').forEach(table => {
        if (table.closest('.table-responsive,.overflow-x-auto,.fm-table-wrap')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        table.before(wrapper);
        wrapper.append(table);
    });
    let currentDialog = null;
    let returnFocus = null;
    let scheduled = false;
    function synchronizeDialogs() {
        scheduled = false;
        const dialogs = [...document.querySelectorAll('.modal-overlay')].filter(dialog => getComputedStyle(dialog).display !== 'none' && dialog.getClientRects().length);
        const next = dialogs.at(-1) || null;
        const open = Boolean(next);
        if (document.documentElement.classList.contains('fm-dialog-open') !== open) document.documentElement.classList.toggle('fm-dialog-open', open);
        document.querySelectorAll('.main-content,.sidebar').forEach(element => { element.inert = open; });
        if (next !== currentDialog) {
            if (next) {
                returnFocus = currentDialog ? returnFocus : document.activeElement;
                next.setAttribute('role', 'dialog');
                next.setAttribute('aria-modal', 'true');
                const heading = next.querySelector('h3');
                if (heading) next.setAttribute('aria-label', heading.textContent.trim());
                next.querySelectorAll('label:not([for])').forEach((label, index) => {
                    const control = label.parentElement.querySelector('input,select,textarea');
                    if (control) {
                        control.id ||= `fm-dialog-field-${index}`;
                        label.htmlFor = control.id;
                    }
                });
                next.querySelector('.modal-panel>div:first-child button')?.setAttribute('aria-label', 'Close dialog');
                (next.querySelector('input:not([type=hidden]),select,textarea') || next.querySelector('button'))?.focus({preventScroll:true});
            } else if (returnFocus?.isConnected) {
                returnFocus.focus({preventScroll:true});
            }
            currentDialog = next;
        }
        wrapTables();
    }
    document.addEventListener('keydown', event => {
        if (event.key !== 'Tab' || !currentDialog) return;
        const focusable = [...currentDialog.querySelectorAll('button,a[href],input,select,textarea,[tabindex="0"]')].filter(element => !element.disabled && element.getClientRects().length);
        if (!focusable.length) return;
        const first = focusable[0], last = focusable.at(-1);
        if (event.shiftKey && (document.activeElement === first || !currentDialog.contains(document.activeElement))) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    new MutationObserver(() => {
        if (!scheduled) { scheduled = true; requestAnimationFrame(synchronizeDialogs); }
    }).observe(document.body, {subtree:true,childList:true,attributes:true,attributeFilter:['style']});
    synchronizeDialogs();

    const dashboard = document.getElementById('fm-dashboard-content');
    if (!dashboard) return;
    const palette = ['#3e8054','#9fbc75','#c4a45e','#709c95','#ae7661','#647f64'];
    function renderCharts() {
        const script = dashboard.querySelector('[data-dashboard-charts]');
        const charts = script ? JSON.parse(script.textContent) : {};
        Object.entries(charts).forEach(([id, config]) => {
            const canvas = dashboard.querySelector(`#${id}`);
            if (!canvas) return;
            const line = config.type === 'line';
            new Chart(canvas, {
                type: config.type,
                data: {labels:config.labels,datasets:[{label:line?'Sales (UGX)':'Total',data:config.values,borderColor:line?'#4e8457':undefined,backgroundColor:line?'rgba(117,161,88,.1)':palette,borderWidth:line?2:0,fill:line,tension:.3,pointRadius:2,hoverOffset:4}]},
                options:{responsive:true,maintainAspectRatio:false,animation:{duration:250},...(line?{}:{cutout:'67%'}),plugins:{legend:{display:!line,position:'bottom',labels:{color:'#849187',boxWidth:9,padding:15}}},...(line?{scales:{x:{grid:{display:false},ticks:{maxTicksLimit:6,color:'#849187',font:{size:10}}},y:{beginAtZero:true,grid:{color:'rgba(128,150,128,.1)'},ticks:{color:'#849187',font:{size:10},callback:value=>new Intl.NumberFormat('en',{notation:'compact'}).format(value)}}}}:{})}
            });
        });
    }
    let busy = false;
    async function refreshDashboard() {
        if (busy || document.hidden || currentDialog || dashboard.contains(document.activeElement) && document.activeElement.matches('input,textarea,select')) return;
        busy = true;
        const status = document.getElementById('fm-live-status');
        try {
            const response = await fetch(dashboard.dataset.liveUrl, {headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(12000)});
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error('Refresh unavailable');
            const data = await response.json();
            dashboard.querySelectorAll('canvas').forEach(canvas => Chart.getChart(canvas)?.destroy());
            dashboard.innerHTML = data.html;
            renderCharts();
            wrapTables();
            status.dataset.state = 'online';
            document.getElementById('fm-live-label').textContent = `Updated ${new Date(data.updated_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',second:'2-digit'})} · from business records`;
        } catch {
            status.dataset.state = 'offline';
            document.getElementById('fm-live-label').textContent = 'Refresh unavailable · showing the last loaded records';
        } finally { busy = false; }
    }
    renderCharts();
    document.getElementById('fm-refresh-dashboard')?.addEventListener('click',refreshDashboard);
    window.addEventListener('farmmantra-notification',refreshDashboard);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshDashboard();});
    setInterval(refreshDashboard,20000);
}
