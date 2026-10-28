<?php
declare(strict_types=1);

function nexoraTechnicianRequireUser(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
        ]);
    }

    $user = $_SESSION['user'] ?? null;
    if (!is_array($user) || !isset($user['id']) || !is_numeric($user['id'])) {
        header('Location: ../login.php', true, 303);
        exit;
    }

    if (!in_array(($user['role'] ?? ''), ['teknisi', 'technician'], true)) {
        if (($user['role'] ?? '') === 'admin') {
            header('Location: ../admin/dashboard.php', true, 303);
        } else {
            header('Location: ../login.php', true, 303);
        }
        exit;
    }

    return $user;
}

function nexoraTechnicianPdo(): PDO
{
    $pdo = require __DIR__ . '/../config/database.php';
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Konfigurasi database tidak menghasilkan koneksi PDO.');
    }

    return $pdo;
}

function nexoraTechnicianEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function nexoraTechnicianCsrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function nexoraTechnicianVerifyCsrf(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($submittedToken)
        || !is_string($sessionToken)
        || !hash_equals($sessionToken, $submittedToken)
    ) {
        throw new RuntimeException('Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.');
    }
}

function nexoraTechnicianOwnedLog(PDO $pdo, int $logId, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, user_id, activity AS activity_title, team, keterangan AS description,
            foto AS photo_filename, tanggal
         FROM logs
         WHERE id = :id AND user_id = :user_id
         LIMIT 1'
    );
    $statement->execute(['id' => $logId, 'user_id' => $userId]);
    $log = $statement->fetch();

    return is_array($log) ? $log : null;
}

function nexoraTechnicianPhotoUpload(?array $upload): ?string
{
    if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload foto gagal. Silakan coba kembali.');
    }

    $temporaryPath = $upload['tmp_name'] ?? '';
    if (
        !is_string($temporaryPath)
        || !is_uploaded_file($temporaryPath)
        || !isset($upload['size'])
        || (int) $upload['size'] < 1
        || (int) $upload['size'] > 2 * 1024 * 1024
    ) {
        throw new RuntimeException('Foto harus berukuran maksimal 2 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($temporaryPath);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!is_string($mimeType) || !isset($extensions[$mimeType])) {
        throw new RuntimeException('Format foto harus JPG, PNG, atau WEBP.');
    }

    $uploadDirectory = __DIR__ . '/../uploads';
    if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {
        throw new RuntimeException('Folder upload belum tersedia atau tidak dapat ditulis.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    if (!move_uploaded_file($temporaryPath, $uploadDirectory . '/' . $filename)) {
        throw new RuntimeException('Foto gagal disimpan. Silakan coba kembali.');
    }

    return $filename;
}

function nexoraTechnicianDeletePhoto(?string $filename): void
{
    if ($filename === null || $filename === '' || basename($filename) !== $filename) {
        return;
    }

    $uploadDirectory = realpath(__DIR__ . '/../uploads');
    if ($uploadDirectory === false) {
        return;
    }

    $photoPath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
    if (is_file($photoPath) && !is_link($photoPath)) {
        unlink($photoPath);
    }
}

function nexoraTechnicianFormatDate(mixed $value): string
{
    try {
        return (new DateTimeImmutable((string) $value))->format('d M Y, H:i');
    } catch (Throwable $exception) {
        return 'Waktu tidak tersedia';
    }
}

function nexoraTechnicianFormatMonth(mixed $value): string
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

function nexoraTechnicianParseDate(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        throw new RuntimeException('Tanggal pekerjaan wajib diisi.');
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (
        $date === false
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d\TH:i') !== $value
    ) {
        throw new RuntimeException('Tanggal pekerjaan tidak valid.');
    }

    return $date->format('Y-m-d H:i:s');
}

function nexoraTechnicianValidateLogInput(): array
{
    $title = $_POST['activity_title'] ?? '';
    $selectedMembers = $_POST['team_members'] ?? [];
    $customMembers = $_POST['team_custom'] ?? '';
    $description = $_POST['description'] ?? '';

    if (!is_string($title) || !is_string($description)) {
        throw new RuntimeException('Isi formulir tidak valid.');
    }

    $members = [];
    if (is_array($selectedMembers)) {
        foreach ($selectedMembers as $m) {
            if (is_string($m) && trim($m) !== '') {
                $members[] = trim($m);
            }
        }
    }

    if (is_string($customMembers) && trim($customMembers) !== '') {
        $extraList = preg_split('/[\r\n,]+/', $customMembers);
        if (is_array($extraList)) {
            foreach ($extraList as $extra) {
                $extraTrimmed = trim($extra);
                if ($extraTrimmed !== '') {
                    $members[] = $extraTrimmed;
                }
            }
        }
    }

    // Unikkan anggota
    $members = array_values(array_unique($members));
    $teamString = $members !== [] ? implode("\n", $members) : null;

    $title = trim($title);
    $description = trim($description);
    $titleCharacterCount = preg_match_all('/./us', $title);

    if ($titleCharacterCount === false || $title === '' || $titleCharacterCount > 200) {
        throw new RuntimeException('Judul wajib diisi dan maksimal 200 karakter.');
    }

    if ($teamString !== null && strlen($teamString) > 5000) {
        throw new RuntimeException('Daftar anggota tim terlalu panjang.');
    }

    if ($description === '' || strlen($description) > 60000) {
        throw new RuntimeException('Keterangan wajib diisi dan tidak boleh melebihi 60.000 byte.');
    }

    return [
        'activity_title' => $title,
        'team' => $teamString,
        'description' => $description,
        'tanggal' => nexoraTechnicianParseDate($_POST['tanggal'] ?? ''),
    ];
}

function nexoraTechnicianRedirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}