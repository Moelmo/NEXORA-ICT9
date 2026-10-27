<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

function escapeHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dashboardForRole(string $role): ?string
{
    return match ($role) {
        'admin' => 'admin/dashboard.php',
        'teknisi' => 'technician/dashboard.php',
        'technician' => 'technician/dashboard.php',
        default => null,
    };
}

$sessionUser = $_SESSION['user'] ?? null;
if (is_array($sessionUser)) {
    $existingDashboard = dashboardForRole((string) ($sessionUser['role'] ?? ''));
    if ($existingDashboard !== null) {
        header('Location: ' . $existingDashboard, true, 303);
        exit;
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$errorMessage = '';
$noticeMessage = isset($_GET['logged_out']) ? 'Anda sudah keluar dari akun.' : '';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (!is_string($username) || !is_string($password) || trim($username) === '' || $password === '') {
        $errorMessage = 'Username dan password wajib diisi.';
    } else {
        try {
            $pdo = require __DIR__ . '/config/database.php';
            if (!$pdo instanceof PDO) {
                throw new RuntimeException('Konfigurasi database tidak menghasilkan koneksi PDO.');
            }

            $statement = $pdo->prepare(
                'SELECT id, nama_lengkap AS full_name, username, password, role
                 FROM users
                 WHERE username = :username
                 LIMIT 1'
            );
            $statement->execute(['username' => trim($username)]);
            $user = $statement->fetch();

            if (
                is_array($user)
                && hash_equals((string) $user['password'], $password)
                && ($destination = dashboardForRole((string) $user['role'])) !== null
            ) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'full_name' => (string) $user['full_name'],
                    'username' => (string) $user['username'],
                    'role' => (string) $user['role'],
                ];
                unset($_SESSION['csrf_token']);

                header('Location: ' . $destination, true, 303);
                exit;
            }

            $errorMessage = 'Username atau password tidak sesuai.';
        } catch (Throwable $exception) {
            error_log('[NEXORA ICT9] Login failed: ' . $exception->getMessage());
            $errorMessage = 'Layanan login belum tersedia. Silakan coba lagi nanti.';
        }
    }
}
?>
<!doctype html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>Login | NEXORA ICT9</title>
    <link rel="stylesheet" href="assets/theme.css">
    <script src="assets/theme.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script src="assets/app.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; }
        h1, .display-font { font-family: 'Space Grotesk', sans-serif; }
    </style>
</head>
<body class="nexora-background min-h-screen bg-gray-100 text-gray-900 antialiased dark:bg-slate-900 dark:text-white">
    <div class="pointer-events-none fixed inset-0 -z-0 opacity-50 dark:opacity-40" aria-hidden="true">
        <div class="absolute inset-0 bg-[linear-gradient(rgba(8,145,178,0.045)_1px,transparent_1px),linear-gradient(90deg,rgba(8,145,178,0.045)_1px,transparent_1px)] bg-[size:44px_44px]"></div>
        <div class="absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-cyan-100/70 to-transparent dark:from-cyan-950/30"></div>
    </div>

    <header class="relative z-10 border-b border-gray-200 bg-white/90 dark:border-slate-700 dark:bg-slate-900/90">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8" aria-label="Navigasi utama">
            <a href="index.php" class="min-w-0">
                <span class="display-font block text-sm font-bold">NEXORA <span class="text-cyan-700 dark:text-cyan-400">ICT9</span></span>
                <span class="block text-xs text-gray-500 dark:text-slate-400">Network Operations Journal</span>
            </a>
            <div class="flex items-center gap-2">
                <button type="button" data-theme-toggle class="grid size-10 place-items-center rounded-md border border-gray-200 bg-white/80 text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-200 dark:hover:bg-slate-700" aria-label="Ganti tema" title="Ganti tema"><svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 15.2A8.5 8.5 0 0 1 8.8 4 8.5 8.5 0 1 0 20 15.2Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg><svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.7" /><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 1.42 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg></button>
                <a href="index.php" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Kembali</a>
            </div>
        </nav>
    </header>

    <main class="relative z-10 mx-auto flex min-h-[calc(100vh-4.5rem)] max-w-7xl items-center justify-center px-4 py-12 sm:px-6">
        <section class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:shadow-none sm:p-8" aria-labelledby="login-title">
            <div class="mb-7">
                <p class="mb-2 text-xs font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-400">Akses sistem</p>
                <h1 id="login-title" class="display-font text-2xl font-semibold">Masuk ke NEXORA</h1>
                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-400">Gunakan akun Admin atau Teknisi untuk melanjutkan.</p>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="mb-5 rounded-md border border-red-300 bg-red-50 px-3.5 py-3 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-200" role="alert">
                    <?= escapeHtml($errorMessage) ?>
                </div>
            <?php elseif ($noticeMessage !== ''): ?>
                <div class="mb-5 rounded-md border border-emerald-300 bg-emerald-50 px-3.5 py-3 text-sm text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200" role="status">
                    <?= escapeHtml($noticeMessage) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                <div>
                    <label for="username" class="mb-1.5 block text-sm font-semibold">Username</label>
                    <input id="username" name="username" type="text" autocomplete="username" required maxlength="50" value="<?= escapeHtml(is_string($_POST['username'] ?? null) ? $_POST['username'] : '') ?>" class="w-full rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500 dark:focus:border-cyan-400 dark:focus:ring-cyan-400/20">
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-semibold">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required class="w-full rounded-md border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500 dark:focus:border-cyan-400 dark:focus:ring-cyan-400/20">
                </div>
                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-cyan-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:ring-offset-2 dark:bg-cyan-600 dark:hover:bg-cyan-500 dark:focus:ring-offset-slate-800">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    Login
                </button>
            </form>
        </section>
    </main>
</body>
</html>