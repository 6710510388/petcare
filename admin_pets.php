<?php
// ===== admin_pets.php : จัดการสัตว์เลี้ยงของลูกค้าทุกคน (แก้ไข / ลบ) =====
require 'config/db.php';
need(['Admin']);                                 // เฉพาะ Admin เท่านั้น
$msg = '';

// ---------- 1) รับฟอร์ม (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $act = (string)($_POST['act'] ?? '');
        if (!in_array($act, ['edit', 'delete'], true)) throw new Exception('คำขอไม่ถูกต้อง');
        if (!filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) || (int)$_POST['id'] < 1) throw new Exception('รหัสไม่ถูกต้อง');
        // ตรวจค่าฝั่งเซิร์ฟเวอร์ : ประเภทต้องเป็น สุนัข/แมว, ชื่อ-สายพันธุ์-อายุไม่ว่าง, น้ำหนัก 0.1-200 กก. (valid_pet อยู่ใน config/db.php)
        if ($act === 'edit' && !valid_pet($_POST))
            throw new Exception("ข้อมูลไม่ถูกต้อง");

        if ($act === 'edit')
            q($conn, "UPDATE pets SET name=?, type=?, breed=?, age=?, weight=? WHERE pet_id=?", "ssssdi",
              trim($_POST['name']), $_POST['type'], trim($_POST['breed']), trim($_POST['age']), $_POST['weight'], $_POST['id']);

        if ($act === 'delete')
            q($conn, "DELETE FROM pets WHERE pet_id=?", "i", $_POST['id']);

        header("Location: admin_pets.php");
        exit();
    } catch (Exception $ex) {
        $msg = "บันทึกไม่สำเร็จ กรุณาตรวจสอบข้อมูล";
    }
}

// ดึงสัตว์เลี้ยงทั้งหมด พร้อมชื่อเจ้าของ (JOIN users)
$pets = rows($conn, "SELECT p.*, u.name owner FROM pets p JOIN users u ON u.user_id = p.owner_id ORDER BY p.pet_id");

$title = "จัดการสัตว์เลี้ยง (Pets)";
$sub = "ตรวจสอบ แก้ไข และลบข้อมูลสัตว์เลี้ยงของลูกค้า";
include 'config/admin_header.php';
?>

                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <?php if (!$pets): ?><p class="text-muted mb-0">ยังไม่มีข้อมูลสัตว์เลี้ยง</p><?php endif; ?>

                    <!-- 1 แถว = 1 ฟอร์ม -->
                    <?php foreach ($pets as $p): ?>
                    <form method="POST" class="row g-2 mb-2 align-items-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $p['pet_id'] ?>">
                        <div class="col-md-2 small text-muted"><i class="fas fa-user me-1"></i><?= e($p['owner']) ?></div>
                        <div class="col-md-2"><input name="name" class="form-control form-control-custom" value="<?= e($p['name']) ?>" required></div>
                        <div class="col-md-1">
                            <select name="type" class="form-select form-control-custom px-2">
                                <?php foreach (['สุนัข', 'แมว'] as $t): ?>
                                    <option <?= $p['type'] == $t ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2"><input name="breed" class="form-control form-control-custom" value="<?= e($p['breed']) ?>" required></div>
                        <div class="col-md-2"><input name="age" class="form-control form-control-custom" value="<?= e($p['age']) ?>" required></div>
                        <div class="col-md-1"><input name="weight" type="number" step="0.1" class="form-control form-control-custom px-2" value="<?= (float)$p['weight'] ?>" required></div>
                        <div class="col-md-2 d-flex gap-1">
                            <button name="act" value="edit" class="btn btn-outline-warning btn-sm rounded-pill flex-fill" title="บันทึก"><i class="fas fa-save"></i></button>
                            <button name="act" value="delete" class="btn btn-outline-danger btn-sm rounded-pill flex-fill" title="ลบ"
                                    onclick="return confirm('ลบสัตว์เลี้ยงตัวนี้?');"><i class="fas fa-trash"></i></button>
                        </div>
                    </form>
                    <?php endforeach; ?>
                </div>

<?php include 'config/admin_footer.php'; ?>
