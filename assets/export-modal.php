<dialog id="export-modal" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-xl border border-gray-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
    <form method="get" action="<?= htmlspecialchars($exportAction, ENT_QUOTES, 'UTF-8') ?>" data-export-form novalidate class="p-5 sm:p-6">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-400">Unduh data</p>
                <h2 class="mt-1 font-display text-xl font-semibold">Export Excel</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">Pilih rentang tanggal jurnal yang akan diekspor.</p>
            </div>
            <button type="button" data-modal-close class="grid size-9 shrink-0 place-items-center rounded-md text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-700" aria-label="Tutup modal export">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
            </button>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="export-start-date" class="mb-1.5 block text-sm font-semibold">Tanggal Mulai</label>
                <input id="export-start-date" name="start_date" type="date" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-cyan-600 dark:border-slate-600 dark:bg-slate-900">
            </div>
            <div>
                <label for="export-end-date" class="mb-1.5 block text-sm font-semibold">Tanggal Selesai</label>
                <input id="export-end-date" name="end_date" type="date" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-cyan-600 dark:border-slate-600 dark:bg-slate-900">
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 border-t border-gray-200 pt-4 dark:border-slate-700 sm:flex-row sm:justify-end">
            <button type="button" data-modal-close class="rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold hover:bg-gray-50 dark:border-slate-600 dark:hover:bg-slate-700">Batal</button>
            <button type="submit" class="rounded-md bg-cyan-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-cyan-800 dark:bg-cyan-600 dark:hover:bg-cyan-500">Unduh CSV</button>
        </div>
    </form>
</dialog>