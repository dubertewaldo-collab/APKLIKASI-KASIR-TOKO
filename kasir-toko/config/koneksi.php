<?php
require_once __DIR__ . '/app.php';

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'kasir_toko';

// Error SQL dilempar sebagai exception supaya transaksi bisa dibatalkan dengan rapi.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $koneksi = mysqli_connect($host, $user, $pass, $dbname);
    mysqli_set_charset($koneksi, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    die('Gagal koneksi MySQL. Pastikan MySQL di XAMPP menyala dan database "' . htmlspecialchars($dbname) . '" sudah diimpor. ('
        . htmlspecialchars($e->getMessage()) . ')');
}

// ---- Helper query dengan prepared statement ----
function db_all($k, $sql, $types = '', $params = []) {
    $st = mysqli_prepare($k, $sql);
    if ($types !== '') mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $rows = $r ? mysqli_fetch_all($r, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($st);
    return $rows;
}
function db_one($k, $sql, $types = '', $params = []) {
    $rows = db_all($k, $sql, $types, $params);
    return $rows[0] ?? null;
}
function db_exec($k, $sql, $types = '', $params = []) {
    $st = mysqli_prepare($k, $sql);
    if ($types !== '') mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    $n = mysqli_stmt_affected_rows($st);
    mysqli_stmt_close($st);
    return $n;
}
