<?php
require_once 'includes/db.php';
require_once __DIR__ . '/../../src/send_email.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function write_log($conn, $user_id, $act, $desc) {
    $conn->prepare("INSERT INTO bukti_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)")
         ->execute([$user_id, $act, $desc, $_SERVER['REMOTE_ADDR']]);
}

function notify_approver_approval_request($conn, $job_id, $job_title, $job_desc, $requester_id) {
    try {
        $approver_stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = 1 LIMIT 1");
        $approver_stmt->execute();
        $approver = $approver_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$approver) {
            $approver_stmt = $conn->prepare("SELECT id, name, email FROM users WHERE role = 'admin' LIMIT 1");
            $approver_stmt->execute();
            $approver = $approver_stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($approver) {
            // In-app notification
            $conn->prepare("INSERT INTO bukti_notifications (user_id, actor_id, job_id, type) VALUES (?, ?, ?, 'approval_request')")
                 ->execute([$approver['id'], $requester_id, $job_id]);
                 
            // Requester name
            $u = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $u->execute([$requester_id]);
            $req_name = $u->fetchColumn() ?: 'Staf';

            // Send Email
            @sendApprovalRequestEmail($approver['email'], $approver['name'], $req_name, $job_title, $job_desc, $job_id);
        }
    } catch (Exception $e) {}
}

function get_tagged_users_from_text($conn, $text, $exclude_user_id = null) {
    if (!$text) return [];
    preg_match_all('/@(\w+)/', $text, $matches);
    if (empty($matches[1])) return [];

    $recipients = [];
    $nicks = array_unique($matches[1]);
    foreach ($nicks as $nick) {
        $u = $conn->prepare("SELECT id, name, email FROM users WHERE (LOWER(nickname) = LOWER(?) OR LOWER(REPLACE(name, ' ', '')) = LOWER(?)) AND email IS NOT NULL AND email != '' LIMIT 1");
        $u->execute([$nick, $nick]);
        $user = $u->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            if ($exclude_user_id !== null && $user['id'] == $exclude_user_id) {
                // If explicitly excluded, skip
                continue;
            }
            $recipients[$user['id']] = $user;
        }
    }
    return $recipients;
}

function get_job_recipients($conn, $job_id, $exclude_user_id = null) {
    $stmt = $conn->prepare("SELECT j.user_id as creator_id, j.description, u.name as creator_name, u.email as creator_email 
                            FROM bukti_jobs j 
                            JOIN users u ON j.user_id = u.id 
                            WHERE j.id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) return [];

    $recipients = [];
    // 1. Creator (always included)
    if (!empty($job['creator_email'])) {
        $recipients[$job['creator_id']] = [
            'id'    => $job['creator_id'],
            'name'  => $job['creator_name'],
            'email' => $job['creator_email']
        ];
    }

    // 2. Tagged users in job description (all tagged users included)
    $tagged = get_tagged_users_from_text($conn, $job['description'], null);
    foreach ($tagged as $id => $u) {
        $recipients[$id] = $u;
    }

    return array_values($recipients);
}

function notify_tagged_users($conn, $actor_id, $job_id, $job_title, $text, $sourceType = 'job') {
    // Include self-tag so user testing their own tag also receives the notification email
    $tagged = get_tagged_users_from_text($conn, $text, null);
    if (empty($tagged)) return [];

    $actor_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $actor_stmt->execute([$actor_id]);
    $actor_name = $actor_stmt->fetchColumn() ?: 'Rekan Tim';

    foreach ($tagged as $user) {
        // In-app notification
        try {
            $conn->prepare("INSERT INTO bukti_notifications (user_id, actor_id, job_id, type) VALUES (?, ?, ?, 'mention')")
                 ->execute([$user['id'], $actor_id, $job_id]);
        } catch (Exception $e) {}

        // Email notification
        @sendTagNotificationEmail($user['email'], $user['name'], $actor_name, $job_title, $text, $job_id, $sourceType);
    }
    return $tagged;
}

// --- FUNGSI HELPER UPLOAD (FIX MASALAH 1 & 2) ---
function process_uploads($conn, $job_id, $files, $progress_id = null) {
    if (!empty($files['name'][0])) {
        $upload_dir = 'assets/uploads/bukti/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] === 0) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $new_name = uniqid() . '_' . time() . '.' . $ext;
                
                $type = 'document';
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $type = 'image';
                elseif (in_array($ext, ['mp4', 'webm', 'ogg', 'mov'])) $type = 'video';
                elseif (in_array($ext, ['mp3', 'wav'])) $type = 'audio';

                if (move_uploaded_file($files['tmp_name'][$key], $upload_dir . $new_name)) {
                    $conn->prepare("INSERT INTO bukti_job_attachments (job_id, progress_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?, ?)")
                          ->execute([$job_id, $progress_id, $name, $new_name, $type]);
                }
            }
        }
    }
}

if ($action == 'create_post') {
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("INSERT INTO bukti_jobs (user_id, title, description, status, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $_POST['title'], $_POST['description'], $_POST['status'], $_POST['start_date'], $_POST['end_date']]);
        $job_id = $conn->lastInsertId();

        // Handle Uploads
        if (isset($_FILES['files'])) {
            process_uploads($conn, $job_id, $_FILES['files']);
        }

        // Tagging Notif & Email
        notify_tagged_users($conn, $user_id, $job_id, $_POST['title'], $_POST['description'], 'job');

        write_log($conn, $user_id, 'CREATE_JOB', "Membuat pekerjaan: " . $_POST['title']);
        
        // Trigger Notifikasi & Email Approval jika status 'pending_approval'
        if ($_POST['status'] === 'pending_approval') {
            notify_approver_approval_request($conn, $job_id, $_POST['title'], $_POST['description'], $user_id);
        }

        $conn->commit();
        echo json_encode(['status'=>'success']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
    }
    exit;
}

if ($action == 'edit_post') {
    $job_id = (int)$_POST['job_id'];
    $check = $conn->prepare("SELECT user_id FROM bukti_jobs WHERE id = ?");
    $check->execute([$job_id]);
    
    if ($check->fetchColumn() == $user_id) {
        $stmt = $conn->prepare("UPDATE bukti_jobs SET title = ?, description = ?, is_edited = 1, status = ?, start_date = ?, end_date = ? WHERE id = ?");
        $stmt->execute([$_POST['title'], $_POST['description'], $_POST['status'], $_POST['start_date'], $_POST['end_date'], $job_id]);
        
        // Handle Uploads saat Edit (Fix Masalah 1)
        if (isset($_FILES['files'])) {
            process_uploads($conn, $job_id, $_FILES['files']);
        }

        // Tagging Notif & Email
        notify_tagged_users($conn, $user_id, $job_id, $_POST['title'], $_POST['description'], 'job');

        // Trigger Notifikasi & Email Approval jika status 'pending_approval'
        if ($_POST['status'] === 'pending_approval') {
            notify_approver_approval_request($conn, $job_id, $_POST['title'], $_POST['description'], $user_id);
        }

        write_log($conn, $user_id, 'EDIT_JOB', "Edit pekerjaan ID: " . $job_id);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    }
    exit;
}

if ($action == 'update_progress') {
    $job_id = $_POST['job_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    
    // Cek apakah user adalah owner ATAU di-tag di post
    $chk = $conn->prepare("SELECT j.status, j.title, j.description, j.user_id,
        (SELECT name FROM users WHERE id = ?) AS my_name,
        (SELECT nickname FROM users WHERE id = ?) AS my_nick
        FROM bukti_jobs j WHERE j.id = ?");
    $chk->execute([$user_id, $user_id, $job_id]);
    $job = $chk->fetch();

    $is_owner  = ($job['user_id'] == $user_id);
    $my_tag    = '@' . str_replace(' ', '', $job['my_name']);
    $my_nick_t = '@' . ($job['my_nick'] ?: str_replace(' ', '', $job['my_name']));
    $is_tagged = (stripos($job['description'], $my_tag) !== false ||
                  stripos($job['description'], $my_nick_t) !== false);

    if (!$is_owner && !$is_tagged) {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
        exit;
    }

    // Owner DAN tagged user bisa update status post
    $conn->prepare("UPDATE bukti_jobs SET status = ? WHERE id = ?")->execute([$status, $job_id]);
    
    $conn->prepare("INSERT INTO bukti_job_progress (job_id, user_id, status_before, status_after, notes) VALUES (?, ?, ?, ?, ?)")
         ->execute([$job_id, $user_id, $job['status'], $status, $notes]);
    $progress_id = $conn->lastInsertId();
    
    // Handle Uploads saat Progress
    if (isset($_FILES['files'])) {
        process_uploads($conn, $job_id, $_FILES['files'], $progress_id);
    }

    // Trigger Notifikasi & Email Approval jika update status ke 'pending_approval'
    if ($status === 'pending_approval') {
        $update_desc = $job['description'] . "\n\n[Update Progres]: " . $notes;
        notify_approver_approval_request($conn, $job_id, $job['title'], $update_desc, $user_id);
    }

    // Kirim notifikasi in-app & email update progress ke creator & seluruh user yang di-tag
    $recipients = get_job_recipients($conn, $job_id, $user_id);
    $note_tagged = get_tagged_users_from_text($conn, $notes, $user_id);
    foreach ($note_tagged as $nt) {
        $recipients[] = $nt;
    }
    // Deduplicate by ID
    $unique_recipients = [];
    foreach ($recipients as $r) {
        $unique_recipients[$r['id']] = $r;
    }

    $u_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $u_stmt->execute([$user_id]);
    $actor_name = $u_stmt->fetchColumn() ?: 'Rekan Tim';

    foreach ($unique_recipients as $rec) {
        try {
            $conn->prepare("INSERT INTO bukti_notifications (user_id, actor_id, job_id, type) VALUES (?, ?, ?, 'comment')")
                 ->execute([$rec['id'], $user_id, $job_id]);
        } catch (Exception $e) {}

        if (!empty($rec['email'])) {
            @sendProgressUpdateEmail($rec['email'], $rec['name'], $actor_name, $job['title'], $job['status'], $status, $notes, $job_id);
        }
    }

    write_log($conn, $user_id, 'UPDATE_PROGRESS', "Update status '{$job['title']}' ke $status");
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action == 'approve_job') {
    $job_id = (int)$_POST['job_id'];
    $is_admin = (($_SESSION['role'] ?? '') === 'admin' || $user_id == 1);
    if (!$is_admin) {
        echo json_encode(['status' => 'error', 'message' => 'Hanya Pimpinan / Admin yang berhak menyetujui pekerjaan']);
        exit;
    }

    $stmt = $conn->prepare("SELECT j.*, u.name as creator_name, u.email as creator_email FROM bukti_jobs j JOIN users u ON j.user_id = u.id WHERE j.id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        echo json_encode(['status' => 'error', 'message' => 'Pekerjaan tidak ditemukan']);
        exit;
    }

    // Update status to in_progress (Lanjut Kerjakan) with approval fields
    $up = $conn->prepare("UPDATE bukti_jobs SET status = 'in_progress', approval_by = ?, approval_at = NOW(), approval_notes = NULL WHERE id = ?");
    $up->execute([$user_id, $job_id]);

    // Log to progress timeline
    $conn->prepare("INSERT INTO bukti_job_progress (job_id, user_id, status_before, status_after, notes) VALUES (?, ?, ?, 'in_progress', ?)")
         ->execute([$job_id, $user_id, $job['status'], 'Disetujui oleh Pimpinan (Lanjut Kerjakan)']);

    write_log($conn, $user_id, 'APPROVE_JOB', "Menyetujui pekerjaan '{$job['title']}' (Lanjut Kerjakan)");

    // Kirim notifikasi in-app & email ke creator dan seluruh user yang di-tag
    $recipients = get_job_recipients($conn, $job_id, $user_id);
    $approver_name = $_SESSION['name'] ?? 'Pimpinan';

    foreach ($recipients as $rec) {
        try {
            $conn->prepare("INSERT INTO bukti_notifications (user_id, actor_id, job_id, type) VALUES (?, ?, ?, 'approval_approved')")
                 ->execute([$rec['id'], $user_id, $job_id]);
        } catch (Exception $e) {}

        if (!empty($rec['email'])) {
            @sendApprovalResultEmail($rec['email'], $rec['name'], $approver_name, $job['title'], 'approved', '', $job_id);
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'Pekerjaan berhasil disetujui (Lanjut Kerjakan)']);
    exit;
}

if ($action == 'reject_job') {
    $job_id = (int)$_POST['job_id'];
    $notes = trim($_POST['notes'] ?? '');
    $is_admin = (($_SESSION['role'] ?? '') === 'admin' || $user_id == 1);
    if (!$is_admin) {
        echo json_encode(['status' => 'error', 'message' => 'Hanya Pimpinan / Admin yang berhak menolak / meminta meeting ulang']);
        exit;
    }

    $stmt = $conn->prepare("SELECT j.*, u.name as creator_name, u.email as creator_email FROM bukti_jobs j JOIN users u ON j.user_id = u.id WHERE j.id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        echo json_encode(['status' => 'error', 'message' => 'Pekerjaan tidak ditemukan']);
        exit;
    }

    // Update status to need_meeting with approval notes
    $up = $conn->prepare("UPDATE bukti_jobs SET status = 'need_meeting', approval_by = ?, approval_at = NOW(), approval_notes = ? WHERE id = ?");
    $up->execute([$user_id, $notes, $job_id]);

    $progress_note = "Meeting Ulang (Tidak Approve)" . ($notes ? ": " . $notes : "");
    $conn->prepare("INSERT INTO bukti_job_progress (job_id, user_id, status_before, status_after, notes) VALUES (?, ?, ?, 'need_meeting', ?)")
         ->execute([$job_id, $user_id, $job['status'], $progress_note]);

    // Insert into discussion comments so everyone sees the instruction
    $comm_content = "⚠️ **INSTRUKSI PIMPINAN: PERLU MEETING ULANG**\n" . ($notes ?: "Harap jadwalkan pembahasan ulang dengan pimpinan.");
    $conn->prepare("INSERT INTO bukti_comments (job_id, user_id, content) VALUES (?, ?, ?)")
         ->execute([$job_id, $user_id, $comm_content]);

    write_log($conn, $user_id, 'REJECT_JOB', "Meminta meeting ulang untuk '{$job['title']}': $notes");

    // Kirim notifikasi in-app & email ke creator dan seluruh user yang di-tag
    $recipients = get_job_recipients($conn, $job_id, $user_id);
    $approver_name = $_SESSION['name'] ?? 'Pimpinan';

    foreach ($recipients as $rec) {
        try {
            $conn->prepare("INSERT INTO bukti_notifications (user_id, actor_id, job_id, type) VALUES (?, ?, ?, 'approval_rejected')")
                 ->execute([$rec['id'], $user_id, $job_id]);
        } catch (Exception $e) {}

        if (!empty($rec['email'])) {
            @sendApprovalResultEmail($rec['email'], $rec['name'], $approver_name, $job['title'], 'need_meeting', $notes, $job_id);
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'Status berhasil diubah menjadi Meeting Ulang']);
    exit;
}

if ($action == 'comment') {
    $job_id = (int)$_POST['job_id'];
    $content = trim($_POST['content']);
    $conn->prepare("INSERT INTO bukti_comments (job_id, user_id, content) VALUES (?, ?, ?)")->execute([$job_id, $user_id, $content]);
    write_log($conn, $user_id, 'COMMENT', 'Komentar pada job ' . $job_id);

    // Fetch job title & creator
    $jt = $conn->prepare("SELECT title, user_id FROM bukti_jobs WHERE id = ?");
    $jt->execute([$job_id]);
    $jdata = $jt->fetch(PDO::FETCH_ASSOC);
    $job_title = $jdata['title'] ?? 'Pekerjaan #' . $job_id;

    // Notify tagged users in comment
    notify_tagged_users($conn, $user_id, $job_id, $job_title, $content, 'comment');

    // Notify job creator if not actor
    if ($jdata && $jdata['user_id'] != $user_id) {
        try {
            $conn->prepare("INSERT INTO bukti_notifications (user_id, actor_id, job_id, type) VALUES (?, ?, ?, 'comment')")
                 ->execute([$jdata['user_id'], $user_id, $job_id]);
        } catch (Exception $e) {}
    }

    echo json_encode(['status' => 'success']);
    exit;
}

if ($action == 'edit_comment') {
    $id = $_POST['comment_id'];
    $content = $_POST['content'];
    
    $check = $conn->prepare("SELECT user_id FROM bukti_comments WHERE id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() == $user_id) {
        $conn->prepare("UPDATE bukti_comments SET content = ? WHERE id = ?")->execute([$content, $id]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    }
    exit;
}

if ($action == 'delete_comment') {
    $id = $_POST['comment_id'];
    $check = $conn->prepare("SELECT user_id FROM bukti_comments WHERE id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() == $user_id) {
        $conn->prepare("UPDATE bukti_comments SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    }
    exit;
}

if ($action == 'delete_post') {
    $job_id = (int)$_POST['job_id'];
    
    // Cek kepemilikan
    $check = $conn->prepare("SELECT user_id, title FROM bukti_jobs WHERE id = ?");
    $check->execute([$job_id]);
    $data = $check->fetch();

    if ($data && ($data['user_id'] == $user_id || $_SESSION['role'] === 'admin')) {
        // Soft Delete
        $stmt = $conn->prepare("UPDATE bukti_jobs SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$job_id]);
        
        write_log($conn, $user_id, 'DELETE_JOB', "Menghapus pekerjaan: " . $data['title']);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    }
    exit;
}

if ($action == 'like') {
    $job_id = $_POST['job_id'];
    
    $check = $conn->prepare("SELECT id FROM bukti_reactions WHERE job_id = ? AND user_id = ?");
    $check->execute([$job_id, $user_id]);
    $liked = false;
    
    if ($check->fetchColumn()) {
        $conn->prepare("DELETE FROM bukti_reactions WHERE job_id = ? AND user_id = ?")->execute([$job_id, $user_id]);
    } else {
        $conn->prepare("INSERT INTO bukti_reactions (job_id, user_id) VALUES (?, ?)")->execute([$job_id, $user_id]);
        $liked = true;
    }
    
    $cnt = $conn->prepare("SELECT COUNT(*) FROM bukti_reactions WHERE job_id = ?");
    $cnt->execute([$job_id]);
    
    echo json_encode(['status' => 'success', 'liked' => $liked, 'count' => $cnt->fetchColumn()]);
    exit;
}

// Delete Attachment action
if ($action == 'delete_attachment') {
    $att_id = (int)$_POST['attachment_id'];
    
    // Check ownership of the attachment via job owner
    $check = $conn->prepare("SELECT a.file_path, j.user_id FROM bukti_job_attachments a JOIN bukti_jobs j ON a.job_id = j.id WHERE a.id = ?");
    $check->execute([$att_id]);
    $data = $check->fetch();
    
    if ($data && ($data['user_id'] == $user_id || $_SESSION['role'] === 'admin')) {
        // Physical file removal
        $filepath = 'assets/uploads/bukti/' . $data['file_path'];
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
        
        $conn->prepare("DELETE FROM bukti_job_attachments WHERE id = ?")->execute([$att_id]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    }
    exit;
}

// Fetch Logic (Detail, Search Users) sama seperti sebelumnya...
if ($action == 'fetch_detail') {
    $job_id = $_POST['job_id'];
    
    // Track view (INSERT IGNORE = skip if already viewed)
    $conn->prepare("INSERT IGNORE INTO bukti_post_views (job_id, user_id) VALUES (?, ?)")
         ->execute([$job_id, $user_id]);
         
    // Mark notifications as read for this job and user
    try {
        $conn->prepare("UPDATE bukti_notifications SET is_read = 1 WHERE job_id = ? AND user_id = ? AND is_read = 0")
             ->execute([$job_id, $user_id]);
    } catch(Exception $e) {}
    
    $stmt = $conn->prepare("SELECT j.*, u.name, u.nickname, u.avatar, u.jabatan,
        (SELECT name FROM users WHERE id = j.approval_by) as approver_name,
        (SELECT COUNT(*) FROM bukti_reactions WHERE job_id = j.id) as like_count,
        (SELECT COUNT(*) FROM bukti_reactions WHERE job_id = j.id AND user_id = ?) as is_liked
        FROM bukti_jobs j JOIN users u ON j.user_id = u.id WHERE j.id = ?");
    $stmt->execute([$user_id, $job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    $job['avatar_url'] = $job['avatar'] && file_exists("assets/img/avatars/".$job['avatar']) ? "assets/img/avatars/".$job['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($job['name']);
    $job['date_fmt'] = tgl_indo($job['created_at']);
    $job['approval_at_fmt'] = !empty($job['approval_at']) ? tgl_indo($job['approval_at']) : null;
    
    $prog = $conn->prepare("SELECT p.*, u.name FROM bukti_job_progress p JOIN users u ON p.user_id = u.id WHERE job_id = ? ORDER BY created_at DESC");
    $prog->execute([$job_id]);
    $history = $prog->fetchAll(PDO::FETCH_ASSOC);
    foreach($history as &$h) {
        $h['date'] = tgl_indo($h['created_at']);
        
        // Fetch attachments associated with this specific progress update
        $patts = $conn->prepare("SELECT * FROM bukti_job_attachments WHERE progress_id = ?");
        $patts->execute([$h['id']]);
        $h['attachments'] = $patts->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Comments only active
    $comments = $conn->prepare("SELECT c.*, u.name, u.avatar FROM bukti_comments c JOIN users u ON c.user_id = u.id WHERE job_id = ? AND c.deleted_at IS NULL ORDER BY created_at ASC");
    $comments->execute([$job_id]);
    $com_res = $comments->fetchAll(PDO::FETCH_ASSOC);
    foreach($com_res as &$c) {
        $c['avatar'] = $c['avatar'] && file_exists("assets/img/avatars/".$c['avatar']) ? "assets/img/avatars/".$c['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($c['name']);
        $c['date'] = tgl_indo($c['created_at']);
        $c['is_mine'] = ($c['user_id'] == $user_id);
    }

    // ONLY fetch attachments that belong to the main job creation (progress_id IS NULL)
    $att = $conn->prepare("SELECT * FROM bukti_job_attachments WHERE job_id = ? AND progress_id IS NULL");
    $att->execute([$job_id]);
    
    // Fetch viewers
    $viewers_stmt = $conn->prepare("SELECT u.name, u.avatar, u.nickname, v.viewed_at 
        FROM bukti_post_views v JOIN users u ON v.user_id = u.id 
        WHERE v.job_id = ? ORDER BY v.viewed_at DESC LIMIT 20");
    $viewers_stmt->execute([$job_id]);
    $viewers = $viewers_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($viewers as &$v) {
        $v['avatar'] = $v['avatar'] && file_exists("assets/img/avatars/".$v['avatar']) ? "assets/img/avatars/".$v['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($v['name']);
        $v['viewed_at_fmt'] = tgl_indo($v['viewed_at'], 'j M Y H:i');
    }
    $view_count = $conn->prepare("SELECT COUNT(*) FROM bukti_post_views WHERE job_id = ?");
    $view_count->execute([$job_id]);
    
    // Cek apakah user di-tag dalam deskripsi post
    $me = $conn->prepare("SELECT name, nickname FROM users WHERE id = ?");
    $me->execute([$user_id]);
    $me_data = $me->fetch(PDO::FETCH_ASSOC);
    $my_tag    = '@' . str_replace(' ', '', $me_data['name']);
    $my_nick_t = '@' . ($me_data['nickname'] ?: str_replace(' ', '', $me_data['name']));
    $is_tagged = (stripos($job['description'], $my_tag) !== false ||
                  stripos($job['description'], $my_nick_t) !== false);

    echo json_encode([
        'status'     => 'success',
        'job'        => $job,
        'history'    => $history,
        'comments'   => $com_res,
        'attachments'=> $att->fetchAll(PDO::FETCH_ASSOC),
        'is_owner'   => ($job['user_id'] == $user_id),
        'is_tagged'  => $is_tagged,
        'is_approver'=> (($_SESSION['role'] ?? '') === 'admin' || $user_id == 1),
        'viewers'    => $viewers,
        'view_count' => (int)$view_count->fetchColumn()
    ]);
    exit;
}

if ($action == 'search_users') {
    $term = $_GET['term'] . '%';
    $stmt = $conn->prepare("SELECT name, nickname, avatar FROM users WHERE name LIKE ? OR nickname LIKE ? LIMIT 5");
    $stmt->execute([$term, $term]);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($res as &$r) {
        $r['avatar'] = $r['avatar'] && file_exists("assets/img/avatars/".$r['avatar']) ? "assets/img/avatars/".$r['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($r['name']);
        $r['nickname'] = $r['nickname'] ?: str_replace(' ', '', $r['name']);
    }
    echo json_encode($res);
    exit;
}
?>