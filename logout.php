<?php
// ===== logout.php : ล้าง session ทั้งหมดแล้วกลับหน้าแรก =====
require __DIR__ . '/config/session.php';         // ใช้ค่า session ชุดเดียวกับทั้งระบบ

$_SESSION = [];
if (ini_get('session.use_cookies')) {            // ลบ cookie ของ session ออกจากเบราว์เซอร์ด้วย
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header("Location: index.php");
exit();
