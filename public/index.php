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
    <title>Grav Center — Workspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=13.0">
    <link rel="icon" type="image/png" href="assets/uploads/logo-square.png">
</head>
<body>

<div class="container mb-5">
    
    <!-- ═══════════════════════════════════════════════════
         FLOATING NAVBAR (Taste Skill Luxury Pill)
         ═══════════════════════════════════════════════════ -->
    <div class="navbar-wander mb-4">
        <a href="#" class="brand-wander">
            <div class="brand-icon-box">
                <i class="bi bi-grid-fill"></i>
            </div>
            <span>Grav Center</span>
            <span class="brand-badge d-none d-md-inline-flex">
                <span class="ping-dot-emerald"></span> Live Workspace
            </span>
        </a>
        <div class="user-pill">
            <span class="user-name d-none d-sm-inline"><?= htmlspecialchars($user['name']) ?></span>
            <span class="role-tag d-none d-md-inline"><?= strtoupper($user['role'] ?? 'USER') ?></span>
            <a href="logout.php" class="btn-logout-circle" title="Keluar dari akun">
                <i class="bi bi-power"></i>
            </a>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         HERO SECTION (Bento Greeting & Widgets)
         ═══════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4 reveal" id="calendarSection">
        <div class="col-lg-8 d-flex flex-column">
            <div class="card-wander h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="hero-tag">
                        <i class="bi bi-stars" style="color: #f59e0b;"></i> PT. Loewix Indonesia
                    </div>
                    <h1 class="greeting-text mb-2">
                        <?= $sapa ?>, <span><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span>
                    </h1>
                    <p class="hero-subtitle mb-4">Have a productive and focused day at Grav Technology workspace.</p>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="widget-dark h-100">
                            <div>
                                <small style="color: #94a3b8; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.6px; font-weight: 600; display: block; margin-bottom: 4px;">Local Time • WIB</small>
                                <h2 id="clock">--:--<span id="clockSec" style="font-size: 0.55em; opacity: 0.6; font-weight: 500; color: #fbbf24;"></span></h2>
                            </div>
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.25); display: flex; align-items: center; justify-content: center; color: #f59e0b; font-size: 1.25rem;">
                                <i class="bi bi-clock-history"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="widget-light h-100">
                            <div>
                                <small style="color: #64748b; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.6px; font-weight: 600; display: block; margin-bottom: 4px;"><?= $hari ?></small>
                                <h4><?= $tanggal ?></h4>
                            </div>
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: #fffbeb; border: 1px solid #fef3c7; display: flex; align-items: center; justify-content: center; color: #d97706; font-size: 1.25rem; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);">
                                <i class="bi bi-calendar4-event"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 d-flex flex-column gap-3">
            <!-- Pengumuman Widget -->
            <div class="ts-card p-3 d-flex flex-column" style="flex: 1; min-height: 170px;">
                <div class="d-flex justify-content-between align-items-center pb-2 mb-2" style="border-bottom: 1px solid #f1f5f9;">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:28px; height:28px; border-radius:8px; background: linear-gradient(135deg, #f59e0b, #d97706); display:flex; align-items:center; justify-content:center; color:white; font-size:0.75rem; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);">
                            <i class="bi bi-megaphone-fill"></i>
                        </div>
                        <span style="font-weight: 700; font-size: 0.88rem; color: #0a0a0a;">Pengumuman</span>
                    </div>
                    <?php if($user['role'] === 'admin'): ?>
                    <button class="btn btn-sm px-2 py-1" style="background: linear-gradient(135deg, #f59e0b, #d97706); color:white; border:none; border-radius:6px; font-size:0.68rem; font-weight:700; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);" onclick="showCreateAnnouncement()">
                        <i class="bi bi-plus-lg me-1"></i>Buat
                    </button>
                    <?php endif; ?>
                </div>
                <div id="announcementList" style="flex:1; overflow-y:auto; max-height: 140px; padding: 2px 0;">
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
            <div class="ts-card p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#059669,#10b981);display:flex;align-items:center;justify-content:center;color:white;font-size:0.75rem;box-shadow:0 2px 6px rgba(16,185,129,0.3);">
                            <i class="bi bi-trophy-fill"></i>
                        </div>
                        <span style="font-weight:700;font-size:0.88rem;color:#0a0a0a;">Target <?= $bulanNama ?> <?= $year ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="targetPct" style="font-size:0.75rem;font-weight:700;color:<?= $pct >= 80 ? '#059669' : ($pct >= 50 ? '#d97706' : '#64748b') ?>;background:<?= $pct >= 80 ? '#ecfdf5' : ($pct >= 50 ? '#fefce8' : '#f1f5f9') ?>;padding:2px 8px;border-radius:var(--radius-full);"><?= $pct ?>%</span>
                        <?php if($user['role'] === 'admin'): ?>
                        <button onclick="editTarget()" style="background:none;border:none;color:#94a3b8;font-size:0.75rem;cursor:pointer;padding:2px;" title="Ubah target">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="background:#f1f5f9;border-radius:8px;height:8px;overflow:hidden;margin-bottom:8px;">
                    <div id="targetBar" style="width:<?= $pct ?>%;height:100%;border-radius:8px;background:linear-gradient(90deg,#059669,#10b981);transition:width 1s ease;box-shadow:0 0 10px rgba(16,185,129,0.5);"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center" style="font-size:0.72rem;color:#64748b;">
                    <span><strong style="color:#0a0a0a;"><?= $monthlyDone ?></strong> selesai dari <strong id="targetNum"><?= $target ?></strong> target</span>
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

    <!-- ═══════════════════════════════════════════════════
         CALENDAR & PERSONAL NOTES BENTO SECTION
         ═══════════════════════════════════════════════════ -->
    <div class="row mb-4 g-3 reveal">
        <!-- Mini Calendar (Left) -->
        <div class="col-lg-5">
            <div class="ts-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <button onclick="calNav(-1)" style="background:none;border:none;color:#64748b;font-size:0.9rem;cursor:pointer;padding:4px 8px;border-radius:8px;transition:all 0.2s;" onmouseenter="this.style.background='#f1f5f9'" onmouseleave="this.style.background='none'">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <span id="calMonthYear" style="font-weight:800;font-size:0.95rem;color:#0a0a0a;letter-spacing:-0.01em;"></span>
                    <button onclick="calNav(1)" style="background:none;border:none;color:#64748b;font-size:0.9rem;cursor:pointer;padding:4px 8px;border-radius:8px;transition:all 0.2s;" onmouseenter="this.style.background='#f1f5f9'" onmouseleave="this.style.background='none'">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
                <div id="calGrid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;text-align:center;"></div>
            </div>
        </div>
        
        <!-- Catatan Pribadi (Right Productivity Studio) -->
        <div class="col-lg-7">
            <div class="ts-card h-100 d-flex flex-column" style="min-height:340px;">
                <!-- Notes Header -->
                <div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background:#ffffff;">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:white;font-size:0.75rem;box-shadow:0 2px 6px rgba(245,158,11,0.3);">
                            <i class="bi bi-journal-bookmark-fill"></i>
                        </div>
                        <div>
                            <span style="font-weight:700;font-size:0.9rem;color:#0a0a0a;">Catatan Pribadi</span>
                            <span id="notes-count" style="font-size:0.68rem;color:#94a3b8;display:block;line-height:1;margin-top:1px;"></span>
                        </div>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <div style="position:relative;">
                            <input id="notes-search" type="text" placeholder="Cari catatan..." style="border:1px solid #e2e8f0;border-radius:20px;padding:5px 10px 5px 28px;font-size:0.75rem;outline:none;width:130px;transition:all 0.25s;" onfocus="this.style.borderColor='#d97706';this.style.width='160px'" onblur="this.style.borderColor='#e2e8f0';this.style.width='130px'" oninput="searchNotes(this.value)">
                            <i class="bi bi-search" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.7rem;pointer-events:none;"></i>
                        </div>
                        <button onclick="createNewNote()" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:white;border:none;border-radius:8px;padding:6px 12px;font-size:0.72rem;font-weight:700;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:4px;box-shadow:0 2px 8px rgba(245,158,11,0.3);">
                            <i class="bi bi-plus-lg"></i> Baru
                        </button>
                    </div>
                </div>

                <!-- Notes Body: List + Editor split -->
                <div style="display:flex;flex:1;overflow:hidden;background:#fafafa;">
                    <!-- Note List (left) -->
                    <div id="notes-list-panel" style="width:200px;flex-shrink:0;border-right:1px solid #f1f5f9;overflow-y:auto;background:#fafafa;">
                        <div id="notes-list-inner" style="padding:8px 6px;"></div>
                    </div>

                    <!-- Note Editor (right) -->
                    <div id="notes-editor-panel" style="flex:1;display:flex;flex-direction:column;overflow:hidden;background:#ffffff;">
                        <!-- Editor Toolbar -->
                        <div id="notes-editor-toolbar" style="display:none;padding:8px 14px;border-bottom:1px solid #f1f5f9;background:#fffdfa;flex-shrink:0;">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <!-- Color picker -->
                                    <div class="d-flex gap-1">
                                        <?php
                                        $noteColors = ['#f59e0b','#ef4444','#3b82f6','#10b981','#8b5cf6','#64748b'];
                                        foreach ($noteColors as $c): ?>
                                        <button type="button" class="note-color-dot" data-color="<?= $c ?>" onclick="setNoteColor('<?= $c ?>')"
                                            style="width:16px;height:16px;border-radius:50%;border:2px solid transparent;background:<?= $c ?>;cursor:pointer;transition:transform 0.15s;"></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <span style="font-size:0.65rem;color:#cbd5e1;">|</span>
                                    <button onclick="togglePinNote()" id="btn-pin-note" title="Pin catatan" style="background:none;border:none;cursor:pointer;padding:2px 4px;border-radius:4px;font-size:0.78rem;color:#94a3b8;transition:color 0.15s;">
                                        <i class="bi bi-pin"></i>
                                    </button>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span id="note-word-count" style="font-size:0.65rem;color:#94a3b8;"></span>
                                    <span id="note-save-status" style="font-size:0.65rem;color:#10b981;font-weight:700;"></span>
                                    <button onclick="deleteCurrentNote()" title="Hapus catatan" style="background:none;border:none;cursor:pointer;padding:2px 4px;border-radius:4px;font-size:0.78rem;color:#ef4444;opacity:0.6;transition:opacity 0.15s;" onmouseenter="this.style.opacity='1'" onmouseleave="this.style.opacity='0.6'">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Editor Fields -->
                        <div style="flex:1;display:flex;flex-direction:column;padding:14px 16px;overflow:hidden;" id="notes-editor-fields">
                            <!-- Empty State -->
                            <div id="notes-empty-state" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;text-align:center;">
                                <div style="width:52px;height:52px;border-radius:16px;background:#f8fafc;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                                    <i class="bi bi-journal-text" style="font-size:1.5rem;color:#cbd5e1;"></i>
                                </div>
                                <p style="font-size:0.82rem;font-weight:700;color:#334155;margin:0 0 4px;">Pilih atau buat catatan</p>
                                <p style="font-size:0.72rem;margin:0;color:#94a3b8;">Catatan hanya bisa dilihat oleh kamu</p>
                            </div>
                            <!-- Title input (hidden until note selected) -->
                            <input id="note-title-input" type="text" placeholder="Judul catatan..." maxlength="255"
                                style="display:none;border:none;outline:none;font-size:0.95rem;font-weight:700;color:#0a0a0a;padding:0 0 8px;border-bottom:1px solid #f1f5f9;margin-bottom:10px;width:100%;background:transparent;"
                                oninput="scheduleAutoSave()">
                            <!-- Content textarea -->
                            <textarea id="note-content-input" placeholder="Mulai menulis... ✍️" 
                                style="display:none;border:none;outline:none;flex:1;resize:none;font-size:0.83rem;line-height:1.7;color:#334155;width:100%;background:transparent;min-height:160px;"
                                oninput="onNoteContentInput()"></textarea>
                            <div id="note-meta-footer" style="display:none;padding-top:8px;border-top:1px solid #f8fafc;flex-shrink:0;">
                                <span id="note-updated-at" style="font-size:0.65rem;color:#94a3b8;"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         ADMIN CONTROL CENTER BANNER
         ═══════════════════════════════════════════════════ -->
    <?php if($user['role'] === 'admin'): ?>
    <div class="row mb-4 reveal">
        <div class="col-12">
            <div class="admin-banner">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,rgba(245,158,11,0.2),rgba(217,119,6,0.3));border:1px solid rgba(245,158,11,0.4);display:flex;align-items:center;justify-content:center;color:#fbbf24;font-size:1.35rem;box-shadow:0 4px 16px rgba(245,158,11,0.25);">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <div>
                        <h5>Admin Control Center</h5>
                        <p>Manage employee access, database privileges, and security audit logs.</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="data-karyawan.php" class="btn-admin-primary">
                        <i class="bi bi-person-gear me-1"></i>Kelola Akses
                    </a>
                    <a href="log.php" class="btn-admin-secondary">
                        <i class="bi bi-journal-text me-1"></i>Audit Log
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════
         YOUR APPLICATIONS GRID (Bento Tickets)
         ═══════════════════════════════════════════════════ -->
    <div class="row mb-3 reveal">
        <div class="col-12 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b;"></div>
                <h5 class="fw-bold text-dark m-0" style="letter-spacing:-0.02em;font-size:1.05rem;">Your Applications</h5>
            </div>
            <span style="font-size:0.75rem;color:#94a3b8;font-weight:600;">Single Sign-On (SSO) Portal</span>
        </div>
    </div>

    <div class="row g-3 pb-5 reveal">
        
        <!-- Salary -->
        <div class="col-md-6 col-xl-4">
            <a href="https://ssll.id-giti.com/" target="_blank" class="app-ticket">
                <div class="app-icon-squircle color-salary">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="app-body">
                    <div class="app-name">Salary</div>
                    <div class="app-desc">Slip Gaji & Kompensasi</div>
                </div>
                <div class="app-arrow"><i class="bi bi-arrow-up-right"></i></div>
            </a>
        </div>
        
        <!-- Bukti -->
        <?php if ($user['app_bukti']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="<?= getSSOLink($conn, $user['id'], 'https://center.id-giti.com/bukti/auth-sso.php') ?>" target="_blank" class="app-ticket">
                <div class="app-icon-squircle color-bukti">
                    <i class="bi bi-journal-check"></i>
                </div>
                <div class="app-body">
                    <div class="app-name">Bukti</div>
                    <div class="app-desc">Timeline Kerja & Progress</div>
                </div>
                <div class="app-arrow"><i class="bi bi-arrow-up-right"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <!-- Sales -->
        <?php if ($user['app_sales']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="https://sales.id-giti.com/center-login.php?nama=<?= htmlspecialchars($user['name']) ?>" target="_blank" class="app-ticket">
                <div class="app-icon-squircle color-sales">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="app-body">
                    <div class="app-name">Sales</div>
                    <div class="app-desc">Target Penjualan & Omset</div>
                </div>
                <div class="app-arrow"><i class="bi bi-arrow-up-right"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <!-- Quotation -->
        <?php if ($user['app_quotation']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="https://quo.id-giti.com/center-login.php?nama=<?= htmlspecialchars($user['name']) ?>" target="_blank" class="app-ticket">
                <div class="app-icon-squircle color-quotation">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                </div>
                <div class="app-body">
                    <div class="app-name">Quotation</div>
                    <div class="app-desc">Pembuatan Penawaran Harga</div>
                </div>
                <div class="app-arrow"><i class="bi bi-arrow-up-right"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <!-- Service [OLD] -->
        <?php if ($user['app_produksi']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="https://service.id-giti.com/src/html/process/center-login.php?nama=<?= htmlspecialchars($user['name']) ?>" class="app-ticket" target="_blank">
                <div class="app-icon-squircle color-service-old">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
                <div class="app-body">
                    <div class="app-name">Service [OLD]</div>
                    <div class="app-desc">Web Service & RMA Lama</div>
                </div>
                <div class="app-arrow"><i class="bi bi-arrow-up-right"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <!-- Service & Production [NEW] -->
        <?php if ($user['app_service']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="<?= getSSOLink($conn, $user['id'], 'https://center.id-giti.com/service/auth-sso.php') ?>" target="_blank" class="app-ticket">
                <div class="app-icon-squircle color-service-new">
                    <i class="bi bi-wrench-adjustable-circle-fill"></i>
                </div>
                <div class="app-body">
                    <div class="app-name">Service & Production</div>
                    <div class="app-desc">Perbaikan Unit & Perakitan</div>
                </div>
                <div class="app-arrow"><i class="bi bi-arrow-up-right"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <!-- Teknisi -->
        <?php if ($user['app_teknisi']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="https://jadwal.id-giti.com/center-login.php?nama=<?= htmlspecialchars($user['name']) ?>" target="_blank" class="app-ticket">
                <div class="app-icon-squircle color-teknisi">
                    <i class="bi bi-tools"></i>
                </div>
                <div class="app-body">
                    <div class="app-name">Teknisi</div>
                    <div class="app-desc">Manajemen Jadwal Lapangan</div>
                </div>
                <div class="app-arrow"><i class="bi bi-arrow-up-right"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <!-- Garansi -->
        <?php if ($user['app_giti']): ?>
        <div class="col-md-6 col-xl-4">
            <a href="https://id-giti.com/admin/dashboard" target="_blank" class="app-ticket">
                <div class="app-icon-squircle color-garansi">
                    <i class="bi bi-qr-code-scan"></i>
                </div>
                <div class="app-body">
                    <div class="app-name">Garansi</div>
                    <div class="app-desc">Validasi & Garansi Produk</div>
                </div>
                <div class="app-arrow"><i class="bi bi-arrow-up-right"></i></div>
            </a>
        </div>
        <?php endif; ?>
        
    </div> 
</div> 

<!-- ═══════════════════════════════════════════════════
     JAVASCRIPT SUITE
     ═══════════════════════════════════════════════════ -->
<script>
    // === LIVE CLOCK ===
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const clockEl = document.getElementById('clock');
        const secEl = document.getElementById('clockSec');
        if (clockEl) {
            clockEl.childNodes[0].textContent = `${hours}:${minutes}`;
        }
        if (secEl) {
            secEl.textContent = `:${seconds}`;
        }
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
                if (!el) return;
                if (!res.data || res.data.length === 0) {
                    el.innerHTML = `<div class="text-center py-3" style="color:#94a3b8;">
                        <i class="bi bi-megaphone" style="font-size:1.3rem;opacity:0.35;"></i>
                        <p style="font-size:0.75rem;margin:4px 0 0;">Belum ada pengumuman</p>
                    </div>`;
                    return;
                }
                let html = '';
                res.data.forEach(a => {
                    const pColors = {urgent:'#ef4444',important:'#d97706',normal:'#64748b'};
                    const pLabels = {urgent:'URGENT',important:'PENTING',normal:''};
                    const pBg = {urgent:'#fef2f2',important:'#fffbeb',normal:'transparent'};
                    html += `<div style="padding:8px 10px;border-radius:10px;margin-bottom:4px;background:#f8fafc;cursor:default;transition:all 0.15s;" 
                                onmouseenter="this.style.background='#f1f5f9'" onmouseleave="this.style.background='#f8fafc'">
                        <div class="d-flex justify-content-between align-items-start">
                            <div style="flex:1;min-width:0;">
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    ${a.priority!=='normal'?`<span style="font-size:0.58rem;font-weight:800;color:${pColors[a.priority]};background:${pBg[a.priority]};padding:1px 6px;border-radius:4px;letter-spacing:0.5px;">${pLabels[a.priority]}</span>`:''}
                                    <span style="font-weight:700;font-size:0.8rem;color:#0a0a0a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${a.title}</span>
                                </div>
                                <p style="font-size:0.72rem;color:#64748b;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${a.content}</p>
                                <div style="font-size:0.62rem;color:#94a3b8;margin-top:2px;">
                                    ${a.author_name} · ${timeAgo(a.created_at)}
                                    ${isAdmin ? ` · <span style="color:${a.target_all==='1'||a.target_all===1?'#059669':'#d97706'};font-weight:600;">
                                        <i class="bi bi-${a.target_all==='1'||a.target_all===1?'people-fill':'person-check'}"></i> 
                                        ${a.target_all==='1'||a.target_all===1?'Semua':(a.recipient_names||'Spesifik')}
                                    </span>` : ''}
                                </div>
                            </div>
                            ${isAdmin?`<button onclick="deleteAnnouncement(${a.id})" class="btn btn-sm p-0 ms-2" style="color:#cbd5e1;font-size:0.7rem;border:none;background:none;" title="Hapus"><i class="bi bi-x-lg"></i></button>`:''}
                        </div>
                    </div>`;
                });
                el.innerHTML = html;
            })
            .catch(() => {
                const el = document.getElementById('announcementList');
                if (el) el.innerHTML = '<div class="text-center py-3" style="color:#94a3b8;font-size:0.75rem;">Gagal memuat</div>';
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

    let annUsers = [];
    let annSelectedUsers = new Set();
    let annTargetAll = true;

    function showCreateAnnouncement() {
        let modal = document.getElementById('createAnnouncementModal');
        if (!modal) {
            const div = document.createElement('div');
            div.innerHTML = `
            <div class="modal fade" id="createAnnouncementModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                        <div class="modal-header border-0 px-4 pt-4 pb-2">
                            <div>
                                <h6 class="fw-bold mb-0" style="color:#0a0a0a;"><i class="bi bi-megaphone-fill me-2" style="color:#d97706;"></i>Buat Pengumuman</h6>
                                <small style="color:#94a3b8;">Pilih siapa yang menerima pengumuman ini</small>
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
                            
                            <div class="mb-3">
                                <label style="font-size:0.7rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:0.5px;">Penerima</label>
                                <div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;background:#fafafa;">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#d97706,#f59e0b);display:flex;align-items:center;justify-content:center;">
                                                <i class="bi bi-people-fill" style="color:white;font-size:0.7rem;"></i>
                                            </div>
                                            <span style="font-weight:600;font-size:0.82rem;">Semua Karyawan</span>
                                        </div>
                                        <label style="position:relative;width:42px;height:22px;cursor:pointer;">
                                            <input type="checkbox" id="annTargetAll" checked onchange="toggleTargetAll(this.checked)" style="display:none;">
                                            <div id="annToggleTrack" style="width:42px;height:22px;border-radius:11px;background:#d97706;transition:background 0.2s;position:absolute;top:0;left:0;"></div>
                                            <div id="annToggleThumb" style="width:18px;height:18px;border-radius:50%;background:white;position:absolute;top:2px;left:22px;transition:left 0.2s;box-shadow:0 1px 3px rgba(0,0,0,0.2);"></div>
                                        </label>
                                    </div>
                                    
                                    <div id="annUserPicker" style="display:none;">
                                        <div style="position:relative;margin-bottom:8px;">
                                            <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.75rem;"></i>
                                            <input type="text" id="annUserSearch" placeholder="Cari karyawan..." oninput="filterAnnUsers(this.value)" style="width:100%;border:1px solid #e2e8f0;border-radius:8px;padding:6px 10px 6px 30px;font-size:0.78rem;outline:none;">
                                        </div>
                                        <div id="annSelectedPills" style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px;"></div>
                                        <div id="annUserList" style="max-height:150px;overflow-y:auto;border:1px solid #f1f5f9;border-radius:8px;background:white;">
                                            <div class="text-center py-2"><div class="spinner-border spinner-border-sm text-warning"></div></div>
                                        </div>
                                    </div>
                                </div>
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
                                <i class="bi bi-send-fill me-1"></i>Kirim Pengumuman
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
            document.body.appendChild(div);
            modal = document.getElementById('createAnnouncementModal');
        }
        
        annTargetAll = true;
        annSelectedUsers.clear();
        document.getElementById('annTargetAll').checked = true;
        document.getElementById('annToggleTrack').style.background = '#d97706';
        document.getElementById('annToggleThumb').style.left = '22px';
        document.getElementById('annUserPicker').style.display = 'none';
        document.getElementById('annSelectedPills').innerHTML = '';
        
        if (annUsers.length === 0) {
            fetch('api_announcement.php?action=list_users')
                .then(r => r.json())
                .then(res => { if (res.status === 'success') { annUsers = res.data; renderAnnUserList(); } });
        }
        
        new bootstrap.Modal(modal).show();
    }

    function toggleTargetAll(checked) {
        annTargetAll = checked;
        document.getElementById('annToggleTrack').style.background = checked ? '#d97706' : '#cbd5e1';
        document.getElementById('annToggleThumb').style.left = checked ? '22px' : '2px';
        document.getElementById('annUserPicker').style.display = checked ? 'none' : 'block';
        if (!checked && annUsers.length > 0) renderAnnUserList();
    }

    function renderAnnUserList(filter = '') {
        const list = document.getElementById('annUserList');
        const filtered = annUsers.filter(u => u.name.toLowerCase().includes(filter.toLowerCase()));
        
        if (filtered.length === 0) {
            list.innerHTML = '<div class="text-center py-2" style="color:#94a3b8;font-size:0.78rem;">Tidak ditemukan</div>';
            return;
        }
        
        list.innerHTML = filtered.map(u => `
            <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;border-bottom:1px solid #f8fafc;transition:background 0.15s;font-size:0.82rem;" 
                   onmouseenter="this.style.background='#fefce8'" onmouseleave="this.style.background='white'">
                <input type="checkbox" value="${u.id}" ${annSelectedUsers.has(u.id) ? 'checked' : ''} 
                       onchange="toggleAnnUser(${u.id}, '${u.name.replace(/'/g, "\\'")}', this.checked)"
                       style="accent-color:#d97706;width:16px;height:16px;cursor:pointer;">
                <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:white;font-size:0.65rem;font-weight:700;">
                    ${u.name.charAt(0).toUpperCase()}
                </div>
                <div style="flex:1;">
                    <div style="font-weight:600;color:#1e293b;line-height:1.2;">${u.name}</div>
                    <div style="font-size:0.68rem;color:#94a3b8;">${u.role === 'admin' ? 'Admin' : 'Karyawan'}</div>
                </div>
            </label>
        `).join('');
        
        updateSelectedPills();
    }

    function toggleAnnUser(id, name, checked) {
        if (checked) {
            annSelectedUsers.add(id);
        } else {
            annSelectedUsers.delete(id);
        }
        updateSelectedPills();
    }

    function updateSelectedPills() {
        const container = document.getElementById('annSelectedPills');
        if (annSelectedUsers.size === 0) {
            container.innerHTML = '<span style="font-size:0.72rem;color:#94a3b8;font-style:italic;">Belum ada yang dipilih</span>';
            return;
        }
        
        const pills = [];
        annSelectedUsers.forEach(id => {
            const user = annUsers.find(u => u.id === id);
            if (user) {
                pills.push(`<span style="display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,rgba(217,119,6,0.1),rgba(245,158,11,0.1));color:#92400e;border:1px solid rgba(217,119,6,0.2);border-radius:20px;padding:3px 10px 3px 8px;font-size:0.72rem;font-weight:600;">
                    ${user.name}
                    <i class="bi bi-x" style="cursor:pointer;font-size:0.8rem;" onclick="removeAnnUser(${id})"></i>
                </span>`);
            }
        });
        container.innerHTML = pills.join('');
    }

    function removeAnnUser(id) {
        annSelectedUsers.delete(id);
        renderAnnUserList(document.getElementById('annUserSearch')?.value || '');
    }

    function filterAnnUsers(q) {
        renderAnnUserList(q);
    }

    function submitAnnouncement() {
        const fd = new FormData();
        fd.append('action', 'create');
        fd.append('title', document.getElementById('annTitle').value);
        fd.append('content', document.getElementById('annContent').value);
        fd.append('priority', document.getElementById('annPriority').value);
        fd.append('expires_at', document.getElementById('annExpires').value);
        fd.append('target_all', annTargetAll ? '1' : '0');
        
        if (!annTargetAll) {
            fd.append('recipients', JSON.stringify([...annSelectedUsers]));
        }

        fetch('api_announcement.php', {method:'POST', body: fd})
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('createAnnouncementModal')).hide();
                    document.getElementById('annTitle').value = '';
                    document.getElementById('annContent').value = '';
                    annSelectedUsers.clear();
                    loadAnnouncements();
                    alert(res.message);
                } else {
                    alert(res.message);
                }
            });
    }

    // === CALENDAR SYSTEM ===
    const MONTHS = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const DAYS = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
    let calMonth = <?= (int)date('m') - 1 ?>;
    let calYear = <?= (int)date('Y') ?>;
    let selectedDate = '<?= date('Y-m-d') ?>';
    let monthEvents = [];

    function calNav(dir) {
        calMonth += dir;
        if (calMonth > 11) { calMonth = 0; calYear++; }
        if (calMonth < 0) { calMonth = 11; calYear--; }
        const grid = document.getElementById('calGrid');
        if (grid) {
            grid.classList.remove('cal-slide-left', 'cal-slide-right');
            void grid.offsetWidth;
            grid.classList.add(dir > 0 ? 'cal-slide-left' : 'cal-slide-right');
        }
        renderCalendar();
    }

    function renderCalendar() {
        const myEl = document.getElementById('calMonthYear');
        if (myEl) myEl.textContent = MONTHS[calMonth] + ' ' + calYear;
        const grid = document.getElementById('calGrid');
        if (!grid) return;
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
            const dateEvents = monthEvents.filter(ev => {
                const end = ev.end_date || ev.event_date;
                return dateStr >= ev.event_date && dateStr <= end;
            });
            const eventColors = [...new Set(dateEvents.map(ev => ev.color))].slice(0, 3);
            
            cell.style.cssText = `padding:4px;cursor:pointer;border-radius:10px;transition:all 0.15s;position:relative;`;
            cell.dataset.date = dateStr;
            
            let bg = 'transparent', color = '#334155', fw = '600';
            if (isSelected) { bg = '#d97706'; color = 'white'; fw = '800'; }
            else if (isToday) { bg = '#fef3c7'; color = '#92400e'; fw = '800'; }
            
            const dotsHtml = eventColors.length > 0 
                ? `<div class="cal-dots" style="display:flex;justify-content:center;gap:2px;margin-top:2px;">${eventColors.map(c => `<div style="width:4px;height:4px;border-radius:50%;background:${c};"></div>`).join('')}</div>` 
                : '<div class="cal-dots" style="height:6px;"></div>';
            
            cell.innerHTML = `
                <div style="width:30px;height:30px;line-height:30px;margin:auto;border-radius:9px;font-size:0.78rem;font-weight:${fw};color:${color};background:${bg};transition:all 0.15s;">${d}</div>
                ${dotsHtml}
            `;
            
            cell.onclick = () => selectDate(dateStr);
            if (!isSelected) {
                cell.onmouseenter = () => { if(!isToday) cell.querySelector('div').style.background='#f1f5f9'; };
                cell.onmouseleave = () => { if(!isToday) cell.querySelector('div').style.background=bg; };
            }
            grid.appendChild(cell);
        }
        
        fetchMonthEvents();
    }

    function selectDate(dateStr) {
        selectedDate = dateStr;
        renderCalendar();
    }

    function fetchMonthEvents() {
        fetch(`api_calendar.php?action=fetch&month=${calMonth+1}&year=${calYear}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    monthEvents = res.data;
                    updateDots();
                }
            })
            .catch(() => {});
    }

    function updateDots() {
        document.querySelectorAll('#calGrid [data-date]').forEach(cell => {
            const dateStr = cell.dataset.date;
            const dateEvents = monthEvents.filter(ev => {
                const end = ev.end_date || ev.event_date;
                return dateStr >= ev.event_date && dateStr <= end;
            });
            const eventColors = [...new Set(dateEvents.map(ev => ev.color))].slice(0, 3);
            const dotContainer = cell.querySelector('.cal-dots');
            if (dotContainer) {
                dotContainer.innerHTML = eventColors.length > 0
                    ? eventColors.map(c => `<div style="width:4px;height:4px;border-radius:50%;background:${c};"></div>`).join('')
                    : '';
                dotContainer.style.height = eventColors.length > 0 ? 'auto' : '6px';
            }
        });
    }

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

    // === CATATAN PRIBADI (PERSONAL NOTES STUDIO) ===
    let notesData      = [];
    let curNoteId      = null;
    let noteAutoSaveT  = null;
    let noteColor      = '#f59e0b';
    let searchTimer    = null;

    function loadNotes(q = '') {
        fetch(`api_notes.php?action=fetch&q=${encodeURIComponent(q)}`)
        .then(r => r.json()).then(res => {
            notesData = res.notes || [];
            renderNotesList();
            const c = notesData.length;
            const countEl = document.getElementById('notes-count');
            if (countEl) countEl.textContent = c ? `${c} catatan` : '';
        }).catch(() => {});
    }

    function renderNotesList() {
        const el = document.getElementById('notes-list-inner');
        if (!el) return;
        if (!notesData.length) {
            el.innerHTML = `<div style="text-align:center;padding:24px 8px;color:#94a3b8;font-size:0.72rem;">
                <i class="bi bi-journal-x" style="font-size:1.4rem;display:block;margin-bottom:6px;opacity:0.5;"></i>Belum ada catatan</div>`;
            return;
        }
        el.innerHTML = notesData.map(n => {
            const active = n.id == curNoteId ? 'background:#fff7ed;border-left:3px solid ' + n.color + ';' : 'border-left:3px solid transparent;';
            return `<div class="note-item" onclick="openNote(${n.id})" data-id="${n.id}"
                style="cursor:pointer;padding:8px 8px 8px 10px;border-radius:8px;margin-bottom:3px;transition:all 0.15s;${active}"
                onmouseenter="if(${n.id}!=curNoteId) this.style.background='#f1f5f9'" 
                onmouseleave="if(${n.id}!=curNoteId) this.style.background='transparent'">
                <div style="display:flex;align-items:center;gap:4px;margin-bottom:2px;">
                    ${n.is_pinned ? `<i class="bi bi-pin-fill" style="color:${n.color};font-size:0.6rem;"></i>` : ''}
                    <span style="font-size:0.78rem;font-weight:700;color:#0a0a0a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;">${escHtml(n.title || 'Tanpa Judul')}</span>
                </div>
                ${n.preview ? `<div style="font-size:0.68rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:155px;">${escHtml(n.preview)}</div>` : ''}
                <div style="font-size:0.6rem;color:#94a3b8;margin-top:3px;">${n.updated_fmt}</div>
            </div>`;
        }).join('');
    }

    function escHtml(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

    function openNote(id) {
        if (curNoteId && curNoteId !== id) saveNote(curNoteId, true);
        curNoteId = id;
        const n = notesData.find(x => x.id == id);
        if (!n) return;
        noteColor = n.color || '#f59e0b';
        showEditor(true);
        document.getElementById('note-title-input').value   = n.title;
        document.getElementById('note-content-input').value = n.content || '';
        document.getElementById('note-updated-at').textContent = 'Diperbarui: ' + n.updated_fmt;
        document.getElementById('note-save-status').textContent = '';
        updateWordCount();
        updateColorDots(noteColor);
        const pinBtn = document.getElementById('btn-pin-note');
        if (pinBtn) {
            pinBtn.dataset.pinned = n.is_pinned ? '1' : '0';
            pinBtn.style.color = n.is_pinned ? '#d97706' : '#94a3b8';
            pinBtn.innerHTML = n.is_pinned ? '<i class="bi bi-pin-fill"></i>' : '<i class="bi bi-pin"></i>';
        }
        renderNotesList();
    }

    function showEditor(show) {
        const toolbar = document.getElementById('notes-editor-toolbar');
        const empty   = document.getElementById('notes-empty-state');
        const title   = document.getElementById('note-title-input');
        const content = document.getElementById('note-content-input');
        const footer  = document.getElementById('note-meta-footer');
        if (toolbar) toolbar.style.display = show ? 'block'  : 'none';
        if (empty)   empty.style.display   = show ? 'none'   : 'flex';
        if (title)   title.style.display   = show ? 'block'  : 'none';
        if (content) content.style.display = show ? 'block'  : 'none';
        if (footer)  footer.style.display  = show ? 'block'  : 'none';
        if (show && title) { title.focus(); }
    }

    function createNewNote() {
        if (curNoteId) saveNote(curNoteId, true);
        fetch('api_notes.php', {method:'POST', body: new URLSearchParams({action:'create',title:'Catatan Baru',content:'',color:noteColor})})
        .then(r => r.json()).then(res => {
            if (res.status === 'success') {
                loadNotes();
                setTimeout(() => {
                    openNote(res.id);
                    const titleEl = document.getElementById('note-title-input');
                    if (titleEl) titleEl.select();
                }, 200);
            }
        });
    }

    function scheduleAutoSave() {
        clearTimeout(noteAutoSaveT);
        const statusEl = document.getElementById('note-save-status');
        if (statusEl) statusEl.textContent = '💾 Menyimpan...';
        noteAutoSaveT = setTimeout(() => { if (curNoteId) saveNote(curNoteId); }, 1000);
    }

    function onNoteContentInput() {
        updateWordCount();
        scheduleAutoSave();
    }

    function saveNote(id, silent = false) {
        const titleEl   = document.getElementById('note-title-input');
        const contentEl = document.getElementById('note-content-input');
        if (!titleEl || !contentEl) return;
        const title   = titleEl.value.trim() || 'Catatan Tanpa Judul';
        const content = contentEl.value;
        const fd = new URLSearchParams({action:'update', id, title, content, color: noteColor});
        fetch('api_notes.php', {method:'POST', body: fd})
        .then(r => r.json()).then(res => {
            if (!silent && res.status === 'success') {
                const statusEl = document.getElementById('note-save-status');
                if (statusEl) statusEl.textContent = '✓ Tersimpan';
                const now = new Date();
                const updatedEl = document.getElementById('note-updated-at');
                if (updatedEl) {
                    updatedEl.textContent = 'Diperbarui: ' + now.toLocaleString('id-ID',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
                }
                setTimeout(() => { if (statusEl) statusEl.textContent = ''; }, 2000);
                const n = notesData.find(x => x.id == id);
                if (n) { n.title = title; n.content = content; n.color = noteColor; n.preview = content.substring(0,80); }
                renderNotesList();
            }
        });
    }

    function updateWordCount() {
        const contentEl = document.getElementById('note-content-input');
        if (!contentEl) return;
        const txt = contentEl.value;
        const words = txt.trim() ? txt.trim().split(/\s+/).length : 0;
        const chars = txt.length;
        const countEl = document.getElementById('note-word-count');
        if (countEl) countEl.textContent = words ? `${words} kata · ${chars} kar` : '';
    }

    function setNoteColor(c) {
        noteColor = c;
        updateColorDots(c);
        scheduleAutoSave();
        renderNotesList();
    }

    function updateColorDots(active) {
        document.querySelectorAll('.note-color-dot').forEach(d => {
            d.style.border = d.dataset.color === active ? `2px solid ${d.dataset.color}` : '2px solid transparent';
            d.style.transform = d.dataset.color === active ? 'scale(1.3)' : 'scale(1)';
        });
    }

    function togglePinNote() {
        if (!curNoteId) return;
        fetch('api_notes.php', {method:'POST', body: new URLSearchParams({action:'pin', id: curNoteId})})
        .then(r => r.json()).then(() => {
            loadNotes();
            setTimeout(() => openNote(curNoteId), 250);
        });
    }

    function deleteCurrentNote() {
        if (!curNoteId) return;
        const n = notesData.find(x => x.id == curNoteId);
        if (!confirm(`Hapus catatan "${n?.title || ''}"?`)) return;
        fetch('api_notes.php', {method:'POST', body: new URLSearchParams({action:'delete', id: curNoteId})})
        .then(() => {
            curNoteId = null;
            showEditor(false);
            loadNotes();
        });
    }

    function searchNotes(q) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadNotes(q), 300);
    }

    loadNotes();
    window.addEventListener('beforeunload', () => { if (curNoteId) saveNote(curNoteId, true); });

    // === SCROLL REVEAL ===
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.05, rootMargin: '0px 0px -40px 0px' });
    
    document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>