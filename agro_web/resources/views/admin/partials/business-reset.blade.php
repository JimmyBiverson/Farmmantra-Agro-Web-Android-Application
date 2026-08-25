<div class="card-full mb-6" style="border:1px solid rgba(239,68,68,.35); background:linear-gradient(135deg,rgba(127,29,29,.16),rgba(30,41,59,.18))">
    <div class="card-body flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-start gap-3">
            <div class="mt-0.5 text-red-400"><i class="fas fa-rotate-left text-lg"></i></div>
            <div>
                <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Fresh business start</h3>
                <p class="mt-1 max-w-2xl text-xs leading-5" style="color:var(--text-muted)">
                    Clear test orders, payments, sales, receipts, customer records, messages, notifications and stock history. Products, prices, franchises, users, branding and audit history stay in place. Franchise balances return to zero.
                </p>
            </div>
        </div>
        <form action="{{ route('web.admin.businessReset') }}" method="POST" class="flex flex-col gap-2 sm:flex-row sm:items-end" onsubmit="return confirm('This permanently removes the test business records. Continue?')">
            @csrf
            <label class="text-xs" style="color:var(--text-muted)">
                Type <strong style="color:var(--text-primary)">START FRESH</strong>
                <input name="confirmation" required autocomplete="off" pattern="START FRESH" placeholder="START FRESH" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm sm:w-40" style="background:var(--bg-input); border-color:rgba(239,68,68,.45); color:var(--text-primary)">
            </label>
            <button type="submit" class="btn-action btn-decline px-3 py-2" title="Clear test business data">
                <i class="fas fa-broom mr-1"></i> Clear test data
            </button>
        </form>
    </div>
</div>
