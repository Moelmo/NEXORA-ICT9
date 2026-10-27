<?php
declare(strict_types=1);

return (static function (): PDO {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME') ?: 'nexora_ict9';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

    $validPort = filter_var(
        $port,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 65535]]
    );

    if ($validPort === false) {
        throw new RuntimeException('DB_PORT harus berupa angka antara 1 dan 65535.');
    }

    if (str_contains($host, ';') || str_contains($host, "\0")) {
        throw new RuntimeException('DB_HOST memiliki format yang tidak valid.');
    }

    if (!preg_match('/\A[a-zA-Z0-9_$-]+\z/', $database)) {
        throw new RuntimeException('DB_NAME memiliki format yang tidak valid.');
    }

    try {
        return new PDO(
            "mysql:host={$host};port={$validPort};dbname={$database};charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );
    } catch (PDOException $exception) {
        error_log('[NEXORA ICT9] Database connection failed: ' . $exception->getMessage());

        throw new RuntimeException(
            'Tidak dapat terhubung ke database. Periksa konfigurasi database.',
            0,
            $exception
        );
    }
})();