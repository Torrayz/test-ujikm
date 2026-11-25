<?php
declare(strict_types=1);

$search = get_string('q', 150);
$availFilter = get_string('avail', 20);
if (!in_array($availFilter, ['', 'available'], true)) {
    $availFilter = '';
}
$pageNum = max(1, (int) ($_GET['p'] ?? 1));

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(title LIKE :q1 OR author LIKE :q2 OR COALESCE(isbn, '') LIKE :q3 OR COALESCE(publisher, '') LIKE :q4)";
    $like = '%' . $search . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}
if ($availFilter === 'available') {
    $where[] = "stock > 0";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM books {$whereSql}");
$countStmt->execute($params);
$totalBooks = (int) $countStmt->fetchColumn();
$pg = paginate($totalBooks, $pageNum, 12);

$listStmt = $pdo->prepare(
    "SELECT * FROM books {$whereSql} ORDER BY title ASC, id DESC LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $listStmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$listStmt->bindValue(':limit', $pg['per_page'], PDO::PARAM_INT);
$listStmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$listStmt->execute();
$books = $listStmt->fetchAll();
?>

<div class="panel">
  <div class="row wrap-row">
    <div>
      <h3>Katalog Buku</h3>
      <p class="muted"><?= number_format($totalBooks) ?> judul tersedia dalam pencarian katalog.</p>
    </div>
    <form method="get" class="actions filter-bar">
      <input type="hidden" name="page" value="catalog">
      <input name="q" value="<?= e($search) ?>" placeholder="Cari judul, penulis, penerbit, atau ISBN">
      <select name="avail" aria-label="Filter ketersediaan">
        <option value="">Semua Koleksi</option>
        <option value="available" <?= $availFilter === 'available' ? 'selected' : '' ?>>Tersedia Saja</option>
      </select>
      <button class="btn primary" type="submit">Cari</button>
      <?php if ($search !== '' || $availFilter !== ''): ?>
        <a class="btn" href="index.php?page=catalog">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="grid">
    <?php foreach ($books as $b): ?>
      <article class="book catalog-card">
        <div class="cover"><?= e(mb_strtoupper(mb_substr($b['title'], 0, 1))) ?></div>
        <h3><?= e($b['title']) ?></h3>
        <p class="book-author"><?= e($b['author']) ?></p>
        <p class="book-meta">
          <?= e($b['publisher'] ?: 'Penerbit -') ?> · <?= e($b['publication_year'] ?: 'Tahun -') ?>
          <?php if (!empty($b['isbn'])): ?>
            <br>ISBN: <?= e($b['isbn']) ?>
          <?php endif; ?>
        </p>
        <div class="card-foot">
          <span class="badge <?= (int) $b['stock'] > 0 ? '' : 'danger' ?>">
            <?= (int) $b['stock'] > 0 ? 'Tersedia: ' . (int) $b['stock'] . ' eks.' : 'Stok Habis' ?>
          </span>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$books): ?>
      <p class="muted">Buku yang Anda cari tidak ditemukan.</p>
    <?php endif; ?>
  </div>

  <?= render_pagination($pg) ?>
</div>
