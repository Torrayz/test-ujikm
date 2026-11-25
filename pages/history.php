<?php
declare(strict_types=1);

$profileStmt = $pdo->prepare(
    "SELECT m.*, u.name, u.email
     FROM members m
     JOIN users u ON u.id = m.user_id
     WHERE m.user_id = :uid LIMIT 1"
);
$profileStmt->execute(['uid' => (int) $user['id']]);
$memberProfile = $profileStmt->fetch();

$statusFilter = get_string('status', 20);
if (!in_array($statusFilter, ['', 'dipinjam', 'dikembalikan'], true)) {
    $statusFilter = '';
}
$pageNum = max(1, (int) ($_GET['p'] ?? 1));

$activeCountStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM loans l JOIN members m ON m.id = l.member_id WHERE m.user_id = :uid AND l.status = 'dipinjam'"
);
$activeCountStmt->execute(['uid' => (int) $user['id']]);
$activeCount = (int) $activeCountStmt->fetchColumn();

$doneCountStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM loans l JOIN members m ON m.id = l.member_id WHERE m.user_id = :uid AND l.status = 'dikembalikan'"
);
$doneCountStmt->execute(['uid' => (int) $user['id']]);
$doneCount = (int) $doneCountStmt->fetchColumn();

$where = ["m.user_id = :uid"];
$params = ['uid' => (int) $user['id']];
if ($statusFilter !== '') {
    $where[] = "l.status = :status";
    $params['status'] = $statusFilter;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM loans l JOIN members m ON m.id = l.member_id {$whereSql}");
foreach ($params as $k => $v) {
    $countStmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$totalRows = (int) $countStmt->fetchColumn();
$pg = paginate($totalRows, $pageNum, 10);

$histStmt = $pdo->prepare(
    "SELECT l.*, b.title, b.author, b.isbn
     FROM loans l
     JOIN members m ON m.id = l.member_id
     JOIN books b ON b.id = l.book_id
     {$whereSql}
     ORDER BY l.id DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $histStmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$histStmt->bindValue(':limit', $pg['per_page'], PDO::PARAM_INT);
$histStmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$histStmt->execute();
$myHistory = $histStmt->fetchAll();
?>

<?php if ($memberProfile): ?>
  <div class="stats stats-3">
    <article class="stat">
      <span>Nomor Anggota (<?= e(ucfirst($memberProfile['member_type'])) ?>)</span>
      <strong class="stat-sm"><?= e($memberProfile['member_number']) ?></strong>
      <small class="muted"><?= e($memberProfile['name']) ?></small>
    </article>
    <article class="stat">
      <span>Sedang Dipinjam</span>
      <strong><?= number_format($activeCount) ?></strong>
      <small class="muted">Buku yang belum dikembalikan</small>
    </article>
    <article class="stat">
      <span>Sudah Dikembalikan</span>
      <strong><?= number_format($doneCount) ?></strong>
      <small class="muted">Total transaksi selesai</small>
    </article>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="row wrap-row">
    <div>
      <h3>Riwayat Peminjaman Saya</h3>
      <p class="muted"><?= number_format($totalRows) ?> transaksi ditemukan.</p>
    </div>
    <form method="get" class="actions filter-bar">
      <input type="hidden" name="page" value="history">
      <select name="status" aria-label="Filter status">
        <option value="">Semua Status</option>
        <option value="dipinjam" <?= $statusFilter === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
        <option value="dikembalikan" <?= $statusFilter === 'dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
      </select>
      <button class="btn primary" type="submit">Filter</button>
      <?php if ($statusFilter !== ''): ?>
        <a class="btn" href="index.php?page=history">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Judul &amp; Penulis</th>
          <th>ISBN</th>
          <th>Tanggal Pinjam</th>
          <th>Tanggal Kembali</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($myHistory as $l): ?>
          <tr>
            <td><strong><?= e($l['title']) ?></strong><small><?= e($l['author']) ?></small></td>
            <td><?= e($l['isbn'] ?: '-') ?></td>
            <td><?= e(format_date($l['loaned_at'])) ?></td>
            <td><?= e(format_date($l['returned_at'])) ?></td>
            <td>
              <span class="badge <?= $l['status'] === 'dipinjam' ? 'pending' : '' ?>">
                <?= e(ucfirst($l['status'])) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$myHistory): ?>
          <tr><td colspan="5" class="muted">Belum ada riwayat peminjaman.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?= render_pagination($pg) ?>
</div>
