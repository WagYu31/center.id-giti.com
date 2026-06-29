<?php 
require_once 'includes/db.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

// --- KONFIGURASI PAGINATION & FILTER ---
$limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_q = $_GET['q'] ?? '';
$date_start = $_GET['start'] ?? '';
$date_end = $_GET['end'] ?? '';

// --- BANGUN QUERY ---
$conditions = ["1=1"];
$params = [];

if (!empty($search_q)) {
    $conditions[] = "(u.name LIKE ? OR l.action LIKE ? OR l.description LIKE ? OR l.ip_address LIKE ?)";
    $search_term = "%$search_q%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_start)) {
    $conditions[] = "DATE(l.created_at) >= ?";
    $params[] = $date_start;
}
if (!empty($date_end)) {
    $conditions[] = "DATE(l.created_at) <= ?";
    $params[] = $date_end;
}

$where_sql = implode(" AND ", $conditions);

$sql_count = "SELECT COUNT(*) FROM bukti_logs l JOIN users u ON l.user_id = u.id WHERE $where_sql";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql_data = "SELECT l.*, u.name 
             FROM bukti_logs l 
             JOIN users u ON l.user_id = u.id 
             WHERE $where_sql 
             ORDER BY l.created_at DESC 
             LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_data);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function build_url($page_num) {
    $params = $_GET;
    $params['page'] = $page_num;
    return '?' . http_build_query($params);
}
?>

<div class="main-wrapper">
    <div class="content-area">

        <style>
            /* Premium Table Styles - ISO 9241-151 */
            .log-header { margin-bottom: 24px; }
            .log-header h3 { font-size: 1.4rem; font-weight: 700; color: var(--text-dark); margin: 0 0 4px; }
            .log-header p { color: var(--text-muted); font-size: 0.88rem; margin: 0; }
            .log-header .log-count { background: #fffbeb; color: #92400e; font-size: 0.75rem; font-weight: 600; padding: 4px 12px; border-radius: 20px; border: 1px solid rgba(217,119,6,0.15); }

            .filter-card { background: white; border-radius: var(--radius); border: 1px solid var(--border-color); padding: 20px 24px; margin-bottom: 20px; box-shadow: var(--shadow-sm); }
            .filter-card label { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
            .filter-card .form-control, .filter-card .input-group-text { border-color: var(--border-color); font-size: 0.85rem; }
            .filter-card .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(217,119,6,0.08); }
            .filter-card .btn-filter { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border: none; font-weight: 600; font-size: 0.85rem; border-radius: 10px; padding: 10px 24px; transition: all 0.2s; }
            .filter-card .btn-filter:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(217,119,6,0.25); }

            .table-card { background: white; border-radius: var(--radius); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden; }
            .log-table { width: 100%; border-collapse: separate; border-spacing: 0; }
            .log-table thead th { background: #f8fafc; color: var(--text-muted); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; padding: 14px 16px; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
            .log-table thead th:first-child { padding-left: 24px; }
            .log-table tbody tr { transition: background 0.15s; }
            .log-table tbody tr:hover { background: #fafbfc; }
            .log-table tbody td { padding: 14px 16px; font-size: 0.85rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
            .log-table tbody td:first-child { padding-left: 24px; }
            .log-table tbody tr:last-child td { border-bottom: none; }

            .log-time { color: var(--text-muted); font-size: 0.78rem; white-space: nowrap; }
            .log-time .date { font-weight: 600; color: var(--text-dark); }
            .log-time .hour { color: #94a3b8; font-size: 0.72rem; }

            .log-user { display: flex; align-items: center; gap: 10px; }
            .log-user .avatar-sm { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem; color: white; flex-shrink: 0; }
            .log-user .uname { font-weight: 600; font-size: 0.84rem; color: var(--text-dark); }

            .action-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.3px; }
            .action-create { background: #ecfdf5; color: #059669; }
            .action-edit { background: #eff6ff; color: #2563eb; }
            .action-delete { background: #fef2f2; color: #dc2626; }
            .action-update { background: #fffbeb; color: #d97706; }
            .action-login { background: #f0f9ff; color: #0284c7; }
            .action-default { background: #f1f5f9; color: #64748b; }

            .log-desc { color: #475569; font-size: 0.82rem; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .log-ip { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.75rem; color: #94a3b8; background: #f8fafc; padding: 3px 8px; border-radius: 5px; }

            .log-empty { text-align: center; padding: 48px 24px !important; }
            .log-empty i { font-size: 2.5rem; color: #e2e8f0; margin-bottom: 8px; }
            .log-empty p { color: #94a3b8; font-size: 0.88rem; margin: 0; }

            .premium-pagination .page-link { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 0.82rem; font-weight: 600; color: var(--text-muted); border: 1px solid var(--border-color); background: white; transition: all 0.2s; }
            .premium-pagination .page-link:hover { background: #f8fafc; color: var(--text-dark); }
            .premium-pagination .page-item.active .page-link { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border-color: transparent; box-shadow: 0 3px 8px rgba(217,119,6,0.2); }
            .premium-pagination .page-item.disabled .page-link { opacity: 0.4; }
        </style>

        <div class="log-header d-flex justify-content-between align-items-center">
            <div>
                <h3><i class="bi bi-clock-history me-2" style="color: var(--primary);"></i>Riwayat Aktivitas</h3>
                <p>Memantau seluruh log aktivitas pengguna sistem.</p>
            </div>
            <span class="log-count"><?php echo $total_rows; ?> Log tercatat</span>
        </div>

        <div class="filter-card">
            <form method="GET" action="">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label>Pencarian</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search" style="color: #94a3b8;"></i></span>
                            <input type="text" name="q" class="form-control" placeholder="Cari nama, aksi, atau IP..." value="<?php echo htmlspecialchars($search_q); ?>">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label>Rentang Waktu</label>
                        <div class="input-group">
                            <input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($date_start); ?>">
                            <span class="input-group-text bg-white border-start-0 border-end-0" style="color: #94a3b8; font-size: 0.8rem;">s/d</span>
                            <input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($date_end); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-filter w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="table-responsive">
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width:36px">#</th>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Aksi</th>
                        <th>Deskripsi</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach($logs as $idx => $l): 
                            $act = $l['action'];
                            $act_class = 'action-default'; $act_icon = 'bi-gear';
                            if (strpos($act, 'CREATE') !== false) { $act_class = 'action-create'; $act_icon = 'bi-plus-circle'; }
                            elseif (strpos($act, 'DELETE') !== false) { $act_class = 'action-delete'; $act_icon = 'bi-trash3'; }
                            elseif (strpos($act, 'EDIT') !== false) { $act_class = 'action-edit'; $act_icon = 'bi-pencil'; }
                            elseif (strpos($act, 'UPDATE') !== false) { $act_class = 'action-update'; $act_icon = 'bi-arrow-repeat'; }
                            elseif (strpos($act, 'LOGIN') !== false) { $act_class = 'action-login'; $act_icon = 'bi-box-arrow-in-right'; }
                            
                            $colors = ['#d97706','#059669','#2563eb','#7c3aed','#dc2626','#0891b2','#c026d3'];
                            $av_color = $colors[crc32($l['name']) % count($colors)];
                            $initials = strtoupper(substr($l['name'],0,1) . (strpos($l['name'],' ')!==false ? substr($l['name'], strpos($l['name'],' ')+1, 1) : ''));
                            $dt = strtotime($l['created_at']);
                        ?>
                        <tr>
                            <td style="color: #cbd5e1; font-size: 0.75rem; font-weight: 600;"><?php echo $offset + $idx + 1; ?></td>
                            <td class="log-time">
                                <div class="date"><?php echo date('d M Y', $dt); ?></div>
                                <div class="hour"><?php echo date('H:i', $dt); ?></div>
                            </td>
                            <td>
                                <div class="log-user">
                                    <div class="avatar-sm" style="background: <?php echo $av_color; ?>;"><?php echo $initials; ?></div>
                                    <span class="uname"><?php echo htmlspecialchars($l['name']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="action-badge <?php echo $act_class; ?>">
                                    <i class="bi <?php echo $act_icon; ?>"></i>
                                    <?php echo htmlspecialchars($act); ?>
                                </span>
                            </td>
                            <td class="log-desc" title="<?php echo htmlspecialchars($l['description']); ?>">
                                <?php echo htmlspecialchars($l['description']); ?>
                            </td>
                            <td><span class="log-ip"><?php echo htmlspecialchars($l['ip_address']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="log-empty">
                                <i class="bi bi-inbox d-block"></i>
                                <p>Tidak ada data aktivitas yang ditemukan.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <?php if($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination premium-pagination justify-content-center gap-1 mb-0">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo build_url($page - 1); ?>"><i class="bi bi-chevron-left"></i></a>
                </li>
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) { echo '<li class="page-item"><a class="page-link" href="'.build_url(1).'">1</a></li>'; if($start_page > 2) echo '<li class="page-item disabled"><span class="page-link border-0">…</span></li>'; }
                for($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo build_url($i); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages) { if($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link border-0">…</span></li>'; echo '<li class="page-item"><a class="page-link" href="'.build_url($total_pages).'">'.$total_pages.'</a></li>'; } ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo build_url($page + 1); ?>"><i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>