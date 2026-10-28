<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

$sessionUser = $_SESSION['user'] ?? null;
$role = is_array($sessionUser) ? ($sessionUser['role'] ?? '') : '';
if ($role === 'admin') {
    require __DIR__ . '/admin/_bootstrap.php';
    nexoraAdminRequireUser();
} elseif (in_array($role, ['teknisi', 'technician'], true)) {
    require __DIR__ . '/technician/_bootstrap.php';
    nexoraTechnicianRequireUser();
} else {
    header('Location: login.php', true, 303);
    exit;
}
$dashboardPath = $role === 'admin' ? 'admin/dashboard.php' : 'technician/dashboard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

$parseDate = static function (mixed $value): ?DateTimeImmutable {
    if (!is_string($value) || $value === '') {
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

$startDate = $parseDate($_GET['start_date'] ?? null);
$endDate = $parseDate($_GET['end_date'] ?? null);
if ($startDate === null || $endDate === null || $startDate > $endDate) {
    header('Location: ' . $dashboardPath . '?status=export-invalid', true, 303);
    exit;
}

$start = $startDate->format('Y-m-d 00:00:00');
$endExclusive = $endDate->modify('+1 day')->format('Y-m-d 00:00:00');

try {
    $pdo = require __DIR__ . '/config/database.php';
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Koneksi database tidak tersedia.');
    }

    if ($role === 'admin') {
        $statement = $pdo->prepare(
                'SELECT users.nama_lengkap AS technician_name, logs.tanggal,
                    logs.activity AS activity_title, logs.team, logs.keterangan AS description,
                    logs.foto AS photo_filename
             FROM logs
             INNER JOIN users ON users.id = logs.user_id
             WHERE logs.tanggal >= :start_date AND logs.tanggal < :end_date
             ORDER BY logs.tanggal ASC'
        );
        $statement->execute(['start_date' => $start, 'end_date' => $endExclusive]);
    } else {
        $statement = $pdo->prepare(
                'SELECT users.nama_lengkap AS technician_name, logs.tanggal,
                    logs.activity AS activity_title, logs.team, logs.keterangan AS description,
                    logs.foto AS photo_filename
             FROM logs
             INNER JOIN users ON users.id = logs.user_id
             WHERE logs.user_id = :user_id
               AND logs.tanggal >= :start_date
               AND logs.tanggal < :end_date
             ORDER BY logs.tanggal ASC'
        );
        $statement->execute([
            'user_id' => (int) $sessionUser['id'],
            'start_date' => $start,
            'end_date' => $endExclusive,
        ]);
    }

    $logs = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('[NEXORA ICT9] Excel export failed: ' . $exception->getMessage());
    header('Location: ' . $dashboardPath . '?status=export-error', true, 303);
    exit;
}

$safeCsvValue = static function (mixed $value): string {
    $value = (string) $value;
    if (preg_match('/\A[\t\r ]*[=+@-]/', $value) === 1) {
        return "'" . $value;
    }

    return $value;
};

$filename = sprintf(
    'nexora-jurnal-%s-sd-%s.csv',
    $startDate->format('Ymd'),
    $endDate->format('Ymd')
);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Nama Teknisi', 'Tanggal & Waktu', 'Activity', 'Tim / Rekan', 'Keterangan', 'Foto'], ';', '"', '\\');
foreach ($logs as $log) {
    fputcsv($output, [
        $safeCsvValue($log['technician_name']),
        $safeCsvValue($log['tanggal']),
        $safeCsvValue($log['activity_title']),
        $safeCsvValue($log['team'] ?? ''),
        $safeCsvValue($log['description']),
        $safeCsvValue($log['photo_filename'] ?? ''),
    ], ';', '"', '\\');
}
fclose($output);