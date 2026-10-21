<?php
// ใช้การเชื่อมต่อฐานข้อมูลเพื่อตรวจสอบ Role ล่าสุดทุกครั้งที่เปิดหน้า index
require __DIR__ . '/config/db.php';

// ดึงรายการบริการจากฐานข้อมูล เพื่อให้บริการที่ Admin เพิ่มหรือแก้ไขแสดงบนหน้า Index
$indexServices = rows($conn, "SELECT service_id, name, description, price FROM services ORDER BY service_id");

// ถ้าล็อกอินอยู่ ให้ใช้ Role ล่าสุดจากฐานข้อมูล ไม่ใช้ค่าเก่าใน Session
if (isset($_SESSION['user_id'])) {
    $currentUser = rows(
        $conn,
        "SELECT user_id, name, role FROM users WHERE user_id = ?",
        "i",
        (int) $_SESSION['user_id']
    )[0] ?? null;

    // บัญชีถูกลบออกจากฐานข้อมูลแล้ว ให้ยุติ Session
    if (!$currentUser) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit();
    }

    $_SESSION['user_name'] = $currentUser['name'];
    $_SESSION['user_role'] = $currentUser['role'];

    // ถ้าบัญชีเป็น Staff แต่ถูกตั้งสถานะ Inactive ให้ยุติ Session
    if (
        $currentUser['role'] === 'Staff' &&
        rows($conn, "SELECT 1 FROM staffs WHERE user_id = ? AND status = 'Inactive'", "i", (int) $currentUser['user_id'])
    ) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit();
    }

    // อนุญาตให้ทุก Role เปิดหน้า Index ได้ โดยเมนูจะแสดงตามสิทธิ์
    $validRoles = ['Customer', 'Staff', 'Admin'];
    if (!in_array($currentUser['role'], $validRoles, true)) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care - บริการดูแลสัตว์เลี้ยงครบวงจร</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts (Prompt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <!-- ไฟล์ CSS หลัก -->
    <!-- Font Awesome (ไอคอนใน Footer) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- 1. Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-petcare d-flex align-items-center" href="index.php">
                <svg class="bi me-2" width="32" height="32" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M4.5 10.5C3.67 10.5 3 11.17 3 12s.67 1.5 1.5 1.5S6 12.83 6 12s-.67-1.5-1.5-1.5zm3-3C6.67 7.5 6 8.17 6 9s.67 1.5 1.5 1.5S9 9.83 9 9s-.67-1.5-1.5-1.5zm9 0c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm3 3c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-1.5-1.5zM12 4.5C10.07 4.5 8.5 6.07 8.5 8c0 2.21 2.69 5.5 3.5 6.36.81-.86 3.5-4.15 3.5-6.36 0-1.93-1.57-3.5-3.5-3.5z"/>
                </svg>
                Pet Care
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="#index">หน้าแรก</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">บริการของเรา</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">เกี่ยวกับเรา</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span class="me-1 text-muted small">สวัสดี <?= e($_SESSION['user_name'] ?? '') ?></span>

                        <!-- รวมเมนูบริการส่วนตัวไว้ใน Dropdown เพื่อให้ Navbar ไม่ยาว -->
                        <div class="dropdown">
                            <button class="btn btn-outline-danger rounded-pill btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                บริการของฉัน
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="booking.php"><i class="fas fa-calendar-plus me-2"></i>จองบริการ</a></li>
                                <li><a class="dropdown-item" href="pets.php"><i class="fas fa-paw me-2"></i>สัตว์เลี้ยงของฉัน</a></li>
                                <li><a class="dropdown-item" href="my_bookings.php"><i class="fas fa-calendar-check me-2"></i>ประวัติการจองของฉัน</a></li>
                            </ul>
                        </div>

                        <?php if (($_SESSION['user_role'] ?? '') === 'Staff'): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary rounded-pill btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">เมนูพนักงาน</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="staff_bookings.php"><i class="fas fa-tasks me-2"></i>จัดการคิวงาน</a></li>
                                </ul>
                            </div>
                        <?php elseif (($_SESSION['user_role'] ?? '') === 'Admin'): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary rounded-pill btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">จัดการระบบ</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="admin_dashboard.php"><i class="fas fa-chart-line me-2"></i>Dashboard</a></li>
                                    <li><a class="dropdown-item" href="admin_bookings.php"><i class="fas fa-calendar-check me-2"></i>จัดการการจองทั้งหมด</a></li>
                                    <li><a class="dropdown-item" href="admin_users.php"><i class="fas fa-users me-2"></i>จัดการผู้ใช้</a></li>
                                    <li><a class="dropdown-item" href="admin_services.php"><i class="fas fa-concierge-bell me-2"></i>จัดการบริการ</a></li>
                                    <li><a class="dropdown-item" href="admin_staff.php"><i class="fas fa-user-shield me-2"></i>จัดการพนักงาน</a></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <a href="logout.php" class="btn btn-petcare rounded-pill btn-sm">ออกจากระบบ</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-danger rounded-pill px-3">Login</a>
                        <a href="register.php" class="btn btn-petcare rounded-pill px-3">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- 2. Hero Section -->
    <section id="index" class="py-5 bg-light">
        <div class="container py-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <h1 class="display-5 fw-bold text-dark mb-3">ดูแลสัตว์เลี้ยงตัวโปรดของคุณด้วยความใส่ใจ</h1>
                    <p class="lead text-muted mb-4">บริการอาบน้ำ ตัดขน และฝากเลี้ยงสัตว์เลี้ยงครบวงจร จองคิวง่ายๆ ได้ตลอด 24 ชั่วโมง หมดปัญหาคิวซ้ำ</p>
                    <div class="d-flex gap-3">
                        <a href="booking.php" class="btn btn-petcare rounded-pill btn-lg px-4 shadow">จองบริการเลย</a>
                        <a href="#services" class="btn btn-outline-secondary rounded-pill btn-lg px-4">ดูบริการ</a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="https://img.kapook.com/u/2015/surauch/Pet/grtudy3.jpg" alt="Pet Care" class="img-fluid rounded-4 shadow-lg">
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Services Section -->
    <section id="services" class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-petcare">บริการของเรา</h2>
                <p class="text-muted">เลือกรักษาและดูแลสัตว์เลี้ยงของคุณด้วยแพ็กเกจระดับพรีเมียม</p>
            </div>
            <div class="row g-4">
                <?php if (!empty($indexServices)): ?>
                    <?php foreach ($indexServices as $service): ?>
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm rounded-4 p-3 text-center">
                                <div class="card-body d-flex flex-column">
                                    <h4 class="fw-bold mb-3"><?= e($service['name']) ?></h4>
                                    <p class="text-muted small"><?= e($service['description'] ?? '') ?></p>
                                    <h5 class="text-petcare fw-bold my-3">เริ่มต้น ฿<?= number_format((float) $service['price'], 0) ?></h5>
                                    <a href="booking.php?service=<?= (int) $service['service_id'] ?>" class="btn btn-outline-danger rounded-pill w-100 mt-auto">เลือกบริการนี้</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center text-muted">ขณะนี้ยังไม่มีบริการ กรุณากลับมาตรวจสอบอีกครั้ง</div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- 4. Footer -->
    <footer id="about" class="bg-dark text-white pt-5 pb-4" >
        <div class="container">
            <div class="row g-4">

                <!-- คอลัมน์ที่ 1: เกี่ยวกับแบรนด์ และ โซเชียลมีเดีย -->
                <div class="col-lg-4 col-md-6">
                    <a class="navbar-brand fw-bold text-petcare d-inline-flex align-items-center fs-4 mb-3" href="index.php">
                        <svg class="bi me-2" width="32" height="32" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M4.5 10.5C3.67 10.5 3 11.17 3 12s.67 1.5 1.5 1.5S6 12.83 6 12s-.67-1.5-1.5-1.5zm3-3C6.67 7.5 6 8.17 6 9s.67 1.5 1.5 1.5S9 9.83 9 9s-.67-1.5-1.5-1.5zm9 0c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm3 3c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-1.5-1.5zM12 4.5C10.07 4.5 8.5 6.07 8.5 8c0 2.21 2.69 5.5 3.5 6.36.81-.86 3.5-4.15 3.5-6.36 0-1.93-1.57-3.5-3.5-3.5z"/>
                        </svg>
                        Pet Care
                    </a>
                    <p class="text-secondary small">
                        บริการดูแลสัตว์เลี้ยงครบวงจร อาบน้ำ ตัดขน และฝากเลี้ยง ดูแลด้วยความรักและมืออาชีพ สะดวก รวดเร็ว หมดปัญหาคิวซ้ำ
                    </p>
                    <!-- โซเชียลมีเดีย -->
                    <div class="mt-3">
                        <h6 class="fw-bold mb-2">ติดตามเราได้ที่</h6>
                        <div class="d-flex gap-2">
                            <a href="https://facebook.com" target="_blank" class="btn btn-outline-light btn-sm rounded-circle" style="width:36px; height:36px; padding:6px;">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="https://line.me" target="_blank" class="btn btn-outline-light btn-sm rounded-circle" style="width:36px; height:36px; padding:6px;">
                                <i class="fab fa-line"></i>
                            </a>
                            <a href="https://instagram.com" target="_blank" class="btn btn-outline-light btn-sm rounded-circle" style="width:36px; height:36px; padding:6px;">
                                <i class="fab fa-instagram"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- คอลัมน์ที่ 2: เมนูทางลัด -->
                <div class="col-lg-3 col-md-6 ms-auto">
                    <h5 class="fw-bold text-petcare mb-3">ลิงก์ที่เป็นประโยชน์</h5>
                    <ul class="list-unstyled text-secondary small space-y-2">
                        <li class="mb-2"><a href="index.php" class="text-secondary text-decoration-none hover-white">หน้าแรก (Home)</a></li>
                        <li class="mb-2"><a href="#services" class="text-secondary text-decoration-none hover-white">บริการของเรา (Services)</a></li>
                        <li class="mb-2"><a href="booking.php" class="text-secondary text-decoration-none hover-white">จองคิวบริการ (Booking)</a></li>
                    </ul>
                </div>

                <!-- คอลัมน์ที่ 3: ข้อมูลติดต่อ & เวลาทำการ -->
                <div class="col-lg-4 col-md-12">
                    <h5 class="fw-bold text-petcare mb-3">ข้อมูลติดต่อร้าน</h5>
                    <ul class="list-unstyled text-secondary small">
                        <li class="mb-2 d-flex align-items-start">
                            <i class="fas fa-map-marker-alt text-petcare me-2 mt-1"></i>
                            <span><strong>ที่อยู่ร้าน:</strong> 123/45 ถนนกาญจนวณิชย์ ตำบลหาดใหญ่ อำเภอหาดใหญ่ จังหวัดสงขลา 90110</span>
                        </li>
                        <li class="mb-2 d-flex align-items-center">
                            <i class="fas fa-phone-alt text-petcare me-2"></i>
                            <span><strong>เบอร์โทรศัพท์:</strong> 081-234-5678, 074-123-456</span>
                        </li>
                        <li class="mb-2 d-flex align-items-center">
                            <i class="fas fa-envelope text-petcare me-2"></i>
                            <span><strong>อีเมล:</strong> contact@petcare.com</span>
                        </li>
                        <li class="mb-2 d-flex align-items-center">
                            <i class="fas fa-clock text-petcare me-2"></i>
                            <span><strong>เวลาเปิด-ปิด:</strong> จันทร์ - อาทิตย์ (08:00 - 19:00 น.)</span>
                        </li>
                    </ul>
                </div>

            </div>

            <hr class="border-secondary my-4">

            <!-- ลิขสิทธิ์ท้ายหน้า -->
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start text-secondary small">
                    &copy; 2026 Pet Care. All Rights Reserved.
                </div>
                <div class="col-md-6 text-center text-md-end text-secondary small">
                    พัฒนาโดย ทีมงาน Pet Care Project
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>