<?php
require 'config/koneksi.php';
require 'config/auth.php';
require 'config/layout.php';
require_login(['admin', 'kasir']);

$id = intval($_GET['id'] ?? 0);
$t = db_one($koneksi, "SELECT t.*, u.nama AS kasir_nama, u.username FROM transaksi t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ? LIMIT 1", 'i', [$id]);
// Kasir hanya boleh melihat struk miliknya sendiri
if (!$t || (!is_admin() && (int)$t['user_id'] !== (int)$_SESSION['user_id'])) {
    http_response_code(404);
    die('Struk tidak ditemukan.');
}
$items = db_all($koneksi, "SELECT * FROM transaksi_item WHERE transaksi_id = ? ORDER BY id ASC", 'i', [$id]);

page_start('Struk ' . kode_trx($t['id'], $t['created_at']), '');
?>
  <div class='struk-actions no-print'>
    <a class='btn' href='pos.php'>Transaksi baru</a>
    <button class='btn btn-ghost' type='button' onclick='window.print()'>Cetak struk</button>
    <a class='btn btn-ghost' href='riwayat.php'>Riwayat</a>
  </div>

  <article class='struk'>
    <header class='struk-head'>
      <h1><?php echo h(NAMA_TOKO); ?></h1>
      <p><?php echo h(ALAMAT_TOKO); ?></p>
    </header>
    <div class='struk-meta'>
      <div><span>No.</span><span><?php echo h(kode_trx($t['id'], $t['created_at'])); ?></span></div>
      <div><span>Waktu</span><span><?php echo tgl_jam($t['created_at']); ?></span></div>
      <div><span>Kasir</span><span><?php echo h($t['kasir_nama'] ?: ($t['username'] ?? '-')); ?></span></div>
    </div>
    <ul class='struk-items'>
      <?php foreach ($items as $it): ?>
        <li>
          <div><?php echo h($it['nama_produk']); ?></div>
          <div class='ln'><span><?php echo (int)$it['qty']; ?> &times; <?php echo rupiah($it['harga']); ?></span><b><?php echo rupiah($it['subtotal']); ?></b></div>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class='struk-sum'>
      <div><span>Total</span><b><?php echo rupiah($t['total']); ?></b></div>
      <div><span>Bayar</span><span><?php echo rupiah($t['bayar']); ?></span></div>
      <div><span>Kembali</span><span><?php echo rupiah($t['kembali']); ?></span></div>
    </div>
    <p class='struk-foot'>Terima kasih sudah berbelanja!</p>
  </article>
<?php page_end(); ?>
