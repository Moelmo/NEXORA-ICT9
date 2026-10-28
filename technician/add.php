<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$user = nexoraTechnicianRequireUser();
$errorMessage = '';
$values = [
    'activity_title' => '',
    'team_members' => [],
    'team_custom' => '',
    'description' => '',
    'tanggal' => date('Y-m-d\TH:i'),
];

try {
    $pdo = nexoraTechnicianPdo();
    $techStmt = $pdo->prepare('SELECT id, nama_lengkap FROM users WHERE role = "teknisi" AND id != :my_id ORDER BY nama_lengkap ASC');
    $techStmt->execute(['my_id' => (int) $user['id']]);
    $availableTechnicians = $techStmt->fetchAll();
} catch (Throwable $e) {
    $availableTechnicians = [];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (is_string($_POST['activity_title'] ?? null)) {
        $values['activity_title'] = $_POST['activity_title'];
    }
    if (is_array($_POST['team_members'] ?? null)) {
        $values['team_members'] = $_POST['team_members'];
    }
    if (is_string($_POST['team_custom'] ?? null)) {
        $values['team_custom'] = $_POST['team_custom'];
    }
    if (is_string($_POST['description'] ?? null)) {
        $values['description'] = $_POST['description'];
    }
    if (is_string($_POST['tanggal'] ?? null)) {
        $values['tanggal'] = $_POST['tanggal'];
    }

    $photoFilename = null;
    try {
        nexoraTechnicianVerifyCsrf();
        $input = nexoraTechnicianValidateLogInput();
        $photoFilename = nexoraTechnicianPhotoUpload($_FILES['photo'] ?? null);

        try {
            $pdo = nexoraTechnicianPdo();
            $statement = $pdo->prepare(
                'INSERT INTO logs (user_id, activity, team, keterangan, foto, tanggal)
                 VALUES (:user_id, :activity_title, :team, :description, :photo_filename, :tanggal)'
            );
            $statement->execute([
                'user_id' => (int) $user['id'],
                'activity_title' => $input['activity_title'],
                'team' => $input['team'],
                'description' => $input['description'],
                'photo_filename' => $photoFilename,
                'tanggal' => $input['tanggal'],
            ]);
        } catch (Throwable $exception) {
            nexoraTechnicianDeletePhoto($photoFilename);
            throw $exception;
        }

        nexoraTechnicianRedirect('dashboard.php?status=created');
    } catch (Throwable $exception) {
        error_log('[NEXORA ICT9] Technician log creation failed: ' . $exception->getMessage());
        $errorMessage = $exception instanceof PDOException
            ? 'Jurnal gagal disimpan. Silakan coba lagi.'
            : $exception->getMessage();
    }
}

$csrfToken = nexoraTechnicianCsrfToken();
?>
<!doctype html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah Jurnal | NEXORA ICT9</title>
    <link rel="stylesheet" href="../assets/theme.css">
    <script src="../assets/theme.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/app.js" defer></script>
    <script>tailwind.config={darkMode:'class'};try{if(localStorage.getItem('nexora-theme')==='light')document.documentElement.classList.remove('dark')}catch(error){}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'DM Sans',sans-serif}h1,h2{font-family:'Space Grotesk',sans-serif}</style>
</head>
<body class="nexora-background min-h-screen bg-gray-100 text-gray-900 antialiased dark:bg-slate-900 dark:text-white">
    <header class="border-b border-gray-200 bg-white dark:border-slate-700 dark:bg-slate-900">
        <nav class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3 sm:px-6" aria-label="Navigasi utama">
            <a href="dashboard.php"><strong class="font-semibold">NEXORA <span class="text-cyan-700 dark:text-cyan-400">ICT9</span></strong><span class="ml-2 text-xs text-gray-500 dark:text-slate-400">Teknisi</span></a>
            <div class="flex items-center gap-2"><button type="button" data-theme-toggle class="grid size-10 place-items-center rounded-md border border-gray-200 bg-white/80 text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-200 dark:hover:bg-slate-700" aria-label="Ganti tema" title="Ganti tema"><svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 15.2A8.5 8.5 0 0 1 8.8 4 8.5 8.5 0 1 0 20 15.2Z" stroke="currentColor" stroke-width="1.7" /></svg><svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.7" /><path d="M12 2v2m0 16v2M2 12h2m16 0h2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg></button><a href="dashboard.php" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-semibold hover:bg-gray-50 dark:border-slate-700 dark:hover:bg-slate-800">Kembali</a></div>
        </nav>
    </header>
    <main class="mx-auto max-w-4xl px-4 py-9 sm:px-6">
        <div class="mb-6"><p class="text-xs font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-400">Jurnal teknisi</p><h1 class="mt-2 text-3xl font-semibold">Tambah aktivitas</h1><p class="mt-2 text-sm text-gray-600 dark:text-slate-400">Catat pekerjaan; foto dokumentasi dapat ditambahkan jika tersedia.</p></div>
        <?php if ($errorMessage !== ''): ?>
            <div class="mb-5 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-200" role="alert"><?= nexoraTechnicianEscape($errorMessage) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="space-y-5 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:shadow-none sm:p-7">
            <input type="hidden" name="csrf_token" value="<?= nexoraTechnicianEscape(nexoraTechnicianCsrfToken()) ?>">
            <div>
                <label for="activity_title" class="mb-1.5 block text-sm font-semibold">Judul Activity</label>
                <input id="activity_title" name="activity_title" type="text" maxlength="200" required value="<?= nexoraTechnicianEscape($values['activity_title']) ?>" placeholder="Contoh: Pemasangan Switch Cisco" class="w-full rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900 dark:focus:border-cyan-400">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-semibold">Tim / Rekan Kerja <span class="text-xs font-normal text-gray-500 dark:text-slate-400">(Opsional)</span></label>
                <p class="mb-2 text-xs text-gray-500 dark:text-slate-400">Pilih dari data teknisi yang bertugas bersama Anda atau tulis nama anggota lain.</p>
                <?php if ($availableTechnicians !== []): ?>
                    <div class="mb-2.5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <?php foreach ($availableTechnicians as $tech): ?>
                            <?php $techName = (string) $tech['nama_lengkap']; ?>
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-gray-200 bg-gray-50/50 p-2.5 text-sm transition hover:bg-gray-100/70 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:bg-slate-800/60">
                                <input type="checkbox" name="team_members[]" value="<?= nexoraTechnicianEscape($techName) ?>" <?= in_array($techName, (array) $values['team_members'], true) ? 'checked' : '' ?> class="size-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-800">
                                <span class="font-medium text-gray-800 dark:text-slate-200"><?= nexoraTechnicianEscape($techName) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <input id="team_custom" name="team_custom" type="text" value="<?= nexoraTechnicianEscape($values['team_custom']) ?>" placeholder="Atau ketik nama tim/rekan lain (pisahkan dengan koma jika lebih dari satu)" class="w-full rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900 dark:focus:border-cyan-400">
            </div>
            <div>
                <label for="description" class="mb-1.5 block text-sm font-semibold">Keterangan pekerjaan</label>
                <textarea id="description" name="description" rows="6" required maxlength="60000" class="w-full resize-y rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm leading-6 outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900 dark:focus:border-cyan-400"><?= nexoraTechnicianEscape($values['description']) ?></textarea>
            </div>
            <div>
                <label for="tanggal" class="mb-1.5 block text-sm font-semibold">Tanggal &amp; waktu pekerjaan</label>
                <input id="tanggal" name="tanggal" type="datetime-local" required value="<?= nexoraTechnicianEscape($values['tanggal']) ?>" class="w-full rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:focus:border-cyan-400">
            </div>
            <div>
                <label for="photo" class="mb-1.5 block text-sm font-semibold">Foto dokumentasi</label>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full cursor-pointer rounded-md border border-gray-300 bg-white text-sm text-gray-700 file:mr-4 file:border-0 file:bg-gray-100 file:px-4 file:py-2.5 file:font-semibold dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:file:bg-slate-700 dark:file:text-slate-100">
                <p class="mt-1.5 text-xs text-gray-500 dark:text-slate-400">Opsional. JPG, PNG, atau WEBP. Maksimal 5 MB.</p>
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-5 dark:border-slate-700 sm:flex-row sm:justify-end">
                <a href="dashboard.php" class="rounded-md border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold hover:bg-gray-50 dark:border-slate-600 dark:hover:bg-slate-700">Batal</a>
                <button type="submit" class="rounded-md bg-cyan-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-cyan-800 dark:bg-cyan-600 dark:hover:bg-cyan-500">Simpan jurnal</button>
            </div>
        </form>
    </main>
</body>
</html>