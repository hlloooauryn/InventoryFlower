<?php
require_once dirname(__DIR__) . '/includes/helpers.php';
require_login();

// 1. Metric: Total Products
$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

// 2. Metric: Total Inventory Value (Stok * Harga)
$totalValue = (float) $pdo->query('SELECT COALESCE(SUM(stock * price), 0) FROM products')->fetchColumn();

// 3. Metric: Low Stock Count
$lowStockCount = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE stock <= minimum_stock')->fetchColumn();

// 4. Metric: Monthly In & Out
$monthlyIn = (int) $pdo->query("
    SELECT COALESCE(SUM(quantity), 0) 
    FROM stock_movements 
    WHERE type = 'IN' 
      AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(created_at) = YEAR(CURRENT_DATE())
")->fetchColumn();

$monthlyOut = (int) $pdo->query("
    SELECT COALESCE(SUM(quantity), 0) 
    FROM stock_movements 
    WHERE type = 'OUT' 
      AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(created_at) = YEAR(CURRENT_DATE())
")->fetchColumn();

// 5. Query Low Stock Products
$lowStockItems = $pdo->query('
    SELECT p.id, p.sku, p.name, p.stock, p.minimum_stock, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.stock <= p.minimum_stock
    ORDER BY p.stock ASC, p.name ASC
    LIMIT 10
')->fetchAll();

// 6. Query Recent Transactions
$recentMovements = $pdo->query('
    SELECT m.id, m.type, m.quantity, m.note, m.created_at,
           p.name AS product_name, p.sku AS product_sku,
           u.username, COALESCE(up.full_name, u.username) AS user_fullname
    FROM stock_movements m
    JOIN products p ON p.id = m.product_id
    JOIN users u ON u.id = m.user_id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    ORDER BY m.created_at DESC, m.id DESC
    LIMIT 8
')->fetchAll();

$pageTitle = 'Dashboard Inventori - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="font-playfair fs-2 mb-1 text-dark">Ringkasan Inventori</h1>
        <p class="text-secondary small mb-0">Pantau pergerakan stok bunga, valuasi barang, dan peringatan stok kritis secara real-time.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('pages/transactions/stock_in.php') ?>" class="btn btn-pastel-primary btn-sm shadow-sm">
            + Barang Masuk
        </a>
        <a href="<?= base_url('pages/transactions/stock_out.php') ?>" class="btn btn-pastel-secondary btn-sm shadow-sm">
            - Barang Keluar
        </a>
    </div>
</div>

<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-pastel border-0 py-2 px-3 small mb-4" role="alert">
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>

<!-- Metric Cards Grid -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-pastel border-0 shadow-sm p-3 h-100">
            <span class="text-secondary small fw-semibold text-uppercase tracking-wider">Total Produk</span>
            <div class="d-flex align-items-baseline justify-content-between mt-2">
                <span class="fs-2 fw-bold text-dark font-playfair"><?= $totalProducts ?></span>
                <span class="badge bg-pastel-mint text-dark px-2 py-1">Item Aktif</span>
            </div>
            <small class="text-muted mt-2 d-block">Tersebar di berbagai kategori</small>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-pastel border-0 shadow-sm p-3 h-100">
            <span class="text-secondary small fw-semibold text-uppercase tracking-wider">Nilai Inventori</span>
            <div class="d-flex align-items-baseline justify-content-between mt-2">
                <span class="fs-4 fw-bold text-dark font-playfair"><?= format_rupiah($totalValue) ?></span>
            </div>
            <small class="text-muted mt-2 d-block">Valuasi stok tersedia (harga jual)</small>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-pastel border-0 shadow-sm p-3 h-100 <?= $lowStockCount > 0 ? 'border-start border-4 border-warning' : '' ?>">
            <span class="text-secondary small fw-semibold text-uppercase tracking-wider">Stok Kritis / Habis</span>
            <div class="d-flex align-items-baseline justify-content-between mt-2">
                <span class="fs-2 fw-bold <?= $lowStockCount > 0 ? 'text-danger' : 'text-success' ?> font-playfair"><?= $lowStockCount ?></span>
                <?php if ($lowStockCount > 0): ?>
                    <span class="badge bg-danger-subtle text-danger px-2 py-1">Perlu Restock</span>
                <?php else: ?>
                    <span class="badge bg-success-subtle text-success px-2 py-1">Aman</span>
                <?php endif; ?>
            </div>
            <small class="text-muted mt-2 d-block">Di bawah batas minimum stok</small>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-pastel border-0 shadow-sm p-3 h-100">
            <span class="text-secondary small fw-semibold text-uppercase tracking-wider">Aktivitas Bulan Ini</span>
            <div class="mt-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-secondary">Masuk (IN):</span>
                    <strong class="text-success">+<?= $monthlyIn ?> unit</strong>
                </div>
                <div class="d-flex justify-content-between small">
                    <span class="text-secondary">Keluar (OUT):</span>
                    <strong class="text-danger">-<?= $monthlyOut ?> unit</strong>
                </div>
            </div>
            <small class="text-muted mt-2 d-block">Periode <?= date('F Y') ?></small>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Low Stock Alert Table -->
    <div class="col-12 col-lg-5">
        <div class="card card-pastel border-0 shadow-sm p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="font-playfair fs-4 mb-0 text-dark">Peringatan Stok Rendah</h2>
                <span class="badge bg-warning text-dark"><?= count($lowStockItems) ?> Produk</span>
            </div>

            <?php if (empty($lowStockItems)): ?>
                <div class="text-center py-4 text-muted">
                    <p class="mb-1">🎉 Semua stok produk dalam batas aman!</p>
                    <small>Tidak ada produk yang menyentuh batas minimum.</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-pastel table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Sisa</th>
                                <th>Min</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStockItems as $item): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-dark d-block"><?= e($item['name']) ?></span>
                                        <small class="text-muted font-monospace"><?= e($item['sku']) ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-bold <?= $item['stock'] <= 0 ? 'text-danger' : 'text-warning' ?>">
                                            <?= (int) $item['stock'] ?>
                                        </span>
                                    </td>
                                    <td class="text-secondary small"><?= (int) $item['minimum_stock'] ?></td>
                                    <td class="text-end">
                                        <a href="<?= base_url('pages/transactions/stock_in.php?product_id=' . $item['id']) ?>" class="btn btn-pastel-primary btn-sm py-0 px-2" title="Restock">
                                            Restock
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Transactions Table -->
    <div class="col-12 col-lg-7">
        <div class="card card-pastel border-0 shadow-sm p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="font-playfair fs-4 mb-0 text-dark">Aktivitas Transaksi Terbaru</h2>
                <a href="<?= base_url('pages/transactions/history.php') ?>" class="btn btn-pastel-outline btn-sm">Lihat Semua &rarr;</a>
            </div>

            <?php if (empty($recentMovements)): ?>
                <div class="text-center py-4 text-muted">
                    <p class="mb-0">Belum ada aktivitas mutasi stok yang tercatat.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-pastel table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Tipe</th>
                                <th>Produk</th>
                                <th>Qty</th>
                                <th>Petugas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMovements as $rm): ?>
                                <tr>
                                    <td class="small text-secondary font-monospace"><?= date('d M, H:i', strtotime($rm['created_at'])) ?></td>
                                    <td>
                                        <?php if ($rm['type'] === 'IN'): ?>
                                            <span class="badge bg-success-subtle text-success py-0 px-1 font-monospace">IN</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger py-0 px-1 font-monospace">OUT</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><?= e($rm['product_name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold <?= $rm['type'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                                            <?= $rm['type'] === 'IN' ? '+' : '-' ?><?= (int) $rm['quantity'] ?>
                                        </span>
                                    </td>
                                    <td class="small text-secondary"><?= e($rm['user_fullname']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
