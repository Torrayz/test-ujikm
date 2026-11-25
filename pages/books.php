<?php
declare(strict_types=1);

$editId = (int) ($_GET['edit'] ?? 0);
$editBook = null;
if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM books WHERE id = :id LIMIT 1");
    $st->execute(['id' => $editId]);
    $editBook = $st->fetch() ?: null;
    if (!$editBook) {
        echo '<div class="alert warning"><span>Data buku yang dipilih tidak ditemukan.</span><button type="button" class="alert-close" aria-label="Tutup">&times;</button></div>';
    }
}

$search = get_string('q', 150);
$stockFilter = get_string('stock', 20);
if (!in_array($stockFilter, ['', 'available', 'empty'], true)) {
    $stockFilter = '';
}
$pageNum = max(1, (int) ($_GET['p'] ?? 1));

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(b.title LIKE :q1 OR b.author LIKE :q2 OR COALESCE(b.isbn, '') LIKE :q3 OR COALESCE(b.publisher, '') LIKE :q4)";
    $like = '%' . $search . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}
if ($stockFilter === 'available') {
    $where[] = "b.stock > 0";
} elseif ($stockFilter === 'empty') {
    $where[] = "b.stock = 0";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM books b {$whereSql}");
$countStmt->execute($params);
$totalBooks = (int) $countStmt->fetchColumn();
$pg = paginate($totalBooks, $pageNum, 10);

$listStmt = $pdo->prepare(
    "SELECT b.*,
            (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.id AND l.status = 'dipinjam') AS active_loans
     FROM books b
     {$whereSql}
     ORDER BY b.title ASC, b.id DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $listStmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$listStmt->bindValue(':limit', $pg['per_page'], PDO::PARAM_INT);
$listStmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$listStmt->execute();
$books = $listStmt->fetchAll();
$currentReturn = page_url();
?>

<div class="panel">
  <h3><?= $editBook ? 'Ubah Data Buku' : 'Tambah Buku Baru' ?></h3>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_book">
    <input type="hidden" name="id" value="<?= (int) ($editBook['id'] ?? 0) ?>">
    <input type="hidden" name="return_to" value="<?= e($currentReturn) ?>">
    <label>ISBN
      <input name="isbn" maxlength="30" placeholder="978-602-..." value="<?= e($editBook['isbn'] ?? '') ?>">
    </label>
    <label>Judul Buku <span class="req">*</span>
      <input name="title" required maxlength="180" placeholder="Masukkan judul buku" value="<?= e($editBook['title'] ?? '') ?>">
    </label>
    <label>Penulis <span class="req">*</span>
      <input name="author" required maxlength="150" placeholder="Nama penulis" value="<?= e($editBook['author'] ?? '') ?>">
    </label>
    <label>Penerbit
      <input name="publisher" maxlength="150" placeholder="Nama penerbit" value="<?= e($editBook['publisher'] ?? '') ?>">
    </label>
    <label>Tahun Terbit
      <input type="number" name="publication_year" min="1000" max="<?= (int) date('Y') + 1 ?>" placeholder="<?= date('Y') ?>" value="<?= e($editBook['publication_year'] ?? '') ?>">
    </label>
    <label>Stok <span class="req">*</span>
      <input type="number" name="stock" min="0" required value="<?= e($editBook['stock'] ?? 1) ?>">
    </label>
    <div class="full actions">
      <button class="btn primary" type="submit"><?= $editBook ? 'Simpan Perubahan' : 'Tambah Buku' ?></button>
      <?php if ($editBook): ?>
        <a class="btn" href="index.php?page=books">Batal</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="panel">
  <div class="row wrap-row">
    <div>
      <h3>Daftar Koleksi Buku</h3>
      <p class="muted"><?= number_format($totalBooks) ?> judul ditemukan.</p>
    </div>
    <form method="get" class="actions filter-bar">
      <input type="hidden" name="page" value="books">
      <input name="q" value="<?= e($search) ?>" placeholder="Cari judul / penulis / ISBN / penerbit">
      <select name="stock" aria-label="Filter stok">
        <option value="">Semua Stok</option>
        <option value="available" <?= $stockFilter === 'available' ? 'selected' : '' ?>>Tersedia (&gt; 0)</option>
        <option value="empty" <?= $stockFilter === 'empty' ? 'selected' : '' ?>>Stok Habis (0)</option>
      </select>
      <button class="btn primary" type="submit">Cari</button>
      <?php if ($search !== '' || $stockFilter !== ''): ?>
        <a class="btn" href="index.php?page=books">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Judul, Penulis &amp; Penerbit</th>
          <th>ISBN</th>
          <th>Tahun</th>
          <th>Stok</th>
          <th>Dipinjam</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($books as $b): ?>
          <tr>
            <td>
              <strong><?= e($b['title']) ?></strong>
              <small><?= e($b['author']) ?> · <?= e($b['publisher'] ?: '-') ?></small>
            </td>
            <td><?= e($b['isbn'] ?: '-') ?></td>
            <td><?= e($b['publication_year'] ?: '-') ?></td>
            <td>
              <?php $stk = (int) $b['stock']; ?>
              <span class="badge <?= $stk === 0 ? 'danger' : ($stk <= 2 ? 'pending' : '') ?>">
                <?= $stk === 0 ? 'Habis (0)' : $stk . ' eks.' ?>
              </span>
            </td>
            <td><?= (int) $b['active_loans'] ?> eks.</td>
            <td class="actions">
              <a class="btn small" href="<?= e(page_url(['edit' => (int) $b['id']])) ?>">Edit</a>
              <form method="post" class="inline" onsubmit="return confirm('Hapus buku <?= e($b['title']) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_book">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <input type="hidden" name="return_to" value="<?= e($currentReturn) ?>">
                <button class="btn danger small" type="submit">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$books): ?>
          <tr><td colspan="6" class="muted">Belum ada data buku yang sesuai filter.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?= render_pagination($pg) ?>
</div>
