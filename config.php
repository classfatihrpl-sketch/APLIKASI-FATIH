<?php
$host = "localhost";
$user = "root";  // ganti sesuai setting
$pass = "";      // ganti sesuai setting
$db   = "aplikasi-restoran";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>
