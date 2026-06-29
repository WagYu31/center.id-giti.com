<?php
require_once 'includes/db.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function write_log($conn, $user_id, $act, $desc) {
    $conn->prepare("INSERT INTO bukti_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)")
         ->execute([$user_id, $act, $desc, $_SERVER['REMOTE_ADDR']]);
}

// --- FUNGSI HELPER UPLOAD (FIX MASALAH 1 & 2) ---
function process_uploads($conn, $job_id, $files) {
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
                    $conn->prepare("INSERT INTO bukti_job_attachments (job_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?)")
                         ->execute([$job_id, $name, $new_name, $type]);
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

        // Tagging Notif
        preg_match_all('/@(\w+)/', $_POST['description'], $matches);
        if($matches[1]) {
            foreach($matches[1] as $nick) {
                $u = $conn->prepare("SELECT id FROM users WHERE nickname = ? LIMIT 1");
                $u->execute([$nick]);
                $tid = $u->fetchColumn();
                if($tid && $tid != $user_id) $conn->prepare("INSERT INTO bukti_notifications (user_id, actor_id, job_id, type) VALUES (?, ?, ?, 'mention')")->execute([$tid, $user_id, $job_id]);
            }
        }

        write_log($conn, $user_id, 'CREATE_JOB', "Membuat pekerjaan: " . $_POST['title']);
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
    
    $old = $conn->prepare("SELECT status, title FROM bukti_jobs WHERE id = ?");
    $old->execute([$job_id]);
    $job = $old->fetch();
    
    $conn->prepare("INSERT INTO bukti_job_progress (job_id, user_id, status_before, status_after, notes) VALUES (?, ?, ?, ?, ?)")
         ->execute([$job_id, $user_id, $job['status'], $status, $notes]);
         
    $conn->prepare("UPDATE bukti_jobs SET status = ? WHERE id = ?")->execute([$status, $job_id]);
    
    // Handle Uploads saat Progress (Fix Masalah 2)
    if (isset($_FILES['files'])) {
        process_uploads($conn, $job_id, $_FILES['files']);
    }

    write_log($conn, $user_id, 'UPDATE_PROGRESS', "Update status '{$job['title']}' ke $status");
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action == 'comment') {
    $conn->prepare("INSERT INTO bukti_comments (job_id, user_id, content) VALUES (?, ?, ?)")->execute([$_POST['job_id'], $user_id, $_POST['content']]);
    write_log($conn, $user_id, 'COMMENT', 'Komentar pada job ' . $_POST['job_id']);
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

    if ($data && $data['user_id'] == $user_id) {
        // Soft Delete
        $stmt = $conn->prepare("UPDATE bukti_jobs SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$job_id]);
        
        write_log($conn, $user_id, 'DELETE_JOB', "Menghapus pekerjaan: " . $data['title']);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus. Akses ditolak.']);
    }
    exit;
}

if ($action == 'edit_post') {
    $job_id = (int)$_POST['job_id'];
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    
    $check = $conn->prepare("SELECT user_id FROM bukti_jobs WHERE id = ?");
    $check->execute([$job_id]);
    
    if ($check->fetchColumn() == $user_id) {
        $stmt = $conn->prepare("UPDATE bukti_jobs SET title = ?, description = ?, is_edited = 1, status = ?, start_date = ?, end_date = ? WHERE id = ?");
        $stmt->execute([$title, $desc, $_POST['status'], $_POST['start_date'], $_POST['end_date'], $job_id]);
        
        if (isset($_FILES['files'])) process_uploads($conn, $job_id, $_FILES['files']);

        write_log($conn, $user_id, 'EDIT_JOB', "Edit pekerjaan ID: " . $job_id);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    }
    exit;
}

if ($action == 'like') {
    $job_id = $_POST['job_id'];
    $check = $conn->prepare("SELECT id FROM bukti_reactions WHERE job_id = ? AND user_id = ? AND type = 'like'");
    $check->execute([$job_id, $user_id]);
    if ($check->rowCount() > 0) {
        $conn->prepare("DELETE FROM bukti_reactions WHERE job_id = ? AND user_id = ?")->execute([$job_id, $user_id]);
        $liked = false;
    } else {
        $conn->prepare("INSERT INTO bukti_reactions (job_id, user_id, type) VALUES (?, ?, 'like')")->execute([$job_id, $user_id]);
        $liked = true;
    }
    $cnt = $conn->prepare("SELECT COUNT(*) FROM bukti_reactions WHERE job_id = ?");
    $cnt->execute([$job_id]);
    echo json_encode(['status' => 'success', 'liked' => $liked, 'count' => $cnt->fetchColumn()]);
    exit;
}

// Fetch Logic (Detail, Search Users) sama seperti sebelumnya...
if ($action == 'fetch_detail') {
    $job_id = $_POST['job_id'];
    
    // Track view (INSERT IGNORE = skip if already viewed)
    $conn->prepare("INSERT IGNORE INTO bukti_post_views (job_id, user_id) VALUES (?, ?)")
         ->execute([$job_id, $user_id]);
    
    $stmt = $conn->prepare("SELECT j.*, u.name, u.avatar, u.jabatan,
        (SELECT COUNT(*) FROM bukti_reactions WHERE job_id = j.id) as like_count,
        (SELECT COUNT(*) FROM bukti_reactions WHERE job_id = j.id AND user_id = ?) as is_liked
        FROM bukti_jobs j JOIN users u ON j.user_id = u.id WHERE j.id = ?");
    $stmt->execute([$user_id, $job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    $job['avatar_url'] = $job['avatar'] && file_exists("assets/img/avatars/".$job['avatar']) ? "assets/img/avatars/".$job['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($job['name']);
    $job['date_fmt'] = tgl_indo($job['created_at']);
    
    $prog = $conn->prepare("SELECT p.*, u.name FROM bukti_job_progress p JOIN users u ON p.user_id = u.id WHERE job_id = ? ORDER BY created_at DESC");
    $prog->execute([$job_id]);
    $history = $prog->fetchAll(PDO::FETCH_ASSOC);
    foreach($history as &$h) $h['date'] = tgl_indo($h['created_at']);
    
    // Comments only active
    $comments = $conn->prepare("SELECT c.*, u.name, u.avatar FROM bukti_comments c JOIN users u ON c.user_id = u.id WHERE job_id = ? AND c.deleted_at IS NULL ORDER BY created_at ASC");
    $comments->execute([$job_id]);
    $com_res = $comments->fetchAll(PDO::FETCH_ASSOC);
    foreach($com_res as &$c) {
        $c['avatar'] = $c['avatar'] && file_exists("assets/img/avatars/".$c['avatar']) ? "assets/img/avatars/".$c['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($c['name']);
        $c['date'] = tgl_indo($c['created_at']);
        $c['is_mine'] = ($c['user_id'] == $user_id);
    }

    $att = $conn->prepare("SELECT * FROM bukti_job_attachments WHERE job_id = ?");
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
    
    echo json_encode([
        'status' => 'success',
        'job' => $job,
        'history' => $history,
        'comments' => $com_res,
        'attachments' => $att->fetchAll(PDO::FETCH_ASSOC),
        'is_owner' => ($job['user_id'] == $user_id),
        'viewers' => $viewers,
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