<?php
// ===== admin_users.php : จัดการผู้ใช้ (เพิ่ม / แก้ไข / ลบ) =====
require 'config/db.php';
need(['Admin']);                                 // เฉพาะ Admin เท่านั้น
$msg = '';
$roles = ['Customer', 'Staff', 'Admin'];          // role ที่อนุญาต (ใช้ทั้งตรวจค่าฝั่งเซิร์ฟเวอร์และแสดงในฟอร์ม)

// ---------- 1) รับฟอร์ม (POST) : ดูจากค่า act ว่าเป็นปุ่มไหน ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    try {
        if (!in_array($act, ['add', 'edit', 'delete'], true))
            throw new Exception("คำขอไม่ถูกต้อง");
        // role ต้องอยู่ในรายการที่อนุญาตเท่านั้น
        if ($act !== 'delete' && !in_array($_POST['role'] ?? '', $roles, true))
            throw new Exception("สิทธิ์ (role) ไม่ถูกต้อง");
        // ตรวจรูปแบบก่อนบันทึก (ฟังก์ชัน valid_* อยู่ใน config/db.php) ถ้าไม่ผ่านให้ throw ไปแสดงที่ catch
        if ($act !== 'delete' && !(valid_name($_POST['name']) && valid_email($_POST['email']) && valid_phone($_POST['phone'])))
            throw new Exception("รูปแบบข้อมูลไม่ถูกต้อง (ชื่อต้องไม่มีตัวเลข / ท้ายโดเมนอีเมลอย่างน้อย 3 ตัวอักษร / เบอร์ 10 หลักขึ้นต้น 0)");
        if ($act === 'add' && !valid_password($_POST['password']))
            throw new Exception("รหัสผ่านต้องยาว 8-72 ตัว และมีทั้งตัวอักษรและตัวเลข");
        if ($act === 'add')       // เพิ่มผู้ใช้ (เข้ารหัสรหัสผ่านด้วย password_hash)
            q($conn, "INSERT INTO users (name, email, phone, password, role) VALUES (?,?,?,?,?)", "sssss",
              $_POST['name'], $_POST['email'], $_POST['phone'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['role']);

        if ($act === 'edit')      // แก้ไขข้อมูล / เปลี่ยน role
            q($conn, "UPDATE users SET name=?, email=?, phone=?, role=? WHERE user_id=?", "ssssi",
              $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['role'], $_POST['id']);

        if ($act === 'delete' && $_POST['id'] != $_SESSION['user_id'])    // ลบ (ห้ามลบตัวเอง)
            q($conn, "DELETE FROM users WHERE user_id=?", "i", $_POST['id']);

        header("Location: admin_users.php");
        exit();
    } catch (Exception $ex) {      // Exception จากการตรวจรูปแบบข้างบน หรือ error จาก DB (mysqli_sql_exception)
        $msg = $ex instanceof mysqli_sql_exception ? "บันทึกไม่สำเร็จ (อีเมลอาจซ้ำ)" : $ex->getMessage();
    }
}

$users = rows($conn, "SELECT * FROM users ORDER BY user_id");

$title = "จัดการผู้ใช้ (Users)";
$sub = "เพิ่ม แก้ไข เปลี่ยนสิทธิ์ และลบผู้ใช้ในระบบ";
include 'config/admin_header.php';
?>

                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">

                    <!-- ฟอร์มเพิ่มผู้ใช้ใหม่ -->
                    <form method="POST" class="row g-2 pb-3 mb-3 border-bottom">
                        <?= csrf_field() ?>
                        <input type="hidden" name="act" value="add">
                        <div class="col-md-2"><input name="name" class="form-control form-control-custom" placeholder="ชื่อ" required></div>
                        <div class="col-md-2"><input name="email" type="email" class="form-control form-control-custom" placeholder="อีเมล" required></div>
                        <div class="col-md-2"><input name="phone" class="form-control form-control-custom" placeholder="เบอร์โทร" maxlength="10" required></div>
                        <div class="col-md-2"><input name="password" type="password" class="form-control form-control-custom" placeholder="รหัสผ่าน" required></div>
                        <div class="col-md-2">
                            <select name="role" class="form-select form-control-custom">
                                <?php foreach ($roles as $r): ?><option><?= $r ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2"><button class="btn btn-petcare rounded-pill w-100"><i class="fas fa-plus me-1"></i> เพิ่ม</button></div>
                    </form>

                    <!-- รายการผู้ใช้ : 1 แถว = 1 ฟอร์ม (ปุ่มบันทึก / ลบ ส่งค่า act ต่างกัน) -->
                    <?php foreach ($users as $u): ?>
                    <form method="POST" class="row g-2 mb-2 align-items-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $u['user_id'] ?>">
                        <div class="col-md-2"><input name="name" class="form-control form-control-custom" value="<?= e($u['name']) ?>" required></div>
                        <div class="col-md-3"><input name="email" type="email" class="form-control form-control-custom" value="<?= e($u['email']) ?>" required></div>
                        <div class="col-md-2"><input name="phone" class="form-control form-control-custom" value="<?= e($u['phone']) ?>" maxlength="10" required></div>
                        <div class="col-md-2">
                            <select name="role" class="form-select form-control-custom">
                                <?php foreach ($roles as $r): ?>
                                    <option <?= $u['role'] == $r ? 'selected' : '' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-1">
                            <button name="act" value="edit" class="btn btn-outline-warning btn-sm rounded-pill flex-fill" title="บันทึก"><i class="fas fa-save"></i> บันทึก</button>
                            <button name="act" value="delete" class="btn btn-outline-danger btn-sm rounded-pill flex-fill" title="ลบ"
                                    onclick="return confirm('ลบผู้ใช้นี้? (สัตว์เลี้ยงและการจองของผู้ใช้จะถูกลบด้วย)');"><i class="fas fa-trash"></i> ลบ</button>
                        </div>
                    </form>
                    <?php endforeach; ?>
                </div>

<?php include 'config/admin_footer.php'; ?>
