<?php
require_once '../includes/config.php';
requireAdmin();

header('Content-Type: application/json');

$id_kat = (int)($_GET['id_kategori'] ?? 0);
if ($id_kat <= 0) {
    echo json_encode(['kode' => '']);
    exit;
}

// Ambil nama kategori
$query = mysqli_query($conn, "SELECT nama_kategori FROM kategori WHERE id_kategori = $id_kat");
$kat = mysqli_fetch_assoc($query);

if (!$kat) {
    echo json_encode(['kode' => '']);
    exit;
}

// Buat prefix dari nama kategori (3 huruf pertama, kapital, hilangkan spasi)
$nama_bersih = str_replace(' ', '', $kat['nama_kategori']);
$prefix = strtoupper(substr($nama_bersih, 0, 3));

// Cari kode terakhir di barang yang menggunakan prefix tersebut
// Kita asumsikan formatnya selalu PREFIX-XXX (contoh: ELE-001)
$q_max = mysqli_query($conn, "
    SELECT kode_barang 
    FROM barang 
    WHERE kode_barang LIKE '{$prefix}-%' 
    ORDER BY CAST(SUBSTRING_INDEX(kode_barang, '-', -1) AS UNSIGNED) DESC 
    LIMIT 1
");

if (mysqli_num_rows($q_max) > 0) {
    $row = mysqli_fetch_assoc($q_max);
    $last_code = $row['kode_barang'];
    
    // Ambil angka terakhir setelah strip '-'
    $parts = explode('-', $last_code);
    $num = (int)end($parts);
    $new_num = $num + 1;
} else {
    // Jika belum ada barang di kategori ini, mulai dari 1
    $new_num = 1;
}

// Format nomor agar selalu 3 digit (contoh: 001)
$new_code = $prefix . '-' . str_pad($new_num, 3, '0', STR_PAD_LEFT);

echo json_encode(['kode' => $new_code]);
