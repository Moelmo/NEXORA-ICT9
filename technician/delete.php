<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$user = nexoraTechnicianRequireUser();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

try {
    nexoraTechnicianVerifyCsrf();
    $logId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($logId === false) {
        nexoraTechnicianRedirect('dashboard.php?status=not-found');
    }

    $pdo = nexoraTechnicianPdo();
    $log = nexoraTechnicianOwnedLog($pdo, (int) $logId, (int) $user['id']);
    if ($log === null) {
        nexoraTechnicianRedirect('dashboard.php?status=not-found');
    }

    $statement = $pdo->prepare('DELETE FROM logs WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => (int) $logId, 'user_id' => (int) $user['id']]);

    if ($statement->rowCount() === 1) {
        nexoraTechnicianDeletePhoto($log['photo_filename'] ?: null);
    }

    nexoraTechnicianRedirect('dashboard.php?status=deleted');
} catch (Throwable $exception) {
    error_log('[NEXORA ICT9] Technician log deletion failed: ' . $exception->getMessage());
    nexoraTechnicianRedirect('dashboard.php?status=error');
}