<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$user = nexoraTechnicianRequireUser();
$logId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($logId === false) {
    http_response_code(404);
    exit('Jurnal tidak ditemukan.');
}

try {
    $pdo = nexoraTechnicianPdo();
    $log = nexoraTechnicianOwnedLog($pdo, (int) $logId, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('[NEXORA ICT9] Technician log lookup failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Jurnal belum dapat dimuat. Silakan coba lagi nanti.');
}

if ($log === null) {
    http_response_code(404);
    exit('Jurnal tidak ditemukan.');
}

$values = [
    'activity_title' => (string) $log['activity_title'],
    'description' => (string) $log['description'],
    'tanggal' => (new DateTimeImmutable((string) $log['tanggal']))->format('Y-m-d\TH:i'),
];
$errorMessage = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (['activity_title', 'description', 'tanggal'] as $field) {
        if (is_string($_POST[$field] ?? null)) {
            $values[$field] = $_POST[$field];
        }
    }

    $newPhotoFilename = null;
    $removePhoto = isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1';
    try {
        nexoraTechnicianVerifyCsrf();
        $input = nexoraTechnicianValidateLogInput();
        $newPhotoFilename = nexoraTechnicianPhotoUpload($_FILES['photo'] ?? null);
        
        if ($newPhotoFilename !== null) {
            $photoFilename = $newPhotoFilename;
        } elseif ($removePhoto) {
            $photoFilename = null;
        } else {
            $photoFilename = $log['photo_filename'] ?: null;
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE logs
                 SET activity = :activity_title,
                     keterangan = :description,
                     foto = :photo_filename,
                     tanggal = :tanggal
                 WHERE id = :id AND user_id = :user_id'
            );
            $statement->execute([
                'activity_title' => $input['activity_title'],
                'description' => $input['description'],
                'photo_filename' => $photoFilename,
                'tanggal' => $input['tanggal'],
                'id' => (int) $logId,
                'user_id' => (int) $user['id'],
            ]);
        } catch (Throwable $exception) {
            nexoraTechnicianDeletePhoto($newPhotoFilename);
            throw $exception;
        }

        if ($newPhotoFilename !== null || $removePhoto) {
            nexoraTechnicianDeletePhoto($log['photo_filename'] ?: null);
        }

        nexoraTechnicianRedirect('dashboard.php?status=updated');
    } catch (Throwable $exception) {
        error_log('[NEXORA ICT9] Technician log update failed: ' . $exception->getMessage());
        $errorMessage = $exception instanceof PDOException
            ? 'Jurnal gagal diperbarui. Silakan coba lagi.'
            : $exception->getMessage();
    }
}

$csrfToken = nexoraTechnicianCsrfToken();
$currentPhoto = !empty($log['photo_filename'])
    ? '../uploads/' . rawurlencode(basename((string) $log['photo_filename']))
    : null;
?>
<!doctype html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Jurnal | NEXORA ICT9</title>
    <link rel="stylesheet" href="../assets/theme.css">
    <script src="../assets/theme.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/app.js" defer></script>
    <script>tailwind.config={darkMode:'class'};try{if(localStorage.getItem('nexora-theme')==='light')document.documentElement.classList.remove('dark')}catch(error){}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'DM Sans',sans-serif}h1{font-family:'Space Grotesk',sans-serif}</style>
</head>
<body class="nexora-background min-h-screen bg-gray-100 text-gray-900 antialiased dark:bg-slate-900 dark:text-white">
    <header class="border-b border-gray-200 bg-white/80 backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/80"><nav class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3 sm:px-6"><a href="dashboard.php"><strong>NEXORA <span class="text-cyan-700 dark:text-cyan-400">ICT9</span></strong><span class="ml-2 text-xs text-gray-500 dark:text-slate-400">Teknisi</span></a><div class="flex items-center gap-2"><button type="button" data-theme-toggle class="grid size-10 place-items-center rounded-md border border-gray-200 bg-white/80 text-gray-700 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-200" aria-label="Ganti tema" title="Ganti tema"><svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 15.2A8.5 8.5 0 0 1 8.8 4 8.5 8.5 0 1 0 20 15.2Z" stroke="currentColor" stroke-width="1.7" /></svg><svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.7" /><path d="M12 2v2m0 16v2M2 12h2m16 0h2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg></button><a href="dashboard.php" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-semibold dark:border-slate-700">Kembali</a></div></nav></header>
    <main class="mx-auto max-w-4xl px-4 py-9 sm:px-6">
        <div class="mb-6"><p class="text-xs font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-400">Jurnal teknisi</p><h1 class="mt-2 text-3xl font-semibold">Edit aktivitas</h1></div>
        <?php if ($errorMessage !== ''): ?><div class="mb-5 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-200" role="alert"><?= nexoraTechnicianEscape($errorMessage) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="edit.php?id=<?= (int) $logId ?>" class="space-y-5 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:shadow-none sm:p-7">
            <input type="hidden" name="csrf_token" value="<?= nexoraTechnicianEscape(nexoraTechnicianCsrfToken()) ?>">
            <div><label for="activity_title" class="mb-1.5 block text-sm font-semibold">Judul Activity</label><input id="activity_title" name="activity_title" type="text" maxlength="200" required value="<?= nexoraTechnicianEscape($values['activity_title']) ?>" class="w-full rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900"></div>
            <div><label for="description" class="mb-1.5 block text-sm font-semibold">Keterangan pekerjaan</label><textarea id="description" name="description" rows="6" maxlength="60000" required class="w-full resize-y rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm leading-6 outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900"><?= nexoraTechnicianEscape($values['description']) ?></textarea></div>
            <div><label for="tanggal" class="mb-1.5 block text-sm font-semibold">Tanggal &amp; waktu pekerjaan</label><input id="tanggal" name="tanggal" type="datetime-local" required value="<?= nexoraTechnicianEscape($values['tanggal']) ?>" class="w-full rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></div>
            <div>
                <label for="photo" class="mb-1.5 block text-sm font-semibold">Ganti foto dokumentasi</label>
                <?php if ($currentPhoto): ?>
                    <div class="mb-3 space-y-2">
                        <img src="<?= nexoraTechnicianEscape($currentPhoto) ?>" alt="Foto dokumentasi saat ini" class="max-h-48 rounded-md border border-gray-200 object-cover dark:border-slate-700">
                        <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-semibold text-red-600 hover:text-red-700 dark:text-red-400">
                            <input type="checkbox" name="remove_photo" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-900">
                            <span>Hapus foto saat ini (jadikan tanpa foto)</span>
                        </label>
                    </div>
                <?php else: ?>
                    <span class="mb-3 inline-flex rounded-full border border-gray-200 bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">Tanpa Foto</span>
                <?php endif; ?>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full cursor-pointer rounded-md border border-gray-300 bg-white text-sm text-gray-700 file:mr-4 file:border-0 file:bg-gray-100 file:px-4 file:py-2.5 file:font-semibold dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:file:bg-slate-700 dark:file:text-slate-100">
                <p class="mt-1.5 text-xs text-gray-500 dark:text-slate-400">Opsional. JPG, PNG, atau WEBP, maksimal 2 MB.</p>
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-5 dark:border-slate-700 sm:flex-row sm:justify-end"><a href="dashboard.php" class="rounded-md border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold dark:border-slate-600">Batal</a><button type="submit" class="rounded-md bg-cyan-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-cyan-800 dark:bg-cyan-600 dark:hover:bg-cyan-500">Simpan perubahan</button></div>
        </form>
    </main>
</body>
</html>