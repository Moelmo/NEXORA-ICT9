<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$admin = nexoraAdminRequireUser();
$errorMessage = '';
$successMessage = '';

if (($_GET['status'] ?? '') === 'password-changed') {
    $successMessage = 'Password admin berhasil diperbarui.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    try {
        nexoraAdminVerifyCsrf();

        if (!is_string($currentPassword) || $currentPassword === '') {
            throw new RuntimeException('Password saat ini wajib diisi.');
        }

        if (!is_string($newPassword) || strlen($newPassword) < 6 || strlen($newPassword) > 255) {
            throw new RuntimeException('Password baru minimal 6 karakter dan maksimal 255 byte.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('Konfirmasi password baru tidak cocok.');
        }

        $pdo = nexoraAdminPdo();
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $admin['id']]);
        $existing = $stmt->fetch();

        if (!is_array($existing) || !hash_equals((string) $existing['password'], $currentPassword)) {
            throw new RuntimeException('Password saat ini tidak sesuai.');
        }

        $update = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
        $update->execute([
            'password' => $newPassword,
            'id' => (int) $admin['id'],
        ]);

        nexoraAdminRedirect('change-password.php?status=password-changed');
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}

$csrfToken = nexoraAdminCsrfToken();
?>
<!doctype html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ganti Password | Admin NEXORA ICT9</title>
    <link rel="stylesheet" href="../assets/theme.css">
    <script src="../assets/theme.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
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
    <style>body{font-family:'DM Sans',sans-serif}h1,h2,.display-font{font-family:'Space Grotesk',sans-serif}</style>
</head>
<body class="nexora-background min-h-screen bg-gray-100 text-gray-900 antialiased dark:bg-slate-900 dark:text-white">
    <header class="border-b border-gray-200 bg-white/80 backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/80">
        <nav class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3 sm:px-6">
            <a href="dashboard.php"><strong>NEXORA <span class="text-cyan-700 dark:text-cyan-400">ICT9</span></strong><span class="ml-2 text-xs text-gray-500 dark:text-slate-400">Admin</span></a>
            <div class="flex items-center gap-2">
                <button type="button" data-theme-toggle class="grid size-10 place-items-center rounded-md border border-gray-200 bg-white/80 text-gray-700 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-200" aria-label="Ganti tema" title="Ganti tema">
                    <svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 15.2A8.5 8.5 0 0 1 8.8 4 8.5 8.5 0 1 0 20 15.2Z" stroke="currentColor" stroke-width="1.7" /></svg>
                    <svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.7" /><path d="M12 2v2m0 16v2M2 12h2m16 0h2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>
                </button>
                <a href="dashboard.php" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-semibold hover:bg-gray-50 dark:border-slate-700 dark:hover:bg-slate-800">Kembali</a>
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-xl px-4 py-9 sm:px-6">
        <div class="mb-6">
            <p class="text-xs font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-400">Keamanan akun</p>
            <h1 class="mt-2 text-3xl font-semibold">Ganti Password Admin</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-slate-400">Perbarui kata sandi login akun administrator Anda.</p>
        </div>

        <?php if ($successMessage !== ''): ?>
            <div class="mb-5 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200" role="status"><?= nexoraAdminEscape($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
            <div class="mb-5 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-200" role="alert"><?= nexoraAdminEscape($errorMessage) ?></div>
        <?php endif; ?>

        <form method="post" action="change-password.php" class="space-y-5 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:shadow-none sm:p-7">
            <input type="hidden" name="csrf_token" value="<?= nexoraAdminEscape($csrfToken) ?>">
            <div>
                <label for="current_password" class="mb-1.5 block text-sm font-semibold">Password Saat Ini</label>
                <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="w-full rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </div>
            <div>
                <label for="new_password" class="mb-1.5 block text-sm font-semibold">Password Baru</label>
                <input id="new_password" name="new_password" type="password" required minlength="6" maxlength="255" autocomplete="new-password" class="w-full rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Minimal 6 karakter.</p>
            </div>
            <div>
                <label for="confirm_password" class="mb-1.5 block text-sm font-semibold">Ulangi Password Baru</label>
                <input id="confirm_password" name="confirm_password" type="password" required minlength="6" maxlength="255" autocomplete="new-password" class="w-full rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-5 dark:border-slate-700 sm:flex-row sm:justify-end">
                <a href="dashboard.php" class="rounded-md border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold hover:bg-gray-50 dark:border-slate-600 dark:hover:bg-slate-700">Batal</a>
                <button type="submit" class="rounded-md bg-cyan-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-cyan-800 dark:bg-cyan-600 dark:hover:bg-cyan-500">Simpan Password</button>
            </div>
        </form>
    </main>
</body>
</html>
