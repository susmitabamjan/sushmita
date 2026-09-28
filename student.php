<?php
include "db.php";

$reg_no = $_SESSION['reg_no'] ?? null;
if (!$reg_no) {
    header("Location: login.php");
    exit();
}

// Fetch student info
$stmt = $conn->prepare("SELECT * FROM users WHERE reg_no = ?");
$stmt->bind_param("s", $reg_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Fetch fees
$stmt = $conn->prepare("SELECT * FROM student WHERE reg_no = ?");
$stmt->bind_param("s", $reg_no);
$stmt->execute();
$fees = $stmt->get_result()->fetch_assoc();

// Calculate PAID from approved payments only
//$stmt = $conn->prepare("SELECT SUM(paid_amount) as total_paid FROM payment WHERE reg_no = ? AND status = 'approved'");
$stmt->bind_param("s", $reg_no);
$stmt->execute();
$paid_result = $stmt->get_result()->fetch_assoc();
$paid = $paid_result['total_paid'] ?? 0;

$total = $fees['total_fee'] ?? 0;
$installment = $fees['installment'] ?? 0;
$due = $total - $paid;

// Course data
$courses = [];
if(isset($fees['faculty'])){
    if($fees['faculty'] == 'Diploma'){
        $courses = [
            ['subject'=>'Mathematics','teacher'=>'Mr. Sharma','status'=>'Ongoing'],
            ['subject'=>'Physics','teacher'=>'Ms. Koirala','status'=>'Ongoing'],
            ['subject'=>'Chemistry','teacher'=>'Mr. Thapa','status'=>'Ongoing'],
        ];
    } elseif($fees['faculty'] == 'B.Tech'){
        $courses = [
            ['subject'=>'Education Theory','teacher'=>'Ms. Rai','status'=>'Ongoing'],
            ['subject'=>'Computer Science','teacher'=>'Mr. Sharma','status'=>'Ongoing'],
            ['subject'=>'Mathematics','teacher'=>'Ms. Koirala','status'=>'Ongoing'],
            ['subject'=>'Physics','teacher'=>'Mr. Thapa','status'=>'Ongoing'],
        ];
    }
}
$notifications = [];

if ($reg_no) {
    $stmt = $conn->prepare("
        SELECT id, comment, created_at
        FROM comments
        WHERE reg_no = ?
        ORDER BY created_at DESC

    ");

    $stmt->bind_param("s", $reg_no);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { 
  margin: 0; 
  padding: 0; 
  box-sizing: border-box; 
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  min-height: 100vh;
  color: #333;
}

/* ============ NAVBAR ============ */
.navbar {
  background: linear-gradient(90deg, #2c3e50 0%, #34495e 100%);
  padding: 15px 40px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 6px 25px rgba(0, 0, 0, 0.25);
  backdrop-filter: blur(10px);
  position: sticky;
  top: 0;
  z-index: 1000;
}

.navbar-brand { 
  font-size: 24px; 
  font-weight: 800; 
  color: white; 
  display: flex; 
  gap: 12px;
  align-items: center;
  letter-spacing: 0.5px;
}

.navbar-brand i {
  background: linear-gradient(135deg, #667eea, #764ba2);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  font-size: 28px;
}

.navbar-links { 
  display: flex; 
  gap: 8px;
  align-items: center;
}

.navbar-links a { 
  color: white; 
  text-decoration: none; 
  font-weight: 500; 
  transition: all 0.3s ease;
  padding: 10px 16px; 
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.navbar-links a:hover { 
  background: rgba(255, 255, 255, 0.15);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.navbar-links a i {
  font-size: 16px;
}

/* ============ CONTAINER ============ */
.container { 
  max-width: 1200px; 
  margin: 0 auto; 
  padding: 40px 20px;
}

/* ============ PAGE HEADER ============ */
.page-header { 
  color: white; 
  margin-bottom: 40px;
  animation: slideInDown 0.6s ease-out;
}

.page-header h1 { 
  font-size: 42px; 
  margin-bottom: 8px;
  font-weight: 800;
  letter-spacing: -0.5px;
}

.page-header p {
  font-size: 16px;
  opacity: 0.9;
  font-weight: 500;
}

/* ============ STATS GRID ============ */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 24px;
  margin-bottom: 40px;
}

.stat-card {
  background: white;
  padding: 28px;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
  border-left: 5px solid #667eea;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 200px;
  height: 200px;
  background: radial-gradient(circle, rgba(102, 126, 234, 0.1), transparent);
  border-radius: 50%;
}

.stat-card:hover { 
  transform: translateY(-8px);
  box-shadow: 0 20px 40px rgba(102, 126, 234, 0.25);
}

.stat-card:nth-child(2) {
  border-left-color: #f39c12;
}

.stat-card:nth-child(3) {
  border-left-color: #e74c3c;
}

.stat-card:nth-child(4) {
  border-left-color: #27ae60;
}

.stat-label { 
  color: #95a5a6; 
  font-size: 11px; 
  text-transform: uppercase; 
  font-weight: 700;
  letter-spacing: 1px;
  margin-bottom: 10px;
}

.stat-value { 
  font-size: 32px; 
  font-weight: 800; 
  color: #2c3e50;
  margin-bottom: 10px;
}

.stat-icon { 
  font-size: 40px; 
  background: linear-gradient(135deg, #667eea, #764ba2);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  opacity: 0.8;
}

/* ============ CARDS ============ */
.card {
  background: white;
  padding: 35px;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
  margin-bottom: 30px;
  animation: slideInUp 0.6s ease-out;
}

.card h2 {
  color: #2c3e50;
  margin-bottom: 25px;
  border-bottom: 3px solid #667eea;
  padding-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 22px;
  font-weight: 700;
}

.card h2 i {
  color: #667eea;
  font-size: 26px;
}

.card h3 {
  color: #2c3e50;
  margin-top: 28px;
  margin-bottom: 15px;
  font-size: 16px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* ============ INFO GRID ============ */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 18px;
  margin-bottom: 25px;
}

.info-box {
  background: linear-gradient(135deg, #f8f9fa 0%, #ecf0f1 100%);
  padding: 20px;
  border-radius: 12px;
  border-left: 4px solid #667eea;
  transition: all 0.3s ease;
}

.info-box:hover {
  border-left-color: #764ba2;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
  transform: translateX(4px);
}

.info-label { 
  color: #95a5a6; 
  font-size: 11px; 
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}

.info-value { 
  font-size: 18px; 
  font-weight: 800; 
  color: #2c3e50;
}

/* ============ PROGRESS BAR ============ */
.progress-container {
  margin: 25px 0;
}

.progress-bar {
  width: 100%;
  height: 40px;
  background: #ecf0f1;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.1);
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #27ae60, #2ecc71);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 13px;
  transition: width 0.5s ease;
  box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
}

/* ============ TABLE ============ */
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}

table th {
  background: linear-gradient(90deg, #2c3e50, #34495e);
  color: white;
  padding: 16px;
  text-align: left;
  font-weight: 700;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

table td {
  padding: 16px;
  border-bottom: 1px solid #ecf0f1;
}

table tbody tr {
  transition: all 0.3s ease;
}

table tbody tr:hover { 
  background: #f8f9fa;
  box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.05);
}

/* ============ BUTTONS ============ */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 14px 28px;
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
  text-decoration: none;
  border-radius: 10px;
  font-weight: 700;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border: none;
  cursor: pointer;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
}

.btn:hover { 
  transform: translateY(-3px);
  box-shadow: 0 12px 30px rgba(102, 126, 234, 0.5);
}

.btn:active {
  transform: translateY(-1px);
}

/* ============ STATUS BADGE ============ */
.status-badge {
  display: inline-block;
  padding: 8px 16px;
  border-radius: 25px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-ongoing { 
  background: linear-gradient(135deg, #d4edda, #c3e6cb);
  color: #155724;
  box-shadow: 0 4px 10px rgba(21, 87, 36, 0.15);
}

/* ============ NOTIFICATION BOX ============ */
#notifBtn {
  position: relative;
  cursor: pointer;
  transition: all 0.3s ease;
}

#notifBtn:hover {
  transform: scale(1.1);
}

#notifCount {
  position: absolute;
  top: -8px;
  right: -12px;
  background: linear-gradient(135deg, #e74c3c, #c0392b);
  color: white;
  padding: 3px 8px;
  border-radius: 50%;
  font-size: 10px;
  font-weight: 800;
  box-shadow: 0 4px 10px rgba(231, 76, 60, 0.3);
  min-width: 20px;
  text-align: center;
}

#notifBox {
  display: none;
  position: absolute;
  right: 20px;
  top: 70px;
  width: 360px;
  background: white;
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
  border-radius: 12px;
  overflow: hidden;
  z-index: 999;
  animation: slideInRight 0.3s ease-out;
}

#notifBox::before {
  content: '';
  position: absolute;
  top: -8px;
  right: 40px;
  width: 16px;
  height: 16px;
  background: white;
  transform: rotate(45deg);
  box-shadow: -2px -2px 5px rgba(0, 0, 0, 0.1);
}

.notif-header {
  padding: 18px;
  background: linear-gradient(90deg, #2c3e50, #34495e);
  color: white;
  font-weight: 700;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.notif-header i {
  font-size: 16px;
}

.notif-body {
  max-height: 400px;
  overflow-y: auto;
}

.notif-item {
  padding: 16px;
  border-bottom: 1px solid #ecf0f1;
  transition: all 0.3s ease;
}

.notif-item:hover {
  background: #f8f9fa;
}

.notif-item:last-child {
  border-bottom: none;
}

.notif-item b {
  color: #667eea;
  font-size: 12px;
  text-transform: uppercase;
}

.notif-item p {
  margin: 8px 0;
  color: #2c3e50;
  font-size: 13px;
  line-height: 1.5;
}

.notif-item small {
  color: #95a5a6;
  font-size: 11px;
}

.notif-empty {
  padding: 40px 20px;
  text-align: center;
  color: #95a5a6;
  font-size: 14px;
}

/* ============ ANIMATIONS ============ */
@keyframes slideInDown {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

/* ============ SCROLLBAR ============ */
.notif-body::-webkit-scrollbar {
  width: 6px;
}

.notif-body::-webkit-scrollbar-track {
  background: #f1f1f1;
}

.notif-body::-webkit-scrollbar-thumb {
  background: #667eea;
  border-radius: 3px;
}

.notif-body::-webkit-scrollbar-thumb:hover {
  background: #764ba2;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 768px) {
  .stats-grid { 
    grid-template-columns: 1fr; 
  }
  
  .navbar { 
    flex-direction: column; 
    gap: 15px;
    padding: 15px 20px;
  }
  
  .navbar-brand {
    font-size: 20px;
  }
  
  .navbar-links { 
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: center;
  }
  
  .page-header h1 { 
    font-size: 28px; 
  }
  
  .container {
    padding: 20px 15px;
  }
  
  .card {
    padding: 20px;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  #notifBox {
    width: 90vw;
    max-width: 360px;
    right: 5vw;
  }
}

@media (max-width: 480px) {
  .navbar-links a {
    padding: 8px 12px;
    font-size: 12px;
  }
  
  .stat-value {
    font-size: 24px;
  }
  
  .page-header h1 {
    font-size: 20px;
  }
  
  .navbar-brand {
    font-size: 18px;
  }
}
</style>
</head>
<body>

<nav class="navbar">
  <div class="navbar-brand">
    <i class="fas fa-graduation-cap"></i> Student Portal
  </div>
  <div class="navbar-links">
    <a href="payment.php"><i class="fas fa-credit-card"></i> Pay Fees</a>
    <a href="history record.php"><i class="fas fa-history"></i> History</a>
    <a href="#" id="notifBtn">
      <i class="fas fa-bell"></i>
      <span id="notifCount"><?= count($notifications) ?></span>
    </a>
    <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>
</nav>

<div class="container">

  <div class="page-header">
    <h1><i class="fas fa-tachometer-alt"></i> Welcome, <?= htmlspecialchars($student['username']) ?> 👋</h1>
    <p>Track your fees and course progress</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Total Fee</div>
      <div class="stat-value">₹<?= number_format($total, 2) ?></div>
      <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
    </div>

    

    

    <div class="stat-card">
      <div class="stat-label">Per Installment</div>
      <div class="stat-value">₹<?= number_format($installment, 2) ?></div>
      <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
    </div>
  </div>

  <div class="card">
    <h2><i class="fas fa-building"></i> Account Details</h2>
    <div class="info-grid">
      <div class="info-box">
        <div class="info-label">Account Name</div>
        <div class="info-value">SINDHULI SAMUDAYIK TECHNICAL INST.</div>
      </div>
      <div class="info-box">
        <div class="info-label">Account Number</div>
        <div class="info-value">08801050253178</div>
      </div>
    </div>
  </div>

  <div class="card">
    <h2><i class="fas fa-user-circle"></i> Student Information</h2>
    <div class="info-grid">
      <div class="info-box">
        <div class="info-label">Registration No</div>
        <div class="info-value"><?= htmlspecialchars($student['reg_no']) ?></div>
      </div>
      <div class="info-box">
        <div class="info-label">Faculty</div>
        <div class="info-value"><?= htmlspecialchars($fees['faculty'] ?? 'N/A') ?></div>
      </div>
      <div class="info-box">
        <div class="info-label">Semester</div>
        <div class="info-value"><?= htmlspecialchars($fees['semester'] ?? 'N/A') ?></div>
      </div>
    </div>

    <h3>Payment Progress</h3>
    <div class="progress-container">
      <div class="progress-bar">
        <div class="progress-fill" style="width: <?= $total > 0 ? ($paid / $total * 100) : 0 ?>%;">
          <?= $total > 0 ? round(($paid / $total * 100), 1) : 0 ?>%
        </div>
      </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px;">
      <?php if($due > 0): ?>
        <a href="payment.php" class="btn"><i class="fas fa-credit-card"></i> Submit Payment</a>
      <?php else: ?>
        <div style="color: #27ae60; font-weight: 700; display: flex; align-items: center; gap: 10px;">
          <i class="fas fa-check-circle"></i> All Payments Completed
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<div id="notifBox">
  <div class="notif-header">
    <i class="fas fa-bell"></i> Notifications
  </div>
  <div class="notif-body">
    <?php if (count($notifications) == 0): ?>
      <div class="notif-empty">
        <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; color: #bdc3c7; display: block;"></i>
        No notifications yet
      </div>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <div class="notif-item">
          <b>Status:</b> <?= htmlspecialchars($n['comment']) ?><br>
          <small><?= $n['created_at'] ?></small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
document.getElementById('notifBtn').addEventListener('click', function(e) {
  e.preventDefault();
  const notifBox = document.getElementById('notifBox');
  notifBox.style.display = notifBox.style.display === 'none' ? 'block' : 'none';
});

document.addEventListener('click', function(e) {
  const notifBtn = document.getElementById('notifBtn');
  const notifBox = document.getElementById('notifBox');
  if (!notifBtn.contains(e.target) && !notifBox.contains(e.target)) {
    notifBox.style.display = 'none';
  }
});
</script>

</body>
</html>