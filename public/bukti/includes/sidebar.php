<nav class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="bi bi-check2-square"></i>
        </div>
        <span>Bukti</span>
    </div>
    <div class="flex-grow-1">
        <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>"><i class="bi bi-house-door"></i> Beranda</a>
        <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>"><i class="bi bi-person"></i> Profil Saya</a>
        
        <?php
        // Fetch unread notifications count
        $unread_count = 0;
        try {
            $stmt_unread = $conn->prepare("SELECT COUNT(*) FROM bukti_notifications WHERE user_id = ? AND is_read = 0 AND deleted_at IS NULL");
            $stmt_unread->execute([$_SESSION['user_id']]);
            $unread_count = (int)$stmt_unread->fetchColumn();
        } catch(Exception $e) {}
        ?>
        <a href="notifikasi.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifikasi.php' ? 'active' : ''; ?> d-flex justify-content-between align-items-center">
            <span><i class="bi bi-bell"></i> Notifikasi</span>
            <?php if ($unread_count > 0): ?>
                <span class="badge rounded-pill bg-danger" style="font-size: 0.68rem; padding: 4px 8px;"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="log.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'log.php' ? 'active' : ''; ?>"><i class="bi bi-clock-history"></i> Riwayat Log</a>
        
        <div class="section-label">Filter Cepat</div>
        <a href="index.php?status=todo" class="nav-link"><i class="bi bi-circle"></i> Belum Mulai</a>
        <a href="index.php?status=in_progress" class="nav-link"><i class="bi bi-play-circle"></i> Dalam Proses</a>
        <a href="index.php?status=done" class="nav-link"><i class="bi bi-check-circle"></i> Selesai</a>
    </div>
    <a href="../index.php" class="btn-center"><i class="bi bi-grid"></i> Center</a>
</nav>
<div class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-lg-none" style="z-index: 999; display: none;" onclick="document.querySelector('.sidebar').classList.remove('show')" id="sidebarOverlay"></div>
<script>
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.target.classList.contains('show')) overlay.style.display = 'block';
            else overlay.style.display = 'none';
        });
    });
    observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
</script>