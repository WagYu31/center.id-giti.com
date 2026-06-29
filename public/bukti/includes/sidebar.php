<nav class="sidebar">
    <div class="sidebar-brand">
        <div class="bg-white text-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
            <i class="bi bi-check2-square"></i>
        </div>
        <span>Bukti</span>
    </div>
    <div class="flex-grow-1">
        <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>"><i class="bi bi-house-door"></i> Beranda</a>
        <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>"><i class="bi bi-person"></i> Profil Saya</a>
        <a href="notifikasi.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifikasi.php' ? 'active' : ''; ?>"><i class="bi bi-bell"></i> Notifikasi</a>
        <a href="log.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'log.php' ? 'active' : ''; ?>"><i class="bi bi-clock-history"></i> Riwayat Log</a>
        
        <div class="px-4 mt-4 mb-2 text-uppercase small fw-bold text-muted opacity-50" style="font-size: 0.7rem;">Filter Cepat</div>
        <a href="index.php?status=todo" class="nav-link"><i class="bi bi-circle"></i> Belum Mulai</a>
        <a href="index.php?status=in_progress" class="nav-link"><i class="bi bi-play-circle"></i> Dalam Proses</a>
        <a href="index.php?status=done" class="nav-link"><i class="bi bi-check-circle"></i> Selesai</a>
    </div>
    <div class="px-4 pb-4">
        <a href="../index.php" class="btn btn-outline-light w-100 border-secondary text-secondary btn-sm"><i class="bi bi-grid me-2"></i> Center</a>
    </div>
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