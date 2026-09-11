<?php 
require_once 'includes/db.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

$user_id = $_SESSION['user_id'];

// Auto mark unread notifications as read when visiting notifikasi.php
try {
    $conn->prepare("UPDATE bukti_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$user_id]);
} catch (Exception $e) {}

$stmt_user = $conn->prepare("SELECT nickname, name FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$curr_user = $stmt_user->fetch();
$nickname = $curr_user['nickname'] ? $curr_user['nickname'] : str_replace(' ', '', $curr_user['name']);

$limit = 20; 
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total notifications from bukti_notifications
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM bukti_notifications WHERE user_id = ? AND deleted_at IS NULL");
$stmt_count->execute([$user_id]);
$total_rows = (int)$stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch notifications with actor & job info
$sql = "
    SELECT 
        n.id as notif_id,
        n.job_id,
        n.actor_id,
        n.type,
        n.is_read,
        n.created_at,
        u.name as actor_name,
        u.avatar as actor_avatar,
        j.title as job_title,
        j.status as job_status
    FROM bukti_notifications n
    LEFT JOIN users u ON n.actor_id = u.id
    LEFT JOIN bukti_jobs j ON n.job_id = j.id
    WHERE n.user_id = ? AND n.deleted_at IS NULL
    ORDER BY n.created_at DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

function time_ago_custom($datetime) { return tgl_indo($datetime); }
?>

<div class="main-wrapper">
    <div class="content-area" style="max-width: 860px;">
        
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold m-0" style="color: #0f172a; font-size: 1.35rem; display: flex; align-items: center; gap: 10px;">
                    <span style="width: 38px; height: 38px; border-radius: 12px; background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(245,158,11,0.2);">
                        <i class="bi bi-bell-fill"></i>
                    </span>
                    Notifikasi & Aktivitas
                </h4>
                <p class="text-muted small mt-1 mb-0">Pemberitahuan persetujuan pekerjaan, arahan meeting, dan tanda sebutan (@<?php echo htmlspecialchars($nickname); ?>).</p>
            </div>
            <a href="index.php" class="btn btn-sm btn-light rounded-pill px-3 py-2 fw-bold text-muted border shadow-sm" style="font-size: 0.82rem;">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
            </a>
        </div>

        <div class="card-custom p-0 overflow-hidden shadow-sm" style="border: 1px solid rgba(226,232,240,0.8); border-radius: 20px; background: #ffffff;">
            <?php if(count($notifications) > 0): ?>
                <?php foreach($notifications as $n): 
                    $actor_name = $n['actor_name'] ?: 'Rekan Tim';
                    $av = $n['actor_avatar'] && file_exists("assets/img/avatars/".$n['actor_avatar']) 
                        ? "assets/img/avatars/".$n['actor_avatar'] 
                        : "https://ui-avatars.com/api/?name=".urlencode($actor_name)."&background=f1f5f9&color=64748b";
                    
                    $job_title = $n['job_title'] ?: 'Pekerjaan #'.$n['job_id'];
                    $type = $n['type'];

                    // Configure visual badges & texts based on notification type
                    if ($type === 'approval_request') {
                        $badge_class = 'badge-3d-pending';
                        $badge_label = 'Menunggu Approval';
                        $icon_badge = '<span style="width:28px; height:28px; border-radius:8px; background:#e0f2fe; color:#0284c7; display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;"><i class="bi bi-shield-lock-fill"></i></span>';
                        $action_text = 'mengajukan permintaan <strong>Approval</strong> untuk:';
                        $border_color = '#0284c7';
                    } elseif ($type === 'approval_approved') {
                        $badge_class = 'badge-3d-done';
                        $badge_label = 'Disetujui (Lanjut)';
                        $icon_badge = '<span style="width:28px; height:28px; border-radius:8px; background:#ecfdf5; color:#10b981; display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;"><i class="bi bi-check-circle-fill"></i></span>';
                        $action_text = 'telah <strong>menyetujui</strong> pekerjaan Anda (Lanjut Kerjakan):';
                        $border_color = '#10b981';
                    } elseif ($type === 'approval_rejected') {
                        $badge_class = 'badge-3d-meeting';
                        $badge_label = 'Meeting Ulang';
                        $icon_badge = '<span style="width:28px; height:28px; border-radius:8px; background:#fff1f2; color:#ef4444; display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;"><i class="bi bi-arrow-repeat"></i></span>';
                        $action_text = 'meminta <strong>Meeting Ulang</strong> untuk pekerjaan:';
                        $border_color = '#ef4444';
                    } elseif ($type === 'comment') {
                        $badge_class = '';
                        $badge_label = 'Komentar';
                        $icon_badge = '<span style="width:28px; height:28px; border-radius:8px; background:#f0fdf4; color:#16a34a; display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;"><i class="bi bi-chat-dots-fill"></i></span>';
                        $action_text = 'memberikan tanggapan / komentar pada:';
                        $border_color = '#cbd5e1';
                    } else {
                        // mention or default
                        $badge_class = '';
                        $badge_label = 'Mention';
                        $icon_badge = '<span style="width:28px; height:28px; border-radius:8px; background:#fef3c7; color:#d97706; display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;"><i class="bi bi-at"></i></span>';
                        $action_text = 'menandai Anda (@'.$nickname.') dalam:';
                        $border_color = '#cbd5e1';
                    }
                ?>
                <a href="index.php?job_id=<?php echo $n['job_id']; ?>" class="text-decoration-none d-block">
                    <div class="p-3 p-md-4 border-bottom d-flex gap-3 align-items-center bg-white notif-row" style="transition: all 0.2s ease;">
                        <div class="position-relative flex-shrink-0">
                            <img src="<?php echo $av; ?>" class="rounded-circle shadow-sm" width="46" height="46" style="object-fit:cover; border: 2px solid #ffffff;">
                            <span class="position-absolute bottom-0 end-0" style="transform: translate(25%, 25%);">
                                <?php echo $icon_badge; ?>
                            </span>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="fw-bold" style="color: #0f172a; font-size: 0.92rem;"><?php echo htmlspecialchars($actor_name); ?></span> 
                                <span class="text-secondary small" style="font-size: 0.83rem;"><?php echo $action_text; ?></span>
                            </div>
                            <div class="text-truncate fw-semibold" style="color: #1e293b; font-size: 0.9rem; max-width: 550px;">
                                "<?php echo htmlspecialchars($job_title); ?>"
                            </div>
                            <div class="d-flex align-items-center gap-3 mt-2">
                                <small class="text-muted" style="font-size: 0.74rem;">
                                    <i class="bi bi-clock me-1"></i> <?php echo time_ago_custom($n['created_at']); ?>
                                </small>
                                <?php if (!empty($badge_label) && !empty($badge_class)): ?>
                                    <span class="badge-3d-status <?php echo $badge_class; ?>" style="font-size: 0.65rem; padding: 2px 8px;">
                                        <span class="pulse-dot" style="width: 5px; height: 5px;"></span> <?php echo $badge_label; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex-shrink-0 text-muted ms-2">
                            <i class="bi bi-chevron-right fs-5"></i>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-5 text-center text-muted">
                    <div style="width: 64px; height: 64px; border-radius: 20px; background: #f8fafc; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 12px; border: 1px dashed #cbd5e1;">
                        <i class="bi bi-bell-slash"></i>
                    </div>
                    <h6 class="fw-bold text-dark m-0">Belum Ada Notifikasi</h6>
                    <p class="small text-muted mt-1 mb-0">Semua aktivitas atau persetujuan pekerjaan akan muncul di sini.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                    <li class="page-item <?php echo $page==$i?'active':''; ?>">
                        <a class="page-link border-0 rounded-circle mx-1 shadow-sm" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>

    </div>
</div>

<style>
.notif-row:hover {
    background: #f8fafc !important;
    transform: translateX(4px);
}
.badge-3d-pending {
    background: linear-gradient(135deg, #e0f2fe, #bae6fd);
    color: #0369a1;
    border: 1px solid #7dd3fc;
}
.badge-3d-meeting {
    background: linear-gradient(135deg, #fee2e2, #fecdd3);
    color: #b91c1c;
    border: 1px solid #fca5a5;
}
.badge-3d-done {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    color: #15803d;
    border: 1px solid #86efac;
}
.pulse-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: currentColor;
    margin-right: 4px;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { transform: scale(0.95); opacity: 0.8; }
    50% { transform: scale(1.3); opacity: 1; }
    100% { transform: scale(0.95); opacity: 0.8; }
}
</style>

<?php require_once 'includes/footer.php'; ?>