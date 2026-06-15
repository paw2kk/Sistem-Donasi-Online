<?php
session_start();
require_once __DIR__ . '/config.php';

// Redirect ke index jika sudah login
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$old   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    $old = ['name' => $name, 'email' => $email];

    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'Lengkapi semua data terlebih dahulu.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $db = get_db();

        // Cek apakah email sudah terdaftar
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email ini sudah terdaftar. Silakan login.';
        } else {
            // Simpan user baru
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $ins->execute([$name, $email, $hash]);

            $_SESSION['register_success'] = "Akun berhasil dibuat! Silakan masuk, $name.";
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akun — ResponBencana</title>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --red:    #B91C1C;
  --red-dk: #7F1D1D;
  --red-lt: #DC2626;
  --navy:   #1E2D5A;
  --navy-dk:#111827;
  --navy-lt:#2E4080;
  --cream:  #FDF6EC;
}
*{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{
  font-family:'Source Sans 3',sans-serif;
  background:var(--cream);
  color:var(--navy-dk);
  min-height:100vh;
  display:flex;
  flex-direction:column;
}
.nav{background:var(--navy-dk);border-bottom:3px solid var(--red);}
.nav-inner{max-width:1200px;margin:0 auto;padding:0 2rem;height:60px;display:flex;align-items:center;justify-content:space-between;}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-logo-icon{width:34px;height:34px;background:var(--red);border-radius:6px;display:flex;align-items:center;justify-content:center;}
.nav-logo-icon svg{width:18px;height:18px;stroke:white;stroke-width:2;}
.nav-logo-text{font-family:'Oswald',sans-serif;font-size:18px;letter-spacing:.04em;color:white;font-weight:600;}
.nav-logo-text span{color:var(--red-lt);}

.auth-wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:3rem 2rem;}
.auth-card{
  width:100%;max-width:420px;
  background:white;border-radius:8px;
  border:1px solid rgba(30,45,90,.1);
  box-shadow:0 8px 32px rgba(30,45,90,.1);
  overflow:hidden;
}
.auth-card-header{background:var(--navy);padding:2rem;border-bottom:4px solid var(--red);}
.auth-card-header-logo{display:flex;align-items:center;gap:10px;margin-bottom:1rem;}
.auth-card-header-logo span{font-family:'Oswald',sans-serif;font-size:16px;color:rgba(255,255,255,.7);letter-spacing:.04em;}
.auth-title{font-family:'Oswald',sans-serif;font-size:1.7rem;font-weight:700;color:white;text-transform:uppercase;letter-spacing:.04em;}
.auth-subtitle{font-size:13px;color:rgba(255,255,255,.45);margin-top:4px;}
.auth-body{padding:2rem;}
.form-group{margin-bottom:.9rem;}
.form-label{display:block;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px;}
.form-input{
  width:100%;padding:9px 13px;
  font-size:14px;font-family:'Source Sans 3',sans-serif;
  border:1.5px solid #E5E7EB;border-radius:4px;
  background:#F9FAFB;color:var(--navy-dk);
  transition:border-color .2s,box-shadow .2s;outline:none;
}
.form-input:focus{border-color:var(--red);box-shadow:0 0 0 3px rgba(185,28,28,.1);background:white;}
.btn-submit{
  width:100%;padding:13px;
  background:var(--red);color:white;
  font-family:'Oswald',sans-serif;font-size:16px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  border:none;border-radius:4px;cursor:pointer;
  transition:all .2s;
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-submit:hover{background:var(--red-lt);transform:translateY(-1px);box-shadow:0 4px 16px rgba(185,28,28,.35);}
.error-box{
  background:#FEF2F2;border:1px solid rgba(185,28,28,.3);
  border-left:4px solid var(--red);border-radius:4px;
  padding:10px 14px;font-size:13px;color:var(--red);margin-bottom:1rem;
}
.auth-divider{text-align:center;font-size:13px;color:#94a3b8;margin:1rem 0 0;}
.auth-divider a{color:var(--red);font-weight:600;text-decoration:none;}
.auth-divider a:hover{text-decoration:underline;}

footer{border-top:3px solid var(--navy);background:var(--navy-dk);padding:1.25rem 2rem;text-align:center;font-size:12px;color:rgba(255,255,255,.35);letter-spacing:.04em;}
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
    <div class="auth-card-header">
      <div class="auth-card-header-logo">
        <div class="nav-logo-icon"><svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg></div>
        <span>ResponBencana</span>
      </div>
      <h1 class="auth-title">Daftar Akun</h1>
      <p class="auth-subtitle">Buat akun kemanusiaan untuk melacak setiap donasi Anda.</p>
    </div>
    <div class="auth-body">

      <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="register.php">
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="name" class="form-input" placeholder="Nama Anda"
                 value="<?= htmlspecialchars($old['name'] ?? '') ?>" required />
        </div>
        <div class="form-group">
          <label class="form-label">Alamat Email</label>
          <input type="email" name="email" class="form-input" placeholder="email@gmail.com"
                 value="<?= htmlspecialchars($old['email'] ?? '') ?>" required />
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-input" placeholder="Minimal 6 karakter" required />
        </div>
        <div class="form-group">
          <label class="form-label">Konfirmasi Password</label>
          <input type="password" name="confirm" class="form-input" placeholder="••••••••" required />
        </div>
        <button type="submit" class="btn-submit" style="margin-top:8px;">
          <svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
          Daftar Akun
        </button>
      </form>

      <p class="auth-divider">Sudah punya akun? <a href="login.php">Masuk disini</a></p>
    </div>
  </div>
</div>

<footer>&copy; 2026 Sistem Donasi Darurat Kebencanaan Kolektif &bull; Kelompok 4 &bull; UMY</footer>
</body>
</html>