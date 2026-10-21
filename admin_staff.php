<?php
// ===== admin_staff.php : จัดการพนักงาน (เพิ่ม / แก้ไข / ลบ) =====
require 'config/db.php';
need(['Admin']);                                 // เฉพาะ Admin เท่านั้น
$msg = '';
$statuses = ['Active', 'On Leave', 'Inactive'];   // สถานะที่อนุญาต (ใช้ทั้งตรวจค่าฝั่งเซิร์ฟเวอร์และแสดงในฟอร์ม)

// ---------- 1) รับฟอร์ม (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    $uid = ($_POST['user_id'] ?? '') ?: null;    // บัญชีผู้ใช้ที่ผูกกับพนักงาน (ว่าง = ไม่ผูก)
    try {
        if (!in_array($act, ['add', 'edit', 'delete'], true))
            throw new Exception("คำขอไม่ถูกต้อง");
        if ($act !== 'delete') {
            // ตรวจรูปแบบชื่อ / เบอร์โทร ก่อนบันทึก (ฟังก์ชัน valid_* อยู่ใน config/db.php)
            if (!(valid_name($_POST['name'] ?? '') && valid_phone($_POST['phone'] ?? '')))
                throw new Exception("รูปแบบไม่ถูกต้อง (ชื่อต้องไม่มีตัวเลข / เบอร์ 10 หลักขึ้นต้น 0)");
            // สถานะต้องอยู่ในรายการที่อนุญาตเท่านั้น
            if (!in_array($_POST['status'] ?? '', $statuses, true))
                throw new Exception("สถานะไม่ถูกต้อง");
            // ตำแหน่งไม่ว่าง ไม่เกิน 100 ตัว
            $pos = trim((string)($_POST['position'] ?? ''));
            if ($pos === '' || mb_strlen($pos) > 100)
                throw new Exception("ตำแหน่งไม่ถูกต้อง");
            // บัญชีที่ผูกต้องเป็นผู้ใช้ role Staff เท่านั้น
            if ($uid !== null && !rows($conn, "SELECT 1 FROM users WHERE user_id=? AND role='Staff'", "i", $uid))
                throw new Exception("บัญชีผู้ใช้ไม่ถูกต้อง");
        }
        if ($act === 'add')
            q($conn, "INSERT INTO staffs (user_id, name, position, phone, status) VALUES (?,?,?,?,?)", "issss",
              $uid, $_POST['name'], $_POST['position'], $_POST['phone'], $_POST['status']);

        if ($act === 'edit')
            q($conn, "UPDATE staffs SET user_id=?, name=?, position=?, phone=?, status=? WHERE staff_id=?", "issssi",
              $uid, $_POST['name'], $_POST['position'], $_POST['phone'], $_POST['status'], $_POST['id']);

        if ($act === 'delete')
            q($conn, "DELETE FROM staffs WHERE staff_id=?", "i", $_POST['id']);

        header("Location: admin_staff.php");
        exit();
    } catch (Exception $ex) {      // Exception จากการตรวจรูปแบบ หรือ error จาก DB
        $msg = $ex instanceof mysqli_sql_exception ? "บันทึกไม่สำเร็จ กรุณาตรวจสอบข้อมูล" : $ex->getMessage();
    }
}

$staffs = rows($conn, "SELECT * FROM staffs ORDER BY staff_id");
$staff_users = rows($conn, "SELECT user_id, name FROM users WHERE role='Staff'");   // บัญชีที่เลือกผูกได้

$title = "จัดการพนักงาน (Staff)";
$sub = "จัดการข้อมูลพนักงาน ตำแหน่ง สถานะ และบัญชีที่ใช้เข้าระบบ";
include 'config/admin_header.php';
?>

                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">

                    <!-- ฟอร์มเพิ่มพนักงานใหม่ -->
                    <form method="POST" class="row g-2 pb-3 mb-3 border-bottom">
                        <?= csrf_field() ?>
                        <input type="hidden" name="act" value="add">
                        <div class="col-md-2"><input name="name" class="form-control form-control-custom" placeholder="ชื่อ" required></div>
                        <div class="col-md-2"><input name="position" class="form-control form-control-custom" placeholder="ตำแหน่ง" required></div>
                        <div class="col-md-2"><input name="phone" class="form-control form-control-custom" placeholder="เบอร์โทร" maxlength="10" required></div>
                        <div class="col-md-2">
                            <select name="status" class="form-select form-control-custom">
                                <?php foreach ($statuses as $st): ?><option><?= $st ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="user_id" class="form-select form-control-custom">
                                <option value="">-- ไม่ผูกบัญชี --</option>
                                <?php foreach ($staff_users as $u): ?><option value="<?= $u['user_id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2"><button class="btn btn-petcare rounded-pill w-100"><i class="fas fa-plus me-1"></i> เพิ่ม</button></div>
                    </form>

                    <!-- รายการพนักงาน : 1 แถว = 1 ฟอร์ม -->
                    <?php foreach ($staffs as $s): ?>
                    <form method="POST" class="row g-2 mb-2 align-items-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $s['staff_id'] ?>">
                        <div class="col-md-2"><input name="name" class="form-control form-control-custom" value="<?= e($s['name']) ?>" required></div>
                        <div class="col-md-2"><input name="position" class="form-control form-control-custom" value="<?= e($s['position']) ?>" required></div>
                        <div class="col-md-2"><input name="phone" class="form-control form-control-custom" value="<?= e($s['phone']) ?>" maxlength="10" required></div>
                        <div class="col-md-2">
                            <select name="status" class="form-select form-control-custom">
                                <?php foreach ($statuses as $st): ?>
                                    <option <?= $s['status'] == $st ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="user_id" class="form-select form-control-custom">
                                <option value="">-- ไม่ผูกบัญชี --</option>
                                <?php foreach ($staff_users as $u): ?>
                                    <option value="<?= $u['user_id'] ?>" <?= $s['user_id'] == $u['user_id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button name="act" value="edit" class="btn btn-outline-warning btn-sm rounded-pill flex-fill" title="บันทึก"><i class="fas fa-save"></i></button>
                            <button name="act" value="delete" class="btn btn-outline-danger btn-sm rounded-pill flex-fill" title="ลบ"
                                    onclick="return confirm('ลบพนักงานคนนี้?');"><i class="fas fa-trash"></i></button>
                        </div>
                    </form>
                    <?php endforeach; ?>
                </div>

<?php include 'config/admin_footer.php'; ?>
