<?php
require '../config/koneksi.php';
require '../config/auth.php';
require '../config/layout.php';
require_login(['admin'], '../');

$msg = '';
$edit = null;   // baris produk yang sedang diedit

function baca_form() {
    $kode = trim($_POST['kode'] ?? '');
    return [
        'kode' => $kode === '' ? null : $kode,
        'nama' => trim($_POST['nama'] ?? ''),
        'harga' => (float)($_POST['harga'] ?? 0),
        'stok' => intval($_POST['stok'] ?? 0),
    ];
}
function valid_form($f) {
    if ($f['nama'] === '' || strlen($f['nama']) > 100) return 'Nama wajib diisi (maks. 100 karakter).';
    if ($f['kode'] !== null && strlen($f['kode']) > 30) return 'Kode maksimal 30 karakter.';
    if ($f['harga'] <= 0) return 'Harga harus lebih dari 0.';
    if ($f['stok'] < 0) return 'Stok tidak boleh negatif.';
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';
    try {
        if ($aksi === 'tambah' || $aksi === 'ubah') {
            $f = baca_form();
            $msg = valid_form($f);
            if ($msg === '') {
                if ($aksi === 'tambah') {
                    db_exec($koneksi, "INSERT INTO produk (kode, nama, harga, stok) VALUES (?, ?, ?, ?)", 'ssdi', [$f['kode'], $f['nama'], $f['harga'], $f['stok']]);
                } else {
                    db_exec($koneksi, "UPDATE produk SET kode = ?, nama = ?, harga = ?, stok = ? WHERE id = ?", 'ssdii', [$f['kode'], $f['nama'], $f['harga'], $f['stok'], intval($_POST['id'] ?? 0)]);
                }
                header('Location: produk.php'); exit;
            }
            if ($aksi === 'ubah') { $edit = $f + ['id' => intval($_POST['id'] ?? 0)]; }
        } elseif ($aksi === 'hapus') {
            db_exec($koneksi, "DELETE FROM produk WHERE id = ?", 'i', [intval($_POST['id'] ?? 0)]);
            header('Location: produk.php'); exit;
        }
    } catch (mysqli_sql_exception $e) {
        $msg = ($e->getCode() === 1062) ? 'Kode produk sudah dipakai produk lain.' : 'Gagal menyimpan produk.';
        if ($aksi === 'ubah') { $edit = baca_form() + ['id' => intval($_POST['id'] ?? 0)]; }
    }
}

if ($edit === null && isset($_GET['edit'])) {
    $edit = db_one($koneksi, "SELECT * FROM produk WHERE id = ?", 'i', [intval($_GET['edit'])]);
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $like = '%' . $q . '%';
    $produk = db_all($koneksi, "SELECT * FROM produk WHERE nama LIKE ? OR kode LIKE ? ORDER BY nama ASC", 'ss', [$like, $like]);
} else {
    $produk = db_all($koneksi, "SELECT * FROM produk ORDER BY nama ASC");
}

page_start('Produk', '../');
?>
  <div class='page-head'>
    <div>
      <h1>Produk</h1>
      <p>Harga dan stok yang tampil di layar kasir.</p>
    </div>
  </div>

  <?php if ($msg): ?><div class='alert-msg' role='alert'><?php echo h($msg); ?></div><?php endif; ?>

  <section class='panel'>
    <h2><?php echo $edit ? 'Ubah produk' : 'Tambah produk'; ?></h2>
    <form method='post' class='row-form'>
      <?php echo csrf_field(); ?>
      <input type='hidden' name='aksi' value='<?php echo $edit ? 'ubah' : 'tambah'; ?>'>
      <?php if ($edit): ?><input type='hidden' name='id' value='<?php echo (int)$edit['id']; ?>'><?php endif; ?>
      <div style='flex:1;min-width:130px'><label for='kode'>Kode / barcode</label><input type='text' id='kode' name='kode' maxlength='30' value='<?php echo h($edit['kode'] ?? ''); ?>' placeholder='opsional'></div>
      <div style='flex:2;min-width:200px'><label for='nama'>Nama produk</label><input type='text' id='nama' name='nama' maxlength='100' required value='<?php echo h($edit['nama'] ?? ''); ?>'></div>
      <div style='flex:1;min-width:120px'><label for='harga'>Harga (Rp)</label><input type='number' id='harga' name='harga' min='1' step='any' required value='<?php echo $edit ? (float)$edit['harga'] : ''; ?>'></div>
      <div style='flex:1;min-width:100px'><label for='stok'>Stok</label><input type='number' id='stok' name='stok' min='0' step='1' required value='<?php echo $edit ? (int)$edit['stok'] : '0'; ?>'></div>
      <button class='btn' type='submit'><?php echo $edit ? 'Simpan perubahan' : 'Tambah'; ?></button>
      <?php if ($edit): ?><a class='btn btn-ghost' href='produk.php'>Batal</a><?php endif; ?>
    </form>
  </section>

  <form method='get' class='filter' style='margin-bottom:14px'>
    <div style='flex:1'><label class='sr-only' for='q'>Cari produk</label><input type='search' id='q' name='q' value='<?php echo h($q); ?>' placeholder='Cari nama atau kode'></div>
    <button class='btn btn-ghost' type='submit'>Cari</button>
  </form>

  <?php if (!$produk): ?>
    <div class='panel empty'><p><?php echo $q !== '' ? 'Tidak ada produk yang cocok.' : 'Belum ada produk. Tambahkan produk pertama di atas.'; ?></p></div>
  <?php else: ?>
  <div class='table-wrap'>
    <table>
      <thead><tr><th>Kode</th><th>Nama</th><th class='right'>Harga</th><th class='right'>Stok</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($produk as $p): $s = (int)$p['stok']; ?>
        <tr>
          <td class='muted'><?php echo h($p['kode'] ?? '-'); ?></td>
          <td><b><?php echo h($p['nama']); ?></b></td>
          <td class='right num'><?php echo rupiah($p['harga']); ?></td>
          <td class='right'><span class='badge <?php echo $s === 0 ? 'badge-bad' : ($s <= STOK_MENIPIS ? 'badge-warn' : 'badge-ok'); ?>'><?php echo $s === 0 ? 'Habis' : $s; ?></span></td>
          <td class='actions right'>
            <a class='btn btn-ghost btn-sm' href='produk.php?edit=<?php echo (int)$p['id']; ?>'>Ubah</a>
            <form method='post' class='inline-form' onsubmit="return confirm('Hapus produk ini? Riwayat transaksi tetap tersimpan.');">
              <?php echo csrf_field(); ?>
              <input type='hidden' name='aksi' value='hapus'>
              <input type='hidden' name='id' value='<?php echo (int)$p['id']; ?>'>
              <button class='btn btn-danger btn-sm' type='submit'>Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
<?php page_end(); ?>
