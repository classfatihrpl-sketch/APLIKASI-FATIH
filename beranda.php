<?php
session_start();

// Pastikan user sudah login, jika belum redirect ke halaman login utama
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

$role    = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$isAdmin = $role === 'admin';

// Ambil rata-rata rating per menu
$ratingSummary = [];
$ratingQuery = "SELECT menu_item_name, AVG(rating) AS avg_rating, COUNT(*) AS total_reviews FROM reviews GROUP BY menu_item_name";
if ($result = $conn->query($ratingQuery)) {
    while ($row = $result->fetch_assoc()) {
        $name = $row['menu_item_name'];
        $ratingSummary[$name] = [
            'avg' => (float)$row['avg_rating'],
            'count' => (int)$row['total_reviews']
        ];
    }
    $result->free();
}

// Ambil komentar per menu
$commentsByMenu = [];
$commentsQuery = "SELECT user, menu_item_name, rating, comment FROM reviews";
if ($commentsResult = $conn->query($commentsQuery)) {
    while ($row = $commentsResult->fetch_assoc()) {
        $name = $row['menu_item_name'];
        if (!isset($commentsByMenu[$name])) {
            $commentsByMenu[$name] = [];
        }
        $commentsByMenu[$name][] = [
            'user'    => $row['user'],
            'rating'  => (int)$row['rating'],
            'comment' => $row['comment'],
        ];
    }
    $commentsResult->free();
}

// Ambil menu dinamis dari tabel menus
$dynamicMenus = [];
$menusQuery = "SELECT name, price, image_path FROM menus";
if ($menusResult = $conn->query($menusQuery)) {
    while ($row = $menusResult->fetch_assoc()) {
        $dynamicMenus[] = $row;
    }
    $menusResult->free();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Beranda - Resto Apps</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            /* Nuansa hangat ala restoran */
            background:
                radial-gradient(circle at top left, rgba(248, 250, 252, 0.15) 0, transparent 50%),
                linear-gradient(135deg, #33150b 0%, #120b26 50%, #050816 100%);
        }
        .menu-item {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(4px);
            border-radius: 0.75rem;
        }
        .menu-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.35);
        }
        .qris-img {
            max-width: 250px;
            margin: 0 auto;
        }
    </style>
</head>
<body class="bg-transparent">
    <div class="container mx-auto px-4 py-10 max-w-5xl">
        <header class="mb-8 relative flex items-center justify-between">
            <div class="flex flex-col gap-1">
                <h1 class="text-3xl font-bold text-orange-100 drop-shadow-sm">Resto Fatih</h1>
                <p class="text-sm text-slate-200/80">Pesan makanan Jepang favoritmu dengan suasana cozy</p>
                <?php if ($role === 'admin' || $role === 'kasir'): ?>
                    <a href="riwayat_pembayaran.php" class="inline-flex items-center mt-1 w-max rounded-full bg-amber-400/90 px-3 py-1 text-[11px] font-semibold text-slate-900 shadow-sm hover:bg-amber-300 hover:shadow-md transition">
                        Kelola Pembayaran (Success / Pending)
                    </a>
                <?php endif; ?>
            </div>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-full text-sm">Logout</a>
        </header>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Menu Items -->
            <div class="md:col-span-2 bg-amber-50/95 p-6 rounded-2xl shadow-xl">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Menu Makanan Jepang</h2>
                
                <input type="text" id="searchInput" placeholder="Cari makanan atau minuman..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md mb-4" 
                       oninput="searchMenu()">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" id="menuItems">
                    <!-- Menu Items akan ditampilkan di sini -->
                </div>
            </div>
            <!-- Order Summary -->
            <div class="bg-amber-50/95 p-6 rounded-2xl shadow-xl">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Pesanan Anda</h2>
                
                <div id="order-summary" class="mb-4 text-gray-700">
                    <p class="text-gray-500 italic">Belum ada pesanan</p>
                </div>
                <div class="border-t border-gray-200 pt-4 mb-4">
                    <div class="flex justify-between items-center">
                        <span class="font-medium">Total:</span>
                        <span id="totalHarga" class="font-bold text-lg">Rp 0</span>
                    </div>
                </div>
                
                <button onclick="payNow()" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg transition-all">Bayar Sekarang</button>
                <!-- QRIS Payment Section -->
                <div id="qrisSection" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                    <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 text-center">Pembayaran QRIS</h3>
                        <div class="bg-gray-100 p-4 rounded-lg text-center mb-4">
                            <img src="qris.png" alt="QR Code untuk pembayaran digital" class="qris-img mx-auto" />
                            <p class="text-sm text-gray-600 mt-2">Total: <span id="qrisTotal" class="font-medium">Rp 0</span></p>
                        </div>
                        <button type="button"
                                onclick="closeQrisAndOpenRating();"
                                class="w-full bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition">
                            Tutup
                        </button>
                    </div>
                </div>

                <!-- Rating & Comment Section -->
                <div id="ratingSection" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden px-4">
                    <div class="bg-white/95 rounded-2xl shadow-2xl max-w-md w-full p-6 border border-amber-100">
                        <div class="text-center mb-4">
                            <h3 class="text-xl font-bold text-gray-900 tracking-tight">Rating & Komentar</h3>
                            <p class="text-xs text-gray-500 mt-1">Bagikan pengalamanmu agar pengunjung lain terbantu</p>
                            <p class="text-sm text-gray-800 font-semibold mt-3" id="ratingMenuTitle"></p>
                        </div>

                        <div class="mb-4">
                            <p class="text-xs text-gray-500 mb-1 text-center">Pilih rating:</p>
                            <div id="ratingStars" class="flex justify-center gap-1.5 mb-1">
                                <button type="button" class="text-gray-300 text-2xl transition-transform hover:scale-110" onclick="setRating(1)">★</button>
                                <button type="button" class="text-gray-300 text-2xl transition-transform hover:scale-110" onclick="setRating(2)">★</button>
                                <button type="button" class="text-gray-300 text-2xl transition-transform hover:scale-110" onclick="setRating(3)">★</button>
                                <button type="button" class="text-gray-300 text-2xl transition-transform hover:scale-110" onclick="setRating(4)">★</button>
                                <button type="button" class="text-gray-300 text-2xl transition-transform hover:scale-110" onclick="setRating(5)">★</button>
                            </div>
                            <p class="text-[11px] text-gray-400 text-center">Tekan bintang untuk mengubah rating</p>
                        </div>

                        <div class="mb-4">
                            <label for="ratingComment" class="block text-xs font-medium text-gray-600 mb-1">Komentar Anda</label>
                            <textarea id="ratingComment" class="w-full border border-gray-200 focus:border-amber-400 focus:ring-1 focus:ring-amber-300 rounded-lg px-3 py-2 text-sm resize-none placeholder:text-gray-400 bg-gray-50" rows="4" placeholder="Ceritakan rasa, porsi, atau pelayanan yang Anda rasakan..."></textarea>
                        </div>

                        <div class="flex gap-2 mt-2">
                            <button type="button"
                                    onclick="document.getElementById('ratingSection').classList.add('hidden');"
                                    class="w-1/2 border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 font-semibold py-2 px-4 rounded-lg text-sm transition-colors">
                                Batal
                            </button>
                            <button type="button"
                                    onclick="submitRating();"
                                    class="w-1/2 bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2 px-4 rounded-lg text-sm shadow-sm shadow-amber-200 transition-colors">
                                Kirim
                            </button>
                        </div>
                    </div>
                </div>

                <!-- All Comments Section -->
                <div id="commentsSection" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden px-4">
                    <div class="bg-white/95 rounded-2xl shadow-2xl max-w-md w-full max-h-[80vh] flex flex-col border border-amber-100">
                        <div class="px-6 pt-5 pb-3 border-b border-gray-100">
                            <h3 class="text-lg font-bold text-gray-900 text-center tracking-tight">Semua Komentar</h3>
                            <p class="text-center text-gray-600 mt-1 text-sm font-medium" id="commentsMenuTitle"></p>
                        </div>
                        <div id="commentsList" class="flex-1 overflow-y-auto px-6 py-4 bg-gradient-to-b from-gray-50/80 to-white text-sm text-gray-700 space-y-3"></div>
                        <div class="px-6 pb-5 pt-2 border-t border-gray-100">
                            <button type="button"
                                    onclick="document.getElementById('commentsSection').classList.add('hidden');"
                                    class="w-full border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 font-semibold py-2.5 px-4 rounded-lg text-sm transition-colors">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    let order = [];
    let lastPaymentTotal = 0;
    let ratingMenuName = '';
    let selectedRating = 0;
    let pendingRatingMenus = [];
    const ratingSummary   = <?= json_encode($ratingSummary) ?>;
    const commentsByMenu  = <?= json_encode($commentsByMenu) ?>;
    const dynamicMenus    = <?= json_encode($dynamicMenus) ?>;
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

    const staticMenus = [
        { name: "Sushi",         price: 30000, img: "https://res.cloudinary.com/jnto/image/upload/w_1440,h_900,c_fill,f_auto,fl_lossy,q_60/v1/media/filer_public/47/7f/477f36c3-a329-437d-a571-d75cdaa73a6f/1_5_zv5jq9" },
        { name: "Ramen",         price: 25000, img: "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQkD-0dVkbjaMEFdBEQGHPno_uI9M3PG63cEg&s" },
        { name: "Tempura",       price: 20000, img: "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQx9RzOO-7de09GHYWnYJXguK-LibOBdguw4A&s" },
        { name: "Matcha Latte",  price: 18000, img: "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTcA8lveFxbl8zLrVkYLGYHj5BzTQeMJeIjEw&s" },
        { name: "Sakura Tea",    price: 16000, img: "data:image/jpeg;base64,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" },
        { name: "Calpis",        price: 14000, img: "data:image/jpeg;base64,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" },
        { name: "Ramune",        price: 12000, img: "https://media.istockphoto.com/id/1206970136/id/vektor/ramune.jpg?s=612x612&w=is&k=20&c=K1B3YyD2xfXpOeEt0MzA5nwzkPAO4-SBleWCAclw5IQ=" },
        { name: "Umeshu",        price: 22000, img: "data:image/jpeg;base64,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" },
        { name: "Ocha (Teh Hijau)", price: 10000, img: "data:image/jpeg;base64,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" },
        { name: "Bento",         price: 25000, img: "data:image/jpeg;base64,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" },
        { name: "Manju",         price: 16000, img: "https://www.shutterstock.com/image-photo/manju-japanese-cake-character-reading-260nw-1175688292.jpg" },
        { name: "Daifuku",       price: 14000, img: "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBwgHBgkIBwgKCgkLDRYPDQwMDRsUFRAWIB0iIiAdHx8kKDQsJCYxJx8fLT0tMTU3Ojo6Iys/RD84QzQ5OjcBCgoKDQwNGg8PGjclHyU3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3N//AABEIAJQA5AMBIgACEQEDEQH/xAAcAAABBQEBAQAAAAAAAAAAAAAEAAECBQYDBwj/xAA/EAACAQMDAgQDBQYEBAcAAAABAgMABBEFEiExQQYTUWEiMnEUgZGxwSNCUqHR8AcVM/FDYnKCFiQlU1WS4f/EABoBAAIDAQEAAAAAAAAAAAAAAAMEAAECBQb/xAAsEQACAgEEAAUCBgMAAAAAAAAAAQIDEQQSITEFEyJBUXGBIzJCYaGxFSRi/9oADAMBAAIRAxEAPwD1xFMCyt1Dc7FHShor1OFf4c9AetFSFlBKjJqpuVJcmSDcKHtxLKH64KXZYSOBMpEiqCO5610+0gKCDkE4zWbvLiRAhW3aQA9GPI+lG21xFNM0PIljG/aG6VSm9zTJKG3sukl3cjNdFO7p0qnl1iO3XJt5z6nZRn+Yr5MbqpKvwfatuaTwwbjzwG4HeuUowpIFNDL5q7hUpELL0rRWMMqkvbf7Q2GIMY5z60VZ3wl65++hIbARXDNLhyTwSKso4UUfCozWYxwGlswdhID0p81AqF6U3NbA4J0s1DJxT1eS8Dk026mIpsGoWPmmJp8GnAqEOZJzTEgcsQB6mpyukMbyScKoyT6V5z4j1y6urhlifZCPlQDr6ZqpTUexvSaWWpltjwjeRXlrLJsjnjZh2DUT1FeHvqk+8iGQqIxy4bDGvR/BPiWPWLY28rbbmJejdWHrWIWqTwM6zwyenjuTyi81O6+x2+VP7RzheM4+vtWN1rVLhb4QxX7RyHBDAEgZ7YH0q98cW7TaUkizNFHFIGlZcfJ3FYK5a0Sae8j3u3BjUvwPTPpSWtushPETmvKimjXaV4uhVxbXshk2jBnKbRn6Vq0ZJEDowZSMgjvXz9e38bs7LM8FwMkIBlSfQ17L4Jnln8L2MkykMU7imaJyklkM6ZKpWMvhSqAfOcCos59KZBpEj1pq57jSqELV1BriYge1dzUTWcC6bQJLbI3zCh7fTLW3M020+a4wGJqwOSaHvnjaHa7bTnjnFYlH3SCJuXAMisq7JVDe5qctqzRIsJHJzyM4odpCxTy3DNkcH070fdF4VjWFgoz8QxnNZlLC4WWalmLwBSXi6dKkM7fOcKdvBNGyX0Mcgt2f9oRk0NcSRXQAljyR2IqvvIyUaVSquo4ZvSo9zxtZpV7+y6ciQDtjpUGuBGygtk9OBWUkvb6SW3bT76NfixJE8eQw9j2oy41a4trpxJHtt0XJZeSKqyTjj9zToaeC6nn8jbI7BUb1olJUcfCQfpWKXXtPviPt995WH+BGXHHathaxxCENFIrgjgg5ouUZnGMUs9hHBFPXCaaO3i8yZwqjqSar28Raamc3APcECo5JdmY1zksxWS3pZrjFNFMgkhk3oR+7UmYKhZiAAMkntVrnldGMHDUdRisIi0uS2MgAdqob/wAXtZWT3UlsqoFyu5vmJ7YoLVtfguLibyGM6RoFQhcqT3PvVLeXSahfaZbtsZ5mw29cYwewrnz1r85VRNJqP6cl7N4pj1bwteSyW81uJEZI2Xqxx1Ge1ZSS1861tZrqcwqy7mDrk46dB/fNanVrKSe5dbeLd5S4SP8AdwOAPas9rV5LbXF/aGUiSFFAfc2BxngdO/5UW2xZ2yO74fbGLcK1htlPfaRYSgBb2WIk4C+WOv06mlokKeH9QS+ttWiaRVbCunwsCPr61RXV1IhZdzIu4DDDqo6dPrVaszzXUUKsd7sEyT17UFSw8pHWtSfpm85PR7nW9a1eKW0iNu1vIgWWV+VyOfhHr7ZquEdtbW8nkyfaXuRudpU+bHTj90dsfTmu8sNrpen29urARMNzFh8zfxAjow4z7VnrzU953SoxdgN2ecn7/vo0mv1dgq9PXL1KPBKbR4729jmUJCRsMqLwpGcDjtXpmm+KrW3SO1uIBDGp8tSnyjFeaWF6ruY8bVHxbQ4wMd+B1zVkJ5vsrLGz7sbyVHDClp6iVM0l0zna+dScYOPB7DGySRh4zuRhkGmIzWX/AMO9Qe90Zw5yIpNoOMfWtOTXThLdHJyLIbJuI20etKljPNKtmCzJ9KiTUjio96oXRFgSOtCXFos/D80bUdyx5d+gqM3GTXRm7yGMytZRxTZZTmRTgD6GjbWF7aNIWZ5MD4nc5JNNLdK2p7wOI0KhT2z1NFLdCQnKqP8ApOaXqi8tsMt/bQyx7jg808lqroVYZBGCKmJARU1cngCj8JFOTRWHS4IhlE2+mKGuLeUI6qpfcNpz3FHXl84uRa26q0g5dj0Uf1oyKJn7GsrnkJ5kkssykehHZbmOJBsfcfM5JGc4+lXsZuYBczXUiCDblFRcbasZ447S1luLpwkaKWIz2rMzXE9zAJLxN9tINxhj4ZQenPrigWuEXl9krir3n2KS/wBde7mkkuCqQx5AQ/31rJS+IHgYgASLk8vW4vvBo1Swjn0K/Do/xFJxgt7ZGMH7q808R6Fq2hyBtVspIkzxL8yMfTcP1oPr7PS6W/R7dsHz8Pg2fhXxYIbqGMuEtn+F02k4PY16FqF8LC182Zll38IgXqf6V4f4HtG1PXULMi2sXxzFvlOP3a1nizVzJO9vaOkdvGBFFk4ypxkj8s1uluuDcvsIW6KvUarFfXuS13W4GuQ0lsiMRsIHCqo/WszFcrJrlnOl1JGYXBjDpuHHPX6UHf3LStgkBWJJG/v7n7sfdVZJesFwuOmAcUvuUpZwdDUeHUKnbjk9yh1C1djc2wd1nQMzrwEHrWA8dRiOCCa2BJLt9oIlyX+oH31PwjqFzFpEMjB8J8jN3Bq61C50vUrEjUrNJCzfG0R2Nn19P5UeVTlLfk8/GMqbFOJ5ld3LTKhzwuQuPT096A0uT/1m2bCnEnR+hrUX/hu3eI/5XqilAf8ATvE2sP8AuHBqlh0G7tL+OWdFnVDuzEwkB9OAQTVJYOtO/fiSLm+1FLi8Teu4RKQFVdq8gdR79/pVbc+UxyZRIeAWXIJ49K2cJsZ40nEKiYxsrLJHtYZHOfw61SXugq8YksoLhwBhVgXjpjLMetD3bpYwbh4pXnY44KO0bZOpjfCcbucH3+tX73gsY5Ht2Xe0YOD6U9p4K1K6sopRc28TScusmcqK0fh/wTp1u8c91LJfyJ8gI2xgfTqfvNblp3Y0xLW2VWSTT6Ln/DyGVNFE0sQjM7mQgds1rDjtQ8QSFdi5x+VS3+nNPxjtWDmSe6TkTJpVDeO9KtGC3b5sCmxjmm5PfrTNgYHSqFx64zxiRdrZxXUZqPPQ1C0Vd1pUVywMhOB0AOK6W1jFbpiNTgVYYFPhVUtIQqjk5OMVUmlyFdssYOSQDAzgChnklu2MNh8MY4e4xx9F9TU4pf8AMlaQkpZKMhidpl9/ZfzojSbmK6EjQJiCJtiHGAT3x7UDLmwUpNLJCx0i3s1ZiMsTuOTnn39a6y6hDCMLgYpapP5cJweayd3dkZ55pfUah1vZELTS7lmQvFOsm5MWmxN8VwcYH8PeoX85tL6yB+V1KsPUcVmLCf7Z4nnncDEACKe3vVv4mnAuLc5+VCaUc3tcn2dyvTqLhWvhsKknuNGvmubfc8EmBJHn+YHtWltL6DU7XbKsc8UgwyuAysPpWbW7jvtLDk4YDB+tVnhW8+zXdxaAnYj7kHbB7fjn8aNGxxaw+GKaqlTg5P8AMjexaZpkOI7fTbRFPQLCoqR8O6S/+pptpnP/ALQzUoblYYck5Y9aDvtbigjdi5wozTjsgl6jkwVzeItlHr/hDwbCubm3MDtyPs7sGP0ArIy/4caLrCsfDmtyxSpw8V0m7B7dMFfrzWntZXu5JdQvMk5O1GHyL2H61nPEbTaPdQa9ppK7QPOUHh19DS7tW7rg7VdNrjt3vd/H0Dm0W90LS4rW6ti0EUYUzxfEOO/tVM0QkRXjYPGTkMO31r07QtXi1WwimjORIvIbt7VV6x4XhmeS403ZbXDcun/ClPuOx9xTqalE58dVKMsTR53qLFVCgEKTxVFcPITjAKj0rW3Xkx3LWV/E1ncp/wAKbp9Vbowrj/knmSKI9u5zgEdzjP5UN0pc5OlDVxa5KDSZ5Ibe+um2+YEWGFcckseT9wH86uPDdtdENE09xs3YYLIVx6c009idPmHl244+aWXgbh6DPNXHh7WLG2Kre27xlmz5kbb1zjqe4ocHWpNZD3UznU7Ixzk0djaLHB8Qwo5OSSTj3PJo6ORcYXAx0xVLeat5+fs4xGO+c7qnBc7kUnrTqRyFVJLkuxKQOCCaYzkHmhIZVOCOnrXRW35A6+lWTASLjilQ4YgfI1KrM4NQeopmGalTGsigscUwGTT5oPUL42UfmLGZPVVHP3VTeEWk28IInkitoWmmcKqAsxJwAKp7Uy+Im8+YGLSU+RTwbn3P/J+f0oeCyu/EdyLjU0aDS4myluTzMR/F7e1FXl6dTvV0jTW2RqP28qjhFHYe9KvM+zW3HC+7+A8Iup9fhsozjC8eaR2+lWNu0YTZGoAUdAOBQ0rLBEsUYCqihVA6AUFBeGK5+L5G4OKJxFC7bl9CGtF3cJEu5jxgDnNU17p6xukMzl7l+TGjABB78da1myOHdcSYL44z2FZGzmN3rF/d8FFcxr3+Xg/zzSdlSU9z7Z0dK3JPHS/lg+laDpcdxLEkk9vcuxkKu4cPnqRxmjtQ8LR3jozX4GwYxs6/zrhfxC/h+120u2aNTsYdQajp2vO7Gyv1WK8QevDD1FT8J8SXDGrHelvrl17fAj4blt7dobW6ST2YFay2k2N5Ya7cy30EkJLAJv6Mo5yK10uoOrlc9DXQzRX8BinGT+6fQ+orOISeI8Czvuw1PkBvNV2IPi61n9QvBeTx22/q29x/EBnj8cUZqGjas2/yrOeRU6MF6j1rMpJNbaiYrpGibb8sikHrzS9m/lsb0Cg7EsmnmvdmlFRwDhfuobXGW40VYdwAMfGaCvnZ7JAnIzXO/kK2cYbk7cfSgO14Z241RTX1On+GWrlY5LFn3eQwUHuVz39/0r1BX8xAR/LtXgnhm4ks9cuJERtoGMdgc963cPiPU4ppFSWFYnO1SULlWx0GDXUqm0jhanw+yyxuCNZq2l2fiW0kgvY086FiEkHVKzWi6c2h3k8TRNwMHccr7Y9M0D4d8Tx6VGsF6k7yl2leZfi4PPPetlrcKa/oH2/SJ45biNDJA6NlX4+U/X863JStrx0znTptpfl2LH9GR8S6n5zeXHZn7QVH7RmztHoo7fWsm0rq43SAdAUU4A+n9mnv9Za7u3mVfLJQq+SRkjqcdj2+6g4ThjvbHGVOOn0rnvO49voKPKoUS+0W986R7d+GI3ICSMD6Gr+GQsgOSO/3VhLe7EEyEEhwQQM+/wDf41urMeZFHMrHO1fhNdXTWbo7WcvxGlQnuRY20pMW7gc0ZC+HViRz1oG3jCQglcqeCetEwggKCOC2D7U2jkSS5DDjNKojIGMZx3pVAfJq/elyepHTNOMYqO0dqwJC7e1KNEdvjxx2NI4AwKrNYt5J4z9nGJQMKw4IqpZxwajHc8ZAvEutP5h0vSz+2YYdx+4KsPDOnLZWrtj9ox+Jj1NVek6N9lkaWQs8hOS5PJNaax/0mHoawo4QW/bCvbEFvM5OKhYWwkmDuMhOaInjyxrrapshfHeqwIlR4rvFgsZiXKqEYkj6Vm/CSNFowLjO5S3rjPJozx/IyaPdMi5Iib7+n/7QPha8W40yONt2JIRyRjtSM3mzk7mlj/qvHyC6XqRtrkxsAYnOD7c0vFdmkzLcQnZcJ8ko6gen0oU6c5kmwxDJkD3rUaJp6amiNdDdDFgH/nPpQIRlJbB7Uzqrfmr7lT4Xs7zxDYRTSIYFQlHkccMR/CO9a+106w0iFpmOSgJaWTkD+lHs6QRrGiqoA+EDgCsLqWs3GtarJY2uPsEJ2SPj/VfPI+gpvbCtZxlnFrhPUzaXEQ/Utf1Ns3enJG9pESNpz+1+h7Vc6Xf2Wu2EV0YElWQYIlQEg9wfoeKrYZFLT23l4RF+4GqPwPe+TreraUoURxyedGAem7r/AD/OqhNuWHymMXUQdLcVhx/o1F94X0ydT9niW1bqPLHw/wD16V514rsbrTZvKu7d1U58uZfiRx7Ht99etq2QGzzXO8tbbULaS2u41eKTqPT3HpRbNPCxCun111Mu8ngNlMtkhi4QyFlkJO4EHKksO4GRx3xT3+qyzMI4yUjyG2j4STgcnHQ/TpXbxho76Jr1xbspdCQyNyN6kcc+vX8KrdoL8qR6jPP9aXeV6T2en2zrjYvcJt7pzsVYRlwArY68csR93X3r0v8AwsukOlzWyqoWN8rxyMjkfTOce1eZSRKqlzzkA5Jx0Pp6Vvf8LC0kVwyKwjL4BPf/AG5FM0fmOZ4ztdDz2YbxxCdM8Xahbx5Ceb5sa44G4Z4/GgY5pZCxgD7c/CemPUfjW1/xAgWXxXNKE5CRqxx7VT2ent5iqVJGcVT0sXLJWl11ipj9AKw0kTuHuC7bSNwB61t4gVLJGSsW0bR6elDaZZPGzDZgsMDjv1FWcFudqHb1BwT+VN11xh0gGovlY/UwqIAqcDBYAsB0zRMCj5cY5zmowRHy1KgAYwfrRKRHHPXvW8iLZAoc9KVFFAec0qoxkvzS6DPNN1enPr6msiJE4PqPWmbGOe1Oe1RPX7xUNDHhTXSyk2S7D+90rg3yn+f0xUFfByOo4qMtxyiymT96oRvlXT2qVtMJ4+cbsciuUyGM715HehsVaw8MoPE9uLixdevcj1rz3TJ30K7e3nL+TuLQzE8bf4SPUdK9Mu3VgeOtZu7sYpCcqXyehGaStrcpcHR0mr8mLi+mV7eIrWWQtBC8gAyXI2rk+/f8K2fho7dHicADzMvxnuff6VmrfQWkIUoEj64Fau1XybZIeyKBRY1uPLBW2qfC6KTxtq72GnrBAT9ou28qM/wDu33D+eKq/DVstnEpXlE5Y+9Lxkqtq9gzuBsicquCc8iu9lGTYttUgt3HelJybswdmmEYaRf9B1lIHhnmLY3ydaovDtisXi/UL9dxDoF9h/fFWMv/AJazRW+Ek5PqK5aDIxnupCCFJGM9zUg/Wl8FSj+FN/JtYDuTNLfg1ysD+yU+1NM2GNdGL4PPyWJMxf8Ai1p0c+kR6oG2y2rhD/zK5Ax+JFeXl0UFiQEGOvPJ/wBh+FexeNIRf+H7mz3lWl2gEeoOf0rzfTPA15dzhJcHoMkYUD9aDOrdPKO5ofEFTptrfK6KjTbS/wDE9/Fp9gCI5TmST0Uda940LRrbQdLhtIFAWJcE9z7muPhfw7Y+HbMiFAZnH7SUjk+1c/EeqC3tzCmPNlPb91e9M1wUTmXXz1VmDMaqEvtQubkgYY5Huortb2UazcD97PHv/vUbdNqKD024HvVhCn7Tn0wO/P8AYo2B9vatqOqwAEcDI7/nXcQD6kHNPDytd0x3qgDkyKxADBHHWukSjJJzmpAA9BU1GKozkgBxwR99Kuu1uxpqhWS2A5JpsDtTBuKRNUKipsf1pE459Ki3AH86hMnNuetcWGDkYUAEk47mu7AYY+gzQ0hYBUjHpVhIjGZoH3Keh6eo9KsrS+juU7cZDL6EVTz5/Zhk3MxwR2WgJGkVt6syMpHT261loI6FYv3NJc2ME+GAFcVsUQ/IB91VFr4gVGK3SlVX/iDp+H4fjV1b30M6lo5EdRxwelUkJ2UWQfqQ/kADgCubW/Oc0YJEI54pxtPQirayC5M9q+iwX4QzL8cedrA4I9aBtjHbbonV8DgGtcY89s0FcadG4JAAJ68UvKlN5HKtZKMdj6MJ4j1NbZBjDZ4UA8k+lGeHEc6dGH+J2JkbjoT2+7pVjf8AhoTPvRYy2e+BROnaVNagB3iUem+l1p2pNjtuujKpQiW1mNsHPpVZfXyQsSzAAVZjy0i2tPyf4V6UALWwWXzDG08nrK+QPuptRlhI5Lllldb21xqMvmMD5Y6Airq2trezHQFyOlcZ75IYy0jrEg+6s7qXiRQGWwGWOf2h46dcCjJBKqLLXwi71jWktFxkPL0SMfrWQlle4nlluWLM6glvTp0oaR2kl3s7M5PxFuST1ouJS6Djjbg+/XmtJHVqojTH9wy3BZEB6hSfxNWEC7pV7HGcfhQkK/Iv1HT3qwt8CUZ45rRiTOqDBGO5ruo5qIXDZ7V2Ve9ZBCArpimxUhzVFC2+9KlSqFBw4pZwPenxTYz3wKoXGZuoFSbkflUQoByMkeppyMjjp6VCETyhrj5ZBOcEn0Ndn4XFRIGASPrVlpgTcDIOMk49vT8jQU8QG9scDpnp1Y/nirV49x5AyOeK4zwBtwPU4UH64/QVA0Z4KG4jI3bj0Pp6/wC1VZEkchljdkcOBuU4OD+daSaEDoOGIxnk98frVZPbAqQqnj4z+B/U1Q9XYmsM4ReIL+2bbLsmTd8O4YOPqKLj8YW4UfaYJ4T6qNw/r/Kqua2YFO/Kk/yoKWHecYwM/wBatElpaZro1I8V6Uy5N6V/60Zf0qf/AIm0j/5CE/8AdWIltVZdwXP+9cXtuRxWsAX4dW/c2z+K9FXOb+NvopP5ChJvGWkj/Ta4k/6IiB/PFZI2nPQUha47CrwT/H1ovpvGZfP2Owc44zK+OfoKDm17Vrg7POSHJwfLTkfeaBWDgjHWu6J1Y+gP86geGlqh7EMyzy7p5ZJZAGG92JPtUwvIHtz+R/SuvkH7RgA5J/Gu8MG8KxztPJ9f74qBdySwhooizse+8Y/CrCCLZDgDgHj6ZIFdIrT9ocj4fMxkfTFGJARGoc8bcnH1zUF5TJpCQW9iDRiL8WT09akkOJAT1zXYR/gKrIu5DBK6BeOKko4p6yDyMBjrk04FOM+tPjPUmoQbinp+BxilUIFA8dB/YpxTUqoAhDgVEDIzzSpVZYmHA+hpKAeDSpVCESSd2fX9aZj8QPoMj8DSpVCweVQUx0+HtQtxCgZwBwAv9aalVhoMr3iQoQR80XP38GgZYIzk4wTg8e5pUqyx2LAZolCv14B/WuMqKOg6UqVaQZHF0XNOFFKlVmiaIuen95rtHGpKA9MYpUqgOQbDAgkXA6UXBCixgAdGp6VWLyZYRxrnp+8P1rrGoK575xTUqpgGd9oAFdaVKqMscn4vuqQpUqoyKpHpTUqhBsD0pUqVQs//2Q==" },
        { name: "Imagawayaki",   price: 12000, img: "data:image/jpeg;base64,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" },
        { name: "Takoyaki",      price: 22000, img: "data:image/jpeg;base64,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" },
        { name: "Okonomiyaki",   price: 10000, img: "data:image/jpeg;base64,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" }
    ];

    const menuItems = [
        ...staticMenus,
        ...dynamicMenus.map(m => ({
            name: m.name,
            price: parseInt(m.price, 10) || 0,
            img: m.image_path && m.image_path !== '' ? m.image_path : 'https://images.unsplash.com/photo-1604908176997-1251884b08a3?auto=format&fit=crop&w=400&q=80'
        }))
    ];

    function displayMenu(items) {
        const menuContainer = document.getElementById('menuItems');
        menuContainer.innerHTML = '';
        items.forEach(item => {
            const menuItem = document.createElement('div');
            menuItem.className = "menu-item bg-white border border-gray-200 rounded-lg p-4 transition-all cursor-pointer flex flex-col gap-3";

            // Klik pada container juga menambah pesanan
            menuItem.onclick = () => addToOrder(item.name, item.price);

            menuItem.innerHTML = `
                <div class="flex items-center w-full">
                    <div class="bg-gray-100 p-3 rounded-xl mr-4 flex items-center justify-center">
                        <img src="${item.img}" alt="${item.name}" class="max-w-[110px] max-h-[110px] rounded-lg object-contain" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-medium text-gray-800">${item.name}</h3>
                        <p class="text-sm text-gray-600">Rp ${item.price.toLocaleString('id-ID')}</p>
                        ${getRatingHtml(item.name)}
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 w-full mt-2">
                    <button class="bg-green-600 hover:bg-green-700 text-white py-1.5 px-3 rounded-md text-sm font-semibold w-full sm:w-auto"
                        onclick="event.stopPropagation(); addToOrder('${item.name}', ${item.price});">
                        Bayar
                    </button>
                    <button class="bg-blue-500 hover:bg-blue-600 text-white py-1.5 px-3 rounded-md text-xs font-semibold w-full sm:w-auto"
                        onclick="event.stopPropagation(); openCommentsModal('${item.name}');">
                        Lihat Komentar
                    </button>
                </div>
            `;
            menuContainer.appendChild(menuItem);
        });
    }

    function getRatingHtml(name) {
        const data = ratingSummary[name];
        if (!data || !data.count) return '<p class="text-xs text-gray-400 mt-1">Belum ada rating</p>';

        const avg = data.avg;
        const fullStars = Math.round(avg);
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            stars += `<span class="${i <= fullStars ? 'text-yellow-400' : 'text-gray-300'}">★</span>`;
        }
        return `<div class="flex items-center gap-2 mt-1 text-xs"><div class="flex">${stars}</div><span class="text-gray-500">(${data.count})</span></div>`;
    }

    function getCommentsHtml(name) {
        const list = commentsByMenu[name];
        if (!list || !list.length) {
            return '<p class="text-[11px] text-gray-400 mt-1 italic">Belum ada komentar, jadilah yang pertama memberi ulasan.</p>';
        }

        const preview = list.slice(0, 3);

        const items = preview.map(c => {
            const safeUser = (c.user || '').toString().substring(0, 20);
            const safeComment = (c.comment || '').toString().substring(0, 80);
            return `
                <div class="mt-1 pt-2 border-t border-gray-100 flex items-start gap-2">
                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 text-[10px] font-semibold text-amber-700">
                        ${safeUser.charAt(0).toUpperCase()}
                    </div>
                    <div class="flex-1">
                        <div class="text-[11px] font-semibold text-gray-800">${safeUser}</div>
                        <div class="text-[11px] text-gray-600 italic">"${safeComment}"</div>
                    </div>
                </div>
            `;
        }).join('');

        return `<div class="mt-1">${items}</div>`;
    }

    function openCommentsModal(menuName) {
        const list = commentsByMenu[menuName] || [];
        const titleEl = document.getElementById('commentsMenuTitle');
        const listEl = document.getElementById('commentsList');

        titleEl.textContent = menuName;

        if (!list.length) {
            listEl.innerHTML = '<p class="text-sm text-gray-500 italic text-center py-4">Belum ada komentar untuk menu ini. Jadilah yang pertama memberikan pendapatmu.</p>';
        } else {
            const items = list.map(c => {
                const safeUser = (c.user || '').toString().substring(0, 30);
                const safeComment = (c.comment || '').toString();
                const rating = c.rating || 0;
                let stars = '';
                for (let i = 1; i <= 5; i++) {
                    stars += `<span class="${i <= rating ? 'text-yellow-400' : 'text-gray-300'} text-[11px]">★</span>`;
                }
                return `
                    <div class="mb-3 pb-3 border-b border-gray-100 last:border-b-0 last:pb-0">
                        <div class="flex items-start gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-[11px] font-semibold text-amber-800">
                                ${safeUser.charAt(0).toUpperCase()}
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-semibold text-gray-900 text-xs sm:text-sm">${safeUser}</span>
                                    <span class="flex items-center gap-1">${stars}</span>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-700 leading-relaxed">${safeComment}</p>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            listEl.innerHTML = items;
        }

        document.getElementById('commentsSection').classList.remove('hidden');
    }

    function searchMenu() {
        const query = document.getElementById('searchInput').value.toLowerCase();
        const filteredItems = menuItems.filter(item => item.name.toLowerCase().includes(query));
        displayMenu(filteredItems);
    }

    function addToOrder(itemName, price) {
        order.push({ name: itemName, price: price });
        updateOrderSummary();
    }

    function updateOrderSummary() {
        let summary = '';
        let total = 0;
        const count = {};

        order.forEach(item => {
            count[item.name] = (count[item.name] || 0) + 1;
        });

        for (let itemName in count) {
            const menuItem = menuItems.find(menuItem => menuItem.name === itemName);
            if (!menuItem) continue;
            let subtotal = menuItem.price * count[itemName];
            summary += `
                <div class="flex items-center justify-between py-2 border-b border-gray-100 gap-2">
                    <div class="flex-1">
                        <span class="font-medium text-gray-800">${itemName}</span>
                        <span class="text-sm text-gray-500"> x ${count[itemName]}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="px-2 py-0.5 text-xs rounded-md border border-gray-300 text-gray-700 hover:bg-gray-100"
                                onclick="decreaseItem('${itemName.replace(/'/g, "\\'")}')">
                            -
                        </button>
                        <span class="text-sm font-semibold text-gray-800">Rp ${subtotal.toLocaleString('id-ID')}</span>
                    </div>
                </div>
            `;
            total += subtotal;
        }

        document.getElementById('order-summary').innerHTML = order.length > 0 ? summary : '<p class="text-gray-500 italic">Belum ada pesanan</p>';
        document.getElementById('totalHarga').innerText = "Rp " + total.toLocaleString('id-ID');
    }

    function decreaseItem(itemName) {
        // Cari index pertama item dengan nama tersebut di array order
        const index = order.findIndex(item => item.name === itemName);
        if (index !== -1) {
            order.splice(index, 1); // hapus satu item
            updateOrderSummary();   // refresh ringkasan dan total
        }
    }

    function payNow() {
        if (order.length === 0) {
            alert('Silakan tambahkan pesanan terlebih dahulu');
            return;
        }

        const count = {};
        order.forEach(item => {
            count[item.name] = (count[item.name] || 0) + 1;
        });

        const orderForServer = [];
        let total = 0;

        for (let itemName in count) {
            const menuItem = menuItems.find(menuItem => menuItem.name === itemName);
            if (!menuItem) continue;
            const quantity = count[itemName];
            const subtotal = menuItem.price * quantity;
            total += subtotal;
            orderForServer.push({
                name: itemName,
                price: menuItem.price,
                quantity: quantity
            });
        }

        fetch('proses_pembayaran.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order: orderForServer,
                total: total
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                lastPaymentTotal = total;

                // Simpan daftar menu yang baru saja dibayar (unik) untuk ditawarkan rating
                const uniqueMenus = Array.from(new Set(orderForServer.map(o => o.name)));
                pendingRatingMenus = uniqueMenus;

                document.getElementById('qrisTotal').innerText = "Rp " + total.toLocaleString('id-ID');
                document.getElementById('qrisSection').classList.remove('hidden');
            } else {
                alert(data.message || 'Terjadi kesalahan saat memproses pembayaran');
            }
        })
        .catch(() => {
            alert('Gagal terhubung ke server pembayaran');
        });
    }

    function closeQrisAndOpenRating() {
        // Tutup popup QRIS
        document.getElementById('qrisSection').classList.add('hidden');

        // Setelah QRIS ditutup, jika ada menu yang baru dibayar, tawarkan rating
        openNextPendingRating();
    }

    function openRatingModal(menuName) {
        ratingMenuName = menuName;
        selectedRating = 0;
        document.getElementById('ratingMenuTitle').innerText = menuName;
        document.getElementById('ratingComment').value = '';
        updateStarDisplay(0);
        document.getElementById('ratingSection').classList.remove('hidden');
    }

    function openNextPendingRating() {
        if (!pendingRatingMenus || pendingRatingMenus.length === 0) return;

        const nextMenu = pendingRatingMenus.shift();
        if (nextMenu) {
            openRatingModal(nextMenu);
        }
    }

    function setRating(value) {
        selectedRating = value;
        updateStarDisplay(value);
    }

    function updateStarDisplay(value) {
        const stars = document.querySelectorAll('#ratingStars button');
        stars.forEach((star, index) => {
            if (index < value) {
                star.classList.add('text-yellow-400');
                star.classList.remove('text-gray-400');
            } else {
                star.classList.add('text-gray-400');
                star.classList.remove('text-yellow-400');
            }
        });
    }

    function submitRating() {
        if (!ratingMenuName || selectedRating === 0) {
            alert('Silakan pilih rating bintang terlebih dahulu');
            return;
        }

        const comment = document.getElementById('ratingComment').value;

        fetch('simpan_rating.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                menu_item_name: ratingMenuName,
                rating: selectedRating,
                comment: comment
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Terima kasih atas rating dan komentar Anda!');

                // Jika masih ada menu lain yang menunggu rating dari transaksi barusan,
                // buka popup rating berikutnya. Jika tidak, tutup.
                if (pendingRatingMenus && pendingRatingMenus.length > 0) {
                    openNextPendingRating();
                } else {
                    document.getElementById('ratingSection').classList.add('hidden');
                }
            } else {
                alert(data.message || 'Gagal menyimpan rating');
            }
        })
        .catch(() => {
            alert('Gagal terhubung ke server rating');
        });
    }

    if (isAdmin) {
        document.getElementById('newFoodForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const foodName = document.getElementById('foodName').value;
            const foodPrice = parseInt(document.getElementById('foodPrice').value);
            const foodImage = document.getElementById('foodImage').files[0];

            const reader = new FileReader();
            reader.onload = function(event) {
                const newItem = {
                    name: foodName,
                    price: foodPrice,
                    img: event.target.result
                };
                menuItems.push(newItem);
                displayMenu(menuItems);
                document.getElementById('newFoodForm').reset();
            };
            reader.readAsDataURL(foodImage);
        });
    }

    displayMenu(menuItems);
</script>
</body>
</html>
