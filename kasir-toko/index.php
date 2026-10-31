<?php
session_name('kasir_toko');
session_start();
require 'config/koneksi.php';
require 'config/layout.php';

// Folder dasar proyek (mis. /kasir-toko), supaya redirect selalu benar
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
function tujuan($role, $base) {
    return $base . ($role === 'admin' ? '/admin/dashboard.php' : '/pos.php');
}

if (isset($_SESSION['user_id'])) {
    header('Location: ' . tujuan($_SESSION['role'] ?? 'kasir', $base));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = db_one($koneksi, "SELECT id, password, role, nama FROM users WHERE username = ? LIMIT 1", 's', [$username]);

    // Pesan sengaja sama agar tidak membocorkan username mana yang terdaftar
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nama'] = $user['nama'];
        header('Location: ' . tujuan($user['role'], $base));
        exit;
    }
    $error = 'Username atau password salah.';
}

html_head('Masuk', '', 'login');
?>
<main class='login-box'>
  <div class='brand brand-lg'><?php echo brand_mark(); ?><span><?php echo h(NAMA_TOKO); ?></span></div>
  <div class='panel'>
    <h1>Masuk</h1>
    <p class='muted' style='margin:4px 0 20px'>Gunakan akun kasir atau admin.</p>
    <?php if ($error): ?><div class='alert-msg' role='alert'><?php echo h($error); ?></div><?php endif; ?>
    <form method='post'>
      <div class='form-row'><label for='username'>Username</label><input type='text' id='username' name='username' autocomplete='username' value='<?php echo h($_POST["username"] ?? ""); ?>' required autofocus></div>
      <div class='form-row'><label for='password'>Password</label><input type='password' id='password' name='password' autocomplete='current-password' required></div>
      <button class='btn btn-block' type='submit'>Masuk</button>
    </form>
  </div>
</main>
</body></html>
