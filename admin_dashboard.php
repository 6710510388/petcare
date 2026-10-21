<?php
// ===== admin_dashboard.php : สรุปภาพรวมระบบ (ข้อมูลจริงจาก DB) =====
require 'config/db.php';
need(['Admin']);                                 // เฉพาะ Admin เท่านั้น

// ฟังก์ชันย่อ : นับจำนวนแถวจาก SQL ที่ใส่เข้ามา (SQL ชุดนี้ไม่มีค่าจากผู้ใช้ จึงปลอดภัย)
$count = fn($sql) => rows($conn, $sql)[0]['c'];

// 1) การ์ดตัวเลขสรุป : [ชื่อ, ค่า, สีตัวเลข, สีพื้นไอคอน, ไอคอน]
$cards = [
    ['การจองทั้งหมด',      $count("SELECT COUNT(*) c FROM bookings"),                          'text-petcare', 'bg-danger',  'fa-calendar-alt'],
    ['รอยืนยัน (Pending)', $count("SELECT COUNT(*) c FROM bookings WHERE status='Pending'"),   'text-warning', 'bg-warning', 'fa-clock'],
    ['ลูกค้าทั้งหมด',      $count("SELECT COUNT(*) c FROM users WHERE role='Customer'"),       'text-primary', 'bg-primary', 'fa-users'],
    ['สัตว์เลี้ยงในระบบ',  $count("SELECT COUNT(*) c FROM pets"),                              'text-success', 'bg-success', 'fa-paw'],
];

// 2) กราฟเส้น : จำนวนการจองในรอบ 7 วันล่าสุด (นับตามวันที่รับบริการ)
$day_names = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
$labels = [];
$data = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $labels[] = $day_names[date('w', strtotime($d))];
    $data[] = (int)$count("SELECT COUNT(*) c FROM bookings WHERE booking_date = '$d'");
}

// 3) กราฟวงกลม : จำนวนการจองแยกตามบริการ
$pie = rows($conn, "SELECT s.name, COUNT(b.booking_id) c FROM services s LEFT JOIN bookings b ON b.service_id = s.service_id GROUP BY s.service_id");

// 4) รายการจองล่าสุด 5 รายการ
$recent = rows($conn, "SELECT b.*, u.name uname, p.name pname, p.type, s.name sname
                       FROM bookings b
                       JOIN users u ON u.user_id = b.user_id
                       JOIN pets p ON p.pet_id = b.pet_id
                       JOIN services s ON s.service_id = b.service_id
                       ORDER BY b.booking_id DESC LIMIT 5");

// ตัวแปรหัวข้อหน้า (ใช้ใน admin_header.php)
$title = "สรุปภาพรวมระบบ (Dashboard)";
$sub = "รายงานสรุปจำนวนการจอง สถิติบอร์ด และข้อมูลบริการทั้งหมด";
include 'config/admin_header.php';
?>

                <!-- 1. การ์ดสรุปตัวเลขสถิติ (Summary Cards) : วนลูปจาก $cards -->
                <div class="row g-3 mb-4">
                    <?php foreach ($cards as [$label, $value, $text, $bg, $icon]): ?>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold"><?= $label ?></span>
                                    <h3 class="fw-bold mb-0 mt-1 <?= $text ?>"><?= $value ?></h3>
                                </div>
                                <div class="<?= $bg ?> bg-opacity-10 <?= $text ?> rounded-circle p-3">
                                    <i class="fas <?= $icon ?> fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- 2. กราฟสรุปสถิติ (Charts Section) -->
                <div class="row g-4 mb-4">
                    <!-- กราฟเส้น: สถิติการจองรายวัน -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                            <h5 class="fw-bold mb-3"><i class="fas fa-chart-area text-petcare me-2"></i>สถิติการจองบริการรายวัน</h5>
                            <canvas id="bookingChart" height="120"></canvas>
                        </div>
                    </div>

                    <!-- กราฟวงกลม: สัดส่วนประเภทบริการ -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                            <h5 class="fw-bold mb-3"><i class="fas fa-chart-pie text-petcare me-2"></i>สัดส่วนการใช้บริการ</h5>
                            <canvas id="servicePieChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- 3. ตารางรายการจองล่าสุด (Recent Bookings Table) -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
                        <h5 class="fw-bold mb-0"><i class="fas fa-history text-petcare me-2"></i>รายการจองล่าสุด</h5>
                        <a href="admin_bookings.php" class="btn btn-outline-danger btn-sm rounded-pill">ดูทั้งหมด</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3">ID</th>
                                    <th class="py-3">ลูกค้า</th>
                                    <th class="py-3">สัตว์เลี้ยง</th>
                                    <th class="py-3">บริการ</th>
                                    <th class="py-3">วัน-เวลา</th>
                                    <th class="py-3">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $b): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-petcare">#BK-<?= sprintf('%04d', $b['booking_id']) ?></td>
                                    <td><?= e($b['uname']) ?></td>
                                    <td><?= e($b['pname']) ?> (<?= e($b['type']) ?>)</td>
                                    <td><?= e($b['sname']) ?></td>
                                    <td><?= thai_date($b['booking_date']) ?> (<?= e($b['booking_time']) ?> น.)</td>
                                    <td><span class="badge <?= badge($b['status']) ?> rounded-pill px-3"><?= $b['status'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (!$recent): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีรายการจอง</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

    <!-- Chart.js สำหรับแสดงกราฟสรุปสถิติ -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <!-- Script วาดกราฟ : ข้อมูลส่งจาก PHP ผ่าน json_encode -->
    <script>
        // 1. กราฟเส้น สถิติการจองบริการ
        new Chart(document.getElementById('bookingChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: 'จำนวนการจอง (ครั้ง)',
                    data: <?= json_encode($data) ?>,
                    borderColor: '#E31B23',
                    backgroundColor: 'rgba(227, 27, 35, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            }
        });

        // 2. กราฟวงกลม สัดส่วนบริการ
        new Chart(document.getElementById('servicePieChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($pie, 'name'), JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    data: <?= json_encode(array_map('intval', array_column($pie, 'c'))) ?>,
                    backgroundColor: ['#E31B23', '#ffc107', '#0d6efd', '#198754', '#6f42c1', '#fd7e14']
                }]
            }
        });
    </script>

<?php include 'config/admin_footer.php'; ?>
