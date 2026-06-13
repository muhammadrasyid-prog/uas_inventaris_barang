<?php
require_once '../includes/config.php';
requireAdmin();

// File ini HANYA proses POST tambah barang, tidak ada output HTML
// sehingga header("Location: ...") aman dipanggil kapan saja.

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['tambah'])) {
    header("Location: barang.php");
    exit();
}

$kode    = trim($_POST['kode_barang'] ?? '');
$nama    = trim($_POST['nama_barang'] ?? '');
$id_kat  = (int) ($_POST['id_kategori'] ?? 0);
$stok    = (int) ($_POST['stok'] ?? 0);
$kondisi = $_POST['kondisi'] ?? 'Baik';
$foto    = null;

// Validasi dasar di server
if ($kode === '' || $nama === '' || $id_kat <= 0) {
    header("Location: barang.php?status=error&msg=" . urlencode("Kode barang, nama barang, dan kategori wajib diisi."));
    exit();
}
if (!in_array($kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat'], true)) {
    $kondisi = 'Baik';
}
if ($stok < 0) {
    $stok = 0;
}

// Upload foto jika ada
if (!empty($_FILES['foto']['name'])) {
    $upload = uploadFile($_FILES['foto']);
    if (!$upload['success']) {
        header("Location: barang.php?status=error&msg=" . urlencode($upload['message']));
        exit();
    }
    $foto = $upload['filename'];
}

// Insert query biasa
$kode_esc    = mysqli_real_escape_string($conn, $kode);
$nama_esc    = mysqli_real_escape_string($conn, $nama);
$kondisi_esc = mysqli_real_escape_string($conn, $kondisi);
$foto_esc    = $foto ? "'" . mysqli_real_escape_string($conn, $foto) . "'" : "NULL";

$query = "INSERT INTO barang (kode_barang, nama_barang, id_kategori, stok, kondisi, foto)
          VALUES ('$kode_esc', '$nama_esc', $id_kat, $stok, '$kondisi_esc', $foto_esc)";

if (mysqli_query($conn, $query)) {
    $new_id = mysqli_insert_id($conn);
    header("Location: barang.php?status=tambah&id_barang=" . $new_id);
} else {
    // Gagal insert (kemungkinan besar kode_barang duplikat -> UNIQUE constraint)
    if ($foto) {
        deleteFile($foto); // jangan tinggalkan file yatim di folder upload
    }
    header("Location: barang.php?status=error&msg=" . urlencode("Kode barang sudah digunakan."));
}
exit();