<?php
require_once 'includes/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";
$show_otp_modal = false;

function sendOtpEmail($toEmail, $otp, $userName) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'verification@grav-tech.com';
        $mail->Password   = '6vt@L0E18'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('verification@grav-tech.com', 'Center ID Security');
        $mail->addAddress($toEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = 'Kode Verifikasi Perubahan Email';
        
        $emailContent = '
        <div style="font-family: sans-serif; max-width: 500px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px; overflow: hidden;">
            <div style="background: #000; padding: 20px; text-align: center;">
                <h2 style="color: #fff; margin: 0; font-size: 18px;">Center ID Security</h2>
            </div>
            <div style="padding: 30px;">
                <p style="color: #333; margin-bottom: 20px;">Halo <strong>' . htmlspecialchars($userName) . '</strong>,</p>
                <p style="color: #555; font-size: 14px; margin-bottom: 25px;">Gunakan kode OTP berikut untuk memverifikasi perubahan email akun Anda:</p>
                <div style="text-align: center; margin-bottom: 25px;">
                    <span style="font-size: 28px; font-weight: bold; letter-spacing: 4px; color: #000; background: #f4f4f4; padding: 10px 20px; border-radius: 4px;">' . $otp . '</span>
                </div>
                <p style="color: #999; font-size: 12px; margin: 0;">Abaikan jika Anda tidak meminta perubahan ini.</p>
            </div>
        </div>';

        $mail->Body = $emailContent;
        $mail->send();
        return true;
    } catch (Exception $e) { return false; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $nickname = trim($_POST['nickname']);
        $bio = trim($_POST['bio']);
        $division = $_POST['division'];
        $jabatan = trim($_POST['jabatan']);
        $jobdesk = trim($_POST['jobdesk']);

        try {
            $base_dir = __DIR__ . '/assets/img/avatars/';

            if (isset($_POST['delete_avatar']) && $_POST['delete_avatar'] == '1') {
                $curr_av = $conn->query("SELECT avatar FROM users WHERE id=$user_id")->fetchColumn();
                if ($curr_av) {
                    $file_path = $base_dir . $curr_av;
                    if (file_exists($file_path)) unlink($file_path);
                }
                $conn->prepare("UPDATE users SET avatar = NULL WHERE id = ?")->execute([$user_id]);
            }

            if (!empty($_FILES['avatar']['name'])) {
                $upload_err = $_FILES['avatar']['error'];

                if ($upload_err !== UPLOAD_ERR_OK) {
                    $php_err_map = [
                        UPLOAD_ERR_INI_SIZE   => 'File terlalu besar (melebihi batas server php.ini upload_max_filesize).',
                        UPLOAD_ERR_FORM_SIZE  => 'File terlalu besar (melebihi MAX_FILE_SIZE form).',
                        UPLOAD_ERR_PARTIAL    => 'Upload tidak lengkap, coba lagi.',
                        UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara server tidak ditemukan.',
                        UPLOAD_ERR_CANT_WRITE => 'Server tidak bisa menulis file, periksa permission.',
                        UPLOAD_ERR_EXTENSION  => 'Upload diblokir oleh ekstensi PHP.',
                    ];
                    $msg = $php_err_map[$upload_err] ?? "Upload gagal (kode: $upload_err).";
                    $msg_type = 'danger';
                } else {
                    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));

                    if (!in_array($ext, $allowed)) {
                        $msg = 'Format foto tidak didukung. Gunakan JPG, PNG, WEBP, atau GIF.';
                        $msg_type = 'danger';
                    } else {
                        // Ensure directory exists & writable
                        if (!is_dir($base_dir)) {
                            mkdir($base_dir, 0775, true);
                        }
                        if (!is_writable($base_dir)) {
                            $msg = 'Folder foto profil tidak bisa ditulis. Hubungi admin untuk perbaiki permission folder assets/img/avatars/.';
                            $msg_type = 'danger';
                        } else {
                            $curr_av = $conn->query("SELECT avatar FROM users WHERE id=$user_id")->fetchColumn();
                            $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;

                            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $base_dir . $new_filename)) {
                                // Delete old avatar
                                if ($curr_av && file_exists($base_dir . $curr_av)) {
                                    unlink($base_dir . $curr_av);
                                }
                                $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$new_filename, $user_id]);
                                $msg = 'Foto profil berhasil diperbarui! ✅';
                                $msg_type = 'success';
                            } else {
                                $msg = 'Gagal menyimpan foto ke server. Coba lagi atau gunakan format/ukuran berbeda.';
                                $msg_type = 'danger';
                            }
                        }
                    }
                }
            }

            $stmt = $conn->prepare("UPDATE users SET nickname=?, bio=?, division=?, jabatan=?, jobdesk=? WHERE id=?");
            $stmt->execute([$nickname, $bio, $division, $jabatan, $jobdesk, $user_id]);
            
            $msg = "Profil berhasil disimpan."; $msg_type = "dark";

        } catch (Exception $e) { 
            $msg = "Gagal update profil."; $msg_type = "danger"; 
        }
    } 
    elseif ($action === 'change_password') {
        $old = $_POST['old_password']; $new = $_POST['new_password']; $conf = $_POST['confirm_password'];
        $curr = $conn->query("SELECT password FROM users WHERE id=$user_id")->fetch();
        if (password_verify($old, $curr['password'])) {
            if ($new === $conf && strlen($new) >= 6) {
                $conn->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
                $msg = "Password berhasil diubah."; $msg_type = "success";
            } else { $msg = "Password baru tidak valid."; $msg_type = "danger"; }
        } else { $msg = "Password lama salah."; $msg_type = "danger"; }
    }
    elseif ($action === 'request_email_change') {
        $new_email = trim($_POST['new_email']);
        if ($conn->query("SELECT id FROM users WHERE email='$new_email' AND id!=$user_id")->rowCount() > 0) {
            $msg = "Email sudah digunakan."; $msg_type = "danger";
        } else {
            $otp = rand(100000, 999999);
            $_SESSION['otp'] = $otp; $_SESSION['new_email'] = $new_email; $_SESSION['otp_time'] = time();
            $uname = $conn->query("SELECT name FROM users WHERE id=$user_id")->fetchColumn();
            if (sendOtpEmail($new_email, $otp, $uname)) { 
                $msg = "OTP terkirim ke $new_email"; 
                $msg_type = "dark"; 
                $show_otp_modal = true; 
            } else { 
                $msg = "Gagal kirim email."; $msg_type = "danger"; 
            }
        }
    }
    elseif ($action === 'verify_email_otp') {
        if ($_POST['otp_code'] == $_SESSION['otp'] && (time() - $_SESSION['otp_time'] < 900)) {
            $email_baru = $_SESSION['new_email']; 
            $conn->prepare("UPDATE users SET email=? WHERE id=?")->execute([$email_baru, $user_id]);
            unset($_SESSION['otp'], $_SESSION['new_email'], $_SESSION['otp_time']);
            $msg = "Email berhasil diubah menjadi " . htmlspecialchars($email_baru);
            $msg_type = "success";
        } else { 
            $msg = "OTP salah/kadaluarsa."; 
            $msg_type = "danger"; 
            $show_otp_modal = true; 
        }
    }
}

$u = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch(PDO::FETCH_ASSOC);

$has_avatar = ($u['avatar'] && file_exists(__DIR__ . '/assets/img/avatars/' . $u['avatar']));
$avatar_src = $has_avatar ? "assets/img/avatars/" . $u['avatar'] : "https://ui-avatars.com/api/?name=" . urlencode($u['name']);

$stats = $conn->query("SELECT status, COUNT(*) as c FROM bukti_jobs WHERE user_id=$user_id AND deleted_at IS NULL GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 
?>

<style>
    /* ═══════════════════════════════════════════════════
       PROFILE PAGE - ISO 9241-151 Ergonomic Design
       ═══════════════════════════════════════════════════ */
    .profile-header {
        background: #fff;
        padding: 32px 36px;
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm);
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 28px;
    }
    .avatar-wrapper { position: relative; width: 100px; height: 100px; flex-shrink: 0; }
    .profile-avatar {
        width: 100%; height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--border-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .btn-upload {
        position: absolute; bottom: 2px; right: 2px;
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: white; width: 30px; height: 30px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: 0.2s;
        border: 2px solid #fff; z-index: 2; font-size: 0.75rem;
        box-shadow: 0 2px 8px rgba(217,119,6,0.25);
    }
    .btn-upload:hover { transform: scale(1.1); }
    .btn-delete-av {
        position: absolute; bottom: 2px; left: 2px;
        background: #ef4444; color: #fff; width: 30px; height: 30px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: 0.2s;
        border: 2px solid #fff; z-index: 2; font-size: 0.75rem;
    }
    .btn-delete-av:hover { background: #dc2626; transform: scale(1.1); }

    .user-info h2 { font-size: 1.35rem; font-weight: 700; color: var(--text-dark); margin: 0; }
    .badge-role {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: white; padding: 3px 10px; border-radius: 6px;
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
        margin-left: 10px; vertical-align: middle; letter-spacing: 0.5px;
    }
    .user-meta { color: var(--text-muted); font-size: 0.85rem; margin-top: 4px; }

    .stats-row {
        display: flex; gap: 32px; margin-top: 14px;
        border-top: 1px solid var(--border-color); padding-top: 14px;
    }
    .stat-val { font-size: 1.1rem; font-weight: 800; color: var(--text-dark); }
    .stat-lbl {
        font-size: 0.65rem; color: var(--text-muted);
        text-transform: uppercase; letter-spacing: 0.8px; font-weight: 700;
    }
    .stat-val.stat-done { color: #059669; }
    .stat-lbl.stat-done { color: #059669; }

    /* Settings Card */
    .settings-card {
        background: #fff; border-radius: var(--radius);
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm); overflow: hidden;
    }
    .settings-nav {
        background: #f8fafc; padding: 16px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex; gap: 8px;
    }
    .nav-btn {
        border: none; background: transparent;
        padding: 9px 18px; border-radius: 8px;
        font-weight: 600; font-size: 0.85rem;
        color: var(--text-muted); transition: 0.2s; cursor: pointer;
    }
    .nav-btn:hover { color: var(--text-dark); background: white; }
    .nav-btn.active {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: white; box-shadow: 0 2px 8px rgba(217,119,6,0.2);
    }

    /* Form Sections */
    .form-section { padding: 32px 36px; display: none; }
    .form-section.active { display: block; animation: fadeIn 0.3s ease; }
    .form-label {
        font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
        color: var(--text-muted); margin-bottom: 7px; display: block;
        letter-spacing: 0.6px;
    }
    .form-control-clean {
        width: 100%; padding: 11px 14px;
        border: 1px solid var(--border-color); border-radius: 10px;
        font-size: 0.88rem; color: var(--text-dark);
        background: #fff; transition: 0.2s;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .form-control-clean:focus {
        border-color: var(--primary); outline: none;
        box-shadow: 0 0 0 3px rgba(217,119,6,0.08);
    }
    .form-control-clean[readonly] {
        background: #f8fafc; color: var(--text-muted);
        border-color: var(--border-color);
    }
    select.form-control-clean {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 36px;
    }
    textarea.form-control-clean { resize: vertical; min-height: 80px; line-height: 1.5; }

    .btn-save {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: white; border: none; padding: 11px 28px; border-radius: 10px;
        font-weight: 600; font-size: 0.88rem; cursor: pointer; transition: 0.2s;
        box-shadow: 0 2px 8px rgba(217,119,6,0.2);
    }
    .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(217,119,6,0.3); }

    /* Security Tab */
    .security-section h5 {
        font-size: 1rem; font-weight: 700; color: var(--text-dark);
        margin-bottom: 20px; display: flex; align-items: center; gap: 8px;
    }
    .security-section h5 i { color: var(--primary); }
    .email-current {
        padding: 14px 16px; background: #f8fafc; border-radius: 10px;
        margin-bottom: 20px; border: 1px solid var(--border-color);
    }
    .email-current small { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); }
    .email-current .email-val { font-weight: 600; color: var(--text-dark); font-size: 0.9rem; margin-top: 2px; }
    .btn-outline-save {
        width: 100%; padding: 11px; border-radius: 10px;
        border: 1px solid var(--border-color); background: white;
        color: var(--text-dark); font-weight: 600; font-size: 0.85rem;
        cursor: pointer; transition: 0.2s;
    }
    .btn-outline-save:hover { border-color: var(--primary); color: #92400e; background: #fffbeb; }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="main-wrapper">
    <div class="content-area" style="max-width: 900px; margin: 0 auto;">
        
        <div class="mb-4">
            <h3 style="font-size: 1.4rem; font-weight: 700; color: var(--text-dark); margin: 0 0 4px;"><i class="bi bi-person-circle me-2" style="color: var(--primary);"></i>Profil Saya</h3>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">Kelola informasi profil dan keamanan akun Anda.</p>
        </div>

        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> border-0 shadow-sm rounded-3 d-flex align-items-center mb-4">
                <i class="bi bi-info-circle-fill me-2 fs-5"></i> <div><?php echo $msg; ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="avatar-wrapper">
                <img src="<?php echo $avatar_src; ?>" class="profile-avatar" id="imgPreview">
                
                <label for="avatarInput" class="btn-upload" title="Ganti Foto" data-bs-toggle="tooltip">
                    <i class="bi bi-camera-fill"></i>
                </label>

                <?php if($has_avatar): ?>
                    <button type="button" class="btn-delete-av" onclick="deleteAvatar()" title="Hapus Foto" data-bs-toggle="tooltip">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                <?php endif; ?>
            </div>
            <div class="user-info flex-grow-1">
                <div class="d-flex align-items-center">
                    <h2 class="fw-bold m-0"><?php echo htmlspecialchars($u['name']); ?></h2>
                    <span class="badge-role"><?php echo htmlspecialchars($u['jabatan']); ?></span>
                </div>
                <div class="user-meta">@<?php echo htmlspecialchars($u['nickname'] ?: str_replace(' ','',$u['name'])); ?> &bull; <?php echo htmlspecialchars($u['email']); ?></div>
                <div class="stats-row">
                    <div class="stat-item"><div class="stat-val"><?php echo $stats['todo']??0; ?></div><div class="stat-lbl">Todo</div></div>
                    <div class="stat-item"><div class="stat-val"><?php echo $stats['in_progress']??0; ?></div><div class="stat-lbl">Proses</div></div>
                    <div class="stat-item"><div class="stat-val stat-done"><?php echo $stats['done']??0; ?></div><div class="stat-lbl stat-done">Selesai</div></div>
                </div>
            </div>
        </div>

        <div class="settings-card">
            <div class="settings-nav">
                <button class="nav-btn active" onclick="switchTab('profile')"><i class="bi bi-person me-2"></i>Edit Profil</button>
                <button class="nav-btn" onclick="switchTab('security')"><i class="bi bi-shield-lock me-2"></i>Keamanan</button>
            </div>

            <div id="tab-profile" class="form-section active">
                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="delete_avatar" id="deleteAvatarInput" value="0">
                    
                    <input type="file" name="avatar" id="avatarInput" hidden accept="image/*">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Nama Lengkap</label><input type="text" class="form-control-clean" value="<?php echo htmlspecialchars($u['name']); ?>" readonly></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Nickname</label><input type="text" name="nickname" class="form-control-clean" value="<?php echo htmlspecialchars($u['nickname']??''); ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Divisi</label><select name="division" class="form-control-clean"><?php foreach(['Marketing','Sales','Finance','Teknisi','Produksi','Operasional','IT','HRD'] as $d) echo "<option value='$d' ".($u['division']==$d?'selected':'').">$d</option>"; ?></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Jabatan</label><input type="text" name="jabatan" class="form-control-clean" value="<?php echo htmlspecialchars($u['jabatan']); ?>"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Bio Singkat</label><textarea name="bio" class="form-control-clean" rows="2"><?php echo htmlspecialchars($u['bio']??''); ?></textarea></div>
                    <div class="mb-4"><label class="form-label">Job Description</label><textarea name="jobdesk" class="form-control-clean" rows="4"><?php echo htmlspecialchars($u['jobdesk']??''); ?></textarea></div>
                    <div class="text-end"><button type="submit" class="btn-save">Simpan Perubahan</button></div>
                </form>
            </div>

            <div id="tab-security" class="form-section">
                <div class="row">
                    <div class="col-md-6 pe-4" style="border-right: 1px solid var(--border-color);">
                        <div class="security-section">
                            <h5><i class="bi bi-key"></i>Ubah Password</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                <div class="mb-3"><label class="form-label">Password Lama</label><input type="password" name="old_password" class="form-control-clean" required></div>
                                <div class="mb-3"><label class="form-label">Password Baru</label><input type="password" name="new_password" class="form-control-clean" required></div>
                                <div class="mb-4"><label class="form-label">Konfirmasi Password</label><input type="password" name="confirm_password" class="form-control-clean" required></div>
                                <button type="submit" class="btn-save w-100">Update Password</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-6 ps-4">
                        <div class="security-section">
                            <h5><i class="bi bi-envelope"></i>Ubah Email</h5>
                            <div class="email-current"><small>EMAIL SAAT INI</small><div class="email-val"><?php echo htmlspecialchars($u['email']); ?></div></div>
                            <form method="POST">
                                <input type="hidden" name="action" value="request_email_change">
                                <div class="mb-4"><label class="form-label">Email Baru</label><input type="email" name="new_email" class="form-control-clean" required placeholder="nama@perusahaan.com"></div>
                                <button type="submit" class="btn-outline-save"><i class="bi bi-send me-1"></i>Kirim Kode OTP</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="otpModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-4 p-3">
            <div class="modal-body text-center">
                <h5 class="fw-bold mb-3">Verifikasi Email</h5>
                <p class="small text-muted mb-3">Masukkan kode OTP yang telah dikirim ke email baru Anda.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="verify_email_otp">
                    <input type="text" name="otp_code" class="form-control form-control-lg text-center fw-bold bg-light border-0 mb-3" placeholder="XXXXXX" maxlength="6" required style="letter-spacing: 4px;">
                    <button type="submit" class="btn-save w-100 mb-2">Verifikasi</button>
                    <a href="profile.php" class="small text-muted text-decoration-none">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    function switchTab(tabName) {
        document.querySelectorAll('.form-section').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + tabName).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    document.getElementById('avatarInput').addEventListener('change', function(e) {
        if(e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) { document.getElementById('imgPreview').src = e.target.result; }
            reader.readAsDataURL(e.target.files[0]);
            
            document.getElementById('deleteAvatarInput').value = '0';
        }
    });

    function deleteAvatar() {
        if(confirm('Apakah Anda yakin ingin menghapus foto profil?')) {
            document.getElementById('deleteAvatarInput').value = '1';
            document.getElementById('avatarInput').value = '';
            document.getElementById('profileForm').submit();
        }
    }

    <?php if($show_otp_modal): ?>
    document.addEventListener('DOMContentLoaded', function() {
        var otpModal = new bootstrap.Modal(document.getElementById('otpModal'));
        otpModal.show();
    });
    <?php endif; ?>
</script>