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
$limit = ($view_mode == 'social') ? 15 : 35;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$cond = ["j.deleted_at IS NULL"]; $param = [];
if($filter_user) { $cond[] = "j.user_id = ?"; $param[] = $filter_user; }
if($filter_status) { $cond[] = "j.status = ?"; $param[] = $filter_status; }
if($search_query) { $cond[] = "(j.title LIKE ? OR j.description LIKE ?)"; $param[] = "%$search_query%"; $param[] = "%$search_query%"; }
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
$my_av = ($me['avatar'] && file_exists("assets/img/avatars/" . $me['avatar'])) ? "assets/img/avatars/" . $me['avatar'] : "https://ui-avatars.com/api/?name=" . urlencode($myName);
$sapa = date('H')<11?"Selamat Pagi": (date('H')<15?"Selamat Siang": (date('H')<18?"Selamat Sore":"Selamat Malam"));

function time_ago($datetime) { return tgl_indo($datetime); }
function format_text($text) { 
    $t = htmlspecialchars($text);
    $t = preg_replace('/\*([^*]+)\*/', '<strong>$1</strong>', $t); // *bold* → bold
    $t = preg_replace('/_([^_]+)_/', '<em>$1</em>', $t); // _italic_ → italic
    $t = preg_replace('/@(\w+)/', '<span class="text-primary fw-bold">@$1</span>', $t);
    return nl2br($t);
}
?>

<style>
    /* Loading Overlay - Premium Gold */
    #loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(245,243,239,0.92); backdrop-filter: blur(8px); z-index: 9999; display: none; align-items: center; justify-content: center; flex-direction: column; }
    .loader { width: 48px; height: 48px; border: 4px solid rgba(234,179,8,0.2); border-top-color: #eab308; border-radius: 50%; display: inline-block; box-sizing: border-box; animation: rotation 0.8s linear infinite; }
    @keyframes rotation { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    /* Preview Files Grid */
    .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; margin-top: 15px; }
    .preview-item { position: relative; width: 100%; padding-top: 100%; background: #fefce8; border-radius: 12px; overflow: hidden; border: 1px solid rgba(234,179,8,0.15); transition: transform 0.2s ease; }
    .preview-item:hover { transform: scale(1.03); }
    .preview-content { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column; }
    .preview-content img, .preview-content video { width: 100%; height: 100%; object-fit: cover; }
    .preview-content i { font-size: 2rem; color: #eab308; }
    .btn-remove-file { position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,0.65); color: white; border: none; border-radius: 50%; width: 22px; height: 22px; font-size: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 5; transition: all 0.2s ease; }
    .btn-remove-file:hover { background: #ef4444; transform: scale(1.15); }
    .file-name-small { font-size: 0.6rem; text-align: center; width: 90%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; color: #6b7280; }
    
    /* Custom Scrollbar */
    .custom-scroll::-webkit-scrollbar { width: 5px; }
    .custom-scroll::-webkit-scrollbar-track { background: transparent; }
    .custom-scroll::-webkit-scrollbar-thumb { background-color: rgba(234,179,8,0.25); border-radius: 10px; }
    .custom-scroll::-webkit-scrollbar-thumb:hover { background-color: rgba(234,179,8,0.45); }

    /* Mention Autocomplete - Gold Theme */
    .mention-list {
        position: absolute;
        background: white;
        border: 1px solid rgba(0,0,0,0.06);
        border-radius: 14px;
        max-height: 220px;
        overflow-y: auto;
        width: 280px;
        z-index: 9999;
        box-shadow: 0 12px 36px rgba(0,0,0,0.12);
        display: none;
        padding: 6px 0;
    }
    .mention-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 15px;
        cursor: pointer;
        transition: all 0.15s ease;
        border-bottom: 1px solid rgba(0,0,0,0.03);
    }
    .mention-item:hover { background: rgba(234,179,8,0.08); }
    .mention-item:last-child { border-bottom: none; }
    .mention-avatar {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        object-fit: cover;
        flex-shrink: 0;
        border: 2px solid rgba(234,179,8,0.2);
    }
    .mention-info { display: flex; flex-direction: column; justify-content: center; }
    .mention-name { font-size: 0.88rem; font-weight: 600; color: #1a1a1a; line-height: 1.2; }
    .mention-nick { font-size: 0.73rem; color: #9ca3af; }

    /* Premium Page-Level Overrides */
    .text-primary { color: #eab308 !important; }
    .bg-primary { background-color: #eab308 !important; }
    .btn-primary { background: linear-gradient(135deg, #eab308, #facc15) !important; border: none !important; color: #1a1a1a !important; font-weight: 600 !important; }
    .btn-primary:hover { box-shadow: 0 4px 16px rgba(234,179,8,0.35) !important; transform: translateY(-1px); }
    .btn-outline-primary { border-color: #eab308 !important; color: #eab308 !important; }
    .btn-outline-primary:hover { background: #eab308 !important; color: #1a1a1a !important; }
    .page-link { color: #eab308; }
    .page-item.active .page-link { background: #eab308; border-color: #eab308; color: #1a1a1a; }
    .badge.bg-success { background: #16a34a !important; }
    .badge.bg-primary { background: linear-gradient(135deg, #eab308, #facc15) !important; color: #1a1a1a !important; }
    .form-control:focus, .form-select:focus { border-color: #eab308; box-shadow: 0 0 0 3px rgba(234,179,8,0.15); }
    .cursor-pointer { cursor: pointer; }
    
    /* Greeting section */
    .greeting-emoji { font-size: 1.8rem; animation: wave 1.5s ease-in-out infinite; display: inline-block; }
    @keyframes wave { 0%,100% { transform: rotate(0deg); } 25% { transform: rotate(15deg); } 75% { transform: rotate(-10deg); } }

    /* Drag & Drop Zone */
    .drop-zone {
        border: 2px dashed rgba(234,179,8,0.3);
        border-radius: 16px;
        padding: 28px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #fffef5;
        position: relative;
    }
    .drop-zone:hover {
        border-color: #eab308;
        background: #fefce8;
    }
    .drop-zone.drag-over {
        border-color: #eab308;
        background: rgba(234,179,8,0.08);
        transform: scale(1.01);
        box-shadow: 0 0 0 4px rgba(234,179,8,0.1);
    }
    .drop-zone .drop-icon {
        font-size: 2.2rem;
        color: #eab308;
        margin-bottom: 8px;
        transition: transform 0.3s ease;
    }
    .drop-zone:hover .drop-icon,
    .drop-zone.drag-over .drop-icon {
        transform: translateY(-4px) scale(1.1);
    }
    .drop-zone .drop-text {
        font-size: 0.88rem;
        color: #6b7280;
        font-weight: 500;
    }
    .drop-zone .drop-text strong {
        color: #eab308;
        cursor: pointer;
    }
    .drop-zone .drop-hint {
        font-size: 0.72rem;
        color: #9ca3af;
        margin-top: 4px;
    }

    /* Description Editor */
    .desc-editor {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        background: #fafafa;
    }
    .desc-editor:focus-within {
        border-color: #eab308;
        box-shadow: 0 0 0 3px rgba(234,179,8,0.12);
        background: white;
    }
    .desc-toolbar {
        display: flex;
        align-items: center;
        gap: 2px;
        padding: 6px 10px;
        background: #f9fafb;
        border-bottom: 1px solid #f3f4f6;
    }
    .desc-tool {
        background: none;
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }
    .desc-tool:hover {
        background: rgba(234,179,8,0.1);
        color: #eab308;
    }
    .desc-divider {
        width: 1px;
        height: 18px;
        background: #e5e7eb;
        margin: 0 6px;
    }
    .desc-hint {
        font-size: 0.68rem;
        color: #9ca3af;
        margin-left: auto;
    }
    .desc-editor textarea {
        background: transparent !important;
        resize: vertical;
        min-height: 100px;
        max-height: 400px;
        font-size: 0.9rem !important;
        line-height: 1.65;
        color: #374151;
    }
    .desc-editor textarea::placeholder {
        color: #9ca3af;
    }
    /* Contenteditable rich editor */
    .rich-editor {
        min-height: 120px;
        max-height: 400px;
        overflow-y: auto;
        padding: 12px 16px;
        font-size: 0.9rem;
        line-height: 1.65;
        color: #374151;
        outline: none;
        background: transparent;
    }
    .rich-editor:empty::before {
        content: 'Deskripsi... Gunakan @ untuk tag';
        color: #9ca3af;
        pointer-events: none;
    }
    .rich-editor b, .rich-editor strong { font-weight: 700; color: #111827; }
    .rich-editor i, .rich-editor em { font-style: italic; }
    /* Paste toast notification */
    .paste-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: #1a1a1a;
        color: white;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 500;
        z-index: 99999;
        animation: toastIn 0.3s ease, toastOut 0.3s ease 2s forwards;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    .paste-toast i { color: #eab308; margin-right: 6px; }
    @keyframes toastIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes toastOut { from { opacity: 1; } to { opacity: 0; } }
</style>

<div id="loading-overlay">
    <span class="loader"></span>
    <p class="mt-3 fw-bold" style="color: #eab308;">Sedang memproses data...</p>
</div>

<div class="main-wrapper">
    <div class="content-area">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div><h3 class="fw-bold mb-1"><?php echo $sapa; ?>, <?php echo htmlspecialchars(explode(' ', $myName)[0]); ?>!</h3><p class="text-muted mb-0">Lihat apa yang sedang dikerjakan tim hari ini.</p></div>
            <div class="btn-group shadow-sm bg-white rounded-pill p-1">
                <a href="?switch_mode=social" class="btn btn-sm rounded-pill px-3 <?php echo $view_mode=='social'?'btn-dark':'text-muted'; ?>"><i class="bi bi-grid-fill me-1"></i> Sosial</a>
                <a href="?switch_mode=formal" class="btn btn-sm rounded-pill px-3 <?php echo $view_mode=='formal'?'btn-dark':'text-muted'; ?>"><i class="bi bi-list-ul me-1"></i> Tabel</a>
            </div>
        </div>

        <div class="card-custom p-3 cursor-pointer mb-4" data-bs-toggle="modal" data-bs-target="#createModal">
            <div class="d-flex gap-3 align-items-center"><img src="<?php echo $my_av; ?>" class="rounded-circle" width="40" height="40"><div class="form-control border-0 bg-light rounded-pill text-muted">Apa yang sedang dikerjakan? (Ketik @ untuk tag)</div></div>
        </div>

        <?php if($view_mode == 'social'): ?>
            <div class="row">
            <?php foreach($jobs as $job): 
                $uName = $job['user_name'] ?? 'User'; $uAvatar = $job['user_avatar'] ?? '';
                $avatarSrc = ($uAvatar && file_exists("assets/img/avatars/".$uAvatar)) ? "assets/img/avatars/".$uAvatar : "https://ui-avatars.com/api/?name=".urlencode($uName);
            ?>
                <div class="col-12">
                    <div class="card-custom p-0" id="post-<?php echo $job['id']; ?>" data-status="<?php echo $job['status']; ?>">
                        <div class="p-3 d-flex justify-content-between align-items-start">
                            <div class="d-flex gap-3 align-items-center">
                                <img src="<?php echo $avatarSrc; ?>" class="rounded-3 shadow-sm" width="46" height="46" style="object-fit:cover; border: 2px solid rgba(234,179,8,0.15);">
                                <div>
                                    <div class="fw-bold" style="color: #111827; font-size: 0.92rem; letter-spacing: -0.01em;"><?php echo htmlspecialchars($job['nickname'] ?: $uName); ?></div>
                                    <div style="font-size: 0.76rem; color: #6b7280;"><?php echo time_ago($job['created_at']); ?><?php if($job['is_edited']): ?><span class="fst-italic ms-1" style="font-size:0.68rem; color: #9ca3af;">• Diedit</span><?php endif; ?></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?php 
                                    $statusConfig = [
                                        'done' => ['bg' => '#f0fdf4', 'color' => '#15803d', 'icon' => 'check-circle-fill', 'label' => 'SELESAI'],
                                        'in_progress' => ['bg' => '#fefce8', 'color' => '#a16207', 'icon' => 'play-circle-fill', 'label' => 'ON PROGRESS'],
                                        'todo' => ['bg' => '#f1f5f9', 'color' => '#475569', 'icon' => 'circle', 'label' => 'BELUM MULAI']
                                    ];
                                    $sc = $statusConfig[$job['status']] ?? $statusConfig['todo'];
                                ?>
                                <span class="px-3 py-2 rounded-pill fw-bold" style="background: <?php echo $sc['bg']; ?>; color: <?php echo $sc['color']; ?>; font-size: 0.72rem; letter-spacing: 0.3px;">
                                    <i class="bi bi-<?php echo $sc['icon']; ?> me-1"></i><?php echo $sc['label']; ?>
                                </span>
                                <?php if($job['user_id'] == $current_user_id): ?>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm rounded-circle border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;"><i class="bi bi-three-dots-vertical" style="color:#6b7280;"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3" style="min-width: 160px;">
                                        <li><a class="dropdown-item py-2" href="#" onclick="openEditModal(<?php echo $job['id']; ?>)" style="font-size:0.88rem; color:#374151;"><i class="bi bi-pencil me-2" style="color:#eab308;"></i> Edit</a></li>
                                        <li><a class="dropdown-item py-2" href="#" onclick="deletePost(<?php echo $job['id']; ?>)" style="font-size:0.88rem; color:#dc2626;"><i class="bi bi-trash me-2"></i> Hapus</a></li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="px-3 pb-2 cursor-pointer" onclick="openDetail(<?php echo $job['id']; ?>)">
                            <h5 class="fw-bold mb-2" style="color: #111827; font-size: 1rem; letter-spacing: -0.01em;"><?php echo htmlspecialchars($job['title']); ?></h5>
                            <p style="color: #374151; white-space: pre-wrap; line-height: 1.65; font-size: 0.9rem;"><?php echo (strlen($job['description'])>200) ? substr(format_text($job['description']),0,200).'...' : format_text($job['description']); ?></p>
                        </div>
                        <div class="px-3 py-2 border-top d-flex justify-content-between align-items-center" style="border-color: rgba(0,0,0,0.04) !important;">
                            <div class="d-flex gap-2">
                                <button onclick="toggleLike(<?php echo $job['id']; ?>, this)" class="btn btn-sm <?php echo $job['is_liked']?'text-white':''; ?> fw-bold px-3 rounded-pill" style="<?php echo $job['is_liked'] ? 'background: linear-gradient(135deg, #eab308, #facc15); color: #1a1a1a;' : 'background: #f9fafb; color: #4b5563;'; ?> font-size: 0.82rem;">
                                    <i class="bi bi-hand-thumbs-up-fill me-1"></i> <span class="count"><?php echo $job['l_count']; ?></span> Suka
                                </button>
                                <button onclick="openDetail(<?php echo $job['id']; ?>)" class="btn btn-sm fw-bold px-3 rounded-pill" style="background: #f9fafb; color: #4b5563; font-size: 0.82rem;">
                                    <i class="bi bi-chat-dots me-1"></i> <?php echo $job['c_count']; ?> Komentar
                                </button>
                            </div>
                            <div class="d-flex align-items-center gap-1" title="<?php echo $job['v_count']; ?> orang melihat">
                                <?php if(!empty($job['viewers'])): ?>
                                <div class="d-flex" style="margin-right: 4px;">
                                    <?php foreach(array_slice($job['viewers'], 0, 3) as $vi => $viewer): ?>
                                    <img src="<?php echo $viewer['avatar']; ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover; border: 2px solid white; margin-left: <?php echo $vi > 0 ? '-6px' : '0'; ?>; position:relative; z-index:<?php echo 5-$vi; ?>;" title="<?php echo htmlspecialchars($viewer['name']); ?>">
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <span style="font-size: 0.72rem; color: #9ca3af;"><i class="bi bi-eye me-1"></i><?php echo $job['v_count']; ?></span>
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
                                <span class="badge rounded-pill border fw-bold 
                                    <?php echo ($job['status']=='done' ? 'bg-success bg-opacity-10 text-success border-success border-opacity-25' : 
                                              ($job['status']=='in_progress' ? 'bg-primary bg-opacity-10 text-primary border-primary border-opacity-25' : 
                                              'bg-secondary bg-opacity-10 text-secondary border-secondary border-opacity-25')); ?> px-2 py-1" style="font-size: 0.75rem;">
                                    <?php 
                                        if($job['status']=='done') echo '<i class="bi bi-check-circle-fill me-1"></i> Selesai'; 
                                        elseif($job['status']=='in_progress') echo '<i class="bi bi-play-circle-fill me-1"></i> On Progress'; 
                                        else echo '<i class="bi bi-circle me-1"></i> Belum Mulai'; 
                                    ?>
                                </span>
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
        <nav class="mt-4"><ul class="pagination justify-content-center"><?php for($i=1; $i<=$total_pages; $i++): ?><li class="page-item <?php echo $page==$i?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav>
        <?php endif; ?>
    </div>

    <div class="widget-area">
        <div class="card-custom p-4">
            <h6 class="fw-bold mb-3">Filter & Pencarian</h6>
            <form>
                <div class="mb-3"><label class="small fw-bold text-muted mb-1">KATA KUNCI</label><input name="q" class="form-control bg-light border-0" placeholder="Cari..." value="<?php echo $search_query; ?>"></div>
                <div class="mb-3"><label class="small fw-bold text-muted mb-1">KARYAWAN</label><select name="user" class="form-select bg-light border-0"><option value="">Semua Karyawan</option><?php foreach($users_list as $u) echo "<option value='{$u['id']}' ".($filter_user==$u['id']?'selected':'').">{$u['name']}</option>"; ?></select></div>
                <div class="mb-3"><label class="small fw-bold text-muted mb-1">STATUS</label><select name="status" class="form-select bg-light border-0"><option value="">Semua Status</option><option value="todo">Belum Mulai</option><option value="in_progress">Dalam Proses</option><option value="done">Selesai</option></select></div>
                <div class="mb-3"><label class="small fw-bold text-muted mb-1">RENTANG WAKTU</label>
                    <div class="d-flex gap-1">
                        <input type="date" name="start" class="form-control form-control-sm bg-light border-0" style="font-size: 0.7rem;" value="<?php echo $filter_date_start; ?>">
                        <input type="date" name="end" class="form-control form-control-sm bg-light border-0" style="font-size: 0.7rem;" value="<?php echo $filter_date_end; ?>">
                    </div>
                </div>
                <button class="btn btn-primary w-100 fw-bold">Terapkan Filter</button>
            </form>
        </div>
        <div class="card-custom p-4">
            <h6 class="fw-bold mb-3">Statistik Saya</h6>
            <?php $stats = $conn->query("SELECT status, COUNT(*) as c FROM bukti_jobs WHERE user_id=$current_user_id AND deleted_at IS NULL GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR); ?>
            <div class="d-flex justify-content-between mb-2"><span><i class="bi bi-circle text-secondary me-2"></i> Belum Mulai</span><b><?php echo $stats['todo']??0; ?></b></div>
            <div class="d-flex justify-content-between mb-2"><span><i class="bi bi-play-circle-fill text-primary me-2"></i> Dalam Proses</span><b><?php echo $stats['in_progress']??0; ?></b></div>
            <div class="d-flex justify-content-between"><span><i class="bi bi-check-circle-fill text-success me-2"></i> Selesai</span><b><?php echo $stats['done']??0; ?></b></div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg" style="height: 85vh;">
            <div class="modal-body p-0 h-100">
                <div class="row g-0 h-100">
                    <!-- Left Panel: Content -->
                    <div class="col-lg-8 h-100 d-flex flex-column" style="background: #fafafa;">
                        <!-- Header -->
                        <div class="p-4 flex-shrink-0" style="background: white; border-bottom: 1px solid rgba(0,0,0,0.04);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex gap-3 align-items-center">
                                    <img src="" id="d-avatar" class="rounded-3 shadow-sm" width="50" height="50" style="object-fit: cover; border: 2px solid rgba(234,179,8,0.2);">
                                    <div>
                                        <h6 class="fw-bold mb-0" id="d-name" style="color: #111827; font-size: 0.95rem; letter-spacing: -0.01em;"></h6>
                                        <small id="d-date" style="color: #6b7280; font-size: 0.78rem;"></small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3"><div id="d-status-badge"></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            </div>
                            <div id="d-viewers"></div>
                        </div>
                        <!-- Body -->
                        <div class="p-4 overflow-auto custom-scroll flex-grow-1" style="min-height: 0;">
                            <h3 class="fw-bold mb-3" id="d-title" style="color: #111827; letter-spacing: -0.02em; font-size: 1.5rem; line-height: 1.3;"></h3>
                            <div id="d-desc" class="mb-4" style="white-space: pre-wrap; font-size: 0.92rem; line-height: 1.7; color: #374151;"></div>
                            <div id="d-att" class="row g-2 mb-4"></div>
                            <!-- Timeline Section -->
                            <div style="background: white; border: 1px solid rgba(0,0,0,0.04); border-radius: 16px; overflow: hidden;">
                                <div class="d-flex justify-content-between align-items-center p-3 px-4" style="border-bottom: 1px solid rgba(0,0,0,0.04);">
                                    <h6 class="fw-bold m-0" style="color: #111827; font-size: 0.92rem;"><i class="bi bi-activity me-2" style="color: #eab308;"></i>Timeline Progress</h6>
                                    <button class="btn btn-sm rounded-pill px-3 fw-bold" id="btn-update-progress" style="display:none; background: linear-gradient(135deg, #eab308, #facc15); color: #1a1a1a; font-size: 0.78rem;" onclick="showProgressForm()"><i class="bi bi-plus-lg me-1"></i> Update</button>
                                </div>
                                <div id="d-timeline" class="p-4"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Right Panel: Discussion -->
                    <div class="col-lg-4 h-100 d-flex flex-column" style="background: #f9fafb; border-left: 1px solid rgba(0,0,0,0.04);">
                        <div class="p-3 px-4 d-flex justify-content-between align-items-center flex-shrink-0" style="background: white; border-bottom: 1px solid rgba(0,0,0,0.04); height: 83px;">
                            <h6 class="fw-bold m-0" style="color: #111827; font-size: 0.95rem;"><i class="bi bi-chat-dots me-2" style="color: #eab308;"></i>Diskusi</h6>
                            <button class="btn btn-sm rounded-pill fw-bold px-3" id="d-like-btn" onclick="toggleLikeInModal()" style="background: #f9fafb; color: #4b5563; font-size: 0.82rem; border: 1px solid #e5e7eb;"><i class="bi bi-hand-thumbs-up-fill"></i>
                            <!--<span id="d-like-count">0</span>-->
                            </button>
                        </div>
                        <div id="d-comments" class="flex-grow-1 p-3 overflow-auto custom-scroll" style="min-height: 0;"></div>
                        <div class="p-3 flex-shrink-0" style="background: white; border-top: 1px solid rgba(0,0,0,0.04);">
                            <div class="position-relative">
                                <input id="d-input" class="form-control rounded-pill bg-light border-0 pe-5" placeholder="Ketik @ untuk tag..." style="padding: 10px 50px 10px 16px; font-size: 0.88rem; color: #374151;">
                                <button class="btn rounded-circle position-absolute top-50 end-0 translate-middle-y me-2" style="width:36px;height:36px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #eab308, #facc15); color: #1a1a1a;" onclick="sendComment()"><i class="bi bi-send-fill" style="font-size: 0.85rem;"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0"><h5 class="fw-bold" id="modalTitle">Buat Pekerjaan Baru</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="formJob">
                    <input type="hidden" name="action" value="create_post" id="formAction">
                    <input type="hidden" name="job_id" id="formJobId">
                    <div class="d-flex gap-2 mb-3 align-items-center">
                        <img src="<?php echo $my_av; ?>" class="rounded-circle" width="40">
                        <div class="fw-bold"><?php echo $myName; ?></div>
                        <select name="status" id="inpStatus" class="form-select form-select-sm border-0 bg-light fw-bold text-primary w-auto"><option value="todo">To Do List</option><option value="in_progress">On Progress</option><option value="done">Done</option></select>
                    </div>
                    <input type="text" name="title" id="inpTitle" class="form-control fw-bold fs-4 border-0 px-0 mb-2" placeholder="Judul Pekerjaan..." required>
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
                        <div class="col-6"><label class="small text-muted fw-bold">Mulai</label><input type="date" name="start_date" id="inpStart" class="form-control border-0 bg-transparent" value="<?php echo date('Y-m-d'); ?>"></div>
                        <div class="col-6"><label class="small text-muted fw-bold">Selesai</label><input type="date" name="end_date" id="inpEnd" class="form-control border-0 bg-transparent" value="<?php echo date('Y-m-d'); ?>"></div>
                    </div>
                    <div class="mt-3">
                        <div class="drop-zone" id="dropZone">
                            <input type="file" id="fileInput" name="files[]" multiple hidden>
                            <div class="drop-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                            <div class="drop-text">Drag & drop file di sini, atau <strong onclick="document.getElementById('fileInput').click()">pilih file</strong></div>
                            <div class="drop-hint">Foto, Video, PDF, Dokumen (maks. 10MB per file)</div>
                        </div>
                        <div id="file-preview-container" class="preview-grid"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0"><button class="btn btn-primary w-100 rounded-pill fw-bold py-2" onclick="submitJob()">Simpan</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="progressModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0 pb-0"><h6 class="fw-bold">Update Progress</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="formProgress">
                    <input type="hidden" name="job_id" id="p-job-id">
                    <div class="mb-3"><label class="small text-muted fw-bold mb-1">Status Baru</label><select name="status" id="p-status" class="form-select bg-light border-0"><option value="todo">Belum Mulai</option><option value="in_progress">Dalam Proses</option><option value="done">Selesai</option></select></div>
                    <div class="mb-3"><label class="small text-muted fw-bold mb-1">Catatan</label><textarea name="notes" id="p-notes" class="form-control bg-light border-0" rows="3"></textarea></div>
                    <div class="mb-3">
                        <div class="drop-zone" id="progressDropZone" style="padding: 18px 15px;">
                            <input type="file" id="progressFileInput" name="files[]" multiple hidden>
                            <div class="drop-icon" style="font-size: 1.5rem; margin-bottom: 4px;"><i class="bi bi-cloud-arrow-up"></i></div>
                            <div class="drop-text" style="font-size: 0.8rem;">Drop file atau <strong onclick="document.getElementById('progressFileInput').click()">pilih</strong></div>
                        </div>
                        <div id="progress-preview-container" class="preview-grid mt-2"></div>
                    </div>
                </form>
                <button class="btn btn-primary w-100 rounded-pill" onclick="saveProgress()">Simpan Update</button>
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
        if (file.type.startsWith('image/')) pc = `<img src="${URL.createObjectURL(file)}">`;
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
            
            // Premium status badges
            let sc = {
                todo: {bg:'#f1f5f9', color:'#475569', icon:'circle', label:'Belum Mulai'},
                in_progress: {bg:'#fefce8', color:'#a16207', icon:'play-circle-fill', label:'Dalam Proses'},
                done: {bg:'#f0fdf4', color:'#15803d', icon:'check-circle-fill', label:'Selesai'}
            };
            let s = sc[j.status] || sc.todo;
            $('#d-status-badge').html(`<span class="px-3 py-2 rounded-pill fw-bold" style="background:${s.bg}; color:${s.color}; font-size:0.75rem; letter-spacing:0.3px;"><i class="bi bi-${s.icon} me-1"></i>${s.label}</span>`);
            
            // Timeline with premium cards
            let th=''; 
            if(res.history.length){ 
                res.history.forEach((h,i)=>{ 
                    let sColor = h.status_after === 'done' ? '#15803d' : (h.status_after === 'in_progress' ? '#a16207' : '#475569');
                    th+=`<div class="d-flex gap-3 mb-3 ${i > 0 ? 'pt-3' : ''}" ${i > 0 ? 'style="border-top: 1px solid rgba(0,0,0,0.04);"' : ''}>
                        <div style="width:8px; height:8px; border-radius:50%; background:${sColor}; margin-top:6px; flex-shrink:0; box-shadow: 0 0 0 3px ${sColor}22;"></div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-bold" style="font-size:0.85rem; color:#111827;">${h.name}</span>
                                <small style="font-size:0.7rem; color:#9ca3af;">${h.date}</small>
                            </div>
                            <span class="px-2 py-1 rounded-pill d-inline-block mt-1" style="font-size:0.68rem; font-weight:600; background:${sColor}10; color:${sColor}; text-transform:uppercase; letter-spacing:0.5px;">${h.status_after}</span>
                            ${h.notes ? `<p class="mt-2 mb-0" style="font-size:0.85rem; color:#4b5563; line-height:1.55;">${h.notes}</p>` : ''}
                        </div>
                    </div>`; 
                }); 
            } else { 
                th='<div class="text-center py-3"><i class="bi bi-clock-history" style="font-size:1.5rem; color:#d1d5db;"></i><p class="mt-2 mb-0" style="font-size:0.82rem; color:#9ca3af;">Belum ada progress</p></div>'; 
            }
            $('#d-timeline').html(th);
            
            // Premium attachment gallery
            let ah=''; 
            res.attachments.forEach(a=>{ 
                let p='assets/uploads/bukti/'+a.file_path; 
                if(a.file_type=='image') {
                    ah+=`<div class="col-4"><div style="position:relative; border-radius:12px; overflow:hidden; cursor:pointer; aspect-ratio:1; background:#f3f4f6;" onclick="showMedia('${a.file_path}','image')"><img src="${p}" class="w-100 h-100" style="object-fit:cover; transition:transform 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'"><div style="position:absolute;inset:0;background:linear-gradient(transparent 60%,rgba(0,0,0,0.3));opacity:0;transition:opacity 0.3s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'"><i class="bi bi-zoom-in position-absolute bottom-0 end-0 m-2 text-white"></i></div></div></div>`; 
                } else if(a.file_type=='video') {
                    ah+=`<div class="col-12"><video src="${p}" controls class="w-100" style="border-radius:12px; max-height:300px; background:#000;"></video></div>`; 
                } else {
                    ah+=`<div class="col-12"><a href="${p}" target="_blank" class="d-flex align-items-center gap-3 p-3 text-decoration-none" style="background:white; border:1px solid #e5e7eb; border-radius:12px; transition:all 0.2s;" onmouseover="this.style.borderColor='#eab308'" onmouseout="this.style.borderColor='#e5e7eb'"><div style="width:40px;height:40px;border-radius:10px;background:#fefce8;display:flex;align-items:center;justify-content:center;"><i class="bi bi-file-earmark-text" style="color:#eab308; font-size:1.1rem;"></i></div><div><div style="font-size:0.85rem; font-weight:600; color:#111827;">${a.file_name}</div><div style="font-size:0.7rem; color:#9ca3af;">Klik untuk download</div></div></a></div>`; 
                }
            });
            $('#d-att').html(ah);

            renderComments(res.comments);
            $('#d-like-count').text(j.like_count);
            let btn=$('#d-like-btn'); 
            if(j.is_liked) {
                btn.css({'background':'linear-gradient(135deg, #eab308, #facc15)', 'color':'#1a1a1a', 'border-color':'transparent'});
            } else {
                btn.css({'background':'#f9fafb', 'color':'#4b5563', 'border-color':'#e5e7eb'});
            }
            
            $('#btn-update-progress').toggle(res.is_owner);
            $('#p-job-id').val(id);
            
            // Render viewers section
            let vh = '';
            if(res.viewers && res.viewers.length > 0) {
                let avatars = res.viewers.slice(0, 5).map((v, i) => 
                    `<img src="${v.avatar}" class="rounded-circle" width="24" height="24" style="object-fit:cover; border:2px solid white; margin-left:${i > 0 ? '-8px' : '0'}; position:relative; z-index:${10-i};" title="${v.name} • ${v.viewed_at_fmt}">`
                ).join('');
                let extra = res.view_count > 5 ? `<span style="margin-left:-4px; width:24px; height:24px; border-radius:50%; background:#f3f4f6; display:inline-flex; align-items:center; justify-content:center; font-size:0.6rem; font-weight:700; color:#6b7280; border:2px solid white; position:relative; z-index:1;">+${res.view_count - 5}</span>` : '';
                vh = `<div class="d-flex align-items-center gap-2 mt-2">
                    <div class="d-flex align-items-center">${avatars}${extra}</div>
                    <span style="font-size:0.75rem; color:#6b7280;">${res.view_count} orang melihat</span>
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
        h = '<div class="text-center py-4"><i class="bi bi-chat-text" style="font-size:2rem; color:#e5e7eb;"></i><p class="mt-2 mb-0" style="font-size:0.82rem; color:#9ca3af;">Belum ada komentar</p></div>';
    } else {
        arr.forEach(c=>{
            let delBtn = c.is_mine ? `<div class="mt-1 d-flex gap-2"><button class="btn btn-link p-0 text-decoration-none" style="font-size:0.7rem; color:#eab308;" onclick="editComment(${c.id}, '${c.content.replace(/'/g, "\\'")}')">Edit</button><button class="btn btn-link p-0 text-decoration-none" style="font-size:0.7rem; color:#dc2626;" onclick="delComment(${c.id})">Hapus</button></div>` : '';
            h+=`<div class="d-flex gap-2 mb-3">
                <img src="${c.avatar}" class="rounded-circle" width="30" height="30" style="object-fit:cover; flex-shrink:0;">
                <div class="w-100">
                    <div style="background:white; border:1px solid rgba(0,0,0,0.04); border-radius:12px; padding:10px 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold" style="font-size:0.82rem; color:#111827;">${c.name}</span>
                            <small style="font-size:0.62rem; color:#9ca3af;">${c.date}</small>
                        </div>
                        <div style="font-size:0.85rem; color:#374151; margin-top:4px; line-height:1.5;">${formatText(c.content)}</div>
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
            new bootstrap.Modal('#createModal').show();
        }
    },'json');
}

function deletePost(id){ if(confirm('Yakin hapus?')) $.post('ajax_action.php', {action:'delete_post', job_id:id}, function(){ location.reload(); }); }

function toggleLike(id, btn){
    $.post('ajax_action.php', {action:'like', job_id:id}, function(res){
        if(res.status=='success') {
            $(btn).find('.count').text(res.count);
            res.liked ? $(btn).removeClass('btn-light text-muted').addClass('btn-primary text-white') : $(btn).removeClass('btn-primary text-white').addClass('btn-light text-muted');
        }
    },'json');
}
function toggleLikeInModal(){ toggleLike(curJob, $('#d-like-btn')); }

function showMedia(p,t){ 
    let fp='assets/uploads/bukti/'+p; 
    let c = t=='image' ? `<img src="${fp}" style="max-height:90vh; max-width:100%">` : `<video src="${fp}" controls autoplay style="max-height:90vh; max-width:100%"></video>`;
    $('#media-container').html(c); new bootstrap.Modal('#mediaModal').show();
}

function showProgressForm(){ 
    $('#p-job-id').val(curJob); progressFiles = []; 
    updatePreviews('progress-preview-container', progressFiles, 'progressFiles');
    new bootstrap.Modal('#progressModal').show(); 
}

function formatText(t){ 
    if(!t) return '';
    t = t.replace(/\*([^*]+)\*/g, '<strong>$1</strong>'); // *bold*
    t = t.replace(/_([^_]+)_/g, '<em>$1</em>'); // _italic_
    t = t.replace(/@(\w+)/g, '<span class="text-primary fw-bold">@$1</span>');
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
                        h+=`<div class="mention-item" onclick="insertTag('${u.nickname}', ${lastAt}, ${cp}, '${sel}')">
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
    div.querySelectorAll('p, div, ul, ol').forEach(el => {
        el.insertAdjacentText('afterend', '\n');
    });
    
    let text = div.textContent || '';
    // Clean up
    text = text.replace(/\n{3,}/g, '\n\n');
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
    setupMentions('#inpDesc'); setupMentions('#d-input');
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
});
</script>