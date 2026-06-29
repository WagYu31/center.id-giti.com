<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Data Karyawan Teladan</h5>
        <span class="badge bg-primary rounded-pill"><?= count($data['employees']) ?> Orang</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Nama</th>
                        <th>Divisi</th>
                        <th>Jabatan</th>
                        <th>Kontak</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['employees'] as $emp): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($emp['name']) ?></td>
                        <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill"><?= htmlspecialchars($emp['division']) ?></span></td>
                        <td><?= htmlspecialchars($emp['jabatan']) ?></td>
                        <td>
                            <?php 
                                $telp = $emp['telp'];
                                if(substr($telp, 0, 1) == '0') $telp = '62'.substr($telp, 1);
                            ?>
                            <a href="https://wa.me/<?= $telp ?>" target="_blank" class="btn btn-success btn-sm rounded-pill text-white">
                                <i class="fab fa-whatsapp me-1"></i> Hubungi
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>