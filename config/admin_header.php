<?php
// ============================================================
//  config/admin_header.php : ส่วนหัวที่ใช้ร่วมกันทุกหน้า Admin (head + Sidebar + หัวข้อหน้า)
//  หน้าที่เรียกใช้ต้องกำหนด $title และ $sub ไว้ก่อน include  (และ $msg ถ้าต้องการแสดง error)
// ============================================================

// เมนู Sidebar : ไฟล์ => [ไอคอน, ข้อความ]
$menu = [
    'index.php'            => ['fa-house', 'หน้าหลัก'],
    'admin_dashboard.php' => ['fa-chart-line', 'Dashboard'],
    'admin_users.php'     => ['fa-users', 'จัดการผู้ใช้ (Users)'],
    'admin_pets.php'      => ['fa-dog', 'จัดการสัตว์เลี้ยง (Pets)'],
    'admin_services.php'  => ['fa-concierge-bell', 'จัดการบริการ (Services)'],
    'admin_staff.php'     => ['fa-user-shield', 'จัดการพนักงาน (Staff)'],
    'admin_bookings.php'  => ['fa-calendar-check', 'รายการจอง (Bookings)'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care - Admin</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts (Prompt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- ไฟล์ CSS หลัก -->
    <link rel="stylesheet" href="css/style.css">

    <style>
        /* สไตล์พิเศษสำหรับแถบ Sidebar ของ Admin */
        .sidebar {
            min-height: 100vh;
            background-color: #1a1d20;
        }
        .sidebar .nav-link {
            color: #a0a0a0;
            padding: 0.8rem 1.2rem;
            border-radius: 0.5rem;
            margin-bottom: 0.3rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #ffffff;
            background-color: #E31B23;
        }
    </style>
</head>
<body class="bg-light">

    <div class="container-fluid p-0">
        <div class="row g-0">

            <!-- ==================== Sidebar ด้านซ้าย ==================== -->
            <div class="col-lg-2 col-md-3 sidebar p-3 text-white">
                <a href="index.php" class="d-flex align-items-center mb-4 text-white text-decoration-none fw-bold fs-5 px-2">
                    <svg class="bi me-2 text-petcare" width="32" height="32" fill="#E31B23" viewBox="0 0 24 24">
                        <path d="M4.5 10.5C3.67 10.5 3 11.17 3 12s.67 1.5 1.5 1.5S6 12.83 6 12s-.67-1.5-1.5-1.5zm3-3C6.67 7.5 6 8.17 6 9s.67 1.5 1.5 1.5S9 9.83 9 9s-.67-1.5-1.5-1.5zm9 0c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm3 3c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-1.5-1.5zM12 4.5C10.07 4.5 8.5 6.07 8.5 8c0 2.21 2.69 5.5 3.5 6.36.81-.86 3.5-4.15 3.5-6.36 0-1.93-1.57-3.5-3.5-3.5z"/>
                    </svg>
                    <span>Pet Care Admin</span>
                </a>
                
                <hr class="border-secondary mb-4">

                <!-- เมนูควบคุมหลัก : วนลูปจาก $menu และไฮไลต์หน้าปัจจุบัน -->
                <ul class="nav nav-pills flex-column mb-auto">
                    <?php foreach ($menu as $file => $m): ?>
                    <li>
                        <a href="<?= $file ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) == $file ? 'active' : '' ?>">
                            <i class="fas <?= $m[0] ?> me-2"></i> <?= $m[1] ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <hr class="border-secondary my-4">

                <a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i> ออกจากระบบ
                </a>
            </div>

            <!-- ==================== ส่วนเนื้อหาหลัก (Main Content) : ปิดใน admin_footer.php ==================== -->
            <div class="col-lg-10 col-md-9 p-4 overflow-y-auto" style="height: 100vh;">
                
                <!-- หัวข้อหน้า -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold mb-1"><?= $title ?></h3>
                        <p class="text-muted small mb-0"><?= $sub ?></p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger px-3 py-2 rounded-pill"><i class="fas fa-user-tag me-1"></i> Role: Administrator</span>
                    </div>
                </div>

                <!-- ข้อความ error (ถ้ามี) -->
                <?php if (!empty($msg)): ?>
                    <div class="alert alert-danger py-2 small"><?= e($msg) ?></div>
                <?php endif; ?>
