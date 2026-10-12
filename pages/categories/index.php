<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_login();

// Handle deletion via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    require_admin();
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
        header('Location: ' . base_url('pages/categories/index.php'));
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    // Check if category is used by products
    $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
    $checkStmt->execute([$id]);
    $usageCount = (int) $checkStmt->fetchColumn();

    if ($usageCount > 0) {
        set_flash('danger', "Kategori tidak dapat dihapus karena masih digunakan oleh $usageCount produk.");
    } else {
        $delStmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $delStmt->execute([$id]);
        set_flash('success', 'Kategori berhasil dihapus.');
    }
    header('Location: ' . base_url('pages/categories/index.php'));
    exit;
}

$search = trim($_GET['q'] ?? '');
$sql = '
    SELECT c.id, c.name, c.created_at, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE c.name LIKE ? ';
    $params[] = '%' . $search . '%';
}
$sql .= ' GROUP BY c.id, c.name, c.created_at ORDER BY c.name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

$pageTitle = 'Kategori Produk - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="font-playfair fs-2 mb-1 text-dark">Kategori Produk</h1>
        <p class="text-secondary small mb-0">Kelola kategori untuk klasifikasi produk bunga dan tanaman.</p>
    </div>
    <?php if (is_admin()): ?>
    <div>
        <a href="<?= base_url('pages/categories/create.php') ?>" class="btn btn-pastel-primary shadow-sm">
            + Tambah Kategori
        </a>
    </div>
    <?php endif; ?>
</div>

<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-pastel border-0 py-2 px-3 small mb-4" role="alert">
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>

<div class="card card-pastel border-0 shadow-sm p-4 mb-4">
    <form method="GET" action="<?= base_url('pages/categories/index.php') ?>" class="row g-2 align-items-center mb-3">
        <div class="col-12 col-md-5">
            <input type="text" name="q" class="form-control form-control-pastel" placeholder="Cari nama kategori..." value="<?= e($search) ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-pastel-secondary">Cari</button>
            <?php if ($search !== ''): ?>
                <a href="<?= base_url('pages/categories/index.php') ?>" class="btn btn-pastel-outline ms-1">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-pastel align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Nama Kategori</th>
                    <th>Jumlah Produk</th>
                    <th>Dibuat Pada</th>
                    <?php if (is_admin()): ?>
                    <th class="text-end" style="width: 140px;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="<?= is_admin() ? '5' : '4' ?>" class="text-center py-4 text-muted">
                            Tidak ada kategori ditemukan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $idx => $cat): ?>
                        <tr>
                            <td class="text-secondary"><?= $idx + 1 ?></td>
                            <td class="fw-semibold text-dark"><?= e($cat['name']) ?></td>
                            <td>
                                <span class="badge bg-pastel-mint text-dark px-2 py-1">
                                    <?= (int) $cat['product_count'] ?> produk
                                </span>
                            </td>
                            <td class="text-secondary small"><?= date('d M Y', strtotime($cat['created_at'])) ?></td>
                            <?php if (is_admin()): ?>
                            <td class="text-end">
                                <a href="<?= base_url('pages/categories/edit.php?id=' . $cat['id']) ?>" class="btn btn-pastel-outline btn-sm me-1" title="Edit">
                                    Edit
                                </a>
                                <form method="POST" action="<?= base_url('pages/categories/index.php') ?>" class="d-inline" onsubmit="return openDeleteModal(this, '<?= e($cat['name']) ?>');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn btn-pastel-danger btn-sm" title="Hapus">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
