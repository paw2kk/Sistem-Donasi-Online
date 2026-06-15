<?php
session_start();
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['user_role'] === 'admin' ? 'admin.php' : 'index.php'));
    exit;
}

$error = '';
$login_as = $_GET['as'] ?? 'user'; // 'user' or 'admin'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_as = $_POST['login_as'] ?? 'user';

    if (empty($email) || empty($password)) {
        $error = 'Masukkan email dan password terlebih dahulu.';
    } else {
        $db   = get_db();
        $stmt = $db->prepare('SELECT id, name, password_hash, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Role check
            if ($login_as === 'admin' && $user['role'] !== 'admin') {
                $error = 'Akun ini tidak memiliki akses admin.';
            } elseif ($login_as === 'user' && $user['role'] === 'admin') {
                $error = 'Gunakan portal Admin untuk login sebagai administrator.';
            } else {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'index.php'));
                exit;
            }
        } else {
            $error = 'Email atau password salah. Silakan coba lagi.';
        }
    }
}
$is_admin_mode = ($login_as === 'admin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $is_admin_mode ? 'Admin Login' : 'Masuk Akun' ?> — ResponBencana</title>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--red:#B91C1C;--red-dk:#7F1D1D;--red-lt:#DC2626;--navy:#1E2D5A;--navy-dk:#111827;--navy-lt:#2E4080;--cream:#FDF6EC;--gold:#D97706;}
*{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:'Source Sans 3',sans-serif;background:var(--cream);color:var(--navy-dk);min-height:100vh;display:flex;flex-direction:column;}
.nav{background:var(--navy-dk);border-bottom:3px solid var(--red);}
.nav-inner{max-width:1200px;margin:0 auto;padding:0 2rem;height:60px;display:flex;align-items:center;justify-content:space-between;}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-logo-icon{width:34px;height:34px;background:var(--red);border-radius:6px;display:flex;align-items:center;justify-content:center;}
.nav-logo-icon svg{width:18px;height:18px;stroke:white;stroke-width:2;}
.nav-logo-text{font-family:'Oswald',sans-serif;font-size:18px;letter-spacing:.04em;color:white;font-weight:600;}
.nav-logo-text span{color:var(--red-lt);}
.auth-wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:3rem 2rem;}
.auth-card{width:100%;max-width:440px;background:white;border-radius:8px;border:1px solid rgba(30,45,90,.1);box-shadow:0 8px 32px rgba(30,45,90,.1);overflow:hidden;}

/* Role Toggle */
.role-toggle{display:grid;grid-template-columns:1fr 1fr;border-bottom:3px solid var(--red);}
.role-tab{display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;font-family:'Oswald',sans-serif;font-size:14px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;text-decoration:none;background:var(--navy-dk);color:rgba(255,255,255,.45);cursor:pointer;transition:all .2s;border:none;}
.role-tab:first-child{border-right:1px solid rgba(255,255,255,.08);}
.role-tab.active{background:var(--navy);color:white;}
.role-tab svg{width:16px;height:16px;stroke:currentColor;flex-shrink:0;}

.auth-card-header{background:var(--navy);padding:1.75rem 2rem;border-bottom:1px solid rgba(255,255,255,.08);}
.auth-title{font-family:'Oswald',sans-serif;font-size:1.6rem;font-weight:700;color:white;text-transform:uppercase;letter-spacing:.04em;}
.auth-subtitle{font-size:13px;color:rgba(255,255,255,.45);margin-top:4px;}
.admin-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(217,119,6,.2);border:1px solid rgba(217,119,6,.4);color:#FCD34D;font-size:11px;font-family:'Oswald',sans-serif;letter-spacing:.06em;text-transform:uppercase;padding:3px 10px;border-radius:3px;margin-bottom:.75rem;}
.auth-body{padding:2rem;}
.form-group{margin-bottom:.9rem;}
.form-label{display:block;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px;}
.form-input{width:100%;padding:9px 13px;font-size:14px;font-family:'Source Sans 3',sans-serif;border:1.5px solid #E5E7EB;border-radius:4px;background:#F9FAFB;color:var(--navy-dk);transition:border-color .2s,box-shadow .2s;outline:none;}
.form-input:focus{border-color:var(--red);box-shadow:0 0 0 3px rgba(185,28,28,.1);background:white;}
.btn-submit{width:100%;padding:13px;background:var(--red);color:white;font-family:'Oswald',sans-serif;font-size:16px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border:none;border-radius:4px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:8px;}
.btn-submit:hover{background:var(--red-lt);transform:translateY(-1px);box-shadow:0 4px 16px rgba(185,28,28,.35);}
.btn-submit.admin-btn{background:#92400E;}
.btn-submit.admin-btn:hover{background:#B45309;}
.error-box{background:#FEF2F2;border:1px solid rgba(185,28,28,.3);border-left:4px solid var(--red);border-radius:4px;padding:10px 14px;font-size:13px;color:var(--red);margin-bottom:1rem;}
.auth-divider{text-align:center;font-size:13px;color:#94a3b8;margin:1rem 0 0;}
.auth-divider a{color:var(--red);font-weight:600;text-decoration:none;}
.auth-divider a:hover{text-decoration:underline;}
.back-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#94a3b8;text-decoration:none;margin-top:1rem;}
.back-link:hover{color:var(--navy);}
footer{border-top:3px solid var(--navy);background:var(--navy-dk);padding:1.25rem 2rem;text-align:center;font-size:12px;color:rgba(255,255,255,.35);letter-spacing:.04em;}
.success-box{background:#F0FDF4;border:1px solid #BBF7D0;border-left:4px solid #16A34A;border-radius:4px;padding:10px 14px;font-size:13px;color:#15803D;margin-bottom:1rem;}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav-inner">
    <a href="index.php" class="nav-logo">
      <div class="nav-logo-icon">
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
      </div>
      <span class="nav-logo-text">Respon<span>Bencana</span></span>
    </a>
  </div>
</nav>

<div class="auth-wrap">
  <div class="auth-card">

    <!-- Role toggle tabs -->
    <div class="role-toggle">
      <a href="login.php?as=user" class="role-tab <?= !$is_admin_mode ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
        Login Donatur
      </a>
      <a href="login.php?as=admin" class="role-tab <?= $is_admin_mode ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
        Login Admin
      </a>
    </div>

    <div class="auth-card-header">
      <?php if ($is_admin_mode): ?>
        <div class="admin-badge">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
          Akses Terbatas
        </div>
      <?php endif; ?>
      <h1 class="auth-title"><?= $is_admin_mode ? 'Panel Administrator' : 'Masuk Akun' ?></h1>
      <p class="auth-subtitle"><?= $is_admin_mode ? 'Login untuk mengelola kampanye & data donasi.' : 'Login agar histori donasi Anda dapat dilacak sistem.' ?></p>
    </div>

    <div class="auth-body">
      <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (isset($_SESSION['register_success'])): ?>
        <div class="success-box"><?= htmlspecialchars($_SESSION['register_success']) ?></div>
        <?php unset($_SESSION['register_success']); ?>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <input type="hidden" name="login_as" value="<?= $is_admin_mode ? 'admin' : 'user' ?>">
        <div class="form-group">
          <label class="form-label">Alamat Email</label>
          <input type="email" name="email" class="form-input" placeholder="email@domain.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-input" placeholder="••••••••" required />
        </div>
        <button type="submit" class="btn-submit <?= $is_admin_mode ? 'admin-btn' : '' ?>">
          <?php if ($is_admin_mode): ?>
            <svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
            Masuk Panel Admin
          <?php else: ?>
            <svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
            Masuk
          <?php endif; ?>
        </button>
      </form>

      <?php if (!$is_admin_mode): ?>
        <p class="auth-divider">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
      <?php endif; ?>
      <div style="text-align:center;">
        <a href="index.php" class="back-link">
          <svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
          Kembali ke halaman utama
        </a>
      </div>
    </div>
  </div>
</div>

<footer>&copy; 2026 Sistem Donasi Darurat Kebencanaan Kolektif &bull; Kelompok 4 &bull; UMY</footer>
</body>
</html>