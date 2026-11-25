<?php
require __DIR__.'/config/database.php';
$pdo=db(); $q=$pdo->query("SELECT COUNT(*) FROM users WHERE role='petugas'"); if((int)$q->fetchColumn()===0){$s=$pdo->prepare("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'petugas')");$s->execute(['Administrator Perpustakaan','admin@perpus.local',password_hash('Admin123!',PASSWORD_DEFAULT)]);echo "Admin dibuat: admin@perpus.local / Admin123!\n";}else echo "Petugas sudah ada.\n";
