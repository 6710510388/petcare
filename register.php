<?php
// ===== register.php : ตรวจสอบข้อมูลและสมัครสมาชิก =====
require 'config/db.php';

// ตัวแปรเก็บข้อความ Error ของแต่ละช่อง
$name_error = $email_error = $phone_error = '';
$password_error = $cpassword_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับข้อมูลจากฟอร์ม
    $name  = trim((string)($_POST['user-name'] ?? ''));
    $email = trim((string)($_POST['user-email'] ?? ''));
    $phone = trim((string)($_POST['user-phone'] ?? ''));
    $pass  = (string)($_POST['user-password'] ?? '');
    $cpass = (string)($_POST['user-cpassword'] ?? '');

    // ตรวจสอบ Name
    if (!valid_name($name)) {
        $name_error = 'ชื่อต้องเป็นตัวอักษร 2-100 ตัว ไม่มีตัวเลขหรือสัญลักษณ์พิเศษ';
    }

    // ตรวจสอบ Email
    if (!valid_email($email)) {
        $email_error = 'รูปแบบ Email ไม่ถูกต้อง เช่น user@gmail.com';
    } elseif (rows($conn, "SELECT 1 FROM users WHERE email = ?", "s", $email)) {
        $email_error = 'อีเมลนี้ถูกใช้แล้ว';
    }

    // ตรวจสอบ Phone
    if (!valid_phone($phone)) {
        $phone_error = 'เบอร์โทรต้องเป็นตัวเลข 10 หลัก ขึ้นต้นด้วย 0 เช่น 0812345678';
    }

    // ตรวจสอบ Password
    if (!valid_password($pass)) {
        $password_error = 'รหัสผ่าน 8-72 ตัว ต้องมีทั้งตัวอักษรและตัวเลข';
    }

    // ตรวจสอบ Confirm Password
    if ($pass !== $cpass) {
        $cpassword_error = 'รหัสผ่านไม่ตรงกัน';
    }

    // บันทึกข้อมูลเมื่อผ่านการตรวจสอบทุกช่อง
    if (
        $name_error === '' &&
        $email_error === '' &&
        $phone_error === '' &&
        $password_error === '' &&
        $cpassword_error === ''
    ) {
        // บันทึกรหัสผ่านเป็น Hash และกำหนด Role เป็น Customer
        q(
            $conn,
            "INSERT INTO users (name, email, phone, password, role)
             VALUES (?, ?, ?, ?, 'Customer')",
            "ssss",
            $name,
            $email,
            $phone,
            password_hash($pass, PASSWORD_DEFAULT)
        );

        header("Location: login.php?registered=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care - Sign Up</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Fonts (Prompt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <!-- CSS เดิม -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <div class="container-fluid vh-100 overflow-hidden p-0">
        <div class="row g-0 h-100">

            <!-- ฝั่งซ้าย: รูปภาพ -->
            <div class="col-md-6 bg-login-image h-100 d-none d-md-block"></div>

            <!-- ฝั่งขวา: ฟอร์มสมัครสมาชิก -->
            <div class="col-12 col-md-6 h-100 d-flex flex-column justify-content-between p-4 p-md-5 overflow-y-auto">

                <!-- หัวข้อ -->
                <div class="text-center mt-auto mt-md-0">
                    <div class="text-petcare mb-2">
                        <svg class="bi" width="48" height="48" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M4.5 10.5C3.67 10.5 3 11.17 3 12s.67 1.5 1.5 1.5S6 12.83 6 12s-.67-1.5-1.5-1.5zm3-3C6.67 7.5 6 8.17 6 9s.67 1.5 1.5 1.5S9 9.83 9 9s-.67-1.5-1.5-1.5zm9 0c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm3 3c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-1.5-1.5zM12 4.5C10.07 4.5 8.5 6.07 8.5 8c0 2.21 2.69 5.5 3.5 6.36.81-.86 3.5-4.15 3.5-6.36 0-1.93-1.57-3.5-3.5-3.5z"/>
                        </svg>
                    </div>
                    <h2 class="fw-bold text-petcare mb-1">Create Account</h2>
                    <p class="text-muted small">Sign up to get started with Pet Care</p>
                </div>

                <!-- ฟอร์มสมัครสมาชิก -->
                <div class="my-auto w-100" style="max-width: 400px; margin-left: auto; margin-right: auto;">

                    <!-- novalidate เพื่อให้ JavaScript ควบคุมลำดับการตรวจสอบ -->
                    <form action="" method="POST" novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="user-name">Name</label>
                            <input
                                id="user-name"
                                type="text"
                                name="user-name"
                                placeholder="Enter Full Name"
                                required
                                maxlength="100"
                                value="<?= e($name ?? '') ?>"
                                class="form-control"
                            />
                            <span class="text-danger">
                                <?= e($name_error) ?>
                            </span>
                        </div>

                        <div class="mb-3">
                            <label for="user-email">Email</label>
                            <input
                                id="user-email"
                                type="email"
                                name="user-email"
                                placeholder="Email"
                                title="รูปแบบอีเมลไม่ถูกต้อง"
                                required
                                maxlength="100"
                                value="<?= e($email ?? '') ?>"
                                class="form-control"
                            />
                            <span class="text-danger">
                                <?= e($email_error) ?>
                            </span>
                        </div>

                        <!-- ช่องเบอร์โทร -->
                        <div class="mb-3">
                            <label for="user-phone">Phone</label>
                            <input
                                id="user-phone"
                                type="tel"
                                name="user-phone"
                                placeholder="Phone (10 digits)"
                                maxlength="10"
                                pattern="0[0-9]{9}"
                                title="ตัวเลข 10 หลัก ขึ้นต้นด้วย 0 เช่น 0812345678"
                                required
                                value="<?= e($phone ?? '') ?>"
                                class="form-control"
                            />
                            <span class="text-danger">
                                <?= e($phone_error) ?>
                            </span>
                        </div>

                        <div class="mb-3">
                            <label for="user-password">Password</label>
                            <input
                                id="user-password"
                                type="password"
                                name="user-password"
                                placeholder="Password (8+ ตัว มีตัวอักษร+ตัวเลข)"
                                required
                                maxlength="72"
                                pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,72}"
                                title="8-72 ตัว ต้องมีทั้งตัวอักษรและตัวเลข"
                                class="form-control"
                            />
                            <span class="text-danger">
                                <?= e($password_error) ?>
                            </span>
                        </div>

                        <div class="mb-3">
                            <label for="user-cpassword">Confirm Password</label>
                            <input
                                id="user-cpassword"
                                type="password"
                                name="user-cpassword"
                                placeholder="Confirm Password"
                                required
                                class="form-control"
                            />
                            <span class="text-danger">
                                <?= e($cpassword_error) ?>
                            </span>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-petcare rounded-pill py-2 fw-semibold">
                                Sign Up
                            </button>
                        </div>

                        <!-- ลิงก์ไปหน้า Login -->
                        <div class="text-center">
                            <span class="text-muted small">Already have an account?</span>
                            <a href="login.php" class="text-petcare small fw-bold text-decoration-underline ms-1">
                                Login
                            </a>
                        </div>
                    </form>
                </div>


            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- ตรวจสอบฟอร์มตามลำดับจากบนลงล่าง -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('form');

        if (!form) return;

        const name = form.querySelector('[name="user-name"]');
        const email = form.querySelector('[name="user-email"]');
        const phone = form.querySelector('[name="user-phone"]');
        const password = form.querySelector('[name="user-password"]');
        const confirmPassword = form.querySelector('[name="user-cpassword"]');

        // ล้างข้อความผิดพลาดของช่องที่กำลังแก้ไข
        [name, email, phone, password, confirmPassword].forEach(function (field) {
            field.addEventListener('input', function () {
                field.setCustomValidity('');
            });
        });

        form.addEventListener('submit', function (event) {
            // หยุดการส่งฟอร์มก่อน เพื่อควบคุมลำดับการตรวจสอบ
            event.preventDefault();

            // ล้างข้อผิดพลาดเก่าทุกช่อง
            [name, email, phone, password, confirmPassword]
                .forEach(function (field) {
                    field.setCustomValidity('');
                });

            // 1. ตรวจ Name
            if (!name.value.trim()) {
                name.setCustomValidity('กรุณากรอกชื่อ');
                name.reportValidity();
                name.focus();
                return;
            }

            // 2. ตรวจ Email
            const emailPattern =
                /^[A-Za-z0-9]+([._%+-][A-Za-z0-9]+)*@([A-Za-z0-9]+(-[A-Za-z0-9]+)*\.)+[A-Za-z]{2,}$/;

            if (!email.value.trim()) {
                email.setCustomValidity('กรุณากรอก Email');
                email.reportValidity();
                email.focus();
                return;
            }

            if (!emailPattern.test(email.value.trim())) {
                email.setCustomValidity(
                    'รูปแบบ Email ไม่ถูกต้อง เช่น user@gmail.com'
                );
                email.reportValidity();
                email.focus();
                return;
            }

            // 3. ตรวจ Phone
            if (!/^0[0-9]{9}$/.test(phone.value.trim())) {
                phone.setCustomValidity(
                    'กรุณากรอกเบอร์โทร 10 หลัก ขึ้นต้นด้วย 0 เช่น 0812345678'
                );
                phone.reportValidity();
                phone.focus();
                return;
            }

            // 4. ตรวจ Password
            if (!/^(?=.*[A-Za-z])(?=.*[0-9]).{8,72}$/.test(password.value)) {
                password.setCustomValidity(
                    'รหัสผ่านต้องมี 8-72 ตัว และมีทั้งตัวอักษรกับตัวเลข'
                );
                password.reportValidity();
                password.focus();
                return;
            }

            // 5. ตรวจ Confirm Password
            if (!confirmPassword.value) {
                confirmPassword.setCustomValidity('กรุณายืนยันรหัสผ่าน');
                confirmPassword.reportValidity();
                confirmPassword.focus();
                return;
            }

            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('รหัสผ่านไม่ตรงกัน');
                confirmPassword.reportValidity();
                confirmPassword.focus();
                return;
            }

            // ผ่านการตรวจสอบฝั่งหน้าเว็บแล้ว
            // ส่งฟอร์มไปให้ PHP ตรวจสอบซ้ำและบันทึกข้อมูล
            form.submit();
        });
    });
    </script>

</body>
</html>
