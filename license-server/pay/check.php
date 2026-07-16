<?php
header('Content-Type: text/html; charset=utf-8');
echo '<div dir="rtl" style="font-family:monospace;padding:20px">';
echo '<h2>تشخیص مشکل license-server</h2>';

echo '<b>PHP Version:</b> ' . PHP_VERSION . '<br>';
echo '<b>PDO:</b> ' . (extension_loaded('pdo') ? '✅' : '❌ نصب نیست') . '<br>';
echo '<b>PDO SQLite:</b> ' . (extension_loaded('pdo_sqlite') ? '✅' : '❌ نصب نیست') . '<br>';
echo '<b>cURL:</b> ' . (extension_loaded('curl') ? '✅' : '❌ نصب نیست') . '<br>';

$data_dir = __DIR__ . '/../data/';
echo '<b>data/ وجود دارد:</b> ' . (is_dir($data_dir) ? '✅' : '❌') . '<br>';
echo '<b>data/ قابل نوشتن:</b> ' . (is_writable($data_dir) ? '✅' : '❌ دسترسی ندارد') . '<br>';

try {
    require_once __DIR__ . '/../includes/Database.php';
    Database::get();
    echo '<b>اتصال به SQLite:</b> ✅<br>';
} catch (Exception $e) {
    echo '<b>اتصال به SQLite:</b> ❌ ' . $e->getMessage() . '<br>';
}

echo '</div>';
