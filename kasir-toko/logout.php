<?php
session_name('kasir_toko');
session_start();
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
