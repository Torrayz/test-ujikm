<?php
declare(strict_types=1);

$availableMembers = $pdo->query(
    "SELECT m.id, m.member_number, m.member_type, u.name
     FROM members m
     JOIN users u ON u.id = m.user_id
     WHERE m.active = 1
     ORDER BY u.name ASC"
)->fetchAll();

$availableBooks = $pdo->query(
    "SELECT id, title, author, stock
     FROM books
     WHERE stock > 0
     ORDER BY title ASC"
)->fetchAll();

$activeLoans = $pdo->query(
    "SELECT l.id, l.loaned_at, m.member_number, u.name AS member_name,
            b.title AS book_title, b.author AS book_author,
            s.name AS staff_name
     FROM loans l
     JOIN members m ON m.id = l.member_id
     JOIN users u ON u.id = m.user_id
     JOIN books b ON b.id = l.book_id
     JOIN users s ON s.id = l.processed_by
     WHERE l.status = 'dipinjam'
     ORDER BY l.loaned_at ASC, l.id ASC"
)->fetchAll();

$search = get_string('q', 150);
$statusFilter = get_string('status', 20);
if (!in_array($statusFilter, ['', 'dipinjam', 'dikembalikan'], true)) {
    $statusFilter = '';
}
$pageNum = max(1, (int) ($_GET['p'] ?? 1));

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(u.name LIKE :q1 OR m.member_number LIKE :q2 OR b.title LIKE :q3 OR b.author LIKE :q4)";
    $like = '%' . $search . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}
if ($statusFilter !== '') {
    $where[] = "l.status = :status";
    $params['status'] = $statusFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM loans l
     JOIN members m ON m.id = l.member_id
     JOIN users u ON u.id = m.user_id
     JOIN books b ON b.id = l.book_id
     {$whereSql}"
);
foreach ($params as $k => $v) {
    $countStmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$countStmt->execute();
$totalLoans = (int) $countStmt->fetchColumn();
$pg = paginate($totalLoans, $pageNum, 10);

$histStmt = $pdo->prepare(
    "SELECT l.*, m.member_number, u.name AS member_name, b.title AS book_title, s.name AS staff_name
     FROM loans l
     JOIN members m ON m.id = l.member_id
     JOIN users u ON u.id = m.user_id
     JOIN books b ON b.id = l.book_id
     JOIN users s ON s.id = l.processed_by
     {$whereSql}
     ORDER BY l.id DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $histStmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$histStmt->bindValue(':limit', $pg['per_page'], PDO::PARAM_INT);
$histStmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$histStmt->execute();
$history = $histStmt->fetchAll();
$currentReturn = page_url();
?>

<div class="panel">
  <h3>Catat Peminjaman Baru</h3>
  <?php if (!$availableMembers || !$availableBooks): ?>
    <p class="muted">
      <?= !$availableMembers ? 'Belum ada anggota aktif. ' : '' ?>
      <?= !$availableBooks ? 'Belum ada buku dengan stok tersedia.' : '' ?>
    </p>
  <?php endif; ?>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="borrow">
    <label>Anggota Aktif <span class="req">*</span>
      <select name="member_id" required>
        <option value="">-- Pilih anggota --</option>
        <?php foreach ($availableMembers as $m): ?>
          <option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?> — <?= e($m['member_number']) ?> (<?= e(ucfirst($m['member_type'])) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Buku Tersedia <span class="req">*</span>
      <select name="book_id" required>
        <option value="">-- Pilih buku --</option>
        <?php foreach ($availableBooks as $b): ?>
          <option value="<?= (int) $b['id'] ?>"><?= e($b['title']) ?> — <?= e($b['author']) ?> (stok <?= (int) $b['stock'] ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="actions align-end">
      <button class="btn primary" type="submit" <?= (!$availableMembers || !$availableBooks) ? 'disabled' : '' ?>>Simpan Peminjaman</button>
    </div>
  </form>
</div>

<div class="panel">
  <div class="row">
    <div>
      <h3>Belum Dikembalikan</h3>
      <p class="muted"><?= count($activeLoans) ?> transaksi sedang dipinjam.</p>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Anggota</th>
          <th>Buku</th>
          <th>Tanggal Pinjam</th>
          <th>Durasi</th>
          <th>Petugas</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($activeLoans as $l): ?>
          <?php $days = days_elapsed($l['loaned_at']); ?>
          <tr>
            <td><strong><?= e($l['member_name']) ?></strong><small><?= e($l['member_number']) ?></small></td>
            <td><strong><?= e($l['book_title']) ?></strong><small><?= e($l['book_author']) ?></small></td>
            <td><?= e(format_date($l['loaned_at'])) ?></td>
            <td><span class="badge <?= $days > 7 ? 'pending' : '' ?>"><?= $days ?> hari</span></td>
            <td><?= e($l['staff_name']) ?></td>
            <td>
              <form method="post" class="inline" onsubmit="return confirm('Konfirmasi pengembalian buku <?= e($l['book_title']) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="return">
                <input type="hidden" name="loan_id" value="<?= (int) $l['id'] ?>">
                <input type="hidden" name="return_to" value="<?= e($currentReturn) ?>">
                <button class="btn success small" type="submit">Proses Kembali</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$activeLoans): ?>
          <tr><td colspan="6" class="muted">Tidak ada peminjaman aktif saat ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <div class="row wrap-row">
    <div>
      <h3>Riwayat Semua Transaksi</h3>
      <p class="muted"><?= number_format($totalLoans) ?> transaksi tercatat.</p>
    </div>
    <form method="get" class="actions filter-bar">
      <input type="hidden" name="page" value="loans">
      <input name="q" value="<?= e($search) ?>" placeholder="Cari anggota / NIM / judul buku">
      <select name="status" aria-label="Filter status">
        <option value="">Semua Status</option>
        <option value="dipinjam" <?= $statusFilter === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
        <option value="dikembalikan" <?= $statusFilter === 'dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
      </select>
      <button class="btn primary" type="submit">Cari</button>
      <?php if ($search !== '' || $statusFilter !== ''): ?>
        <a class="btn" href="index.php?page=loans">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Anggota</th>
          <th>Buku</th>
          <th>Tgl Pinjam</th>
          <th>Tgl Kembali</th>
          <th>Petugas</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $l): ?>
          <tr>
            <td><strong><?= e($l['member_name']) ?></strong><small><?= e($l['member_number']) ?></small></td>
            <td><?= e($l['book_title']) ?></td>
            <td><?= e(format_date($l['loaned_at'])) ?></td>
            <td><?= e(format_date($l['returned_at'])) ?></td>
            <td><?= e($l['staff_name']) ?></td>
            <td>
              <span class="badge <?= $l['status'] === 'dipinjam' ? 'pending' : '' ?>">
                <?= e(ucfirst($l['status'])) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$history): ?>
          <tr><td colspan="6" class="muted">Belum ada riwayat transaksi yang sesuai filter.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?= render_pagination($pg) ?>
</div>
