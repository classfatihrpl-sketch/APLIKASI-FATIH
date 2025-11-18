<?php
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header("Location: beranda.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Restoran Mang Fatih</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top left, #fef3c7 0, transparent 45%),
                        radial-gradient(circle at bottom right, #bbf7d0 0, transparent 45%),
                        linear-gradient(130deg, #0f172a, #1e293b);
            color: #0f172a;
        }
        .login-wrapper {
            width: 100%;
            max-width: 960px;
            padding: 16px;
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
            gap: 32px;
            align-items: center;
        }
        @media (max-width: 768px) {
            .login-wrapper {
                grid-template-columns: minmax(0, 1fr);
                max-width: 420px;
            }
        }
        .login-hero {
            display: flex;
            flex-direction: column;
            gap: 12px;
            color: #e5e7eb;
        }
        .login-hero-title {
            font-size: clamp(1.8rem, 3vw, 2.2rem);
            font-weight: 700;
            letter-spacing: 0.03em;
        }
        .login-hero-subtitle {
            font-size: 0.95rem;
            max-width: 320px;
            color: #cbd5f5;
        }
        .login-badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .login-badge {
            font-size: 0.75rem;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid rgba(248, 250, 252, 0.15);
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(10px);
            color: #e5e7eb;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .login-badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #22c55e;
        }
        .login-card {
            background: rgba(15, 23, 42, 0.85);
            border-radius: 20px;
            padding: 28px 26px 24px;
            box-shadow:
                0 24px 60px rgba(15, 23, 42, 0.65),
                0 0 0 1px rgba(148, 163, 184, 0.25);
            backdrop-filter: blur(16px);
            color: #e5e7eb;
        }
        @media (max-width: 480px) {
            .login-card {
                padding: 24px 20px;
            }
        }
        .login-card-header {
            margin-bottom: 18px;
            text-align: left;
        }
        .login-brand {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: #9ca3af;
        }
        .login-title {
            margin-top: 4px;
            font-size: 1.3rem;
            font-weight: 600;
            color: #f9fafb;
        }
        .login-sub {
            margin-top: 4px;
            font-size: 0.85rem;
            color: #9ca3af;
        }
        .login-form-group {
            margin-top: 14px;
            text-align: left;
        }
        .login-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: #e5e7eb;
            margin-bottom: 4px;
        }
        .login-input {
            width: 100%;
            padding: 10px 11px;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, 0.6);
            background: rgba(15, 23, 42, 0.85);
            color: #f9fafb;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .login-input::placeholder {
            color: #6b7280;
        }
        .login-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 1px rgba(129, 140, 248, 0.6);
            background: rgba(15, 23, 42, 0.95);
        }
        .login-button {
            width: 100%;
            margin-top: 16px;
            padding: 11px 12px;
            border-radius: 999px;
            border: none;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            cursor: pointer;
            color: #0f172a;
            background: linear-gradient(135deg, #facc15, #f97316);
            box-shadow:
                0 12px 35px rgba(249, 115, 22, 0.55),
                0 0 0 1px rgba(251, 191, 36, 0.75);
            transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
        }
        .login-button:hover {
            transform: translateY(-1px);
            filter: brightness(1.03);
            box-shadow:
                0 18px 40px rgba(249, 115, 22, 0.75),
                0 0 0 1px rgba(251, 191, 36, 0.9);
        }
        .login-button:active {
            transform: translateY(0);
            box-shadow:
                0 10px 28px rgba(249, 115, 22, 0.55),
                0 0 0 1px rgba(251, 191, 36, 0.9);
        }
        .login-footer {
            margin-top: 14px;
            font-size: 0.8rem;
            color: #9ca3af;
            text-align: left;
        }
        .login-footer a {
            color: #facc15;
            text-decoration: none;
            font-weight: 500;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
        .error {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.5);
            color: #fecaca;
            padding: 8px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-bottom: 10px;
            text-align: left;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-hero">
            <div class="login-hero-title">RESTORAN MANG IWAN</div>
            <p class="login-hero-subtitle">Kelola pesanan, pembayaran, dan pengalaman pelanggan dengan lebih cepat dan rapi.</p>
            <div class="login-badge-row">
                <span class="login-badge">
                    <span class="login-badge-dot"></span>
                    Kasir &amp; admin terintegrasi
                </span>
                <span class="login-badge">
                    ⭐ Rating pelanggan langsung tercatat
                </span>
            </div>
        </div>

        <div class="login-card" style="animation: fadeIn 0.6s ease-out;">
            <div class="login-card-header">
                <div class="login-brand">Masuk ke sistem</div>
                <div class="login-title">Login Akun</div>
                <div class="login-sub">Masukkan username dan password yang sudah terdaftar.</div>
            </div>

            <?php if (!empty($error)) echo "<div class='error'>$error</div>"; ?>

            <form method="post">
                <div class="login-form-group">
                    <label class="login-label" for="username">Username</label>
                    <input class="login-input" id="username" type="text" name="username" placeholder="Masukkan username" required>
                </div>
                <div class="login-form-group">
                    <label class="login-label" for="password">Password</label>
                    <input class="login-input" id="password" type="password" name="password" placeholder="Masukkan password" required>
                </div>
                <button type="submit" class="login-button">Masuk</button>
            </form>

            <div class="login-footer">
                Belum punya akun? <a href="register.php">Daftar sekarang</a>
            </div>
        </div>
    </div>
</body>
</html>
