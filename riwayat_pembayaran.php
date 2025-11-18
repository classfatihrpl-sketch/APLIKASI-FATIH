<?php
session_start();

// Hanya user yang sudah login dengan role tertentu yang boleh akses
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require 'config.php';

$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
if ($role !== 'admin' && $role !== 'kasir') {
    header('Location: beranda.php');
    exit;
}

// Proses update status jika ada POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'], $_POST['new_status'])) {
    $paymentId  = (int)$_POST['payment_id'];
    $newStatus  = $_POST['new_status'] === 'success' ? 'success' : 'pending';

    $stmt = $conn->prepare("UPDATE payments SET payment_status = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('si', $newStatus, $paymentId);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: riwayat_pembayaran.php');
    exit;
}

// Ambil filter tanggal dari query string
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate   = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Ambil data pembayaran dengan filter tanggal (jika ada)
$payments = [];
$sql = "SELECT id, user, total_amount, payment_method, payment_status, created_at FROM payments";
$conditions = [];

if ($startDate !== '' && $endDate !== '') {
    $conditions[] = "DATE(created_at) BETWEEN '" . $conn->real_escape_string($startDate) . "' AND '" . $conn->real_escape_string($endDate) . "'";
} elseif ($startDate !== '') {
    $conditions[] = "DATE(created_at) >= '" . $conn->real_escape_string($startDate) . "'";
} elseif ($endDate !== '') {
    $conditions[] = "DATE(created_at) <= '" . $conn->real_escape_string($endDate) . "'";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

$sql .= " ORDER BY id DESC";

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    $result->free();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Riwayat Pembayaran - Resto Delicious</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(circle at top left, rgba(255,255,255,0.15) 0, transparent 50%),
                linear-gradient(135deg, #33150b 0%, #120b26 50%, #050816 100%);
        }
    </style>
</head>
<body class="bg-transparent min-h-screen text-slate-50">
    <div class="bg-slate-900/40 border-b border-slate-700/60 backdrop-blur">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center text-xs font-bold shadow-lg">
                    RD
                </div>
                <div>
                    <h1 class="text-lg md:text-2xl font-semibold tracking-tight text-slate-50">Riwayat Pembayaran</h1>
                    <p class="text-[11px] md:text-xs text-slate-400">Pantau semua transaksi yang terjadi di Resto Delicious</p>
                </div>
            </div>
            <a href="beranda.php" class="inline-flex items-center gap-1 text-xs md:text-sm text-emerald-400 hover:text-emerald-300">
                <span class="text-base">&larr;</span>
                <span>Kembali ke Beranda</span>
            </a>
        </div>
    </div>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-200/80">Daftar seluruh transaksi pembayaran yang tercatat di sistem.</p>
            <span class="inline-flex items-center rounded-full bg-slate-900/60 border border-slate-600 px-3 py-1 text-xs font-medium text-slate-100">
                Total data: <?php echo count($payments); ?>
            </span>
        </div>

        <form method="get" class="mb-5 flex flex-wrap items-end gap-3 text-xs md:text-sm bg-slate-900/70 border border-slate-700/70 rounded-xl px-4 py-3">
            <div class="flex flex-col">
                <label for="start_date" class="mb-1 text-slate-300">Dari tanggal</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="rounded-md bg-slate-900 border border-slate-600 px-2 py-1.5 text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
            </div>
            <div class="flex flex-col">
                <label for="end_date" class="mb-1 text-slate-300">Sampai tanggal</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="rounded-md bg-slate-900 border border-slate-600 px-2 py-1.5 text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
            </div>
            <div class="flex gap-2 mt-4 md:mt-0">
                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-md bg-emerald-500 hover:bg-emerald-600 text-xs font-semibold text-white shadow-sm">Filter</button>
                <a href="riwayat_pembayaran.php" class="inline-flex items-center px-3 py-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-xs font-semibold text-slate-100">Reset</a>
            </div>
        </form>

        <div class="bg-slate-900/70 rounded-xl shadow-2xl ring-1 ring-slate-700/70 overflow-hidden backdrop-blur">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-slate-100">
                    <thead>
                        <tr class="bg-slate-800/70 text-slate-100 border-b border-slate-700">
                            <th class="px-3 py-2 text-left font-semibold">ID</th>
                            <th class="px-3 py-2 text-left font-semibold">User</th>
                            <th class="px-3 py-2 text-left font-semibold">Total</th>
                            <th class="px-3 py-2 text-left font-semibold">Metode</th>
                            <th class="px-3 py-2 text-left font-semibold">Status</th>
                            <th class="px-3 py-2 text-left font-semibold">Waktu</th>
                            <th class="px-3 py-2 text-left font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/70">
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-slate-400 text-sm">Belum ada data pembayaran.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $pay): ?>
                                <tr class="hover:bg-slate-800/60 transition-colors">
                                    <td class="px-3 py-2 align-top text-slate-100"><?php echo htmlspecialchars($pay['id']); ?></td>
                                    <td class="px-3 py-2 align-top text-slate-100"><?php echo htmlspecialchars($pay['user']); ?></td>
                                    <td class="px-3 py-2 align-top font-medium text-emerald-300">Rp <?php echo number_format($pay['total_amount'], 0, ',', '.'); ?></td>
                                    <td class="px-3 py-2 align-top text-slate-100"><?php echo htmlspecialchars($pay['payment_method']); ?></td>
                                    <td class="px-3 py-2 align-top">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold <?php echo $pay['payment_status'] === 'success' ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/40' : 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/40'; ?>">
                                            <?php echo htmlspecialchars($pay['payment_status'] === 'success' ? 'Success' : $pay['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 align-top text-slate-300 text-xs md:text-sm"><?php echo htmlspecialchars($pay['created_at'] ?? ''); ?></td>
                                    <td class="px-3 py-2 align-top">
                                        <form method="post" class="inline-block">
                                            <input type="hidden" name="payment_id" value="<?php echo (int)$pay['id']; ?>" />
                                            <input type="hidden" name="new_status" value="<?php echo $pay['payment_status'] === 'success' ? 'pending' : 'success'; ?>" />
                                            <button type="submit" class="px-3 py-1.5 text-xs rounded-full bg-blue-500 hover:bg-blue-600 text-white shadow-sm">
                                                Tandai <?php echo $pay['payment_status'] === 'success' ? 'Pending' : 'Success'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
