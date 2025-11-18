<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User belum login']);
    exit;
}

require 'config.php';

$user = $_SESSION['user'];

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['menu_item_name']) || !isset($data['rating']) || !isset($data['comment'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$menuItemName = $conn->real_escape_string($data['menu_item_name']);
$rating       = (int)$data['rating'];
$comment      = $conn->real_escape_string($data['comment']);

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating harus antara 1 sampai 5']);
    exit;
}

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO reviews (user, menu_item_name, rating, comment) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Gagal prepare: ' . $conn->error);
    }
    $stmt->bind_param('ssis', $user, $menuItemName, $rating, $comment);
    if (!$stmt->execute()) {
        throw new Exception('Gagal eksekusi: ' . $stmt->error);
    }
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Rating berhasil disimpan']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan rating: ' . $e->getMessage()]);
}

$conn->close();
exit;
