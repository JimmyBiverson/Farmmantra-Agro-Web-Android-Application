{{-- Security process for destructive admin actions (client requirement):
     any form posting to an admin ".../delete" route or the business reset
     asks for the admin's password and a reason before it is submitted. --}}
@once
@push('scripts')
<div id="fm-secure-dlg" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,.55); align-items:center; justify-content:center; padding:1rem">
    <form id="fm-secure-form" style="width:min(26rem,100%); background:var(--bg-card-solid,#fff); color:var(--text-primary,#111); border-radius:1rem; padding:1.25rem; box-shadow:0 20px 50px rgba(0,0,0,.3)">
        <h3 style="font-weight:700; margin:0 0 .3rem"><i class="fas fa-shield-halved" style="color:#dc2626"></i> Confirm this action</h3>
        <p id="fm-secure-msg" style="font-size:.85rem; color:var(--text-muted,#666); margin:0 0 .8rem"></p>
        <label style="display:block; font-size:.75rem; font-weight:600; margin-bottom:.25rem">Your password</label>
        <input id="fm-secure-pass" type="password" autocomplete="current-password" required style="width:100%; padding:.55rem .7rem; border-radius:.6rem; border:1px solid var(--border-color,#ccc); background:var(--bg-input,#fff); color:inherit; margin-bottom:.7rem">
        <label style="display:block; font-size:.75rem; font-weight:600; margin-bottom:.25rem">Reason</label>
        <input id="fm-secure-reason" type="text" minlength="5" required placeholder="Why is this needed?" style="width:100%; padding:.55rem .7rem; border-radius:.6rem; border:1px solid var(--border-color,#ccc); background:var(--bg-input,#fff); color:inherit; margin-bottom:1rem">
        <div style="display:flex; gap:.5rem; justify-content:flex-end">
            <button type="button" id="fm-secure-cancel" style="padding:.5rem 1rem; border-radius:.6rem; border:1px solid var(--border-color,#ccc); background:transparent; color:inherit">Cancel</button>
            <button type="submit" style="padding:.5rem 1rem; border-radius:.6rem; border:0; background:#dc2626; color:#fff; font-weight:600">Confirm</button>
        </div>
    </form>
</div>
<script>
(function () {
    const dlg = document.getElementById('fm-secure-dlg');
    const pass = document.getElementById('fm-secure-pass');
    const reason = document.getElementById('fm-secure-reason');
    const msg = document.getElementById('fm-secure-msg');
    let target = null;
    const isSensitive = (form) => /\/admin\/[a-z-]+\/delete(-image)?$|\/admin\/business-reset$/.test(new URL(form.action, location.href).pathname);
    const deactivates = (form) => /\/admin\/(users|franchises|products|categories)\/delete$/.test(new URL(form.action, location.href).pathname);
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || form.id === 'fm-secure-form' || !isSensitive(form) || form.dataset.fmConfirmed === '1') return;
        e.preventDefault();
        e.stopImmediatePropagation();
        target = form;
        msg.textContent = deactivates(form)
            ? 'Records are never deleted: this will DEACTIVATE the record and keep its history. Enter your password and a reason.'
            : 'This permanently removes the item. Enter your password and a reason. The action is recorded in the audit log.';
        pass.value = ''; reason.value = '';
        dlg.style.display = 'flex';
        pass.focus();
    }, true);
    document.getElementById('fm-secure-cancel').addEventListener('click', () => { dlg.style.display = 'none'; target = null; });
    document.getElementById('fm-secure-form').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!target) return;
        for (const [name, value] of [['confirm_password', pass.value], ['action_reason', reason.value]]) {
            let input = target.querySelector('input[name="' + name + '"]');
            if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = name; target.appendChild(input); }
            input.value = value;
        }
        target.dataset.fmConfirmed = '1';
        target.removeAttribute('onsubmit');
        dlg.style.display = 'none';
        target.submit();
    });
})();
</script>
@endpush
@endonce
