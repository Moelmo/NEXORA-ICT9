<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
nexoraAdminRequireUser();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

try {
    nexoraAdminVerifyCsrf();
    $logId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($logId === false) {
        nexoraAdminRedirect('dashboard.php?status=error');
    }

    $pdo = nexoraAdminPdo();
    $find = $pdo->prepare('SELECT foto AS photo_filename FROM logs WHERE id = :id LIMIT 1');
    $find->execute(['id' => (int) $logId]);
    $log = $find->fetch();
    if (!is_array($log)) {
        nexoraAdminRedirect('dashboard.php?status=not-found');
    }

    $delete = $pdo->prepare('DELETE FROM logs WHERE id = :id');
    $delete->execute(['id' => (int) $logId]);
    if ($delete->rowCount() === 1) {
        nexoraAdminDeletePhoto($log['photo_filename'] ?: null);
    }

    nexoraAdminRedirect('dashboard.php?status=deleted');
} catch (Throwable $exception) {
    error_log('[NEXORA ICT9] Admin log deletion failed: ' . $exception->getMessage());
    nexoraAdminRedirect('dashboard.php?status=error');
}