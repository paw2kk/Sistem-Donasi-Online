<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Admin tidak boleh berdonasi
if (($_SESSION['user_role'] ?? '') === 'admin') {
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$amount      = (int)($_POST['amount'] ?? 0);
$comment     = trim($_POST['comment'] ?? '') ?: 'Bismillah, semoga bermanfaat.';
$campaign_id = (int)($_POST['campaign_id'] ?? 1);
$user_id     = $_SESSION['user_id'];
$user_name   = $_SESSION['user_name'];

// Validate campaign exists and is active
$db = get_db();
$stmt = $db->prepare('SELECT id FROM campaigns WHERE id = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$campaign_id]);
if (!$stmt->fetch()) {
    $_SESSION['donate_error'] = 'Kampanye tidak ditemukan atau sudah tidak aktif.';
    header('Location: index.php');
    exit;
}

if ($amount < 1000) {
    $_SESSION['donate_error'] = 'Nominal donasi minimal Rp 1.000.';
    header('Location: index.php');
    exit;
}

// Validate file upload
$allowed_types = ['image/jpeg', 'image/png'];
$file = $_FILES['bukti'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['donate_error'] = 'Bukti transfer wajib diunggah.';
    header('Location: index.php');
    exit;
}

if (!in_array($file['type'], $allowed_types)) {
    $_SESSION['donate_error'] = 'Format file harus .jpg atau .png.';
    header('Location: index.php');
    exit;
}

// Save file
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'bukti_' . time() . '_' . uniqid() . '.' . strtolower($ext);
move_uploaded_file($file['tmp_name'], $upload_dir . $filename);

// Save to DB
$db->beginTransaction();
try {
    $db->prepare(
        'INSERT INTO donations (campaign_id, user_id, amount, comment, bukti_file, status) VALUES (?,?,?,?,?,?)'
    )->execute([$campaign_id, $user_id, $amount, $comment, $filename, 'Approved']);
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    error_log('Donate error: ' . $e->getMessage());
    $_SESSION['donate_error'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
    header('Location: index.php');
    exit;
}

$_SESSION['donate_success'] = "Terima kasih, $user_name! Donasi sebesar Rp "
    . number_format($amount, 0, ',', '.') . " telah terekam.";

header('Location: index.php');
exit;