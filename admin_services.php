<?php
// ===== admin_services.php : จัดการบริการและราคา (เพิ่ม / แก้ไข / ลบ) =====
require 'config/db.php';
need(['Admin']);                                 // เฉพาะ Admin เท่านั้น
$msg = '';

// ---------- 1) รับฟอร์ม (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    try {
        if (!in_array($act, ['add', 'edit', 'delete'], true)) throw new Exception('คำขอไม่ถูกต้อง');
        if (in_array($act, ['edit', 'delete'], true) && (!filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) || (int)$_POST['id'] < 1)) throw new Exception('รหัสไม่ถูกต้อง');
        // ตรวจค่าฝั่งเซิร์ฟเวอร์ : ชื่อบริการไม่ว่าง (ไม่เกิน 100 ตัว), รายละเอียดไม่เกิน 1,000 ตัว, ราคาเป็นตัวเลข 0 - 1,000,000
        if ($act === 'add' || $act === 'edit') {
            $sname  = trim((string)($_POST['name'] ?? ''));
            $sdesc  = trim((string)($_POST['description'] ?? ''));
            $sprice = $_POST['price'] ?? '';
            if ($sname === '' || mb_strlen($sname) > 100 || mb_strlen($sdesc) > 1000
                || !is_numeric($sprice) || (float)$sprice < 0 || (float)$sprice > 1000000)
                throw new Exception("ข้อมูลไม่ถูกต้อง");
        }

        if ($act === 'add')
            q($conn, "INSERT INTO services (name, description, price) VALUES (?,?,?)", "ssd",
              $sname, $sdesc, $sprice);

        if ($act === 'edit')
            q($conn, "UPDATE services SET name=?, description=?, price=? WHERE service_id=?", "ssdi",
              $sname, $sdesc, $sprice, $_POST['id']);

        if ($act === 'delete')
            q($conn, "DELETE FROM services WHERE service_id=?", "i", $_POST['id']);

        header("Location: admin_services.php");
        exit();
    } catch (Exception $ex) {
        $msg = "บันทึกไม่สำเร็จ กรุณาตรวจสอบข้อมูล";
    }
}

$services = rows($conn, "SELECT * FROM services ORDER BY service_id");

$title = "จัดการบริการ (Services)";
$sub = "เพิ่ม แก้ไข ราคา และลบบริการของร้าน";
include 'config/admin_header.php';
?>

                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">

                    <!-- ฟอร์มเพิ่มบริการใหม่ -->
                    <form method="POST" class="row g-2 pb-3 mb-3 border-bottom">
                        <?= csrf_field() ?>
                        <input type="hidden" name="act" value="add">
                        <div class="col-md-3"><input name="name" class="form-control form-control-custom" placeholder="ชื่อบริการ" required></div>
                        <div class="col-md-5"><input name="description" class="form-control form-control-custom" placeholder="รายละเอียด"></div>
                        <div class="col-md-2"><input name="price" type="number" step="0.01" min="0" class="form-control form-control-custom" placeholder="ราคา (฿)" required></div>
                        <div class="col-md-2"><button class="btn btn-petcare rounded-pill w-100"><i class="fas fa-plus me-1"></i> เพิ่ม</button></div>
                    </form>

                    <!-- รายการบริการ : 1 แถว = 1 ฟอร์ม -->
                    <?php foreach ($services as $s): ?>
                    <form method="POST" class="row g-2 mb-2 align-items-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $s['service_id'] ?>">
                        <div class="col-md-3"><input name="name" class="form-control form-control-custom" value="<?= e($s['name']) ?>" required></div>
                        <div class="col-md-5"><input name="description" class="form-control form-control-custom" value="<?= e($s['description']) ?>"></div>
                        <div class="col-md-2"><input name="price" type="number" step="0.01" min="0" class="form-control form-control-custom" value="<?= (float)$s['price'] ?>" required></div>
                        <div class="col-md-2 d-flex gap-1">
                            <button name="act" value="edit" class="btn btn-outline-warning btn-sm rounded-pill flex-fill" title="บันทึก"><i class="fas fa-save"></i></button>
                            <button name="act" value="delete" class="btn btn-outline-danger btn-sm rounded-pill flex-fill" title="ลบ"
                                    onclick="return confirm('ลบบริการนี้? (การจองที่ใช้บริการนี้จะถูกลบด้วย)');"><i class="fas fa-trash"></i></button>
                        </div>
                    </form>
                    <?php endforeach; ?>
                </div>

<?php include 'config/admin_footer.php'; ?>
