<?php
declare(strict_types=1);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function enforce_active_session(PDO $pdo): void
{
    $user = current_user();
    if (!$user) {
        return;
    }

    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => (int) $user['id']]);
    $fresh = $stmt->fetch();

    if (!$fresh) {
        unset($_SESSION['user']);
        session_regenerate_id(true);
        flash('warning', 'Sesi Anda tidak lagi valid. Silakan login kembali.');
        redirect('index.php');
    }

    if ($fresh['role'] === 'anggota') {
        $check = $pdo->prepare("SELECT active FROM members WHERE user_id = :uid LIMIT 1");
        $check->execute(['uid' => (int) $fresh['id']]);
        $active = $check->fetchColumn();
        if ((int) $active !== 1) {
            unset($_SESSION['user']);
            session_regenerate_id(true);
            flash('danger', 'Akun anggota Anda telah dinonaktifkan. Hubungi petugas perpustakaan.');
            redirect('index.php');
        }
    }

    $_SESSION['user'] = $fresh;
}

function require_login(): void
{
    if (!current_user()) {
        flash('warning', 'Silakan login terlebih dahulu.');
        redirect('index.php');
    }
}

function require_role(string ...$roles): void
{
    require_login();
    if (!in_array(current_user()['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('403 - Anda tidak memiliki hak akses.');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    $saved = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sent) || !is_string($saved) || !hash_equals($saved, $sent)) {
        flash('danger', 'Sesi formulir kedaluwarsa atau token keamanan tidak valid. Silakan coba lagi.');
        $ref = (string) ($_POST['return_to'] ?? 'index.php');
        if (!str_starts_with($ref, 'index.php')) {
            $ref = 'index.php';
        }
        redirect($ref);
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $value = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $value;
}

function post_string(string $key, int $max = 255): string
{
    return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
}

function get_string(string $key, int $max = 255): string
{
    return mb_substr(trim((string) ($_GET[$key] ?? '')), 0, $max);
}

function format_date(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $time = strtotime($date);
    return $time ? date('d-m-Y', $time) : e($date);
}

function days_elapsed(?string $date): int
{
    if (!$date) {
        return 0;
    }
    $start = strtotime($date);
    if (!$start) {
        return 0;
    }
    $diff = (int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $start))) / 86400);
    return max(0, $diff);
}

function paginate(int $total, int $page, int $perPage = 10): array
{
    $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
    $currentPage = max(1, min($page, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return [
        'page' => $currentPage,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}

function page_url(array $overrides = []): string
{
    $params = $_GET;
    foreach ($overrides as $key => $val) {
        if ($val === null || $val === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $val;
        }
    }
    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}

function render_pagination(array $pg, string $paramName = 'p'): string
{
    if ($pg['total_pages'] <= 1) {
        return '';
    }
    $current = $pg['page'];
    $totalPages = $pg['total_pages'];
    $html = '<div class="pagination"><span class="muted">Halaman ' . $current . ' dari ' . $totalPages . ' (' . $pg['total'] . ' data)</span><div class="pages">';
    if ($current > 1) {
        $html .= '<a class="btn small" href="' . e(page_url([$paramName => $current - 1])) . '">‹ Sebelumnya</a>';
    }
    $start = max(1, $current - 2);
    $end = min($totalPages, $current + 2);
    for ($i = $start; $i <= $end; $i++) {
        $cls = $i === $current ? 'btn primary small' : 'btn small';
        $html .= '<a class="' . $cls . '" href="' . e(page_url([$paramName => $i])) . '">' . $i . '</a>';
    }
    if ($current < $totalPages) {
        $html .= '<a class="btn small" href="' . e(page_url([$paramName => $current + 1])) . '">Berikutnya ›</a>';
    }
    $html .= '</div></div>';
    return $html;
}

function svg_icon(string $name): string
{
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="5" rx="2"/><rect x="13" y="10" width="8" height="11" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/></svg>',
        'books' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
        'members' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'loans' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></svg>',
        'catalog' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
        'history' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    ];
    return $icons[$name] ?? '';
}
