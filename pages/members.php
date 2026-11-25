<?php
declare(strict_types=1);

$editId = (int) ($_GET['edit'] ?? 0);
$editMember = null;
if ($editId > 0) {
    $st = $pdo->prepare(
        "SELECT m.*, u.name, u.email
         FROM members m
         JOIN users u ON u.id = m.user_id
         WHERE m.id = :id LIMIT 1"
    );
    $st->execute(['id' => $editId]);
    $editMember = $st->fetch() ?: null;
    if (!$editMember) {
        echo '<div class="alert warning"><span>Data anggota yang dipilih tidak ditemukan.</span><button type="button" class="alert-close" aria-label="Tutup">&times;</button></div>';
    }
}

$search = get_string('q', 150);
$typeFilter = get_string('type', 20);
if (!in_array($typeFilter, ['', 'mahasiswa', 'dosen'], true)) {
    $typeFilter = '';
}
$statusFilter = get_string('status', 10);
if (!in_array($statusFilter, ['', '1', '0'], true)) {
    $statusFilter = '';
}
$pageNum = max(1, (int) ($_GET['p'] ?? 1));

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(u.name LIKE :q1 OR u.email LIKE :q2 OR m.member_number LIKE :q3 OR COALESCE(m.phone, '') LIKE :q4)";
    $like = '%' . $search . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}
if ($typeFilter !== '') {
    $where[] = "m.member_type = :type";
    $params['type'] = $typeFilter;
}
if ($statusFilter !== '') {
    $where[] = "m.active = :active";
    $params['active'] = (int) $statusFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM members m JOIN users u ON u.id = m.user_id {$whereSql}");
foreach ($params as $k => $v) {
    $countStmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$totalMembers = (int) $countStmt->fetchColumn();
$pg = paginate($totalMembers, $pageNum, 10);

$listStmt = $pdo->prepare(
    "SELECT m.*, u.name, u.email,
            (SELECT COUNT(*) FROM loans l WHERE l.member_id = m.id AND l.status = 'dipinjam') AS active_loans
     FROM members m
     JOIN users u ON u.id = m.user_id
     {$whereSql}
     ORDER BY u.name ASC, m.id DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $listStmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$listStmt->bindValue(':limit', $pg['per_page'], PDO::PARAM_INT);
$listStmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$listStmt->execute();
$members = $listStmt->fetchAll();
$currentReturn = page_url();
?>

<div class="panel">
  <h3><?= $editMember ? 'Ubah Data Anggota' : 'Registrasi Anggota Baru' ?></h3>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_member">
    <input type="hidden" name="id" value="<?= (int) ($editMember['id'] ?? 0) ?>">
    <input type="hidden" name="return_to" value="<?= e($currentReturn) ?>">

    <label>Nama Lengkap <span class="req">*</span>
      <input name="name" required maxlength="120" placeholder="Nama lengkap anggota" value="<?= e($editMember['name'] ?? '') ?>">
    </label>
    <label>Email Login <span class="req">*</span>
      <input type="email" name="email" required maxlength="150" placeholder="nama@unpam.ac.id" value="<?= e($editMember['email'] ?? '') ?>">
    </label>
    <label>Nomor Anggota / NIM / NIP <span class="req">*</span>
      <input name="member_number" required maxlength="40" placeholder="Contoh: 2024101001" value="<?= e($editMember['member_number'] ?? '') ?>">
    </label>
    <label>Jenis Anggota
      <select name="member_type">
        <option value="mahasiswa" <?= ($editMember['member_type'] ?? '') === 'mahasiswa' ? 'selected' : '' ?>>Mahasiswa</option>
        <option value="dosen" <?= ($editMember['member_type'] ?? '') === 'dosen' ? 'selected' : '' ?>>Dosen</option>
      </select>
    </label>
    <label><?= $editMember ? 'Password Baru (opsional)' : 'Password Awal' ?> <?= $editMember ? '' : '<span class="req">*</span>' ?>
      <div class="pw-wrap">
        <input type="password" name="password" minlength="8" <?= $editMember ? '' : 'required' ?> placeholder="<?= $editMember ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter' ?>">
        <button type="button" class="pw-toggle" aria-label="Tampilkan password">Lihat</button>
      </div>
    </label>
    <label>Telepon
      <input name="phone" maxlength="30" placeholder="08..." value="<?= e($editMember['phone'] ?? '') ?>">
    </label>
    <label class="full">Alamat
      <textarea name="address" rows="2" maxlength="1000" placeholder="Alamat domisili anggota"><?= e($editMember['address'] ?? '') ?></textarea>
    </label>
    <div class="full actions">
      <button class="btn primary" type="submit"><?= $editMember ? 'Simpan Perubahan' : 'Daftarkan Anggota' ?></button>
      <?php if ($editMember): ?>
        <a class="btn" href="index.php?page=members">Batal</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="panel">
  <div class="row wrap-row">
    <div>
      <h3>Daftar Anggota</h3>
      <p class="muted"><?= number_format($totalMembers) ?> anggota ditemukan.</p>
    </div>
    <form method="get" class="actions filter-bar">
      <input type="hidden" name="page" value="members">
      <input name="q" value="<?= e($search) ?>" placeholder="Cari nama / NIM / email / telepon">
      <select name="type" aria-label="Filter jenis">
        <option value="">Semua Jenis</option>
        <option value="mahasiswa" <?= $typeFilter === 'mahasiswa' ? 'selected' : '' ?>>Mahasiswa</option>
        <option value="dosen" <?= $typeFilter === 'dosen' ? 'selected' : '' ?>>Dosen</option>
      </select>
      <select name="status" aria-label="Filter status">
        <option value="">Semua Status</option>
        <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Aktif</option>
        <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Nonaktif</option>
      </select>
      <button class="btn primary" type="submit">Cari</button>
      <?php if ($search !== '' || $typeFilter !== '' || $statusFilter !== ''): ?>
        <a class="btn" href="index.php?page=members">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nama &amp; Email</th>
          <th>Nomor Anggota</th>
          <th>Jenis</th>
          <th>Kontak &amp; Alamat</th>
          <th>Dipinjam</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
          <tr>
            <td><strong><?= e($m['name']) ?></strong><small><?= e($m['email']) ?></small></td>
            <td><?= e($m['member_number']) ?></td>
            <td><?= e(ucfirst($m['member_type'])) ?></td>
            <td>
              <?= e($m['phone'] ?: '-') ?>
              <small><?= e($m['address'] ? mb_strimwidth($m['address'], 0, 50, '...') : '-') ?></small>
            </td>
            <td><?= (int) $m['active_loans'] ?> buku</td>
            <td>
              <span class="badge <?= (int) $m['active'] ? '' : 'pending' ?>">
                <?= (int) $m['active'] ? 'Aktif' : 'Nonaktif' ?>
              </span>
            </td>
            <td class="actions">
              <a class="btn small" href="<?= e(page_url(['edit' => (int) $m['id']])) ?>">Edit</a>
              <form method="post" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_member">
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <input type="hidden" name="active" value="<?= (int) $m['active'] ? 0 : 1 ?>">
                <input type="hidden" name="return_to" value="<?= e($currentReturn) ?>">
                <button class="btn small" type="submit"><?= (int) $m['active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
              </form>
              <form method="post" class="inline" onsubmit="return confirm('Hapus anggota <?= e($m['name']) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_member">
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <input type="hidden" name="return_to" value="<?= e($currentReturn) ?>">
                <button class="btn danger small" type="submit">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$members): ?>
          <tr><td colspan="7" class="muted">Belum ada data anggota yang sesuai filter.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?= render_pagination($pg) ?>
</div>
