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
        
        <div class="col-lg-4">
            <div class="card-notes">
                <div class="notes-header">
                    <div class="notes-title">
                        <div class="icon-note-yellow">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <span>Quick Notes</span>
                    </div>
                    <div id="saveStatus" class="save-status">
                        <i class="bi bi-check-circle-fill me-1"></i> Saved
                    </div>
                </div>
                <textarea id="noteInput" class="notes-area" placeholder="Tulis catatan harianmu di sini..."><?= htmlspecialchars($user['notes'] ?? '') ?></textarea>
                <button id="btnSaveNote" class="btn-save-note" onclick="saveNote()">Simpan</button>
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
    
    const noteInput = document.getElementById('noteInput');
    const btnSave = document.getElementById('btnSaveNote');
    const statusText = document.getElementById('saveStatus');
    let originalContent = noteInput.value; 

    noteInput.addEventListener('input', function() {
        if (this.value !== originalContent) {
            btnSave.classList.add('show');
            statusText.classList.remove('show');
        } else {
            btnSave.classList.remove('show');
        }
    });

    function saveNote() {
        const content = noteInput.value;
        const btnIcon = btnSave.innerHTML;

        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        btnSave.disabled = true;

        const formData = new FormData();
        formData.append('notes', content);

        fetch('save_note.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if(data.trim() === "success") {
                originalContent = content;
                btnSave.classList.remove('show');
                statusText.classList.add('show');
                setTimeout(() => {
                    statusText.classList.remove('show');
                }, 3000);
            } else {
                alert("Gagal menyimpan catatan: " + data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("Terjadi kesalahan koneksi.");
        })
        .finally(() => {
            btnSave.innerHTML = 'Simpan';
            btnSave.disabled = false;
        });
    }
</script>

</body>
</html>