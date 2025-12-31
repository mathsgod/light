<?php
// 檢查請求的路徑是否存在對應的實體檔案
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && file_exists($file)) {
    // 如果檔案存在，回傳 false 讓 PHP 直接供應該靜態檔案
    return false;
}

// 否則，全部導向 index.php
require_once 'index.php';