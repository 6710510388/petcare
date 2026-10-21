<?php
// ===== login.php : ตรวจอีเมล/รหัสผ่านจากตาราง users แล้วสร้าง session =====
require 'config/db.php';
$error = '';
$success = isset($_GET['registered']) ? 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ' : '';   // ส่งมาจากหน้า register

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['user-email'] ?? ''));
    $pass  = (string)($_POST['user-password'] ?? '');

    if (login_blocked($email)) {
        // ผิดเกินกำหนด : ล็อกชั่วคราว 15 นาที (ดู login_blocked ใน config/db.php)
        $error = "ลองเข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารอสักครู่ (ประมาณ 15 นาที) แล้วลองใหม่";
    } else {
        // ค้นหาผู้ใช้จากอีเมล
        $u = rows($conn, "SELECT * FROM users WHERE email = ?", "s", $email)[0] ?? null;
        // password_verify : เทียบรหัสที่พิมพ์กับรหัสที่ hash ไว้ใน DB
        if ($u && password_verify($pass, $u['password'])) {
            // พนักงานที่ถูกตั้งเป็น Inactive เข้าสู่ระบบไม่ได้
            if ($u['role'] === 'Staff' && rows($conn, "SELECT 1 FROM staffs WHERE user_id=? AND status='Inactive'", "i", $u['user_id'])) {
                $error = "บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ";
            } else {
                session_regenerate_id(true);       // สร้าง session id ใหม่ตอน login (กัน session fixation)
                unset($_SESSION['csrf']);          // หมุน CSRF token หลังยืนยันตัวตน
                login_clear($email);               // ล็อกอินสำเร็จ = ล้างจำนวนครั้งที่ผิด
                $_SESSION['user_id']   = $u['user_id'];
                $_SESSION['user_name'] = $u['name'];
                $_SESSION['user_role'] = $u['role'];
                // หลังเข้าสู่ระบบสำเร็จ ให้ทุก Role เริ่มที่หน้า Index ก่อน
                header('Location: index.php');
                exit();
            }
        } else {
            login_fail($email);                    // นับจำนวนครั้งที่ผิด
            $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care - Login</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts (Prompt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <!-- ดึงไฟล์ CSS ที่เราแยกไว้นำมาใช้งาน -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <div class="container-fluid vh-100 overflow-hidden p-0">
        <div class="row g-0 h-100">

            <!-- ฝั่งซ้าย: รูปภาพ -->
            <div class="col-md-6 bg-login-image h-100 d-none d-md-block"></div>

            <!-- ฝั่งขวา: ฟอร์ม Login -->
            <div class="col-12 col-md-6 h-100 d-flex flex-column justify-content-between p-4 p-md-5">

                <!-- หัวข้อ -->
                <div class="text-center mt-auto mt-md-0">
                    <div class="text-petcare mb-2">
                        <svg class="bi" width="56" height="56" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M4.5 10.5C3.67 10.5 3 11.17 3 12s.67 1.5 1.5 1.5S6 12.83 6 12s-.67-1.5-1.5-1.5zm3-3C6.67 7.5 6 8.17 6 9s.67 1.5 1.5 1.5S9 9.83 9 9s-.67-1.5-1.5-1.5zm9 0c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm3 3c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-1.5-1.5zM12 4.5C10.07 4.5 8.5 6.07 8.5 8c0 2.21 2.69 5.5 3.5 6.36.81-.86 3.5-4.15 3.5-6.36 0-1.93-1.57-3.5-3.5-3.5z"/>
                        </svg>
                    </div>
                    <h1 class="fw-bold text-petcare mb-1">Pet Care</h1>
                    <p class="text-muted small">Sign In To Continue</p>
                </div>

                <!-- ฟอร์มเข้าสู่ระบบ -->
                <div class="my-auto w-100" style="max-width: 400px; margin-left: auto; margin-right: auto;">
                    <form action="" method="POST">
                        <?= csrf_field() ?>

                        <!-- ข้อความแจ้งเตือน (สมัครสำเร็จ / Login ผิด) -->
                        <?php if ($success): ?><div class="alert alert-success py-2 small"><?= $success ?></div><?php endif; ?>
                        <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= $error ?></div><?php endif; ?>

                        <div class="mb-3">
                            <label for="user-email">Email</label>
                            <input type="text" name="user-email" placeholder="Your Email" required class="form-control" />
                        </div>

                        <div class="mb-3">
                            <label for="user-password">Password</label>
                            <input type="password" name="user-password" placeholder="Your Password" required class="form-control" />
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-petcare rounded-pill py-2 fw-semibold">Login</button>
                        </div>

                        <!-- ลิงก์สลับไปหน้า Sign Up -->
                        <div class="text-center">
                            <span class="text-muted small">Don't have an account?</span>
                            <a href="register.php" class="text-petcare small fw-bold text-decoration-underline ms-1">Sign Up</a>
                        </div>
                    </form>
                </div>


            </div>

        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>