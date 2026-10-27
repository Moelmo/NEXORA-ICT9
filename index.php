<?php
declare(strict_types=1);

session_start();

$logs = [];
$databaseReady = false;
$databaseConfig = __DIR__ . '/config/database.php';
$startDateValue = is_string($_GET['start_date'] ?? null) ? $_GET['start_date'] : '';
$endDateValue = is_string($_GET['end_date'] ?? null) ? $_GET['end_date'] : '';
$searchQuery = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$sort = ($_GET['sort'] ?? 'newest') === 'oldest' ? 'oldest' : 'newest';
$filterError = '';
$stats = [
    'total' => 0,
    'this_month' => 0,
    'with_photo' => 0,
];

$parseFilterDate = static function (string $value): ?DateTimeImmutable {
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (
        $date === false
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d') !== $value
    ) {
        return null;
    }

    return $date;
};

$startDate = $parseFilterDate($startDateValue);
$endDate = $parseFilterDate($endDateValue);
$invalidStart = $startDateValue !== '' && $startDate === null;
$invalidEnd = $endDateValue !== '' && $endDate === null;
if ($invalidStart || $invalidEnd || ($startDate && $endDate && $startDate > $endDate)) {
    $filterError = 'Rentang tanggal tidak valid. Periksa tanggal mulai dan selesai.';
}

if (is_file($databaseConfig)) {
    try {
        $pdo = require $databaseConfig;

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Konfigurasi database tidak menghasilkan koneksi PDO.');
        }

        $conditions = [];
        $parameters = [];
        if ($searchQuery !== '') {
            $conditions[] = '(logs.activity LIKE :search OR logs.keterangan LIKE :search OR users.nama_lengkap LIKE :search)';
            $parameters['search'] = '%' . $searchQuery . '%';
        }
        if ($filterError === '') {
            if ($startDate) {
                $conditions[] = 'logs.tanggal >= :start_date';
                $parameters['start_date'] = $startDate->format('Y-m-d 00:00:00');
            }
            if ($endDate) {
                $conditions[] = 'logs.tanggal < :end_date';
                $parameters['end_date'] = $endDate->modify('+1 day')->format('Y-m-d 00:00:00');
            }
        }

        $whereClause = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $direction = $sort === 'oldest' ? 'ASC' : 'DESC';
        $statement = $pdo->prepare(
                'SELECT users.nama_lengkap AS technician_name,
                    logs.tanggal,
                    logs.activity AS activity_title,
                    logs.keterangan AS description,
                    logs.foto AS photo_filename
             FROM logs
             INNER JOIN users ON users.id = logs.user_id
             ' . $whereClause . '
             ORDER BY logs.tanggal ' . $direction
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
        error_log('[NEXORA ICT9] Public dashboard query failed: ' . $exception->getMessage());
    }
}

$sessionUser = $_SESSION['user'] ?? null;
$role = is_array($sessionUser) ? strtolower((string) ($sessionUser['role'] ?? '')) : '';
$dashboardUrl = $role === 'admin'
    ? 'admin/dashboard.php'
    : 'technician/dashboard.php';

function escapeHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatLogDate(mixed $value): string
{
    if (!$value) {
        return 'Waktu tidak tersedia';
    }

    try {
        return (new DateTimeImmutable((string) $value))->format('d M Y, H:i');
    } catch (Throwable $exception) {
        return 'Waktu tidak tersedia';
    }
}

function photoUrl(mixed $filename): ?string
{
    if (!is_string($filename) || trim($filename) === '') {
        return null;
    }

    return 'uploads/' . rawurlencode(basename($filename));
}

function formatLogMonth(mixed $value): string
{
    try {
        $date = new DateTimeImmutable((string) $value);
        $monthNames = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return $monthNames[(int) $date->format('n')] . ' ' . $date->format('Y');
    } catch (Throwable $exception) {
        return 'Tanggal tidak tersedia';
    }
}
?>
<!doctype html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <meta name="description" content="Jurnal pekerjaan dan catatan operasional teknisi jaringan NEXORA ICT9.">
    <title>NEXORA ICT9 | Jurnal Operasional</title>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="assets/app.js" defer></script>
    <script>
        tailwind.config = { darkMode: 'class' };
        (() => {
            try {
                if (localStorage.getItem('nexora-theme') === 'light') {
                    document.documentElement.classList.remove('dark');
                }
            } catch (error) {
                // Keep the default dark theme when browser storage is unavailable.
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; }
        h1, h2, h3, .display-font { font-family: 'Space Grotesk', sans-serif; }
    </style>
</head>
<body class="nexora-background min-h-screen bg-gray-100 text-gray-900 antialiased transition-colors dark:bg-slate-900 dark:text-white">
    <div class="pointer-events-none fixed inset-0 -z-0 opacity-50 dark:opacity-40" aria-hidden="true">
        <div class="absolute inset-0 bg-[linear-gradient(rgba(8,145,178,0.045)_1px,transparent_1px),linear-gradient(90deg,rgba(8,145,178,0.045)_1px,transparent_1px)] bg-[size:44px_44px]"></div>
        <div class="absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-cyan-100/70 to-transparent dark:from-cyan-950/30"></div>
    </div>

    <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/90 backdrop-blur-xl dark:border-slate-700 dark:bg-slate-900/90">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8" aria-label="Navigasi utama">
            <a href="index.php" class="flex min-w-0 items-center gap-3" aria-label="NEXORA ICT9, beranda">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg border border-cyan-600/20 bg-cyan-600/10 text-cyan-700 dark:border-cyan-300/30 dark:bg-cyan-300/10 dark:text-cyan-400">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 18V6l8 12V6m4 12V6h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
                <span class="min-w-0">
                    <span class="display-font block truncate text-sm font-bold">NEXORA <span class="text-cyan-700 dark:text-cyan-400">ICT9</span></span>
                    <span class="block truncate text-xs text-gray-500 dark:text-slate-400">Network Operations Journal</span>
                </span>
            </a>

            <div class="flex shrink-0 items-center gap-2">
                <button type="button" data-theme-toggle class="grid size-10 place-items-center rounded-md border border-gray-200 bg-white/80 text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-cyan-600 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-200 dark:hover:bg-slate-700" aria-label="Ganti tema" title="Ganti tema">
                    <svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.2 15.2A8.5 8.5 0 0 1 8.8 3.8 8.5 8.5 0 1 0 20.2 15.2Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.7" /><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>
                </button>

                <?php if ($sessionUser): ?>
                    <a href="<?= escapeHtml($dashboardUrl) ?>" class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-cyan-600/50 hover:text-cyan-700 dark:border-slate-700 dark:text-slate-200 dark:hover:border-cyan-400/50 dark:hover:text-cyan-300">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 13h6V4H4v9Zm0 7h6v-3H4v3Zm10 0h6v-9h-6v9Zm0-16v3h6V4h-6Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" /></svg>
                        <span class="hidden sm:inline">Dashboard</span>
                    </a>
                    <a href="logout.php" class="rounded-md bg-cyan-700 px-3 py-2.5 text-sm font-bold text-white transition hover:bg-cyan-800 dark:bg-cyan-600 dark:hover:bg-cyan-500">Keluar</a>
                <?php else: ?>
                    <a href="login.php" class="inline-flex items-center gap-2 rounded-md bg-cyan-700 px-3.5 py-2.5 text-sm font-bold text-white transition hover:bg-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:ring-offset-2 dark:bg-cyan-600 dark:hover:bg-cyan-500 dark:focus:ring-offset-slate-900">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Login
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main class="relative z-10 mx-auto max-w-7xl px-4 pb-16 pt-10 sm:px-6 sm:pt-14 lg:px-8">
        <section class="mb-8 flex flex-col justify-between gap-6 border-b border-gray-200 pb-8 dark:border-slate-700 sm:flex-row sm:items-end">
            <div>
                <p class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-400">
                    <span class="size-1.5 rounded-full bg-cyan-600 dark:bg-cyan-400"></span>
                    Operations journal <span class="text-gray-400 dark:text-slate-600">/</span> Public view
                </p>
                <h1 class="nexora-title display-font text-3xl font-bold sm:text-4xl">Jurnal Aktivitas</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-slate-400 sm:text-base">Catatan pekerjaan dan dokumentasi operasional tim teknisi NEXORA ICT9.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3 self-start sm:self-auto">
                <div class="flex items-center gap-3 rounded-xl border border-slate-200/50 bg-white/80 px-3.5 py-2.5 shadow-xl shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/60">
                    <span class="grid size-8 place-items-center rounded bg-cyan-50 text-cyan-700 dark:bg-slate-700 dark:text-cyan-400">
                        <i data-lucide="calendar-days" class="size-4" aria-hidden="true"></i>
                    </span>
                    <span>
                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">Total catatan</span>
                        <span class="display-font block text-lg font-semibold leading-5"><?= (int) $stats['total'] ?></span>
                    </span>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-slate-200/50 bg-white/80 px-3.5 py-2.5 shadow-xl shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/60">
                    <span class="grid size-8 place-items-center rounded bg-emerald-50 text-emerald-700 dark:bg-slate-700 dark:text-emerald-400">
                        <i data-lucide="clock" class="size-4" aria-hidden="true"></i>
                    </span>
                    <span>
                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">Bulan Ini</span>
                        <span class="display-font block text-lg font-semibold leading-5 text-emerald-600 dark:text-emerald-400"><?= (int) $stats['this_month'] ?></span>
                    </span>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-slate-200/50 bg-white/80 px-3.5 py-2.5 shadow-xl shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/60">
                    <span class="grid size-8 place-items-center rounded bg-indigo-50 text-indigo-700 dark:bg-slate-700 dark:text-indigo-400">
                        <i data-lucide="image" class="size-4" aria-hidden="true"></i>
                    </span>
                    <span>
                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">Ada Foto</span>
                        <span class="display-font block text-lg font-semibold leading-5 text-indigo-600 dark:text-indigo-400"><?= (int) $stats['with_photo'] ?></span>
                    </span>
                </div>
            </div>
        </section>

        <section aria-labelledby="activity-heading">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 id="activity-heading" class="display-font flex items-center gap-2 text-lg font-semibold"><i data-lucide="activity" class="size-5 text-cyan-700 dark:text-cyan-400" aria-hidden="true"></i>Aktivitas terbaru</h2>
                <span class="text-xs text-gray-500 dark:text-slate-500">Urut berdasarkan waktu terbaru</span>
            </div>

            <form method="get" action="index.php" class="mb-5 grid gap-3 rounded-xl border border-slate-200/50 bg-white/70 p-4 shadow-lg shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/50 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr_auto_auto] sm:items-end">
                <div>
                    <label for="search-input" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Pencarian</label>
                    <input id="search-input" name="q" type="text" placeholder="Cari aktivitas, keterangan, teknisi..." value="<?= escapeHtml($searchQuery) ?>" class="w-full rounded-md border border-slate-200 bg-white/80 px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 outline-none focus:border-cyan-600 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-100">
                </div>
                <div>
                    <label for="start-date" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Dari tanggal</label>
                    <input id="start-date" name="start_date" type="date" value="<?= escapeHtml($startDateValue) ?>" class="w-full rounded-md border border-slate-200 bg-white/80 px-3 py-2 text-sm text-slate-800 outline-none focus:border-cyan-600 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-100">
                </div>
                <div>
                    <label for="end-date" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Sampai tanggal</label>
                    <input id="end-date" name="end_date" type="date" value="<?= escapeHtml($endDateValue) ?>" class="w-full rounded-md border border-slate-200 bg-white/80 px-3 py-2 text-sm text-slate-800 outline-none focus:border-cyan-600 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-100">
                </div>
                <div>
                    <label for="sort-order" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Urutkan</label>
                    <select id="sort-order" name="sort" class="w-full rounded-md border border-slate-200 bg-white/80 px-3 py-2 text-sm text-slate-800 outline-none focus:border-cyan-600 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-100">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Terlama</option>
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-cyan-700 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-800 dark:bg-cyan-600 dark:hover:bg-cyan-500"><i data-lucide="search" class="size-4" aria-hidden="true"></i>Filter</button>
                <a href="index.php" class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100/70 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/60"><i data-lucide="rotate-ccw" class="size-4" aria-hidden="true"></i>Reset</a>
            </form>

            <?php if ($filterError !== ''): ?>
                <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-200" role="alert"><?= escapeHtml($filterError) ?></div>
            <?php endif; ?>
            <?php if (!$databaseReady): ?>
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-300/20 dark:bg-amber-300/[0.06] dark:text-amber-100/80" role="status">
                    Jurnal belum dapat dimuat. Koneksi database belum tersedia.
                </div>
            <?php elseif ($logs === []): ?>
                <div class="rounded-xl border border-slate-200/50 bg-white/80 px-5 py-12 text-center shadow-xl shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/60">
                    <span class="mx-auto mb-4 grid size-12 place-items-center rounded-lg border border-gray-200 bg-gray-50 text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    </span>
                    <h3 class="display-font text-base font-semibold">Belum ada aktivitas</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">Catatan pekerjaan yang dipublikasikan akan tampil di sini.</p>
                </div>
            <?php else: ?>
                <div class="hidden overflow-hidden rounded-xl border border-slate-200/50 bg-white/80 shadow-xl shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/60 md:block">
                    <table class="w-full table-fixed text-left">
                        <colgroup>
                            <col class="w-[17%]"><col class="w-[17%]"><col class="w-[19%]"><col class="w-[35%]"><col class="w-[12%]">
                        </colgroup>
                        <thead class="border-b border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-900/70">
                            <tr class="text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-slate-400">
                                <th scope="col" class="px-4 py-3.5">Nama Teknisi</th>
                                <th scope="col" class="px-4 py-3.5">Tanggal &amp; Waktu</th>
                                <th scope="col" class="px-4 py-3.5">Activity</th>
                                <th scope="col" class="px-4 py-3.5">Keterangan</th>
                                <th scope="col" class="px-4 py-3.5">Bukti Foto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <?php $lastMonth = null; ?>
                            <?php foreach ($logs as $log): ?>
                                <?php $image = photoUrl($log['photo_filename'] ?? null); ?>
                                <?php $monthLabel = formatLogMonth($log['tanggal'] ?? null); ?>
                                <?php if ($monthLabel !== $lastMonth): ?>
                                    <tr class="bg-slate-100/70 dark:bg-slate-900/70"><th colspan="5" class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-cyan-800 dark:text-cyan-300"><?= escapeHtml($monthLabel) ?></th></tr>
                                    <?php $lastMonth = $monthLabel; ?>
                                <?php endif; ?>
                                <tr class="align-top transition hover:bg-gray-50 dark:hover:bg-white/[0.025]">
                                    <td class="break-words px-4 py-4 text-sm font-semibold text-gray-800 dark:text-slate-200"><?= escapeHtml($log['technician_name'] ?? 'Teknisi') ?></td>
                                    <td class="px-4 py-4 text-sm leading-5 text-gray-600 dark:text-slate-400"><?= escapeHtml(formatLogDate($log['tanggal'] ?? null)) ?></td>
                                    <td class="break-words px-4 py-4 text-sm font-semibold"><?= escapeHtml($log['activity_title'] ?? 'Aktivitas') ?></td>
                                    <td class="break-words px-4 py-4 text-sm leading-6 text-gray-600 dark:text-slate-400"><?= nl2br(escapeHtml($log['description'] ?? '')) ?></td>
                                    <td class="px-4 py-4">
                                        <?php if ($image): ?>
                                            <button type="button" class="photo-preview group block overflow-hidden rounded border border-gray-200 focus:outline-none focus:ring-2 focus:ring-cyan-600 dark:border-slate-700 dark:focus:ring-cyan-400" data-full-src="<?= escapeHtml($image) ?>" data-alt="Dokumentasi: <?= escapeHtml($log['activity_title'] ?? 'aktivitas') ?>" aria-label="Lihat foto dokumentasi ukuran penuh">
                                                <img src="<?= escapeHtml($image) ?>" alt="Dokumentasi: <?= escapeHtml($log['activity_title'] ?? 'aktivitas') ?>" loading="lazy" class="size-14 object-cover transition group-hover:scale-105">
                                            </button>
                                        <?php else: ?>
                                            <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2 py-1 text-[11px] font-semibold text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">Tanpa Foto</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 md:hidden">
                    <?php $lastCardMonth = null; ?>
                    <?php foreach ($logs as $log): ?>
                        <?php $image = photoUrl($log['photo_filename'] ?? null); ?>
                        <?php $monthLabel = formatLogMonth($log['tanggal'] ?? null); ?>
                        <?php if ($monthLabel !== $lastCardMonth): ?>
                            <h3 class="px-1 pt-3 font-display text-sm font-bold uppercase tracking-wider text-cyan-800 dark:text-cyan-300"><?= escapeHtml($monthLabel) ?></h3>
                            <?php $lastCardMonth = $monthLabel; ?>
                        <?php endif; ?>
                        <article class="rounded-xl border border-slate-200/50 bg-white/80 p-4 shadow-xl shadow-slate-900/5 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-800/60">
                            <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1 border-b border-gray-200 pb-3 dark:border-slate-700">
                                <h3 class="flex min-w-0 items-center gap-2 break-words text-sm font-semibold"><i data-lucide="user-round" class="size-4 shrink-0 text-cyan-700 dark:text-cyan-400" aria-hidden="true"></i><?= escapeHtml($log['technician_name'] ?? 'Teknisi') ?></h3>
                                <time class="text-xs text-gray-500 dark:text-slate-500"><?= escapeHtml(formatLogDate($log['tanggal'] ?? null)) ?></time>
                            </div>
                            <div class="py-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-400">Activity</p>
                                <h4 class="mt-1 break-words display-font text-base font-semibold"><?= escapeHtml($log['activity_title'] ?? 'Aktivitas') ?></h4>
                                <p class="mt-2 break-words whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-slate-400"><?= escapeHtml($log['description'] ?? '') ?></p>
                            </div>
                            <div class="border-t border-gray-200 pt-3 dark:border-slate-700">
                                <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-500">Bukti foto</p>
                                <?php if ($image): ?>
                                    <button type="button" class="photo-preview group block max-w-full overflow-hidden rounded border border-gray-200 focus:outline-none focus:ring-2 focus:ring-cyan-600 dark:border-slate-700 dark:focus:ring-cyan-400" data-full-src="<?= escapeHtml($image) ?>" data-alt="Dokumentasi: <?= escapeHtml($log['activity_title'] ?? 'aktivitas') ?>" aria-label="Lihat foto dokumentasi ukuran penuh">
                                        <img src="<?= escapeHtml($image) ?>" alt="Dokumentasi: <?= escapeHtml($log['activity_title'] ?? 'aktivitas') ?>" loading="lazy" class="max-h-52 max-w-full object-cover transition group-hover:opacity-90">
                                    </button>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">Tanpa Foto</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <dialog id="photo-dialog" class="m-auto max-h-[90vh] max-w-[calc(100vw-2rem)] rounded-lg border border-gray-200 bg-white p-2 text-gray-900 shadow-2xl backdrop:bg-black/80 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
        <div class="flex items-center justify-between gap-4 px-1 pb-2">
            <p id="photo-caption" class="max-w-[75vw] truncate text-sm text-gray-600 dark:text-slate-300"></p>
            <button id="close-photo" type="button" data-modal-close class="grid size-8 shrink-0 place-items-center rounded text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white" aria-label="Tutup pratinjau foto">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
            </button>
        </div>
        <img id="photo-full" src="" alt="" class="max-h-[78vh] max-w-full rounded object-contain">
    </dialog>

    <footer class="relative z-10 border-t border-gray-200 dark:border-slate-700">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-xs text-gray-500 dark:text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <span>NEXORA ICT9 <span class="text-gray-400 dark:text-slate-700">/</span> Network Excellence Operations Recording App</span>
            <span>Jurnal operasional teknisi jaringan</span>
        </div>
    </footer>

</body>
</html>