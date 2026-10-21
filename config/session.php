<?php
// ============================================================
//  config/session.php : ตั้งค่าความปลอดภัยพื้นฐาน (เรียกจาก config/db.php และ index.php)
//   1) ซ่อน error จากผู้ใช้ (ดู APP_DEBUG)   2) Security headers
//   3) Session ที่ปลอดภัย (HttpOnly / SameSite / strict mode / หมดเวลาเมื่อไม่ใช้งาน)
//   4) CSRF token : ตรวจอัตโนมัติทุกคำขอ POST 
// ============================================================

// เปลี่ยนเป็น true ชั่วคราวเมื่ออยากเห็น error ตอนพัฒนา (ตอนใช้งานจริงต้องเป็น false)
define('APP_DEBUG', false);

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// Exception ที่ไม่มีใครจัดการ : แสดงข้อความทั่วไป ไม่เปิดเผย path / ชื่อ user ของฐานข้อมูล (รายละเอียดอยู่ใน log ของ Apache)
set_exception_handler(function ($ex) {
    error_log((string)$ex);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo APP_DEBUG ? '<pre>' . htmlspecialchars((string)$ex) . '</pre>' : 'เกิดข้อผิดพลาดของระบบ กรุณาลองใหม่อีกครั้ง';
});

// ---------- Security headers ----------
header('X-Frame-Options: SAMEORIGIN');                         // กันถูกฝังในเว็บอื่น (Clickjacking)
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
// อนุญาตเฉพาะแหล่งที่เว็บนี้ใช้จริง : Bootstrap/Chart.js (jsdelivr), ฟอนต์ Google, Font Awesome (cdnjs), รูป (https)
header("Content-Security-Policy: default-src 'self'; "
     . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
     . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
     . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; "
     . "img-src 'self' data: https:; "
     . "frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

// ---------- Session ----------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');       // ไม่รับ session id ที่เซิร์ฟเวอร์ไม่ได้สร้างเอง
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '3600');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),   // ส่งเฉพาะ HTTPS เมื่อเว็บรันบน HTTPS
        'httponly' => true,                        // JavaScript อ่าน cookie ไม่ได้
        'samesite' => 'Lax',                       // ไม่ส่ง cookie ไปกับคำขอข้ามเว็บแบบ POST
    ]);
    session_start();
}

// ไม่มีการใช้งานเกิน 60 นาที = ล้าง session (ต้อง Login ใหม่)
if (isset($_SESSION['last_active']) && time() - $_SESSION['last_active'] > 3600) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_active'] = time();

// ---------- CSRF ----------
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

// csrf_field() : ใส่ในทุก <form method="POST"> 
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

// ตรวจอัตโนมัติ : ทุกคำขอ POST ต้องมี token ที่ตรงกับใน session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        exit('คำขอไม่ถูกต้องหรือหมดอายุ กรุณากลับไปโหลดหน้าเว็บใหม่แล้วลองอีกครั้ง');
    }
}
?>