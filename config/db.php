<?php
// ============================================================
//  config/db.php : เชื่อมต่อฐานข้อมูล + ฟังก์ชันช่วยที่ทุกหน้าเรียกใช้
// ============================================================

require __DIR__ . '/session.php';                  // session ปลอดภัย + security headers + CSRF (ตรวจ POST อัตโนมัติ)

$host = "localhost";
$user = "root";      // ค่า default ของ XAMPP
$password = "";      // ค่า default ของ XAMPP จะไม่มีรหัสผ่าน
$dbname = "petcare_db";

// เชื่อมต่อฐานข้อมูลด้วย MySQLi
$conn = mysqli_connect($host, $user, $password, $dbname);
if (!$conn) {
    error_log("DB connect failed: " . mysqli_connect_error());   // รายละเอียดเก็บใน log ไม่แสดงให้ผู้ใช้เห็น
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่ภายหลัง");
}
mysqli_set_charset($conn, "utf8mb4");              // รองรับภาษาไทย

date_default_timezone_set('Asia/Bangkok');         // ใช้เวลาไทยทั้งระบบ

// ---------- ฟังก์ชันช่วย ----------

// e() : กัน XSS ก่อนแสดงข้อความออกหน้าเว็บ (escape ทั้ง " และ ')
function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

// q() : รัน SQL แบบ Prepared Statement (กัน SQL Injection)
//   ตัวอย่าง q($conn, "DELETE FROM pets WHERE pet_id=?", "i", $id);
//   ชนิดข้อมูล: i = ตัวเลขจำนวนเต็ม, s = ข้อความ, d = ทศนิยม
function q($conn, $sql, $types = '', ...$params) {
    $st = mysqli_prepare($conn, $sql);
    if ($types) mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    return $st;
}

// rows() : รัน SELECT แล้วคืนผลเป็น array ของแถว
//   ตัวอย่าง $pets = rows($conn, "SELECT * FROM pets WHERE owner_id=?", "i", $id);
function rows($conn, $sql, $types = '', ...$params) {
    return mysqli_fetch_all(mysqli_stmt_get_result(q($conn, $sql, $types, ...$params)), MYSQLI_ASSOC);
}

// need() : บังคับ Login (และเช็ค role ถ้าระบุ) ถ้าไม่ผ่านให้เด้งไปหน้า login
//   need() = ขอแค่ Login   |   need(['Admin']) = เฉพาะ Admin
//   เช็คกับฐานข้อมูลทุกครั้ง : ผู้ใช้ที่ถูกลบ / ถูกเปลี่ยน role / พนักงานที่ตั้งเป็น Inactive จะถูกตัดสิทธิ์ทันที
function need($roles = []) {
    global $conn;
    $u = null;
    if (isset($_SESSION['user_id'])) {
        $u = rows($conn, "SELECT role FROM users WHERE user_id=?", "i", $_SESSION['user_id'])[0] ?? null;
        if ($u && $u['role'] === 'Staff'
            && rows($conn, "SELECT 1 FROM staffs WHERE user_id=? AND status='Inactive'", "i", $_SESSION['user_id'])) {
            $u = null;
        }
    }
    if (!$u) {
        // ไม่มีบัญชีหรือ session ใช้งานไม่ได้จริง จึงค่อยให้เข้าสู่ระบบใหม่
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // อัปเดตข้อมูลบัญชีจากฐานข้อมูลล่าสุด แต่คง session ไว้
    $_SESSION['user_role'] = $u['role'];
    $profile = rows($conn, "SELECT name FROM users WHERE user_id=?", "i", $_SESSION['user_id'])[0] ?? null;
    if ($profile) $_SESSION['user_name'] = $profile['name'];

    // ผู้ใช้ล็อกอินอยู่แต่ไม่มีสิทธิ์: กลับไปยังหน้าที่เข้าได้ล่าสุด
    // ใช้ session เพราะเมื่อพิมพ์ URL โดยตรง เบราว์เซอร์มักไม่ส่ง HTTP_REFERER มาให้
    if ($roles && !in_array($u['role'], $roles, true)) {
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        $lastAllowed = $_SESSION['last_allowed_url'] ?? '';
        $lastPath = is_string($lastAllowed) ? (parse_url($lastAllowed, PHP_URL_PATH) ?: '') : '';

        // ป้องกัน redirect กลับ URL เดิม/วนลูป และอนุญาตเฉพาะ path ภายในเว็บ
        if (is_string($lastAllowed) && $lastAllowed !== ''
            && str_starts_with($lastAllowed, '/')
            && !str_starts_with($lastAllowed, '//')
            && $lastPath !== $currentPath
            && basename($lastPath) !== 'login.php') {
            header('Location: ' . $lastAllowed);
            exit();
        }

        // กรณียังไม่มีหน้าที่เข้าได้ก่อนหน้า ให้กลับไปยังหน้าแรกของ Role
        $home = [
            'Customer' => 'index.php',
            'Staff'    => 'staff_bookings.php',
            'Admin'    => 'admin_dashboard.php'
        ];
        header('Location: ' . ($home[$u['role']] ?? 'index.php'));
        exit();
    }

    // บันทึกหน้าปัจจุบันเป็นหน้าที่เข้าถึงได้ล่าสุด เฉพาะหลังผ่านการตรวจสิทธิ์แล้ว
    // เก็บ path ภายในเว็บพร้อม query string เพื่อกลับไปหน้าเดิมได้ เช่น booking.php?service=2
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '';
    if (is_string($requestUri) && $requestUri !== ''
        && str_starts_with($requestUri, '/')
        && !str_starts_with($requestUri, '//')
        && basename($requestPath) !== 'login.php') {
        $_SESSION['last_allowed_url'] = $requestUri;
    }
}

// ---------- จำกัดการเดารหัสผ่าน (ไม่ต้องเพิ่มตารางในฐานข้อมูล เก็บในโฟลเดอร์ temp ของเครื่อง) ----------
// ผิดเกิน 5 ครั้ง / 15 นาที ต่ออีเมล+IP  หรือเกิน 30 ครั้ง / 15 นาที ต่อ IP = ล็อกชั่วคราว
function login_attempts($f) {
    $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : [];
    return array_values(array_filter((array)($d['t'] ?? []), fn($t) => $t > time() - 900));
}
function login_files($email) {
    $b = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'petcare_login_';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return [$b . hash('sha256', strtolower($email) . '|' . $ip) . '.json', $b . 'ip_' . hash('sha256', $ip) . '.json'];
}
function login_blocked($email) {
    [$a, $b] = login_files($email);
    return count(login_attempts($a)) >= 5 || count(login_attempts($b)) >= 30;
}
function login_fail($email) {
    foreach (login_files($email) as $f) {
        $t = login_attempts($f);
        $t[] = time();
        @file_put_contents($f, json_encode(['t' => $t]), LOCK_EX);
    }
}
function login_clear($email) { @unlink(login_files($email)[0]); }

// thai_date() : แปลง 2026-10-25 เป็น "25 ต.ค. 2026"
function thai_date($d) {
    $m = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $t = strtotime($d);
    return date('j', $t) . ' ' . $m[(int)date('n', $t)] . ' ' . date('Y', $t);
}

// ---------- ฟังก์ชันตรวจรูปแบบข้อมูล (ใช้ในหน้า register / admin) : ถูกต้อง = true ----------

// อีเมล : ชื่อหน้า @ ต้องไม่ขึ้นต้น/ลงท้าย/ซ้อนเครื่องหมายพิเศษ, ท้ายสุดของโดเมน (เช่น .com) ต้องเป็นตัวอักษร A-Z อย่างน้อย 3 ตัว
//   ถ้าต้องการให้รับ .th / .io ด้วย ให้เปลี่ยน {3,} เป็น {2,}
function valid_email($s) {
    return strlen($s) <= 100
        && preg_match('/^[A-Za-z0-9]+([._%+-][A-Za-z0-9]+)*@([A-Za-z0-9]+(-[A-Za-z0-9]+)*\.)+[A-Za-z]{2,}$/', $s);
}

// เบอร์โทร : ตัวเลข 10 หลัก ขึ้นต้นด้วย 0 (เช่น 0812345678)
function valid_phone($s) { return preg_match('/^0[0-9]{9}$/', $s); }

// ชื่อ : ตัวอักษรไทย/อังกฤษ (รวมสระวรรณยุกต์) เว้นวรรค . ' - ได้ ยาว 2-100 ตัว ห้ามมีตัวเลข
function valid_name($s) { return preg_match('/^[\p{L}\p{M}][\p{L}\p{M}\s.\'-]{1,99}$/u', $s); }

// รหัสผ่าน : 8-72 ตัวอักษร ต้องมีทั้งตัวอักษรและตัวเลข
function valid_password($s) {
    return strlen($s) >= 8 && strlen($s) <= 72 && preg_match('/[A-Za-z]/', $s) && preg_match('/[0-9]/', $s);
}

// ข้อมูลสัตว์เลี้ยง (ใช้ใน pets.php / admin_pets.php) : ประเภทต้องเป็น สุนัข/แมว, ชื่อ-สายพันธุ์-อายุไม่ว่างและไม่ยาวเกิน, น้ำหนัก 0.1-200 กก.
function valid_pet($d) {
    $name = trim((string)($d['name'] ?? ''));
    $breed = trim((string)($d['breed'] ?? ''));
    $age = trim((string)($d['age'] ?? ''));
    $w = (float)($d['weight'] ?? 0);
    return in_array($d['type'] ?? '', ['สุนัข', 'แมว'], true)
        && $name !== '' && mb_strlen($name) <= 100
        && $breed !== '' && mb_strlen($breed) <= 50
        && $age !== '' && mb_strlen($age) <= 20
        && $w >= 0.1 && $w <= 200;
}

// badge() : คืนคลาสสีของป้ายสถานะการจอง
function badge($status) {
    $c = ['Pending'   => 'bg-warning bg-opacity-10 text-warning text-dark',
          'Confirmed' => 'bg-success bg-opacity-10 text-success',
          'Completed' => 'bg-primary bg-opacity-10 text-primary',
          'Cancelled' => 'bg-danger bg-opacity-10 text-danger'];
    return $c[$status] ?? 'bg-secondary';
}

// set_status() : เปลี่ยนสถานะการจอง
//   ถ้าเปลี่ยนเป็น Completed จะบันทึกลงตาราง service_history ให้อัตโนมัติ (ไม่บันทึกซ้ำ)
//   $strict = true (หน้าพนักงาน) : บังคับลำดับ Pending -> Confirmed / Cancelled, Confirmed -> Completed เท่านั้น
//   $strict = false (หน้า Admin) : Admin เปลี่ยนเป็นสถานะใดก็ได้
function set_status($conn, $id, $status, $staff_id = null, $strict = false) {
    $allowed_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
    if (!is_numeric($id) || (int)$id < 1 || !in_array($status, $allowed_statuses, true)) return false;
    $id = (int)$id;
    if ($strict) {
        // พนักงานเปลี่ยนสถานะได้เฉพาะตามลำดับที่กำหนดเท่านั้น
        $allowed = ['Pending' => ['Confirmed', 'Cancelled'], 'Confirmed' => ['Completed']];
        $cur = rows($conn, "SELECT status FROM bookings WHERE booking_id=?", "i", $id)[0]['status'] ?? null;
        if ($cur === null || !in_array($status, $allowed[$cur] ?? [], true)) return false;
    }
    q($conn, "UPDATE bookings SET status=? WHERE booking_id=?", "si", $status, $id);
    if ($status === 'Completed') {
        q($conn, "INSERT INTO service_history (booking_id, pet_id, staff_id, service_date, details_notes, total_price)
                  SELECT b.booking_id, b.pet_id, COALESCE(?, b.staff_id), b.booking_date, b.note, s.price
                  FROM bookings b JOIN services s ON s.service_id = b.service_id
                  WHERE b.booking_id = ?
                  AND NOT EXISTS (SELECT 1 FROM service_history WHERE booking_id = b.booking_id)", "ii", $staff_id, $id);
    }
    return true;
}
?>
