<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User  belum login']);
    exit;
}

$user = $_SESSION['user'];

// Terima data JSON dari client
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['order']) || !isset($data['total'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$order = $data['order']; // array {name, price, quantity}
$total = $data['total'];

// Koneksi database (ubah sesuai konfigurasi Anda)
$host = 'localhost';
$db   = 'aplikasi-restoran';
$user_db = 'root';
$pass_db = '';

$conn = new mysqli($host, $user_db, $pass_db, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
    exit;
}

$conn->begin_transaction();

try {
    // Simpan ke tabel payments
    $stmt = $conn->prepare("INSERT INTO payments (user, total_amount, payment_method, payment_status) VALUES (?, ?, 'QRIS', 'paid')");
    $stmt->bind_param("si", $user, $total);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();

    // Simpan detail pembayaran
    $stmt = $conn->prepare("INSERT INTO payment_details (payment_id, menu_item_name, quantity, price_per_item, subtotal) VALUES (?, ?, ?, ?, ?)");
    foreach ($order as $item) {
        $name = $item['name'];
        $quantity = $item['quantity'];
        $price = $item['price'];
        $subtotal = $price * $quantity;
        $stmt->bind_param("isiii", $payment_id, $name, $quantity, $price, $subtotal);
        $stmt->execute();
    }
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data pembayaran']);
}

$conn->close();