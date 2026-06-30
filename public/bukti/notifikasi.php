<?php 
require_once 'includes/db.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

$user_id = $_SESSION['user_id'];

$stmt_user = $conn->prepare("SELECT nickname, name FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$curr_user = $stmt_user->fetch();
$nickname = $curr_user['nickname'] ? $curr_user['nickname'] : str_replace(' ', '', $curr_user['name']);
$tag_pattern = "%@" . $nickname . "%";

$limit = 15; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$sql_count = "
    SELECT 
        (SELECT COUNT(*) FROM bukti_jobs WHERE description LIKE ? AND deleted_at IS NULL) + 
        (SELECT COUNT(*) FROM bukti_comments WHERE content LIKE ? AND deleted_at IS NULL) 
    as total";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->execute([$tag_pattern, $tag_pattern]);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql = "
    SELECT id as item_id, id as job_id, user_id as actor_id, 'job' as type, description as content, created_at 
    FROM bukti_jobs 
    WHERE description LIKE ? AND deleted_at IS NULL
    
    UNION ALL
    
    SELECT id as item_id, job_id, user_id as actor_id, 'comment' as type, content, created_at 
    FROM bukti_comments 
    WHERE content LIKE ? AND deleted_at IS NULL
    
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sql);
$stmt->execute([$tag_pattern, $tag_pattern]);
$mentions = $stmt->fetchAll(PDO::FETCH_ASSOC);

function time_ago_custom($datetime) { return tgl_indo($datetime); }
function format_text_preview($text) {
    return strlen($text) > 100 ? substr(strip_tags($text), 0, 100) . "..." : strip_tags($text);
}
?>

<div class="main-wrapper">
    <div class="content-area" style="max-width: 800px;">
        
        <h4 class="fw-bold mb-4">Mentions & Tag</h4>
        <p class="text-muted mb-4">Daftar pekerjaan dan komentar di mana Anda (@<?php echo htmlspecialchars($nickname); ?>) ditandai.</p>

        <div class="card-custom p-0 overflow-hidden">
            <?php if(count($mentions) > 0): ?>
                <?php foreach($mentions as $m): 
                    $stmt_actor = $conn->prepare("SELECT name, avatar FROM users WHERE id = ?");
                    $stmt_actor->execute([$m['actor_id']]);
                    $actor = $stmt_actor->fetch();
                    
                    $av = $actor['avatar'] && file_exists("assets/img/avatars/".$actor['avatar']) ? "assets/img/avatars/".$actor['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($actor['name']);
                    
                    if($m['type'] == 'job') {
                        $context = "menandai Anda dalam pekerjaannya";
                        $icon = '<i class="bi bi-briefcase-fill text-primary"></i>';
                    } else {
                        $context = "menandai Anda dalam komentar";
                        $icon = '<i class="bi bi-chat-dots-fill text-success"></i>';
                    }
                ?>
                <div class="p-3 border-bottom d-flex gap-3 align-items-start bg-white cursor-pointer hover-bg-light" onclick="openDetail(<?php echo $m['job_id']; ?>)">
                    <img src="<?php echo $av; ?>" class="rounded-circle border" width="45" height="45" style="object-fit:cover;">
                    <div class="flex-grow-1">
                        <div class="mb-1">
                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($actor['name']); ?></span> 
                            <span class="text-secondary small"><?php echo $context; ?></span>
                        </div>
                        <div class="text-dark bg-light p-2 rounded small border d-inline-block mb-1">
                            <?php echo $icon; ?> <span class="fst-italic">"<?php echo format_text_preview($m['content']); ?>"</span>
                        </div>
                        <div class="d-block">
                            <small class="text-muted"><i class="bi bi-clock me-1"></i> <?php echo time_ago_custom($m['created_at']); ?></small>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-light rounded-circle"><i class="bi bi-chevron-right"></i></button>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-at display-4 mb-3 d-block"></i>
                    Belum ada yang menandai Anda.
                </div>
            <?php endif; ?>
        </div>

        <?php if($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                    <li class="page-item <?php echo $page==$i?'active':''; ?>">
                        <a class="page-link border-0 rounded-circle mx-1" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>

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
                            <button class="btn btn-sm btn-light border rounded-pill fw-bold text-muted" id="d-like-btn" onclick="toggleLikeInModal()"><i class="bi bi-hand-thumbs-up-fill"></i> <span id="d-like-count">0</span></button>
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
                        <label class="btn btn-sm btn-light w-100 border text-start rounded-pill"><i class="bi bi-paperclip"></i> Bukti/File <input type="file" id="progressFileInput" name="files[]" multiple hidden></label>
                        <div id="progress-preview-container" class="preview-grid mt-2" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px;"></div>
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
let progressFiles = []; 

function toggleLoading(show) {} 

$('#progressFileInput').on('change', function(e) {
    const files = Array.from(e.target.files);
    progressFiles = progressFiles.concat(files);
    updatePreviews();
    $(this).val('');
});

function updatePreviews() {
    const container = $('#progress-preview-container'); container.empty();
    progressFiles.forEach((file, index) => {
        let pc = '';
        if (file.type.startsWith('image/')) pc = `<img src="${URL.createObjectURL(file)}" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">`;
        else pc = `<div style="width:100%;height:100%;background:#f8f9fa;display:flex;align-items:center;justify-content:center;border-radius:8px;"><i class="bi bi-file-earmark-text"></i></div>`;
        container.append(`<div style="position:relative;width:100%;padding-top:100%;"><div style="position:absolute;top:0;left:0;width:100%;height:100%;">${pc}<button type="button" onclick="removeFile(${index})" style="position:absolute;top:2px;right:2px;background:rgba(0,0,0,0.6);color:white;border:none;border-radius:50%;width:20px;height:20px;font-size:12px;display:flex;align-items:center;justify-content:center;">&times;</button></div></div>`);
    });
}

function removeFile(index) {
    progressFiles.splice(index, 1);
    updatePreviews();
}

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

$(document).on('keydown', '#d-input', function(e){
    if(e.key === 'Enter' || e.keyCode === 13) { e.preventDefault(); sendComment(); }
});

function delComment(id){ if(confirm('Hapus komentar?')) $.post('ajax_action.php', {action:'delete_comment', comment_id:id}, function(){ openDetail(curJob); }); }
function editComment(id, old){ let n=prompt("Edit:", old); if(n!==null && n.trim()!=="") $.post('ajax_action.php', {action:'edit_comment', comment_id:id, content:n}, function(){ openDetail(curJob); }); }

function saveProgress(){
    let fd = new FormData($('#formProgress')[0]); fd.append('action', 'update_progress');
    fd.delete('files[]'); progressFiles.forEach((f) => { fd.append('files[]', f); });
    $.ajax({url:'ajax_action.php', type:'POST', data:fd, contentType:false, processData:false, success:function(){ location.reload(); }});
}

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
    updatePreviews();
    new bootstrap.Modal('#progressModal').show(); 
}

function formatText(t){ return t?t.replace(/@(\w+)/g, '<span class="text-primary fw-bold">@$1</span>').replace(/\n/g, '<br>'):''; }
</script>
<style>
.custom-scroll::-webkit-scrollbar { width: 6px; }
.custom-scroll::-webkit-scrollbar-track { background: transparent; }
.custom-scroll::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,0.1); border-radius: 10px; }
.custom-scroll::-webkit-scrollbar-thumb:hover { background-color: rgba(0,0,0,0.2); }
</style>