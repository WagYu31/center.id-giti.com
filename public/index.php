<?php
// ISOLASI SESI CENTER
session_name('CENTER_SESSION');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/', // Berlaku di root
    'domain' => '', 
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

require_once '../config/database.php';
require_once '../src/auth.php';
require_once '../src/functions.php';

auto_login($conn);
check_login();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_unset();
    session_destroy();
    setcookie('remember_user', '', time() - 3600, '/', '', true, true);
    header("Location: login.php");
    exit();
}

// Sync session role agar selalu up-to-date dari DB
$_SESSION['user_role'] = $user['role'];

function getSSOLink($conn, $user_id, $target_url) {
    $token = generate_sso_token($user_id, $conn);
    return $target_url . "?sso_token=" . $token;
}

date_default_timezone_set('Asia/Jakarta');
$jam = date('H');
if ($jam >= 5 && $jam < 11) $sapa = "Good Morning";
elseif ($jam >= 11 && $jam < 15) $sapa = "Good Afternoon";
elseif ($jam >= 15 && $jam < 18) $sapa = "Good Evening";
else $sapa = "Good Night";

$userImage = $user['avatar'] ?? 'default.png'; 
$avatarUrl = "assets/img/avatars/" . $userImage;
$hasAvatar = !empty($userImage) && file_exists(__DIR__ . "/assets/img/avatars/" . $userImage);

$hari = date('l');
$tanggal = date('d M Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Center Loewix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=11.0">
    <link rel="icon" type="image/png" href="assets/uploads/logo-square.png">
</head>
<body>

<div class="container mb-5">
    
    <div class="d-flex justify-content-between align-items-center navbar-wander mb-5">
        <a href="#" class="brand-wander">
            <div class="bg-white text-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="bi bi-grid-fill"></i>
            </div>
            <span>Grav Center</span>
        </a>
        <div class="user-pill">
            <span class="small fw-bold px-2 d-none d-sm-inline"><?= htmlspecialchars($user['name']) ?></span>
            <a href="logout.php" class="btn-logout-circle" title="Logout">
                <i class="bi bi-power"></i>
            </a>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-lg-8 d-flex flex-column">
            <div class="card-wander h-100 d-flex flex-column justify-content-center">
                <div class="mb-4">
                    <h1 class="fw-bold display-6 mb-1 text-dark">
                        <?= $sapa ?>, <span style="color: var(--green-accent);"><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span>
                    </h1>
                    <p class="text-secondary">Have a productive day at Grav Technology!</p>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="widget-dark h-100">
                            <div>
                                <small class="opacity-75 d-block mb-1">Local Time</small>
                                <h2 class="m-0 fw-bold" id="clock">--:--<span id="clockSec" style="font-size: 0.5em; opacity: 0.5; font-weight: 500;"></span></h2>
                            </div>
                            <i class="bi bi-clock-history fs-1 opacity-50"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="widget-light h-100">
                            <div>
                                <small class="text-muted d-block mb-1"><?= $hari ?></small>
                                <h4 class="m-0 fw-bold"><?= $tanggal ?></h4>
                            </div>
                            <div class="bg-white p-2 rounded-circle shadow-sm">
                                <i class="bi bi-calendar-event fs-4 text-dark"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 d-flex flex-column gap-3">
            <!-- Pengumuman Widget -->
            <div class="card-notes d-flex flex-column" style="flex: 1;">
                <div class="notes-header" style="border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 12px; margin-bottom: 0;">
                    <div class="notes-title">
                        <div class="icon-note-yellow">
                            <i class="bi bi-megaphone"></i>
                        </div>
                        <span>Pengumuman</span>
                    </div>
                    <?php if($user['role'] === 'admin'): ?>
                    <button class="btn btn-sm px-2 py-0" style="background: linear-gradient(135deg,#d97706,#f59e0b); color:white; border:none; border-radius:6px; font-size:0.7rem; font-weight:600;" onclick="showCreateAnnouncement()">
                        <i class="bi bi-plus"></i> Buat
                    </button>
                    <?php endif; ?>
                </div>
                <div id="announcementList" style="flex:1; overflow-y:auto; max-height: 200px; padding: 8px 0;">
                    <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-warning"></div></div>
                </div>
            </div>
            
            <!-- Target Bulanan Widget -->
            <?php
            // Calculate monthly target
            $month = date('m'); $year = date('Y');
            $monthlyDone = 0; $monthlyTotal = 0;
            try {
                $monthlyDone = (int)$conn->query("SELECT COUNT(*) FROM bukti_jobs WHERE user_id={$user['id']} AND status='done' AND deleted_at IS NULL AND MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetchColumn();
                $monthlyTotal = (int)$conn->query("SELECT COUNT(*) FROM bukti_jobs WHERE user_id={$user['id']} AND deleted_at IS NULL AND MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetchColumn();
            } catch(Exception $e) {}
            // Dynamic target from DB
            $target = 30;
            try {
                $conn->exec("ALTER TABLE users ADD COLUMN monthly_target INT DEFAULT 30");
            } catch(Exception $e) {}
            try {
                $dbTarget = $conn->query("SELECT monthly_target FROM users WHERE id={$user['id']}")->fetchColumn();
                if ($dbTarget) $target = (int)$dbTarget;
            } catch(Exception $e) {}
            $pct = $target > 0 ? min(100, round(($monthlyDone / $target) * 100)) : 0;
            $bulanNama = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][(int)$month];
            ?>
            <div style="background: white; border-radius: 16px; padding: 20px; border: 1px solid rgba(0,0,0,0.06);">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#059669,#10b981);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-trophy-fill" style="color:white;font-size:0.75rem;"></i>
                        </div>
                        <span style="font-weight:700;font-size:0.88rem;color:#0f172a;">Target <?= $bulanNama ?> <?= $year ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="targetPct" style="font-size:0.75rem;font-weight:700;color:<?= $pct >= 80 ? '#059669' : ($pct >= 50 ? '#d97706' : '#64748b') ?>;"><?= $pct ?>%</span>
                        <?php if($user['role'] === 'admin'): ?>
                        <button onclick="editTarget()" style="background:none;border:none;color:#94a3b8;font-size:0.75rem;cursor:pointer;padding:2px;" title="Ubah target">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="background:#f1f5f9;border-radius:8px;height:10px;overflow:hidden;margin-bottom:10px;">
                    <div id="targetBar" style="width:<?= $pct ?>%;height:100%;border-radius:8px;background:linear-gradient(90deg,#059669,#10b981);transition:width 1s ease;"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center" style="font-size:0.75rem;color:#64748b;">
                    <span><strong style="color:#0f172a;"><?= $monthlyDone ?></strong> selesai dari <strong id="targetNum"><?= $target ?></strong> target</span>
                    <span><?= $monthlyTotal ?> total tugas</span>
                </div>
                <!-- Inline edit (hidden by default) -->
                <div id="targetEditRow" style="display:none;margin-top:10px;padding-top:10px;border-top:1px solid #f1f5f9;">
                    <div class="d-flex align-items-center gap-2">
                        <input type="number" id="targetInput" value="<?= $target ?>" min="1" max="999" style="width:80px;border:1px solid #e2e8f0;border-radius:8px;padding:5px 10px;font-size:0.82rem;font-weight:600;text-align:center;outline:none;" onfocus="this.style.borderColor='#d97706'" onblur="this.style.borderColor='#e2e8f0'">
                        <button onclick="saveTarget()" style="background:linear-gradient(135deg,#059669,#10b981);color:white;border:none;border-radius:8px;padding:5px 14px;font-size:0.75rem;font-weight:600;cursor:pointer;">
                            <i class="bi bi-check-lg"></i> Simpan
                        </button>
                        <button onclick="cancelEditTarget()" style="background:#f1f5f9;color:#64748b;border:none;border-radius:8px;padding:5px 14px;font-size:0.75rem;font-weight:600;cursor:pointer;">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar & Planner -->
    <div class="row mb-4 g-3">
        <!-- Mini Calendar -->
        <div class="col-lg-5">
            <div style="background:white;border-radius:16px;padding:20px;border:1px solid rgba(0,0,0,0.06);height:100%;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <button onclick="calNav(-1)" style="background:none;border:none;color:#64748b;font-size:1rem;cursor:pointer;padding:4px 8px;border-radius:6px;" onmouseenter="this.style.background='#f1f5f9'" onmouseleave="this.style.background='none'">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <span id="calMonthYear" style="font-weight:700;font-size:0.95rem;color:#0f172a;"></span>
                    <button onclick="calNav(1)" style="background:none;border:none;color:#64748b;font-size:1rem;cursor:pointer;padding:4px 8px;border-radius:6px;" onmouseenter="this.style.background='#f1f5f9'" onmouseleave="this.style.background='none'">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
                <div id="calGrid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center;"></div>
            </div>
        </div>
        
        <!-- Event Panel -->
        <div class="col-lg-7">
            <div style="background:white;border-radius:16px;padding:20px;border:1px solid rgba(0,0,0,0.06);height:100%;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#d97706,#f59e0b);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-calendar-event" style="color:white;font-size:0.75rem;"></i>
                        </div>
                        <div>
                            <span id="eventDateTitle" style="font-weight:700;font-size:0.9rem;color:#0f172a;">Hari Ini</span>
                            <span id="eventDateSub" style="font-size:0.7rem;color:#94a3b8;display:block;line-height:1;"></span>
                        </div>
                    </div>
                    <button onclick="showAddEvent()" style="background:linear-gradient(135deg,#d97706,#f59e0b);color:white;border:none;border-radius:8px;padding:5px 12px;font-size:0.72rem;font-weight:600;cursor:pointer;">
                        <i class="bi bi-plus-lg me-1"></i>Tambah
                    </button>
                </div>
                
                <!-- Add Event Form (hidden) -->
                <div id="addEventForm" style="display:none;background:#fefce8;border-radius:12px;padding:14px;margin-bottom:12px;border:1px solid #fef08a;">
                    <div class="row g-2 mb-2">
                        <div class="col">
                            <input type="text" id="evTitle" placeholder="Judul rencana..." style="width:100%;border:1px solid #e2e8f0;border-radius:8px;padding:7px 10px;font-size:0.82rem;outline:none;" onfocus="this.style.borderColor='#d97706'" onblur="this.style.borderColor='#e2e8f0'">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-auto">
                            <input type="time" id="evTime" style="border:1px solid #e2e8f0;border-radius:8px;padding:5px 8px;font-size:0.78rem;outline:none;">
                        </div>
                        <div class="col-auto d-flex align-items-center">
                            <div class="form-check form-switch m-0" style="min-height:auto;">
                                <input class="form-check-input" type="checkbox" id="evMultiDay" onchange="toggleMultiDay()" style="cursor:pointer;">
                                <label class="form-check-label" for="evMultiDay" style="font-size:0.72rem;font-weight:600;color:#64748b;cursor:pointer;">Multi-hari</label>
                            </div>
                        </div>
                        <div class="col" id="endDateWrap" style="display:none;">
                            <div class="d-flex align-items-center gap-1">
                                <span style="font-size:0.7rem;color:#94a3b8;white-space:nowrap;">s/d</span>
                                <input type="date" id="evEndDate" style="width:100%;border:1px solid #e2e8f0;border-radius:8px;padding:5px 8px;font-size:0.78rem;outline:none;">
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex gap-2">
                            <button type="button" class="ev-color-btn" data-color="#d97706" style="width:24px;height:24px;border-radius:50%;border:none;background:#d97706;cursor:pointer;box-shadow:0 0 0 3px white, 0 0 0 5px #d97706;transition:box-shadow 0.15s;" onclick="pickColor(this)"></button>
                            <button type="button" class="ev-color-btn" data-color="#ef4444" style="width:24px;height:24px;border-radius:50%;border:none;background:#ef4444;cursor:pointer;box-shadow:none;transition:box-shadow 0.15s;" onclick="pickColor(this)"></button>
                            <button type="button" class="ev-color-btn" data-color="#3b82f6" style="width:24px;height:24px;border-radius:50%;border:none;background:#3b82f6;cursor:pointer;box-shadow:none;transition:box-shadow 0.15s;" onclick="pickColor(this)"></button>
                            <button type="button" class="ev-color-btn" data-color="#059669" style="width:24px;height:24px;border-radius:50%;border:none;background:#059669;cursor:pointer;box-shadow:none;transition:box-shadow 0.15s;" onclick="pickColor(this)"></button>
                            <button type="button" class="ev-color-btn" data-color="#8b5cf6" style="width:24px;height:24px;border-radius:50%;border:none;background:#8b5cf6;cursor:pointer;box-shadow:none;transition:box-shadow 0.15s;" onclick="pickColor(this)"></button>
                        </div>
                        <div class="ms-auto d-flex gap-1">
                            <button type="button" onclick="cancelAddEvent()" style="background:#f1f5f9;color:#64748b;border:none;border-radius:6px;padding:5px 10px;font-size:0.72rem;font-weight:600;cursor:pointer;">Batal</button>
                            <button type="button" onclick="submitEvent()" style="background:linear-gradient(135deg,#d97706,#f59e0b);color:white;border:none;border-radius:6px;padding:5px 10px;font-size:0.72rem;font-weight:600;cursor:pointer;">
                                <i class="bi bi-check-lg"></i> Simpan
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Event List -->
                <div id="eventList" style="max-height:220px;overflow-y:auto;">
                    <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-warning"></div></div>
                </div>
            </div>
        </div>
    </div>

    <?php if($user['role'] === 'admin'): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="admin-banner">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                        <i class="bi bi-shield-lock-fill fs-3"></i>
                    </div>
                    <div>
                        <h5>Admin Control Center</h5>
                        <p>Manage users, permissions, and system settings.</p>
                    </div>
                </div>
                <a href="data-karyawan.php" class="btn btn-light fw-bold px-4 rounded-pill">Open</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-12">
            <h5 class="fw-bold text-dark"><i class="bi bi-grid-1x2-fill me-2"></i>Your Applications</h5>
        </div>
    </div>

    <div class="row g-4 pb-5">
        
        <div class="col-md-6 col-xl-4">
            <a href="https://ssll.id-giti.com/" target="_blank" class="app-ticket">
                <div class="ticket-stub color-ssll">
                    <i class="bi bi-cash"></i>
                </div>
                <div class="ticket-body">
                    <div class="app-name">Salary</div>
                    <div class="app-desc">Kerja Untuk Jajan</div>
                </div>
                <div class="ticket-arrow"><i class="bi bi-chevron-right"></i></div>
            </a>
        </div>
        
        <?php if ($user['app_bukti']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="<?= getSSOLink($conn, $user['id'], 'https://center.id-giti.com/bukti/auth-sso.php') ?>" target="_blank" class="app-ticket">
                <div class="ticket-stub color-bukti">
                    <i class="bi bi-journal-text"></i>
                </div>
                <div class="ticket-body">
                    <div class="app-name">Bukti</div>
                    <div class="app-desc">Semua Akan Kukerjakan</div>
                </div>
                <div class="ticket-arrow"><i class="bi bi-chevron-right"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($user['app_sales']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="https://sales.id-giti.com/center-login.php?nama=<?= htmlspecialchars($user['name']) ?>" target="_blank" class="app-ticket">
            <!--<a href="<?= getSSOLink($conn, $user['id'], 'https://center.id-giti.com/sales/auth-sso.php') ?>" target="_blank" class="app-ticket">-->
                <div class="ticket-stub color-sales">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="ticket-body">
                    <div class="app-name">Sales</div>
                    <div class="app-desc">Target & Omset</div>
                </div>
                <div class="ticket-arrow"><i class="bi bi-chevron-right"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($user['app_quotation']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="https://quo.id-giti.com/center-login.php?nama=<?= htmlspecialchars($user['name']) ?>" target="_blank" class="app-ticket">
                <div class="ticket-stub color-quo">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div class="ticket-body">
                    <div class="app-name">Quotation</div>
                    <div class="app-desc">Buat Penawaran</div>
                </div>
                <div class="ticket-arrow"><i class="bi bi-chevron-right"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($user['app_produksi']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="https://service.id-giti.com/src/html/process/center-login.php?nama=<?= htmlspecialchars($user['name']) ?>" class="app-ticket" target="_blank">
                <div class="ticket-stub color-prod">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="ticket-body">
                    <div class="app-name">Service [OLD]</div>
                    <div class="app-desc">Web Service Lama</div>
                </div>
                <div class="ticket-arrow"><i class="bi bi-chevron-right"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($user['app_service']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="<?= getSSOLink($conn, $user['id'], 'https://center.id-giti.com/service/auth-sso.php') ?>" target="_blank" class="app-ticket">
                <div class="ticket-stub bg-soft-info text-info">
                    <i class="bi bi-wrench-adjustable"></i>
                </div>
                <div class="ticket-body">
                    <div class="app-name">Service & Production [NEW]</div>
                    <div class="app-desc">Perbaikan Unit</div>
                </div>
                <div class="ticket-arrow"><i class="bi bi-chevron-right"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($user['app_teknisi']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="https://jadwal.id-giti.com/center-login.php?nama=<?= htmlspecialchars($user['name']) ?>" target="_blank" class="app-ticket">
                <div class="ticket-stub color-tech">
                    <i class="bi bi-tools"></i>
                </div>
                <div class="ticket-body">
                    <div class="app-name">Teknisi</div>
                    <div class="app-desc">Manajemen Jadwal</div>
                </div>
                <div class="ticket-arrow"><i class="bi bi-chevron-right"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($user['app_giti']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="https://id-giti.com/admin/dashboard" target="_blank" class="app-ticket">
                <div class="ticket-stub color-warranty">
                    <i class="bi bi-qr-code-scan"></i>
                </div>
                <div class="ticket-body">
                    <div class="app-name">Garansi</div>
                    <div class="app-desc">Garansi Produk</div>
                </div>
                <div class="ticket-arrow"><i class="bi bi-chevron-right"></i></div>
            </a>
        </div>
        <?php endif; ?>
        
    </div> 
</div> 

<script>
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const clockEl = document.getElementById('clock');
        const secEl = document.getElementById('clockSec');
        clockEl.childNodes[0].textContent = `${hours}:${minutes}`;
        if (secEl) secEl.textContent = `:${seconds}`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // === ANNOUNCEMENT SYSTEM ===
    const isAdmin = <?= $user['role'] === 'admin' ? 'true' : 'false' ?>;

    function timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 60) return 'Baru saja';
        if (diff < 3600) return Math.floor(diff/60) + 'm lalu';
        if (diff < 86400) return Math.floor(diff/3600) + 'j lalu';
        if (diff < 604800) return Math.floor(diff/86400) + 'h lalu';
        return new Date(dateStr).toLocaleDateString('id-ID', {day:'numeric',month:'short'});
    }

    function loadAnnouncements() {
        fetch('api_announcement.php?action=fetch')
            .then(r => r.json())
            .then(res => {
                const el = document.getElementById('announcementList');
                if (!res.data || res.data.length === 0) {
                    el.innerHTML = `<div class="text-center py-4" style="color:#94a3b8;">
                        <i class="bi bi-megaphone" style="font-size:1.5rem;opacity:0.3;"></i>
                        <p style="font-size:0.8rem;margin:6px 0 0;">Belum ada pengumuman</p>
                    </div>`;
                    return;
                }
                let html = '';
                res.data.forEach(a => {
                    const pColors = {urgent:'#ef4444',important:'#d97706',normal:'#64748b'};
                    const pLabels = {urgent:'URGENT',important:'PENTING',normal:''};
                    const pBg = {urgent:'#fef2f2',important:'#fffbeb',normal:'transparent'};
                    html += `<div style="padding:10px 16px;border-bottom:1px solid rgba(0,0,0,0.03);cursor:default;transition:background 0.15s;" 
                                onmouseenter="this.style.background='#fafbfc'" onmouseleave="this.style.background='transparent'">
                        <div class="d-flex justify-content-between align-items-start">
                            <div style="flex:1;min-width:0;">
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    ${a.priority!=='normal'?`<span style="font-size:0.6rem;font-weight:700;color:${pColors[a.priority]};background:${pBg[a.priority]};padding:1px 6px;border-radius:4px;letter-spacing:0.5px;">${pLabels[a.priority]}</span>`:''}
                                    <span style="font-weight:700;font-size:0.82rem;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${a.title}</span>
                                </div>
                                <p style="font-size:0.75rem;color:#64748b;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${a.content}</p>
                                <div style="font-size:0.65rem;color:#94a3b8;margin-top:3px;">${a.author_name} · ${timeAgo(a.created_at)}</div>
                            </div>
                            ${isAdmin?`<button onclick="deleteAnnouncement(${a.id})" class="btn btn-sm p-0 ms-2" style="color:#cbd5e1;font-size:0.7rem;border:none;background:none;" title="Hapus"><i class="bi bi-x-lg"></i></button>`:''}
                        </div>
                    </div>`;
                });
                el.innerHTML = html;
            })
            .catch(() => {
                document.getElementById('announcementList').innerHTML = '<div class="text-center py-3" style="color:#94a3b8;font-size:0.8rem;">Gagal memuat</div>';
            });
    }
    loadAnnouncements();

    function deleteAnnouncement(id) {
        if (!confirm('Hapus pengumuman ini?')) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch('api_announcement.php', {method:'POST', body: fd})
            .then(r => r.json())
            .then(() => loadAnnouncements());
    }

    function showCreateAnnouncement() {
        // Create modal dynamically
        let modal = document.getElementById('createAnnouncementModal');
        if (!modal) {
            const div = document.createElement('div');
            div.innerHTML = `
            <div class="modal fade" id="createAnnouncementModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                        <div class="modal-header border-0 px-4 pt-4 pb-2">
                            <div>
                                <h6 class="fw-bold mb-0" style="color:#0f172a;"><i class="bi bi-megaphone me-2" style="color:#d97706;"></i>Buat Pengumuman</h6>
                                <small style="color:#94a3b8;">Akan tampil di dashboard semua karyawan</small>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body px-4 pb-4">
                            <div class="mb-3">
                                <label style="font-size:0.7rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:0.5px;">Judul</label>
                                <input type="text" id="annTitle" class="form-control" placeholder="Judul pengumuman..." style="border-radius:10px;border-color:#e2e8f0;font-size:0.88rem;">
                            </div>
                            <div class="mb-3">
                                <label style="font-size:0.7rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:0.5px;">Isi</label>
                                <textarea id="annContent" class="form-control" rows="3" placeholder="Detail pengumuman..." style="border-radius:10px;border-color:#e2e8f0;font-size:0.88rem;"></textarea>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label style="font-size:0.7rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:0.5px;">Prioritas</label>
                                    <select id="annPriority" class="form-select" style="border-radius:10px;border-color:#e2e8f0;font-size:0.85rem;">
                                        <option value="normal">Normal</option>
                                        <option value="important">Penting</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label style="font-size:0.7rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:0.5px;">Kedaluwarsa</label>
                                    <input type="date" id="annExpires" class="form-control" style="border-radius:10px;border-color:#e2e8f0;font-size:0.85rem;">
                                </div>
                            </div>
                            <button onclick="submitAnnouncement()" class="btn w-100 fw-bold" style="background:linear-gradient(135deg,#d97706,#f59e0b);color:white;border:none;border-radius:10px;padding:10px;">
                                <i class="bi bi-send me-1"></i>Kirim Pengumuman
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
            document.body.appendChild(div);
            modal = document.getElementById('createAnnouncementModal');
        }
        new bootstrap.Modal(modal).show();
    }

    function submitAnnouncement() {
        const fd = new FormData();
        fd.append('action', 'create');
        fd.append('title', document.getElementById('annTitle').value);
        fd.append('content', document.getElementById('annContent').value);
        fd.append('priority', document.getElementById('annPriority').value);
        fd.append('expires_at', document.getElementById('annExpires').value);

        fetch('api_announcement.php', {method:'POST', body: fd})
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('createAnnouncementModal')).hide();
                    document.getElementById('annTitle').value = '';
                    document.getElementById('annContent').value = '';
                    loadAnnouncements();
                } else {
                    alert(res.message);
                }
            });
    }

    // === CALENDAR PLANNER ===
    const MONTHS = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const DAYS = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
    let calMonth = <?= (int)date('m') - 1 ?>;
    let calYear = <?= (int)date('Y') ?>;
    let selectedDate = '<?= date('Y-m-d') ?>';
    let monthEvents = [];
    let selectedColor = '#d97706';

    function calNav(dir) {
        calMonth += dir;
        if (calMonth > 11) { calMonth = 0; calYear++; }
        if (calMonth < 0) { calMonth = 11; calYear--; }
        renderCalendar();
    }

    function renderCalendar() {
        document.getElementById('calMonthYear').textContent = MONTHS[calMonth] + ' ' + calYear;
        const grid = document.getElementById('calGrid');
        grid.innerHTML = '';
        
        // Day headers
        DAYS.forEach(d => {
            const h = document.createElement('div');
            h.style.cssText = 'font-size:0.68rem;font-weight:700;color:#94a3b8;padding:6px 0;text-transform:uppercase;';
            h.textContent = d;
            grid.appendChild(h);
        });
        
        const firstDay = new Date(calYear, calMonth, 1).getDay();
        const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
        const today = new Date();
        
        // Empty cells
        for (let i = 0; i < firstDay; i++) {
            const e = document.createElement('div');
            e.style.padding = '6px';
            grid.appendChild(e);
        }
        
        // Day cells
        for (let d = 1; d <= daysInMonth; d++) {
            const cell = document.createElement('div');
            const dateStr = calYear + '-' + String(calMonth+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            const isToday = d === today.getDate() && calMonth === today.getMonth() && calYear === today.getFullYear();
            const isSelected = dateStr === selectedDate;
            const hasEvents = monthEvents.some(ev => {
                const end = ev.end_date || ev.event_date;
                return dateStr >= ev.event_date && dateStr <= end;
            });
            
            cell.style.cssText = `padding:4px;cursor:pointer;border-radius:10px;transition:all 0.15s;position:relative;`;
            
            let bg = 'transparent', color = '#334155', fw = '500';
            if (isSelected) { bg = '#d97706'; color = 'white'; fw = '700'; }
            else if (isToday) { bg = '#fef3c7'; color = '#92400e'; fw = '700'; }
            
            cell.innerHTML = `
                <div style="width:32px;height:32px;line-height:32px;margin:auto;border-radius:10px;font-size:0.78rem;font-weight:${fw};color:${color};background:${bg};transition:all 0.15s;">${d}</div>
                ${hasEvents ? '<div style="width:5px;height:5px;border-radius:50%;background:#d97706;margin:2px auto 0;"></div>' : '<div style="height:7px;"></div>'}
            `;
            
            cell.onclick = () => selectDate(dateStr);
            if (!isSelected) {
                cell.onmouseenter = () => { if(!isToday) cell.querySelector('div').style.background='#f8fafc'; };
                cell.onmouseleave = () => { if(!isToday) cell.querySelector('div').style.background=bg; };
            }
            grid.appendChild(cell);
        }
        
        fetchMonthEvents();
    }

    function selectDate(dateStr) {
        selectedDate = dateStr;
        const d = new Date(dateStr + 'T00:00:00');
        const dayNames = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        const mNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        document.getElementById('eventDateTitle').textContent = dayNames[d.getDay()] + ', ' + d.getDate() + ' ' + mNames[d.getMonth()];
        document.getElementById('eventDateSub').textContent = d.getFullYear();
        
        renderCalendar();
        fetchDateEvents(dateStr);
    }

    function fetchMonthEvents() {
        fetch(`api_calendar.php?action=fetch&month=${calMonth+1}&year=${calYear}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    monthEvents = res.data;
                    // Re-render dots
                    const grid = document.getElementById('calGrid');
                    const cells = grid.querySelectorAll('div[style*="cursor:pointer"]');
                    // Already handled in renderCalendar, but update dots
                }
            });
    }

    function fetchDateEvents(dateStr) {
        const list = document.getElementById('eventList');
        list.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm text-warning"></div></div>';
        
        fetch(`api_calendar.php?action=fetch_date&date=${dateStr}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    if (res.data.length === 0) {
                        list.innerHTML = `
                            <div class="text-center py-4">
                                <i class="bi bi-calendar2-check" style="font-size:2rem;color:#e2e8f0;"></i>
                                <p style="font-size:0.78rem;color:#94a3b8;margin:8px 0 0;">Tidak ada rencana</p>
                            </div>`;
                        return;
                    }
                    list.innerHTML = res.data.map(ev => {
                        const mN = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                        let dateInfo = '';
                        if (ev.end_date && ev.end_date !== ev.event_date) {
                            const s = new Date(ev.event_date + 'T00:00:00');
                            const e = new Date(ev.end_date + 'T00:00:00');
                            dateInfo = `<span style="font-size:0.68rem;background:#f1f5f9;color:#64748b;padding:1px 6px;border-radius:4px;font-weight:600;"><i class="bi bi-calendar-range me-1"></i>${s.getDate()} ${mN[s.getMonth()]} → ${e.getDate()} ${mN[e.getMonth()]}</span>`;
                        }
                        return `
                        <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid #f8fafc;${ev.is_done == 1 ? 'opacity:0.5;' : ''}" id="ev-${ev.id}">
                            <div style="width:4px;min-height:32px;border-radius:4px;background:${ev.color};margin-top:2px;flex-shrink:0;"></div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:600;font-size:0.82rem;color:#0f172a;${ev.is_done == 1 ? 'text-decoration:line-through;' : ''}">${ev.title}</div>
                                <div class="d-flex gap-2 flex-wrap align-items-center" style="margin-top:2px;">
                                    ${ev.event_time ? `<span style="font-size:0.7rem;color:#94a3b8;"><i class="bi bi-clock me-1"></i>${ev.event_time.substring(0,5)}</span>` : ''}
                                    ${dateInfo}
                                </div>
                            </div>
                            <div class="d-flex gap-1">
                                <button onclick="toggleEvent(${ev.id})" style="background:none;border:none;cursor:pointer;color:${ev.is_done == 1 ? '#059669' : '#cbd5e1'};font-size:0.9rem;" title="${ev.is_done == 1 ? 'Batalkan' : 'Selesai'}">
                                    <i class="bi bi-check-circle${ev.is_done == 1 ? '-fill' : ''}"></i>
                                </button>
                                <button onclick="deleteEvent(${ev.id})" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:0.8rem;" title="Hapus">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </div>`;
                    }).join('');
                }
            });
    }

    function showAddEvent() { 
        document.getElementById('addEventForm').style.display = 'block'; 
        document.getElementById('evTitle').focus();
        // Reset color to default
        selectedColor = '#d97706';
        document.querySelectorAll('.ev-color-btn').forEach(b => b.style.boxShadow = 'none');
        document.querySelector('.ev-color-btn[data-color="#d97706"]').style.boxShadow = '0 0 0 3px white, 0 0 0 5px #d97706';
    }
    function cancelAddEvent() { 
        document.getElementById('addEventForm').style.display = 'none'; 
        document.getElementById('evTitle').value = ''; 
        document.getElementById('evTime').value = ''; 
        document.getElementById('evMultiDay').checked = false;
        document.getElementById('endDateWrap').style.display = 'none';
        document.getElementById('evEndDate').value = '';
    }

    function toggleMultiDay() {
        const isMulti = document.getElementById('evMultiDay').checked;
        document.getElementById('endDateWrap').style.display = isMulti ? 'block' : 'none';
        if (!isMulti) document.getElementById('evEndDate').value = '';
    }

    function pickColor(btn) {
        document.querySelectorAll('.ev-color-btn').forEach(b => b.style.boxShadow = 'none');
        btn.style.boxShadow = '0 0 0 3px white, 0 0 0 5px ' + btn.dataset.color;
        selectedColor = btn.dataset.color;
    }

    function submitEvent() {
        const title = document.getElementById('evTitle').value.trim();
        if (!title) { alert('Judul wajib diisi'); return; }
        
        const isMulti = document.getElementById('evMultiDay').checked;
        const endDate = document.getElementById('evEndDate').value;
        
        if (isMulti && !endDate) { alert('Pilih tanggal selesai'); return; }
        if (isMulti && endDate < selectedDate) { alert('Tanggal selesai harus setelah tanggal mulai'); return; }
        
        const fd = new FormData();
        fd.append('action', 'create');
        fd.append('title', title);
        fd.append('event_date', selectedDate);
        fd.append('event_time', document.getElementById('evTime').value);
        fd.append('color', selectedColor);
        if (isMulti && endDate) fd.append('end_date', endDate);
        
        fetch('api_calendar.php', {method:'POST', body: fd})
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    cancelAddEvent();
                    renderCalendar();
                    fetchDateEvents(selectedDate);
                } else { alert(res.message); }
            });
    }

    function toggleEvent(id) {
        const fd = new FormData();
        fd.append('action', 'toggle');
        fd.append('id', id);
        fetch('api_calendar.php', {method:'POST', body: fd})
            .then(r => r.json())
            .then(() => fetchDateEvents(selectedDate));
    }

    function deleteEvent(id) {
        if (!confirm('Hapus rencana ini?')) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch('api_calendar.php', {method:'POST', body: fd})
            .then(r => r.json())
            .then(() => { renderCalendar(); fetchDateEvents(selectedDate); });
    }

    // Init calendar
    selectDate(selectedDate);
    renderCalendar();

    // === TARGET EDIT ===
    function editTarget() {
        document.getElementById('targetEditRow').style.display = 'block';
        document.getElementById('targetInput').focus();
    }
    function cancelEditTarget() {
        document.getElementById('targetEditRow').style.display = 'none';
    }
    function saveTarget() {
        const newTarget = parseInt(document.getElementById('targetInput').value);
        if (isNaN(newTarget) || newTarget < 1 || newTarget > 999) { alert('Target harus 1-999'); return; }
        
        const fd = new FormData();
        fd.append('action', 'update');
        fd.append('target', newTarget);
        fetch('api_target.php', {method:'POST', body: fd})
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    document.getElementById('targetNum').textContent = newTarget;
                    const done = <?= $monthlyDone ?>;
                    const pct = Math.min(100, Math.round((done / newTarget) * 100));
                    document.getElementById('targetPct').textContent = pct + '%';
                    document.getElementById('targetBar').style.width = pct + '%';
                    document.getElementById('targetPct').style.color = pct >= 80 ? '#059669' : (pct >= 50 ? '#d97706' : '#64748b');
                    cancelEditTarget();
                } else { alert(res.message); }
            })
            .catch(() => alert('Gagal koneksi'));
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>