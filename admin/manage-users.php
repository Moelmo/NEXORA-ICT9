<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$admin = nexoraAdminRequireUser();
$errorMessage = '';
$formValues = ['full_name' => '', 'username' => ''];
$successMessages = [
    'user-created' => 'Akun teknisi berhasil dibuat.',
    'user-deleted' => 'Akun teknisi berhasil dihapus.',
    'password-reset' => 'Password teknisi berhasil direset.',
];
$status = $_GET['status'] ?? '';
$successMessage = is_string($status) ? ($successMessages[$status] ?? '') : '';

try {
    $pdo = nexoraAdminPdo();
} catch (Throwable $exception) {
    error_log('[NEXORA ICT9] Admin user manager connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Manajemen akun belum tersedia. Silakan coba lagi nanti.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $formValues['full_name'] = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
    $formValues['username'] = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $action = $_POST['action'] ?? '';

    try {
        nexoraAdminVerifyCsrf();

        if ($action === 'create') {
            $password = $_POST['password'] ?? '';
            $nameLength = preg_match_all('/./us', $formValues['full_name']);
            if (
                $nameLength === false
                || $formValues['full_name'] === ''
                || $nameLength > 150
                || !preg_match('/\A[a-zA-Z0-9_.-]{3,50}\z/', $formValues['username'])
                || !is_string($password)
                || strlen($password) < 6
                || strlen($password) > 255
            ) {
                throw new RuntimeException('Isi nama lengkap, username 3-50 karakter, dan password 6-255 byte.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO users (nama_lengkap, username, password, role)
                 VALUES (:full_name, :username, :password, \'teknisi\')'
            );
            $statement->execute([
                'full_name' => $formValues['full_name'],
                'username' => $formValues['username'],
                'password' => $password,
            ]);

            nexoraAdminRedirect('manage-users.php?status=user-created');
        }

        if ($action === 'delete') {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($userId === false || (int) $userId === (int) $admin['id']) {
                throw new RuntimeException('Akun teknisi tidak valid.');
            }

            $find = $pdo->prepare(
                'SELECT id FROM users WHERE id = :id AND role = \'teknisi\' LIMIT 1'
            );
            $find->execute(['id' => (int) $userId]);
            if (!$find->fetch()) {
                throw new RuntimeException('Akun teknisi tidak ditemukan.');
            }

            $logCount = $pdo->prepare('SELECT COUNT(*) FROM logs WHERE user_id = :user_id');
            $logCount->execute(['user_id' => (int) $userId]);
            if ((int) $logCount->fetchColumn() > 0) {
                throw new RuntimeException('Akun ini masih memiliki jurnal. Hapus atau pindahkan jurnalnya terlebih dahulu.');
            }

            $delete = $pdo->prepare('DELETE FROM users WHERE id = :id AND role = \'teknisi\'');
            $delete->execute(['id' => (int) $userId]);
            nexoraAdminRedirect('manage-users.php?status=user-deleted');
        }

        if ($action === 'reset_password') {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $newPassword = $_POST['new_password'] ?? '';
            if ($userId === false || (int) $userId === (int) $admin['id']) {
                throw new RuntimeException('Akun teknisi tidak valid.');
            }

            if (!is_string($newPassword) || strlen($newPassword) < 6 || strlen($newPassword) > 255) {
                throw new RuntimeException('Password baru minimal 6 karakter.');
            }

            $update = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id AND role = \'teknisi\'');
            $update->execute([
                'password' => $newPassword,
                'id' => (int) $userId,
            ]);

            nexoraAdminRedirect('manage-users.php?status=password-reset');
        }

        throw new RuntimeException('Aksi tidak dikenal.');
    } catch (PDOException $exception) {
        error_log('[NEXORA ICT9] Admin user update failed: ' . $exception->getMessage());
        $errorMessage = $exception->getCode() === '23000'
            ? 'Username sudah digunakan.'
            : 'Perubahan akun gagal disimpan. Silakan coba lagi.';
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}

try {
    $statement = $pdo->prepare(
        'SELECT users.id, users.nama_lengkap AS full_name, users.username, users.created_at,
                COUNT(logs.id) AS log_count
         FROM users
         LEFT JOIN logs ON logs.user_id = users.id
         WHERE users.role = :role
         GROUP BY users.id, users.nama_lengkap, users.username, users.created_at
         ORDER BY users.nama_lengkap ASC'
    );
    $statement->execute(['role' => 'teknisi']);
    $technicians = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('[NEXORA ICT9] Admin technician list failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Daftar teknisi belum dapat dimuat. Silakan coba lagi nanti.');
}

$csrfToken = nexoraAdminCsrfToken();
?>
<!doctype html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>Manajemen Teknisi | NEXORA ICT9</title>
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
    <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/90 backdrop-blur-xl dark:border-slate-700 dark:bg-slate-900/90">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8" aria-label="Navigasi admin">
            <a href="dashboard.php"><strong>NEXORA <span class="text-cyan-700 dark:text-cyan-400">ICT9</span></strong><span class="ml-2 text-xs text-gray-500 dark:text-slate-400">Admin</span></a>
            <div class="flex items-center gap-2"><button type="button" data-theme-toggle class="grid size-10 place-items-center rounded-md border border-gray-200 bg-white/80 text-gray-700 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-200" aria-label="Ganti tema" title="Ganti tema"><svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 15.2A8.5 8.5 0 0 1 8.8 4 8.5 8.5 0 1 0 20 15.2Z" stroke="currentColor" stroke-width="1.7" /></svg><svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.7" /><path d="M12 2v2m0 16v2M2 12h2m16 0h2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg></button><a href="dashboard.php" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-semibold dark:border-slate-700">Dashboard</a><a href="../logout.php" class="rounded-md bg-cyan-700 px-3 py-2 text-sm font-bold text-white dark:bg-cyan-600">Keluar</a></div>
        </nav>
    </header>
    <main class="mx-auto max-w-7xl px-4 pb-14 pt-9 sm:px-6 lg:px-8">
        <section class="mb-7 border-b border-gray-200 pb-7 dark:border-slate-700"><p class="text-xs font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-400">Administrasi</p><h1 class="mt-2 text-3xl font-semibold">Manajemen teknisi</h1><p class="mt-2 text-sm text-gray-600 dark:text-slate-400">Buat dan hapus akun teknisi.</p></section>
        <?php if ($successMessage !== ''): ?><div class="mb-5 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200" role="status"><?= nexoraAdminEscape($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="mb-5 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-200" role="alert"><?= nexoraAdminEscape($errorMessage) ?></div><?php endif; ?>

        <section class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:shadow-none sm:p-6" aria-labelledby="new-user-heading">
            <h2 id="new-user-heading" class="text-lg font-semibold">Tambah akun teknisi</h2>
            <form method="post" action="manage-users.php" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <input type="hidden" name="csrf_token" value="<?= nexoraAdminEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="create">
                <div><label for="full_name" class="mb-1.5 block text-sm font-semibold">Nama lengkap</label><input id="full_name" name="full_name" type="text" required maxlength="150" value="<?= nexoraAdminEscape($formValues['full_name']) ?>" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-cyan-600 dark:border-slate-600 dark:bg-slate-900"></div>
                <div><label for="username" class="mb-1.5 block text-sm font-semibold">Username</label><input id="username" name="username" type="text" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" value="<?= nexoraAdminEscape($formValues['username']) ?>" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-cyan-600 dark:border-slate-600 dark:bg-slate-900"><p class="mt-1 text-xs text-gray-500 dark:text-slate-500">Huruf, angka, titik, garis bawah, atau tanda hubung.</p></div>
                <div><label for="password" class="mb-1.5 block text-sm font-semibold">Password awal</label><input id="password" name="password" type="password" required minlength="6" maxlength="255" value="nama123" autocomplete="new-password" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-cyan-600 dark:border-slate-600 dark:bg-slate-900"><p class="mt-1 text-xs text-gray-500 dark:text-slate-500">Default nama123; ganti untuk akun yang lebih aman.</p></div>
                <div class="flex items-end"><button type="submit" class="w-full rounded-md bg-cyan-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-cyan-800 dark:bg-cyan-600 dark:hover:bg-cyan-500">Buat akun</button></div>
            </form>
        </section>

        <section aria-labelledby="technician-list-heading">
            <div class="mb-4 flex items-center justify-between"><h2 id="technician-list-heading" class="text-lg font-semibold">Akun teknisi</h2><span class="text-xs text-gray-500 dark:text-slate-500"><?= count($technicians) ?> akun</span></div>
            <?php if ($technicians === []): ?>
                <div class="rounded-lg border border-gray-200 bg-white px-5 py-10 text-center text-sm text-gray-600 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:shadow-none">Belum ada akun teknisi.</div>
            <?php else: ?>
                <div class="hidden overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:shadow-none md:block">
                    <table class="w-full text-left"><thead class="border-b border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-900/70"><tr class="text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-slate-400"><th class="px-4 py-3.5">Nama</th><th class="px-4 py-3.5">Username</th><th class="px-4 py-3.5">Jumlah jurnal</th><th class="px-4 py-3.5">Dibuat</th><th class="px-4 py-3.5">Tindakan</th></tr></thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700"><?php foreach ($technicians as $technician): ?><tr><td class="px-4 py-4 text-sm font-semibold"><?= nexoraAdminEscape($technician['full_name']) ?></td><td class="px-4 py-4 text-sm text-gray-600 dark:text-slate-400"><?= nexoraAdminEscape($technician['username']) ?></td><td class="px-4 py-4 text-sm text-gray-600 dark:text-slate-400"><?= (int) $technician['log_count'] ?></td><td class="px-4 py-4 text-sm text-gray-600 dark:text-slate-400"><?= nexoraAdminEscape(nexoraAdminFormatDate($technician['created_at'])) ?></td><td class="px-4 py-4">
    <div class="flex items-center gap-3">
        <button type="button" data-reset-user-id="<?= (int) $technician['id'] ?>" data-reset-user-name="<?= nexoraAdminEscape($technician['full_name']) ?>" class="btn-reset-password text-sm font-semibold text-cyan-700 hover:underline dark:text-cyan-400">Reset password</button>
        <span class="text-gray-300 dark:text-slate-600">|</span>
        <form method="post" action="manage-users.php" data-confirm-message="Hapus akun teknisi ini? Akun dengan jurnal tidak dapat dihapus.">
            <input type="hidden" name="csrf_token" value="<?= nexoraAdminEscape($csrfToken) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="<?= (int) $technician['id'] ?>">
            <button type="submit" class="text-sm font-semibold text-red-700 hover:underline dark:text-red-400">Hapus akun</button>
        </form>
    </div>
</td></tr><?php endforeach; ?></tbody>
                    </table>
                </div>
                <div class="space-y-3 md:hidden"><?php foreach ($technicians as $technician): ?><article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:shadow-none"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="break-words font-semibold"><?= nexoraAdminEscape($technician['full_name']) ?></h3><p class="mt-1 text-sm text-gray-600 dark:text-slate-400">@<?= nexoraAdminEscape($technician['username']) ?></p></div><span class="shrink-0 text-xs text-gray-500 dark:text-slate-500"><?= (int) $technician['log_count'] ?> jurnal</span></div><div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3 dark:border-slate-700">
    <button type="button" data-reset-user-id="<?= (int) $technician['id'] ?>" data-reset-user-name="<?= nexoraAdminEscape($technician['full_name']) ?>" class="btn-reset-password text-sm font-semibold text-cyan-700 hover:underline dark:text-cyan-400">Reset password</button>
    <form method="post" action="manage-users.php" data-confirm-message="Hapus akun teknisi ini? Akun dengan jurnal tidak dapat dihapus.">
        <input type="hidden" name="csrf_token" value="<?= nexoraAdminEscape($csrfToken) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" value="<?= (int) $technician['id'] ?>">
        <button type="submit" class="text-sm font-semibold text-red-700 hover:underline dark:text-red-400">Hapus akun</button>
    </form>
</div></article><?php endforeach; ?></div>
            <?php endif; ?>
        </section>
    </main>

    <dialog id="reset-password-dialog" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl border border-gray-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
        <form method="post" action="manage-users.php" class="p-5 sm:p-6">
            <input type="hidden" name="csrf_token" value="<?= nexoraAdminEscape($csrfToken) ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" id="reset-user-id" name="user_id" value="">
            <h2 class="font-display text-lg font-semibold">Reset Password Teknisi</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">Atur password baru untuk <strong id="reset-user-name"></strong>.</p>
            <div class="mt-4">
                <label for="reset-new-password" class="mb-1.5 block text-sm font-semibold">Password Baru</label>
                <input id="reset-new-password" name="new_password" type="password" required minlength="6" maxlength="255" class="w-full rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Minimal 6 karakter.</p>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" data-modal-close class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold hover:bg-gray-50 dark:border-slate-600 dark:hover:bg-slate-700">Batal</button>
                <button type="submit" class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-800 dark:bg-cyan-600 dark:hover:bg-cyan-500">Simpan Password</button>
            </div>
        </form>
    </dialog>

    <script>
        document.querySelectorAll('.btn-reset-password').forEach(button => {
            button.addEventListener('click', () => {
                const dialog = document.getElementById('reset-password-dialog');
                document.getElementById('reset-user-id').value = button.dataset.resetUserId;
                document.getElementById('reset-user-name').textContent = button.dataset.resetUserName;
                document.getElementById('reset-new-password').value = '';
                dialog.showModal();
            });
        });
    </script>
</body>
</html>