(() => {
    const root = document.documentElement;
    if (window.lucide) lucide.createIcons();
    const themeButtons = document.querySelectorAll('[data-theme-toggle]');

    themeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const isDark = root.classList.toggle('dark');
            try {
                localStorage.setItem('nexora-theme', isDark ? 'dark' : 'light');
            } catch (error) {
                // The selected theme remains active for this page view.
            }
        });
    });

    document.querySelectorAll('[data-modal-open]').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById(button.dataset.modalOpen)?.showModal();
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });

    document.querySelectorAll('dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });

    document.querySelectorAll('.photo-preview').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById('photo-dialog');
            const image = document.getElementById('photo-full');
            const caption = document.getElementById('photo-caption');
            if (!dialog || !image || !caption) return;

            image.src = button.dataset.fullSrc || button.dataset.src || '';
            image.alt = button.dataset.alt || '';
            caption.textContent = image.alt;
            dialog.showModal();
        });
    });

    const makeMessageDialog = (message, isError) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'm-auto w-[calc(100%-2rem)] max-w-md rounded-xl border border-gray-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100';
        const panel = document.createElement('div');
        panel.className = 'p-5 sm:p-6';
        const heading = document.createElement('h2');
        heading.className = `font-display text-lg font-semibold ${isError ? 'text-red-700 dark:text-red-300' : 'text-emerald-700 dark:text-emerald-300'}`;
        heading.textContent = isError ? 'Perlu perhatian' : 'Berhasil';
        const body = document.createElement('p');
        body.className = 'mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300';
        body.textContent = message;
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'mt-5 w-full rounded-md bg-cyan-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-cyan-800 dark:bg-cyan-600 dark:hover:bg-cyan-500';
        close.textContent = 'Tutup';
        close.addEventListener('click', () => dialog.close());
        panel.append(heading, body, close);
        dialog.append(panel);
        dialog.addEventListener('close', () => {
            dialog.remove();
            messageIsOpen = false;
            showNextMessage();
        }, { once: true });
        document.body.append(dialog);
        dialog.showModal();
    };

    const messageQueue = [];
    let messageIsOpen = false;
    const showNextMessage = () => {
        if (messageIsOpen || messageQueue.length === 0) return;
        messageIsOpen = true;
        const next = messageQueue.shift();
        makeMessageDialog(next.message, next.isError);
    };

    document.querySelectorAll('[role="alert"], [role="status"]').forEach((notice) => {
        const message = notice.textContent.trim();
        const isError = notice.getAttribute('role') === 'alert';
        notice.remove();
        messageQueue.push({ message, isError });
    });
    showNextMessage();

    document.querySelectorAll('[data-export-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const startDate = form.elements.namedItem('start_date').value;
            const endDate = form.elements.namedItem('end_date').value;
            let validationMessage = '';

            if (!startDate || !endDate) {
                validationMessage = 'Pilih tanggal mulai dan tanggal selesai.';
            } else if (startDate > endDate) {
                validationMessage = 'Tanggal mulai harus sama dengan atau sebelum tanggal selesai.';
            }

            if (validationMessage !== '') {
                event.preventDefault();
                messageQueue.push({ message: validationMessage, isError: true });
                showNextMessage();
            }
        });
    });

    const confirmation = document.createElement('dialog');
    confirmation.className = 'm-auto w-[calc(100%-2rem)] max-w-md rounded-xl border border-gray-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100';
    confirmation.innerHTML = '<div class="p-5 sm:p-6"><h2 class="font-display text-lg font-semibold">Konfirmasi tindakan</h2><p data-confirm-message class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300"></p><div class="mt-5 flex justify-end gap-2"><button type="button" data-confirm-cancel class="rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold dark:border-slate-600">Batal</button><button type="button" data-confirm-accept class="rounded-md bg-red-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-800">Ya, lanjutkan</button></div></div>';
    document.body.append(confirmation);

    let pendingForm = null;
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-confirm-message]');
        if (!form) return;
        if (form.dataset.confirmApproved === 'true') {
            delete form.dataset.confirmApproved;
            return;
        }

        event.preventDefault();
        pendingForm = form;
        confirmation.querySelector('[data-confirm-message]').textContent = form.dataset.confirmMessage;
        confirmation.showModal();
    });

    confirmation.querySelector('[data-confirm-cancel]').addEventListener('click', () => confirmation.close());
    confirmation.querySelector('[data-confirm-accept]').addEventListener('click', () => {
        if (!pendingForm) return;
        const form = pendingForm;
        pendingForm = null;
        confirmation.close();
        form.dataset.confirmApproved = 'true';
        form.requestSubmit();
    });
})();