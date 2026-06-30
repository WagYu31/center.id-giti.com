<?php
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

// Security: Hanya Admin/Authorized
if ($_SESSION['user_role'] !== 'admin') {
    redirect('index.php');
}

$page = "Data Karyawan";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Karyawan - Center Loewix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=4.0">
    <link rel="icon" type="image/png" href="assets/uploads/logo-square.png">
</head>
<body>

<div class="container pb-5">
    
    <div class="d-flex justify-content-between align-items-center navbar-wander mb-4">
        <a href="index.php" class="brand-wander text-white text-decoration-none">
            <i class="bi bi-arrow-left-circle-fill"></i> Back to Dashboard
        </a>
        <div class="user-pill">
            <span class="small fw-bold px-2"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
        </div>
    </div>

    <div class="card-wander">
        <div class="row align-items-center mb-4 g-3">
            <div class="col-md-4">
                <h4 class="fw-bold mb-0">Kelola Akses</h4>
                <p class="text-secondary small mb-0">Atur izin aplikasi karyawan</p>
            </div>
            <div class="col-md-4">
                <div class="search-container mx-auto">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Cari nama..." onkeyup="filterTable()">
                </div>
            </div>
            <div class="col-md-4 text-end">
                <a href="register.php" class="btn btn-dark rounded-pill px-4 fw-bold">
                    <i class="bi bi-person-plus-fill me-1"></i> Tambah
                </a>
            </div>
        </div>

        <?php
        $sql = "SELECT * FROM users WHERE id != '1' ORDER BY name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll();
        ?>

        <div class="table-responsive d-none d-md-block">
            <table class="table table-wander table-hover align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Karyawan</th>
                        <th>Jabatan</th>
                        <th class="text-center">Bukti</th>
                        <th class="text-center">Teknisi</th>
                        <th class="text-center">Sales</th>
                        <th class="text-center">Quo</th>
                        <th class="text-center">Service</th>
                        <th class="text-center">Produksi</th>
                        <th class="text-center">Giti</th> <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="desktopTableBody">
                    <?php foreach($users as $row): 
                        $imgFile = $row['avatar'] ?? 'default.png';
                        $imgPath = "assets/img/avatars/" . $imgFile;
                    ?>
                    <tr class="user-row" data-name="<?= strtolower($row['name']) ?>">
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <img src="<?= $imgPath ?>" class="table-avatar" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['name']) ?>&background=random';">
                                <div>
                                    <span class="d-block fw-bold text-dark"><?= htmlspecialchars($row['name']) ?></span>
                                    <small class="text-muted d-block" style="font-size: 0.75rem;"><?= htmlspecialchars($row['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="d-block fw-semibold small"><?= htmlspecialchars($row['jabatan']) ?></span>
                            <small class="text-secondary" style="font-size: 0.7rem;"><?= htmlspecialchars($row['division']) ?></small>
                        </td>
                        
                        <?php 
                        $apps = [
                            'app_bukti' => 'bukti', 
                            'app_teknisi' => 'teknisi', 
                            'app_sales' => 'sales', 
                            'app_quotation' => 'quotation', 
                            'app_service' => 'service', 
                            'app_produksi' => 'produksi',
                            'app_giti' => 'giti'
                        ];
                        foreach($apps as $col => $val): 
                            // Logic: Jika di DB 1 atau 'Y', maka checked
                            $isChecked = ($row[$col] == 1 || $row[$col] == 'Y') ? 'checked' : '';
                        ?>
                        <td class="text-center">
                            <div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input wander-switch update-status" type="checkbox" role="switch"
                                    data-id="<?= $row['id'] ?>" 
                                    data-field="<?= $col ?>" 
                                    <?= $isChecked ?>>
                            </div>
                        </td>
                        <?php endforeach; ?>

                        <td class="text-center">
                            <button class="btn btn-icon-action" style="color:#d97706;" onclick="showResetModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')" title="Reset Password">
                                <i class="bi bi-key"></i>
                            </button>
                            <a href="#" class="btn btn-icon-action text-danger" onclick="return confirm('Hapus user ini?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="d-md-none" id="mobileListContainer">
            <?php foreach($users as $row): 
                 $imgFile = $row['avatar'] ?? 'default.png';
                 $imgPath = "assets/img/avatars/" . $imgFile;
            ?>
            <div class="mobile-employee-card user-row" data-name="<?= strtolower($row['name']) ?>">
                <div class="mobile-card-header">
                    <img src="<?= $imgPath ?>" class="table-avatar" style="width: 50px; height: 50px;" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['name']) ?>&background=random';">
                    <div class="ms-3">
                        <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($row['name']) ?></h5>
                        <small class="text-muted"><?= htmlspecialchars($row['jabatan']) ?> • <?= htmlspecialchars($row['division']) ?></small>
                    </div>
                </div>
                
                <div class="app-grid-mobile">
                    <?php foreach($apps as $col => $label): 
                        $isChecked = ($row[$col] == 1 || $row[$col] == 'Y') ? 'checked' : '';
                    ?>
                    <div class="app-item-mobile">
                        <span class="app-label text-capitalize"><?= $label ?></span>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input wander-switch update-status" type="checkbox" role="switch"
                                data-id="<?= $row['id'] ?>" 
                                data-field="<?= $col ?>" 
                                <?= $isChecked ?>>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div id="noResults" class="text-center py-5 d-none">
            <i class="bi bi-search fs-1 text-muted mb-3 d-block"></i>
            <p class="text-secondary">Tidak ada karyawan yang ditemukan.</p>
        </div>

    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-4 shadow-lg" style="overflow:hidden;">
            <div class="modal-header border-0 px-4 pt-4 pb-1">
                <div>
                    <h6 class="fw-bold mb-0" style="color:#0f172a;"><i class="bi bi-key me-2" style="color:#d97706;"></i>Reset Password</h6>
                    <small id="resetUserLabel" style="color:#94a3b8;"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <input type="hidden" id="resetUserId">
                <div class="mb-3">
                    <label style="font-size:0.7rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:0.5px;">Password Baru</label>
                    <div class="input-group">
                        <input type="password" id="resetNewPassword" class="form-control" placeholder="Min. 6 karakter" style="border-radius:10px 0 0 10px;border-color:#e2e8f0;font-size:0.88rem;">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePwdVisibility()" style="border-radius:0 10px 10px 0;border-color:#e2e8f0;" title="Tampilkan">
                            <i class="bi bi-eye" id="togglePwdIcon"></i>
                        </button>
                    </div>
                </div>
                <button class="btn btn-sm w-100 mb-3" onclick="generatePassword()" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:0.8rem;font-weight:600;color:#64748b;">
                    <i class="bi bi-shuffle me-1"></i>Generate Password Acak
                </button>
                <button onclick="submitResetPassword()" class="btn w-100 fw-bold" style="background:linear-gradient(135deg,#d97706,#f59e0b);color:white;border:none;border-radius:10px;padding:10px;font-size:0.88rem;">
                    <i class="bi bi-check-lg me-1"></i>Reset Password
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // 1. FILTER SEARCH (Tanpa reload page, pure JS filtering)
    function filterTable() {
        var input = document.getElementById("searchInput");
        var filter = input.value.toLowerCase();
        var nodes = document.getElementsByClassName('user-row');
        var found = false;

        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].dataset.name.includes(filter)) {
                nodes[i].style.display = ""; // Show
                found = true;
            } else {
                nodes[i].style.display = "none"; // Hide
            }
        }

        // Show/Hide empty state
        document.getElementById('noResults').classList.toggle('d-none', found);
    }

    // 2. AJAX AUTO UPDATE (Y/N Logic)
    $(document).ready(function() {
        $(".update-status").on("change", function() {
            var $this = $(this);
            var userId = $this.data("id");
            var field = $this.data("field");
            
            var value = $this.is(":checked") ? "Y" : "N";

            // $this.prop('disabled', true);

            $.ajax({
                url: "update_status.php",
                type: "POST",
                data: {
                    id: userId,
                    field: field,
                    value: value
                },
                success: function(response) {
                    console.log("Updated: " + field + " -> " + value);
                    // $this.prop('disabled', false);
                },
                error: function(xhr, status, error) {
                    console.error("Error:", error);
                    // Kembalikan status checkbox jika gagal
                    $this.prop('checked', !$this.is(":checked")); 
                    alert("Gagal update database. Cek koneksi.");
                }
            });
        });
    });
    // 3. RESET PASSWORD
    function showResetModal(userId, userName) {
        document.getElementById('resetUserId').value = userId;
        document.getElementById('resetUserLabel').textContent = 'Untuk: ' + userName;
        document.getElementById('resetNewPassword').value = '';
        new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
    }

    function togglePwdVisibility() {
        const inp = document.getElementById('resetNewPassword');
        const icon = document.getElementById('togglePwdIcon');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            inp.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }

    function generatePassword() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
        let pwd = '';
        for (let i = 0; i < 10; i++) pwd += chars.charAt(Math.floor(Math.random() * chars.length));
        const inp = document.getElementById('resetNewPassword');
        inp.value = pwd;
        inp.type = 'text';
        document.getElementById('togglePwdIcon').className = 'bi bi-eye-slash';
    }

    function submitResetPassword() {
        const userId = document.getElementById('resetUserId').value;
        const newPwd = document.getElementById('resetNewPassword').value;
        if (newPwd.length < 6) { alert('Password minimal 6 karakter'); return; }
        if (!confirm('Yakin reset password user ini?')) return;

        const fd = new FormData();
        fd.append('user_id', userId);
        fd.append('new_password', newPwd);

        fetch('reset_password.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('resetPasswordModal')).hide();
                    alert(res.message);
                } else {
                    alert(res.message);
                }
            })
            .catch(() => alert('Gagal koneksi ke server'));
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>