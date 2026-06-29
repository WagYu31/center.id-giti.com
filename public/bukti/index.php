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

$sql = "SELECT j.*, u.name as user_name, u.avatar as user_avatar, u.nickname, u.jabatan, 
        (SELECT COUNT(*) FROM bukti_comments WHERE job_id = j.id AND deleted_at IS NULL) as c_count, 
        (SELECT COUNT(*) FROM bukti_reactions WHERE job_id = j.id AND type='like') as l_count, 
        (SELECT COUNT(*) FROM bukti_reactions WHERE job_id = j.id AND user_id=$current_user_id AND type='like') as is_liked 
        FROM bukti_jobs j JOIN users u ON j.user_id = u.id WHERE $where ORDER BY j.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql); $stmt->execute($param); $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$users_list = $conn->query("SELECT id, name FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$stmt_me = $conn->prepare("SELECT avatar, name FROM users WHERE id=?"); $stmt_me->execute([$current_user_id]); $me = $stmt_me->fetch();
$myName = $me['name'] ?? 'User'; 
$my_av = ($me['avatar'] && file_exists("assets/img/avatars/" . $me['avatar'])) ? "assets/img/avatars/" . $me['avatar'] : "https://ui-avatars.com/api/?name=" . urlencode($myName);
$sapa = date('H')<11?"Selamat Pagi": (date('H')<15?"Selamat Siang": (date('H')<18?"Selamat Sore":"Selamat Malam"));

function time_ago($datetime) { return tgl_indo($datetime); }
function format_text($text) { return nl2br(preg_replace('/@(\w+)/', '<span class="text-primary fw-bold">@$1</span>', htmlspecialchars($text))); }
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
                    <div class="card-custom p-0" id="post-<?php echo $job['id']; ?>">
                        <div class="p-3 d-flex justify-content-between align-items-start">
                            <div class="d-flex gap-3">
                                <img src="<?php echo $avatarSrc; ?>" class="rounded-circle" width="45" height="45" style="object-fit:cover;">
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($job['nickname'] ?: $uName); ?></div>
                                    <div class="small text-muted"><?php echo time_ago($job['created_at']); ?><?php if($job['is_edited']): ?><span class="fst-italic ms-1" style="font-size:0.7rem">• Diedit</span><?php endif; ?></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-<?php echo ($job['status']=='done'?'success':($job['status']=='in_progress'?'primary':'secondary')); ?> bg-opacity-10 text-<?php echo ($job['status']=='done'?'success':($job['status']=='in_progress'?'primary':'secondary')); ?> px-3 py-2 rounded-pill">
                                    <?php if($job['status']=='in_progress') echo '<i class="bi bi-play-circle-fill me-1"></i> ON PROGRESS'; elseif($job['status']=='done') echo '<i class="bi bi-check-circle-fill me-1"></i> SELESAI'; else echo '<i class="bi bi-circle me-1"></i> BELUM MULAI'; ?>
                                </span>
                                <?php if($job['user_id'] == $current_user_id): ?>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                        <li><a class="dropdown-item" href="#" onclick="openEditModal(<?php echo $job['id']; ?>)"><i class="bi bi-pencil me-2"></i> Edit</a></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deletePost(<?php echo $job['id']; ?>)"><i class="bi bi-trash me-2"></i> Hapus</a></li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="px-3 pb-2 cursor-pointer" onclick="openDetail(<?php echo $job['id']; ?>)">
                            <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($job['title']); ?></h5>
                            <p class="text-secondary" style="white-space: pre-wrap;"><?php echo (strlen($job['description'])>200) ? substr(format_text($job['description']),0,200).'...' : format_text($job['description']); ?></p>
                        </div>
                        <div class="px-3 py-2 border-top d-flex gap-3">
                            <button onclick="toggleLike(<?php echo $job['id']; ?>, this)" class="btn btn-sm <?php echo $job['is_liked']?'btn-primary text-white':'btn-light text-muted'; ?> fw-bold px-3 rounded-pill">
                                <i class="bi bi-hand-thumbs-up-fill me-1"></i> <span class="count"><?php echo $job['l_count']; ?></span> Suka
                            </button>
                            <button onclick="openDetail(<?php echo $job['id']; ?>)" class="btn btn-sm btn-light text-muted fw-bold px-3 rounded-pill">
                                <i class="bi bi-chat-dots me-1"></i> <?php echo $job['c_count']; ?> Komentar
                            </button>
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
        <div class="modal-content border-0 rounded-4 overflow-hidden" style="height: 85vh;">
            <div class="modal-body p-0 h-100">
                <div class="row g-0 h-100">
                    <div class="col-lg-8 h-100 bg-white border-end d-flex flex-column">
                        <div class="p-4 border-bottom flex-shrink-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex gap-3 align-items-center"><img src="" id="d-avatar" class="rounded-circle shadow-sm" width="50" height="50" style="object-fit: cover;"><div><h6 class="fw-bold mb-0 text-dark" id="d-name"></h6><small class="text-muted" id="d-date"></small></div></div>
                                <div class="d-flex align-items-center gap-3"><div id="d-status-badge"></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            </div>
                        </div>
                        <div class="p-4 overflow-auto custom-scroll flex-grow-1" style="min-height: 0;">
                            <h3 class="fw-bold mb-3 text-dark" id="d-title"></h3>
                            <div id="d-desc" class="text-secondary mb-4" style="white-space: pre-wrap; font-size: 1rem; line-height: 1.6;"></div>
                            <div id="d-att" class="row g-2 mb-4"></div>
                            <div class="card bg-light border-0 rounded-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3"><h6 class="fw-bold m-0 text-primary"><i class="bi bi-activity me-2"></i>Timeline Progress</h6><button class="btn btn-sm btn-primary rounded-pill px-3" id="btn-update-progress" style="display:none;" onclick="showProgressForm()"><i class="bi bi-plus-lg me-1"></i> Update</button></div>
                                    <div id="d-timeline" class="ps-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 h-100 bg-light d-flex flex-column">
                        <div class="p-3 border-bottom bg-white d-flex justify-content-between align-items-center flex-shrink-0" style="height: 83px;">
                            <h6 class="fw-bold m-0">Diskusi</h6>
                            <button class="btn btn-sm btn-light border rounded-pill fw-bold text-muted" id="d-like-btn" onclick="toggleLikeInModal()"><i class="bi bi-hand-thumbs-up-fill"></i>
                            <!--<span id="d-like-count">0</span>-->
                            </button>
                        </div>
                        <div id="d-comments" class="flex-grow-1 p-3 overflow-auto custom-scroll" style="min-height: 0;"></div>
                        <div class="p-3 bg-white border-top flex-shrink-0">
                            <div class="position-relative">
                                <input id="d-input" class="form-control rounded-pill pe-5 bg-light border-0" placeholder="Ketik @ untuk tag..." style="padding-right: 50px;">
                                <button class="btn btn-primary rounded-circle position-absolute top-50 end-0 translate-middle-y me-2" style="width:35px;height:35px; display: flex; align-items: center; justify-content: center;" onclick="sendComment()"><i class="bi bi-send-fill" style="font-size: 0.9rem;"></i></button>
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
                        <select name="status" id="inpStatus" class="form-select form-select-sm border-0 bg-light fw-bold text-primary w-auto"><option value="todo">Todo</option><option value="in_progress">On Progress</option><option value="done">Done</option></select>
                    </div>
                    <input type="text" name="title" id="inpTitle" class="form-control fw-bold fs-4 border-0 px-0 mb-2" placeholder="Judul Pekerjaan..." required>
                    <textarea name="description" id="inpDesc" class="form-control border-0 px-0 fs-6" rows="4" placeholder="Deskripsi... Gunakan @ untuk tag"></textarea>
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
            let b={todo:'secondary', in_progress:'primary', done:'success'};
            let l={todo:'Belum Mulai', in_progress:'Dalam Proses', done:'Selesai'};
            $('#d-status-badge').html(`<span class="badge bg-${b[j.status]} px-3 py-2 rounded-pill">${l[j.status]}</span>`);
            
            let th=''; if(res.history.length){ res.history.forEach(h=>{ th+=`<div class="timeline-box"><div class="timeline-item"><div class="fw-bold small">${h.name} <span class="badge bg-light text-dark border ms-1 uppercase">${h.status_after}</span></div><div class="timeline-date">${h.date}</div><div class="timeline-content">${h.notes}</div></div></div>`; }); } else { th='<div class="text-muted small ps-3 fst-italic">Belum ada progress.</div>'; }
            $('#d-timeline').html(th);
            
            let ah=''; res.attachments.forEach(a=>{ let p='assets/uploads/bukti/'+a.file_path; if(a.file_type=='image') ah+=`<div class="col-4"><img src="${p}" class="img-fluid rounded cursor-pointer" onclick="showMedia('${a.file_path}','image')"></div>`; else if(a.file_type=='video') ah+=`<div class="col-12"><video src="${p}" controls class="w-100 rounded"></video></div>`; else ah+=`<div class="col-12"><a href="${p}" target="_blank" class="btn btn-light w-100 text-start border"><i class="bi bi-file-earmark"></i> ${a.file_name}</a></div>`; });
            $('#d-att').html(ah);

            renderComments(res.comments);
            $('#d-like-count').text(j.like_count);
            let btn=$('#d-like-btn'); j.is_liked?btn.removeClass('btn-light text-muted').addClass('btn-primary text-white'):btn.removeClass('btn-primary text-white').addClass('btn-light text-muted');
            
            $('#btn-update-progress').toggle(res.is_owner);
            $('#p-job-id').val(id);
            new bootstrap.Modal('#detailModal').show();
        }
    },'json');
}

function renderComments(arr){
    let h=''; arr.forEach(c=>{
        let delBtn = c.is_mine ? `<div class="mt-1"><button class="btn btn-link p-0 text-muted" style="font-size:0.7rem" onclick="editComment(${c.id}, '${c.content.replace(/'/g, "\\'")}')">Edit</button> <button class="btn btn-link p-0 text-danger ms-2" style="font-size:0.7rem" onclick="delComment(${c.id})">Hapus</button></div>` : '';
        h+=`<div class="d-flex gap-2 mb-3"><img src="${c.avatar}" class="rounded-circle" width="32" height="32"><div class="w-100"><div class="bg-white border rounded-3 p-2 px-3 shadow-sm"><div class="d-flex justify-content-between"><span class="fw-bold small">${c.name}</span><small class="text-muted" style="font-size:0.6rem">${c.date}</small></div><div class="small text-dark mt-1">${formatText(c.content)}</div></div>${delBtn}</div></div>`;
    });
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

function formatText(t){ return t?t.replace(/@(\w+)/g, '<span class="text-primary fw-bold">@$1</span>').replace(/\n/g, '<br>'):''; }

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

$(document).ready(()=>{ 
    setupMentions('#inpDesc'); setupMentions('#d-input'); 
    setupDropZone('dropZone', 'fileInput', selectedFiles, 'selectedFiles', 'file-preview-container');
    setupDropZone('progressDropZone', 'progressFileInput', progressFiles, 'progressFiles', 'progress-preview-container');
    $('#createModal').on('hidden.bs.modal', function(){ $('#formJob')[0].reset(); $('#modalTitle').text('Buat Pekerjaan Baru'); $('#formAction').val('create_post'); selectedFiles = []; updatePreviews('file-preview-container', selectedFiles, 'selectedFiles'); });
});
</script>