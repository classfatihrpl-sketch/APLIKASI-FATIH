<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User  belum login']);
    exit;
}

require 'config.php';

$user = $_SESSION['user'];

// Terima data JSON dari client
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['order']) || !isset($data['total'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$order = $data['order']; // array {name, price, quantity}
$total = $data['total'];

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
    exit;
}

$conn->begin_transaction();

try {
    // Simpan ke tabel payments, status awal pending
    $stmt = $conn->prepare("INSERT INTO payments (user, total_amount, payment_method, payment_status) VALUES (?, ?, 'QRIS', 'pending')");
    if (!$stmt) {
        throw new Exception('Gagal prepare payments: ' . $conn->error);
    }
    $stmt->bind_param("si", $user, $total);
    if (!$stmt->execute()) {
        throw new Exception('Gagal eksekusi payments: ' . $stmt->error);
    }
    $payment_id = $stmt->insert_id;
    $stmt->close();

    // Simpan detail pembayaran
    $stmt = $conn->prepare("INSERT INTO payment_details (payment_id, menu_item_name, quantity, price_per_item, subtotal) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Gagal prepare payment_details: ' . $conn->error);
    }
    foreach ($order as $item) {
        $name = $item['name'];
        $quantity = $item['quantity'];
        $price = $item['price'];
        $subtotal = $price * $quantity;
        $stmt->bind_param("isiii", $payment_id, $name, $quantity, $price, $subtotal);
        if (!$stmt->execute()) {
            throw new Exception('Gagal eksekusi payment_details: ' . $stmt->error);
        }
    }
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data pembayaran: ' . $e->getMessage()]);
}

$conn->close();
exit;