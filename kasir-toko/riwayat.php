<?php
require 'config/koneksi.php';
require 'config/auth.php';
require 'config/layout.php';
require_login(['admin', 'kasir']);

$hari_ini = date('Y-m-d');
$dari = tanggal_param('dari', $hari_ini);
$sampai = tanggal_param('sampai', $hari_ini);
if ($sampai < $dari) { $sampai = $dari; }
list($awal, $akhir) = rentang($dari, $sampai);

$sql = "SELECT t.id, t.total, t.created_at, u.nama AS kasir_nama, u.username,
               (SELECT COALESCE(SUM(i.qty), 0) FROM transaksi_item i WHERE i.transaksi_id = t.id) AS jml
        FROM transaksi t LEFT JOIN users u ON t.user_id = u.id
        WHERE t.created_at >= ? AND t.created_at < ?";
if (is_admin()) {
    $rows = db_all($koneksi, $sql . " ORDER BY t.created_at DESC, t.id DESC LIMIT 500", 'ss', [$awal, $akhir]);
} else {
    // Kasir hanya melihat transaksinya sendiri
    $rows = db_all($koneksi, $sql . " AND t.user_id = ? ORDER BY t.created_at DESC, t.id DESC LIMIT 500", 'ssi', [$awal, $akhir, (int)$_SESSION['user_id']]);
}
$omzet = 0;
foreach ($rows as $r) { $omzet += $r['total']; }

page_start('Riwayat', '');
?>
  <div class='page-head'>
    <div>
      <h1>Riwayat transaksi</h1>
      <p><?php echo is_admin() ? 'Semua kasir.' : 'Transaksi yang kamu input.'; ?></p>
    </div>
  </div>

  <form method='get' class='filter panel'>
    <div><label for='dari'>Dari</label><input type='date' id='dari' name='dari' value='<?php echo h($dari); ?>'></div>
    <div><label for='sampai'>Sampai</label><input type='date' id='sampai' name='sampai' value='<?php echo h($sampai); ?>'></div>
    <button class='btn' type='submit'>Tampilkan</button>
    <a class='btn btn-ghost' href='riwayat.php'>Hari ini</a>
  </form>

  <div class='stats'>
    <div class='stat'><div class='label'>Jumlah transaksi</div><div class='value'><?php echo count($rows); ?></div></div>
    <div class='stat'><div class='label'>Total penjualan</div><div class='value'><?php echo rupiah($omzet); ?></div></div>
  </div>

  <?php if (!$rows): ?>
    <div class='panel empty'><p>Belum ada transaksi pada rentang ini.</p><a class='btn' href='pos.php'>Buka kasir</a></div>
  <?php else: ?>
  <div class='table-wrap'>
    <table>
      <thead><tr><th>No. transaksi</th><th>Waktu</th><th>Kasir</th><th class='right'>Item</th><th class='right'>Total</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href='struk.php?id=<?php echo (int)$r['id']; ?>'><?php echo h(kode_trx($r['id'], $r['created_at'])); ?></a></td>
          <td class='num'><?php echo tgl_jam($r['created_at']); ?></td>
          <td><?php echo h($r['kasir_nama'] ?: ($r['username'] ?? '(dihapus)')); ?></td>
          <td class='right num'><?php echo (int)$r['jml']; ?></td>
          <td class='right num'><b><?php echo rupiah($r['total']); ?></b></td>
          <td class='right'><a href='struk.php?id=<?php echo (int)$r['id']; ?>'>Lihat struk</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (count($rows) >= 500): ?><p class='hint'>Menampilkan 500 transaksi terbaru. Persempit rentang tanggal untuk melihat yang lain.</p><?php endif; ?>
  <?php endif; ?>
<?php page_end(); ?>
