<?php
// ============================================================
//  db.php — Koneksi Database PDO
//  Include file ini di setiap halaman yang butuh database:
//    require_once __DIR__ . '/db.php';
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'responbencana');
define('DB_USER', 'root');        // ← ganti sesuai user MySQL kamu
define('DB_PASS', '');            // ← ganti sesuai password MySQL kamu
define('DB_PORT', '3306');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // Tampilkan pesan ramah, jangan expose detail koneksi ke user
        error_log('DB connection error: ' . $e->getMessage());
        die('<p style="font-family:sans-serif;color:#B91C1C;padding:2rem;">
             ⚠️ Koneksi database gagal. Periksa konfigurasi db.php dan pastikan MySQL berjalan.
             </p>');
    }

    return $pdo;
}