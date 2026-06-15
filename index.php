<?php
session_start();
require_once __DIR__ . '/config.php';

$db = get_db();

// All active campaigns with donation totals
$campaigns = $db->query(
    'SELECT c.*,
            COALESCE(SUM(CASE WHEN d.status="Approved" THEN d.amount ELSE 0 END), 0) AS total_donated,
            COUNT(CASE WHEN d.status="Approved" THEN 1 END) AS donor_count
     FROM campaigns c
     LEFT JOIN donations d ON d.campaign_id = c.id
     WHERE c.is_active = 1
     GROUP BY c.id
     ORDER BY c.created_at ASC'
)->fetchAll();

// Featured campaign (first / id=1 fallback)
$featured = $campaigns[0] ?? null;
$featured_id = $featured ? $featured['id'] : 1;

// All recent donations (last 100)
$donations = $db->query(
    'SELECT d.id, u.name, d.amount, d.comment, d.status, d.created_at, d.campaign_id, c.title AS campaign_title
     FROM donations d
     JOIN users u ON u.id = d.user_id
     JOIN campaigns c ON c.id = d.campaign_id
     WHERE d.status = "Approved"
     ORDER BY d.created_at DESC
     LIMIT 100'
)->fetchAll();

// Session
$is_logged_in = isset($_SESSION['user_id']);
$is_admin     = ($is_logged_in && ($_SESSION['user_role'] ?? '') === 'admin');
$user_name    = $_SESSION['user_name'] ?? '';
$user_avatar  = $user_name ? mb_strtoupper(mb_substr($user_name, 0, 1)) : '';

// User stats
$user_total = 0;
$user_count = 0;
if ($is_logged_in) {
    $stmt = $db->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM donations WHERE user_id = ? AND status = ?');
    $stmt->execute([$_SESSION['user_id'], 'Approved']);
    $row        = $stmt->fetch();
    $user_total = (int)$row['total'];
    $user_count = (int)$row['cnt'];
}

// Flash
$flash_success = $_SESSION['donate_success'] ?? '';
$flash_error   = $_SESSION['donate_error']   ?? '';
unset($_SESSION['donate_success'], $_SESSION['donate_error']);

// Helpers
function fmt_full(int $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }
function fmt_short(int $n): string {
    if ($n >= 1000000) return 'Rp ' . rtrim(rtrim(number_format($n/1000000,1),'0'),'.') . ' Jt';
    if ($n >= 1000)    return 'Rp ' . number_format($n/1000,0,',','.') . ' Rb';
    return 'Rp ' . number_format($n,0,',','.');
}
function time_ago(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'Baru saja';
    if ($diff < 3600)  return floor($diff/60) . ' menit lalu';
    if ($diff < 86400) return floor($diff/3600) . ' jam lalu';
    return floor($diff/86400) . ' hari lalu';
}
function days_left(?string $deadline): string {
    if (!$deadline) return '∞';
    $diff = (strtotime($deadline) - time()) / 86400;
    if ($diff < 0) return 'Berakhir';
    return floor($diff) . ' hari';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dana Darurat Kemanusiaan — Tanggap Bencana Nasional</title>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--red:#B91C1C;--red-dk:#7F1D1D;--red-lt:#DC2626;--navy:#1E2D5A;--navy-dk:#111827;--navy-lt:#2E4080;--cream:#FDF6EC;--warm:#F5ECD7;--gold:#D97706;--white:#FFFFFF;}
*{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:'Source Sans 3',sans-serif;background:var(--cream);color:var(--navy-dk);overflow-x:hidden;}
.oswald{font-family:'Oswald',sans-serif;}

/* NAV */
.nav{background:var(--navy-dk);position:sticky;top:0;z-index:100;border-bottom:3px solid var(--red);}
.nav-inner{max-width:1200px;margin:0 auto;padding:0 2rem;height:60px;display:flex;align-items:center;justify-content:space-between;}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-logo-icon{width:34px;height:34px;background:var(--red);border-radius:6px;display:flex;align-items:center;justify-content:center;}
.nav-logo-icon svg{width:18px;height:18px;stroke:white;stroke-width:2;}
.nav-logo-text{font-family:'Oswald',sans-serif;font-size:18px;letter-spacing:.04em;color:white;font-weight:600;}
.nav-logo-text span{color:var(--red-lt);}
.nav-links{display:flex;align-items:center;gap:16px;}
.nav-link{font-size:13px;font-weight:600;color:rgba(255,255,255,.65);text-decoration:none;letter-spacing:.04em;text-transform:uppercase;transition:color .2s;}
.nav-link:hover{color:white;}
.btn-nav{font-family:'Oswald',sans-serif;font-size:13px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;background:var(--red);color:white;padding:7px 18px;border-radius:4px;border:none;cursor:pointer;text-decoration:none;display:inline-block;transition:background .2s;}
.btn-nav:hover{background:var(--red-lt);}
.user-avatar-sm{width:32px;height:32px;border-radius:50%;background:var(--red);color:white;font-family:'Oswald',sans-serif;font-size:15px;font-weight:600;display:flex;align-items:center;justify-content:center;border:2px solid rgba(255,255,255,.3);}
.btn-logout{font-size:12px;color:#FCA5A5;background:none;border:none;cursor:pointer;font-family:'Source Sans 3',sans-serif;text-decoration:none;}
.btn-logout:hover{text-decoration:underline;}

/* HERO */
.hero-banner{background:var(--navy-dk);position:relative;overflow:hidden;padding:3rem 2rem 2.5rem;}
.hero-banner::before{content:'';position:absolute;inset:0;background:repeating-linear-gradient(-55deg,transparent,transparent 22px,rgba(255,255,255,.03) 22px,rgba(255,255,255,.03) 24px);}
.hero-banner::after{content:'';position:absolute;bottom:0;left:0;right:0;height:5px;background:linear-gradient(90deg,var(--red) 0%,var(--red-lt) 50%,var(--red-dk) 100%);}
.hero-inner{max-width:1200px;margin:0 auto;position:relative;z-index:1;}
.hero-tag{display:inline-flex;align-items:center;gap:8px;background:var(--red);color:white;font-family:'Oswald',sans-serif;font-size:12px;font-weight:500;letter-spacing:.08em;text-transform:uppercase;padding:5px 14px 5px 10px;border-radius:3px;margin-bottom:1rem;}
.hero-tag-dot{width:8px;height:8px;background:white;border-radius:50%;animation:blink 1.6s infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:.3;}}
.hero-title{font-family:'Oswald',sans-serif;font-size:clamp(2rem,5vw,3.6rem);font-weight:700;line-height:1.0;letter-spacing:.02em;text-transform:uppercase;color:white;}
.hero-title .accent{color:var(--red-lt);}
.hero-subtitle{font-size:14px;color:rgba(255,255,255,.6);max-width:500px;line-height:1.7;margin-top:.75rem;}

/* CAMPAIGNS GRID */
.section-wrap{max-width:1200px;margin:0 auto;padding:2.5rem 2rem;}
.section-title{font-family:'Oswald',sans-serif;font-size:1.5rem;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.03em;margin-bottom:1.5rem;display:flex;align-items:center;gap:10px;}
.section-title::after{content:'';flex:1;height:2px;background:linear-gradient(90deg,rgba(30,45,90,.1),transparent);}
.campaigns-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1.5rem;margin-bottom:3rem;}
.campaign-card{background:white;border-radius:8px;border:1px solid rgba(30,45,90,.1);box-shadow:0 2px 12px rgba(30,45,90,.07);overflow:hidden;transition:transform .2s,box-shadow .2s;display:flex;flex-direction:column;}
.campaign-card:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(30,45,90,.13);}
.campaign-card-top{background:var(--navy);padding:1.25rem;border-bottom:3px solid var(--red);}
.campaign-card-title{font-family:'Oswald',sans-serif;font-size:1.05rem;font-weight:700;color:white;text-transform:uppercase;letter-spacing:.03em;line-height:1.3;}
.campaign-card-desc{font-size:12px;color:rgba(255,255,255,.5);margin-top:.4rem;line-height:1.6;}
.campaign-card-body{padding:1.25rem;flex:1;display:flex;flex-direction:column;gap:.75rem;}
.campaign-raised{font-family:'Oswald',sans-serif;font-size:1.5rem;font-weight:700;color:var(--red);}
.campaign-target{font-size:12px;color:#94A3B8;}
.campaign-progress-track{height:8px;background:#E5E7EB;border-radius:4px;overflow:hidden;}
.campaign-progress-fill{height:100%;background:linear-gradient(90deg,var(--red-dk),var(--red-lt));border-radius:4px;}
.campaign-meta{display:flex;justify-content:space-between;font-size:12px;color:#94A3B8;}
.campaign-meta strong{color:var(--navy);font-weight:600;}
.btn-donate{display:block;width:100%;padding:10px;background:var(--red);color:white;font-family:'Oswald',sans-serif;font-size:14px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;border:none;border-radius:4px;cursor:pointer;text-align:center;text-decoration:none;transition:all .2s;margin-top:auto;}
.btn-donate:hover{background:var(--red-lt);transform:translateY(-1px);box-shadow:0 4px 14px rgba(185,28,28,.3);}
.btn-donate:disabled{background:#CBD5E1;cursor:not-allowed;transform:none;box-shadow:none;}

/* DONATE MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:200;align-items:center;justify-content:center;padding:1.5rem;}
.modal-overlay.open{display:flex;}
.modal{background:white;border-radius:10px;max-width:500px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.modal-header{background:var(--navy);padding:1.25rem 1.5rem;border-bottom:3px solid var(--red);display:flex;align-items:center;justify-content:space-between;}
.modal-title{font-family:'Oswald',sans-serif;font-size:1.1rem;font-weight:700;color:white;text-transform:uppercase;letter-spacing:.04em;}
.modal-close{background:none;border:none;cursor:pointer;color:rgba(255,255,255,.6);display:flex;align-items:center;padding:4px;border-radius:3px;transition:color .2s;}
.modal-close:hover{color:white;}
.modal-close svg{width:18px;height:18px;stroke:currentColor;}
.modal-body{padding:1.5rem;}
.bank-info{background:var(--warm);border:1px solid rgba(185,28,28,.2);border-radius:5px;padding:10px 14px;font-size:13px;color:var(--navy-dk);margin-bottom:1rem;}
.form-group{margin-bottom:.9rem;}
.form-label{display:block;font-size:11px;font-weight:600;color:#94A3B8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px;}
.form-input{width:100%;padding:9px 13px;font-size:14px;font-family:'Source Sans 3',sans-serif;border:1.5px solid #E5E7EB;border-radius:4px;background:#F9FAFB;color:var(--navy-dk);transition:border-color .2s;outline:none;}
.form-input:focus{border-color:var(--red);box-shadow:0 0 0 3px rgba(185,28,28,.1);background:white;}
.quick-amounts{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:.9rem;}
.quick-btn{padding:7px 4px;font-family:'Oswald',sans-serif;font-size:12px;font-weight:600;letter-spacing:.04em;background:#F1F5F9;border:1.5px solid #E2E8F0;border-radius:4px;cursor:pointer;transition:all .2s;color:var(--navy-dk);}
.quick-btn:hover,.quick-btn.active{background:var(--red);border-color:var(--red);color:white;}
.file-upload{border:2px dashed #CBD5E1;border-radius:6px;padding:1rem;text-align:center;font-size:13px;color:#94A3B8;position:relative;cursor:pointer;transition:border-color .2s;}
.file-upload:hover{border-color:var(--red);}
.file-upload input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;}
.btn-submit{width:100%;padding:12px;background:var(--red);color:white;font-family:'Oswald',sans-serif;font-size:15px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border:none;border-radius:4px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:.5rem;}
.btn-submit:hover{background:var(--red-lt);transform:translateY(-1px);box-shadow:0 4px 16px rgba(185,28,28,.35);}

/* TABLE */
.table-section{background:var(--navy-dk);padding:3rem 2rem;}
.table-wrap{max-width:1200px;margin:0 auto;}
.table-head-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;}
.table-head-bar h3{font-family:'Oswald',sans-serif;font-size:1.3rem;color:white;text-transform:uppercase;letter-spacing:.04em;}
.table-head-bar p{font-size:13px;color:rgba(255,255,255,.4);margin-top:3px;}
table{width:100%;border-collapse:collapse;}
thead th{padding:10px 14px;font-size:11px;font-weight:600;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.07em;border-bottom:1px solid rgba(255,255,255,.06);text-align:left;}
tbody td{padding:12px 14px;font-size:14px;color:rgba(255,255,255,.7);border-bottom:1px solid rgba(255,255,255,.04);}
tbody tr:hover td{background:rgba(255,255,255,.03);}
.td-id{color:rgba(255,255,255,.25);font-size:12px;font-weight:600;}
.td-amount{font-family:'Oswald',sans-serif;font-size:15px;font-weight:700;color:#4ADE80;}
.td-name{font-weight:600;color:rgba(255,255,255,.85);}
.td-date{color:rgba(255,255,255,.35);font-size:13px;}
.td-status{text-align:right;}
.status-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:.04em;}
.status-badge.approved{background:rgba(74,222,128,.12);color:#4ADE80;border:1px solid rgba(74,222,128,.2);}
.status-badge.pending{background:rgba(251,191,36,.12);color:#FBBF24;border:1px solid rgba(251,191,36,.2);}
.status-dot{width:5px;height:5px;border-radius:50%;background:currentColor;}

/* FLASH TOAST */
.toast{position:fixed;bottom:2rem;right:2rem;z-index:300;padding:14px 20px;border-radius:6px;font-size:14px;font-weight:500;max-width:380px;box-shadow:0 8px 32px rgba(0,0,0,.2);transition:opacity .4s;}
.toast-success{background:#16A34A;color:white;}
.toast-error{background:var(--red);color:white;}

/* LOGIN PROMPT */
.login-prompt{background:var(--navy);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:2rem;text-align:center;margin-top:1rem;}
.login-prompt p{color:rgba(255,255,255,.6);font-size:14px;margin-bottom:1rem;}
.btn-login{display:inline-flex;align-items:center;gap:8px;padding:10px 24px;background:var(--red);color:white;font-family:'Oswald',sans-serif;font-size:14px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;border-radius:4px;text-decoration:none;transition:all .2s;}
.btn-login:hover{background:var(--red-lt);}

footer{border-top:3px solid var(--navy);background:var(--navy-dk);padding:1.25rem 2rem;text-align:center;font-size:12px;color:rgba(255,255,255,.35);letter-spacing:.04em;}
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <div class="nav-inner">
    <a href="index.php" class="nav-logo">
      <div class="nav-logo-icon"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg></div>
      <span class="nav-logo-text">Respon<span>Bencana</span></span>
    </a>
    <div class="nav-links">
      <?php if ($is_logged_in): ?>
        <div class="user-avatar-sm"><?= $user_avatar ?></div>
        <span style="font-size:13px;color:rgba(255,255,255,.7);"><?= htmlspecialchars($user_name) ?></span>
        <?php if ($is_admin): ?>
          <a href="admin.php" class="btn-nav" style="background:#92400E;">Panel Admin</a>
        <?php endif; ?>
        <a href="logout.php" class="btn-logout">Keluar</a>
      <?php else: ?>
        <a href="login.php" class="btn-nav">Masuk / Daftar</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- HERO -->
<div class="hero-banner">
  <div class="hero-inner">
    <div class="hero-tag">
      <span class="hero-tag-dot"></span>
      Penggalangan Dana Aktif
    </div>
    <h1 class="hero-title">DONASI <span class="accent">KEMANUSIAAN</span><br>TANGGAP BENCANA</h1>
    <p class="hero-subtitle">Salurkan kepedulian Anda untuk korban bencana alam di seluruh Indonesia. Setiap rupiah membawa harapan.</p>
  </div>
</div>

<!-- FLASH TOAST -->
<?php if ($flash_success): ?>
  <div class="toast toast-success" id="flash-toast"><?= htmlspecialchars($flash_success) ?></div>
<?php elseif ($flash_error): ?>
  <div class="toast toast-error" id="flash-toast"><?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>

<!-- CAMPAIGNS -->
<div class="section-wrap">
  <h2 class="section-title">
    <svg style="flex-shrink:0;width:20px;height:20px;stroke:var(--red);stroke-width:2;" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
    Kampanye Aktif
  </h2>

  <?php if (empty($campaigns)): ?>
    <div style="text-align:center;padding:4rem 2rem;color:#94A3B8;">Belum ada kampanye aktif saat ini.</div>
  <?php else: ?>
  <div class="campaigns-grid">
    <?php foreach ($campaigns as $c):
      $pct = $c['target_amount'] > 0 ? min(($c['total_donated'] / $c['target_amount']) * 100, 100) : 0;
    ?>
    <div class="campaign-card">
      <div class="campaign-card-top">
        <div class="campaign-card-title"><?= htmlspecialchars($c['title']) ?></div>
        <?php if ($c['description']): ?>
          <div class="campaign-card-desc"><?= htmlspecialchars(mb_substr($c['description'], 0, 100)) ?>…</div>
        <?php endif; ?>
      </div>
      <div class="campaign-card-body">
        <div>
          <div class="campaign-raised"><?= fmt_short((int)$c['total_donated']) ?></div>
          <div class="campaign-target">dari target <?= fmt_full((int)$c['target_amount']) ?></div>
        </div>
        <div>
          <div class="campaign-progress-track">
            <div class="campaign-progress-fill" style="width:<?= $pct ?>%;"></div>
          </div>
          <div class="campaign-meta" style="margin-top:5px;">
            <span><strong><?= round($pct) ?>%</strong> tercapai</span>
            <span><strong><?= $c['donor_count'] ?></strong> donatur</span>
            <span><?= days_left($c['deadline']) ?> tersisa</span>
          </div>
        </div>
        <?php if ($is_logged_in && !$is_admin): ?>
          <button class="btn-donate" onclick="openDonateModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'])) ?>')">
            Donasi Sekarang
          </button>
        <?php elseif ($is_admin): ?>
          <div style="padding:8px;background:#F1F5F9;border-radius:4px;font-size:12px;color:#64748B;text-align:center;">Admin tidak dapat berdonasi</div>
        <?php else: ?>
          <a href="login.php" class="btn-donate">Login untuk Berdonasi</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!$is_logged_in): ?>
  <div class="login-prompt">
    <p>Daftar atau masuk akun agar histori donasi Anda dapat dilacak dan tercatat di sistem transparansi kami.</p>
    <a href="login.php" class="btn-login">
      <svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
      Masuk / Daftar Akun
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- DONATIONS TABLE -->
<section class="table-section" id="tabel-donasi">
  <div class="table-wrap">
    <div class="table-head-bar">
      <div>
        <h3>Laporan Transparansi Dana Masuk</h3>
        <p>Audit seluruh aliran dana kemanusiaan yang terekam di sistem</p>
      </div>
    </div>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>ID Transaksi</th>
            <th>Nama Donatur</th>
            <th>Kampanye</th>
            <th>Nominal</th>
            <th>Waktu</th>
            <th style="text-align:right;">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($donations as $d): ?>
            <tr>
              <td class="td-id">TX-<?= str_pad($d['id'], 6, '0', STR_PAD_LEFT) ?></td>
              <td class="td-name"><?= htmlspecialchars($d['name']) ?></td>
              <td style="font-size:13px;color:rgba(255,255,255,.4);"><?= htmlspecialchars(mb_substr($d['campaign_title'], 0, 40)) ?></td>
              <td class="td-amount"><?= fmt_full((int)$d['amount']) ?></td>
              <td class="td-date"><?= time_ago($d['created_at']) ?></td>
              <td class="td-status">
                <span class="status-badge approved">
                  <span class="status-dot"></span>Approved
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($donations)): ?>
            <tr><td colspan="6" style="text-align:center;color:rgba(255,255,255,.3);padding:2rem;">Belum ada donasi masuk.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<footer>&copy; 2026 Sistem Donasi Darurat Kebencanaan Kolektif &bull; Kelompok 4 &bull; UMY</footer>

<!-- DONATE MODAL -->
<div class="modal-overlay" id="donateModal">
  <div class="modal">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="modal-campaign-title">Donasi</div>
        <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:2px;">Salurkan kepedulian Anda</div>
      </div>
      <button class="modal-close" onclick="closeModal()">
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="bank-info">
        <strong>BRI · 1234-5678-9012-3456</strong><br>
        a/n Yayasan Tanggap Bencana Nasional
      </div>
      <form action="donate.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="campaign_id" id="modal-campaign-id" value="1">
        <div class="form-group">
          <label class="form-label">Akun Donatur</label>
          <input type="text" class="form-input" disabled value="<?= htmlspecialchars($user_name) ?>" />
        </div>
        <label class="form-label" style="margin-bottom:6px;">Nominal Cepat</label>
        <div class="quick-amounts">
          <button type="button" class="quick-btn" onclick="setAmount(50000,this)">Rp 50K</button>
          <button type="button" class="quick-btn" onclick="setAmount(100000,this)">Rp 100K</button>
          <button type="button" class="quick-btn" onclick="setAmount(250000,this)">Rp 250K</button>
          <button type="button" class="quick-btn" onclick="setAmount(500000,this)">Rp 500K</button>
          <button type="button" class="quick-btn" onclick="setAmount(1000000,this)">Rp 1 Jt</button>
          <button type="button" class="quick-btn" onclick="setAmount(2000000,this)">Rp 2 Jt</button>
        </div>
        <div class="form-group">
          <label class="form-label">Jumlah Donasi (Rp)</label>
          <input type="number" name="amount" id="donate-amount" class="form-input" placeholder="Atau masukkan nominal lain" required min="1000" />
        </div>
        <div class="form-group">
          <label class="form-label">Pesan / Doa</label>
          <textarea name="comment" class="form-input" rows="2" placeholder="Tuliskan doa terbaik..." style="resize:none;"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Bukti Transfer (.jpg, .png)</label>
          <div class="file-upload">
            <input type="file" name="bukti" accept=".jpg,.jpeg,.png" required onchange="updateFileName(this)" />
            <div id="file-label"><strong>Klik untuk upload</strong> atau drag &amp; drop</div>
          </div>
        </div>
        <button type="submit" class="btn-submit">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="white" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
          Kirim Donasi
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function openDonateModal(id, title) {
  document.getElementById('modal-campaign-id').value = id;
  document.getElementById('modal-campaign-title').textContent = title;
  document.getElementById('donateModal').classList.add('open');
}
function closeModal() {
  document.getElementById('donateModal').classList.remove('open');
}
document.getElementById('donateModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
function setAmount(val, btn) {
  document.getElementById('donate-amount').value = val;
  document.querySelectorAll('.quick-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
function updateFileName(input) {
  const l = document.getElementById('file-label');
  l.innerHTML = input.files && input.files.length
    ? `<strong style="color:var(--red)">✓ ${input.files[0].name}</strong>`
    : '<strong>Klik untuk upload</strong> atau drag &amp; drop';
}
const toast = document.getElementById('flash-toast');
if (toast) setTimeout(() => toast.style.opacity = '0', 4000);
</script>
</body>
</html>