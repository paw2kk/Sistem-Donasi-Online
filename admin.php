<?php
session_start();
require_once __DIR__ . '/config.php';

// Guard: must be admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: login.php?as=admin');
    exit;
}

$db = get_db();

// ─── ACTIONS ────────────────────────────────────────────────

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$flash  = '';
$flash_type = 'success';

// CREATE
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title  = trim($_POST['title'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $target = (int)($_POST['target_amount'] ?? 0);
    $dl     = $_POST['deadline'] ?? '';
    $active = isset($_POST['is_active']) ? 1 : 0;

    if (!$title || $target < 1) {
        $flash = 'Judul dan target wajib diisi.';
        $flash_type = 'error';
    } else {
        $db->prepare('INSERT INTO campaigns (title, description, target_amount, deadline, is_active) VALUES (?,?,?,?,?)')
           ->execute([$title, $desc, $target, $dl ?: null, $active]);
        $flash = "Kampanye \"$title\" berhasil ditambahkan.";
    }
}

// UPDATE
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $title  = trim($_POST['title'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $target = (int)($_POST['target_amount'] ?? 0);
    $dl     = $_POST['deadline'] ?? '';
    $active = isset($_POST['is_active']) ? 1 : 0;

    if (!$id || !$title || $target < 1) {
        $flash = 'Data tidak valid.';
        $flash_type = 'error';
    } else {
        $db->prepare('UPDATE campaigns SET title=?, description=?, target_amount=?, deadline=?, is_active=? WHERE id=?')
           ->execute([$title, $desc, $target, $dl ?: null, $active, $id]);
        $flash = "Kampanye berhasil diperbarui.";
    }
}

// DELETE
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $db->prepare('DELETE FROM campaigns WHERE id=?')->execute([$id]);
        $flash = "Kampanye berhasil dihapus.";
    }
}

// TOGGLE ACTIVE
if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id'] ?? 0);
    $val = (int)($_POST['current'] ?? 0);
    if ($id) {
        $db->prepare('UPDATE campaigns SET is_active=? WHERE id=?')->execute([$val ? 0 : 1, $id]);
        $flash = 'Status kampanye diperbarui.';
    }
}

// ─── DATA ───────────────────────────────────────────────────

// All campaigns with donation totals
$campaigns = $db->query(
    'SELECT c.*,
            COALESCE(SUM(CASE WHEN d.status="Approved" THEN d.amount ELSE 0 END), 0) AS total_donated,
            COUNT(CASE WHEN d.status="Approved" THEN 1 END) AS donor_count
     FROM campaigns c
     LEFT JOIN donations d ON d.campaign_id = c.id
     GROUP BY c.id
     ORDER BY c.created_at DESC'
)->fetchAll();

// Recent donations (last 20)
$recent_donations = $db->query(
    'SELECT d.id, d.amount, d.status, d.created_at, d.comment,
            u.name AS user_name, c.title AS campaign_title
     FROM donations d
     JOIN users u ON u.id = d.user_id
     JOIN campaigns c ON c.id = d.campaign_id
     ORDER BY d.created_at DESC LIMIT 20'
)->fetchAll();

// Summary stats
$total_raised = $db->query('SELECT COALESCE(SUM(amount),0) FROM donations WHERE status="Approved"')->fetchColumn();
$total_donors = $db->query('SELECT COUNT(DISTINCT user_id) FROM donations WHERE status="Approved"')->fetchColumn();
$total_campaigns = count($campaigns);

// Edit target (for pre-filled form)
$edit_id = (int)($_GET['edit'] ?? 0);
$edit_data = null;
if ($edit_id) {
    $stmt = $db->prepare('SELECT * FROM campaigns WHERE id=?');
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
}

function fmt_rp(int $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}
function fmt_short(int $n): string {
    if ($n >= 1000000) return 'Rp ' . number_format($n / 1000000, 1, ',', '.') . ' Jt';
    if ($n >= 1000)    return 'Rp ' . number_format($n / 1000, 0, ',', '.') . ' Rb';
    return 'Rp ' . number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel Admin — ResponBencana</title>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--red:#B91C1C;--red-dk:#7F1D1D;--red-lt:#DC2626;--navy:#1E2D5A;--navy-dk:#111827;--navy-lt:#2E4080;--cream:#FDF6EC;--gold:#D97706;}
*{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:'Source Sans 3',sans-serif;background:#F1F5F9;color:#1E293B;min-height:100vh;display:flex;flex-direction:column;}

/* NAV */
.nav{background:var(--navy-dk);border-bottom:3px solid var(--red);position:sticky;top:0;z-index:100;}
.nav-inner{max-width:1400px;margin:0 auto;padding:0 2rem;height:60px;display:flex;align-items:center;justify-content:space-between;}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-logo-icon{width:34px;height:34px;background:var(--red);border-radius:6px;display:flex;align-items:center;justify-content:center;}
.nav-logo-icon svg{width:18px;height:18px;stroke:white;stroke-width:2;}
.nav-logo-text{font-family:'Oswald',sans-serif;font-size:18px;letter-spacing:.04em;color:white;font-weight:600;}
.nav-logo-text span{color:var(--red-lt);}
.nav-right{display:flex;align-items:center;gap:16px;}
.admin-chip{background:rgba(217,119,6,.2);border:1px solid rgba(217,119,6,.4);color:#FCD34D;font-size:11px;font-family:'Oswald',sans-serif;letter-spacing:.06em;text-transform:uppercase;padding:4px 12px;border-radius:3px;display:flex;align-items:center;gap:6px;}
.btn-nav-logout{font-size:12px;color:#FCA5A5;background:none;border:none;cursor:pointer;font-family:'Source Sans 3',sans-serif;text-decoration:none;}
.btn-nav-logout:hover{text-decoration:underline;}

/* LAYOUT */
.page-wrap{max-width:1400px;margin:0 auto;padding:2rem;}

/* HEADER BAR */
.page-header{margin-bottom:2rem;}
.page-title{font-family:'Oswald',sans-serif;font-size:2rem;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.03em;}
.page-subtitle{font-size:14px;color:#64748B;margin-top:4px;}

/* STAT CARDS */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.25rem;margin-bottom:2rem;}
.stat-card{background:white;border-radius:8px;padding:1.25rem 1.5rem;border:1px solid #E2E8F0;box-shadow:0 1px 4px rgba(0,0,0,.05);display:flex;align-items:center;gap:1rem;}
.stat-icon{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon svg{width:22px;height:22px;stroke:white;stroke-width:1.8;}
.stat-val{font-family:'Oswald',sans-serif;font-size:1.5rem;font-weight:700;color:var(--navy);line-height:1.1;}
.stat-lbl{font-size:12px;color:#94A3B8;margin-top:2px;font-weight:500;}

/* CARD */
.card{background:white;border-radius:8px;border:1px solid #E2E8F0;box-shadow:0 1px 6px rgba(0,0,0,.06);overflow:hidden;margin-bottom:2rem;}
.card-header{padding:1.25rem 1.5rem;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;}
.card-title{font-family:'Oswald',sans-serif;font-size:1.1rem;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.03em;display:flex;align-items:center;gap:8px;}
.card-title svg{width:18px;height:18px;stroke:var(--red);stroke-width:2;}
.card-body{padding:1.5rem;}

/* FORM */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.form-group{margin-bottom:0;}
.form-group.full{grid-column:1/-1;}
.form-label{display:block;font-size:11px;font-weight:600;color:#94A3B8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px;}
.form-input,.form-textarea,.form-select{width:100%;padding:9px 13px;font-size:14px;font-family:'Source Sans 3',sans-serif;border:1.5px solid #E2E8F0;border-radius:6px;background:#F8FAFC;color:#1E293B;transition:border-color .2s,box-shadow .2s;outline:none;}
.form-input:focus,.form-textarea:focus,.form-select:focus{border-color:var(--red);box-shadow:0 0 0 3px rgba(185,28,28,.08);background:white;}
.form-textarea{resize:vertical;min-height:80px;}
.form-check{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;color:#475569;}
.form-check input{width:16px;height:16px;accent-color:var(--red);cursor:pointer;}

.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;font-family:'Oswald',sans-serif;font-size:13px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;border:none;border-radius:6px;cursor:pointer;transition:all .2s;text-decoration:none;}
.btn-primary{background:var(--red);color:white;}
.btn-primary:hover{background:var(--red-lt);transform:translateY(-1px);box-shadow:0 4px 12px rgba(185,28,28,.3);}
.btn-secondary{background:#F1F5F9;color:#475569;border:1px solid #E2E8F0;}
.btn-secondary:hover{background:#E2E8F0;}
.btn-warning{background:#D97706;color:white;}
.btn-warning:hover{background:#B45309;}
.btn-danger{background:#DC2626;color:white;}
.btn-danger:hover{background:#B91C1C;}
.btn-success{background:#16A34A;color:white;}
.btn-success:hover{background:#15803D;}
.btn-sm{padding:5px 12px;font-size:12px;}

/* CAMPAIGN TABLE */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead th{padding:10px 14px;font-size:11px;font-weight:600;color:#94A3B8;text-transform:uppercase;letter-spacing:.07em;text-align:left;border-bottom:2px solid #F1F5F9;white-space:nowrap;}
tbody td{padding:12px 14px;font-size:14px;color:#334155;border-bottom:1px solid #F8FAFC;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:#FAFBFD;}
.progress-mini{height:6px;background:#E2E8F0;border-radius:3px;overflow:hidden;min-width:80px;}
.progress-mini-fill{height:100%;background:linear-gradient(90deg,var(--red-dk),var(--red-lt));border-radius:3px;}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:.04em;}
.badge-active{background:#DCFCE7;color:#15803D;}
.badge-inactive{background:#FEE2E2;color:#DC2626;}
.badge-approved{background:#DCFCE7;color:#15803D;}
.badge-pending{background:#FEF9C3;color:#A16207;}
.badge-dot{width:5px;height:5px;border-radius:50%;background:currentColor;}

/* FLASH */
.flash{padding:12px 16px;border-radius:6px;font-size:14px;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;}
.flash-success{background:#DCFCE7;border:1px solid #BBF7D0;color:#15803D;}
.flash-error{background:#FEE2E2;border:1px solid #FECACA;color:#DC2626;}
.flash svg{width:16px;height:16px;stroke:currentColor;flex-shrink:0;}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:2rem;}
.modal-overlay.open{display:flex;}
.modal{background:white;border-radius:10px;max-width:560px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.modal-header{padding:1.25rem 1.5rem;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between;}
.modal-title{font-family:'Oswald',sans-serif;font-size:1.2rem;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.03em;}
.modal-close{background:none;border:none;cursor:pointer;color:#94A3B8;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:4px;transition:background .2s;}
.modal-close:hover{background:#F1F5F9;color:#475569;}
.modal-close svg{width:18px;height:18px;stroke:currentColor;}
.modal-body{padding:1.5rem;}
.modal-footer{padding:1rem 1.5rem;border-top:1px solid #F1F5F9;display:flex;justify-content:flex-end;gap:.75rem;}

/* Confirm delete */
.confirm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1100;align-items:center;justify-content:center;padding:2rem;}
.confirm-overlay.open{display:flex;}
.confirm-box{background:white;border-radius:10px;padding:2rem;max-width:400px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.confirm-icon{width:56px;height:56px;background:#FEE2E2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;}
.confirm-icon svg{width:28px;height:28px;stroke:#DC2626;}
.confirm-title{font-family:'Oswald',sans-serif;font-size:1.2rem;font-weight:700;color:var(--navy);}
.confirm-text{font-size:14px;color:#64748B;margin:.5rem 0 1.5rem;}

footer{border-top:2px solid #E2E8F0;background:var(--navy-dk);padding:1.25rem 2rem;text-align:center;font-size:12px;color:rgba(255,255,255,.35);letter-spacing:.04em;margin-top:auto;}
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
    <div class="nav-right">
      <div class="admin-chip">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
        Admin · <?= htmlspecialchars($_SESSION['user_name']) ?>
      </div>
      <a href="logout.php" class="btn-nav-logout">Keluar</a>
    </div>
  </div>
</nav>

<div class="page-wrap">

  <div class="page-header">
    <h1 class="page-title">Panel Administrasi</h1>
    <p class="page-subtitle">Kelola kampanye donasi dan pantau seluruh aktivitas penggalangan dana.</p>
  </div>

  <?php if ($flash): ?>
    <div class="flash <?= $flash_type === 'error' ? 'flash-error' : 'flash-success' ?>">
      <?php if ($flash_type === 'success'): ?>
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <?php else: ?>
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
      <?php endif; ?>
      <?= htmlspecialchars($flash) ?>
    </div>
  <?php endif; ?>

  <!-- SUMMARY STATS -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:var(--red);">
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
      </div>
      <div>
        <div class="stat-val"><?= $total_campaigns ?></div>
        <div class="stat-lbl">Total Kampanye</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#16A34A;">
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <div>
        <div class="stat-val"><?= fmt_short((int)$total_raised) ?></div>
        <div class="stat-lbl">Total Dana Terkumpul</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:var(--navy);">
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
      </div>
      <div>
        <div class="stat-val"><?= $total_donors ?></div>
        <div class="stat-lbl">Donatur Unik</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#D97706;">
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
      </div>
      <div>
        <div class="stat-val"><?= count(array_filter($campaigns, fn($c) => $c['is_active'])) ?></div>
        <div class="stat-lbl">Kampanye Aktif</div>
      </div>
    </div>
  </div>

  <!-- CAMPAIGNS TABLE -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
        Daftar Kampanye
      </h2>
      <button class="btn btn-primary" onclick="openCreateModal()">
        <svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Tambah Kampanye
      </button>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Judul Kampanye</th>
            <th>Target</th>
            <th>Terkumpul</th>
            <th>Progress</th>
            <th>Donatur</th>
            <th>Deadline</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($campaigns as $c):
            $pct = $c['target_amount'] > 0 ? min(($c['total_donated'] / $c['target_amount']) * 100, 100) : 0;
          ?>
          <tr>
            <td style="color:#94A3B8;font-weight:600;">#<?= $c['id'] ?></td>
            <td>
              <div style="font-weight:600;color:#1E293B;max-width:220px;"><?= htmlspecialchars($c['title']) ?></div>
              <?php if ($c['description']): ?>
                <div style="font-size:12px;color:#94A3B8;margin-top:2px;"><?= htmlspecialchars(mb_substr($c['description'], 0, 60)) ?>…</div>
              <?php endif; ?>
            </td>
            <td style="font-weight:600;white-space:nowrap;"><?= fmt_rp((int)$c['target_amount']) ?></td>
            <td style="font-weight:700;color:#16A34A;white-space:nowrap;"><?= fmt_rp((int)$c['total_donated']) ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div class="progress-mini" style="width:80px;">
                  <div class="progress-mini-fill" style="width:<?= $pct ?>%;"></div>
                </div>
                <span style="font-size:12px;font-family:'Oswald',sans-serif;color:var(--red);font-weight:600;"><?= round($pct) ?>%</span>
              </div>
            </td>
            <td style="text-align:center;font-weight:600;"><?= $c['donor_count'] ?></td>
            <td style="white-space:nowrap;color:#64748B;font-size:13px;">
              <?= $c['deadline'] ? date('d/m/Y', strtotime($c['deadline'])) : '—' ?>
            </td>
            <td>
              <span class="badge <?= $c['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                <span class="badge-dot"></span>
                <?= $c['is_active'] ? 'Aktif' : 'Nonaktif' ?>
              </span>
            </td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <button class="btn btn-warning btn-sm" onclick='openEditModal(<?= json_encode($c) ?>)'>Edit</button>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <input type="hidden" name="current" value="<?= $c['is_active'] ?>">
                  <button type="submit" class="btn btn-secondary btn-sm"><?= $c['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                </form>
                <button class="btn btn-danger btn-sm" onclick="openConfirmDelete(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'])) ?>')">Hapus</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($campaigns)): ?>
            <tr><td colspan="9" style="text-align:center;color:#94A3B8;padding:2rem;">Belum ada kampanye.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- RECENT DONATIONS -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
        Donasi Terbaru
      </h2>
      <a href="index.php" class="btn btn-secondary btn-sm">Lihat di Publik</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>TX ID</th>
            <th>Donatur</th>
            <th>Kampanye</th>
            <th>Nominal</th>
            <th>Waktu</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_donations as $d): ?>
          <tr>
            <td style="color:#94A3B8;font-weight:600;">TX-<?= str_pad($d['id'], 6, '0', STR_PAD_LEFT) ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($d['user_name']) ?></td>
            <td style="font-size:13px;color:#64748B;max-width:180px;"><?= htmlspecialchars(mb_substr($d['campaign_title'], 0, 35)) ?>…</td>
            <td style="font-weight:700;color:#16A34A;"><?= fmt_rp((int)$d['amount']) ?></td>
            <td style="font-size:13px;color:#94A3B8;"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
            <td>
              <span class="badge <?= $d['status'] === 'Approved' ? 'badge-approved' : 'badge-pending' ?>">
                <span class="badge-dot"></span><?= $d['status'] ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recent_donations)): ?>
            <tr><td colspan="6" style="text-align:center;color:#94A3B8;padding:2rem;">Belum ada donasi.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /page-wrap -->

<!-- ===== CREATE MODAL ===== -->
<div class="modal-overlay" id="createModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Kampanye Baru</h3>
      <button class="modal-close" onclick="closeModal('createModal')">
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group full">
            <label class="form-label">Judul Kampanye *</label>
            <input type="text" name="title" class="form-input" placeholder="Nama kampanye" required />
          </div>
          <div class="form-group full">
            <label class="form-label">Deskripsi</label>
            <textarea name="description" class="form-textarea" placeholder="Deskripsi singkat kampanye..."></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Target Donasi (Rp) *</label>
            <input type="number" name="target_amount" class="form-input" placeholder="Contoh: 50000000" min="1" required />
          </div>
          <div class="form-group">
            <label class="form-label">Batas Waktu</label>
            <input type="date" name="deadline" class="form-input" min="<?= date('Y-m-d') ?>" />
          </div>
          <div class="form-group full">
            <label class="form-check">
              <input type="checkbox" name="is_active" checked />
              Kampanye langsung aktif
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
          Simpan Kampanye
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ===== EDIT MODAL ===== -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Edit Kampanye</h3>
      <button class="modal-close" onclick="closeModal('editModal')">
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="edit-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group full">
            <label class="form-label">Judul Kampanye *</label>
            <input type="text" name="title" id="edit-title" class="form-input" required />
          </div>
          <div class="form-group full">
            <label class="form-label">Deskripsi</label>
            <textarea name="description" id="edit-description" class="form-textarea"></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Target Donasi (Rp) *</label>
            <input type="number" name="target_amount" id="edit-target" class="form-input" min="1" required />
          </div>
          <div class="form-group">
            <label class="form-label">Batas Waktu</label>
            <input type="date" name="deadline" id="edit-deadline" class="form-input" />
          </div>
          <div class="form-group full">
            <label class="form-check">
              <input type="checkbox" name="is_active" id="edit-active" />
              Kampanye aktif
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Batal</button>
        <button type="submit" class="btn btn-warning">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== CONFIRM DELETE ===== -->
<div class="confirm-overlay" id="confirmDelete">
  <div class="confirm-box">
    <div class="confirm-icon">
      <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
    </div>
    <div class="confirm-title">Hapus Kampanye?</div>
    <p class="confirm-text" id="confirm-text">Semua donasi terkait akan ikut terhapus. Tindakan ini tidak bisa dibatalkan.</p>
    <form method="POST" id="deleteForm">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="delete-id">
      <div style="display:flex;gap:.75rem;justify-content:center;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('confirmDelete')">Batal</button>
        <button type="submit" class="btn btn-danger">Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>

<footer>&copy; 2026 Sistem Donasi Darurat Kebencanaan Kolektif &bull; Kelompok 4 &bull; UMY</footer>

<script>
function openCreateModal() {
  document.getElementById('createModal').classList.add('open');
}
function openEditModal(c) {
  document.getElementById('edit-id').value        = c.id;
  document.getElementById('edit-title').value     = c.title;
  document.getElementById('edit-description').value = c.description || '';
  document.getElementById('edit-target').value    = c.target_amount;
  document.getElementById('edit-deadline').value  = c.deadline || '';
  document.getElementById('edit-active').checked  = c.is_active == 1;
  document.getElementById('editModal').classList.add('open');
}
function openConfirmDelete(id, title) {
  document.getElementById('delete-id').value = id;
  document.getElementById('confirm-text').textContent =
    `Kampanye "${title}" dan semua donasi terkait akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.`;
  document.getElementById('confirmDelete').classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
// Close on overlay click
document.querySelectorAll('.modal-overlay,.confirm-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});
</script>
</body>
</html>