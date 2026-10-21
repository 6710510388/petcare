<?php
// ===== booking.php : ฟอร์มจองคิว + บันทึกการจอง (Single File) =====
require 'config/db.php';
need();                                          // ต้อง Login ก่อน
$user_id = $_SESSION['user_id'];

$current_date = date('Y-m-d');                   // วันที่ปัจจุบัน
$current_time = date('H:i');                     // เวลาปัจจุบัน
$time_slots = ['09:00', '10:30', '13:00', '14:30', '16:00'];   // รอบเวลาที่เปิดให้บริการ
$slot_capacity = 3;                              // โควตาสูงสุด 3 คิวต่อรอบ
$msg = '';                                       // ข้อความ error (ถ้ามี)

// วันที่ที่เลือก (ค่าเริ่มต้นเป็นวันนี้)
$selected_date = $_POST['booking_date'] ?? $_GET['booking_date'] ?? $current_date;

// เก็บค่าสัตว์เลี้ยงและบริการที่เลือกไว้ ทั้งตอนเปลี่ยนวันที่และตอนตรวจสอบฟอร์ม
$selected_pet_id = (string)($_POST['pet_id'] ?? $_GET['pet_id'] ?? '');
$selected_service_id = (string)($_POST['service_id'] ?? $_GET['service_id'] ?? '');

// ---------- 1) รับฟอร์มจอง (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $time = (string)($_POST['booking_time'] ?? '');
    $pet_id = filter_var($_POST['pet_id'] ?? null, FILTER_VALIDATE_INT);
    $service_id = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT);
    $note = trim((string)($_POST['note'] ?? ''));
    $selected_date = (string)($_POST['booking_date'] ?? '');
    $date_obj = DateTime::createFromFormat('!Y-m-d', $selected_date);
    $valid_date = $date_obj && $date_obj->format('Y-m-d') === $selected_date;
    $my_pet = ($pet_id && $service_id) ? rows($conn, "SELECT pet_id FROM pets WHERE pet_id=? AND owner_id=?", "ii", $pet_id, $user_id) : [];
    $service_exists = $service_id ? rows($conn, "SELECT service_id FROM services WHERE service_id=?", "i", $service_id) : [];
    $is_past = !$valid_date || $selected_date < $current_date || ($selected_date === $current_date && $time <= $current_time);
    if (!$my_pet || !$service_exists || !in_array($time, $time_slots, true) || $is_past || mb_strlen($note) > 1000) {
        $msg = "ข้อมูลการจองไม่ถูกต้อง";
    } else {
        // ใช้ MySQL named lock แย่งล็อกตามวันและรอบ เพื่อป้องกันคำขอพร้อมกันจองเกิน 3 คิว
        $lock_name = 'petcare_slot_' . substr(hash('sha256', $selected_date . '|' . $time), 0, 48);
        $lock = rows($conn, "SELECT GET_LOCK(?, 5) acquired", "s", $lock_name)[0]['acquired'] ?? 0;
        if ((int)$lock !== 1) {
            $msg = "ระบบกำลังรับจองรอบนี้ กรุณาลองใหม่อีกครั้ง";
        } else {
            try {
                $used = (int)(rows($conn, "SELECT COUNT(*) c FROM bookings WHERE booking_date=? AND booking_time=? AND status IN ('Pending','Confirmed')", "ss", $selected_date, $time)[0]['c'] ?? 0);
                if ($used >= $slot_capacity) {
                    $msg = "รอบเวลานี้คิวเต็มแล้ว";
                } else {
                    q($conn, "DELETE FROM bookings WHERE pet_id=? AND booking_date=? AND booking_time=? AND status='Cancelled'", "iss", $pet_id, $selected_date, $time);
                    q($conn, "INSERT INTO bookings (user_id, pet_id, service_id, booking_date, booking_time, note) VALUES (?,?,?,?,?,?)", "iiisss", $user_id, $pet_id, $service_id, $selected_date, $time, $note);
                    header("Location: my_bookings.php"); exit();
                }
            } catch (mysqli_sql_exception $ex) {
                error_log('Booking insert failed: ' . $ex->getMessage());
                $msg = "สัตว์เลี้ยงตัวนี้จองรอบเวลานี้ไปแล้ว หรือข้อมูลไม่ถูกต้อง";
            } finally {
                q($conn, "SELECT RELEASE_LOCK(?)", "s", $lock_name);
            }
        }
    }
}

// ---------- 2) ดึงข้อมูลมาแสดงในฟอร์ม ----------
$pets = rows($conn, "SELECT pet_id, name, type, breed FROM pets WHERE owner_id=?", "i", $user_id);
$services = rows($conn, "SELECT service_id, name, price FROM services");
// จำนวนคิวที่จองแล้วในแต่ละรอบของวันที่เลือก  ผลลัพธ์เช่น ['09:00' => 3, '10:30' => 1]
$booking_counts = array_column(
    rows($conn, "SELECT booking_time, COUNT(*) c FROM bookings WHERE booking_date=? AND status IN ('Pending','Confirmed') GROUP BY booking_time",
         "s", $selected_date), 'c', 'booking_time');
// บริการที่เลือกไว้ล่วงหน้า (มาจากปุ่ม "เลือกบริการนี้" / "จองซ้ำอีกครั้ง")
$pre_service = $_POST['service_id'] ?? $_GET['service'] ?? $_GET['service_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care - จองคิวบริการ</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts (Prompt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">
    
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
                <a href="my_bookings.php" class="btn btn-outline-danger rounded-pill btn-sm">รายการจองของฉัน</a>
                <a href="index.php" class="btn btn-outline-secondary rounded-pill btn-sm">หน้าหลัก</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <div class="text-center mb-4">
                    <h2 class="fw-bold text-petcare">จองบริการดูแลสัตว์เลี้ยง</h2>
                    <p class="text-muted small">เลือกรอบเวลาที่ต้องการ (จำกัดไม่เกิน 3 คิวต่อรอบ เพื่อบริการที่ดีที่สุด)</p>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">

                    <!-- ข้อความแจ้งเตือนเมื่อจองไม่สำเร็จ -->
                    <?php if ($msg): ?>
                        <div class="alert alert-danger rounded-4 small"><?= e($msg) ?></div>
                    <?php endif; ?>

                    <!-- action="" = ส่งข้อมูลกลับมาที่ไฟล์นี้ (ส่วน PHP ด้านบนรับต่อ) -->
                    <form action="" method="POST" id="bookingForm"><?= csrf_field() ?>
                        
                        <!-- 1. เลือกสัตว์เลี้ยง (ดึงจากตาราง pets) -->
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fas fa-dog text-petcare me-2"></i>เลือกสัตว์เลี้ยงของคุณ</label>
                            <div class="input-group">
                                <select name="pet_id" class="form-select form-control-custom" required>
                                    <option value="" selected disabled>-- เลือกสัตว์เลี้ยง --</option>
                                    <?php foreach ($pets as $p): ?>
                                        <option value="<?= $p['pet_id'] ?>" <?= $selected_pet_id == (string)$p['pet_id'] ? 'selected' : '' ?>>
                                            <?= e($p['name']) ?> (<?= e($p['type']) ?> - <?= e($p['breed']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="pets.php" class="btn btn-outline-danger rounded-pill ms-2">+ เพิ่มสัตว์เลี้ยง</a>
                            </div>
                        </div>

                        <!-- 2. เลือกบริการ (ดึงจากตาราง services) -->
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fas fa-concierge-bell text-petcare me-2"></i>เลือกบริการ</label>
                            <select name="service_id" class="form-select form-control-custom" required>
                                <option value="" selected disabled>-- เลือกบริการ --</option>
                                <?php foreach ($services as $s): ?>
                                    <option value="<?= $s['service_id'] ?>" <?= $pre_service == $s['service_id'] ? 'selected' : '' ?>>
                                        <?= e($s['name']) ?> (เริ่มต้น ฿<?= number_format($s['price']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 3. วันที่รับบริการ -->
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fas fa-calendar-alt text-petcare me-2"></i>วันที่รับบริการ</label>
                            <input 
                                type="date" 
                                name="booking_date" 
                                id="booking_date"
                                class="form-control form-control-custom" 
                                value="<?= e($selected_date) ?>"
                                min="<?= $current_date ?>" 
                                onchange="reloadTimeSlots()"
                                required
                            >
                            <div class="form-text text-muted small ms-2">* ระบบอัปเดตสถิติคิวว่างและรอบเวลาที่เปิดให้บริการให้อัตโนมัติ</div>
                        </div>

                        <!-- 4. เลือกรอบเวลา (ตรวจทั้ง คิวเต็ม และ เวลาผ่านไปแล้ว) -->
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fas fa-clock text-petcare me-2"></i>เลือกรอบเวลาที่ต้องการ</label>
                            <div class="row g-2">
                                <?php foreach ($time_slots as $time): 
                                    $booked = $booking_counts[$time] ?? 0;          // จำนวนที่จองแล้ว
                                    $available = $slot_capacity - $booked;          // คิวที่เหลือ
                                    $is_full = ($available <= 0);                   // เช็คที่ 1: คิวเต็มหรือยัง
                                    $is_past = ($selected_date === $current_date && $time <= $current_time);   // เช็คที่ 2: เวลาผ่านไปแล้วหรือยัง
                                    $is_disabled = ($is_full || $is_past);          // ปิดปุ่มถ้าเข้าเงื่อนไขใดเงื่อนไขหนึ่ง
                                ?>
                                    <div class="col-6 col-md-4">
                                        <input 
                                            type="radio" 
                                            class="btn-check" 
                                            name="booking_time" 
                                            id="time_<?= str_replace(':', '', $time) ?>" 
                                            value="<?= $time ?>" 
                                            autocomplete="off"
                                            <?= $is_disabled ? 'disabled' : '' ?>
                                            required
                                        >
                                        <label class="btn btn-outline-danger rounded-4 w-100 py-2 d-flex flex-column align-items-center <?= $is_disabled ? 'disabled border-secondary bg-light text-muted' : '' ?>" for="time_<?= str_replace(':', '', $time) ?>">
                                            <span class="fw-bold fs-6"><?= $time ?> น.</span>
                                            <small class="mt-1 opacity-75">
                                                <?php if ($is_past): ?>
                                                    <span class="badge bg-secondary">เลยเวลาแล้ว</span>
                                                <?php elseif ($is_full): ?>
                                                    <span class="badge bg-secondary">เต็มแล้ว (3/3)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success">ว่างอีก <?= $available ?> คิว</span>
                                                <?php endif; ?>
                                            </small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 5. หมายเหตุเพิ่มเติม -->
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fas fa-sticky-note text-petcare me-2"></i>หมายเหตุเพิ่มเติม (ถ้ามี)</label>
                            <textarea name="note" class="form-control rounded-4" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติม เช่น น้องหมากลัวเสียงดัง"><?= e($_POST['note'] ?? '') ?></textarea>
                        </div>

                        <!-- 6. สรุปเงื่อนไขการล็อกคิว -->
                        <div class="p-3 bg-light rounded-4 mb-4 border">
                            <div class="d-flex align-items-center gap-2 text-muted small mb-1">
                                <i class="fas fa-info-circle text-petcare"></i>
                                <span>เมื่อกดจอง ระบบจะทำการ<strong>ล็อกคิวทันที</strong>และนับเป็น 1 คิวของรอบเวลานั้น</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                <span class="fw-bold small">สถานะเริ่มต้น:</span>
                                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Pending (รอยืนยันจากพนักงาน)</span>
                            </div>
                        </div>

                        <!-- ปุ่ม Submit -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-petcare rounded-pill py-3 fw-bold shadow-sm">
                                ยืนยันการจองบริการ (ล็อกคิวทันที)
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- JavaScript เปลี่ยนวันที่แล้วโหลดรอบเวลาใหม่ -->
    <script>
        // เมื่อเปลี่ยนวันที่ ให้โหลดจำนวนคิวของวันใหม่โดยเก็บสัตว์เลี้ยงและบริการเดิมไว้
        function reloadTimeSlots() {
            const dateInput = document.getElementById('booking_date');
            const petSelect = document.querySelector('[name="pet_id"]');
            const serviceSelect = document.querySelector('[name="service_id"]');

            if (!dateInput.value) {
                alert('กรุณาเลือกวันที่รับบริการ');
                return;
            }

            const params = new URLSearchParams();
            params.set('booking_date', dateInput.value);

            // ส่งค่าที่เลือกไว้กลับไปใน URL เพื่อให้ PHP เลือกค่าเดิมหลังโหลดหน้าใหม่
            if (petSelect && petSelect.value) {
                params.set('pet_id', petSelect.value);
            }
            if (serviceSelect && serviceSelect.value) {
                params.set('service_id', serviceSelect.value);
            }

            window.location.href = 'booking.php?' + params.toString();
        }

        // แจ้งเตือนให้ครบถ้วนก่อนส่งคำขอจอง
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('bookingForm');
            if (!form) return;

            form.addEventListener('submit', function (event) {
                const pet = form.querySelector('[name="pet_id"]');
                const service = form.querySelector('[name="service_id"]');
                const date = form.querySelector('[name="booking_date"]');
                const times = form.querySelectorAll('[name="booking_time"]');
                const selectedTime = form.querySelector('[name="booking_time"]:checked');

                if (!pet.value) {
                    event.preventDefault();
                    alert('กรุณาเลือกสัตว์เลี้ยงก่อนจอง');
                    pet.focus();
                    return;
                }

                if (!service.value) {
                    event.preventDefault();
                    alert('กรุณาเลือกบริการก่อนจอง');
                    service.focus();
                    return;
                }

                if (!date.value) {
                    event.preventDefault();
                    alert('กรุณาเลือกวันที่รับบริการ');
                    date.focus();
                    return;
                }

                if (!selectedTime) {
                    event.preventDefault();
                    alert(times.length ? 'กรุณาเลือกรอบเวลาที่ต้องการ หากทุกรอบเต็มแล้ว กรุณาเลือกวันอื่น' : 'ไม่มีรอบเวลาให้เลือก');
                }
            });
        });
    </script>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
