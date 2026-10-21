<?php
// ===== pets.php : แสดง / เพิ่ม / แก้ไข / ลบ สัตว์เลี้ยงของผู้ใช้ที่ Login (Single File) =====
require 'config/db.php';
need();                                          // ต้อง Login ก่อน
$uid = $_SESSION['user_id'];

// ---------- 1) รับฟอร์ม (POST) : ดูว่าปุ่มไหนส่งมาจากค่า act ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['act'] ?? '');
    // ตรวจสอบ action, ประเภทสัตว์เลี้ยง และข้อมูลทุกช่องจากฝั่งเซิร์ฟเวอร์
    if (!in_array($act, ['add', 'edit', 'delete'], true)) {
        http_response_code(400); exit('คำขอไม่ถูกต้อง');
    }
    if ($act !== 'delete' && !valid_pet($_POST)) {
        header("Location: pets.php?err=1"); exit();
    }

    if ($act === 'add')        // เพิ่มสัตว์เลี้ยง
        q($conn, "INSERT INTO pets (owner_id, name, type, breed, age, weight) VALUES (?,?,?,?,?,?)",
          "issssd", $uid, $_POST['name'], $_POST['type'], $_POST['breed'], $_POST['age'], $_POST['weight']);

    if ($act === 'edit')       // แก้ไข (เช็ค owner_id ด้วย กันแก้ของคนอื่น)
        q($conn, "UPDATE pets SET name=?, type=?, breed=?, age=?, weight=? WHERE pet_id=? AND owner_id=?",
          "ssssdii", $_POST['name'], $_POST['type'], $_POST['breed'], $_POST['age'], $_POST['weight'], $_POST['id'], $uid);

    if ($act === 'delete')     // ลบ
        q($conn, "DELETE FROM pets WHERE pet_id=? AND owner_id=?", "ii", $_POST['id'], $uid);

    header("Location: pets.php");                // กัน refresh แล้วส่งฟอร์มซ้ำ
    exit();
}

// ---------- 2) ดึงสัตว์เลี้ยงของเราจากฐานข้อมูล ----------
$pets = rows($conn, "SELECT * FROM pets WHERE owner_id=? ORDER BY pet_id", "i", $uid);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care - จัดการข้อมูลสัตว์เลี้ยง</title>
    
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
                <a href="booking.php" class="btn btn-petcare rounded-pill btn-sm">จองบริการ</a>
                <a href="index.php" class="btn btn-outline-secondary rounded-pill btn-sm">หน้าหลัก</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        
        <!-- หัวข้อ และ ปุ่มเพิ่มสัตว์เลี้ยง -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-petcare mb-1">สัตว์เลี้ยงของฉัน (My Pets)</h2>
                <p class="text-muted small mb-0">จัดการข้อมูลสัตว์เลี้ยงของคุณเพื่อใช้สำหรับการจองบริการ</p>
            </div>
            <button class="btn btn-petcare rounded-pill px-3 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addPetModal">
                <i class="fas fa-plus me-1"></i> เพิ่มสัตว์เลี้ยง
            </button>
        </div>

        <?php if (isset($_GET['err'])): ?>
            <div class="alert alert-danger small">บันทึกไม่สำเร็จ: ชื่อต้องไม่ว่าง (ไม่เกิน 100 ตัว) และน้ำหนักต้องอยู่ระหว่าง 0.1 - 200 กิโลกรัม</div>
        <?php endif; ?>

        <?php if (!$pets): ?>
            <p class="text-muted">ยังไม่มีสัตว์เลี้ยง กดปุ่ม "เพิ่มสัตว์เลี้ยง" เพื่อเริ่มต้น</p>
        <?php endif; ?>

        <!-- รายการสัตว์เลี้ยง : วนลูปสร้างการ์ดจากข้อมูลใน DB -->
        <div class="row g-4">
            <?php foreach ($pets as $p): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-danger bg-opacity-10 text-petcare rounded-circle p-3 me-3">
                                <i class="fas <?= $p['type'] == 'แมว' ? 'fa-cat' : 'fa-dog' ?> fa-2x"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-0"><?= e($p['name']) ?></h4>
                                <span class="badge bg-danger bg-opacity-10 text-petcare rounded-pill px-3"><?= e($p['type']) ?></span>
                            </div>
                        </div>
                        <ul class="list-unstyled small text-muted mb-4 space-y-2">
                            <li class="mb-1"><strong>สายพันธุ์:</strong> <?= e($p['breed']) ?></li>
                            <li class="mb-1"><strong>อายุ:</strong> <?= e($p['age']) ?></li>
                            <li class="mb-1"><strong>น้ำหนัก:</strong> <?= (float)$p['weight'] ?> กิโลกรัม</li>
                        </ul>
                        <!-- ปุ่มจัดการ -->
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-warning btn-sm rounded-pill flex-grow-1" data-bs-toggle="modal" data-bs-target="#editPetModal<?= $p['pet_id'] ?>">
                                <i class="fas fa-edit me-1"></i> แก้ไข
                            </button>
                            <!-- ลบ : ใช้ฟอร์ม POST (ปลอดภัยกว่าลิงก์) -->
                            <form method="POST" class="flex-grow-1" onsubmit="return confirm('ยืนยันการลบข้อมูลสัตว์เลี้ยงตัวนี้?');">
                            <?= csrf_field() ?>
                                <input type="hidden" name="act" value="delete">
                                <input type="hidden" name="id" value="<?= $p['pet_id'] ?>">
                                <button class="btn btn-outline-danger btn-sm rounded-pill w-100"><i class="fas fa-trash me-1"></i> ลบ</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ==================== Modal เพิ่มสัตว์เลี้ยงใหม่ ==================== -->
    <div class="modal fade" id="addPetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-petcare"><i class="fas fa-plus-circle me-2"></i>เพิ่มข้อมูลสัตว์เลี้ยง</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                            <?= csrf_field() ?>
                    <input type="hidden" name="act" value="add">
                    <div class="modal-body py-4">
                        
                        <!-- ชื่อสัตว์เลี้ยง -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ชื่อสัตว์เลี้ยง</label>
                            <input type="text" name="name" maxlength="100" class="form-control form-control-custom" placeholder="เช่น มอมแมม, ส้มส้ม" required>
                        </div>

                        <!-- ประเภทสัตว์เลี้ยง -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ประเภทสัตว์เลี้ยง</label>
                            <select name="type" class="form-select form-control-custom" required>
                                <option value="" selected disabled>-- เลือกประเภท --</option>
                                <option value="สุนัข">สุนัข</option>
                                <option value="แมว">แมว</option>
                            </select>
                        </div>

                        <!-- สายพันธุ์ -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold">สายพันธุ์</label>
                            <input type="text" name="breed" class="form-control form-control-custom" placeholder="เช่น โกลเด้น, เปอร์เซีย" required>
                        </div>

                        <!-- อายุ และ น้ำหนัก -->
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">อายุ (ปี/เดือน)</label>
                                <input type="text" name="age" class="form-control form-control-custom" placeholder="เช่น 2 ปี" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">น้ำหนัก (กก.)</label>
                                <input type="number" step="0.1" min="0.1" max="200" name="weight" class="form-control form-control-custom" placeholder="เช่น 5.5" required>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-petcare rounded-pill px-4">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== Modal แก้ไข : สร้าง 1 อันต่อสัตว์ 1 ตัว (ใส่ค่าเดิมไว้ให้) ==================== -->
    <?php foreach ($pets as $p): ?>
    <div class="modal fade" id="editPetModal<?= $p['pet_id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-petcare"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลสัตว์เลี้ยง</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="act" value="edit">
                    <input type="hidden" name="id" value="<?= $p['pet_id'] ?>">
                    <div class="modal-body py-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ชื่อสัตว์เลี้ยง</label>
                            <input type="text" name="name" class="form-control form-control-custom" value="<?= e($p['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ประเภทสัตว์เลี้ยง</label>
                            <select name="type" class="form-select form-control-custom" required>
                                <option value="สุนัข" <?= $p['type'] == 'สุนัข' ? 'selected' : '' ?>>สุนัข</option>
                                <option value="แมว" <?= $p['type'] == 'แมว' ? 'selected' : '' ?>>แมว</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">สายพันธุ์</label>
                            <input type="text" name="breed" class="form-control form-control-custom" value="<?= e($p['breed']) ?>" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">อายุ (ปี/เดือน)</label>
                                <input type="text" name="age" class="form-control form-control-custom" value="<?= e($p['age']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">น้ำหนัก (กก.)</label>
                                <input type="number" step="0.1" min="0.1" max="200" name="weight" class="form-control form-control-custom" value="<?= (float)$p['weight'] ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-petcare rounded-pill px-4">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
