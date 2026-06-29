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
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                $conn->prepare("UPDATE users SET avatar = NULL WHERE id = ?")->execute([$user_id]);
            }

            if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $curr_av = $conn->query("SELECT avatar FROM users WHERE id=$user_id")->fetchColumn();
                    
                    $new_filename = "avatar_" . $user_id . "_" . time() . "." . $ext;
                    
                    if (!is_dir($base_dir)) {
                        mkdir($base_dir, 0777, true);
                    }
                    
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $base_dir . $new_filename)) {
                        if ($curr_av) {
                            $old_file = $base_dir . $curr_av;
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                        $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$new_filename, $user_id]);
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
    .profile-header { background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 2px 15px rgba(0,0,0,0.03); margin-bottom: 30px; display: flex; align-items: center; gap: 30px; }
    .avatar-wrapper { position: relative; width: 120px; height: 120px; flex-shrink: 0; }
    .profile-avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid #f8f9fa; }
    .btn-upload { position: absolute; bottom: 0; right: 0; background: #000; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; border: 2px solid #fff; z-index: 2; }
    .btn-upload:hover { background: #333; transform: scale(1.1); }
    .btn-delete-av { position: absolute; bottom: 0; left: 0; background: #dc3545; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; border: 2px solid #fff; z-index: 2; }
    .btn-delete-av:hover { background: #bb2d3b; transform: scale(1.1); }
    .badge-role { background: #000; color: #fff; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-left: 10px; vertical-align: middle; }
    .stats-row { display: flex; gap: 40px; margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px; }
    .stat-val { font-size: 1.2rem; font-weight: 800; color: #000; }
    .stat-lbl { font-size: 0.7rem; color: #888; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
    .settings-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 15px rgba(0,0,0,0.03); overflow: hidden; }
    .settings-nav { background: #f8f9fa; padding: 20px; border-bottom: 1px solid #eee; display: flex; gap: 10px; }
    .nav-btn { border: none; background: transparent; padding: 10px 20px; border-radius: 8px; font-weight: 600; color: #666; transition: 0.2s; cursor: pointer; }
    .nav-btn:hover { color: #000; background: rgba(0,0,0,0.05); }
    .nav-btn.active { background: #000; color: #fff; }
    .form-section { padding: 40px; display: none; }
    .form-section.active { display: block; animation: fadeIn 0.3s ease; }
    .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #888; margin-bottom: 8px; display: block; letter-spacing: 0.5px; }
    .form-control-clean { width: 100%; padding: 12px 15px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; color: #333; background: #fff; transition: 0.2s; }
    .form-control-clean:focus { border-color: #000; outline: none; box-shadow: 0 0 0 3px rgba(0,0,0,0.05); }
    .form-control-clean[readonly] { background: #f9f9f9; color: #666; border-color: #eee; }
    .btn-save { background: #000; color: #fff; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; }
    .btn-save:hover { background: #333; transform: translateY(-2px); }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="main-wrapper">
    <div class="content-area" style="max-width: 900px; margin: 0 auto;">
        
        <div class="mb-4">
            <h3 class="fw-bold mb-1">Profil Saya</h3>
            <p class="text-muted mb-0">Kelola informasi profil dan keamanan akun Anda.</p>
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
                <div class="text-muted mt-1">@<?php echo htmlspecialchars($u['nickname'] ?: str_replace(' ','',$u['name'])); ?> &bull; <?php echo htmlspecialchars($u['email']); ?></div>
                <div class="stats-row">
                    <div class="stat-item"><div class="stat-val"><?php echo $stats['todo']??0; ?></div><div class="stat-lbl">Todo</div></div>
                    <div class="stat-item"><div class="stat-val"><?php echo $stats['in_progress']??0; ?></div><div class="stat-lbl">Proses</div></div>
                    <div class="stat-item"><div class="stat-val text-success"><?php echo $stats['done']??0; ?></div><div class="stat-lbl text-success">Selesai</div></div>
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
                    <div class="col-md-6 border-end pe-4">
                        <h5 class="fw-bold mb-4">Ubah Password</h5>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3"><label class="form-label">Password Lama</label><input type="password" name="old_password" class="form-control-clean" required></div>
                            <div class="mb-3"><label class="form-label">Password Baru</label><input type="password" name="new_password" class="form-control-clean" required></div>
                            <div class="mb-4"><label class="form-label">Konfirmasi Password</label><input type="password" name="confirm_password" class="form-control-clean" required></div>
                            <button type="submit" class="btn-save w-100">Update Password</button>
                        </form>
                    </div>
                    <div class="col-md-6 ps-4">
                        <h5 class="fw-bold mb-4">Ubah Email</h5>
                        <div class="p-3 bg-light rounded mb-4 border"><small class="text-muted d-block">EMAIL SAAT INI</small><div class="fw-bold text-dark"><?php echo htmlspecialchars($u['email']); ?></div></div>
                        <form method="POST">
                            <input type="hidden" name="action" value="request_email_change">
                            <div class="mb-4"><label class="form-label">Email Baru</label><input type="email" name="new_email" class="form-control-clean" required placeholder="nama@perusahaan.com"></div>
                            <button type="submit" class="btn-save w-100 bg-white text-dark border">Kirim Kode OTP</button>
                        </form>
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