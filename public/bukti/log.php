<?php 
require_once 'includes/db.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

// --- KONFIGURASI PAGINATION & FILTER ---
$limit = 25; // Jumlah baris per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Ambil Parameter Filter
$search_q = $_GET['q'] ?? '';
$date_start = $_GET['start'] ?? '';
$date_end = $_GET['end'] ?? '';

// --- BANGUN QUERY ---
$conditions = ["1=1"];
$params = [];

// Filter Kata Kunci (Nama, Aksi, Deskripsi, IP)
if (!empty($search_q)) {
    $conditions[] = "(u.name LIKE ? OR l.action LIKE ? OR l.description LIKE ? OR l.ip_address LIKE ?)";
    $search_term = "%$search_q%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Filter Tanggal
if (!empty($date_start)) {
    $conditions[] = "DATE(l.created_at) >= ?";
    $params[] = $date_start;
}
if (!empty($date_end)) {
    $conditions[] = "DATE(l.created_at) <= ?";
    $params[] = $date_end;
}

$where_sql = implode(" AND ", $conditions);

// --- HITUNG TOTAL DATA (Untuk Pagination) ---
$sql_count = "SELECT COUNT(*) FROM bukti_logs l JOIN users u ON l.user_id = u.id WHERE $where_sql";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// --- AMBIL DATA LOG ---
$sql_data = "SELECT l.*, u.name 
             FROM bukti_logs l 
             JOIN users u ON l.user_id = u.id 
             WHERE $where_sql 
             ORDER BY l.created_at DESC 
             LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_data);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fungsi untuk menjaga parameter URL saat pindah halaman
function build_url($page_num) {
    $params = $_GET;
    $params['page'] = $page_num;
    return '?' . http_build_query($params);
}
?>

<div class="main-wrapper">
    <div class="content-area">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Riwayat Aktivitas</h3>
                <p class="text-muted mb-0">Memantau seluruh log aktivitas pengguna sistem.</p>
            </div>
        </div>

        <div class="card-custom p-4 mb-4">
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="small text-muted fw-bold mb-1">PENCARIAN</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="q" class="form-control bg-light border-start-0" placeholder="Cari Nama, Aksi, atau IP..." value="<?php echo htmlspecialchars($search_q); ?>">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="small text-muted fw-bold mb-1">RENTANG WAKTU</label>
                        <div class="input-group">
                            <input type="date" name="start" class="form-control bg-light" value="<?php echo htmlspecialchars($date_start); ?>">
                            <span class="input-group-text bg-white border-0">s/d</span>
                            <input type="date" name="end" class="form-control bg-light" value="<?php echo htmlspecialchars($date_end); ?>">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Filter</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-custom p-0 table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3">Waktu</th>
                        <th class="py-3">User</th>
                        <th class="py-3">Aksi</th>
                        <th class="py-3">Deskripsi</th>
                        <th class="py-3">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach($logs as $l): ?>
                        <tr>
                            <td class="ps-4 text-muted small" style="white-space: nowrap;">
                                <?php echo tgl_indo($l['created_at']); ?>
                            </td>
                            <td class="fw-bold text-dark">
                                <?php echo htmlspecialchars($l['name']); ?>
                            </td>
                            <td>
                                <?php 
                                    // Warna Badge Berdasarkan Aksi
                                    $bg_class = 'secondary';
                                    if (strpos($l['action'], 'CREATE') !== false) $bg_class = 'success';
                                    elseif (strpos($l['action'], 'DELETE') !== false) $bg_class = 'danger';
                                    elseif (strpos($l['action'], 'EDIT') !== false || strpos($l['action'], 'UPDATE') !== false) $bg_class = 'warning text-dark';
                                    elseif (strpos($l['action'], 'LOGIN') !== false) $bg_class = 'info text-dark';
                                ?>
                                <span class="badge bg-<?php echo $bg_class; ?> bg-opacity-10 text-<?php echo str_replace(' text-dark','',$bg_class); ?> border border-<?php echo str_replace(' text-dark','',$bg_class); ?>">
                                    <?php echo htmlspecialchars($l['action']); ?>
                                </span>
                            </td>
                            <td class="text-secondary small">
                                <?php echo htmlspecialchars($l['description']); ?>
                            </td>
                            <td class="text-muted small font-monospace">
                                <?php echo htmlspecialchars($l['ip_address']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Tidak ada data aktivitas yang ditemukan.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link border-0 rounded-circle mx-1" href="<?php echo build_url($page - 1); ?>"><i class="bi bi-chevron-left"></i></a>
                </li>

                <?php 
                // Logic simpel untuk membatasi jumlah tombol pagination yang tampil
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) { echo '<li class="page-item"><a class="page-link border-0 rounded-circle mx-1" href="'.build_url(1).'">1</a></li>'; if($start_page > 2) echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>'; }

                for($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link border-0 rounded-circle mx-1" href="<?php echo build_url($i); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages) { if($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>'; echo '<li class="page-item"><a class="page-link border-0 rounded-circle mx-1" href="'.build_url($total_pages).'">'.$total_pages.'</a></li>'; } ?>

                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link border-0 rounded-circle mx-1" href="<?php echo build_url($page + 1); ?>"><i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>