<?php if (isset($modal_data) && $modal_data): ?>
<div class="modal fade" id="globalAlertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg text-center" style="border-radius: 16px;">
            <div class="modal-body p-5">
                <?php
                // Tentukan warna berdasarkan tipe alert (success, warning, danger)
                $type = $modal_data[0];
                $color = '';
                if ($type === 'success') $color = '#198754';
                elseif ($type === 'warning') $color = '#ffc107';
                elseif ($type === 'danger') $color = '#dc3545';
                else $color = '#0d6efd'; // info
                
                $title = 'Informasi';
                if ($type === 'success') $title = 'Berhasil!';
                elseif ($type === 'warning') $title = 'Perhatian';
                elseif ($type === 'danger') $title = 'Gagal!';
                ?>
                <!-- Ikon Besar Animasi -->
                <div class="mb-4">
                    <i class="bi <?= htmlspecialchars($modal_data[1]) ?>" style="font-size: 5rem; color: <?= $color ?>;"></i>
                </div>
                
                <h4 class="mb-3 fw-bold text-dark"><?= $title ?></h4>
                <p class="text-muted mb-4" style="font-size: 1.1rem;"><?= htmlspecialchars($modal_data[2]) ?></p>
                
                <button type="button" class="btn btn-<?= $type ?> px-4 py-2" data-bs-dismiss="modal" style="border-radius: 8px; font-weight: 500;">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Animasi pop in untuk modal */
#globalAlertModal .modal-content {
    transform: scale(0.8);
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
#globalAlertModal.show .modal-content {
    transform: scale(1);
}
</style>

<script>
// Tunggu DOM load agar Bootstrap JS bisa digunakan
document.addEventListener("DOMContentLoaded", function() {
    var modalEl = document.getElementById('globalAlertModal');
    if (modalEl) {
        var alertModal = new bootstrap.Modal(modalEl);
        alertModal.show();
    }
});
</script>
<?php endif; ?>
