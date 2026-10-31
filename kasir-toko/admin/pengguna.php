<?php
require '../config/koneksi.php';
require '../config/auth.php';
require '../config/layout.php';
require_login(['admin'], '../');

$msg = '';
$ok = '';
$me = intval($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';
    try {
        if ($aksi === 'tambah') {
            $username = trim($_POST['username'] ?? '');
            $nama = trim($_POST['nama'] ?? '');
            $pass = $_POST['password'] ?? '';
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'kasir';
            if ($username === '' || strlen($username) > 50 || strlen($nama) > 100) {
                $msg = 'Username wajib diisi (maks. 50 karakter), nama maksimal 100 karakter.';
            } elseif (strlen($pass) < 6) {
                $msg = 'Password minimal 6 karakter.';
            } else {
                db_exec($koneksi, "INSERT INTO users (username, password, nama, role) VALUES (?, ?, ?, ?)", 'ssss', [$username, password_hash($pass, PASSWORD_DEFAULT), $nama, $role]);
                header('Location: pengguna.php'); exit;
            }
        } elseif ($aksi === 'password') {
            $id = intval($_POST['id'] ?? 0);
            $pass = $_POST['password'] ?? '';
            if (strlen($pass) < 6) {
                $msg = 'Password baru minimal 6 karakter.';
            } else {
                db_exec($koneksi, "UPDATE users SET password = ? WHERE id = ?", 'si', [password_hash($pass, PASSWORD_DEFAULT), $id]);
                $ok = 'Password berhasil diganti.';
            }
        } elseif ($aksi === 'hapus') {
            $id = intval($_POST['id'] ?? 0);
            if ($id === $me) {
                $msg = 'Kamu tidak bisa menghapus akunmu sendiri.';
            } else {
                db_exec($koneksi, "DELETE FROM users WHERE id = ?", 'i', [$id]);
                header('Location: pengguna.php'); exit;
            }
        }
    } catch (mysqli_sql_exception $e) {
        $msg = ($e->getCode() === 1062) ? 'Username sudah dipakai.' : 'Gagal menyimpan data pengguna.';
    }
}

$users = db_all($koneksi, "SELECT id, username, nama, role FROM users ORDER BY role ASC, username ASC");

page_start('Pengguna', '../');
?>
  <div class='page-head'>
    <div>
      <h1>Pengguna</h1>
      <p>Buat akun untuk kasir dan atur password.</p>
    </div>
  </div>

  <?php if ($msg): ?><div class='alert-msg' role='alert'><?php echo h($msg); ?></div><?php endif; ?>
  <?php if ($ok): ?><div class='ok-msg' role='status'><?php echo h($ok); ?></div><?php endif; ?>

  <section class='panel'>
    <h2>Tambah pengguna</h2>
    <form method='post' class='row-form'>
      <?php echo csrf_field(); ?>
      <input type='hidden' name='aksi' value='tambah'>
      <div style='flex:1;min-width:140px'><label for='username'>Username</label><input type='text' id='username' name='username' maxlength='50' required autocomplete='off'></div>
      <div style='flex:1;min-width:140px'><label for='nama'>Nama</label><input type='text' id='nama' name='nama' maxlength='100'></div>
      <div style='flex:1;min-width:140px'><label for='password'>Password</label><input type='password' id='password' name='password' minlength='6' required autocomplete='new-password'></div>
      <div style='min-width:120px'><label for='role'>Peran</label><select id='role' name='role'><option value='kasir'>Kasir</option><option value='admin'>Admin</option></select></div>
      <button class='btn' type='submit'>Tambah</button>
    </form>
  </section>

  <div class='table-wrap'>
    <table>
      <thead><tr><th>Username</th><th>Nama</th><th>Peran</th><th>Ganti password</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><b><?php echo h($u['username']); ?></b><?php if ((int)$u['id'] === $me) echo " <span class='muted small'>(kamu)</span>"; ?></td>
          <td><?php echo h($u['nama']); ?></td>
          <td><span class='badge <?php echo $u['role'] === 'admin' ? 'badge-info' : ''; ?>'><?php echo $u['role'] === 'admin' ? 'Admin' : 'Kasir'; ?></span></td>
          <td>
            <form method='post' class='inline-form'>
              <?php echo csrf_field(); ?>
              <input type='hidden' name='aksi' value='password'>
              <input type='hidden' name='id' value='<?php echo (int)$u['id']; ?>'>
              <input type='password' class='inline' name='password' minlength='6' placeholder='Password baru' style='width:150px' aria-label='Password baru untuk <?php echo h($u['username']); ?>' autocomplete='new-password' required>
              <button class='btn btn-ghost btn-sm' type='submit'>Simpan</button>
            </form>
          </td>
          <td class='right'>
            <?php if ((int)$u['id'] !== $me): ?>
            <form method='post' class='inline-form' onsubmit="return confirm('Hapus pengguna ini? Riwayat transaksinya tetap tersimpan.');">
              <?php echo csrf_field(); ?>
              <input type='hidden' name='aksi' value='hapus'>
              <input type='hidden' name='id' value='<?php echo (int)$u['id']; ?>'>
              <button class='btn btn-danger btn-sm' type='submit'>Hapus</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php page_end(); ?>
