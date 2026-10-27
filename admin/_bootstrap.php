<?php
declare(strict_types=1);

function nexoraAdminRequireUser(): array
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

    if (($user['role'] ?? '') !== 'admin') {
        if (in_array(($user['role'] ?? ''), ['teknisi', 'technician'], true)) {
            header('Location: ../technician/dashboard.php', true, 303);
        } else {
            header('Location: ../login.php', true, 303);
        }
        exit;
    }

    return $user;
}

function nexoraAdminPdo(): PDO
{
    $pdo = require __DIR__ . '/../config/database.php';
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Konfigurasi database tidak menghasilkan koneksi PDO.');
    }

    return $pdo;
}

function nexoraAdminEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function nexoraAdminCsrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function nexoraAdminVerifyCsrf(): void
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

function nexoraAdminFormatDate(mixed $value): string
{
    try {
        return (new DateTimeImmutable((string) $value))->format('d M Y, H:i');
    } catch (Throwable $exception) {
        return 'Waktu tidak tersedia';
    }
}

function nexoraAdminFormatMonth(mixed $value): string
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

function nexoraAdminParseDate(mixed $value): string
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

function nexoraAdminValidateLogInput(): array
{
    $title = $_POST['activity_title'] ?? '';
    $description = $_POST['description'] ?? '';
    if (!is_string($title) || !is_string($description)) {
        throw new RuntimeException('Isi formulir tidak valid.');
    }

    $title = trim($title);
    $description = trim($description);
    $titleCharacterCount = preg_match_all('/./us', $title);
    if ($titleCharacterCount === false || $title === '' || $titleCharacterCount > 200) {
        throw new RuntimeException('Judul wajib diisi dan maksimal 200 karakter.');
    }
    if ($description === '' || strlen($description) > 60000) {
        throw new RuntimeException('Keterangan wajib diisi dan tidak boleh melebihi 60.000 byte.');
    }

    return [
        'activity_title' => $title,
        'description' => $description,
        'tanggal' => nexoraAdminParseDate($_POST['tanggal'] ?? ''),
    ];
}

function nexoraAdminUploadPhoto(?array $upload): ?string
{
    if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload foto gagal. Silakan coba kembali.');
    }

    $temporaryPath = $upload['tmp_name'] ?? '';
    $actualSize = is_string($temporaryPath) ? filesize($temporaryPath) : false;
    if (
        !is_string($temporaryPath)
        || !is_uploaded_file($temporaryPath)
        || $actualSize === false
        || $actualSize < 1
        || $actualSize > 2 * 1024 * 1024
    ) {
        throw new RuntimeException('Foto harus berukuran maksimal 2 MB.');
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
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

function nexoraAdminDeletePhoto(?string $filename): void
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

function nexoraAdminRedirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}