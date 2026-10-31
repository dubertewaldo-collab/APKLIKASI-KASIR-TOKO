<?php
require '../config/koneksi.php';
require '../config/auth.php';
require '../config/layout.php';
require_login(['admin'], '../');

list($awal, $akhir) = rentang(date('Y-m-d'), date('Y-m-d'));
$hari = db_one($koneksi, "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS omzet FROM transaksi WHERE created_at >= ? AND created_at < ?", 'ss', [$awal, $akhir]);
$item = db_one($koneksi, "SELECT COALESCE(SUM(i.qty), 0) AS n FROM transaksi_item i JOIN transaksi t ON t.id = i.transaksi_id WHERE t.created_at >= ? AND t.created_at < ?", 'ss', [$awal, $akhir]);
$jml_menipis = db_one($koneksi, "SELECT COUNT(*) AS n FROM produk WHERE stok <= ?", 'i', [STOK_MENIPIS])['n'];
$menipis = db_all($koneksi, "SELECT id, nama, stok FROM produk WHERE stok <= ? ORDER BY stok ASC, nama ASC LIMIT 8", 'i', [STOK_MENIPIS]);
$terbaru = db_all($koneksi, "SELECT t.id, t.total, t.created_at, u.nama AS kasir_nama, u.username FROM transaksi t LEFT JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC, t.id DESC LIMIT 6");

page_start('Ringkasan', '../');
?>
  <div class='page-head'>
    <div>
      <h1>Ringkasan hari ini</h1>
      <p><?php echo tgl(date('Y-m-d')); ?></p>
    </div>
    <a class='btn' href='../pos.php'>Buka kasir</a>
  </div>

  <div class='stats'>
    <div class='stat'><div class='label'>Penjualan hari ini</div><div class='value'><?php echo rupiah($hari['omzet']); ?></div></div>
    <div class='stat'><div class='label'>Transaksi</div><div class='value'><?php echo (int)$hari['n']; ?></div></div>
    <div class='stat'><div class='label'>Barang terjual</div><div class='value'><?php echo (int)$item['n']; ?></div></div>
    <a class='stat <?php echo $jml_menipis ? 'alert' : ''; ?>' href='produk.php'>
      <div class='label'>Stok menipis</div><div class='value'><?php echo (int)$jml_menipis; ?></div>
    </a>
  </div>

  <div class='cols'>
    <section class='panel'>
      <div class='panel-head'><h2>Perlu restok</h2><a href='produk.php'>Kelola produk</a></div>
      <?php if (!$menipis): ?>
        <p class='muted'>Semua stok aman.</p>
      <?php else: ?>
        <table><tbody>
        <?php foreach ($menipis as $p): ?>
          <tr><td><?php echo h($p['nama']); ?></td><td class='right'><span class='badge <?php echo (int)$p['stok'] === 0 ? 'badge-bad' : 'badge-warn'; ?>'><?php echo (int)$p['stok'] === 0 ? 'Habis' : 'Sisa ' . (int)$p['stok']; ?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    </section>

    <section class='panel'>
      <div class='panel-head'><h2>Transaksi terbaru</h2><a href='../riwayat.php'>Lihat semua</a></div>
      <?php if (!$terbaru): ?>
        <p class='muted'>Belum ada transaksi.</p>
      <?php else: ?>
        <table><tbody>
        <?php foreach ($terbaru as $r): ?>
          <tr>
            <td><a href='../struk.php?id=<?php echo (int)$r['id']; ?>'><?php echo h(kode_trx($r['id'], $r['created_at'])); ?></a><br><span class='muted small'><?php echo tgl_jam($r['created_at']); ?> &middot; <?php echo h($r['kasir_nama'] ?: ($r['username'] ?? '-')); ?></span></td>
            <td class='right num'><b><?php echo rupiah($r['total']); ?></b></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    </section>
  </div>
<?php page_end(); ?>
