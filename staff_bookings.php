<?php
// ===== staff_bookings.php : พนักงานดูรายการจอง + อัปเดตสถานะ (Single File) =====
require 'config/db.php';
need(['Staff']);                                  // เฉพาะ Staff เท่านั้น

// ---------- 1) รับคิว / อัปเดตสถานะ ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = rows($conn, "SELECT staff_id FROM staffs WHERE user_id=? AND status='Active'", "i", $_SESSION['user_id'])[0]['staff_id'] ?? null;
    if (!$staff_id) {
        http_response_code(403);
        exit('ไม่พบข้อมูลพนักงานที่เปิดใช้งาน');
    }

    $booking_id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $new_status = (string)($_POST['status'] ?? '');
    if (!$booking_id || !in_array($new_status, ['Confirmed', 'Cancelled', 'Completed'], true)) {
        header("Location: staff_bookings.php?error=invalid");
        exit();
    }

    if ($new_status === 'Confirmed') {
        // Atomic claim: รับคิวได้เฉพาะเมื่อยัง Pending และยังไม่มีพนักงานรับ
        // หากพนักงานอีกคนรับไปก่อนแล้ว affected_rows จะเป็น 0
        $st = q($conn, "UPDATE bookings SET status='Confirmed', staff_id=?
                        WHERE booking_id=? AND status='Pending' AND staff_id IS NULL AND user_id <> ?",
                  "iii", $staff_id, $booking_id, (int)$_SESSION['user_id']);
        $claimed = mysqli_stmt_affected_rows($st) === 1;
        mysqli_stmt_close($st);
        if (!$claimed) {
            header("Location: staff_bookings.php?error=taken");
            exit();
        }
    } elseif ($new_status === 'Cancelled') {
        // ยกเลิกได้เฉพาะคิวที่ยังไม่มีผู้รับ หรือเป็นคิวของพนักงานคนนี้
        q($conn, "UPDATE bookings SET status='Cancelled', staff_id=COALESCE(staff_id, ?)
                  WHERE booking_id=? AND status='Pending' AND (staff_id IS NULL OR staff_id=?) AND user_id <> ?",
           "iiii", $staff_id, $booking_id, $staff_id, (int)$_SESSION['user_id']);
    } elseif ($new_status === 'Completed') {
        // ทำงานต่อได้เฉพาะคิวที่รับโดยพนักงานคนนี้เท่านั้น
        $ok = rows($conn, "SELECT booking_id FROM bookings
                           WHERE booking_id=? AND status='Confirmed' AND staff_id=? AND user_id <> ?",
                    "iii", $booking_id, $staff_id, (int)$_SESSION['user_id']);
        if ($ok) set_status($conn, $booking_id, 'Completed', $staff_id, true);
    }

    $query = $_SERVER['QUERY_STRING'] ?? '';
    header("Location: staff_bookings.php" . ($query !== '' ? '?' . $query : ''));
    exit();
}

// ---------- 2) ตัวกรอง (วันที่ / สถานะ) ----------
$date   = $_GET['date_filter'] ?? '';
$status = (string)($_GET['status_filter'] ?? '');
if (!in_array($status, ['', 'Pending', 'Confirmed', 'Completed', 'Cancelled'], true)) $status = '';
$current_staff_id = rows($conn, "SELECT staff_id FROM staffs WHERE user_id=? AND status='Active'", "i", $_SESSION['user_id'])[0]['staff_id'] ?? null;

// (?='' OR col=?) = ถ้าไม่ได้เลือกตัวกรองให้ผ่านทุกแถว  จึงไม่ต้องต่อ SQL เอง
$list = rows($conn, "SELECT b.*, u.name uname, u.phone uphone, p.name pname, p.type, p.breed, p.weight, s.name sname, s.price, assigned.name staff_name
                     FROM bookings b
                     JOIN users u ON u.user_id = b.user_id
                     JOIN pets p ON p.pet_id = b.pet_id
                     JOIN services s ON s.service_id = b.service_id
                     LEFT JOIN staffs assigned ON assigned.staff_id = b.staff_id
                     WHERE (? = '' OR b.booking_date = ?) AND (? = '' OR b.status = ?)
                     ORDER BY b.booking_date DESC, b.booking_time", "ssss", $date, $date, $status, $status);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care Staff - รายการจองคิวบริการ</title>
    
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

    <!-- Navbar ฝั่ง พนักงาน (Staff Navbar) -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold text-petcare d-flex align-items-center" href="index.php">
                <svg class="bi me-2" width="32" height="32" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M4.5 10.5C3.67 10.5 3 11.17 3 12s.67 1.5 1.5 1.5S6 12.83 6 12s-.67-1.5-1.5-1.5zm3-3C6.67 7.5 6 8.17 6 9s.67 1.5 1.5 1.5S9 9.83 9 9s-.67-1.5-1.5-1.5zm9 0c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm3 3c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-1.5-1.5zM12 4.5C10.07 4.5 8.5 6.07 8.5 8c0 2.21 2.69 5.5 3.5 6.36.81-.86 3.5-4.15 3.5-6.36 0-1.93-1.57-3.5-3.5-3.5z"/>
                </svg>
                Pet Care <span class="badge bg-danger ms-2 font-monospace fs-6">Staff Panel</span>
            </a>
            <div class="d-flex align-items-center gap-2 gap-md-3">
                <span class="text-white small d-none d-md-inline"><i class="fas fa-user-circle me-1"></i> พนักงาน: <?= e($_SESSION['user_name']) ?></span>
                <a href="index.php" class="btn btn-outline-light rounded-pill btn-sm"><i class="fas fa-house me-1"></i>หน้าหลัก</a>
                <a href="logout.php" class="btn btn-outline-light rounded-pill btn-sm">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid px-4 py-4">
        
        <!-- หัวข้อหน้า และ ตัวกรองวันที่/สถานะ -->
        <div class="row align-items-center mb-4 g-3">
            <div class="col-md-6">
                <h3 class="fw-bold text-dark mb-1"><i class="fas fa-clipboard-list text-petcare me-2"></i>จัดการคิวและการให้บริการ</h3>
                <p class="text-muted small mb-0">ตรวจสอบข้อมูลสัตว์เลี้ยงและอัปเดตสถานะการรับบริการของลูกค้า</p>
            </div>
            
            <!-- ตัวกรองค้นหา (Filter) : ส่งแบบ GET กลับมาที่หน้านี้ (ล้างวันที่ = ดูทุกวัน) -->
            <div class="col-md-6">
                <form class="row g-2 justify-content-md-end" method="GET">
                    <div class="col-auto">
                        <input type="date" name="date_filter" class="form-control form-control-custom" value="<?= e($date) ?>" onchange="this.form.requestSubmit()" aria-label="กรองตามวันที่">
                    </div>
                    <div class="col-auto">
                        <select name="status_filter" class="form-select form-control-custom" onchange="this.form.requestSubmit()" aria-label="กรองตามสถานะ">
                            <option value="">-- สถานะทั้งหมด --</option>
                            <?php foreach (['Pending' => 'รอยืนยัน', 'Confirmed' => 'ยืนยันแล้ว', 'Completed' => 'เสร็จสิ้น', 'Cancelled' => 'ยกเลิก'] as $k => $label): ?>
                                <option value="<?= $k ?>" <?= $status == $k ? 'selected' : '' ?>><?= $k ?> (<?= $label ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-petcare rounded-pill px-3"><i class="fas fa-filter me-1"></i> กรอง</button>
                    </div>
                    <div class="col-auto">
                        <a href="staff_bookings.php" class="btn btn-outline-secondary rounded-pill px-3" title="ล้างตัวกรองและแสดงรายการจองทั้งหมด">
                            <i class="fas fa-rotate-left me-1"></i> รีเซ็ต
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- ตารางรายการคิวจอง (Bookings Table) -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3">รหัสจอง</th>
                                <th class="py-3">วัน-เวลา</th>
                                <th class="py-3">ลูกค้า / เบอร์โทร</th>
                                <th class="py-3">สัตว์เลี้ยง</th>
                                <th class="py-3">บริการ</th>
                                <th class="py-3">สถานะ</th>
                                <th class="py-3 text-end pe-4">อัปเดตสถานะ (Actions)</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php if (!$list): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">ไม่พบรายการจอง</td></tr>
                            <?php endif; ?>

                            <!-- วนลูปแสดงทุกคิว : คิวที่จบแล้ว (Completed/Cancelled) จะจางลงและไม่มีปุ่ม -->
                            <?php foreach ($list as $b): $done = in_array($b['status'], ['Completed', 'Cancelled']); ?>
                            <tr class="<?= $done ? 'bg-light opacity-75' : '' ?>">
                                <td class="ps-4 fw-bold <?= $done ? 'text-muted' : 'text-petcare' ?>">#BK-<?= sprintf('%04d', $b['booking_id']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= thai_date($b['booking_date']) ?></div>
                                    <div class="small text-muted"><i class="far fa-clock me-1"></i><?= e($b['booking_time']) ?> น.</div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($b['uname']) ?></div>
                                    <div class="small text-muted"><i class="fas fa-phone me-1"></i><?= e($b['uphone']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><i class="fas <?= $b['type'] == 'แมว' ? 'fa-cat' : 'fa-dog' ?> text-petcare me-1"></i><?= e($b['pname']) ?></div>
                                    <div class="small text-muted"><?= e($b['breed']) ?> (<?= (float)$b['weight'] ?> กก.)</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($b['sname']) ?></span>
                                    <div class="small text-petcare fw-bold">฿<?= number_format($b['price']) ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= badge($b['status']) ?> rounded-pill px-3 py-2"><?= e($b['status']) ?></span>
                                    <div class="small text-muted mt-1">ผู้ดูแล: <?= e($b['staff_name'] ?: 'ยังไม่มีผู้รับคิว') ?></div>
                                </td>
                                <td class="text-end pe-4">
                                    <!-- รายการจองของพนักงานเองแสดงให้ดูได้ แต่ห้ามรับคิว/จัดการคิวตัวเอง -->
                                    <?php if ((int)$b['user_id'] === (int)$_SESSION['user_id']): ?>
                                        <span class="text-muted small"><i class="fas fa-user-lock me-1"></i>รายการจองของคุณ (รับคิวเองไม่ได้)</span>
                                    <?php elseif ($b['status'] == 'Pending' && empty($b['staff_id'])): ?>
                                        <form method="POST" class="btn-group gap-1">
                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $b['booking_id'] ?>">
                                            <button name="status" value="Confirmed" class="btn btn-sm btn-success rounded-pill px-3" onclick="return confirm('ยืนยันรับคิวบริการนี้?');">
                                                <i class="fas fa-check me-1"></i> รับคิว
                                            </button>
                                            <button name="status" value="Cancelled" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('ปฏิเสธรายการจองนี้?');">
                                                ยกเลิก
                                            </button>
                                        </form>
                                    <?php elseif ($b['status'] == 'Confirmed' && (int)$b['staff_id'] === (int)$current_staff_id): ?>
                                        <form method="POST">
                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $b['booking_id'] ?>">
                                            <button name="status" value="Completed" class="btn btn-sm btn-primary rounded-pill px-3" onclick="return confirm('บริการเสร็จสิ้นแล้วหรือไม่?');">
                                                <i class="fas fa-check-double me-1"></i> บริการเสร็จสิ้น
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small"><i class="fas fa-lock me-1"></i><?= $b['status'] == 'Completed' ? 'เสร็จสิ้นแล้ว' : ($b['status'] == 'Cancelled' ? 'ยกเลิกแล้ว' : ($b['status'] == 'Confirmed' ? 'พนักงานคนอื่นรับคิวแล้ว' : 'รอการรับคิว')) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
