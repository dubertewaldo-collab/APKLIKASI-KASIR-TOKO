<?php
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/auth.php';

function rupiah($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function tgl($d) {
    static $bln = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime((string)$d);
    if (!$t) return h($d);
    return date('j', $t) . ' ' . $bln[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function tgl_jam($d) {
    $t = strtotime((string)$d);
    return $t ? tgl($d) . ', ' . date('H:i', $t) : h($d);
}

function kode_trx($id, $created) {
    return 'TRX-' . date('ymd', strtotime((string)$created)) . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}

// Ambil tanggal (Y-m-d) dari $_GET; kalau tidak valid pakai $default.
function tanggal_param($key, $default) {
    $v = $_GET[$key] ?? '';
    $d = DateTime::createFromFormat('Y-m-d', (string)$v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : $default;
}

// Rentang [awal, akhir) untuk query: dari 00:00 hari pertama sampai 00:00 sehari setelah hari terakhir.
function rentang($dari, $sampai) {
    return [$dari . ' 00:00:00', date('Y-m-d', strtotime($sampai . ' +1 day')) . ' 00:00:00'];
}

function brand_mark() {
    return "<svg class='mark' viewBox='0 0 32 32' width='30' height='30' aria-hidden='true'>"
        . "<rect width='32' height='32' rx='9' fill='currentColor'/>"
        . "<path d='M10 7h12v18l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5z' fill='#fff'/>"
        . "<path d='M13 11h6M13 14.5h6M13 18h3.5' stroke='currentColor' stroke-width='1.6' stroke-linecap='round' fill='none'/></svg>";
}

function html_head($title, $root, $body_class = '') {
    $v = @filemtime(__DIR__ . '/../css/style.css') ?: 1;   // ?v= supaya CSS baru langsung terbaca
    echo "<!doctype html><html lang='id'><head><meta charset='utf-8'>"
        . "<meta name='viewport' content='width=device-width, initial-scale=1'>"
        . "<title>" . h($title) . " - " . h(NAMA_TOKO) . "</title>"
        . "<link rel='preconnect' href='https://fonts.googleapis.com'>"
        . "<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>"
        . "<link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap' rel='stylesheet'>"
        . "<link rel='stylesheet' href='" . $root . "css/style.css?v=" . $v . "'></head>"
        . "<body" . ($body_class ? " class='" . h($body_class) . "'" : '') . ">";
}

// Halaman setelah login. $root: '' di folder utama, '../' di folder admin.
function page_start($title, $root = '', $wide = false) {
    $cur = basename($_SERVER['SCRIPT_NAME']);
    if (is_admin()) {
        $items = [
            $root . 'admin/dashboard.php' => 'Ringkasan',
            $root . 'pos.php' => 'Kasir',
            $root . 'admin/produk.php' => 'Produk',
            $root . 'riwayat.php' => 'Riwayat',
            $root . 'admin/laporan.php' => 'Laporan',
            $root . 'admin/pengguna.php' => 'Pengguna',
        ];
    } else {
        $items = [$root . 'pos.php' => 'Kasir', $root . 'riwayat.php' => 'Riwayat'];
    }
    html_head($title, $root);
    echo "<header class='topbar'><div class='topbar-in'>";
    echo "<a class='brand' href='" . h(array_key_first($items)) . "'>" . brand_mark() . "<span>" . h(NAMA_TOKO) . "</span></a>";
    echo "<nav class='nav' aria-label='Menu utama'>";
    foreach ($items as $href => $label) {
        $on = (basename($href) === $cur) ? " aria-current='page'" : '';
        echo "<a href='" . h($href) . "'" . $on . ">" . h($label) . "</a>";
    }
    echo "</nav><div class='who'><span class='who-name'>" . h(($_SESSION['nama'] ?? '') ?: 'Pengguna')
        . "</span><a class='btn btn-ghost btn-sm' href='" . $root . "logout.php'>Keluar</a></div>";
    echo "</div></header><main class='wrap" . ($wide ? " wide" : "") . "'>";
}

function page_end() {
    echo "</main></body></html>";
}
