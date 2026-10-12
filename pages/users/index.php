<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_admin();

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
        header('Location: ' . base_url('pages/users/index.php'));
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $currentUser = current_user();

    if ($id === (int) $currentUser['id']) {
        set_flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri.');
        header('Location: ' . base_url('pages/users/index.php'));
        exit;
    }

    // Check if user has recorded transactions
    $checkMov = $pdo->prepare('SELECT COUNT(*) FROM stock_movements WHERE user_id = ?');
    $checkMov->execute([$id]);
    if ((int) $checkMov->fetchColumn() > 0) {
        set_flash('danger', 'Pengguna tidak dapat dihapus karena tercatat dalam histori audit transaksi.');
        header('Location: ' . base_url('pages/users/index.php'));
        exit;
    }

    $delStmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $delStmt->execute([$id]);
    set_flash('success', 'Pengguna berhasil dihapus.');
    header('Location: ' . base_url('pages/users/index.php'));
    exit;
}

$sql = '
    SELECT u.id, u.username, u.role, u.created_at, p.full_name
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    ORDER BY u.id ASC
';
$users = $pdo->query($sql)->fetchAll();

$pageTitle = 'Manajemen Pengguna - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="font-playfair fs-2 mb-1 text-dark">Manajemen Pengguna</h1>
        <p class="text-secondary small mb-0">Kelola akun administrator dan staf operasional toko bunga.</p>
    </div>
    <div>
        <a href="<?= base_url('pages/users/create.php') ?>" class="btn btn-pastel-primary shadow-sm">
            + Tambah Pengguna
        </a>
    </div>
</div>

<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-pastel border-0 py-2 px-3 small mb-4" role="alert">
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>

<div class="card card-pastel border-0 shadow-sm p-4 mb-4">
    <div class="table-responsive">
        <table class="table table-pastel align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Nama Lengkap</th>
                    <th>Username</th>
                    <th>Peran (Role)</th>
                    <th>Terdaftar Sejak</th>
                    <th class="text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $idx => $u): ?>
                    <tr>
                        <td class="text-secondary"><?= $idx + 1 ?></td>
                        <td class="fw-semibold text-dark">
                            <?= e($u['full_name'] ?: $u['username']) ?>
                            <?php if ($u['id'] === current_user()['id']): ?>
                                <span class="badge bg-light text-muted border ms-1">Anda</span>
                            <?php endif; ?>
                        </td>
                        <td class="font-monospace text-secondary">@<?= e($u['username']) ?></td>
                        <td>
                            <span class="badge-role badge-role-<?= e($u['role']) ?>">
                                <?= e(ucfirst($u['role'])) ?>
                            </span>
                        </td>
                        <td class="text-secondary small"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td class="text-end">
                            <a href="<?= base_url('pages/users/edit.php?id=' . $u['id']) ?>" class="btn btn-pastel-outline btn-sm me-1" title="Edit">
                                Edit
                            </a>
                            <?php if ($u['id'] !== current_user()['id']): ?>
                                <form method="POST" action="<?= base_url('pages/users/index.php') ?>" class="d-inline" onsubmit="return openDeleteModal(this, '<?= e($u['username']) ?>');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-pastel-danger btn-sm" title="Hapus">
                                        Hapus
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
