<?php
// ===== my_bookings.php : รายการจองของลูกค้า + ยกเลิกการจอง (Single File) =====
require 'config/db.php';
need();                                          // ต้อง Login ก่อน
$uid = $_SESSION['user_id'];

// ---------- 1) ยกเลิกการจอง (ทำได้เฉพาะรายการ Pending ของตัวเอง) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    q($conn, "UPDATE bookings SET status='Cancelled' WHERE booking_id=? AND user_id=? AND status='Pending'", "ii", $_POST['id'], $uid);
    header("Location: my_bookings.php");
    exit();
}

// ---------- 2) ดึงรายการจองของเรา (JOIN สัตว์เลี้ยง + บริการ + ประวัติ) ----------
// COALESCE: ถ้าเสร็จสิ้นแล้วใช้ราคาจากประวัติ ถ้ายังไม่เสร็จใช้ราคาปัจจุบันของบริการ
$bookings = rows($conn, "SELECT b.*, p.name pet_name, p.type, s.name service_name, COALESCE(h.total_price, s.price) price
                         FROM bookings b
                         JOIN pets p ON p.pet_id = b.pet_id
                         JOIN services s ON s.service_id = b.service_id
                         LEFT JOIN service_history h ON h.booking_id = b.booking_id
                         WHERE b.user_id = ?
                         ORDER BY b.booking_date DESC, b.booking_time DESC", "i", $uid);

// แท็บ => สถานะที่ใช้กรอง ('' = ทั้งหมด)
$tabs = ['all' => '', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care - ประวัติการจองบริการ</title>
    
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
</head>
<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-petcare d-flex align-items-center" href="index.php">
                <svg class="bi me-2" width="32" height="32" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M4.5 10.5C3.67 10.5 3 11.17 3 12s.67 1.5 1.5 1.5S6 12.83 6 12s-.67-1.5-1.5-1.5zm3-3C6.67 7.5 6 8.17 6 9s.67 1.5 1.5 1.5S9 9.83 9 9s-.67-1.5-1.5-1.5zm9 0c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm3 3c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-1.5-1.5zM12 4.5C10.07 4.5 8.5 6.07 8.5 8c0 2.21 2.69 5.5 3.5 6.36.81-.86 3.5-4.15 3.5-6.36 0-1.93-1.57-3.5-3.5-3.5z"/>
                </svg>
                Pet Care
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="booking.php" class="btn btn-petcare rounded-pill btn-sm">+ จองบริการใหม่</a>
                <a href="index.php" class="btn btn-outline-secondary rounded-pill btn-sm">หน้าหลัก</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        
        <!-- หัวข้อหน้า -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-petcare mb-1">รายการจองของฉัน (My Bookings)</h2>
                <p class="text-muted small mb-0">ติดตามสถานะคิวบริการและดูประวัติการใช้บริการย้อนหลัง</p>
            </div>
        </div>

        <!-- แท็บสลับประเภทรายการ (Tabs) -->
        <ul class="nav nav-pills mb-4 bg-white p-2 rounded-pill shadow-sm d-inline-flex" id="bookingTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill px-4 text-dark fw-semibold" id="all-tab" data-bs-toggle="pill" data-bs-target="#all" type="button">ทั้งหมด</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4 text-dark fw-semibold" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pending" type="button">รอยืนยัน (Pending)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4 text-dark fw-semibold" id="confirmed-tab" data-bs-toggle="pill" data-bs-target="#confirmed" type="button">ยืนยันแล้ว (Confirmed)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4 text-dark fw-semibold" id="completed-tab" data-bs-toggle="pill" data-bs-target="#completed" type="button">เสร็จสิ้น (Completed)</button>
            </li>
        </ul>

        <?php if (!$bookings): ?>
            <p class="text-muted">ยังไม่มีรายการจอง <a href="booking.php" class="text-petcare">จองบริการเลย</a></p>
        <?php endif; ?>

        <!-- เนื้อหาในแท็บ : วนทีละแท็บ แล้ววนการ์ดที่ตรงกับสถานะของแท็บนั้น -->
        <div class="tab-content" id="bookingTabContent">
            <?php foreach ($tabs as $id => $tab_status): ?>
            <div class="tab-pane fade <?= $id == 'all' ? 'show active' : '' ?>" id="<?= $id ?>" role="tabpanel">
                <div class="row g-3">

                    <?php foreach ($bookings as $b):
                        if ($tab_status && $b['status'] != $tab_status) continue;               // ข้ามรายการที่ไม่ตรงแท็บ
                        $done = in_array($b['status'], ['Completed', 'Cancelled']);             // รายการที่จบแล้ว (จางลง)
                        // เลือกไอคอนตามสถานะ / ชนิดสัตว์
                        $icon = $b['status'] == 'Completed' ? 'fa-check-circle'
                              : ($b['status'] == 'Cancelled' ? 'fa-times-circle'
                              : ($b['type'] == 'แมว' ? 'fa-cat' : 'fa-dog'));
                    ?>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-4 p-3 <?= $done ? 'bg-white opacity-75' : '' ?>">
                            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="<?= $done ? 'bg-secondary bg-opacity-10 text-secondary' : 'bg-danger bg-opacity-10 text-petcare' ?> rounded-circle p-3">
                                        <i class="fas <?= $icon ?> fa-2x"></i>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <h5 class="fw-bold mb-0"><?= e($b['pet_name']) ?> (<?= e($b['type']) ?>)</h5>
                                            <span class="badge <?= badge($b['status']) ?> rounded-pill px-3"><?= $b['status'] ?></span>
                                        </div>
                                        <p class="text-muted small mb-1"><strong>บริการ:</strong> <?= e($b['service_name']) ?></p>
                                        <p class="text-muted small mb-0"><i class="far fa-calendar-alt me-1"></i> <?= thai_date($b['booking_date']) ?> | <i class="far fa-clock me-1"></i> <?= e($b['booking_time']) ?> น.</p>
                                    </div>
                                </div>
                                <div class="text-md-end border-top border-md-0 pt-2 pt-md-0">
                                    <h5 class="fw-bold <?= $done ? 'text-muted' : 'text-petcare' ?> mb-2">฿<?= number_format($b['price']) ?></h5>

                                    <?php if ($b['status'] == 'Pending'): ?>
                                        <!-- Pending : ลูกค้ายกเลิกเองได้ -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการยกเลิกรายการจองนี้?');">
                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $b['booking_id'] ?>">
                                            <button class="btn btn-outline-danger btn-sm rounded-pill">ยกเลิกการจอง</button>
                                        </form>
                                    <?php elseif ($b['status'] == 'Confirmed'): ?>
                                        <!-- Confirmed : ต้องโทรหาร้าน -->
                                        <button class="btn btn-outline-danger btn-sm rounded-pill" onclick="alert('กรุณาติดต่อร้านทางโทรศัพท์เพื่อขอยกเลิก');">ยกเลิกการจอง</button>
                                    <?php elseif ($b['status'] == 'Completed'): ?>
                                        <!-- Completed : จองซ้ำ (เลือกบริการเดิมไว้ให้) -->
                                        <a href="booking.php?service=<?= $b['service_id'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill">จองซ้ำอีกครั้ง</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
