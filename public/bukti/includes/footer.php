<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
// Global Tagging System
function setupMentions(selector) {
    $(document).on('input', selector, function() {
        let val = $(this).val();
        let cursorPos = this.selectionStart;
        let lastAt = val.lastIndexOf('@', cursorPos - 1);
        
        if (lastAt !== -1) {
            let query = val.substring(lastAt + 1, cursorPos);
            if (!query.includes(' ')) {
                // Posisi dropdown
                // Simplifikasi: tempel di bawah input
                let offset = $(this).offset();
                let height = $(this).outerHeight();
                
                if ($('#mention-box').length === 0) $('body').append('<div id="mention-box" class="mention-list"></div>');
                
                $('#mention-box').css({
                    top: offset.top + height + 'px', 
                    left: offset.left + 'px',
                    display: 'block'
                });
                
                $.get('ajax_action.php', {action: 'search_users', term: query}, function(res) {
                    let html = '';
                    if(res.length > 0) {
                        res.forEach(u => {
                            html += `<div class="mention-item" onclick="insertTag('${u.nickname}', ${lastAt}, ${cursorPos}, '${selector}')">
                                <img src="${u.avatar}" class="mention-avatar"> 
                                <div><div class="fw-bold">${u.name}</div><small class="text-muted">@${u.nickname}</small></div>
                            </div>`;
                        });
                    } else {
                        html = '<div class="p-2 small text-muted text-center">Tidak ditemukan</div>';
                    }
                    $('#mention-box').html(html);
                }, 'json');
                return;
            }
        }
        $('#mention-box').hide();
    });
    
    // Hide click outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#mention-box').length) $('#mention-box').hide();
    });
}

function insertTag(name, start, end, selector) {
    let input = $(selector);
    let val = input.val();
    let text = val.substring(0, start) + '@' + name + ' ' + val.substring(end);
    input.val(text).focus();
    $('#mention-box').hide();
}

$(document).ready(() => {
    setupMentions('#inpDesc');
    setupMentions('#d-input');
    setupMentions('#create-desc'); // ID baru untuk create modal
});
</script>
</body>
</html>