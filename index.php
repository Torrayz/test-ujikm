<?php
declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/actions.php';
require __DIR__ . '/includes/layout.php';

send_security_headers();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post_request($pdo);
}

enforce_active_session($pdo);
$flash = get_flash();
$user = current_user();

if (!$user) {
    require __DIR__ . '/pages/login.php';
    exit;
}

$role = $user['role'];
$allowed = $role === 'petugas'
    ? ['dashboard', 'books', 'members', 'loans', 'catalog', 'settings']
    : ['catalog', 'history', 'settings'];

$page = (string) ($_GET['page'] ?? ($role === 'petugas' ? 'dashboard' : 'catalog'));
if (!in_array($page, $allowed, true)) {
    $page = $allowed[0];
}

$titles = [
    'dashboard' => 'Dashboard',
    'books' => 'Manajemen Buku',
    'members' => 'Data Anggota',
    'loans' => 'Peminjaman & Pengembalian',
    'catalog' => 'Katalog Buku',
    'history' => 'Riwayat Saya',
    'settings' => 'Pengaturan Akun',
];
$title = $titles[$page] ?? 'Perpustakaan';

render_layout_start($title, $page, $user, $flash);
require __DIR__ . '/pages/' . $page . '.php';
render_layout_end();
