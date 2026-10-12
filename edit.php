<?php
require_once __DIR__ . '/includes/helpers.php';
$idParam = isset($_GET['id']) ? '?id=' . (int) $_GET['id'] : '';
header('Location: ' . base_url('pages/products/edit.php' . $idParam));
exit;
