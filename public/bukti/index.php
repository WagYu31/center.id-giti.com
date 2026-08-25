<?php 
require_once 'includes/db.php'; 

$current_user_id = $_SESSION['user_id'];
$view_mode = $_SESSION['dashboard_mode'] ?? 'social';

if (isset($_GET['switch_mode'])) {
    $new_mode = ($_GET['switch_mode'] == 'formal') ? 'formal' : 'social';
    $conn->prepare("UPDATE users SET dashboard_mode = ? WHERE id = ?")->execute([$new_mode, $current_user_id]);
    $_SESSION['dashboard_mode'] = $new_mode;
    $redirect_url = strtok($_SERVER["REQUEST_URI"], '?');
    if (!empty($_GET)) {
        $params = $_GET; unset($params['switch_mode']);
        if (!empty($params)) $redirect_url .= '?' . http_build_query($params);
    }
    header("Location: " . $redirect_url); 
    exit;
}

require_once 'includes/header.php'; 
require_once 'includes/sidebar.php';

$filter_user = $_GET['user'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search_query = $_GET['q'] ?? '';
$filter_date_start = $_GET['start'] ?? '';
$filter_date_end = $_GET['end'] ?? '';
$limit = ($view_mode == 'social') ? 15 : 35;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$cond = ["j.deleted_at IS NULL"]; $param = [];

if ($filter_user) {
    // Ambil info nama & nickname user yang difilter untuk mendeteksi mention/tag
    $uStmt = $conn->prepare("SELECT name, nickname FROM users WHERE id = ?");
    $uStmt->execute([$filter_user]);
    $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
    if ($uRow) {
        $cleanName = '@' . str_replace(' ', '', $uRow['name']);
        $cleanNick = !empty($uRow['nickname']) ? '@' . str_replace(' ', '', $uRow['nickname']) : $cleanName;
        // User adalah pembuat post, ATAU di-tag di post, ATAU mengunggah progress
        $cond[] = "(j.user_id = ? OR j.description LIKE ? OR j.description LIKE ? OR j.id IN (SELECT job_id FROM bukti_job_progress WHERE user_id = ?))";
        $param[] = $filter_user;
        $param[] = "%$cleanName%";
        $param[] = "%$cleanNick%";
        $param[] = $filter_user;
    } else {
        $cond[] = "j.user_id = ?";
        $param[] = $filter_user;
    }
}

if ($filter_status) { 
    $cond[] = "j.status = ?"; 
    $param[] = $filter_status; 
}

if ($search_query) { 
    $cond[] = "(j.title LIKE ? OR j.description LIKE ?)"; 
    $param[] = "%$search_query%"; 
    $param[] = "%$search_query%"; 
}

if (!empty($filter_date_start)) {
    $cond[] = "DATE(j.created_at) >= ?";
    $param[] = $filter_date_start;
}

if (!empty($filter_date_end)) {
    $cond[] = "DATE(j.created_at) <= ?";
    $param[] = $filter_date_end;
}

$where = implode(" AND ", $cond);

$stmt_count = $conn->prepare("SELECT COUNT(*) FROM bukti_jobs j WHERE $where");
$stmt_count->execute($param);
$total_pages = ceil($stmt_count->fetchColumn() / $limit);

// Check if views table exists
$has_views_table = false;
try { $conn->query("SELECT 1 FROM bukti_post_views LIMIT 1"); $has_views_table = true; } catch(Exception $e) {}

$v_count_sql = $has_views_table ? "(SELECT COUNT(*) FROM bukti_post_views WHERE job_id = j.id)" : "0";
$sql = "SELECT j.*, u.name as user_name, u.avatar as user_avatar, u.nickname, u.jabatan, 
        (SELECT COUNT(*) FROM bukti_comments WHERE job_id = j.id AND deleted_at IS NULL) as c_count, 
        (SELECT COUNT(*) FROM bukti_reactions WHERE job_id = j.id AND type='like') as l_count, 
        (SELECT COUNT(*) FROM bukti_reactions WHERE job_id = j.id AND user_id=$current_user_id AND type='like') as is_liked,
        $v_count_sql as v_count
        FROM bukti_jobs j JOIN users u ON j.user_id = u.id WHERE $where ORDER BY j.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql); $stmt->execute($param); $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch viewer avatars for each job (safe)
foreach($jobs as &$jb) {
    $jb['viewers'] = [];
    if($has_views_table) {
        try {
            $vs = $conn->prepare("SELECT u.name, u.avatar FROM bukti_post_views v JOIN users u ON v.user_id = u.id WHERE v.job_id = ? ORDER BY v.viewed_at DESC LIMIT 5");
            $vs->execute([$jb['id']]);
            $jb['viewers'] = $vs->fetchAll(PDO::FETCH_ASSOC);
            foreach($jb['viewers'] as &$vw) {
                $vw['avatar'] = $vw['avatar'] && file_exists("assets/img/avatars/".$vw['avatar']) ? "assets/img/avatars/".$vw['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($vw['name']);
            }
        } catch(Exception $e) {}
    }
}

$users_list = $conn->query("SELECT id, name FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$stmt_me = $conn->prepare("SELECT avatar, name FROM users WHERE id=?"); $stmt_me->execute([$current_user_id]); $me = $stmt_me->fetch();
$myName = $me['name'] ?? 'User'; 
$my_av = (!empty($me['avatar']) && file_exists("assets/img/avatars/" . $me['avatar'])) ? "assets/img/avatars/" . $me['avatar'] : "https://ui-avatars.com/api/?name=" . urlencode($myName) . "&background=f59e0b&color=ffffff&bold=true";
$sapa = date('H')<11?"Selamat Pagi": (date('H')<15?"Selamat Siang": (date('H')<18?"Selamat Sore":"Selamat Malam"));

function time_ago($datetime) { return tgl_indo($datetime); }
function format_text($text) { 
    $t = htmlspecialchars($text);
    $t = preg_replace('/\*([^*]+)\*/', '<strong>$1</strong>', $t); // *bold* → bold
    $t = preg_replace('/_([^_]+)_/', '<em>$1</em>', $t); // _italic_ → italic
    $t = preg_replace('/@([a-zA-Z0-9_]+)/', '<span class="mention-tag">@$1</span>', $t);
    // Collapse blank lines between bullet items → single newline
    $t = preg_replace('/\n{2,}(\x{2022})/u', "\n$1", $t);
    // Collapse 3+ newlines globally → max 2
    $t = preg_replace('/\n{3,}/', "\n\n", $t);
    return nl2br($t);
}
?>

<style>
    /* ══════════════════════════════════════════════════════════════
       TASTE SKILL 3D MODERN VIBRANT DESIGN SYSTEM
       ══════════════════════════════════════════════════════════════ */
    
    /* Loading Overlay - Premium Gold Glassmorphism */
    #loading-overlay { 
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(250, 247, 242, 0.88); 
        backdrop-filter: blur(14px); 
        z-index: 99999; 
        display: none; align-items: center; justify-content: center; flex-direction: column; 
    }
    .loader { 
        width: 52px; height: 52px; 
        border: 4px solid rgba(245, 158, 11, 0.2); 
        border-top-color: #f59e0b; 
        border-radius: 50%; 
        display: inline-block; box-sizing: border-box; 
        animation: rotation 0.75s cubic-bezier(0.68, -0.55, 0.27, 1.55) infinite; 
    }
    @keyframes rotation { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    /* ── Hero 3D Showcase Banner ── */
    .hero-3d-banner {
        position: relative;
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 40%, #ffedd5 100%);
        border: 1px solid rgba(245, 158, 11, 0.35);
        border-radius: 24px;
        padding: 26px 30px;
        box-shadow: 0 16px 40px -8px rgba(245, 158, 11, 0.22), 0 4px 16px -2px rgba(15, 23, 42, 0.04), inset 0 2px 0 rgba(255, 255, 255, 1);
        overflow: hidden;
        perspective: 1000px;
    }
    .hero-3d-bg-glow {
        position: absolute;
        top: -50%; right: -20%;
        width: 320px; height: 320px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(245, 158, 11, 0.3) 0%, rgba(245, 158, 11, 0) 70%);
        pointer-events: none;
        animation: pulseGlow 6s ease-in-out infinite alternate;
    }
    @keyframes pulseGlow { 0% { transform: scale(0.9); opacity: 0.7; } 100% { transform: scale(1.2); opacity: 1; } }

    .hero-3d-tag {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.6px;
        padding: 4px 10px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(217, 119, 6, 0.35);
    }
    .hero-3d-title {
        font-weight: 800;
        color: #0f172a;
        font-size: 1.6rem;
        letter-spacing: -0.02em;
    }
    .gradient-text {
        background: linear-gradient(135deg, #d97706 0%, #f59e0b 50%, #ea580c 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .hero-3d-subtitle {
        color: #475569;
        font-size: 0.9rem;
        max-width: 520px;
        line-height: 1.5;
    }
    
    /* Hero KPI Chips */
    .hero-kpi-chip {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.9);
        border-radius: 16px;
        padding: 8px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05), inset 0 1px 0 rgba(255, 255, 255, 1);
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .hero-kpi-chip:hover { transform: translateY(-2px) scale(1.03); }
    .kpi-chip-icon { font-size: 1.25rem; }
    .kpi-chip-val { font-size: 1.05rem; font-weight: 800; color: #0f172a; line-height: 1; }
    .kpi-chip-lbl { font-size: 0.68rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; }
    
    .chip-done { border-color: rgba(16, 185, 129, 0.3); background: linear-gradient(135deg, #ecfdf5 0%, #ffffff 100%); }
    .chip-done .kpi-chip-val { color: #059669; }
    .chip-progress { border-color: rgba(245, 158, 11, 0.3); background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%); }
    .chip-progress .kpi-chip-val { color: #d97706; }
    .chip-todo { border-color: rgba(148, 163, 184, 0.3); background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); }

    /* ── 3D Interactive Model Showcase ── */
    .model-3d-container {
        perspective: 900px;
        display: inline-block;
    }
    .model-3d-card {
        width: 160px; height: 160px;
        margin: 0 auto;
        position: relative;
        transform-style: preserve-3d;
        transition: transform 0.2s ease-out;
    }
    .model-3d-core {
        width: 100%; height: 100%;
        border-radius: 50%;
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #d97706 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 16px 36px -4px rgba(245, 158, 11, 0.45), inset 0 2px 4px rgba(255, 255, 255, 0.6);
        position: relative;
        animation: floatCore 6s ease-in-out infinite alternate;
    }
    @keyframes floatCore { 0% { transform: translateY(0px) rotate(0deg); } 100% { transform: translateY(-8px) rotate(3deg); } }
    
    .model-avatar-ring {
        position: relative;
        z-index: 2;
        border-radius: 50%;
        box-shadow: 0 0 0 5px rgba(255, 255, 255, 0.45);
    }
    .model-badge-float {
        position: absolute;
        bottom: -10px;
        background: #ffffff;
        border: 1px solid rgba(245, 158, 11, 0.4);
        padding: 4px 10px;
        border-radius: 9999px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 5;
        white-space: nowrap;
    }

    .floating-item {
        position: absolute;
        font-size: 1.5rem;
        z-index: 4;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.15));
    }
    .float-item-1 { top: -10px; left: -10px; animation: floatIcon1 4s ease-in-out infinite alternate; }
    .float-item-2 { top: -8px; right: -10px; animation: floatIcon2 4.5s ease-in-out infinite alternate; }
    .float-item-3 { bottom: 0px; right: -15px; animation: floatIcon1 5s ease-in-out infinite alternate; }
    .float-item-4 { bottom: -5px; left: -12px; animation: floatIcon2 4.2s ease-in-out infinite alternate; }
    
    @keyframes floatIcon1 { 0% { transform: translateY(0px) scale(1); } 100% { transform: translateY(-10px) scale(1.1); } }
    @keyframes floatIcon2 { 0% { transform: translateY(0px) scale(1); } 100% { transform: translateY(10px) scale(1.1); } }

    /* ── Vibrant 3D Feed Cards ── */
    .card-custom {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 20px;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03), 0 8px 24px -4px rgba(15, 23, 42, 0.06), inset 0 1px 0 rgba(255, 255, 255, 1);
        margin-bottom: 22px;
        overflow: hidden;
        position: relative;
        transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .card-custom:hover {
        box-shadow: 0 12px 32px -4px rgba(245, 158, 11, 0.15), 0 4px 12px rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255, 255, 255, 1);
        transform: translateY(-3px) scale(1.004);
    }
    .card-custom[data-status="done"] {
        border-left: 5px solid #10b981 !important;
        background: linear-gradient(180deg, #ffffff 0%, #f7fef9 100%);
    }
    .card-custom[data-status="in_progress"] {
        border-left: 5px solid #f59e0b !important;
        background: linear-gradient(180deg, #ffffff 0%, #fffdfa 100%);
    }
    .card-custom[data-status="todo"] {
        border-left: 5px solid #64748b !important;
        background: linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
    }

    /* ── 3D Status Badges with Glowing Pulse ── */
    .badge-3d-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 13px;
        border-radius: 9999px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.3px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.03), inset 0 1px 0 rgba(255,255,255,0.7);
        transition: transform 0.2s ease;
    }
    .badge-3d-status:hover { transform: scale(1.03); }
    
    .badge-3d-done {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        color: #047857;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }
    .badge-3d-done .pulse-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
        animation: pulseGreen 2s infinite;
    }
    @keyframes pulseGreen {
        0%, 100% { transform: scale(1); box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25); }
        50% { transform: scale(1.15); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.1); }
    }

    .badge-3d-progress {
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        color: #b45309;
        border: 1px solid rgba(245, 158, 11, 0.3);
    }
    .badge-3d-progress .pulse-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: #f59e0b;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25);
        animation: pulseAmber 2s infinite;
    }
    @keyframes pulseAmber {
        0%, 100% { transform: scale(1); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25); }
        50% { transform: scale(1.15); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.1); }
    }

    .badge-3d-todo {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        color: #475569;
        border: 1px solid rgba(148, 163, 184, 0.3);
    }
    .badge-3d-todo .pulse-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: #94a3b8;
        box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.2);
    }

    /* ── 3D Tactile Buttons ── */
    .btn-3d-primary {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
        border: none !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        border-radius: 12px;
        box-shadow: 0 4px 14px -2px rgba(245, 158, 11, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.4) !important;
        transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }
    .btn-3d-primary:hover {
        background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%) !important;
        box-shadow: 0 8px 20px -3px rgba(245, 158, 11, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.5) !important;
        transform: translateY(-2px);
    }
    .btn-3d-primary:active {
        transform: translateY(1px) scale(0.98);
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.35), inset 0 2px 4px rgba(0,0,0,0.15) !important;
    }

    .btn-3d-pill {
        background: #f8fafc;
        border: 1px solid rgba(226, 232, 240, 0.8);
        color: #475569;
        font-weight: 600;
        font-size: 0.82rem;
        padding: 6px 14px;
        border-radius: 9999px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03), inset 0 1px 0 rgba(255,255,255,0.8);
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .btn-3d-pill:hover {
        background: #ffffff;
        color: #d97706;
        border-color: rgba(245, 158, 11, 0.3);
        box-shadow: 0 4px 12px -2px rgba(245, 158, 11, 0.15), inset 0 1px 0 rgba(255,255,255,1);
        transform: translateY(-1px);
    }
    .btn-3d-pill.liked {
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
        color: #d97706;
        border-color: rgba(245, 158, 11, 0.4);
        box-shadow: 0 2px 8px -1px rgba(245, 158, 11, 0.2), inset 0 1px 0 rgba(255,255,255,0.9);
    }

    /* ── Post Input / Creator Bar ── */
    .post-creator-card {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 18px;
        padding: 14px 18px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.95);
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .post-creator-card:hover {
        border-color: rgba(245, 158, 11, 0.4);
        box-shadow: 0 8px 24px -4px rgba(245, 158, 11, 0.12), inset 0 1px 0 rgba(255, 255, 255, 1);
        transform: translateY(-2px);
    }
    .post-creator-input {
        background: #f8fafc;
        border: 1px solid rgba(226, 232, 240, 0.7);
        border-radius: 9999px;
        padding: 10px 18px;
        font-size: 0.9rem;
        color: #64748b;
        flex-grow: 1;
        transition: background 0.2s;
    }
    .post-creator-card:hover .post-creator-input {
        background: #f1f5f9;
        color: #334155;
    }

    /* ── 3D Animated Upload Drop Zone ── */
    .drop-zone {
        border: 2px dashed rgba(245, 158, 11, 0.35);
        border-radius: 18px;
        padding: 26px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        background: linear-gradient(135deg, #ffffff 0%, #fffdfa 100%);
        position: relative;
        box-shadow: inset 0 2px 6px rgba(245, 158, 11, 0.03);
    }
    .drop-zone:hover {
        border-color: #f59e0b;
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px -4px rgba(245, 158, 11, 0.15), inset 0 1px 0 rgba(255,255,255,0.9);
    }
    .drop-zone.drag-over {
        border-color: #d97706;
        background: rgba(245, 158, 11, 0.1);
        transform: scale(1.02);
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.18);
    }
    .drop-zone .drop-icon {
        width: 48px; height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #d97706;
        display: inline-flex;
        align-items: center; justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 8px;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .drop-zone:hover .drop-icon,
    .drop-zone.drag-over .drop-icon {
        transform: translateY(-4px) scale(1.12);
    }
    .drop-zone .drop-text {
        font-size: 0.88rem;
        color: #475569;
        font-weight: 600;
    }
    .drop-zone .drop-text strong {
        color: #d97706;
        cursor: pointer;
    }
    .drop-zone .drop-hint {
        font-size: 0.72rem;
        color: #94a3b8;
        margin-top: 4px;
    }

    /* ── Preview Grid ── */
    .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(84px, 1fr)); gap: 10px; margin-top: 15px; }
    .preview-item { 
        position: relative; width: 100%; padding-top: 100%; 
        background: #f8fafc; border-radius: 14px; overflow: hidden; 
        border: 1px solid rgba(226, 232, 240, 0.9); 
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); 
    }
    .preview-item:hover { transform: scale(1.05); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
    .preview-content { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column; }
    .preview-content img, .preview-content video { width: 100%; height: 100%; object-fit: cover; }
    .preview-content i { font-size: 2rem; color: #f59e0b; }
    .btn-remove-file { 
        position: absolute; top: 4px; right: 4px; 
        background: rgba(15, 23, 42, 0.75); color: white; 
        border: none; border-radius: 50%; width: 22px; height: 22px; 
        font-size: 11px; display: flex; align-items: center; justify-content: center; 
        cursor: pointer; z-index: 5; transition: all 0.2s ease; 
        backdrop-filter: blur(4px);
    }
    .btn-remove-file:hover { background: #ef4444; transform: scale(1.18); }

    /* ── 3D Modal Windows (Detail & Progress) ── */
    .modal-content {
        border-radius: 24px !important;
        border: 1px solid rgba(255, 255, 255, 0.8) !important;
        box-shadow: 0 24px 60px -12px rgba(15, 23, 42, 0.2), 0 8px 24px -4px rgba(0,0,0,0.06) !important;
        overflow: hidden;
    }
    .modal-backdrop.show {
        backdrop-filter: blur(8px);
        background-color: rgba(15, 23, 42, 0.45);
    }

    /* ── Timeline Stepper in Modal ── */
    .timeline-node {
        position: relative;
        padding-left: 28px;
        margin-bottom: 20px;
    }
    .timeline-node:last-child { margin-bottom: 0; }
    .timeline-node::before {
        content: '';
        position: absolute;
        left: 8px; top: 22px; bottom: -18px;
        width: 2px;
        background: #e2e8f0;
    }
    .timeline-node:last-child::before { display: none; }
    .timeline-icon-dot {
        position: absolute;
        left: 0; top: 4px;
        width: 18px; height: 18px;
        border-radius: 50%;
        background: #ffffff;
        border: 3px solid #f59e0b;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        z-index: 2;
    }

    /* ── Modern Chat Bubbles in Detail Modal ── */
    .chat-bubble-card {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 16px;
        padding: 12px 16px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03), inset 0 1px 0 rgba(255, 255, 255, 0.95);
        transition: border-color 0.2s;
    }
    .chat-bubble-card:hover {
        border-color: rgba(245, 158, 11, 0.3);
    }

    /* Tag Mentions in Text */
    .mention-tag, .mention-badge {
        background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
        color: #0369a1;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 8px;
        font-size: 0.88em;
        display: inline-block;
        box-shadow: 0 1px 2px rgba(2, 132, 199, 0.08);
        border: 1px solid rgba(14, 165, 233, 0.25);
    }

    /* Description Editor */
    .desc-editor {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.25s ease;
        background: #ffffff;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }
    .desc-editor:focus-within {
        border-color: #f59e0b;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
    }
    .desc-toolbar {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 8px 12px;
        background: #f8fafc;
        border-bottom: 1px solid #edf2f7;
    }
    .desc-tool {
        background: none;
        border: none;
        width: 32px; height: 32px;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        color: #64748b;
        cursor: pointer;
        transition: all 0.15s ease;
        font-size: 0.92rem;
    }
    .desc-tool:hover {
        background: rgba(245, 158, 11, 0.12);
        color: #d97706;
    }
    .desc-divider { width: 1px; height: 18px; background: #e2e8f0; margin: 0 4px; }
    .desc-hint { font-size: 0.7rem; color: #94a3b8; margin-left: auto; font-weight: 500; }
    .rich-editor {
        min-height: 120px;
        max-height: 380px;
        overflow-y: auto;
        padding: 14px 18px;
        font-size: 0.92rem;
        line-height: 1.65;
        color: #1e293b;
        outline: none;
    }
    .rich-editor:empty::before {
        content: 'Tuliskan deskripsi pekerjaan... Gunakan @ untuk tag rekan tim';
        color: #94a3b8;
        pointer-events: none;
    }

    /* ── Custom Scrollbar ── */
    .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scroll::-webkit-scrollbar-track { background: transparent; }
    .custom-scroll::-webkit-scrollbar-thumb { background-color: rgba(203, 213, 225, 0.8); border-radius: 9999px; }
    .custom-scroll::-webkit-scrollbar-thumb:hover { background-color: rgba(245, 158, 11, 0.5); }

    /* ── Mention Autocomplete ── */
    .mention-list {
        position: absolute;
        background: white;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 16px;
        max-height: 220px;
        overflow-y: auto;
        width: 280px;
        z-index: 99999;
        box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.15);
        display: none;
        padding: 6px 0;
    }
    .mention-item {
        display: flex; align-items: center; gap: 12px;
        padding: 10px 16px; cursor: pointer;
        transition: all 0.15s ease;
        border-bottom: 1px solid #f8fafc;
    }
    .mention-item:hover { background: rgba(245, 158, 11, 0.08); }
    .mention-item:last-child { border-bottom: none; }
    .mention-avatar {
        width: 36px; height: 36px;
        border-radius: 10px; object-fit: cover;
        flex-shrink: 0;
        border: 2px solid rgba(245, 158, 11, 0.25);
    }
    .mention-name { font-size: 0.88rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .mention-nick { font-size: 0.73rem; color: #64748b; }

    /* Page link & Button Overrides */
    .btn-primary { background: linear-gradient(135deg, #f59e0b, #d97706) !important; border: none !important; color: #ffffff !important; font-weight: 700 !important; box-shadow: 0 4px 14px -2px rgba(245,158,11,0.45) !important; }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 20px -3px rgba(245,158,11,0.55) !important; }
    .btn-outline-primary { border-color: #f59e0b !important; color: #d97706 !important; font-weight: 600; }
    .btn-outline-primary:hover { background: #f59e0b !important; color: #ffffff !important; }
</style>

<div id="loading-overlay" style="display: none;">
    <span class="loader"></span>
    <p class="mt-3 fw-bold" style="color: #eab308;">Sedang memproses data...</p>
</div>

<div class="main-wrapper">
    <div class="content-area">
        <!-- ═══ 3D HERO SHOWCASE BANNER ═══ -->
        <div class="hero-3d-banner mb-4">
            <div class="hero-3d-bg-glow"></div>
            <div class="row align-items-center position-relative z-2">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="hero-3d-tag"><i class="bi bi-stars me-1"></i>TASTE SKILL 3D WORKSPACE</span>
                        <span class="badge rounded-pill bg-warning bg-opacity-25 text-warning fw-bold px-3 py-1" style="font-size: 0.72rem;">✨ Live Tim</span>
                    </div>
                    <h2 class="hero-3d-title mb-2">
                        <?php echo $sapa; ?>, <span class="gradient-text"><?php echo htmlspecialchars(explode(' ', $myName)[0]); ?></span>!
                    </h2>
                    <p class="hero-3d-subtitle mb-3">
                        Pantau progres pekerjaan tim secara real-time, rayakan pencapaian bersama, dan kolaborasi lebih cepat.
                    </p>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="hero-kpi-chip chip-done">
                            <span class="kpi-chip-icon">🏆</span>
                            <div>
                                <div class="kpi-chip-val"><?php echo $stats['done'] ?? 0; ?></div>
                                <div class="kpi-chip-lbl">Selesai</div>
                            </div>
                        </div>
                        <div class="hero-kpi-chip chip-progress">
                            <span class="kpi-chip-icon">⚡</span>
                            <div>
                                <div class="kpi-chip-val"><?php echo $stats['in_progress'] ?? 0; ?></div>
                                <div class="kpi-chip-lbl">Proses</div>
                            </div>
                        </div>
                        <div class="hero-kpi-chip chip-todo">
                            <span class="kpi-chip-icon">🎯</span>
                            <div>
                                <div class="kpi-chip-val"><?php echo $stats['todo'] ?? 0; ?></div>
                                <div class="kpi-chip-lbl">To-Do</div>
                            </div>
                        </div>
                        <a href="analytics.php" class="btn btn-sm btn-3d-primary rounded-pill px-3 py-2 ms-auto d-inline-flex align-items-center gap-1">
                            <i class="bi bi-trophy-fill"></i> Leaderboard KPI <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 text-center d-none d-lg-block">
                    <!-- 3D Interactive Model Showcase -->
                    <div class="model-3d-container" id="hero3dContainer">
                        <div class="model-3d-card" id="hero3dCard">
                            <div class="floating-item float-item-1">🚀</div>
                            <div class="floating-item float-item-2">✨</div>
                            <div class="floating-item float-item-3">🏆</div>
                            <div class="floating-item float-item-4">🛡️</div>
                            <div class="model-3d-core">
                                <div class="model-avatar-ring">
                                    <img src="<?php echo $my_av; ?>" class="rounded-circle shadow" width="70" height="70" style="object-fit:cover; border:3px solid white;">
                                </div>
                                <div class="model-badge-float">
                                    <span class="fw-bold" style="font-size:0.75rem; color:#d97706;">TOP PERFORMER</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h5 class="fw-bold mb-1" style="color: #0f172a; letter-spacing: -0.01em;">Aktivitas Tim Terbaru</h5>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Linimasa pekerjaan dan bukti hasil progres kerja.</p>
            </div>
            <div class="btn-group shadow-sm bg-white rounded-pill p-1">
                <a href="?switch_mode=social" class="btn btn-sm rounded-pill px-3 <?php echo $view_mode=='social'?'btn-dark':'text-muted'; ?>"><i class="bi bi-grid-fill me-1"></i> Sosial</a>
                <a href="?switch_mode=formal" class="btn btn-sm rounded-pill px-3 <?php echo $view_mode=='formal'?'btn-dark':'text-muted'; ?>"><i class="bi bi-list-ul me-1"></i> Tabel</a>
                <a href="analytics.php" class="btn btn-sm rounded-pill px-3 text-muted" style="font-weight: 600;"><i class="bi bi-trophy-fill me-1 text-warning"></i> KPI</a>
            </div>
        </div>

        <div class="post-creator-card d-flex align-items-center gap-3 mb-4" data-bs-toggle="modal" data-bs-target="#createModal">
            <div style="position: relative;">
                <img src="<?php echo $my_av; ?>" class="rounded-circle shadow-sm" width="44" height="44" style="object-fit: cover; border: 2px solid rgba(245, 158, 11, 0.4);">
                <span style="position: absolute; bottom: -2px; right: -2px; width: 16px; height: 16px; border-radius: 50%; background: #f59e0b; color: white; display: flex; align-items: center; justify-content: center; font-size: 10px; border: 2px solid white;"><i class="bi bi-plus-lg"></i></span>
            </div>
            <div class="post-creator-input">
                Apa yang sedang dikerjakan? (Ketik @ untuk tag rekan kerja...)
            </div>
            <button class="btn btn-sm btn-3d-primary px-3 rounded-pill d-none d-sm-inline-flex align-items-center gap-1">
                <i class="bi bi-plus-circle-fill"></i> Buat
            </button>
        </div>

        <?php if($view_mode == 'social'): ?>
            <div class="row">
            <?php foreach($jobs as $job): 
                $uName = $job['user_name'] ?? 'User'; $uAvatar = $job['user_avatar'] ?? '';
                $avatarSrc = ($uAvatar && file_exists("assets/img/avatars/".$uAvatar)) ? "assets/img/avatars/".$uAvatar : "https://ui-avatars.com/api/?name=".urlencode($uName)."&background=f59e0b&color=ffffff&bold=true";
            ?>
                <div class="col-12">
                    <div class="card-custom" id="post-<?php echo $job['id']; ?>" data-status="<?php echo $job['status']; ?>">
                        <div class="p-3 px-4 d-flex justify-content-between align-items-start">
                            <div class="d-flex gap-3 align-items-center">
                                <div style="position: relative;">
                                    <img src="<?php echo $avatarSrc; ?>" class="rounded-4 shadow-sm" width="46" height="46" style="object-fit:cover; border: 2px solid rgba(245, 158, 11, 0.25); box-shadow: 0 4px 10px rgba(0,0,0,0.06);">
                                </div>
                                <div>
                                    <div class="fw-bold" style="color: #0f172a; font-size: 0.95rem; letter-spacing: -0.01em;"><?php echo htmlspecialchars($job['nickname'] ?: $uName); ?></div>
                                    <div style="font-size: 0.76rem; color: #64748b;"><?php echo time_ago($job['created_at']); ?><?php if($job['is_edited']): ?><span class="fst-italic ms-1" style="font-size:0.68rem; color: #94a3b8;">• Diedit</span><?php endif; ?></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?php 
                                    if ($job['status'] == 'done') {
                                        echo '<span class="badge-3d-status badge-3d-done"><span class="pulse-dot"></span> SELESAI</span>';
                                    } elseif ($job['status'] == 'in_progress') {
                                        echo '<span class="badge-3d-status badge-3d-progress"><span class="pulse-dot"></span> ON PROGRESS</span>';
                                    } else {
                                        echo '<span class="badge-3d-status badge-3d-todo"><span class="pulse-dot"></span> BELUM MULAI</span>';
                                    }
                                ?>
                                <?php if($job['user_id'] == $current_user_id): ?>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm rounded-circle border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width:32px; height:32px; display:flex; align-items:center; justify-content:center; background:#f8fafc;"><i class="bi bi-three-dots-vertical" style="color:#64748b;"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2" style="min-width: 160px; box-shadow: 0 12px 30px rgba(0,0,0,0.1) !important;">
                                        <li><a class="dropdown-item rounded-3 py-2" href="#" onclick="openEditModal(<?php echo $job['id']; ?>)" style="font-size:0.88rem; color:#334155; font-weight:600;"><i class="bi bi-pencil-square me-2 text-warning"></i> Edit</a></li>
                                        <li><a class="dropdown-item rounded-3 py-2 text-danger" href="#" onclick="deletePost(<?php echo $job['id']; ?>)" style="font-size:0.88rem; font-weight:600;"><i class="bi bi-trash3 me-2"></i> Hapus</a></li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="px-4 pb-3 cursor-pointer" onclick="openDetail(<?php echo $job['id']; ?>)">
                            <h5 class="fw-bold mb-2" style="color: #0f172a; font-size: 1.05rem; letter-spacing: -0.015em; line-height: 1.4;"><?php echo htmlspecialchars($job['title']); ?></h5>
                            <div class="rich-content" style="color: #475569; line-height: 1.65; font-size: 0.9rem; margin-bottom: 0;"><?php echo (strlen($job['description'])>220) ? substr(format_text($job['description']),0,220).'...' : format_text($job['description']); ?></div>
                        </div>
                        <div class="px-4 py-3 border-top d-flex justify-content-between align-items-center" style="border-color: rgba(226, 232, 240, 0.6) !important; background: #fafbfc;">
                            <div class="d-flex gap-2">
                                <button onclick="toggleLike(<?php echo $job['id']; ?>, this)" class="btn-3d-pill <?php echo $job['is_liked']?'liked':''; ?> d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-hand-thumbs-up-fill" style="<?php echo $job['is_liked']?'color:#d97706;':'color:#94a3b8;'; ?>"></i> <span class="count"><?php echo $job['l_count']; ?></span> Suka
                                </button>
                                <button onclick="openDetail(<?php echo $job['id']; ?>)" class="btn-3d-pill d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-chat-dots-fill text-muted"></i> <?php echo $job['c_count']; ?> Komentar
                                </button>
                            </div>
                            <div class="d-flex align-items-center gap-1" style="cursor:pointer;" onclick="showViewers(<?php echo $job['id']; ?>)" title="Klik untuk lihat viewer">
                                <?php if(!empty($job['viewers'])): ?>
                                <div class="d-flex" style="margin-right: 4px;">
                                    <?php foreach(array_slice($job['viewers'], 0, 3) as $vi => $viewer): ?>
                                    <img src="<?php echo $viewer['avatar']; ?>" class="rounded-circle" width="22" height="22" style="object-fit:cover; border: 2px solid white; margin-left: <?php echo $vi > 0 ? '-6px' : '0'; ?>; position:relative; z-index:<?php echo 5-$vi; ?>; box-shadow: 0 1px 3px rgba(0,0,0,0.1);" title="<?php echo htmlspecialchars($viewer['name']); ?>">
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;"><i class="bi bi-eye me-1"></i><?php echo $job['v_count']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card-custom p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light"><tr><th class="ps-4">Pekerjaan</th><th>Status</th><th>Oleh</th><th class="text-end pe-4">Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach($jobs as $job): $uName = $job['user_name']??'User'; ?>
                        <tr>
                            <td class="ps-4"><div class="fw-bold"><?php echo htmlspecialchars($job['title']); ?></div><small class="text-muted"><?php echo time_ago($job['created_at']); ?></small></td>
                            <td>
                                <?php 
                                    if ($job['status'] == 'done') {
                                        echo '<span class="badge-3d-status badge-3d-done"><span class="pulse-dot"></span> Selesai</span>';
                                    } elseif ($job['status'] == 'in_progress') {
                                        echo '<span class="badge-3d-status badge-3d-progress"><span class="pulse-dot"></span> On Progress</span>';
                                    } else {
                                        echo '<span class="badge-3d-status badge-3d-todo"><span class="pulse-dot"></span> Belum Mulai</span>';
                                    }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($job['nickname'] ?: $uName); ?></td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end align-items-center gap-2">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="openDetail(<?php echo $job['id']; ?>)">Detail</button>
                                    
                                    <?php if($job['user_id'] == $current_user_id): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm rounded-circle border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                            <li><a class="dropdown-item" href="#" onclick="openEditModal(<?php echo $job['id']; ?>)"><i class="bi bi-pencil me-2"></i> Edit</a></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deletePost(<?php echo $job['id']; ?>)"><i class="bi bi-trash me-2"></i> Hapus</a></li>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for($i=1; $i<=$total_pages; $i++): 
                    $pgParams = $_GET;
                    $pgParams['page'] = $i;
                    $pgUrl = '?' . http_build_query($pgParams);
                ?>
                <li class="page-item <?php echo $page==$i?'active':''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars($pgUrl); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <div class="widget-area">
        <div class="card-custom p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold m-0">Filter & Pencarian</h6>
                <?php if(!empty($filter_user) || !empty($filter_status) || !empty($search_query) || !empty($filter_date_start) || !empty($filter_date_end)): ?>
                <a href="index.php" class="small text-danger fw-bold text-decoration-none" title="Reset Filter"><i class="bi bi-x-circle me-1"></i>Reset</a>
                <?php endif; ?>
            </div>
            <form method="GET" action="index.php">
                <div class="mb-3"><label class="small fw-bold text-muted mb-1">KATA KUNCI</label><input name="q" class="form-control bg-light border-0" placeholder="Cari..." value="<?php echo htmlspecialchars($search_query); ?>" style="border-radius:12px; padding:10px 14px;"></div>
                <div class="mb-3"><label class="small fw-bold text-muted mb-1">KARYAWAN</label><select name="user" class="form-select bg-light border-0" style="border-radius:12px; padding:10px 14px;"><option value="">Semua Karyawan</option><?php foreach($users_list as $u) echo "<option value='{$u['id']}' ".($filter_user==$u['id']?'selected':'').">{$u['name']}</option>"; ?></select></div>
                <div class="mb-3">
                    <label class="small fw-bold text-muted mb-1">STATUS</label>
                    <select name="status" class="form-select bg-light border-0" style="border-radius:12px; padding:10px 14px;">
                        <option value="">Semua Status</option>
                        <option value="todo" <?php echo $filter_status=='todo'?'selected':''; ?>>⚪ Belum Mulai</option>
                        <option value="in_progress" <?php echo $filter_status=='in_progress'?'selected':''; ?>>🟡 Dalam Proses</option>
                        <option value="done" <?php echo $filter_status=='done'?'selected':''; ?>>🟢 Selesai</option>
                    </select>
                </div>
                <div class="mb-3"><label class="small fw-bold text-muted mb-1">RENTANG WAKTU</label>
                    <div class="d-flex gap-1">
                        <input type="date" name="start" class="form-control form-control-sm bg-light border-0" style="font-size: 0.75rem; border-radius:10px;" value="<?php echo htmlspecialchars($filter_date_start); ?>">
                        <input type="date" name="end" class="form-control form-control-sm bg-light border-0" style="font-size: 0.75rem; border-radius:10px;" value="<?php echo htmlspecialchars($filter_date_end); ?>">
                    </div>
                </div>
                <button class="btn btn-3d-primary w-100 fw-bold py-2" style="border-radius:12px;">Terapkan Filter</button>
            </form>
        </div>
        <?php
        $stat_title = "Statistik Saya";
        if (!empty($filter_user)) {
            $stat_user_stmt = $conn->prepare("SELECT name, nickname FROM users WHERE id = ?");
            $stat_user_stmt->execute([$filter_user]);
            $stat_user_row = $stat_user_stmt->fetch(PDO::FETCH_ASSOC);
            if ($stat_user_row) {
                $stat_title = "Statistik " . htmlspecialchars(explode(' ', $stat_user_row['name'])[0]);
                $cleanStatName = '@' . str_replace(' ', '', $stat_user_row['name']);
                $cleanStatNick = !empty($stat_user_row['nickname']) ? '@' . str_replace(' ', '', $stat_user_row['nickname']) : $cleanStatName;
                
                $stat_cond = ["j.deleted_at IS NULL"];
                $stat_param = [];
                $stat_cond[] = "(j.user_id = ? OR j.description LIKE ? OR j.description LIKE ? OR j.id IN (SELECT job_id FROM bukti_job_progress WHERE user_id = ?))";
                $stat_param[] = $filter_user;
                $stat_param[] = "%$cleanStatName%";
                $stat_param[] = "%$cleanStatNick%";
                $stat_param[] = $filter_user;
                
                if (!empty($filter_date_start)) { $stat_cond[] = "DATE(j.created_at) >= ?"; $stat_param[] = $filter_date_start; }
                if (!empty($filter_date_end)) { $stat_cond[] = "DATE(j.created_at) <= ?"; $stat_param[] = $filter_date_end; }
                if (!empty($search_query)) { $stat_cond[] = "(j.title LIKE ? OR j.description LIKE ?)"; $stat_param[] = "%$search_query%"; $stat_param[] = "%$search_query%"; }
                
                $stat_where = implode(" AND ", $stat_cond);
                $stat_stmt = $conn->prepare("SELECT j.status, COUNT(*) as c FROM bukti_jobs j WHERE $stat_where GROUP BY j.status");
                $stat_stmt->execute($stat_param);
                $stats = $stat_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } else {
                $stats = [];
            }
        } else {
            // Default: Statistik user login
            $stat_stmt = $conn->prepare("SELECT j.status, COUNT(*) as c FROM bukti_jobs j WHERE j.user_id = ? AND j.deleted_at IS NULL GROUP BY j.status");
            $stat_stmt->execute([$current_user_id]);
            $stats = $stat_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        ?>
        <div class="card-custom p-4" style="background: linear-gradient(180deg, #ffffff 0%, #fffdfa 100%);">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold m-0" style="color: #0f172a; font-size: 0.95rem;"><i class="bi bi-pie-chart-fill text-warning me-2"></i><?= $stat_title ?></h6>
                <span class="badge rounded-pill bg-warning bg-opacity-15 text-warning fw-bold" style="font-size: 0.68rem;">Live</span>
            </div>
            
            <div class="d-flex flex-column gap-2 mb-3">
                <div class="p-2 px-3 rounded-3 d-flex justify-content-between align-items-center" style="background: #f0fdf4; border: 1px solid rgba(16, 185, 129, 0.25);">
                    <div class="d-flex align-items-center gap-2">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);"></span>
                        <span class="fw-bold" style="font-size: 0.82rem; color: #047857;">Selesai</span>
                    </div>
                    <b class="fs-6" style="color: #047857;"><?php echo $stats['done']??0; ?></b>
                </div>
                
                <div class="p-2 px-3 rounded-3 d-flex justify-content-between align-items-center" style="background: #fffbeb; border: 1px solid rgba(245, 158, 11, 0.25);">
                    <div class="d-flex align-items-center gap-2">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25);"></span>
                        <span class="fw-bold" style="font-size: 0.82rem; color: #b45309;">Dalam Proses</span>
                    </div>
                    <b class="fs-6" style="color: #b45309;"><?php echo $stats['in_progress']??0; ?></b>
                </div>
                
                <div class="p-2 px-3 rounded-3 d-flex justify-content-between align-items-center" style="background: #f8fafc; border: 1px solid rgba(148, 163, 184, 0.25);">
                    <div class="d-flex align-items-center gap-2">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.2);"></span>
                        <span class="fw-bold" style="font-size: 0.82rem; color: #475569;">Belum Mulai</span>
                    </div>
                    <b class="fs-6" style="color: #475569;"><?php echo $stats['todo']??0; ?></b>
                </div>
            </div>
            
            <?php 
                $tot = ($stats['done']??0) + ($stats['in_progress']??0) + ($stats['todo']??0);
                $pDone = $tot > 0 ? round((($stats['done']??0) / $tot) * 100) : 0;
                $pProg = $tot > 0 ? round((($stats['in_progress']??0) / $tot) * 100) : 0;
                $pTodo = $tot > 0 ? round((($stats['todo']??0) / $tot) * 100) : 0;
            ?>
            <div class="progress rounded-pill mb-2" style="height: 8px; background: #e2e8f0; overflow:hidden;">
                <div class="progress-bar bg-success" style="width: <?php echo $pDone; ?>%;"></div>
                <div class="progress-bar bg-warning" style="width: <?php echo $pProg; ?>%;"></div>
                <div class="progress-bar bg-secondary" style="width: <?php echo $pTodo; ?>%;"></div>
            </div>
            <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.72rem; font-weight: 600;">
                <span>Total: <?php echo $tot; ?> Tugas</span>
                <span class="text-success fw-bold"><?php echo $pDone; ?>% Selesai</span>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 overflow-hidden shadow-lg" style="height: 86vh;">
            <div class="modal-body p-0 h-100">
                <div class="row g-0 h-100">
                    <!-- Left Panel: Content & Progress Timeline -->
                    <div class="col-lg-8 h-100 d-flex flex-column" style="background: #fafbfc;">
                        <!-- Header -->
                        <div class="p-4 flex-shrink-0" style="background: #ffffff; border-bottom: 1px solid rgba(226, 232, 240, 0.8);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex gap-3 align-items-center">
                                    <img src="" id="d-avatar" class="rounded-4 shadow-sm" width="52" height="52" style="object-fit: cover; border: 2px solid rgba(245,158,11,0.3); box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
                                    <div>
                                        <h6 class="fw-bold mb-0" id="d-name" style="color: #0f172a; font-size: 1rem; letter-spacing: -0.01em;"></h6>
                                        <small id="d-date" style="color: #64748b; font-size: 0.78rem;"></small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div id="d-status-badge"></div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                            </div>
                            <div id="d-viewers"></div>
                        </div>
                        <!-- Body -->
                        <div class="p-4 overflow-auto custom-scroll flex-grow-1" style="min-height: 0;">
                            <h3 class="fw-bold mb-3" id="d-title" style="color: #0f172a; letter-spacing: -0.02em; font-size: 1.45rem; line-height: 1.35;"></h3>
                            <div id="d-desc" class="mb-4" style="white-space: pre-wrap; font-size: 0.94rem; line-height: 1.7; color: #334155;"></div>
                            
                            <!-- Attachments Preview Grid -->
                            <div id="d-att" class="row g-2 mb-4"></div>
                            
                            <!-- 3D Timeline Progress Stepper -->
                            <div style="background: #ffffff; border: 1px solid rgba(226, 232, 240, 0.9); border-radius: 20px; overflow: hidden; box-shadow: 0 2px 10px rgba(15,23,42,0.03);">
                                <div class="d-flex justify-content-between align-items-center p-3 px-4" style="border-bottom: 1px solid rgba(226, 232, 240, 0.7); background: #f8fafc;">
                                    <h6 class="fw-bold m-0" style="color: #0f172a; font-size: 0.92rem;"><i class="bi bi-activity me-2 text-warning"></i>Timeline Progress</h6>
                                    <button class="btn btn-sm btn-3d-primary px-3 rounded-pill" id="btn-update-progress" style="display:none; font-size: 0.78rem;" onclick="showProgressForm()"><i class="bi bi-plus-lg me-1"></i> Update Progres</button>
                                </div>
                                <div id="d-timeline" class="p-4"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Right Panel: Discussion -->
                    <div class="col-lg-4 h-100 d-flex flex-column" style="background: #f8fafc; border-left: 1px solid rgba(226, 232, 240, 0.8);">
                        <div class="p-3 px-4 d-flex justify-content-between align-items-center flex-shrink-0" style="background: #ffffff; border-bottom: 1px solid rgba(226, 232, 240, 0.8); height: 83px;">
                            <h6 class="fw-bold m-0" style="color: #0f172a; font-size: 0.95rem;"><i class="bi bi-chat-dots-fill me-2 text-warning"></i>Diskusi</h6>
                            <button class="btn btn-sm btn-3d-pill px-3" id="d-like-btn" onclick="toggleLikeInModal()">
                                <i class="bi bi-hand-thumbs-up-fill me-1"></i> <span id="d-like-count">0</span>
                            </button>
                        </div>
                        <div id="d-comments" class="flex-grow-1 p-3 overflow-auto custom-scroll" style="min-height: 0;"></div>
                        <div class="p-3 flex-shrink-0" style="background: #ffffff; border-top: 1px solid rgba(226, 232, 240, 0.8);">
                            <div class="position-relative">
                                <input id="d-input" class="form-control rounded-pill bg-light border-0 pe-5" placeholder="Ketik @ untuk tag rekan kerja..." style="padding: 11px 52px 11px 18px; font-size: 0.88rem; color: #1e293b;">
                                <button class="btn btn-3d-primary rounded-circle position-absolute top-50 end-0 translate-middle-y me-2" style="width:36px; height:36px; display: flex; align-items: center; justify-content: center;" onclick="sendComment()"><i class="bi bi-send-fill" style="font-size: 0.82rem;"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header border-0 pb-0 px-4 pt-4"><h5 class="fw-bold m-0" id="modalTitle" style="color: #0f172a;"><i class="bi bi-plus-circle-fill text-warning me-2"></i>Buat Pekerjaan Baru</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body px-4 py-3">
                <form id="formJob">
                    <input type="hidden" name="action" value="create_post" id="formAction">
                    <input type="hidden" name="job_id" id="formJobId">
                    <div class="d-flex gap-2 mb-3 align-items-center">
                        <img src="<?php echo $my_av; ?>" class="rounded-circle shadow-sm" width="38" height="38" style="object-fit: cover; border: 2px solid rgba(245,158,11,0.3);">
                        <div class="fw-bold" style="color: #0f172a; font-size: 0.95rem;"><?php echo $myName; ?></div>
                        <select name="status" id="inpStatus" class="form-select form-select-sm border-0 bg-light fw-bold text-warning w-auto" style="border-radius: 10px; margin-left: auto;"><option value="todo">Belum Mulai</option><option value="in_progress">On Progress</option><option value="done">Selesai</option></select>
                    </div>
                    <input type="text" name="title" id="inpTitle" class="form-control fw-bold fs-4 border-0 px-0 mb-3" placeholder="Judul Pekerjaan..." required style="color: #0f172a; letter-spacing: -0.02em;">
                    <div class="desc-editor">
                        <div class="desc-toolbar">
                            <button type="button" class="desc-tool" onclick="execFmt('bold')" title="Bold (Ctrl+B)"><i class="bi bi-type-bold"></i></button>
                            <button type="button" class="desc-tool" onclick="execFmt('italic')" title="Italic (Ctrl+I)"><i class="bi bi-type-italic"></i></button>
                            <button type="button" class="desc-tool" onclick="execFmt('insertUnorderedList')" title="Bullet"><i class="bi bi-list-ul"></i></button>
                            <button type="button" class="desc-tool" onclick="execFmt('insertOrderedList')" title="Daftar"><i class="bi bi-list-ol"></i></button>
                            <span class="desc-divider"></span>
                            <button type="button" class="desc-tool" onclick="execFmt('removeFormat')" title="Hapus Format"><i class="bi bi-eraser"></i></button>
                            <span class="desc-hint">Ctrl+B = Bold ✨</span>
                        </div>
                        <div contenteditable="true" id="richDesc" class="rich-editor"></div>
                        <textarea name="description" id="inpDesc" hidden></textarea>
                    </div>
                    <div class="bg-light p-3 rounded-4 mt-3 row g-2">
                        <div class="col-6"><label class="small text-muted fw-bold mb-1" style="font-size: 0.72rem; text-transform: uppercase;">Tanggal Mulai</label><input type="date" name="start_date" id="inpStart" class="form-control border-0 bg-white rounded-3" value="<?php echo date('Y-m-d'); ?>"></div>
                        <div class="col-6"><label class="small text-muted fw-bold mb-1" style="font-size: 0.72rem; text-transform: uppercase;">Target Selesai</label><input type="date" name="end_date" id="inpEnd" class="form-control border-0 bg-white rounded-3" value="<?php echo date('Y-m-d'); ?>"></div>
                    </div>
                    <div class="mt-3">
                        <div class="drop-zone" id="dropZone">
                            <input type="file" id="fileInput" name="files[]" multiple hidden>
                            <div class="drop-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                            <div class="drop-text">Drag & drop file di sini, atau <strong onclick="document.getElementById('fileInput').click()">pilih file</strong></div>
                            <div class="drop-hint">Foto, Video, PDF, Dokumen (maks. 10MB per file)</div>
                        </div>
                        <div id="file-preview-container" class="preview-grid"></div>
                        
                        <div id="existing-files-container" class="mt-3" style="display:none;">
                            <label class="small text-muted fw-bold mb-2">File Terupload Sebelumnya</label>
                            <div id="existing-files-list" style="display:flex; flex-wrap:wrap; gap:8px;"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0"><button class="btn btn-3d-primary w-100 rounded-pill py-2" onclick="submitJob()"><i class="bi bi-cloud-check-fill me-1"></i> Simpan Pekerjaan</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="progressModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header border-0 px-4 pt-4 pb-2" style="background: #ffffff;">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 34px; height: 34px; border-radius: 12px; background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; display: inline-flex; align-items: center; justify-content: center; font-size: 1.15rem; box-shadow: 0 4px 10px rgba(245,158,11,0.2);"><i class="bi bi-lightning-charge-fill"></i></span>
                    <h6 class="fw-bold m-0" id="progress-modal-title" style="color: #0f172a; font-size: 1rem;">Update Progress Kerja</h6>
                </div>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4 pt-2" style="background: #ffffff;">
                <form id="formProgress">
                    <input type="hidden" name="job_id" id="p-job-id">
                    <div class="mb-3" id="p-status-wrap">
                        <label class="small text-muted fw-bold mb-1" style="font-size: 0.74rem; letter-spacing: 0.5px; text-transform: uppercase;">Status Baru</label>
                        <select name="status" id="p-status" class="form-select bg-light border-0 fw-bold" style="border-radius: 12px; padding: 10px 14px; font-size: 0.88rem; color: #1e293b;">
                            <option value="todo">⚪ Belum Mulai</option>
                            <option value="in_progress">🟡 Dalam Proses</option>
                            <option value="done">🟢 Selesai</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted fw-bold mb-1" style="font-size: 0.74rem; letter-spacing: 0.5px; text-transform: uppercase;">Catatan <span class="fw-normal text-muted">(Ketik @ untuk tag)</span></label>
                        <textarea name="notes" id="p-notes" class="form-control bg-light border-0" rows="3" placeholder="Tuliskan catatan progres atau update..." style="border-radius: 14px; padding: 12px 14px; font-size: 0.88rem; color: #1e293b; resize: none;"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="drop-zone" id="progressDropZone" style="padding: 20px 15px;">
                            <input type="file" id="progressFileInput" name="files[]" multiple hidden>
                            <div class="drop-icon" style="width: 38px; height: 38px; font-size: 1.2rem; margin-bottom: 6px;"><i class="bi bi-cloud-arrow-up"></i></div>
                            <div class="drop-text" style="font-size: 0.82rem;">Drop file atau <strong onclick="document.getElementById('progressFileInput').click()">pilih file</strong></div>
                        </div>
                        <div id="progress-preview-container" class="preview-grid mt-2"></div>
                    </div>
                </form>
                <button class="btn btn-3d-primary w-100 rounded-pill py-2 mt-2" onclick="saveProgress()" style="font-size: 0.92rem;"><i class="bi bi-check2-circle me-1"></i> Simpan Update</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mediaModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen bg-dark p-0">
        <div class="modal-content bg-transparent">
            <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-4 z-3" data-bs-dismiss="modal"></button>
            <div class="modal-body d-flex justify-content-center align-items-center" id="media-container"></div>
        </div>
    </div>
</div>

<!-- Viewers Modal -->
<div class="modal fade" id="viewersModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div>
                    <h6 class="fw-bold mb-0" style="color: #111827; font-size: 0.95rem;"><i class="bi bi-eye me-2" style="color: #eab308;"></i>Dilihat oleh</h6>
                    <small id="viewers-count" style="color: #9ca3af; font-size: 0.75rem;"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 0.7rem;"></button>
            </div>
            <div class="modal-body px-4 py-3" id="viewers-list" style="max-height: 350px;">
                <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-warning"></div></div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
let curJob = null;
let selectedFiles = []; 
let progressFiles = []; 

function toggleLoading(show) { if(show) $('#loading-overlay').css('display', 'flex'); else $('#loading-overlay').hide(); }

// --- UPLOADS ---
$('#fileInput').on('change', function(e) {
    const files = Array.from(e.target.files);
    selectedFiles = selectedFiles.concat(files);
    updatePreviews('file-preview-container', selectedFiles, 'selectedFiles');
    $(this).val('');
});

$('#progressFileInput').on('change', function(e) {
    const files = Array.from(e.target.files);
    progressFiles = progressFiles.concat(files);
    updatePreviews('progress-preview-container', progressFiles, 'progressFiles');
    $(this).val('');
});

function updatePreviews(containerId, fileArray, arrayName) {
    const container = $('#' + containerId); container.empty();
    fileArray.forEach((file, index) => {
        let pc = '';
         const objUrl = URL.createObjectURL(file);
        if (file.type.startsWith('image/')) pc = `<img src="${objUrl}" onclick="openLightbox('${objUrl}','${file.name.replace(/'/g,"\\'")}')">`;
        else if (file.type.startsWith('video/')) pc = `<div class="preview-content"><i class="bi bi-camera-video"></i><div class="file-name-small">${file.name}</div></div>`;
        else pc = `<div class="preview-content"><i class="bi bi-file-earmark-text"></i><div class="file-name-small">${file.name}</div></div>`;
        container.append(`<div class="preview-item"><button type="button" class="btn-remove-file" onclick="removeFile(${index}, '${arrayName}')"><i class="bi bi-x"></i></button><div class="preview-content">${pc}</div></div>`);
    });
}

function removeFile(index, arrayName) {
    if(arrayName === 'selectedFiles') { selectedFiles.splice(index, 1); updatePreviews('file-preview-container', selectedFiles, 'selectedFiles'); } 
    else { progressFiles.splice(index, 1); updatePreviews('progress-preview-container', progressFiles, 'progressFiles'); }
}

// --- ACTIONS ---
function openDetail(id){
    curJob=id;
    $.post('ajax_action.php', {action:'fetch_detail', job_id:id}, function(res){
        if(res.status=='success'){
            let j=res.job;
            $('#d-title').text(j.title); $('#d-desc').html(formatText(j.description));
            $('#d-name').text(j.nickname||j.name); $('#d-date').text(j.date_fmt); $('#d-avatar').attr('src',j.avatar_url);
            
            // 3D Status Badges
            let sc = {
                todo: {class:'badge-3d-todo', label:'Belum Mulai'},
                in_progress: {class:'badge-3d-progress', label:'Dalam Proses'},
                done: {class:'badge-3d-done', label:'Selesai'}
            };
            let s = sc[j.status] || sc.todo;
            $('#d-status-badge').html(`<span class="badge-3d-status ${s.class}"><span class="pulse-dot"></span> ${s.label}</span>`);
            
            // 3D Timeline Stepper
            let th=''; 
            if(res.history.length){ 
                th += '<div class="ps-1">';
                res.history.forEach((h, i)=>{ 
                    let sClass = h.status_after === 'done' ? 'badge-3d-done' : (h.status_after === 'in_progress' ? 'badge-3d-progress' : 'badge-3d-todo');
                    let dotColor = h.status_after === 'done' ? '#10b981' : (h.status_after === 'in_progress' ? '#f59e0b' : '#94a3b8');
                    
                    // Render progress attachments HTML
                    let pattHtml = '';
                    if (h.attachments && h.attachments.length > 0) {
                        pattHtml += `<div class="row g-2 mt-2">`;
                        h.attachments.forEach(a => {
                            let p = 'assets/uploads/bukti/' + a.file_path;
                            if (a.file_type == 'image') {
                                pattHtml += `<div class="col-4"><div style="position:relative; border-radius:12px; overflow:hidden; cursor:pointer; aspect-ratio:1; background:#f8fafc; border:1px solid rgba(226,232,240,0.9); box-shadow:0 2px 6px rgba(0,0,0,0.04);" onclick="showMedia('${a.file_path}','image')"><img src="${p}" class="w-100 h-100" style="object-fit:cover;"></div></div>`;
                            } else if (a.file_type == 'video') {
                                pattHtml += `<div class="col-12"><video src="${p}" controls class="w-100 rounded-3 shadow-sm" style="max-height:160px; background:#000;"></video></div>`;
                            } else {
                                pattHtml += `<div class="col-12"><a href="${p}" target="_blank" class="d-flex align-items-center gap-2 p-2 px-3 text-decoration-none border rounded-3" style="background:#ffffff; font-size:0.78rem; border-color:rgba(226,232,240,0.9)!important;"><i class="bi bi-file-earmark-text-fill text-warning"></i> <span style="font-weight:600; color:#334155;">${a.file_name}</span></a></div>`;
                            }
                        });
                        pattHtml += `</div>`;
                    }

                    th+=`<div class="timeline-node">
                        <div class="timeline-icon-dot" style="border-color:${dotColor}; box-shadow: 0 0 0 3px ${dotColor}25;"></div>
                        <div class="chat-bubble-card">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold" style="font-size:0.88rem; color:#0f172a;">${h.name}</span>
                                <small style="font-size:0.72rem; color:#94a3b8;">${h.date}</small>
                            </div>
                            <span class="badge-3d-status ${sClass} my-1" style="font-size:0.65rem; padding:3px 8px;"><span class="pulse-dot" style="width:5px;height:5px;"></span> ${h.status_after}</span>
                            ${h.notes ? `<div class="mt-2 mb-0" style="font-size:0.88rem; color:#334155; line-height:1.6;">${formatText(h.notes)}</div>` : ''}
                            ${pattHtml}
                        </div>
                    </div>`; 
                }); 
                th += '</div>';
            } else { 
                th='<div class="text-center py-4 px-3" style="background:#f8fafc; border-radius:16px; border:1px dashed rgba(226,232,240,0.9);"><div style="width:44px; height:44px; border-radius:14px; background:#fffbeb; color:#d97706; display:inline-flex; align-items:center; justify-content:center; font-size:1.3rem; margin-bottom:8px;"><i class="bi bi-lightning-charge-fill"></i></div><h6 class="fw-bold m-0" style="font-size:0.9rem; color:#0f172a;">Belum ada update progres</h6><p class="mt-1 mb-0" style="font-size:0.78rem; color:#94a3b8;">Klik tombol "+ Update Progres" untuk mencatat progres pekerjaan.</p></div>'; 
            }
            $('#d-timeline').html(th);
            
            // 3D Attachment gallery
            let ah=''; 
            res.attachments.forEach(a=>{ 
                let p='assets/uploads/bukti/'+a.file_path; 
                if(a.file_type=='image') {
                    ah+=`<div class="col-4"><div style="position:relative; border-radius:14px; overflow:hidden; cursor:pointer; aspect-ratio:1; background:#f8fafc; border:1px solid rgba(226,232,240,0.9); box-shadow:0 2px 8px rgba(0,0,0,0.04);" onclick="showMedia('${a.file_path}','image')"><img src="${p}" class="w-100 h-100" style="object-fit:cover; transition:transform 0.3s ease;" onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform='scale(1)'"><div style="position:absolute;inset:0;background:linear-gradient(transparent 60%,rgba(0,0,0,0.4));opacity:0;transition:opacity 0.25s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'"><i class="bi bi-arrows-fullscreen position-absolute bottom-0 end-0 m-2 text-white" style="font-size:0.85rem;"></i></div></div></div>`; 
                } else if(a.file_type=='video') {
                    ah+=`<div class="col-12"><video src="${p}" controls class="w-100 rounded-4 shadow-sm" style="max-height:300px; background:#000;"></video></div>`; 
                } else {
                    ah+=`<div class="col-12"><a href="${p}" target="_blank" class="d-flex align-items-center gap-3 p-3 text-decoration-none" style="background:#ffffff; border:1px solid rgba(226,232,240,0.9); border-radius:16px; box-shadow:0 2px 6px rgba(0,0,0,0.03); transition:all 0.2s;" onmouseover="this.style.borderColor='#f59e0b'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='rgba(226,232,240,0.9)'; this.style.transform='translateY(0)'"><div style="width:42px;height:42px;border-radius:12px;background:#fef3c7;display:flex;align-items:center;justify-content:center; flex-shrink:0;"><i class="bi bi-file-earmark-text-fill" style="color:#d97706; font-size:1.2rem;"></i></div><div><div style="font-size:0.88rem; font-weight:700; color:#0f172a;">${a.file_name}</div><div style="font-size:0.72rem; color:#94a3b8;">Klik untuk buka / download</div></div></a></div>`; 
                }
            });
            $('#d-att').html(ah);
            
            if (res.attachments.length === 0) {
                $('#d-att').hide();
            } else {
                $('#d-att').show();
            }

            renderComments(res.comments);
            $('#d-like-count').text(j.like_count);
            let btn=$('#d-like-btn'); 
            if(j.is_liked) {
                btn.addClass('liked');
                btn.find('i').css('color', '#d97706');
            } else {
                btn.removeClass('liked');
                btn.find('i').css('color', '#94a3b8');
            }
            
            $('#btn-update-progress').toggle(res.is_owner || res.is_tagged);
            window._curIsOwner  = res.is_owner;
            window._curIsTagged = res.is_tagged;
            $('#p-job-id').val(id);
            
            // Render viewers section
            let vh = '';
            if(res.viewers && res.viewers.length > 0) {
                let avatars = res.viewers.slice(0, 5).map((v, i) => 
                    `<img src="${v.avatar}" class="rounded-circle" width="24" height="24" style="object-fit:cover; border:2px solid white; margin-left:${i > 0 ? '-8px' : '0'}; position:relative; z-index:${10-i}; box-shadow:0 1px 3px rgba(0,0,0,0.1);" title="${v.name} • ${v.viewed_at_fmt}">`
                ).join('');
                let extra = res.view_count > 5 ? `<span style="margin-left:-4px; width:24px; height:24px; border-radius:50%; background:#f1f5f9; display:inline-flex; align-items:center; justify-content:center; font-size:0.65rem; font-weight:700; color:#64748b; border:2px solid white; position:relative; z-index:1;">+${res.view_count - 5}</span>` : '';
                vh = `<div class="d-flex align-items-center gap-2 mt-2">
                    <div class="d-flex align-items-center">${avatars}${extra}</div>
                    <span style="font-size:0.75rem; color:#64748b; font-weight:500;">${res.view_count} orang melihat</span>
                </div>`;
            }
            $('#d-viewers').html(vh);
            
            new bootstrap.Modal('#detailModal').show();
        }
    },'json');
}

function renderComments(arr){
    let h=''; 
    if(arr.length === 0) {
        h = '<div class="text-center py-5 px-3"><div style="width:48px; height:48px; border-radius:16px; background:linear-gradient(135deg, #fef3c7, #fde68a); color:#d97706; display:inline-flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:10px; box-shadow:0 4px 12px rgba(245,158,11,0.2);"><i class="bi bi-chat-dots-fill"></i></div><h6 class="fw-bold m-0" style="font-size:0.92rem; color:#0f172a;">Belum ada diskusi</h6><p class="mt-1 mb-0" style="font-size:0.78rem; color:#94a3b8;">Tuliskan tanggapan atau tag rekan tim di bawah.</p></div>';
    } else {
        arr.forEach(c=>{
            let delBtn = c.is_mine ? `<div class="mt-1 d-flex gap-2"><button class="btn btn-link p-0 text-decoration-none fw-bold" style="font-size:0.7rem; color:#d97706;" onclick="editComment(${c.id}, '${c.content.replace(/'/g, "\\'")}')">Edit</button><button class="btn btn-link p-0 text-decoration-none fw-bold" style="font-size:0.7rem; color:#ef4444;" onclick="delComment(${c.id})">Hapus</button></div>` : '';
            h+=`<div class="d-flex gap-2 mb-3">
                <img src="${c.avatar}" class="rounded-circle shadow-sm" width="32" height="32" style="object-fit:cover; flex-shrink:0; border:2px solid white;">
                <div class="w-100">
                    <div class="chat-bubble-card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold" style="font-size:0.85rem; color:#0f172a;">${c.name}</span>
                            <small style="font-size:0.68rem; color:#94a3b8;">${c.date}</small>
                        </div>
                        <div style="font-size:0.88rem; color:#334155; line-height:1.55;">${formatText(c.content)}</div>
                    </div>
                    ${delBtn}
                </div>
            </div>`;
        });
    }
    $('#d-comments').html(h);
}

function sendComment(){
    let c=$('#d-input').val().trim(); if(!c) return;
    $.post('ajax_action.php', {action:'comment', job_id:curJob, content:c}, function(){ openDetail(curJob); $('#d-input').val(''); });
}

// FIX: Event Delegation untuk Enter
$(document).on('keydown', '#d-input', function(e){
    if(e.key === 'Enter' || e.keyCode === 13) { e.preventDefault(); sendComment(); }
});

function delComment(id){ if(confirm('Hapus komentar?')) $.post('ajax_action.php', {action:'delete_comment', comment_id:id}, function(){ openDetail(curJob); }); }
function editComment(id, old){ let n=prompt("Edit:", old); if(n!==null && n.trim()!=="") $.post('ajax_action.php', {action:'edit_comment', comment_id:id, content:n}, function(){ openDetail(curJob); }); }

function saveProgress(){
    let fd = new FormData($('#formProgress')[0]); fd.append('action', 'update_progress');
    fd.delete('files[]'); progressFiles.forEach((f) => { fd.append('files[]', f); });
    toggleLoading(true);
    $.ajax({url:'ajax_action.php', type:'POST', data:fd, contentType:false, processData:false, success:function(){ location.reload(); }, error: function(){ toggleLoading(false); alert('Error'); }});
}

function submitJob(){
    syncDesc(); // Sync rich editor → hidden textarea
    let form = document.getElementById('formJob'); let fd = new FormData(form);
    if(!fd.get('title')) { alert('Judul wajib diisi!'); return; }
    fd.delete('files[]'); selectedFiles.forEach((file) => { fd.append('files[]', file); });
    toggleLoading(true);
    $.ajax({url:'ajax_action.php', type:'POST', data:fd, contentType:false, processData:false, dataType:'json', success:function(d){ if(d.status=='success') location.reload(); else { toggleLoading(false); alert(d.message); } }, error:function(){ toggleLoading(false); alert('Error server'); }});
}

function openEditModal(id){
    $.post('ajax_action.php', {action:'fetch_detail', job_id:id}, function(res){
        if(res.status=='success'){
            let j=res.job;
            $('#modalTitle').text('Edit Pekerjaan'); $('#formAction').val('edit_post'); $('#formJobId').val(j.id);
            $('#inpTitle').val(j.title); $('#inpDesc').val(j.description); $('#inpStatus').val(j.status);
            // Load description into rich editor
            document.getElementById('richDesc').innerHTML = plainToRich(j.description);
            $('#inpStart').val(j.start_date); $('#inpEnd').val(j.end_date);
            selectedFiles = []; updatePreviews('file-preview-container', selectedFiles, 'selectedFiles');
            
            // Render existing attachments for editing
            const extContainer = document.getElementById('existing-files-container');
            const extList = document.getElementById('existing-files-list');
            if (res.attachments && res.attachments.length > 0) {
                extContainer.style.display = 'block';
                let eh = '';
                res.attachments.forEach(a => {
                    let p = 'assets/uploads/bukti/' + a.file_path;
                    let iconHtml = '';
                    if (a.file_type == 'image') {
                        iconHtml = `<img src="${p}" style="width:100%;height:100%;object-fit:cover;">`;
                    } else if (a.file_type == 'video') {
                        iconHtml = `<div class="d-flex align-items-center justify-content-center h-100 bg-dark rounded text-white" style="width:80px;"><i class="bi bi-play-circle-fill" style="font-size:1.5rem;"></i></div>`;
                    } else {
                        iconHtml = `<div class="d-flex align-items-center justify-content-center h-100 bg-light rounded text-secondary" style="width:80px;"><i class="bi bi-file-earmark-text" style="font-size:1.5rem;"></i></div>`;
                    }
                    eh += `
                    <div id="att-card-${a.id}" style="position:relative; width:80px; height:80px; border-radius:10px; overflow:hidden; border:1px solid #e5e7eb;">
                        ${iconHtml}
                        <button type="button" onclick="deleteExistingAttachment(${a.id})" class="btn btn-danger btn-sm p-0 d-flex align-items-center justify-content-center" style="position:absolute; top:4px; right:4px; width:20px; height:20px; border-radius:50%; font-size:0.65rem; box-shadow:0 1px 3px rgba(0,0,0,0.3);">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>`;
                });
                extList.innerHTML = eh;
            } else {
                extContainer.style.display = 'none';
                extList.innerHTML = '';
            }
            
            new bootstrap.Modal('#createModal').show();
        }
    },'json');
}

function deleteExistingAttachment(id) {
    if (confirm('Hapus file ini secara permanen?')) {
        $.post('ajax_action.php', {action: 'delete_attachment', attachment_id: id}, function(res) {
            if (res.status == 'success') {
                $(`#att-card-${id}`).remove();
                if ($('#existing-files-list').children().length === 0) {
                    $('#existing-files-container').hide();
                }
            } else {
                alert(res.message);
            }
        }, 'json');
    }
}

function deletePost(id){ if(confirm('Yakin hapus?')) $.post('ajax_action.php', {action:'delete_post', job_id:id}, function(){ location.reload(); }); }

function toggleLike(id, btn){
    $.post('ajax_action.php', {action:'like', job_id:id}, function(res){
        if(res.status=='success') {
            $(btn).find('.count').text(res.count);
            if(res.liked) {
                $(btn).addClass('liked');
                $(btn).find('i').css('color', '#d97706');
            } else {
                $(btn).removeClass('liked');
                $(btn).find('i').css('color', '#94a3b8');
            }
        }
    },'json');
}
function toggleLikeInModal(){ toggleLike(curJob, $('#d-like-btn')); }

function showMedia(p,t){ 
    let fp='assets/uploads/bukti/'+p; 
    let c = t=='image' ? `<img src="${fp}" style="max-height:90vh; max-width:100%">` : `<video src="${fp}" controls autoplay style="max-height:90vh; max-width:100%"></video>`;
    $('#media-container').html(c); new bootstrap.Modal('#mediaModal').show();
}

function showViewers(jobId) {
    $('#viewers-list').html('<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-warning"></div></div>');
    $('#viewers-count').text('');
    new bootstrap.Modal('#viewersModal').show();
    
    // Fetch viewers via detail endpoint
    $.post('ajax_action.php', {action:'fetch_detail', job_id: jobId}, function(res) {
        if(res.status == 'success') {
            let v = res.viewers || [];
            $('#viewers-count').text(res.view_count || 0);
            
            if(v.length === 0) {
                $('#viewers-list').html('<div class="text-center py-4 text-muted" style="font-size:0.85rem;"><i class="bi bi-eye-slash d-block mb-1" style="font-size:1.5rem; color:#cbd5e1;"></i>Belum ada yang melihat</div>');
            } else {
                let vListHtml = '<div class="d-flex flex-column gap-2">';
                v.forEach(user => {
                    vListHtml += `
                        <div class="d-flex align-items-center gap-3 p-2 rounded-3" style="background:#f8fafc; border:1px solid rgba(226,232,240,0.8);">
                            <img src="${user.avatar}" class="rounded-circle shadow-sm" width="38" height="38" style="object-fit:cover; border:2px solid white; flex-shrink:0;">
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="fw-bold text-truncate" style="font-size:0.88rem; color:#0f172a;">${user.name}</div>
                                <div style="font-size:0.72rem; color:#94a3b8;"><i class="bi bi-clock me-1"></i>${user.viewed_at_fmt}</div>
                            </div>
                        </div>
                    `;
                });
                vListHtml += '</div>';
                $('#viewers-list').html(vListHtml);
            }
        }
    }, 'json');
}

function showProgressForm() { 
    if(!window._curIsOwner && !window._curIsTagged) { alert('Hanya pembuat atau orang yang di-tag yang dapat mengupdate progres pekerjaan ini.'); return; }
    $('#p-job-id').val(curJob); 
    progressFiles = []; 
    updatePreviews('progress-preview-container', progressFiles, 'progressFiles');
    $('#progress-modal-title').text('Update Progress Kerja');
    $('#p-status-wrap').show();
    $('#p-notes').val('');
    new bootstrap.Modal('#progressModal').show(); 
}

function formatText(t){ 
    if(!t) return '';
    t = t.replace(/\*([^*]+)\*/g, '<strong>$1</strong>'); // *bold*
    t = t.replace(/_([^_]+)_/g, '<em>$1</em>'); // _italic_
    t = t.replace(/@([a-zA-Z0-9_]+)/g, '<span class="mention-tag">@$1</span>');
    t = t.replace(/\n/g, '<br>');
    return t;
}

function setupMentions(sel) {
    $(document).on('input', sel, function() {
        let val = $(this).val(); let cp = this.selectionStart; let lastAt = val.lastIndexOf('@', cp - 1);
        if(lastAt !== -1 && !val.substring(lastAt+1, cp).includes(' ')) {
            let q = val.substring(lastAt+1, cp); let off = $(this).offset();
            if($('#mention-box').length===0) $('body').append('<div id="mention-box" class="mention-list"></div>');
            $('#mention-box').css({top:off.top+$(this).outerHeight(), left:off.left, display:'block'});
            $.get('ajax_action.php', {action:'search_users', term:q}, function(res){
                let h=''; if(res.length){ 
                    res.forEach(u=>{ 
                        h+=`<div class="mention-item" onmousedown="event.preventDefault(); insertTag('${u.nickname}', ${lastAt}, ${cp}, '${sel}')">
                                <img src="${u.avatar}" class="mention-avatar">
                                <div class="mention-info">
                                    <span class="mention-name">${u.name}</span>
                                    <span class="mention-nick">@${u.nickname}</span>
                                </div>
                            </div>`; 
                    }); 
                } else { h='<div class="p-2 small text-muted text-center">...</div>'; }
                $('#mention-box').html(h);
            },'json');
        } else { $('#mention-box').hide(); }
    });
    $(document).on('click', function(e){ if(!$(e.target).closest('#mention-box').length) $('#mention-box').hide(); });
}
function insertTag(n,s,e,sel){ let i=$(sel); i.val(i.val().substring(0,s)+'@'+n+' '+i.val().substring(e)).focus(); $('#mention-box').hide(); }

// @mention for contenteditable rich editor
let savedRange = null;

function setupRichMentions(editorId) {
    const editor = document.getElementById(editorId);
    if (!editor) return;
    
    editor.addEventListener('input', function() {
        const sel = window.getSelection();
        if (!sel.rangeCount) return;
        
        const range = sel.getRangeAt(0);
        savedRange = range.cloneRange(); // Save selection range
        
        const textNode = range.startContainer;
        if (textNode.nodeType !== Node.TEXT_NODE) { $('#mention-box').hide(); return; }
        
        const text = textNode.textContent;
        const cursorPos = range.startOffset;
        const lastAt = text.lastIndexOf('@', cursorPos - 1);
        
        if (lastAt !== -1 && !text.substring(lastAt + 1, cursorPos).includes(' ')) {
            const query = text.substring(lastAt + 1, cursorPos);
            
            // Position dropdown near cursor
            const rect = range.getBoundingClientRect();
            if ($('#mention-box').length === 0) $('body').append('<div id="mention-box" class="mention-list"></div>');
            
            // Update positioning and style of mention box
            $('#mention-box').css({
                top: rect.bottom + window.scrollY + 4,
                left: rect.left + window.scrollX,
                display: 'block'
            });
            
            $.get('ajax_action.php', {action: 'search_users', term: query}, function(res) {
                let h = '';
                if (res.length) {
                    res.forEach(u => {
                        // Use onmousedown with preventDefault to keep editor focus and selection
                        h += `<div class="mention-item" onmousedown="event.preventDefault(); insertRichTag('${u.nickname}', '${u.name}', '${editorId}')">
                                <img src="${u.avatar}" class="mention-avatar">
                                <div class="mention-info">
                                    <span class="mention-name">${u.name}</span>
                                    <span class="mention-nick">@${u.nickname}</span>
                                </div>
                            </div>`;
                    });
                } else {
                    h = '<div class="p-2 small text-muted text-center">Tidak ditemukan</div>';
                }
                $('#mention-box').html(h);
            }, 'json');
        } else {
            $('#mention-box').hide();
        }
    });
    
    // Track cursor positioning updates
    editor.addEventListener('keyup', function() {
        const sel = window.getSelection();
        if (sel.rangeCount) {
            savedRange = sel.getRangeAt(0).cloneRange();
        }
    });
    editor.addEventListener('mouseup', function() {
        const sel = window.getSelection();
        if (sel.rangeCount) {
            savedRange = sel.getRangeAt(0).cloneRange();
        }
    });
}

function insertRichTag(nickname, name, editorId) {
    try {
        const sel = window.getSelection();
        let range = savedRange || (sel.rangeCount ? sel.getRangeAt(0) : null);
        if (!range) return;

        let textNode = range.startContainer;
        if (textNode.nodeType !== Node.TEXT_NODE) {
            if (textNode.hasChildNodes() && range.startOffset < textNode.childNodes.length) {
                textNode = textNode.childNodes[range.startOffset];
            }
            if (!textNode || textNode.nodeType !== Node.TEXT_NODE) {
                // Fallback: just insert the span at current range
                const mentionSpan = document.createElement('span');
                mentionSpan.className = 'mention-tag';
                mentionSpan.contentEditable = 'false';
                mentionSpan.style.cssText = 'background:rgba(217,119,6,0.12);color:#92400e;padding:1px 6px;border-radius:4px;font-weight:600;font-size:0.85em;cursor:default;';
                mentionSpan.textContent = '@' + nickname;
                mentionSpan.dataset.nickname = nickname;
                
                range.insertNode(mentionSpan);
                
                const space = document.createTextNode('\u00A0');
                range.collapse(false);
                range.insertNode(space);
                
                const newRange = document.createRange();
                newRange.setStartAfter(space);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
                savedRange = newRange.cloneRange();
                $('#mention-box').hide();
                return;
            }
        }

        const text = textNode.textContent;
        const cursorPos = range.startOffset;
        const lastAt = text.lastIndexOf('@', cursorPos - 1);
        
        if (lastAt === -1) return;

        // Create range to select "@query" text
        const queryRange = document.createRange();
        queryRange.setStart(textNode, lastAt);
        queryRange.setEnd(textNode, cursorPos);
        
        // Delete "@query"
        queryRange.deleteContents();
        
        // Insert styled mention span
        const mentionSpan = document.createElement('span');
        mentionSpan.className = 'mention-tag';
        mentionSpan.contentEditable = 'false';
        mentionSpan.style.cssText = 'background:rgba(217,119,6,0.12);color:#92400e;padding:1px 6px;border-radius:4px;font-weight:600;font-size:0.85em;cursor:default;';
        mentionSpan.textContent = '@' + nickname;
        mentionSpan.dataset.nickname = nickname;
        
        queryRange.insertNode(mentionSpan);
        
        // Insert space after tag
        const space = document.createTextNode('\u00A0');
        const nextRange = document.createRange();
        nextRange.setStartAfter(mentionSpan);
        nextRange.collapse(true);
        nextRange.insertNode(space);
        
        // Set cursor after the space
        const finalRange = document.createRange();
        finalRange.setStartAfter(space);
        finalRange.collapse(true);
        
        sel.removeAllRanges();
        sel.addRange(finalRange);
        
        savedRange = finalRange.cloneRange();
        $('#mention-box').hide();
        
        const editor = document.getElementById(editorId);
        if (editor) editor.focus();
    } catch (err) {
        console.error("Mention tag insertion error:", err);
    }
}

// --- DRAG & DROP ---
function setupDropZone(zoneId, fileInputId, fileArray, arrayName, containerId) {
    const zone = document.getElementById(zoneId);
    if (!zone) return;
    
    // Click to open file picker
    zone.addEventListener('click', function(e) {
        if (e.target.tagName !== 'STRONG') document.getElementById(fileInputId).click();
    });
    
    // Drag events
    ['dragenter', 'dragover'].forEach(evt => {
        zone.addEventListener(evt, function(e) {
            e.preventDefault(); e.stopPropagation();
            zone.classList.add('drag-over');
        });
    });
    
    ['dragleave', 'drop'].forEach(evt => {
        zone.addEventListener(evt, function(e) {
            e.preventDefault(); e.stopPropagation();
            zone.classList.remove('drag-over');
        });
    });
    
    zone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = Array.from(dt.files);
        if (arrayName === 'selectedFiles') {
            selectedFiles = selectedFiles.concat(files);
            updatePreviews(containerId, selectedFiles, 'selectedFiles');
        } else {
            progressFiles = progressFiles.concat(files);
            updatePreviews(containerId, progressFiles, 'progressFiles');
        }
    });
    
    // Prevent default on body to avoid browser opening the file
    ['dragover', 'drop'].forEach(evt => {
        document.body.addEventListener(evt, function(e) { e.preventDefault(); });
    });
}

// --- SMART PASTE HANDLER ---
function cleanPastedText(html, plain) {
    // If we have HTML, convert it smartly
    if (html && html !== plain) {
        let temp = document.createElement('div');
        temp.innerHTML = html;
        
        // Convert <br> and block elements to newlines
        temp.querySelectorAll('br').forEach(el => el.replaceWith('\n'));
        temp.querySelectorAll('p, div, h1, h2, h3, h4, h5, h6, tr').forEach(el => {
            el.insertAdjacentText('afterend', '\n');
        });
        
        // Convert <li> to bullet points
        temp.querySelectorAll('li').forEach(el => {
            let parent = el.parentElement;
            let isOrdered = parent && parent.tagName === 'OL';
            let index = Array.from(parent ? parent.children : []).indexOf(el) + 1;
            let prefix = isOrdered ? index + '. ' : '• ';
            el.innerHTML = prefix + el.innerHTML;
            el.insertAdjacentText('afterend', '\n');
        });
        
        // Get plain text
        plain = temp.textContent || temp.innerText || '';
    }
    
    // Clean up the text
    let text = plain || '';
    
    // Normalize various unicode dashes/bullets to standard ones
    text = text.replace(/[\u2022\u2023\u25E6\u2043\u2219]/g, '•');
    text = text.replace(/[\u2013\u2014]/g, '-');
    text = text.replace(/[\u201C\u201D\u201E\u201F]/g, '"');
    text = text.replace(/[\u2018\u2019\u201A\u201B]/g, "'");
    text = text.replace(/\u00A0/g, ' '); // non-breaking space → regular space
    text = text.replace(/\u200B/g, '');  // zero-width space → remove
    text = text.replace(/\u200C/g, '');  // zero-width non-joiner → remove
    text = text.replace(/\u200D/g, '');  // zero-width joiner → remove
    text = text.replace(/\uFEFF/g, '');  // BOM → remove
    
    // Fix excessive whitespace
    text = text.replace(/[ \t]+/g, ' ');           // multiple spaces → single
    text = text.replace(/\n\s*\n\s*\n/g, '\n\n');  // max 2 newlines
    text = text.replace(/^\s+|\s+$/gm, function(match) { // trim each line
        return match.includes('\n') ? '\n' : match.trim() ? ' ' : '';
    });
    
    // Trim leading/trailing whitespace per line
    text = text.split('\n').map(line => line.trim()).join('\n');
    
    // Remove leading/trailing blank lines
    text = text.replace(/^\n+|\n+$/g, '');
    
    return text;
}

function setupSmartPaste(selector) {
    $(document).on('paste', selector, function(e) {
        e.preventDefault();
        
        let cd = (e.originalEvent || e).clipboardData;
        let html = cd.getData('text/html');
        let plain = cd.getData('text/plain');
        
        let cleaned = cleanPastedText(html, plain);
        
        // Insert at cursor position
        let ta = this;
        let start = ta.selectionStart;
        let end = ta.selectionEnd;
        let before = ta.value.substring(0, start);
        let after = ta.value.substring(end);
        
        ta.value = before + cleaned + after;
        
        // Move cursor to end of pasted text
        let newPos = start + cleaned.length;
        ta.setSelectionRange(newPos, newPos);
        
        // Auto-resize
        autoResizeTextarea(ta);
        
        // Show toast notification
        if (html && html !== plain) {
            showPasteToast();
        }
        
        // Trigger input event for mentions
        $(ta).trigger('input');
    });
}

function showPasteToast() {
    let existing = document.querySelector('.paste-toast');
    if (existing) existing.remove();
    
    let toast = document.createElement('div');
    toast.className = 'paste-toast';
    toast.innerHTML = '<i class="bi bi-magic"></i> Teks berhasil dirapikan!';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
}

function autoResizeTextarea(ta) {
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 400) + 'px';
}

// --- WYSIWYG EDITOR ---
function execFmt(cmd) {
    document.execCommand(cmd, false, null);
    document.getElementById('richDesc').focus();
}

// Convert rich HTML to plain text with *bold* markers for storage
function richToPlain(html) {
    let div = document.createElement('div');
    div.innerHTML = html;
    
    // Convert <b>/<strong> to *text*
    div.querySelectorAll('b, strong').forEach(el => {
        el.replaceWith('*' + el.textContent + '*');
    });
    // Convert <i>/<em> to _text_
    div.querySelectorAll('i, em').forEach(el => {
        el.replaceWith('_' + el.textContent + '_');
    });
    // Convert <li> to bullets
    div.querySelectorAll('li').forEach(el => {
        let parent = el.parentElement;
        let isOl = parent && parent.tagName === 'OL';
        let idx = Array.from(parent ? parent.children : []).indexOf(el) + 1;
        el.replaceWith((isOl ? idx + '. ' : '• ') + el.textContent + '\n');
    });
    // Convert <br> and block elements to newlines
    div.querySelectorAll('br').forEach(el => el.replaceWith('\n'));
    // Add \n before ul/ol so bullet list never merges with preceding text
    div.querySelectorAll('ul, ol').forEach(el => {
        el.insertAdjacentText('beforebegin', '\n');
        el.insertAdjacentText('afterend', '\n');
    });
    div.querySelectorAll('p, div').forEach(el => {
        el.insertAdjacentText('afterend', '\n');
    });
    
    let text = div.textContent || '';
    // Clean up
    text = text.replace(/\n{3,}/g, '\n\n');
    // Remove blank lines before bullet/ordered list items
    text = text.replace(/\n\n([•\d])/g, '\n$1');
    text = text.replace(/^\n+|\n+$/g, '');
    return text;
}

// Convert plain text with markers back to HTML for editing
function plainToRich(text) {
    if (!text) return '';
    let t = text
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/\*([^*]+)\*/g, '<strong>$1</strong>')
        .replace(/_([^_]+)_/g, '<em>$1</em>')
        .replace(/@(\w+)/g, '<span class="mention-tag" contenteditable="false" style="background:rgba(217,119,6,0.12);color:#92400e;padding:1px 6px;border-radius:4px;font-weight:600;font-size:0.85em;cursor:default;" data-nickname="$1">@$1</span>')
        .replace(/\n/g, '<br>');
    return t;
}

// Sync richDesc → hidden textarea before submit
function syncDesc() {
    let editor = document.getElementById('richDesc');
    let hidden = document.getElementById('inpDesc');
    if (editor && hidden) {
        hidden.value = richToPlain(editor.innerHTML);
    }
}

// Smart paste for contenteditable
function setupRichPaste() {
    let editor = document.getElementById('richDesc');
    if (!editor) return;
    
    editor.addEventListener('paste', function(e) {
        e.preventDefault();
        let cd = e.clipboardData;
        let plain = cd.getData('text/plain');
        
        // Clean the text
        plain = plain.replace(/\u00A0/g, ' ');
        plain = plain.replace(/\u200B|\u200C|\u200D|\uFEFF/g, '');
        plain = plain.replace(/[ \t]+/g, ' ');
        plain = plain.split('\n').map(l => l.trim()).join('\n');
        plain = plain.replace(/\n{3,}/g, '\n\n');
        
        // Insert as plain text (clean)
        document.execCommand('insertText', false, plain);
    });
}

$(document).ready(()=>{ 
    setupMentions('#inpDesc'); setupMentions('#d-input'); setupMentions('#p-notes');
    setupRichMentions('richDesc');
    setupDropZone('dropZone', 'fileInput', selectedFiles, 'selectedFiles', 'file-preview-container');
    setupDropZone('progressDropZone', 'progressFileInput', progressFiles, 'progressFiles', 'progress-preview-container');
    setupRichPaste();
    
    // Sync before any submit
    $(document).on('click', '[onclick*="submitJob"]', function() { syncDesc(); });
    
    $('#createModal').on('hidden.bs.modal', function(){ 
        $('#formJob')[0].reset(); 
        $('#modalTitle').text('Buat Pekerjaan Baru'); 
        $('#formAction').val('create_post'); 
        document.getElementById('richDesc').innerHTML = '';
        selectedFiles = []; 
        updatePreviews('file-preview-container', selectedFiles, 'selectedFiles'); 
    });

    // ── 3D Hero Parallax Mouse Interaction ──
    const heroCard = document.getElementById('hero3dCard');
    const heroContainer = document.getElementById('hero3dContainer');
    if (heroContainer && heroCard) {
        heroContainer.addEventListener('mousemove', function(e) {
            const rect = heroContainer.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            const tiltX = (y / (rect.height / 2)) * -22;
            const tiltY = (x / (rect.width / 2)) * 22;
            heroCard.style.transform = `rotateX(${tiltX}deg) rotateY(${tiltY}deg) scale3d(1.08, 1.08, 1.08)`;
        });
        heroContainer.addEventListener('mouseleave', function() {
            heroCard.style.transform = 'rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
        });
    }
});</script>

<!-- ═══ FILE LIGHTBOX ═══ -->
<div id="fileLightbox" onclick="if(event.target===this)closeLightbox()">
    <button class="lb-close" onclick="closeLightbox()"><i class="bi bi-x-lg"></i></button>
    <img id="lbImg" class="lb-img" src="" alt="Preview">
    <span id="lbName" class="lb-name"></span>
</div>
<script>
function openLightbox(src, name) {
    document.getElementById('lbImg').src = src;
    document.getElementById('lbName').textContent = name;
    document.getElementById('fileLightbox').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('fileLightbox').classList.remove('show');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});
</script>
