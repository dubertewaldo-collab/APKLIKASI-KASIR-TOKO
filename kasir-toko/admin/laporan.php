<?php
require '../config/koneksi.php';
require '../config/auth.php';
require '../config/layout.php';
require_login(['admin'], '../');

$hari_ini = date('Y-m-d');
$dari = tanggal_param('dari', date('Y-m-01'));   // default: awal bulan ini
$sampai = tanggal_param('sampai', $hari_ini);
if ($sampai < $dari) { $sampai = $dari; }
list($awal, $akhir) = rentang($dari, $sampai);
$p = 'ss'; $v = [$awal, $akhir];

$ringkas = db_one($koneksi, "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS omzet FROM transaksi WHERE created_at >= ? AND created_at < ?", $p, $v);
$item = db_one($koneksi, "SELECT COALESCE(SUM(i.qty), 0) AS n FROM transaksi_item i JOIN transaksi t ON t.id = i.transaksi_id WHERE t.created_at >= ? AND t.created_at < ?", $p, $v);
$perhari = db_all($koneksi, "SELECT DATE(created_at) AS tgl, COUNT(*) AS n, SUM(total) AS omzet FROM transaksi WHERE created_at >= ? AND created_at < ? GROUP BY DATE(created_at) ORDER BY tgl DESC", $p, $v);
$terlaris = db_all($koneksi, "SELECT i.nama_produk, SUM(i.qty) AS qty, SUM(i.subtotal) AS omzet FROM transaksi_item i JOIN transaksi t ON t.id = i.transaksi_id WHERE t.created_at >= ? AND t.created_at < ? GROUP BY i.nama_produk ORDER BY qty DESC, omzet DESC LIMIT 10", $p, $v);
$rata = $ringkas['n'] > 0 ? $ringkas['omzet'] / $ringkas['n'] : 0;

page_start('Laporan', '../');
?>
  <div class='page-head'>
    <div>
      <h1>Laporan penjualan</h1>
      <p><?php echo tgl($dari); ?> &ndash; <?php echo tgl($sampai); ?></p>
    </div>
    <button class='btn btn-ghost no-print' type='button' onclick='window.print()'>Cetak</button>
  </div>

  <form method='get' class='filter panel no-print'>
    <div><label for='dari'>Dari</label><input type='date' id='dari' name='dari' value='<?php echo h($dari); ?>'></div>
    <div><label for='sampai'>Sampai</label><input type='date' id='sampai' name='sampai' value='<?php echo h($sampai); ?>'></div>
    <button class='btn' type='submit'>Tampilkan</button>
    <a class='btn btn-ghost' href='laporan.php'>Bulan ini</a>
  </form>

  <div class='stats'>
    <div class='stat'><div class='label'>Total penjualan</div><div class='value'><?php echo rupiah($ringkas['omzet']); ?></div></div>
    <div class='stat'><div class='label'>Transaksi</div><div class='value'><?php echo (int)$ringkas['n']; ?></div></div>
    <div class='stat'><div class='label'>Barang terjual</div><div class='value'><?php echo (int)$item['n']; ?></div></div>
    <div class='stat'><div class='label'>Rata-rata / transaksi</div><div class='value'><?php echo rupiah($rata); ?></div></div>
  </div>

  <?php if (!$perhari): ?>
    <div class='panel empty'><p>Tidak ada transaksi pada rentang ini.</p></div>
  <?php else: ?>
  <div class='cols'>
    <section class='panel'>
      <h2>Per hari</h2>
      <table>
        <thead><tr><th>Tanggal</th><th class='right'>Transaksi</th><th class='right'>Penjualan</th></tr></thead>
        <tbody>
        <?php foreach ($perhari as $r): ?>
          <tr><td><?php echo tgl($r['tgl']); ?></td><td class='right num'><?php echo (int)$r['n']; ?></td><td class='right num'><b><?php echo rupiah($r['omzet']); ?></b></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <section class='panel'>
      <h2>Produk terlaris</h2>
      <table>
        <thead><tr><th>Produk</th><th class='right'>Terjual</th><th class='right'>Penjualan</th></tr></thead>
        <tbody>
        <?php foreach ($terlaris as $r): ?>
          <tr><td><?php echo h($r['nama_produk']); ?></td><td class='right num'><?php echo (int)$r['qty']; ?></td><td class='right num'><?php echo rupiah($r['omzet']); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </div>
  <?php endif; ?>
<?php page_end(); ?>
