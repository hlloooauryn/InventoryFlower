<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_login();

$typeFilter = $_GET['type'] ?? '';
$productIdFilter = isset($_GET['product_id']) && $_GET['product_id'] !== '' ? (int) $_GET['product_id'] : null;
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$where = [];
$params = [];

if (in_array($typeFilter, ['IN', 'OUT'], true)) {
    $where[] = 'm.type = ?';
    $params[] = $typeFilter;
}

if ($productIdFilter !== null) {
    $where[] = 'm.product_id = ?';
    $params[] = $productIdFilter;
}

if ($startDate !== '') {
    $where[] = 'DATE(m.created_at) >= ?';
    $params[] = $startDate;
}

if ($endDate !== '') {
    $where[] = 'DATE(m.created_at) <= ?';
    $params[] = $endDate;
}

$whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM stock_movements m $whereClause";
$pagination = paginate($pdo, $countSql, $params, $page, $perPage);

$sql = "
    SELECT 
        m.id, m.type, m.quantity, m.note, m.created_at,
        p.name AS product_name, p.sku AS product_sku,
        u.username, COALESCE(up.full_name, u.username) AS user_fullname
    FROM stock_movements m
    JOIN products p ON p.id = m.product_id
    JOIN users u ON u.id = m.user_id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    $whereClause
    ORDER BY m.created_at DESC, m.id DESC
    LIMIT ? OFFSET ?
";

$fetchParams = $params;
$fetchParams[] = $pagination['per_page'];
$fetchParams[] = $pagination['offset'];

$stmt = $pdo->prepare($sql);
$stmt->execute($fetchParams);
$movements = $stmt->fetchAll();

// Product list for filter dropdown
$productsList = $pdo->query('SELECT id, name, sku FROM products ORDER BY name ASC')->fetchAll();

$pageTitle = 'Riwayat Transaksi Stok - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="font-playfair fs-2 mb-1 text-dark">Riwayat Transaksi Stok</h1>
        <p class="text-secondary small mb-0">Audit jejak pergerakan barang masuk dan keluar secara kronologis.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('pages/transactions/stock_in.php') ?>" class="btn btn-pastel-primary btn-sm">
            + Barang Masuk
        </a>
        <a href="<?= base_url('pages/transactions/stock_out.php') ?>" class="btn btn-pastel-secondary btn-sm">
            - Barang Keluar
        </a>
    </div>
</div>

<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-pastel border-0 py-2 px-3 small mb-4" role="alert">
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>

<!-- Filter Toolbar -->
<div class="card card-pastel border-0 shadow-sm p-4 mb-4">
    <form method="GET" action="<?= base_url('pages/transactions/history.php') ?>" class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-secondary">Tipe Mutasi</label>
            <select name="type" class="form-select form-control-pastel">
                <option value="">Semua Tipe (Masuk & Keluar)</option>
                <option value="IN" <?= $typeFilter === 'IN' ? 'selected' : '' ?>>Barang Masuk (IN)</option>
                <option value="OUT" <?= $typeFilter === 'OUT' ? 'selected' : '' ?>>Barang Keluar (OUT)</option>
            </select>
        </div>

        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-secondary">Produk</label>
            <select name="product_id" class="form-select form-control-pastel">
                <option value="">Semua Produk</option>
                <?php foreach ($productsList as $pl): ?>
                    <option value="<?= $pl['id'] ?>" <?= $productIdFilter === (int)$pl['id'] ? 'selected' : '' ?>>
                        [<?= e($pl['sku']) ?>] <?= e($pl['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold text-secondary">Dari Tanggal</label>
            <input type="date" name="start_date" class="form-control form-control-pastel" value="<?= e($startDate) ?>">
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold text-secondary">Sampai Tanggal</label>
            <input type="date" name="end_date" class="form-control form-control-pastel" value="<?= e($endDate) ?>">
        </div>

        <div class="col-12 col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-pastel-secondary flex-fill">Filter</button>
            <?php if ($typeFilter !== '' || $productIdFilter !== null || $startDate !== '' || $endDate !== ''): ?>
                <a href="<?= base_url('pages/transactions/history.php') ?>" class="btn btn-pastel-outline">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- History Table Card -->
<div class="card card-pastel border-0 shadow-sm p-4 mb-4">
    <div class="table-responsive">
        <table class="table table-pastel align-middle mb-0">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Tipe</th>
                    <th>SKU & Produk</th>
                    <th>Jumlah</th>
                    <th>Dicatat Oleh</th>
                    <th>Keterangan / Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            Belum ada riwayat transaksi yang tercatat sesuai kriteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td class="text-secondary small font-monospace">
                                <?= date('d M Y, H:i', strtotime($m['created_at'])) ?>
                            </td>
                            <td>
                                <?php if ($m['type'] === 'IN'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-bold">
                                        &darr; MASUK
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fw-bold">
                                        &uarr; KELUAR
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace me-1"><?= e($m['product_sku']) ?></span>
                                <span class="fw-semibold text-dark"><?= e($m['product_name']) ?></span>
                            </td>
                            <td>
                                <span class="fw-bold <?= $m['type'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                                    <?= $m['type'] === 'IN' ? '+' : '-' ?><?= (int) $m['quantity'] ?> unit
                                </span>
                            </td>
                            <td class="small">
                                <span class="text-dark fw-semibold"><?= e($m['user_fullname']) ?></span>
                                <span class="text-muted d-block font-monospace" style="font-size: 0.75rem;">@<?= e($m['username']) ?></span>
                            </td>
                            <td class="text-secondary small">
                                <?= e($m['note'] ?: '-') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <nav class="d-flex justify-content-between align-items-center mt-4 pt-2 border-top border-pastel" aria-label="Navigasi Halaman Transaksi">
            <span class="small text-muted">
                Menampilkan <?= count($movements) ?> dari <?= $pagination['total'] ?> transaksi
            </span>
            <ul class="pagination pagination-sm pagination-pastel mb-0">
                <li class="page-item <?= $pagination['current_page'] <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= build_query_string(['page' => $pagination['current_page'] - 1]) ?>">&laquo;</a>
                </li>
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                    <li class="page-item <?= $pagination['current_page'] === $i ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= build_query_string(['page' => $i]) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= build_query_string(['page' => $pagination['current_page'] + 1]) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
