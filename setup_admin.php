<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Jalankan file ini melalui terminal/Command Prompt.\n");
}

require __DIR__ . '/config/database.php';

try {
    $pdo = db();
    $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='petugas'")->fetchColumn();
    if ($count > 0) {
        echo "Akun petugas sudah tersedia. Tidak ada perubahan.\n";
        exit(0);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO users (name,email,password_hash,role)
         VALUES (:name,:email,:hash,'petugas')"
    );
    $stmt->execute([
        'name' => 'Administrator Perpustakaan',
        'email' => 'admin@perpus.local',
        'hash' => password_hash('Admin123!', PASSWORD_DEFAULT),
    ]);

    echo "Akun petugas berhasil dibuat.\n";
    echo "Email: admin@perpus.local\nPassword: Admin123!\n";
    echo "Segera ganti password setelah login.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal membuat akun: " . $e->getMessage() . "\n");
    exit(1);
}
