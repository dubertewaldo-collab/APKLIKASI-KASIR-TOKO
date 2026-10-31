<?php
require 'config/koneksi.php';
require 'config/auth.php';
require 'config/layout.php';
require_login(['admin', 'kasir']);

// Error yang boleh ditampilkan ke kasir (beda dengan error sistem).
class UserError extends Exception {}

// Simpan transaksi. Harga & stok SELALU diambil dari database, bukan dari browser.
function proses_transaksi($k, $uid, $cart, $bayar) {
    if (!is_array($cart) || !$cart) throw new UserError('Keranjang masih kosong.');

    $qtyById = [];
    foreach ($cart as $row) {
        $id = intval($row['id'] ?? 0);
        $qty = intval($row['qty'] ?? 0);
        if ($id < 1 || $qty < 1 || $qty > 9999) throw new UserError('Data keranjang tidak valid.');
        $qtyById[$id] = ($qtyById[$id] ?? 0) + $qty;
    }
    ksort($qtyById);   // urutan konsisten mengurangi risiko deadlock

    mysqli_begin_transaction($k);
    try {
        $items = [];
        $total = 0.0;
        foreach ($qtyById as $id => $qty) {
            $p = db_one($k, "SELECT id, nama, harga, stok FROM produk WHERE id = ? FOR UPDATE", 'i', [$id]);
            if (!$p) throw new UserError('Ada produk yang sudah dihapus. Muat ulang halaman.');
            if ((int)$p['stok'] < $qty) throw new UserError('Stok "' . $p['nama'] . '" tinggal ' . (int)$p['stok'] . '.');
            $harga = (float)$p['harga'];
            $sub = round($harga * $qty, 2);
            $total += $sub;
            $items[] = ['id' => (int)$p['id'], 'nama' => $p['nama'], 'harga' => $harga, 'qty' => $qty, 'sub' => $sub];
        }
        if ($bayar < $total) throw new UserError('Uang yang diterima kurang dari total.');
        $kembali = round($bayar - $total, 2);

        db_exec($k, "INSERT INTO transaksi (user_id, total, bayar, kembali, created_at) VALUES (?, ?, ?, ?, ?)",
            'iddds', [$uid, $total, $bayar, $kembali, date('Y-m-d H:i:s')]);
        $trx_id = mysqli_insert_id($k);

        foreach ($items as $it) {
            db_exec($k, "INSERT INTO transaksi_item (transaksi_id, produk_id, nama_produk, harga, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)",
                'iisdid', [$trx_id, $it['id'], $it['nama'], $it['harga'], $it['qty'], $it['sub']]);
            db_exec($k, "UPDATE produk SET stok = stok - ? WHERE id = ?", 'ii', [$it['qty'], $it['id']]);
        }
        mysqli_commit($k);
        return $trx_id;
    } catch (Throwable $e) {
        mysqli_rollback($k);
        throw $e;
    }
}

$error = '';
$initCart = [];
$bayarAwal = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cart = json_decode($_POST['cart'] ?? '[]', true);
    $bayar = (float)preg_replace('/\D/', '', $_POST['bayar'] ?? '0');
    try {
        $id = proses_transaksi($koneksi, intval($_SESSION['user_id']), $cart, $bayar);
        header('Location: struk.php?id=' . $id);
        exit;
    } catch (UserError $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('POS: ' . $e->getMessage());
        $error = 'Transaksi gagal disimpan. Coba lagi.';
    }
    // Pertahankan keranjang supaya kasir tidak mengetik ulang
    $initCart = is_array($cart) ? array_values($cart) : [];
    $bayarAwal = $bayar;
}

$produk = [];
foreach (db_all($koneksi, "SELECT id, kode, nama, harga, stok FROM produk ORDER BY nama ASC") as $p) {
    $produk[] = ['id' => (int)$p['id'], 'kode' => $p['kode'], 'nama' => $p['nama'], 'harga' => (float)$p['harga'], 'stok' => (int)$p['stok']];
}
$J = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

page_start('Kasir', '', true);
?>
  <?php if ($error): ?><div class='alert-msg' role='alert'><?php echo h($error); ?></div><?php endif; ?>
  <div class='pos'>
    <section class='pos-products' aria-label='Daftar produk'>
      <label class='sr-only' for='cari'>Cari produk</label>
      <input type='search' id='cari' class='pos-search' placeholder='Cari nama produk, atau scan / ketik kode lalu Enter' autocomplete='off' autofocus>
      <div id='grid' class='pos-grid'></div>
    </section>

    <aside class='pos-cart panel' aria-label='Keranjang'>
      <div class='panel-head'><h2>Keranjang</h2><button type='button' class='btn btn-ghost btn-sm' id='btnKosong'>Kosongkan</button></div>
      <div id='cartList'></div>
      <p id='pesan' class='pos-msg' role='status'></p>

      <div class='cart-total'><span>Total</span><b id='total'>Rp 0</b></div>

      <form method='post' id='payForm' autocomplete='off'>
        <?php echo csrf_field(); ?>
        <input type='hidden' name='cart' id='cartInput'>
        <input type='hidden' name='bayar' id='bayarInput'>
        <div class='form-row'>
          <label for='bayarView'>Uang diterima</label>
          <input type='text' id='bayarView' inputmode='numeric' placeholder='0'>
        </div>
        <div class='quick' id='quick'>
          <button type='button' data-n='pas'>Uang pas</button>
          <button type='button' data-n='10000'>10.000</button>
          <button type='button' data-n='20000'>20.000</button>
          <button type='button' data-n='50000'>50.000</button>
          <button type='button' data-n='100000'>100.000</button>
        </div>
        <div class='cart-change'><span id='kembaliLabel'>Kembalian</span><b id='kembali'>Rp 0</b></div>
        <button class='btn btn-block btn-lg' type='submit' id='btnBayar' disabled>Bayar &amp; cetak struk</button>
      </form>
    </aside>
  </div>

<script>
(function () {
  var PRODUK = <?php echo json_encode($produk, $J); ?>;
  var INIT = <?php echo json_encode($initCart, $J); ?>;
  var BAYAR0 = <?php echo (int)$bayarAwal; ?>;
  var LOW = <?php echo (int)STOK_MENIPIS; ?>;

  var byId = {};
  PRODUK.forEach(function (p) { byId[p.id] = p; });
  var cart = {};          // id -> qty
  var bayar = BAYAR0 || 0;

  function $(id) { return document.getElementById(id); }
  var cari = $('cari'), grid = $('grid'), list = $('cartList'), totalEl = $('total'),
      bayarView = $('bayarView'), kembaliEl = $('kembali'), kembaliLabel = $('kembaliLabel'),
      btnBayar = $('btnBayar'), form = $('payForm'), cartInput = $('cartInput'),
      bayarInput = $('bayarInput'), pesan = $('pesan');

  function rp(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
  }
  function total() {
    var t = 0;
    Object.keys(cart).forEach(function (id) { t += byId[id].harga * cart[id]; });
    return t;
  }
  var timer;
  function info(msg) {
    pesan.textContent = msg;
    clearTimeout(timer);
    timer = setTimeout(function () { pesan.textContent = ''; }, 2500);
  }

  function add(id) {
    var p = byId[id];
    if (!p) return;
    var q = (cart[id] || 0) + 1;
    if (q > p.stok) { info('Stok ' + p.nama + ' hanya ' + p.stok + '.'); return; }
    cart[id] = q;
    renderCart();
  }
  function setQty(id, q) {
    var p = byId[id];
    if (q > p.stok) { info('Stok ' + p.nama + ' hanya ' + p.stok + '.'); q = p.stok; }
    if (q <= 0) delete cart[id]; else cart[id] = q;
    renderCart();
  }

  function renderGrid() {
    var f = cari.value.trim().toLowerCase();
    grid.textContent = '';
    var shown = 0;
    PRODUK.forEach(function (p) {
      if (f && p.nama.toLowerCase().indexOf(f) < 0 && (p.kode || '').toLowerCase().indexOf(f) < 0) return;
      shown++;
      var b = el('button', 'tile');
      b.type = 'button';
      b.appendChild(el('span', 'tile-name', p.nama));
      b.appendChild(el('span', 'tile-price', rp(p.harga)));
      b.appendChild(el('span', 'tile-stock' + (p.stok <= LOW ? ' low' : ''), p.stok > 0 ? 'Stok ' + p.stok : 'Habis'));
      if (p.stok <= 0) b.disabled = true;
      b.onclick = function () { add(p.id); };
      grid.appendChild(b);
    });
    if (!shown) {
      grid.appendChild(el('p', 'muted pos-empty', PRODUK.length ? 'Tidak ada produk yang cocok.' : 'Belum ada produk. Tambahkan lewat menu Produk.'));
    }
  }

  function renderCart() {
    list.textContent = '';
    var ids = Object.keys(cart);
    if (!ids.length) list.appendChild(el('p', 'cart-empty', 'Belum ada barang. Pilih produk di sebelah kiri.'));
    ids.forEach(function (id) {
      var p = byId[id], q = cart[id];
      var row = el('div', 'line');
      row.appendChild(el('div', 'line-name', p.nama));
      row.appendChild(el('div', 'line-total', rp(p.harga * q)));
      row.appendChild(el('div', 'line-sub', rp(p.harga) + ' / pcs'));
      var ctl = el('div', 'line-ctl');
      var minus = el('button', null, '\u2212');
      minus.type = 'button';
      minus.setAttribute('aria-label', 'Kurangi ' + p.nama);
      minus.onclick = function () { setQty(id, q - 1); };
      var plus = el('button', null, '+');
      plus.type = 'button';
      plus.setAttribute('aria-label', 'Tambah ' + p.nama);
      plus.onclick = function () { setQty(id, q + 1); };
      var del = el('button', 'del', '\u00d7');
      del.type = 'button';
      del.setAttribute('aria-label', 'Hapus ' + p.nama);
      del.onclick = function () { setQty(id, 0); };
      ctl.appendChild(minus);
      ctl.appendChild(el('span', 'qty', String(q)));
      ctl.appendChild(plus);
      ctl.appendChild(del);
      row.appendChild(ctl);
      list.appendChild(row);
    });
    totalEl.textContent = rp(total());
    updateBayar();
  }

  function updateBayar() {
    var t = total(), n = Object.keys(cart).length, sel = bayar - t;
    if (n && bayar >= t) {
      kembaliLabel.textContent = 'Kembalian';
      kembaliEl.textContent = rp(sel);
      kembaliEl.className = 'ok';
    } else if (n && bayar > 0) {
      kembaliLabel.textContent = 'Kurang';
      kembaliEl.textContent = rp(-sel);
      kembaliEl.className = 'bad';
    } else {
      kembaliLabel.textContent = 'Kembalian';
      kembaliEl.textContent = rp(0);
      kembaliEl.className = '';
    }
    btnBayar.disabled = !(n && bayar >= t);
  }

  function setBayar(n) {
    bayar = Math.max(0, Math.min(n, 999999999999));
    bayarView.value = bayar ? bayar.toLocaleString('id-ID') : '';
    updateBayar();
  }

  bayarView.addEventListener('input', function () {
    var d = bayarView.value.replace(/\D/g, '').slice(0, 12);
    setBayar(d ? parseInt(d, 10) : 0);
  });
  $('quick').addEventListener('click', function (e) {
    var n = e.target.getAttribute('data-n');
    if (!n) return;
    setBayar(n === 'pas' ? total() : parseInt(n, 10));
  });
  $('btnKosong').addEventListener('click', function () {
    cart = {};
    setBayar(0);
    renderCart();
    cari.focus();
  });

  cari.addEventListener('input', renderGrid);
  cari.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var f = cari.value.trim().toLowerCase();
    if (!f) return;
    var hit = PRODUK.filter(function (p) { return (p.kode || '').toLowerCase() === f; })[0];
    if (!hit) {
      hit = PRODUK.filter(function (p) {
        return p.nama.toLowerCase().indexOf(f) >= 0 || (p.kode || '').toLowerCase().indexOf(f) >= 0;
      })[0];
    }
    if (!hit) { info('Produk tidak ditemukan.'); return; }
    if (hit.stok <= 0) { info(hit.nama + ' sedang habis.'); return; }
    add(hit.id);
    cari.value = '';
    renderGrid();
  });

  form.addEventListener('submit', function (e) {
    if (!Object.keys(cart).length || bayar < total()) { e.preventDefault(); return; }
    cartInput.value = JSON.stringify(Object.keys(cart).map(function (id) {
      return { id: parseInt(id, 10), qty: cart[id] };
    }));
    bayarInput.value = String(bayar);
    btnBayar.disabled = true;   // cegah klik ganda
  });

  // Pulihkan keranjang kalau transaksi sebelumnya ditolak server
  INIT.forEach(function (r) {
    var p = byId[parseInt(r.id, 10)];
    var q = Math.min(parseInt(r.qty, 10) || 0, p ? p.stok : 0);
    if (p && q > 0) cart[p.id] = (cart[p.id] || 0) + q;
  });
  renderGrid();
  renderCart();
  setBayar(bayar);
})();
</script>
<?php page_end(); ?>
