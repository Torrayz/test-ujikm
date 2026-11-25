<?php
declare(strict_types=1);

function render_layout_start(string $title, string $page, array $user, ?array $flash): void
{
    $role = $user['role'];
    ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($title) ?> | Perpustakaan UNPAM</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/app.js" defer></script>
</head>
<body>
<div class="layout" id="appLayout">
  <aside class="sidebar" id="sidebar">
    <div class="brand-row">
      <div class="brand">PERPUS UNPAM<span>Library Management System</span></div>
      <button type="button" class="icon-btn mobile-only" id="closeMenu" aria-label="Tutup menu">✕</button>
    </div>
    <div class="nav-title">MENU UTAMA</div>
    <nav class="nav">
      <?php if ($role === 'petugas'): ?>
        <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard"><?= svg_icon('dashboard') ?><span>Dashboard</span></a>
        <a class="<?= $page === 'books' ? 'active' : '' ?>" href="index.php?page=books"><?= svg_icon('books') ?><span>Data Buku</span></a>
        <a class="<?= $page === 'members' ? 'active' : '' ?>" href="index.php?page=members"><?= svg_icon('members') ?><span>Data Anggota</span></a>
        <a class="<?= $page === 'loans' ? 'active' : '' ?>" href="index.php?page=loans"><?= svg_icon('loans') ?><span>Peminjaman</span></a>
        <a class="<?= $page === 'catalog' ? 'active' : '' ?>" href="index.php?page=catalog"><?= svg_icon('catalog') ?><span>Katalog Buku</span></a>
      <?php else: ?>
        <a class="<?= $page === 'catalog' ? 'active' : '' ?>" href="index.php?page=catalog"><?= svg_icon('catalog') ?><span>Katalog Buku</span></a>
        <a class="<?= $page === 'history' ? 'active' : '' ?>" href="index.php?page=history"><?= svg_icon('history') ?><span>Riwayat Saya</span></a>
      <?php endif; ?>
      <a class="<?= $page === 'settings' ? 'active' : '' ?>" href="index.php?page=settings"><?= svg_icon('settings') ?><span>Pengaturan Akun</span></a>
    </nav>
    <div class="sidebar-foot">
      <div class="user-chip">
        <strong><?= e($user['name']) ?></strong>
        <small><?= $role === 'petugas' ? 'Petugas Perpustakaan' : 'Anggota Perpustakaan' ?></small>
      </div>
    </div>
  </aside>
  <div class="backdrop" id="sidebarBackdrop"></div>

  <main class="main">
    <header class="top">
      <div class="top-left">
        <button type="button" class="icon-btn mobile-only" id="openMenu" aria-label="Buka menu">☰</button>
        <div>
          <small>Perpustakaan / <?= e($title) ?></small>
          <h1><?= e($title) ?></h1>
        </div>
      </div>
      <div class="actions">
        <span class="user-meta hide-sm"><strong><?= e($user['name']) ?></strong> · <span class="role-pill"><?= $role === 'petugas' ? 'Petugas' : 'Anggota' ?></span></span>
        <form method="post" class="inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="logout">
          <button class="btn" type="submit">Keluar</button>
        </form>
      </div>
    </header>
    <section class="content">
      <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']) ?>" role="alert">
          <span><?= e($flash['message']) ?></span>
          <button type="button" class="alert-close" aria-label="Tutup pesan">&times;</button>
        </div>
      <?php endif; ?>
    <?php
}

function render_layout_end(): void
{
    ?>
      <div class="footer">© <?= date('Y') ?> Sistem Informasi Perpustakaan Universitas Pamulang · PHP Native &amp; MySQL</div>
    </section>
  </main>
</div>
</body>
</html>
    <?php
}
