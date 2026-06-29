<div class="row mb-4">
    <div class="col-12">
        <h4 class="mb-3">Halo, <?= htmlspecialchars($data['user']['name']) ?>! 👋</h4>
        <p class="text-muted">Selamat datang di Center Loewix. Pilih aplikasi di bawah untuk melanjutkan.</p>
    </div>
</div>

<div class="row g-4">
    <?php 
    $u = $data['user'];
    $token = $data['sso_token'];
    
    $cards = [
        ['key' => 'bukti', 'title' => 'Bukti', 'desc' => 'Kerja Untuk Jajan', 'icon' => 'fa-file-invoice', 'link' => 'https://bukti.grav-tech.com/sso_login.php?token='.$token, 'color' => 'primary'],
        ['key' => 'sales', 'title' => 'Sales', 'desc' => 'Semoga Capai Target', 'icon' => 'fa-chart-line', 'link' => 'https://sales.grav-tech.com/sso_login.php?token='.$token, 'color' => 'success'],
        ['key' => 'quotation', 'title' => 'Quotation', 'desc' => 'Buat Penawaran', 'icon' => 'fa-file-contract', 'link' => 'https://quo.grav-tech.com/sso_login.php?token='.$token, 'color' => 'info'],
        ['key' => 'service', 'title' => 'Service', 'desc' => 'Penyihir CCTV', 'icon' => 'fa-tools', 'link' => 'https://service.grav-tech.com/sso_login.php?token='.$token, 'color' => 'warning'],
        ['key' => 'teknisi', 'title' => 'Teknisi', 'desc' => 'Dukun CCTV', 'icon' => 'fa-wrench', 'link' => 'https://jadwal.grav-tech.com/sso_login.php?token='.$token, 'color' => 'danger'],
    ];

    foreach($cards as $card): 
        if(isset($u[$card['key']]) && $u[$card['key']] == 'Y'):
    ?>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm hover-card transition-card">
            <div class="card-body text-center">
                <div class="icon-box bg-light text-<?= $card['color'] ?> rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                    <i class="fas <?= $card['icon'] ?> fa-2x"></i>
                </div>
                <h5 class="card-title"><?= $card['title'] ?></h5>
                <p class="card-text text-muted small"><?= $card['desc'] ?></p>
                <a href="<?= $card['link'] ?>" target="_blank" class="btn btn-outline-<?= $card['color'] ?> btn-sm px-4 rounded-pill">Akses</a>
            </div>
        </div>
    </div>
    <?php endif; endforeach; ?>

    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm transition-card">
            <div class="card-body text-center">
                <div class="icon-box bg-light text-dark rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                    <i class="fas fa-users fa-2x"></i>
                </div>
                <h5 class="card-title">Data Karyawan</h5>
                <p class="card-text text-muted small">Daftar Rakyat Teladan</p>
                <a href="<?= BASE_URL ?>/employee" class="btn btn-outline-dark btn-sm px-4 rounded-pill">Lihat</a>
            </div>
        </div>
    </div>
</div>