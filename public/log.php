<?php
session_name('CENTER_SESSION');
session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'domain'   => '',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require_once '../config/database.php';
require_once '../src/auth.php';
require_once '../src/functions.php';

auto_login($conn);
check_login();

// Hanya Admin yang boleh akses
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

date_default_timezone_set('Asia/Jakarta');

// ── Auto-create tabel jika belum ada ───────────────────────────
$conn->exec("CREATE TABLE IF NOT EXISTS login_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NULL,
    user_name   VARCHAR(255) NULL,
    user_email  VARCHAR(255) NOT NULL,
    ip_address  VARCHAR(45)  NOT NULL,
    user_agent  TEXT NULL,
    app         VARCHAR(50)  DEFAULT 'Center',
    status      ENUM('success','failed') NOT NULL,
    login_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_at (login_at),
    INDEX idx_status   (status),
    INDEX idx_user_id  (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Filter params ──────────────────────────────────────────────
$filter_status = $_GET['status'] ?? '';
$filter_search = trim($_GET['q'] ?? '');
$filter_date   = $_GET['date'] ?? '';
$page          = max(1, intval($_GET['p'] ?? 1));
$per_page      = 50;
$offset        = ($page - 1) * $per_page;

// ── Build WHERE ────────────────────────────────────────────────
$where  = [];
$params = [];

if ($filter_status === 'success' || $filter_status === 'failed') {
    $where[]  = 'status = ?';
    $params[] = $filter_status;
}
if ($filter_search !== '') {
    $like     = '%' . $filter_search . '%';
    $where[]  = '(user_name LIKE ? OR user_email LIKE ? OR ip_address LIKE ?)';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($filter_date !== '') {
    $where[]  = 'DATE(login_at) = ?';
    $params[] = $filter_date;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Counts ─────────────────────────────────────────────────────
$total = $conn->prepare("SELECT COUNT(*) FROM login_logs $where_sql");
$total->execute($params);
$total_rows  = (int)$total->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));

// Stats today
$today_all  = $conn->query("SELECT COUNT(*) FROM login_logs WHERE DATE(login_at) = CURDATE()")->fetchColumn();
$today_ok   = $conn->query("SELECT COUNT(*) FROM login_logs WHERE DATE(login_at) = CURDATE() AND status='success'")->fetchColumn();
$today_fail = $conn->query("SELECT COUNT(*) FROM login_logs WHERE DATE(login_at) = CURDATE() AND status='failed'")->fetchColumn();
$unique_ips = $conn->query("SELECT COUNT(DISTINCT ip_address) FROM login_logs WHERE DATE(login_at) = CURDATE()")->fetchColumn();

// ── Fetch rows ─────────────────────────────────────────────────
$params_paged   = array_merge($params, [$per_page, $offset]);
$stmt = $conn->prepare("SELECT * FROM login_logs $where_sql ORDER BY login_at DESC LIMIT ? OFFSET ?");
$stmt->execute($params_paged);
$logs = $stmt->fetchAll();

// ── Utility: parse user-agent ───────────────────────────────────
function parse_ua($ua) {
    $browser = 'Unknown';
    $os      = 'Unknown';

    if (preg_match('/MSIE|Trident/i', $ua))        $browser = 'Internet Explorer';
    elseif (preg_match('/Edg\//i', $ua))            $browser = 'Edge';
    elseif (preg_match('/OPR|Opera/i', $ua))        $browser = 'Opera';
    elseif (preg_match('/Firefox/i', $ua))          $browser = 'Firefox';
    elseif (preg_match('/Chrome/i', $ua))           $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $ua))           $browser = 'Safari';

    if (preg_match('/Windows NT/i', $ua))           $os = 'Windows';
    elseif (preg_match('/Mac OS X/i', $ua))         $os = 'macOS';
    elseif (preg_match('/Android/i', $ua))          $os = 'Android';
    elseif (preg_match('/iPhone|iPad/i', $ua))      $os = 'iOS';
    elseif (preg_match('/Linux/i', $ua))            $os = 'Linux';

    return "$browser · $os";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Log - Center Loewix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=4.0">
    <link rel="icon" type="image/png" href="assets/uploads/logo-square.png">
    <style>
        body { background: #f4f6fb; font-family: 'Plus Jakarta Sans', sans-serif; }
        .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            border: 1px solid #f0f0f0;
        }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
        }
        .stat-val { font-size: 1.75rem; font-weight: 700; line-height: 1; }
        .stat-label { font-size: 0.72rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin-top: 4px; }
        .log-table th { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #94a3b8; border-bottom: 1px solid #f0f0f0 !important; }
        .log-table td { font-size: 0.85rem; vertical-align: middle; border-bottom: 1px solid #f8f9fb !important; }
        .log-table tr:hover td { background: #f8faff; }
        .badge-success { background: #dcfce7; color: #16a34a; font-weight: 600; font-size: 0.72rem; padding: 4px 10px; border-radius: 20px; }
        .badge-failed  { background: #fee2e2; color: #dc2626; font-weight: 600; font-size: 0.72rem; padding: 4px 10px; border-radius: 20px; }
        .ip-badge { background: #f1f5f9; color: #334155; font-family: monospace; font-size: 0.8rem; padding: 3px 8px; border-radius: 6px; }
        .filter-bar { background: #fff; border-radius: 14px; padding: 14px 18px; box-shadow: 0 1px 6px rgba(0,0,0,.05); border: 1px solid #f0f0f0; }
        .user-avatar-sm { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; }
        .user-initials-sm {
            width: 34px; height: 34px; border-radius: 50%;
            background: linear-gradient(135deg, #eab308, #f59e0b);
            color: #fff; font-weight: 700; font-size: 0.7rem;
            display: inline-flex; align-items: center; justify-content: center;
            text-transform: uppercase; flex-shrink: 0;
        }
        .page-card { background: #fff; border-radius: 18px; box-shadow: 0 2px 16px rgba(0,0,0,.06); border: 1px solid #f0f0f0; overflow: hidden; }
    </style>
</head>
<body>

<div class="container-fluid px-3 px-md-4 pb-5">

    <!-- ── Navbar ── -->
    <div class="d-flex justify-content-between align-items-center navbar-wander mb-4">
        <a href="index.php" class="brand-wander text-white text-decoration-none">
            <i class="bi bi-arrow-left-circle-fill"></i> Back to Dashboard
        </a>
        <div class="user-pill">
            <span class="small fw-bold px-2"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
        </div>
    </div>

    <!-- ── Header ── -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:50px;height:50px;border-radius:14px;background:linear-gradient(135deg,#1e293b,#334155);display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-shield-fill-check text-white fs-4"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0">Login Log</h4>
            <p class="text-secondary small mb-0">Monitor semua aktivitas login ke Grav Center</p>
        </div>
    </div>

    <!-- ── Stats Today ── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:#eff6ff;"><i class="bi bi-box-arrow-in-right" style="color:#3b82f6;"></i></div>
                    <div>
                        <div class="stat-val"><?= $today_all ?></div>
                        <div class="stat-label">Login Hari Ini</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:#f0fdf4;"><i class="bi bi-check-circle-fill" style="color:#22c55e;"></i></div>
                    <div>
                        <div class="stat-val"><?= $today_ok ?></div>
                        <div class="stat-label">Berhasil</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:#fef2f2;"><i class="bi bi-x-circle-fill" style="color:#ef4444;"></i></div>
                    <div>
                        <div class="stat-val"><?= $today_fail ?></div>
                        <div class="stat-label">Gagal</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:#fafaf9;"><i class="bi bi-pc-display" style="color:#78716c;"></i></div>
                    <div>
                        <div class="stat-val"><?= $unique_ips ?></div>
                        <div class="stat-label">IP Unik Hari Ini</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Filter Bar ── -->
    <div class="filter-bar mb-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nama, email, atau IP..." value="<?= htmlspecialchars($filter_search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="success" <?= $filter_status==='success'?'selected':'' ?>>✅ Berhasil</option>
                    <option value="failed"  <?= $filter_status==='failed' ?'selected':'' ?>>❌ Gagal</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-dark px-3 fw-semibold">Filter</button>
                <a href="log.php" class="btn btn-sm btn-outline-secondary px-3 ms-1">Reset</a>
            </div>
            <div class="col-auto ms-auto">
                <span class="text-muted small"><?= number_format($total_rows) ?> record</span>
            </div>
        </form>
    </div>

    <!-- ── Table ── -->
    <div class="page-card">
        <div class="table-responsive">
            <table class="table log-table mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width:50px;">#</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>IP Address</th>
                        <th>Browser / OS</th>
                        <th>App</th>
                        <th>Status</th>
                        <th class="pe-4">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Belum ada data log.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $i => $log): ?>
                    <tr>
                        <td class="ps-4 text-muted" style="font-size:0.75rem;"><?= $offset + $i + 1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($log['user_name']): ?>
                                    <div class="user-initials-sm"><?= mb_substr(preg_replace('/\s+/', '', $log['user_name']), 0, 2) ?></div>
                                    <span class="fw-semibold"><?= htmlspecialchars($log['user_name']) ?></span>
                                <?php else: ?>
                                    <div class="user-initials-sm" style="background:linear-gradient(135deg,#dc2626,#ef4444);">?</div>
                                    <span class="text-muted fst-italic">Unknown</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            title="<?= htmlspecialchars($log['user_email']) ?>">
                            <?= htmlspecialchars($log['user_email']) ?>
                        </td>
                        <td>
                            <span class="ip-badge"><?= htmlspecialchars($log['ip_address']) ?></span>
                        </td>
                        <td class="text-muted" style="font-size:0.78rem;">
                            <?= htmlspecialchars(parse_ua($log['user_agent'] ?? '')) ?>
                        </td>
                        <td>
                            <span style="font-size:0.78rem;background:#f1f5f9;color:#475569;padding:3px 9px;border-radius:6px;font-weight:600;">
                                <?= htmlspecialchars($log['app']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['status'] === 'success'): ?>
                                <span class="badge-success"><i class="bi bi-check-lg me-1"></i>Berhasil</span>
                            <?php else: ?>
                                <span class="badge-failed"><i class="bi bi-x-lg me-1"></i>Gagal</span>
                            <?php endif; ?>
                        </td>
                        <td class="pe-4 text-muted" style="font-size:0.78rem;white-space:nowrap;">
                            <?php
                                $dt = new DateTime($log['login_at']);
                                $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
                                echo $dt->format('d M Y, H:i:s') . ' WIB';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Pagination ── -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-center py-3">
            <nav>
                <ul class="pagination pagination-sm mb-0 gap-1">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link rounded-3" href="?p=<?= $page-1 ?>&status=<?= urlencode($filter_status) ?>&q=<?= urlencode($filter_search) ?>&date=<?= urlencode($filter_date) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php for ($pg = max(1,$page-2); $pg <= min($total_pages,$page+2); $pg++): ?>
                    <li class="page-item <?= $pg==$page?'active':'' ?>">
                        <a class="page-link rounded-3" href="?p=<?= $pg ?>&status=<?= urlencode($filter_status) ?>&q=<?= urlencode($filter_search) ?>&date=<?= urlencode($filter_date) ?>"><?= $pg ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link rounded-3" href="?p=<?= $page+1 ?>&status=<?= urlencode($filter_status) ?>&q=<?= urlencode($filter_search) ?>&date=<?= urlencode($filter_date) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
