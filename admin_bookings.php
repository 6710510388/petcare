<?php
// ===== admin_bookings.php : จัดการรายการจองทั้งหมด (เปลี่ยนสถานะ / ลบ) =====
require 'config/db.php';
need(['Admin']);                                 // เฉพาะ Admin เท่านั้น
$msg = '';

// ---------- 1) รับฟอร์ม (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $act = (string)($_POST['act'] ?? '');
        if (!in_array($act, ['status', 'delete'], true)) throw new Exception('คำขอไม่ถูกต้อง');
        if (!filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) || (int)$_POST['id'] < 1) throw new Exception('รหัสไม่ถูกต้อง');
        // เปลี่ยนสถานะ (set_status จะตรวจว่าสถานะอยู่ในรายการที่อนุญาต / ถ้าเป็น Completed จะบันทึก service_history ให้อัตโนมัติ)
        if ($act === 'status')
            set_status($conn, $_POST['id'] ?? 0, (string)($_POST['status'] ?? ''));

        if ($act === 'delete')
            q($conn, "DELETE FROM bookings WHERE booking_id=?", "i", $_POST['id']);

        header("Location: admin_bookings.php");
        exit();
    } catch (mysqli_sql_exception $ex) {
        $msg = "บันทึกไม่สำเร็จ กรุณาลองใหม่";
    }
}

// ดึงการจองทั้งหมด พร้อมชื่อลูกค้า / สัตว์เลี้ยง / บริการ
$bookings = rows($conn, "SELECT b.*, u.name uname, p.name pname, s.name sname, assigned.name staff_name
                         FROM bookings b
                         JOIN users u ON u.user_id = b.user_id
                         JOIN pets p ON p.pet_id = b.pet_id
                         JOIN services s ON s.service_id = b.service_id
                         LEFT JOIN staffs assigned ON assigned.staff_id = b.staff_id
                         ORDER BY b.booking_id DESC");

$title = "รายการจอง (Bookings)";
$sub = "ตรวจสอบ เปลี่ยนสถานะ และลบรายการจองทั้งหมดในระบบ";
include 'config/admin_header.php';
?>

                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <?php if (!$bookings): ?><p class="text-muted mb-0">ยังไม่มีรายการจอง</p><?php endif; ?>

                    <!-- 1 แถว = 1 ฟอร์ม -->
                    <?php foreach ($bookings as $b): ?>
                    <form method="POST" class="row g-2 py-2 border-bottom align-items-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $b['booking_id'] ?>">
                        <div class="col-md-1 fw-bold text-petcare">#BK-<?= sprintf('%04d', $b['booking_id']) ?></div>
                        <div class="col-md-2">
                            <div><?= e($b['uname']) ?></div>
                            <?php if (trim((string)($b['note'] ?? '')) !== ''): ?>
                                <div class="small text-muted mt-1"><strong>หมายเหตุ:</strong> <?= nl2br(e($b['note'])) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2">
                            <div class="fw-semibold"><?= e($b['pname']) ?></div>
                            <div class="small text-muted"><?= e($b['sname']) ?></div>
                        </div>
                        <div class="col-md-2">
                            <?= thai_date($b['booking_date']) ?>
                            <div class="small text-muted"><?= e($b['booking_time']) ?> น.</div>
                        </div>
                        <div class="col-md-2">
                            <div class="small text-muted">ผู้ดูแลคิว</div>
                            <div class="fw-semibold"><?= e($b['staff_name'] ?: 'ยังไม่มีผู้รับคิว') ?></div>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select form-control-custom">
                                <?php foreach (['Pending', 'Confirmed', 'Completed', 'Cancelled'] as $st): ?>
                                    <option <?= $b['status'] == $st ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex gap-1">
                            <button name="act" value="status" class="btn btn-outline-warning btn-sm rounded-pill flex-fill" title="บันทึกสถานะ"><i class="fas fa-save"></i></button>
                            <button name="act" value="delete" class="btn btn-outline-danger btn-sm rounded-pill flex-fill" title="ลบ"
                                    onclick="return confirm('ลบรายการจองนี้?');"><i class="fas fa-trash"></i></button>
                        </div>
                    </form>
                    <?php endforeach; ?>
                </div>

<?php include 'config/admin_footer.php'; ?>
