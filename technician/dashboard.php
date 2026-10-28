<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$user = nexoraTechnicianRequireUser();
$logs = [];
$databaseReady = false;
$errorMessage = '';
$successMessages = [
    'created' => 'Jurnal berhasil ditambahkan.',
    'updated' => 'Jurnal berhasil diperbarui.',
    'deleted' => 'Jurnal berhasil dihapus.',
];
$status = $_GET['status'] ?? '';
$successMessage = is_string($status) ? ($successMessages[$status] ?? '') : '';
$errorMessages = [
    'export-invalid' => 'Rentang tanggal export tidak valid.',
    'export-error' => 'Export belum dapat dibuat. Silakan coba lagi.',
];
$stats = [
    'total' => 0,
    'this_month' => 0,
    'with_photo' => 0,
];
$statusErrorMessage = is_string($status) ? ($errorMessages[$status] ?? '') : '';
$exportAction = '../export-excel.php';
$searchQuery = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$startDateValue = is_string($_GET['start_date'] ?? null) ? $_GET['start_date'] : '';
$endDateValue = is_string($_GET['end_date'] ?? null) ? $_GET['end_date'] : '';
$sort = ($_GET['sort'] ?? 'newest') === 'oldest' ? 'oldest' : 'newest';
$parseFilterDate = static function (string $value): ?DateTimeImmutable {
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
        return null;
    }
    return $date;
};
$startDate = $parseFilterDate($startDateValue);
$endDate = $parseFilterDate($endDateValue);
$filterError = '';
if (($startDateValue !== '' && $startDate === null) || ($endDateValue !== '' && $endDate === null) || ($startDate && $endDate && $startDate > $endDate)) {
    $filterError = 'Rentang tanggal tidak valid. Periksa tanggal mulai dan selesai.';
}
if ($filterError !== '') {
    $statusErrorMessage = $filterError;
}

try {
    $pdo = nexoraTechnicianPdo();
    $conditions = ['user_id = :user_id'];
    $parameters = ['user_id' => (int) $user['id']];
    if ($searchQuery !== '') {
        $conditions[] = '(activity LIKE :search OR team LIKE :search OR keterangan LIKE :search)';
        $parameters['search'] = '%' . $searchQuery . '%';
    }
    if ($filterError === '') {
        if ($startDate) {
            $conditions[] = 'tanggal >= :start_date';
            $parameters['start_date'] = $startDate->format('Y-m-d 00:00:00');
        }
        if ($endDate) {
            $conditions[] = 'tanggal < :end_date';
            $parameters['end_date'] = $endDate->modify('+1 day')->format('Y-m-d 00:00:00');
        }
    }
    $direction = $sort === 'oldest' ? 'ASC' : 'DESC';
    $statement = $pdo->prepare(
        'SELECT id, activity AS activity_title, team, keterangan AS description,
            foto AS photo_filename, tanggal
         FROM logs
         WHERE ' . implode(' AND ', $conditions) . '
         ORDER BY tanggal ' . $direction
    );
    $statement->execute($parameters);
    $logs = $statement->fetchAll();
    $databaseReady = true;

    $stats = [
        'total' => count($logs),
        'this_month' => 0,
        'with_photo' => 0,
    ];
    $currentMonthPrefix = (new DateTimeImmutable('now'))->format('Y-m');
    foreach ($logs as $item) {
        if (isset($item['tanggal']) && str_starts_with((string) $item['tanggal'], $currentMonthPrefix)) {
            $stats['this_month']++;
        }
        if (!empty($item['photo_filename'])) {
            $stats['with_photo']++;
        }
    }
} catch (Throwable $exception) {
    error_log('[NEXORA ICT9] Technician dashboard failed: ' . $exception->getMessage());
    $errorMessage = 'Jurnal belum dapat dimuat. Silakan coba lagi nanti.';
}

$csrfToken = nexoraTechnicianCsrfToken();
?>
<!doctype html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>Dashboard Teknisi | NEXORA ICT9</title>
    <link rel="stylesheet" href="../assets/theme.css">
    <script src="../assets/theme.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="../assets/app.js" defer></script>
    <script>
        tailwind.config = { darkMode: 'class' };
        try {
            if (localStorage.getItem('nexora-theme') === 'light') document.documentElement.classList.remove('dark');
        } catch (error) {}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'DM Sans',sans-serif}h1,h2,h3,.display-font{font-family:'Space Grotesk',sans-serif}</style>
</head>
<body class="nexora-background min-h-screen bg-gray-100 text-gray-900 antialiased dark:bg-slate-900 dark:text-white">
    <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/90 backdrop-blur-xl dark:border-slate-700 dark:bg-slate-900/90">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8" aria-label="Navigasi utama">
            <a href="dashboard.php" class="min-w-0">
                <span class="display-font block text-sm font-bold">NEXORA <span class="text-cyan-700 dark:text-cyan-400">ICT9</span></span>
                <span class="block text-xs text-gray-500 dark:text-slate-400">Ruang kerja teknisi</span>
            </a>
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" data-theme-toggle class="grid size-10 place-items-center rounded-md border border-gray-200 bg-white/80 text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-200 dark:hover:bg-slate-700" aria-label="Ganti tema" title="Ganti tema">
                    <svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 15.2A8.5 8.5 0 0 1 8.8 4 8.5 8.5 0 1 0 20 15.2Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.7" /><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>
                </button>
                <button type="button" data-modal-open="export-modal" class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white/80 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-200 dark:hover:bg-slate-700">
                    <svg class="size-4 text-cyan-700 dark:text-cyan-400" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 17v3h14v-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <span class="hidden sm:inline">Export Excel</span>
                </button>
                <a href="../index.php" class="hidden rounded-md border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 sm:inline-flex">Lihat publik</a>
                <span class="hidden max-w-40 truncate text-sm text-gray-600 dark:text-slate-300 sm:block"><?= nexoraTechnicianEscape($user['full_name'] ?? 'Teknisi') ?></span>
                <a href="change-password.php" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" title="Ganti Password">Ganti Password</a>
                <a href="../logout.php" class="rounded-md bg-cyan-700 px-3 py-2 text-sm font-bold text-white hover:bg-cyan-800 dark:bg-cyan-600 dark:hover:bg-cyan-500">Keluar</a>
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-7xl px-4 pb-14 pt-9 sm:px-6 lg:px-8">
        <section class="mb-7 flex flex-col justify-between gap-5 border-b border-gray-200 pb-7 dark:border-slate-700 sm:flex-row sm:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-400">Area teknisi</p>
                <h1 class="nexora-title display-font text-3xl font-bold">Jurnal Saya</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-400">Log pekerjaan yang dibuat oleh <?= nexoraTechnicianEscape($user['full_name'] ?? 'akun ini') ?>.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-3 rounded-xl border border-slate-200/50 bg-white/80 px-3.5 py-2.5 shadow-xl shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/60">
                    <span class="grid size-8 place-items-center rounded bg-cyan-50 text-cyan-700 dark:bg-slate-700 dark:text-cyan-400">
                        <i data-lucide="clipboard-list" class="size-4" aria-hidden="true"></i>
                    </span>
                    <span>
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">Total</span>
                        <span class="display-font block text-base font-semibold leading-5"><?= (int) $stats['total'] ?></span>
                    </span>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-slate-200/50 bg-white/80 px-3.5 py-2.5 shadow-xl shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/60">
                    <span class="grid size-8 place-items-center rounded bg-emerald-50 text-emerald-700 dark:bg-slate-700 dark:text-emerald-400">
                        <i data-lucide="calendar" class="size-4" aria-hidden="true"></i>
                    </span>
                    <span>
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">Bulan Ini</span>
                        <span class="display-font block text-base font-semibold leading-5 text-emerald-600 dark:text-emerald-400"><?= (int) $stats['this_month'] ?></span>
                    </span>
                </div>
                <a href="log-add.php" class="inline-flex items-center justify-center gap-2 rounded-lg bg-cyan-700 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-900/10 transition hover:bg-cyan-800 dark:bg-cyan-600 dark:hover:bg-cyan-500">
                    <i data-lucide="plus" class="size-4" aria-hidden="true"></i>
                    Tambah jurnal
                </a>
            </div>
        </section>

        <form method="get" action="dashboard.php" class="mb-5 grid gap-3 rounded-xl border border-slate-200/50 bg-white/70 p-4 shadow-lg shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/50 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr_auto_auto] sm:items-end">
            <div><label for="search-input" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Pencarian</label><input id="search-input" name="q" type="text" placeholder="Cari aktivitas / keterangan..." value="<?= nexoraTechnicianEscape($searchQuery) ?>" class="w-full rounded-md border border-slate-200 bg-white/80 px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-100"></div>
            <div><label for="start-date" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Dari tanggal</label><input id="start-date" name="start_date" type="date" value="<?= nexoraTechnicianEscape($startDateValue) ?>" class="w-full rounded-md border border-slate-200 bg-white/80 px-3 py-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-100"></div>
            <div><label for="end-date" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Sampai tanggal</label><input id="end-date" name="end_date" type="date" value="<?= nexoraTechnicianEscape($endDateValue) ?>" class="w-full rounded-md border border-slate-200 bg-white/80 px-3 py-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-100"></div>
            <div><label for="sort-order" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Urutkan</label><select id="sort-order" name="sort" class="w-full rounded-md border border-slate-200 bg-white/80 px-3 py-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-100"><option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option><option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Terlama</option></select></div>
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-cyan-700 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-800 dark:bg-cyan-600 dark:hover:bg-cyan-500"><i data-lucide="search" class="size-4" aria-hidden="true"></i>Filter</button>
            <a href="dashboard.php" class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200"><i data-lucide="rotate-ccw" class="size-4" aria-hidden="true"></i>Reset</a>
        </form>

        <?php if ($successMessage !== ''): ?>
            <div class="mb-5 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200" role="status"><?= nexoraTechnicianEscape($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($statusErrorMessage !== ''): ?>
            <div class="mb-5 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-200" role="alert"><?= nexoraTechnicianEscape($statusErrorMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
            <div class="mb-5 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-200" role="alert"><?= nexoraTechnicianEscape($errorMessage) ?></div>
        <?php elseif ($databaseReady && $logs === []): ?>
            <div class="rounded-xl border border-slate-200/50 bg-white/80 px-5 py-12 text-center shadow-xl shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/60">
                <i data-lucide="clipboard-plus" class="mx-auto mb-3 size-8 text-cyan-700 dark:text-cyan-400" aria-hidden="true"></i>
                <h2 class="display-font text-lg font-semibold">Belum ada jurnal</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">Catatan yang Anda buat akan ditampilkan di sini.</p>
            </div>
        <?php elseif ($databaseReady): ?>
            <div class="hidden overflow-hidden rounded-xl border border-slate-200/50 bg-white/80 shadow-xl shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/60 md:block">
                <table class="w-full table-fixed text-left">
                    <colgroup><col class="w-[18%]"><col class="w-[15%]"><col class="w-[20%]"><col class="w-[27%]"><col class="w-[10%]"><col class="w-[10%]"></colgroup>
                    <thead class="border-b border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-900/70">
                        <tr class="text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-slate-400">
                            <th scope="col" class="px-4 py-3.5">Nama Teknisi</th>
                            <th scope="col" class="px-4 py-3.5">Tanggal</th>
                            <th scope="col" class="px-4 py-3.5">Activity</th>
                            <th scope="col" class="px-4 py-3.5">Keterangan</th>
                            <th scope="col" class="px-4 py-3.5">Foto</th>
                            <th scope="col" class="px-4 py-3.5">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                        <?php $lastMonth = null; ?>
                        <?php foreach ($logs as $log): ?>
                            <?php $photo = !empty($log['photo_filename']) ? '../uploads/' . rawurlencode(basename((string) $log['photo_filename'])) : null; ?>
                            <?php $monthLabel = nexoraTechnicianFormatMonth($log['tanggal'] ?? null); ?>
                            <?php if ($monthLabel !== $lastMonth): ?>
                                <tr class="bg-slate-100/70 dark:bg-slate-900/70"><th colspan="6" class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-cyan-800 dark:text-cyan-300"><?= nexoraTechnicianEscape($monthLabel) ?></th></tr>
                                <?php $lastMonth = $monthLabel; ?>
                            <?php endif; ?>
                            <tr class="align-top hover:bg-gray-50 dark:hover:bg-white/[0.025]">
                                <td class="break-words px-4 py-4 text-sm font-semibold text-gray-800 dark:text-slate-200">
                                    <div><?= nexoraTechnicianEscape($user['full_name'] ?? 'Teknisi') ?></div>
                                    <?php if (!empty($log['team'])): ?>
                                        <div class="mt-1 space-y-0.5 text-xs font-normal text-cyan-700 dark:text-cyan-400">
                                            <?php foreach (array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", (string) $log['team'])))) as $teamMember): ?>
                                                <div class="flex items-center gap-1">
                                                    <i data-lucide="user" class="size-3 shrink-0 opacity-70" aria-hidden="true"></i>
                                                    <span class="break-words"><?= nexoraTechnicianEscape($teamMember) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-600 dark:text-slate-400"><span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200/70 bg-slate-100/70 px-2 py-1 text-xs dark:border-slate-700/70 dark:bg-slate-900/50"><i data-lucide="clock-3" class="size-3.5" aria-hidden="true"></i><?= nexoraTechnicianEscape(nexoraTechnicianFormatDate($log['tanggal'] ?? null)) ?></span></td>
                                <td class="break-words px-4 py-4 text-sm font-semibold"><?= nexoraTechnicianEscape($log['activity_title'] ?? '') ?></td>
                                <td class="break-words px-4 py-4 text-sm leading-6 text-gray-600 dark:text-slate-400"><?= nl2br(nexoraTechnicianEscape($log['description'] ?? '')) ?></td>
                                <td class="px-4 py-4">
                                    <?php if ($photo): ?>
                                        <button type="button" class="photo-preview overflow-hidden rounded border border-gray-200 focus:outline-none focus:ring-2 focus:ring-cyan-600 dark:border-slate-700 dark:focus:ring-cyan-400" data-src="<?= nexoraTechnicianEscape($photo) ?>" data-alt="<?= nexoraTechnicianEscape($log['activity_title'] ?? 'Foto jurnal') ?>" aria-label="Lihat foto jurnal"><img src="<?= nexoraTechnicianEscape($photo) ?>" alt="<?= nexoraTechnicianEscape($log['activity_title'] ?? 'Foto jurnal') ?>" loading="lazy" class="size-14 object-cover"></button>
                                    <?php else: ?>
                                        <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2 py-1 text-[11px] font-semibold text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">Tanpa Foto</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col items-start gap-2">
                                        <a href="log-edit.php?id=<?= (int) $log['id'] ?>" class="inline-flex items-center gap-1 text-sm font-semibold text-cyan-700 hover:underline dark:text-cyan-400"><i data-lucide="pencil" class="size-3.5" aria-hidden="true"></i>Edit</a>
                                        <form method="post" action="log-delete.php" data-confirm-message="Hapus jurnal ini? Tindakan ini tidak dapat dibatalkan.">
                                            <input type="hidden" name="csrf_token" value="<?= nexoraTechnicianEscape($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $log['id'] ?>">
                                            <button type="submit" class="inline-flex items-center gap-1 text-sm font-semibold text-red-700 hover:underline dark:text-red-400"><i data-lucide="trash-2" class="size-3.5" aria-hidden="true"></i>Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="space-y-3 md:hidden">
                <?php $lastCardMonth = null; ?>
                <?php foreach ($logs as $log): ?>
                    <?php $photo = !empty($log['photo_filename']) ? '../uploads/' . rawurlencode(basename((string) $log['photo_filename'])) : null; ?>
                    <?php $monthLabel = nexoraTechnicianFormatMonth($log['tanggal'] ?? null); ?>
                    <?php if ($monthLabel !== $lastCardMonth): ?>
                        <h3 class="px-1 pt-3 font-display text-sm font-bold uppercase tracking-wider text-cyan-800 dark:text-cyan-300"><?= nexoraTechnicianEscape($monthLabel) ?></h3>
                        <?php $lastCardMonth = $monthLabel; ?>
                    <?php endif; ?>
                    <article class="rounded-xl border border-slate-200/50 bg-white/80 p-4 shadow-xl shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/60">
                        <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-200 pb-3 dark:border-slate-700">
                            <div>
                                <p class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-slate-400">
                                    <i data-lucide="user-round" class="size-3.5 text-cyan-700 dark:text-cyan-400" aria-hidden="true"></i>
                                    <span><?= nexoraTechnicianEscape($user['full_name'] ?? 'Teknisi') ?></span>
                                </p>
                                <?php if (!empty($log['team'])): ?>
                                    <div class="mt-1 space-y-0.5 text-xs text-cyan-700 dark:text-cyan-400">
                                        <?php foreach (array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", (string) $log['team'])))) as $teamMember): ?>
                                            <div class="flex items-center gap-1">
                                                <i data-lucide="user" class="size-3 shrink-0 opacity-70" aria-hidden="true"></i>
                                                <span class="break-words"><?= nexoraTechnicianEscape($teamMember) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <h2 class="display-font mt-2 break-words text-base font-semibold"><?= nexoraTechnicianEscape($log['activity_title'] ?? '') ?></h2>
                            </div>
                            <time class="inline-flex shrink-0 items-center gap-1 rounded-full border border-slate-200/70 bg-slate-100/70 px-2 py-1 text-xs text-gray-500 dark:border-slate-700/70 dark:bg-slate-900/50 dark:text-slate-400"><i data-lucide="clock-3" class="size-3.5" aria-hidden="true"></i><?= nexoraTechnicianEscape(nexoraTechnicianFormatDate($log['tanggal'] ?? null)) ?></time>
                        </div>
                        <p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-gray-600 dark:text-slate-400"><?= nexoraTechnicianEscape($log['description'] ?? '') ?></p>
                        <div class="mt-3 border-t border-gray-200 pt-3 dark:border-slate-700">
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-500">Foto dokumentasi</p>
                            <?php if ($photo): ?>
                                <button type="button" class="photo-preview max-w-full overflow-hidden rounded border border-gray-200 focus:outline-none focus:ring-2 focus:ring-cyan-600 dark:border-slate-700 dark:focus:ring-cyan-400" data-src="<?= nexoraTechnicianEscape($photo) ?>" data-alt="<?= nexoraTechnicianEscape($log['activity_title'] ?? 'Foto jurnal') ?>" aria-label="Lihat foto jurnal"><img src="<?= nexoraTechnicianEscape($photo) ?>" alt="<?= nexoraTechnicianEscape($log['activity_title'] ?? 'Foto jurnal') ?>" loading="lazy" class="max-h-48 max-w-full object-cover"></button>
                            <?php else: ?>
                                <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">Tanpa Foto</span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 flex items-center gap-4 border-t border-gray-200 pt-3 dark:border-slate-700">
                            <a href="log-edit.php?id=<?= (int) $log['id'] ?>" class="inline-flex items-center gap-1 text-sm font-semibold text-cyan-700 hover:underline dark:text-cyan-400"><i data-lucide="pencil" class="size-3.5" aria-hidden="true"></i>Edit</a>
                            <form method="post" action="log-delete.php" data-confirm-message="Hapus jurnal ini? Tindakan ini tidak dapat dibatalkan.">
                                <input type="hidden" name="csrf_token" value="<?= nexoraTechnicianEscape($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= (int) $log['id'] ?>">
                                <button type="submit" class="inline-flex items-center gap-1 text-sm font-semibold text-red-700 hover:underline dark:text-red-400"><i data-lucide="trash-2" class="size-3.5" aria-hidden="true"></i>Hapus</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/../assets/export-modal.php'; ?>
    <dialog id="photo-dialog" class="m-auto max-h-[90vh] max-w-[calc(100vw-2rem)] rounded-lg border border-gray-200 bg-white p-2 text-gray-900 shadow-2xl backdrop:bg-black/80 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
        <div class="flex items-center justify-between gap-4 px-1 pb-2"><p id="photo-caption" class="max-w-[75vw] truncate text-sm text-gray-600 dark:text-slate-300"></p><button id="close-photo" type="button" data-modal-close class="grid size-8 place-items-center rounded hover:bg-gray-100 dark:hover:bg-white/10" aria-label="Tutup foto">×</button></div>
        <img id="photo-full" src="" alt="" class="max-h-[78vh] max-w-full rounded object-contain">
    </dialog>
</body>
</html>