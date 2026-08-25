<?php 
require_once 'includes/db.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

$current_user_id = $_SESSION['user_id'];
$period = $_GET['period'] ?? 'all_time';
$custom_start = $_GET['start'] ?? '';
$custom_end = $_GET['end'] ?? '';

// Build date filtering based on selected period
$date_job_cond = "";
$date_prog_cond = "";

if ($period === 'this_month') {
    $date_job_cond = " AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $date_prog_cond = " AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $period_label = "Bulan Ini (" . date('F Y') . ")";
} elseif ($period === 'last_month') {
    $date_job_cond = " AND MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
    $date_prog_cond = " AND MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
    $period_label = "Bulan Lalu (" . date('F Y', strtotime('-1 month')) . ")";
} elseif ($period === 'custom' && !empty($custom_start) && !empty($custom_end)) {
    $date_job_cond = " AND DATE(created_at) >= '$custom_start' AND DATE(created_at) <= '$custom_end'";
    $date_prog_cond = " AND DATE(created_at) >= '$custom_start' AND DATE(created_at) <= '$custom_end'";
    $period_label = date('d M Y', strtotime($custom_start)) . " — " . date('d M Y', strtotime($custom_end));
} else {
    $period = 'all_time';
    $period_label = "Sepanjang Waktu (All Time)";
}

// Fetch complete KPI data per user
$sql = "SELECT 
    u.id, 
    u.name, 
    u.avatar, 
    u.nickname, 
    u.jabatan,
    u.division,
    COALESCE(j_created.total_created, 0) as total_created,
    COALESCE(j_done.total_done, 0) as total_done,
    COALESCE(p_updates.total_updates, 0) as total_updates
FROM users u
LEFT JOIN (
    SELECT user_id, COUNT(*) as total_created 
    FROM bukti_jobs 
    WHERE deleted_at IS NULL $date_job_cond
    GROUP BY user_id
) j_created ON u.id = j_created.user_id
LEFT JOIN (
    SELECT user_id, COUNT(*) as total_done 
    FROM bukti_jobs 
    WHERE deleted_at IS NULL AND status = 'done' $date_job_cond
    GROUP BY user_id
) j_done ON u.id = j_done.user_id
LEFT JOIN (
    SELECT user_id, COUNT(*) as total_updates 
    FROM bukti_job_progress 
    WHERE 1=1 $date_prog_cond
    GROUP BY user_id
) p_updates ON u.id = p_updates.user_id
ORDER BY (COALESCE(j_created.total_created, 0)*15 + COALESCE(p_updates.total_updates, 0)*20 + COALESCE(j_done.total_done, 0)*25) DESC, COALESCE(p_updates.total_updates, 0) DESC";

$stmt = $conn->query($sql);
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall summary metrics
$grand_total_created = 0;
$grand_total_updates = 0;
$grand_total_done = 0;

$top_updater = null;
$top_creator = null;
$top_finisher = null;
$mvp_user = null;

$chart_names = [];
$chart_created = [];
$chart_updates = [];
$chart_done = [];
$chart_kpi = [];

foreach ($leaderboard as $idx => &$row) {
    $created = (int)$row['total_created'];
    $updates = (int)$row['total_updates'];
    $done    = (int)$row['total_done'];
    
    // KPI Formula: (Created * 15) + (Updates * 20) + (Done * 25)
    $kpi_score = ($created * 15) + ($updates * 20) + ($done * 25);
    $completion_rate = $created > 0 ? min(100, round(($done / $created) * 100)) : ($updates > 0 ? 100 : 0);
    
    $row['kpi_score'] = $kpi_score;
    $row['completion_rate'] = $completion_rate;
    
    // Avatar processing
    $avatar_file = $row['avatar'] ?? '';
    $row['avatar_url'] = (!empty($avatar_file) && file_exists(__DIR__ . "/assets/img/avatars/" . $avatar_file)) 
        ? "assets/img/avatars/" . $avatar_file 
        : "https://ui-avatars.com/api/?name=" . urlencode($row['name']) . "&background=f59e0b&color=ffffff&bold=true";
    
    $grand_total_created += $created;
    $grand_total_updates += $updates;
    $grand_total_done += $done;
    
    // Track Category Champions
    if ($top_updater === null || $updates > (int)$top_updater['total_updates']) {
        if ($updates > 0) $top_updater = $row;
    }
    if ($top_creator === null || $created > (int)$top_creator['total_created']) {
        if ($created > 0) $top_creator = $row;
    }
    if ($top_finisher === null || $done > (int)$top_finisher['total_done']) {
        if ($done > 0) $top_finisher = $row;
    }
    if ($idx === 0 && $kpi_score > 0) {
        $mvp_user = $row;
    }
    
    // Top 8 for Chart
    if ($idx < 8 && ($created > 0 || $updates > 0 || $done > 0)) {
        $chart_names[] = explode(' ', $row['name'])[0];
        $chart_created[] = $created;
        $chart_updates[] = $updates;
        $chart_done[] = $done;
        $chart_kpi[] = $kpi_score;
    }
}
unset($row);

$overall_completion_rate = $grand_total_created > 0 ? round(($grand_total_done / $grand_total_created) * 100) : 0;
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* ══════════════════════════════════════════════════════════════
       TASTE SKILL 3D VIBRANT ANALYTICS & ISOMETRIC PODIUM
       Luminous Aurora Canvas • 3D Bento Cards • 3D Stepped Holographic Stage
       ══════════════════════════════════════════════════════════════ */
    
    :root {
        --ts-bg-canvas: #faf6f0;
        --ts-card-white: #ffffff;
        --ts-border-subtle: rgba(226, 232, 240, 0.85);
        --ts-amber-gold: #f59e0b;
        --ts-amber-glow: rgba(245, 158, 11, 0.35);
        --ts-emerald: #10b981;
        --ts-cyan: #06b6d4;
        --ts-indigo: #6366f1;
        --ts-coral: #f43f5e;
    }

    body {
        background-color: var(--ts-bg-canvas) !important;
        background-image: 
            radial-gradient(at 5% 5%, rgba(251, 191, 36, 0.22) 0px, transparent 45%),
            radial-gradient(at 95% 10%, rgba(14, 165, 233, 0.18) 0px, transparent 45%),
            radial-gradient(at 50% 40%, rgba(244, 63, 94, 0.08) 0px, transparent 50%),
            radial-gradient(at 85% 90%, rgba(16, 185, 129, 0.18) 0px, transparent 45%),
            radial-gradient(at 10% 90%, rgba(168, 85, 247, 0.14) 0px, transparent 45%) !important;
        background-attachment: fixed !important;
    }

    .main-content {
        padding-bottom: 70px;
    }

    /* ── 3D Bento KPI Cards ── */
    .ts-kpi-card {
        background: #ffffff;
        border-radius: 22px;
        border: 1px solid rgba(226, 232, 240, 0.9);
        box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(0, 0, 0, 0.02);
        transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        overflow: hidden;
    }
    .ts-kpi-card:hover {
        transform: translateY(-5px) scale(1.01);
        box-shadow: 0 16px 38px -6px rgba(15, 23, 42, 0.12), inset 0 1px 0 rgba(255, 255, 255, 1);
    }

    .card-kpi-created {
        background: linear-gradient(135deg, #e0f2fe 0%, #ffffff 55%, #f0f9ff 100%) !important;
        border: 1px solid rgba(14, 165, 233, 0.35) !important;
        box-shadow: 0 10px 28px -4px rgba(14, 165, 233, 0.18), inset 0 2px 0 #ffffff !important;
    }
    .card-kpi-updates {
        background: linear-gradient(135deg, #fef3c7 0%, #ffffff 55%, #fffbeb 100%) !important;
        border: 1px solid rgba(245, 158, 11, 0.4) !important;
        box-shadow: 0 10px 28px -4px rgba(245, 158, 11, 0.22), inset 0 2px 0 #ffffff !important;
    }
    .card-kpi-done {
        background: linear-gradient(135deg, #dcfce7 0%, #ffffff 55%, #f0fdf4 100%) !important;
        border: 1px solid rgba(16, 185, 129, 0.35) !important;
        box-shadow: 0 10px 28px -4px rgba(16, 185, 129, 0.18), inset 0 2px 0 #ffffff !important;
    }
    .card-kpi-mvp {
        background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 60%, #312e81 100%) !important;
        border: 2px solid #f59e0b !important;
        box-shadow: 0 14px 36px -6px rgba(245, 158, 11, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3) !important;
    }

    /* ══════════════════════════════════════════════════════════════
       3D ISOMETRIC STEPPED PODIUM
       ══════════════════════════════════════════════════════════════ */
    .podium-stage-wrap {
        perspective: 1200px;
        position: relative;
    }

    .podium-container {
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 20px;
        padding: 40px 10px 10px;
        transform-style: preserve-3d;
        transition: transform 0.25s ease-out;
    }

    .podium-col {
        flex: 1;
        max-width: 230px;
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        transform-style: preserve-3d;
        transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .podium-col:hover {
        transform: translateY(-10px) translateZ(30px) scale(1.03);
    }

    .podium-col.rank-1 { order: 2; z-index: 3; }
    .podium-col.rank-2 { order: 1; z-index: 2; }
    .podium-col.rank-3 { order: 3; z-index: 1; }

    /* 3D Animated Floating Trophies */
    @keyframes tsTrophyFloat {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-10px) rotate(4deg); }
    }

    @keyframes tsCrownGlow {
        0%, 100% { filter: drop-shadow(0 0 14px rgba(245, 158, 11, 0.8)) drop-shadow(0 0 28px rgba(251, 191, 36, 0.4)); }
        50% { filter: drop-shadow(0 0 24px rgba(245, 158, 11, 1)) drop-shadow(0 0 45px rgba(251, 191, 36, 0.7)); }
    }

    .crown-3d-gold {
        font-size: 2rem;
        margin-bottom: -10px;
        z-index: 10;
        animation: tsTrophyFloat 2.8s ease-in-out infinite, tsCrownGlow 2.5s ease-in-out infinite;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
    }

    .trophy-3d-gold {
        font-size: 2.8rem;
        animation: tsTrophyFloat 3s ease-in-out infinite, tsCrownGlow 2.5s ease-in-out infinite;
        display: inline-block;
        margin-bottom: 6px;
    }

    .trophy-3d-silver {
        font-size: 2.3rem;
        animation: tsTrophyFloat 3.5s ease-in-out infinite 0.4s;
        display: inline-block;
        margin-bottom: 6px;
        filter: drop-shadow(0 4px 12px rgba(100, 116, 139, 0.4));
    }

    .trophy-3d-bronze {
        font-size: 2.1rem;
        animation: tsTrophyFloat 4s ease-in-out infinite 0.8s;
        display: inline-block;
        margin-bottom: 6px;
        filter: drop-shadow(0 4px 12px rgba(194, 65, 12, 0.4));
    }

    .podium-avatar-wrap {
        position: relative;
        margin-bottom: 14px;
        z-index: 5;
    }

    .podium-avatar {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        object-fit: cover;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }

    .rank-1 .podium-avatar {
        width: 92px;
        height: 92px;
        border: 4px solid #fef08a;
        box-shadow: 0 0 0 5px rgba(245, 158, 11, 0.45), 0 16px 36px rgba(245, 158, 11, 0.45);
    }

    .rank-2 .podium-avatar {
        border: 4px solid #ffffff;
        box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.4), 0 12px 28px rgba(0, 0, 0, 0.12);
    }

    .rank-3 .podium-avatar {
        border: 4px solid #fed7aa;
        box-shadow: 0 0 0 4px rgba(234, 88, 12, 0.35), 0 10px 24px rgba(0, 0, 0, 0.12);
    }

    .podium-rank-badge {
        position: absolute;
        bottom: -6px;
        right: -4px;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.85rem;
        color: white;
        border: 2px solid white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    }

    .rank-1 .podium-rank-badge { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .rank-2 .podium-rank-badge { background: linear-gradient(135deg, #94a3b8, #475569); }
    .rank-3 .podium-rank-badge { background: linear-gradient(135deg, #ea580c, #9a3412); }

    /* ── 3D Realistic Stepped Cylindrical Pillars ── */
    .podium-block {
        width: 100%;
        border-radius: 20px 20px 16px 16px;
        padding: 18px 12px;
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
        transform-style: preserve-3d;
    }

    .rank-1 .podium-block {
        height: 200px;
        background: linear-gradient(180deg, #fef08a 0%, #f59e0b 45%, #b45309 100%);
        border: 1px solid rgba(254, 240, 138, 0.8);
        box-shadow: 0 24px 50px -10px rgba(245, 158, 11, 0.55), inset 0 3px 6px rgba(255, 255, 255, 0.95), inset 0 -6px 12px rgba(146, 64, 14, 0.45);
    }

    .rank-2 .podium-block {
        height: 155px;
        background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 45%, #64748b 100%);
        border: 1px solid rgba(255, 255, 255, 0.9);
        box-shadow: 0 18px 40px -10px rgba(100, 116, 139, 0.4), inset 0 3px 6px rgba(255, 255, 255, 0.95), inset 0 -6px 12px rgba(51, 65, 85, 0.4);
    }

    .rank-3 .podium-block {
        height: 130px;
        background: linear-gradient(180deg, #ffedd5 0%, #fb923c 45%, #c2410c 100%);
        border: 1px solid rgba(254, 215, 170, 0.8);
        box-shadow: 0 16px 36px -10px rgba(234, 88, 12, 0.35), inset 0 3px 6px rgba(255, 255, 255, 0.95), inset 0 -6px 12px rgba(154, 52, 18, 0.4);
    }

    .podium-name {
        font-weight: 800;
        font-size: 0.95rem;
        color: #ffffff;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .podium-role {
        font-size: 0.7rem;
        color: rgba(255, 255, 255, 0.9);
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        margin-bottom: 8px;
    }

    .podium-score-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 800;
        font-size: 0.82rem;
        padding: 5px 14px;
        border-radius: 9999px;
        margin: auto;
        border: 1px solid rgba(255, 255, 255, 0.6);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.8);
    }

    .rank-1 .podium-score-pill {
        background: linear-gradient(135deg, #78350f, #451a03);
        color: #fef08a;
    }

    .rank-2 .podium-score-pill {
        background: linear-gradient(135deg, #1e293b, #0f172a);
        color: #ffffff;
    }

    .rank-3 .podium-score-pill {
        background: linear-gradient(135deg, #7c2d12, #431407);
        color: #ffedd5;
    }

    /* ══════════════════════════════════════════════════════════════
       CATEGORY CHAMPIONS BENTO WIDGETS
       ══════════════════════════════════════════════════════════════ */
    .champ-card {
        background: #ffffff;
        border-radius: 18px;
        padding: 14px 18px;
        border: 1px solid rgba(226, 232, 240, 0.85);
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .champ-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08);
    }

    .champ-card-orange { background: linear-gradient(135deg, #fff7ed 0%, #ffffff 100%); border-color: rgba(249, 115, 22, 0.3); }
    .champ-card-cyan { background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%); border-color: rgba(14, 165, 233, 0.3); }
    .champ-card-green { background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); border-color: rgba(16, 185, 129, 0.3); }

    /* ══════════════════════════════════════════════════════════════
       FILTER PILL TABS
       ══════════════════════════════════════════════════════════════ */
    .period-tab-group {
        background: #ffffff;
        border: 1px solid var(--ts-border-subtle);
        border-radius: 50px;
        padding: 4px;
        display: inline-flex;
        gap: 4px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
    }

    .period-tab {
        border: none;
        background: transparent;
        color: #64748b;
        font-weight: 600;
        font-size: 0.8rem;
        padding: 6px 14px;
        border-radius: 40px;
        transition: all 0.25s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .period-tab:hover {
        color: #0f172a;
        background: #f8fafc;
    }

    .period-tab.active {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* ══════════════════════════════════════════════════════════════
       TABLE LEADERBOARD
       ══════════════════════════════════════════════════════════════ */
    .kpi-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 6px;
    }

    .kpi-table th {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #94a3b8;
        letter-spacing: 0.6px;
        padding: 8px 14px;
        border: none;
    }

    .kpi-table tr td {
        background: #ffffff;
        padding: 12px 14px;
        vertical-align: middle;
        border-top: 1px solid var(--ts-border-subtle);
        border-bottom: 1px solid var(--ts-border-subtle);
        transition: all 0.2s ease;
    }

    .kpi-table tr td:first-child {
        border-left: 1px solid var(--ts-border-subtle);
        border-top-left-radius: 14px;
        border-bottom-left-radius: 14px;
    }

    .kpi-table tr td:last-child {
        border-right: 1px solid var(--ts-border-subtle);
        border-top-right-radius: 14px;
        border-bottom-right-radius: 14px;
    }

    .kpi-table tbody tr:hover td {
        background: #fffdfa;
        border-color: rgba(245, 158, 11, 0.35);
        transform: translateY(-1px);
    }

    .main-wrapper {
        margin-left: 292px;
        padding: 24px 32px 60px;
        min-height: 100vh;
        display: block;
    }

    @media (max-width: 992px) {
        .main-wrapper {
            margin-left: 0 !important;
            padding: 16px !important;
        }
    }
</style>

<div class="main-wrapper">
    <div class="content-area">
    
    <!-- ══════════════════════════════════════════════════════════════
         TOP HEADER & PERIOD CONTROLS
         ══════════════════════════════════════════════════════════════ -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25); color: #92400e; font-size: 0.72rem; font-weight: 700;">
                <i class="bi bi-stars" style="color: #f59e0b;"></i> Team Performance & KPI System
            </div>
            <h3 class="fw-bold m-0" style="color: #0a0a0a; letter-spacing: -0.02em;">Dashboard Analytics & Leaderboard</h3>
            <p class="text-muted small m-0 mt-1">Pantau keaktifan update progres, inisiatif pekerjaan baru, dan penyelesaian tugas tim.</p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- View Switcher -->
            <div class="btn-group shadow-sm bg-white rounded-pill p-1">
                <a href="index.php?switch_mode=social" class="btn btn-sm rounded-pill px-3 text-muted"><i class="bi bi-grid-fill me-1"></i> Sosial</a>
                <a href="index.php?switch_mode=formal" class="btn btn-sm rounded-pill px-3 text-muted"><i class="bi bi-list-ul me-1"></i> Tabel</a>
                <a href="analytics.php" class="btn btn-sm rounded-pill px-3 btn-dark" style="font-weight: 600;"><i class="bi bi-trophy-fill me-1 text-warning"></i> KPI</a>
            </div>

            <!-- Period Filter -->
            <div class="period-tab-group">
                <a href="?period=this_month" class="period-tab <?= $period === 'this_month' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-check"></i> Bulan Ini
                </a>
                <a href="?period=last_month" class="period-tab <?= $period === 'last_month' ? 'active' : '' ?>">
                    <i class="bi bi-calendar2-minus"></i> Bulan Lalu
                </a>
                <a href="?period=all_time" class="period-tab <?= $period === 'all_time' ? 'active' : '' ?>">
                    <i class="bi bi-trophy"></i> Semua Waktu
                </a>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         TOP 4 METRIC BENTO CARDS
         ══════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <!-- Metric 1: Total Created -->
        <div class="col-sm-6 col-xl-3">
            <div class="ts-kpi-card card-kpi-created p-3 h-100 d-flex align-items-center gap-3">
                <div style="width: 56px; height: 56px; border-radius: 18px; background: linear-gradient(135deg, #0284c7, #0369a1); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; box-shadow: 0 8px 20px -4px rgba(2, 132, 199, 0.45); flex-shrink: 0;">
                    <i class="bi bi-journal-plus"></i>
                </div>
                <div>
                    <span style="font-size: 0.72rem; color: #0284c7; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Tugas Dibuat</span>
                    <h2 class="fw-bold m-0" style="color: #0c4a6e; letter-spacing: -0.02em; font-size: 1.9rem;"><?= $grand_total_created ?></h2>
                    <small style="font-size: 0.7rem; color: #0284c7; font-weight: 600;">Inisiatif Pekerjaan Baru</small>
                </div>
            </div>
        </div>

        <!-- Metric 2: Total Updates -->
        <div class="col-sm-6 col-xl-3">
            <div class="ts-kpi-card card-kpi-updates p-3 h-100 d-flex align-items-center gap-3">
                <div style="width: 56px; height: 56px; border-radius: 18px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; box-shadow: 0 8px 20px -4px rgba(245, 158, 11, 0.45); flex-shrink: 0;">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>
                <div>
                    <span style="font-size: 0.72rem; color: #d97706; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Update Progres</span>
                    <h2 class="fw-bold m-0" style="color: #78350f; letter-spacing: -0.02em; font-size: 1.9rem;"><?= $grand_total_updates ?></h2>
                    <small style="font-size: 0.7rem; color: #d97706; font-weight: 600;">Laporan & Bukti Kerja</small>
                </div>
            </div>
        </div>

        <!-- Metric 3: Total Completed -->
        <div class="col-sm-6 col-xl-3">
            <div class="ts-kpi-card card-kpi-done p-3 h-100 d-flex align-items-center gap-3">
                <div style="width: 56px; height: 56px; border-radius: 18px; background: linear-gradient(135deg, #10b981, #059669); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; box-shadow: 0 8px 20px -4px rgba(16, 185, 129, 0.45); flex-shrink: 0;">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <span style="font-size: 0.72rem; color: #059669; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Tugas Selesai</span>
                    <h2 class="fw-bold m-0" style="color: #064e3b; letter-spacing: -0.02em; font-size: 1.9rem;"><?= $grand_total_done ?></h2>
                    <small style="font-size: 0.7rem; color: #059669; font-weight: 600;">Rate: <?= $overall_completion_rate ?>% Terselesaikan</small>
                </div>
            </div>
        </div>

        <!-- Metric 4: MVP of the Month -->
        <div class="col-sm-6 col-xl-3">
            <div class="ts-kpi-card card-kpi-mvp p-3 h-100 d-flex align-items-center gap-3">
                <div style="width: 56px; height: 56px; border-radius: 18px; background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #0a0a0a; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; box-shadow: 0 8px 22px rgba(245, 158, 11, 0.55); flex-shrink: 0;">
                    <i class="bi bi-award-fill"></i>
                </div>
                <div style="min-width: 0;">
                    <span style="font-size: 0.7rem; color: #fbbf24; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px;">👑 Top Performer</span>
                    <h5 class="fw-bold m-0 text-truncate" style="color: white; font-size: 1rem;"><?= $mvp_user ? htmlspecialchars($mvp_user['name']) : '—' ?></h5>
                    <small style="font-size: 0.72rem; color: #fde68a; font-weight: 600;"><?= $mvp_user ? $mvp_user['kpi_score'] . ' Poin KPI' : 'Belum ada data' ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         3D PODIUM & CATEGORY CHAMPIONS BENTO
         ══════════════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <!-- 3D Podium Col -->
        <div class="col-lg-7">
            <div class="ts-kpi-card p-4 h-100 d-flex flex-column justify-content-between" style="background: linear-gradient(180deg, #ffffff 0%, #fffdf8 100%); border: 1px solid rgba(245, 158, 11, 0.3);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h5 class="fw-bold m-0" style="color: #0f172a; letter-spacing: -0.02em;">🏆 Top 3 Podium Juara</h5>
                        <small class="text-muted">Karyawan dengan kontribusi & KPI tertinggi periode <?= $period_label ?></small>
                    </div>
                    <span class="badge rounded-pill" style="background: rgba(245, 158, 11, 0.2); color: #d97706; font-size: 0.72rem; padding: 6px 14px; font-weight: 800; border: 1px solid rgba(245, 158, 11, 0.3);">
                        ✨ Live Standing
                    </span>
                </div>

                <!-- 3D Isometric Stepped Podium Elements with Parallax -->
                <div class="podium-stage-wrap" id="podiumStageWrap">
                    <div class="podium-container" id="podiumContainer">
                        
                        <!-- Rank 2: Silver (Left) -->
                        <?php if (isset($leaderboard[1]) && $leaderboard[1]['kpi_score'] > 0): $u2 = $leaderboard[1]; ?>
                        <div class="podium-col rank-2">
                            <div class="trophy-3d-silver">🥈</div>
                            <div class="podium-avatar-wrap">
                                <img src="<?= $u2['avatar_url'] ?>" class="podium-avatar" alt="Silver">
                                <div class="podium-rank-badge">2</div>
                            </div>
                            <div class="podium-block">
                                <div>
                                    <div class="podium-name" title="<?= htmlspecialchars($u2['name']) ?>"><?= htmlspecialchars(explode(' ', $u2['name'])[0]) ?></div>
                                    <div class="podium-role"><?= htmlspecialchars($u2['division'] ?? 'Team') ?></div>
                                </div>
                                <div class="podium-score-pill">
                                    ⚡ <?= $u2['kpi_score'] ?> pts
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Rank 1: Gold (Center) -->
                        <?php if (isset($leaderboard[0]) && $leaderboard[0]['kpi_score'] > 0): $u1 = $leaderboard[0]; ?>
                        <div class="podium-col rank-1">
                            <div class="crown-3d-gold">👑</div>
                            <div class="trophy-3d-gold">🏆</div>
                            <div class="podium-avatar-wrap">
                                <img src="<?= $u1['avatar_url'] ?>" class="podium-avatar" alt="Gold Champion">
                                <div class="podium-rank-badge">1</div>
                            </div>
                            <div class="podium-block">
                                <div>
                                    <div class="podium-name" title="<?= htmlspecialchars($u1['name']) ?>"><?= htmlspecialchars(explode(' ', $u1['name'])[0]) ?></div>
                                    <div class="podium-role" style="color: #451a03; font-weight: 700;"><?= htmlspecialchars($u1['jabatan'] ?? 'Champion') ?></div>
                                </div>
                                <div class="podium-score-pill">
                                    🌟 <?= $u1['kpi_score'] ?> pts
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Rank 3: Bronze (Right) -->
                        <?php if (isset($leaderboard[2]) && $leaderboard[2]['kpi_score'] > 0): $u3 = $leaderboard[2]; ?>
                        <div class="podium-col rank-3">
                            <div class="trophy-3d-bronze">🥉</div>
                            <div class="podium-avatar-wrap">
                                <img src="<?= $u3['avatar_url'] ?>" class="podium-avatar" alt="Bronze">
                                <div class="podium-rank-badge">3</div>
                            </div>
                            <div class="podium-block">
                                <div>
                                    <div class="podium-name" title="<?= htmlspecialchars($u3['name']) ?>"><?= htmlspecialchars(explode(' ', $u3['name'])[0]) ?></div>
                                    <div class="podium-role"><?= htmlspecialchars($u3['division'] ?? 'Team') ?></div>
                                </div>
                                <div class="podium-score-pill">
                                    🚀 <?= $u3['kpi_score'] ?> pts
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>

        <!-- Category Champions & Formula Bento -->
        <div class="col-lg-5">
            <div class="d-flex flex-column gap-3 h-100">
                
                <!-- Category 1: King of Updates -->
                <div class="champ-card champ-card-orange">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #f59e0b, #ea580c); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; box-shadow: 0 6px 16px rgba(234, 88, 12, 0.35);">
                            <i class="bi bi-fire"></i>
                        </div>
                        <div>
                            <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #ea580c; letter-spacing: 0.5px;">Terbanyak Update Progres</span>
                            <div class="fw-bold" style="font-size: 0.95rem; color: #0f172a;"><?= $top_updater ? htmlspecialchars($top_updater['name']) : '—' ?></div>
                            <small class="text-muted" style="font-size: 0.72rem;"><?= $top_updater ? $top_updater['total_updates'] . ' kali mengunggah progres' : 'Belum ada data' ?></small>
                        </div>
                    </div>
                    <span class="badge rounded-pill bg-warning text-dark px-3 py-2" style="font-size: 0.75rem; font-weight: 800;">
                        🔥 <?= $top_updater ? $top_updater['total_updates'] . ' Updates' : '0' ?>
                    </span>
                </div>

                <!-- Category 2: Initiative Master -->
                <div class="champ-card champ-card-cyan">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #06b6d4, #0284c7); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; box-shadow: 0 6px 16px rgba(2, 132, 199, 0.35);">
                            <i class="bi bi-lightbulb-fill"></i>
                        </div>
                        <div>
                            <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #0284c7; letter-spacing: 0.5px;">Terbanyak Buat Pekerjaan</span>
                            <div class="fw-bold" style="font-size: 0.95rem; color: #0f172a;"><?= $top_creator ? htmlspecialchars($top_creator['name']) : '—' ?></div>
                            <small class="text-muted" style="font-size: 0.72rem;"><?= $top_creator ? $top_creator['total_created'] . ' tugas baru dibuat' : 'Belum ada data' ?></small>
                        </div>
                    </div>
                    <span class="badge rounded-pill bg-info text-white px-3 py-2" style="font-size: 0.75rem; font-weight: 800;">
                        💡 <?= $top_creator ? $top_creator['total_created'] . ' Tasks' : '0' ?>
                    </span>
                </div>

                <!-- Category 3: Execution Finisher -->
                <div class="champ-card champ-card-green">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #10b981, #059669); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; box-shadow: 0 6px 16px rgba(5, 150, 105, 0.35);">
                            <i class="bi bi-bullseye"></i>
                        </div>
                        <div>
                            <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #059669; letter-spacing: 0.5px;">Terbanyak Selesaikan Tugas</span>
                            <div class="fw-bold" style="font-size: 0.95rem; color: #0f172a;"><?= $top_finisher ? htmlspecialchars($top_finisher['name']) : '—' ?></div>
                            <small class="text-muted" style="font-size: 0.72rem;"><?= $top_finisher ? $top_finisher['total_done'] . ' tugas berstatus Selesai' : 'Belum ada data' ?></small>
                        </div>
                    </div>
                    <span class="badge rounded-pill bg-success px-3 py-2" style="font-size: 0.75rem; font-weight: 800;">
                        🎯 <?= $top_finisher ? $top_finisher['total_done'] . ' Selesai' : '0' ?>
                    </span>
                </div>

                <!-- KPI Points Rule Guide Box -->
                <div class="champ-card" style="background: linear-gradient(135deg, #fffbeb 0%, #fff7ed 100%); border: 1px dashed rgba(245, 158, 11, 0.5);">
                    <div class="w-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-info-circle-fill text-warning" style="font-size: 0.9rem;"></i>
                            <span style="font-size: 0.78rem; font-weight: 800; color: #92400e;">Rumus Perhitungan Poin KPI</span>
                        </div>
                        <div class="d-flex justify-content-between flex-wrap gap-2 text-muted" style="font-size: 0.74rem;">
                            <span class="badge bg-white text-dark border px-2 py-1"><b class="text-primary">+15 pt</b> Buat Task</span>
                            <span class="badge bg-white text-dark border px-2 py-1"><b class="text-warning">+20 pt</b> Update Progres</span>
                            <span class="badge bg-white text-dark border px-2 py-1"><b class="text-success">+25 pt</b> Selesai</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    <!-- ══════════════════════════════════════════════════════════════
         INTERACTIVE KPI COMPARISON CHART
         ══════════════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="ts-kpi-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold m-0" style="color: #0a0a0a; letter-spacing: -0.02em;">📊 Grafik Perbandingan Kontribusi Tim</h5>
                        <small class="text-muted">Perbandingan Tugas Dibuat vs Update Progres vs Tugas Selesai per Karyawan</small>
                    </div>
                </div>
                <div style="height: 280px; position: relative;">
                    <canvas id="kpiComparisonChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         FULL TEAM KPI LEADERBOARD TABLE
         ══════════════════════════════════════════════════════════════ -->
    <div class="row g-4">
        <div class="col-12">
            <div class="ts-kpi-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold m-0" style="color: #0a0a0a; letter-spacing: -0.02em;">📋 Tabel Peringkat KPI Seluruh Karyawan</h5>
                        <small class="text-muted">Daftar lengkap seluruh karyawan diurutkan berdasarkan total skor KPI</small>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="kpi-table">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">Rank</th>
                                <th>Karyawan</th>
                                <th>Divisi / Jabatan</th>
                                <th style="text-align: center;">Tugas Dibuat</th>
                                <th style="text-align: center;">Update Progres</th>
                                <th style="text-align: center;">Selesai</th>
                                <th style="text-align: center; width: 140px;">Completion Rate</th>
                                <th style="text-align: right;">Total KPI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $pos => $emp): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <?php if ($pos === 0): ?>
                                        <span style="font-size: 1.25rem;">🥇</span>
                                    <?php elseif ($pos === 1): ?>
                                        <span style="font-size: 1.15rem;">🥈</span>
                                    <?php elseif ($pos === 2): ?>
                                        <span style="font-size: 1.15rem;">🥉</span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-light text-dark fw-bold" style="font-size: 0.75rem; width: 26px; height: 26px; line-height: 18px;"><?= $pos + 1 ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= $emp['avatar_url'] ?>" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover;" alt="">
                                        <div>
                                            <div class="fw-bold" style="color: #0a0a0a; font-size: 0.88rem;"><?= htmlspecialchars($emp['name']) ?></div>
                                            <small class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($emp['nickname'] ?: '') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border" style="font-size: 0.72rem; font-weight: 600;">
                                        <?= htmlspecialchars($emp['division'] ?? 'Team') ?>
                                    </span>
                                    <div style="font-size: 0.72rem; color: #64748b; margin-top: 2px;"><?= htmlspecialchars($emp['jabatan'] ?? 'Karyawan') ?></div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge rounded-pill" style="background: rgba(6, 182, 212, 0.1); color: #0891b2; font-weight: 700; font-size: 0.78rem; padding: 4px 10px;">
                                        <?= $emp['total_created'] ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge rounded-pill" style="background: rgba(245, 158, 11, 0.1); color: #d97706; font-weight: 700; font-size: 0.78rem; padding: 4px 10px;">
                                        <?= $emp['total_updates'] ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge rounded-pill" style="background: rgba(16, 185, 129, 0.1); color: #059669; font-weight: 700; font-size: 0.78rem; padding: 4px 10px;">
                                        <?= $emp['total_done'] ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; border-radius: 4px; background: #f1f5f9;">
                                            <div class="progress-bar" style="width: <?= $emp['completion_rate'] ?>%; background: <?= $emp['completion_rate'] >= 80 ? '#10b981' : ($emp['completion_rate'] >= 40 ? '#f59e0b' : '#94a3b8') ?>;"></div>
                                        </div>
                                        <span style="font-size: 0.72rem; font-weight: 700; color: #475569; width: 32px; text-align: right;"><?= $emp['completion_rate'] ?>%</span>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <span class="fw-bold" style="font-size: 0.95rem; color: #0a0a0a; font-family: 'IBM Plex Mono', monospace;">
                                        <?= $emp['kpi_score'] ?>
                                    </span>
                                    <span style="font-size: 0.68rem; color: #94a3b8; font-weight: 600;"> pts</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     CHART.JS INITIALIZATION
     ══════════════════════════════════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('kpiComparisonChart');
    if (!ctx) return;

    const names = <?= json_encode($chart_names) ?>;
    const created = <?= json_encode($chart_created) ?>;
    const updates = <?= json_encode($chart_updates) ?>;
    const done = <?= json_encode($chart_done) ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: names,
            datasets: [
                {
                    label: 'Tugas Dibuat',
                    data: created,
                    backgroundColor: 'rgba(6, 182, 212, 0.85)',
                    borderRadius: 6,
                    barPercentage: 0.65,
                    categoryPercentage: 0.8
                },
                {
                    label: 'Update Progres',
                    data: updates,
                    backgroundColor: 'rgba(245, 158, 11, 0.9)',
                    borderRadius: 6,
                    barPercentage: 0.65,
                    categoryPercentage: 0.8
                },
                {
                    label: 'Tugas Selesai',
                    data: done,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderRadius: 6,
                    barPercentage: 0.65,
                    categoryPercentage: 0.8
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 12,
                        boxHeight: 12,
                        borderRadius: 3,
                        useBorderRadius: true,
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 11, weight: '600' },
                        color: '#475569'
                    }
                },
                tooltip: {
                    backgroundColor: '#0a0a0a',
                    titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '700' },
                    bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: true
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 11, weight: '600' },
                        color: '#334155'
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        precision: 0,
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 10 },
                        color: '#94a3b8'
                    }
                }
            }
        }
    });

    // ── 3D Isometric Podium Stage Parallax ──
    const podiumWrap = document.getElementById('podiumStageWrap');
    const podiumCont = document.getElementById('podiumContainer');
    if (podiumWrap && podiumCont) {
        podiumWrap.addEventListener('mousemove', function(e) {
            const rect = podiumWrap.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            const tiltX = (y / (rect.height / 2)) * -14;
            const tiltY = (x / (rect.width / 2)) * 14;
            podiumCont.style.transform = `rotateX(${tiltX}deg) rotateY(${tiltY}deg) translateZ(10px)`;
        });
        podiumWrap.addEventListener('mouseleave', function() {
            podiumCont.style.transform = 'rotateX(0deg) rotateY(0deg) translateZ(0px)';
        });
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
