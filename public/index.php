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
            $target = 30; // Default target per bulan
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
                    <span style="font-size:0.75rem;font-weight:700;color:<?= $pct >= 80 ? '#059669' : ($pct >= 50 ? '#d97706' : '#64748b') ?>;"><?= $pct ?>%</span>
                </div>
                <div style="background:#f1f5f9;border-radius:8px;height:10px;overflow:hidden;margin-bottom:10px;">
                    <div style="width:<?= $pct ?>%;height:100%;border-radius:8px;background:linear-gradient(90deg,#059669,#10b981);transition:width 1s ease;"></div>
                </div>
                <div class="d-flex justify-content-between" style="font-size:0.75rem;color:#64748b;">
                    <span><strong style="color:#0f172a;"><?= $monthlyDone ?></strong> selesai dari <strong><?= $target ?></strong> target</span>
                    <span><?= $monthlyTotal ?> total tugas</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Notes -->
    <div class="row mb-4">
        <div class="col-12">
            <div style="background:white;border-radius:16px;padding:20px 24px;border:1px solid rgba(0,0,0,0.06);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="icon-note-yellow"><i class="bi bi-journal-text"></i></div>
                        <span class="fw-bold" style="font-size:0.95rem;">Quick Notes</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="saveStatus" style="font-size:0.72rem;font-weight:600;color:#059669;opacity:0;transition:opacity 0.3s;"><i class="bi bi-check-circle-fill me-1"></i>Tersimpan</span>
                        <button id="btnSaveNote" onclick="saveNote()" style="display:none;background:linear-gradient(135deg,#d97706,#f59e0b);color:white;border:none;border-radius:8px;padding:6px 16px;font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.2s;">
                            <i class="bi bi-floppy me-1"></i>Simpan
                        </button>
                    </div>
                </div>
                <textarea id="noteInput" placeholder="Tulis catatan harianmu di sini..." style="width:100%;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;font-size:0.85rem;color:#334155;resize:vertical;min-height:60px;max-height:140px;font-family:inherit;transition:border 0.2s;outline:none;" onfocus="this.style.borderColor='#d97706'" onblur="this.style.borderColor='#e2e8f0'"><?= htmlspecialchars($user['notes'] ?? '') ?></textarea>
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

    // === QUICK NOTES ===
    const noteInput = document.getElementById('noteInput');
    const btnSave = document.getElementById('btnSaveNote');
    const statusText = document.getElementById('saveStatus');
    let originalNote = noteInput.value;

    noteInput.addEventListener('input', function() {
        if (this.value !== originalNote) {
            btnSave.style.display = 'inline-block';
            statusText.style.opacity = '0';
        } else {
            btnSave.style.display = 'none';
        }
    });

    function saveNote() {
        btnSave.disabled = true;
        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        const fd = new FormData();
        fd.append('notes', noteInput.value);
        fetch('save_note.php', {method:'POST', body: fd})
            .then(r => r.text())
            .then(d => {
                if (d.trim() === 'success') {
                    originalNote = noteInput.value;
                    btnSave.style.display = 'none';
                    statusText.style.opacity = '1';
                    setTimeout(() => { statusText.style.opacity = '0'; }, 3000);
                } else { alert('Gagal simpan: ' + d); }
            })
            .catch(() => alert('Gagal koneksi'))
            .finally(() => { btnSave.disabled = false; btnSave.innerHTML = '<i class="bi bi-floppy me-1"></i>Simpan'; });
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>