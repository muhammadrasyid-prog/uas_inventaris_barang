<?php
require_once '../includes/config.php';
requireAdmin();

// ===== PROSES UPDATE PENGATURAN =====
if (isset($_POST['update'])) {
    $max_pinjam = (int) $_POST['max_pinjam_per_user'];
    $max_hari = (int) $_POST['max_hari_pinjam'];
    
    if ($max_pinjam < 1) $max_pinjam = 1;
    if ($max_hari < 1) $max_hari = 1;
    
    $query = "UPDATE pengaturan SET 
              max_pinjam_per_user = $max_pinjam,
              max_hari_pinjam = $max_hari
              WHERE id = 1";
    
    if (mysqli_query($conn, $query)) {
        header("Location: pengaturan.php?status=sukses");
        exit();
    } else {
        $error = "Gagal update: " . mysqli_error($conn);
    }
}

// Ambil data pengaturan
$setting = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1"));

$page_title = "Pengaturan Sistem";
$current_page = basename(__FILE__);
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="p-4">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="fw-bold mb-0" style="color:#002645;">
                <i class="bi bi-gear me-2"></i>Pengaturan Sistem Peminjaman
            </h5>
        </div>
        <div class="card-body">
            
            <!-- Alert Modal -->
            <?php
            $modal_data = null;
            if (isset($_GET['status']) && $_GET['status'] === 'sukses') {
                $modal_data = ['success', 'bi-check-circle', 'Pengaturan berhasil disimpan!'];
            } elseif (isset($error)) {
                $modal_data = ['danger', 'bi-exclamation-triangle', $error];
            }
            ?>
            <?php include_once '../includes/alert_modal.php'; ?>

            <form method="POST" class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-box-seam me-1"></i> Maksimal Peminjaman per User
                    </label>
                    <div class="input-group">
                        <input type="number" name="max_pinjam_per_user" 
                               class="form-control" value="<?= $setting['max_pinjam_per_user'] ?>" 
                               min="1" max="20" required>
                        <span class="input-group-text">barang</span>
                    </div>
                    <small class="text-muted">Berapa banyak barang yang bisa dipinjam 1 user secara bersamaan?</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-calendar me-1"></i> Maksimal Hari Peminjaman
                    </label>
                    <div class="input-group">
                        <input type="number" name="max_hari_pinjam" 
                               class="form-control" value="<?= $setting['max_hari_pinjam'] ?>" 
                               min="1" max="30" required>
                        <span class="input-group-text">hari</span>
                    </div>
                    <small class="text-muted">Berapa lama maksimal barang bisa dipinjam?</small>
                </div>

                <div class="col-12">
                    <hr>
                    <button type="submit" name="update" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Simpan Pengaturan
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                </div>
            </form>

            <div class="alert alert-info mt-4">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Informasi:</strong> Pengaturan ini akan langsung berlaku untuk semua user peminjam.
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>