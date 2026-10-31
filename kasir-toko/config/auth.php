<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('kasir_toko');   // nama sesi khusus, supaya tidak bentrok dengan aplikasi lain di localhost
    session_start();
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Batasi halaman untuk role tertentu. $root = '' (folder utama) atau '../' (folder admin).
function require_login(array $roles, $root = '') {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $roles, true)) {
        header('Location: ' . $root . 'index.php');
        exit;
    }
}

function is_admin() {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return "<input type='hidden' name='csrf' value='" . h(csrf_token()) . "'>";
}

function csrf_check() {
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) {
        http_response_code(403);
        die('Permintaan tidak valid (CSRF). Muat ulang halaman lalu coba lagi.');
    }
}
