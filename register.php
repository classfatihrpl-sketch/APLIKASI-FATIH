<?php
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Ambil role dari form, pastikan hanya user atau admin yang boleh
    $role = isset($_POST['role']) ? $_POST['role'] : 'user';
    if ($role !== 'admin') {
        $role = 'user';
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sss", $username, $hash, $role);
        if ($stmt->execute()) {
            header("Location: index.php");
            exit;
        } else {
            if ($stmt->errno === 1062) { // duplicate entry
                $error = "Username sudah dipakai, silakan gunakan nama lain.";
            } else {
                $error = "Gagal menyimpan user: " . $stmt->error;
            }
        }
        $stmt->close();
    } else {
        $error = "Gagal menyiapkan query register.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Register - Resto Delicious</title>
    <style>
        * {
            margin: 0; padding: 0; box-sizing: border-box;
            font-family: "Segoe UI", sans-serif;
        }
        body {
            /* Sama nuansanya dengan halaman login */
            background:
                radial-gradient(circle at top left, rgba(255,255,255,0.15) 0, transparent 50%),
                linear-gradient(135deg, #33150b 0%, #120b26 50%, #050816 100%);
            height: 100vh; display: flex;
            justify-content: center; align-items: center;
        }
        .register-box {
            background: rgba(248, 250, 252, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px 36px 32px;
            border-radius: 18px;
            width: 360px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.6);
            text-align: center;
            animation: fadeIn 0.9s ease-out;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        .register-logo {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 30% 0, #f97316, #b91c1c 55%, #7c2d12 100%);
            color: #f9fafb;
            font-weight: 800;
            font-size: 22px;
            box-shadow: 0 10px 25px rgba(127, 29, 29, 0.45);
        }
        .register-box h2 {
            margin-bottom: 6px;
            color: #111827;
            letter-spacing: 0.03em;
        }
        .register-subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 18px;
        }
        .register-box input,
        .register-box select {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            outline: none;
            transition: 0.3s;
            background-color: #f9fafb;
        }
        .register-box input:focus,
        .register-box select:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.25);
            background-color: #ffffff;
        }
        .register-box button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        .register-box button:hover {
            background: linear-gradient(135deg, #16a34a, #15803d);
            box-shadow: 0 8px 20px rgba(22, 163, 74, 0.4);
        }
        .register-box p {
            margin-top: 15px;
            font-size: 14px;
        }
        .register-box a {
            color: #4facfe;
            text-decoration: none;
            font-weight: bold;
        }
        .register-box a:hover {
            text-decoration: underline;
        }
        .error {
            color: red;
            margin-bottom: 10px;
            font-size: 14px;
        }
        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(-20px);} 
            to {opacity: 1; transform: translateY(0);} 
        }
    </style>
</head>
<body>
    <div class="register-box">
        <div class="register-logo">RD</div>
        <h2>Register</h2>
        <p class="register-subtitle">Buat akun baru sebagai user atau admin</p>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role" required>
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
            <button type="submit">Register</button>
        </form>
        <p>Sudah punya akun? <a href="index.php">Login</a></p>
    </div>
</body>
</html>
