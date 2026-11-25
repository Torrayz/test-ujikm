<?php
declare(strict_types=1);

$stats = [
    [
        'label' => 'Judul Buku',
        'value' => (int) $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn(),
        'sub' => 'Total koleksi terdaftar',
    ],
    [
        'label' => 'Total Stok Tersedia',
        'value' => (int) $pdo->query("SELECT COALESCE(SUM(stock), 0) FROM books")->fetchColumn(),
        'sub' => 'Eksemplar siap dipinjam',
    ],
    [
        'label' => 'Anggota Aktif',
        'value' => (int) $pdo->query("SELECT COUNT(*) FROM members WHERE active = 1")->fetchColumn(),
        'sub' => (int) $pdo->query("SELECT COUNT(*) FROM members WHERE active = 0")->fetchColumn() . ' anggota nonaktif',
    ],
    [
        'label' => 'Sedang Dipinjam',
        'value' => (int) $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'dipinjam'")->fetchColumn(),
        'sub' => (int) $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'dikembalikan'")->fetchColumn() . ' transaksi selesai',
    ],
];

$recentLoans = $pdo->query(
    "SELECT l.id, l.loaned_at, u.name AS member_name, m.member_number, b.title AS book_title
     FROM loans l
     JOIN members m ON m.id = l.member_id
     JOIN users u ON u.id = m.user_id
     JOIN books b ON b.id = l.book_id
     WHERE l.status = 'dipinjam'
     ORDER BY l.loaned_at ASC, l.id ASC
     LIMIT 5"
)->fetchAll();

$lowStockBooks = $pdo->query(
    "SELECT id, title, author, stock
     FROM books
     WHERE stock <= 2
     ORDER BY stock ASC, title ASC
     LIMIT 6"
)->fetchAll();
?>
<div class="panel">
  <h2>Selamat bekerja, <?= e($user['name']) ?>.</h2>
  <p class="muted">Ringkasan pengelolaan koleksi, keanggotaan, dan transaksi peminjaman perpustakaan.</p>
</div>

<div class="stats">
  <?php foreach ($stats as $s): ?>
    <article class="stat">
      <span><?= e($s['label']) ?></span>
      <strong><?= number_format($s['value']) ?></strong>
      <small class="muted"><?= e($s['sub']) ?></small>
    </article>
  <?php endforeach; ?>
</div>

<div class="split-2">
  <div class="panel">
    <div class="row">
      <div>
        <h3>Peminjaman Aktif Terbaru</h3>
        <p class="muted">Daftar buku yang belum dikembalikan.</p>
      </div>
      <a class="btn small" href="index.php?page=loans">Lihat Semua</a>
    </div>
    <div class="table-wrap">
      <table class="compact">
        <thead>
          <tr>
            <th>Anggota</th>
            <th>Buku</th>
            <th>Pinjam</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentLoans as $l): ?>
            <tr>
              <td><strong><?= e($l['member_name']) ?></strong><small><?= e($l['member_number']) ?></small></td>
              <td><?= e($l['book_title']) ?></td>
              <td><?= e(format_date($l['loaned_at'])) ?><small><?= days_elapsed($l['loaned_at']) ?> hari</small></td>
              <td>
                <form method="post" class="inline" onsubmit="return confirm('Konfirmasi pengembalian buku ini?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="return">
                  <input type="hidden" name="loan_id" value="<?= (int) $l['id'] ?>">
                  <input type="hidden" name="return_to" value="index.php?page=dashboard">
                  <button class="btn success small" type="submit">Kembali</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recentLoans): ?>
            <tr><td colspan="4" class="muted">Tidak ada peminjaman aktif saat ini.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="row">
      <div>
        <h3>Stok Buku Menipis / Habis</h3>
        <p class="muted">Koleksi dengan sisa stok 2 eksemplar atau kurang.</p>
      </div>
      <a class="btn small" href="index.php?page=books&stock=empty">Kelola Stok</a>
    </div>
    <div class="table-wrap">
      <table class="compact">
        <thead>
          <tr>
            <th>Judul &amp; Penulis</th>
            <th>Stok</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lowStockBooks as $b): ?>
            <tr>
              <td><strong><?= e($b['title']) ?></strong><small><?= e($b['author']) ?></small></td>
              <td>
                <span class="badge <?= (int) $b['stock'] === 0 ? 'danger' : 'pending' ?>">
                  <?= (int) $b['stock'] === 0 ? 'Habis' : 'Sisa ' . (int) $b['stock'] ?>
                </span>
              </td>
              <td><a class="btn small" href="index.php?page=books&edit=<?= (int) $b['id'] ?>">Ubah</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$lowStockBooks): ?>
            <tr><td colspan="3" class="muted">Semua koleksi memiliki stok yang mencukupi.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="panel">
  <h3>Akses Cepat</h3>
  <div class="grid">
    <a class="book quick-card" href="index.php?page=books">
      <strong>Kelola Buku</strong>
      <p>Tambah, ubah, cari, dan hapus koleksi buku perpustakaan.</p>
    </a>
    <a class="book quick-card" href="index.php?page=members">
      <strong>Kelola Anggota</strong>
      <p>Daftarkan mahasiswa/dosen, ubah data, atau atur status aktif.</p>
    </a>
    <a class="book quick-card" href="index.php?page=loans">
      <strong>Transaksi Peminjaman</strong>
      <p>Catat peminjaman dan proses pengembalian buku secara otomatis.</p>
    </a>
  </div>
</div>
