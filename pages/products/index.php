<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_login();

// Filters & Sorting params
$search = trim($_GET['q'] ?? '');
$categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : null;
$stockFilter = $_GET['stock_filter'] ?? ''; // 'low' or ''
$sort = $_GET['sort'] ?? 'name';
$order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$allowedSortColumns = [
    'name' => 'p.name',
    'sku' => 'p.sku',
    'category' => 'c.name',
    'price' => 'p.price',
    'stock' => 'p.stock',
    'created_at' => 'p.created_at',
];
$sortColumn = $allowedSortColumns[$sort] ?? 'p.name';

// Build base conditions
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($categoryId !== null) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}

if ($stockFilter === 'low') {
    $where[] = 'p.stock <= p.minimum_stock';
}

$whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

// Count total records for pagination
$countSql = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id $whereClause";
$pagination = paginate($pdo, $countSql, $params, $page, $perPage);

// Fetch products
$sql = "
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    $whereClause
    ORDER BY $sortColumn $order
    LIMIT ? OFFSET ?
";

$fetchParams = $params;
$fetchParams[] = $pagination['per_page'];
$fetchParams[] = $pagination['offset'];

$stmt = $pdo->prepare($sql);
$stmt->execute($fetchParams);
$products = $stmt->fetchAll();

// Fetch categories for filter dropdown
$catStmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
$allCategories = $catStmt->fetchAll();

$pageTitle = 'Katalog Produk - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="font-playfair fs-2 mb-1 text-dark">Katalog Produk</h1>
        <p class="text-secondary small mb-0">Manajemen inventori bunga potong, buket, tanaman hias, dan aksesori.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (is_admin()): ?>
            <a href="<?= base_url('pages/products/create.php') ?>" class="btn btn-pastel-primary shadow-sm">
                + Tambah Produk
            </a>
        <?php endif; ?>
    </div>
</div>

<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-pastel border-0 py-2 px-3 small mb-4" role="alert">
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>

<!-- Filter & Search Toolbar -->
<div class="card card-pastel border-0 shadow-sm p-4 mb-4">
    <form method="GET" action="<?= base_url('pages/products/index.php') ?>" class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
            <input type="text" name="q" class="form-control form-control-pastel" placeholder="Cari nama atau SKU..." value="<?= e($search) ?>">
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <select name="category_id" class="form-select form-control-pastel">
                <option value="">Semua Kategori</option>
                <?php foreach ($allCategories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-2">
            <select name="stock_filter" class="form-select form-control-pastel">
                <option value="">Semua Stok</option>
                <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Stok Kritis / Rendah</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-pastel-secondary">Terapkan</button>
            <?php if ($search !== '' || $categoryId !== null || $stockFilter !== ''): ?>
                <a href="<?= base_url('pages/products/index.php') ?>" class="btn btn-pastel-outline ms-1">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="card card-pastel border-0 shadow-sm p-4 mb-4">
    <div class="table-responsive">
        <table class="table table-pastel align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 70px;">Foto</th>
                    <th>
                        <a href="?<?= build_query_string(['sort' => 'sku', 'order' => $sort === 'sku' && $order === 'ASC' ? 'desc' : 'asc']) ?>" class="text-decoration-none text-dark">
                            SKU <?= $sort === 'sku' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="?<?= build_query_string(['sort' => 'name', 'order' => $sort === 'name' && $order === 'ASC' ? 'desc' : 'asc']) ?>" class="text-decoration-none text-dark">
                            Nama Produk <?= $sort === 'name' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="?<?= build_query_string(['sort' => 'category', 'order' => $sort === 'category' && $order === 'ASC' ? 'desc' : 'asc']) ?>" class="text-decoration-none text-dark">
                            Kategori <?= $sort === 'category' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="?<?= build_query_string(['sort' => 'price', 'order' => $sort === 'price' && $order === 'ASC' ? 'desc' : 'asc']) ?>" class="text-decoration-none text-dark">
                            Harga <?= $sort === 'price' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="?<?= build_query_string(['sort' => 'stock', 'order' => $sort === 'stock' && $order === 'ASC' ? 'desc' : 'asc']) ?>" class="text-decoration-none text-dark">
                            Stok <?= $sort === 'stock' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                        </a>
                    </th>
                    <th>Status</th>
                    <th class="text-end" style="min-width: 170px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            Tidak ada produk yang cocok dengan pencarian atau filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $prod): ?>
                        <?php 
                            $isLowStock = $prod['stock'] <= $prod['minimum_stock'];
                        ?>
                        <tr class="<?= $isLowStock ? 'table-warning-soft' : '' ?>">
                            <td>
                                <?php if (!empty($prod['image'])): ?>
                                    <img src="<?= base_url('assets/product/' . $prod['image']) ?>" alt="<?= e($prod['name']) ?>" class="rounded-3 shadow-sm" style="width: 48px; height: 48px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-3 bg-light d-flex align-items-center justify-content-center text-muted" style="width: 48px; height: 48px; font-size: 1.2rem;">
                                        🌸
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace"><?= e($prod['sku']) ?></span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark d-block"><?= e($prod['name']) ?></span>
                                <small class="text-muted">Min: <?= (int)$prod['minimum_stock'] ?> unit</small>
                            </td>
                            <td>
                                <span class="badge bg-pastel-mint text-dark px-2 py-1">
                                    <?= e($prod['category_name'] ?? 'Tanpa Kategori') ?>
                                </span>
                            </td>
                            <td class="fw-semibold text-dark">
                                <?= format_rupiah((float) $prod['price']) ?>
                            </td>
                            <td>
                                <span class="fw-bold <?= $isLowStock ? 'text-danger' : 'text-success' ?>">
                                    <?= (int) $prod['stock'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($prod['stock'] <= 0): ?>
                                    <span class="badge bg-danger">Habis</span>
                                <?php elseif ($isLowStock): ?>
                                    <span class="badge bg-warning text-dark">Kritis</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Aman</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-pastel-outline btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Transaksi
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-pastel">
                                        <li>
                                            <a class="dropdown-item" href="<?= base_url('pages/transactions/stock_in.php?product_id=' . $prod['id']) ?>">
                                                + Barang Masuk
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="<?= base_url('pages/transactions/stock_out.php?product_id=' . $prod['id']) ?>">
                                                - Barang Keluar
                                            </a>
                                        </li>
                                    </ul>
                                </div>

                                <?php if (is_admin()): ?>
                                    <a href="<?= base_url('pages/products/edit.php?id=' . $prod['id']) ?>" class="btn btn-pastel-outline btn-sm" title="Edit">
                                        Edit
                                    </a>
                                    <form method="POST" action="<?= base_url('pages/products/delete.php') ?>" class="d-inline" onsubmit="return openDeleteModal(this, '<?= e($prod['name']) ?>');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                                        <button type="submit" class="btn btn-pastel-danger btn-sm" title="Hapus">
                                            Hapus
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <nav class="d-flex justify-content-between align-items-center mt-4 pt-2 border-top border-pastel" aria-label="Navigasi Halaman">
            <span class="small text-muted">
                Menampilkan <?= count($products) ?> dari <?= $pagination['total'] ?> produk
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
