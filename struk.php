<?php
require_once 'includes/config.php';

// Validasi id harus integer
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    die("Error: ID Peminjaman tidak valid.");
}

// Query data menggunakan mysqli_query biasa
$query = "
    SELECT p.*, u.nama_lengkap, b.nama_barang, b.kode_barang 
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    JOIN barang b ON p.id_barang = b.id_barang
    WHERE p.id_peminjaman = $id
";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    die("Error: Data peminjaman tidak ditemukan.");
}

// Format No. Pinjam (contoh: #00123)
$no_pinjam = '#' . str_pad($data['id_peminjaman'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Peminjaman <?= $no_pinjam ?></title>
    <!-- Bootstrap CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Courier New', Courier, monospace;
            padding: 30px 10px;
            color: #000;
        }
        .struk-container {
            max-width: 400px;
            margin: 0 auto;
            background-color: #fff;
            padding: 25px;
            border: 2px solid #000;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }
        .struk-header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .struk-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 2px;
            letter-spacing: 1px;
        }
        .struk-subtitle {
            font-size: 11px;
            text-transform: uppercase;
        }
        .struk-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 13px;
        }
        .struk-label {
            color: #555;
            font-weight: bold;
        }
        .struk-val {
            text-align: right;
            max-width: 60%;
            word-wrap: break-word;
        }
        .struk-divider {
            border-top: 1px dashed #000;
            margin: 12px 0;
        }
        .signature-container {
            margin-top: 35px;
            display: flex;
            justify-content: flex-end;
        }
        .signature-box {
            text-align: center;
            width: 160px;
            font-size: 12px;
        }
        .signature-line {
            height: 60px;
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
        }
        .btn-container {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Media Print */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
                margin: 0;
            }
            .struk-container {
                border: none;
                box-shadow: none;
                padding: 10px;
            }
            .btn-container {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Container Struk -->
    <div class="struk-container" id="struk-box">
        <div class="struk-header">
            <div class="struk-title">SISTEM INVENTARIS BARANG</div>
            <div class="struk-subtitle">Bukti Peminjaman Resmi</div>
        </div>
        
        <div class="struk-row">
            <span class="struk-label">No. Pinjam:</span>
            <span class="struk-val fw-bold"><?= $no_pinjam ?></span>
        </div>
        
        <div class="struk-row">
            <span class="struk-label">Peminjam:</span>
            <span class="struk-val"><?= htmlspecialchars($data['nama_lengkap']) ?></span>
        </div>
        
        <div class="struk-divider"></div>
        
        <div class="struk-row">
            <span class="struk-label">Barang:</span>
            <span class="struk-val fw-bold"><?= htmlspecialchars($data['nama_barang']) ?></span>
        </div>
        
        <div class="struk-row">
            <span class="struk-label">Kode:</span>
            <span class="struk-val"><code><?= htmlspecialchars($data['kode_barang']) ?></code></span>
        </div>
        
        <div class="struk-row">
            <span class="struk-label">Jumlah:</span>
            <span class="struk-val"><?= (int)$data['jumlah'] ?> unit</span>
        </div>
        
        <div class="struk-divider"></div>
        
        <div class="struk-row">
            <span class="struk-label">Tgl Pinjam:</span>
            <span class="struk-val"><?= date('d M Y', strtotime($data['tanggal_pinjam'])) ?></span>
        </div>
        
        <div class="struk-row">
            <span class="struk-label">Tenggat Kembali:</span>
            <span class="struk-val"><?= date('d M Y', strtotime($data['tanggal_kembali_rencana'])) ?></span>
        </div>
        
        <div class="struk-row">
            <span class="struk-label">Status:</span>
            <span class="struk-val text-uppercase fw-bold text-primary"><?= htmlspecialchars($data['status']) ?></span>
        </div>
        
        <div class="signature-container">
            <div class="signature-box">
                <div>Peminjam,</div>
                <div class="signature-line"></div>
                <div class="fw-bold"><?= htmlspecialchars($data['nama_lengkap']) ?></div>
            </div>
        </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="btn-container" id="action-buttons">
        <button onclick="window.print()" class="btn btn-primary btn-sm flex-fill">
            <i class="bi bi-printer me-1"></i> Cetak Struk
        </button>
        <button onclick="downloadPDF()" class="btn btn-outline-danger btn-sm flex-fill">
            <i class="bi bi-file-pdf me-1"></i> Download PDF
        </button>
    </div>

    <!-- jsPDF & html2canvas CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <script>
        window.jsPDF = window.jspdf.jsPDF;

        function downloadPDF() {
            // Sembunyikan tombol secara manual sebelum proses rendering pdf
            const btnContainer = document.getElementById('action-buttons');
            btnContainer.style.setProperty('display', 'none', 'important');
            
            const element = document.getElementById('struk-box');
            
            // Konfigurasi html2canvas untuk menangkap resolusi tinggi
            html2canvas(element, {
                scale: 2,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                
                // Dimensi canvas hasil render
                const imgWidth = canvas.width;
                const imgHeight = canvas.height;
                
                // Konversi px ke mm untuk jsPDF
                // Rasio 96 DPI: 1px = 0.264583 mm
                const widthMm = imgWidth * 0.264583 / 2; // dibagi 2 karena scale 2x
                const heightMm = imgHeight * 0.264583 / 2;
                
                // Buat dokumen PDF standalone seukuran struk
                const doc = new jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: [widthMm + 20, heightMm + 20]
                });
                
                // Gambar struk ke PDF dengan margin 10mm
                doc.addImage(imgData, 'PNG', 10, 10, widthMm, heightMm);
                doc.save('struk-peminjaman-<?= $no_pinjam ?>.pdf');
                
                // Tampilkan kembali tombol
                btnContainer.style.setProperty('display', 'flex', 'important');
            }).catch(err => {
                console.error("PDF generation error:", err);
                btnContainer.style.setProperty('display', 'flex', 'important');
            });
        }
    </script>
</body>
</html>
