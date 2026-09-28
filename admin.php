<?php
include "db.php";

if(session_status() == PHP_SESSION_NONE){
    session_start();
}

// Handle Delete
if(isset($_GET['delete'])){
    $reg = $_GET['delete'];
    $conn->query("DELETE FROM payment WHERE reg_no='$reg'");
    $conn->query("DELETE FROM student WHERE reg_no='$reg'");
    $conn->query("DELETE FROM users WHERE reg_no='$reg'");
    header("Location: admin.php");
    exit();
}

if (isset($_POST['update_status']) && isset($_POST['payment_id'])) {
    $id = $_POST['payment_id'];
    $new_status = $_POST['update_status'];

    $stmt = $conn->prepare("UPDATE payment SET status = ? WHERE payment_id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();

    if ($new_status === 'approved') {
        $stmt2 = $conn->prepare("
            SELECT s.reg_no, s.semester, s.installment
            FROM student s
            WHERE s.reg_no = (SELECT reg_no FROM payment WHERE payment_id = ?)
            LIMIT 1
        ");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $student = $stmt2->get_result()->fetch_assoc();

        if ($student) {
            $reg_no = $student['reg_no'];
            $current_semester = $student['semester'];
            $installment = $student['installment'];

            $stmt3 = $conn->prepare("
    SELECT COALESCE(SUM(paid_installments), 0) AS total_paid
    FROM payment
    WHERE reg_no = ? AND status = 'approved'
");
$stmt3->bind_param("s", $reg_no);
$stmt3->execute();
$total_paid = $stmt3->get_result()->fetch_assoc()['total_paid'];

// One installment amount
$one_installment = $installment;

// 2 installments = 1 semester
$per_semester = $one_installment * 2;

// Required amount up to current semester
$required_total = $current_semester * $per_semester;

// ✅ Upgrade when paid reaches or exceeds required
if ($total_paid >= $required_total) {

    $new_semester = $current_semester + 1;

    $stmt4 = $conn->prepare("UPDATE student SET semester = ? WHERE reg_no = ?");
    $stmt4->bind_param("is", $new_semester, $reg_no);
    $stmt4->execute();
}
            }
        }
    }


if (isset($_POST['send_comment'])) {
    $reg_no = $_POST['reg_no'];
    $comment = $_POST['comment'];

    $stmt = $conn->prepare("
        INSERT INTO comments (reg_no, comment, created_at)
        VALUES (?, ?, NOW())
    ");

    $stmt->bind_param("ss", $reg_no, $comment);
    $stmt->execute();
}

// ================= SEND COMMENT TO ALL =================
if (isset($_POST['send_all_comment'])) {
    $comment = $_POST['comment_all'];

    if (!empty($comment)) {
        $result = $conn->query("SELECT reg_no FROM users WHERE username != 'admin'");
        $stmt = $conn->prepare("INSERT INTO comments (reg_no, comment) VALUES (?, ?)");

        while ($row = $result->fetch_assoc()) {
            $reg_no = $row['reg_no'];
            $stmt->bind_param("ss", $reg_no, $comment);
            $stmt->execute();
        }

        $message = "Comment sent to all students";
        $messageType = "success";
    }
}

// Stats
$total_students = $conn->query("SELECT COUNT(*) as total FROM users WHERE username != 'admin'")->fetch_assoc()['total'];
$total_collected = $conn->query("SELECT SUM(paid_installments) as total FROM payment WHERE status='approved'")->fetch_assoc()['total'] ?? 0;
$pending_approvals = $conn->query("SELECT COUNT(*) as total FROM payment WHERE status='pending'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel - Student Fee Manager</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
  padding: 0;
}

.navbar {
  background: linear-gradient(90deg, #2c3e50 0%, #34495e 100%);
  padding: 15px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  position: sticky;
  top: 0;
  z-index: 1000;
}

.navbar-brand {
  font-size: 22px;
  font-weight: 700;
  color: white;
  display: flex;
  align-items: center;
  gap: 10px;
}

.navbar-links {
  display: flex;
  gap: 25px;
  align-items: center;
}

.navbar-links a {
  color: white;
  text-decoration: none;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s ease;
  padding: 8px 12px;
  border-radius: 6px;
}

.navbar-links a:hover {
  background: rgba(255, 255, 255, 0.15);
  transform: translateY(-2px);
}

.navbar-user {
  display: flex;
  align-items: center;
  gap: 15px;
  color: white;
}

.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 30px 20px;
}

.page-header {
  color: white;
  margin-bottom: 30px;
}

.page-header h1 {
  font-size: 36px;
  font-weight: 700;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-header p {
  font-size: 16px;
  opacity: 0.95;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 35px;
}

.stat-card {
  background: white;
  padding: 28px;
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
  border-top: 4px solid #667eea;
}

.stat-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  margin-bottom: 15px;
}

.stat-icon {
  font-size: 32px;
  color: #667eea;
}

.stat-label {
  color: #7f8c8d;
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.stat-number {
  font-size: 40px;
  font-weight: 700;
  color: #2c3e50;
  margin: 10px 0 5px 0;
}

.stat-subtitle {
  color: #95a5a6;
  font-size: 12px;
}

.comment-section {
  margin-bottom: 20px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  border-left: 4px solid #667eea;
}

.comment-section label {
  font-weight: 600;
  margin-bottom: 8px;
  display: block;
  color: #2c3e50;
}

.comment-section textarea {
  width: 100%;
  padding: 10px;
  border-radius: 6px;
  border: 1px solid #ccc;
  font-family: inherit;
  resize: vertical;
  min-height: 80px;
}

.comment-section button {
  margin-top: 10px;
}

.section-card {
  background: white;
  border-radius: 12px;
  padding: 30px;
  margin-bottom: 30px;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.section-title {
  font-size: 24px;
  font-weight: 700;
  color: #2c3e50;
  margin-bottom: 25px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 3px solid #667eea;
  padding-bottom: 15px;
}

.section-title i {
  color: #667eea;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
  align-items: end;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  margin-bottom: 8px;
  color: #2c3e50;
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.form-group input,
.form-group select {
  padding: 12px 14px;
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  font-size: 14px;
  font-family: inherit;
  transition: all 0.3s ease;
  background: #f8f9fa;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #667eea;
  background: white;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn {
  padding: 12px 24px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
  height: 44px;
  align-items: center;
}

.btn-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4);
}

.btn-delete {
  background: #e74c3c;
  color: white;
  padding: 8px 14px;
  font-size: 12px;
  border-radius: 5px;
}

.btn-delete:hover {
  background: #c0392b;
}

.btn-comment {
  background: #3498db;
  color: white;
  padding: 8px 14px;
  font-size: 12px;
  border-radius: 5px;
}

.btn-comment:hover {
  background: #2980b9;
}

.table-wrapper {
  overflow-x: auto;
  margin-top: 15px;
}

table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

table thead {
  background: linear-gradient(90deg, #2c3e50, #34495e);
  color: white;
  position: sticky;
  top: 0;
}

table th {
  padding: 16px;
  text-align: left;
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

table td {
  padding: 14px 16px;
  border-bottom: 1px solid #ecf0f1;
  font-size: 14px;
}

table tbody tr {
  transition: all 0.2s ease;
}

table tbody tr:hover {
  background: #f8f9fa;
}

table tbody tr:nth-child(even) {
  background: #f9fafb;
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.badge-approved {
  background: #d4edda;
  color: #155724;
}

.badge-pending {
  background: #fff3cd;
  color: #856404;
}

.badge-rejected {
  background: #f8d7da;
  color: #721c24;
}

.amount {
  font-weight: 600;
  font-size: 15px;
}

.amount-paid {
  color: #27ae60;
}

.amount-due {
  color: #e74c3c;
}

.amount-total {
  color: #2c3e50;
}

.action-group {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.status-select {
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 5px;
  cursor: pointer;
  font-weight: 500;
}

.modal {
  display: none;
  position: fixed;
  z-index: 1;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  background-color: #fefefe;
  margin: 15% auto;
  padding: 20px;
  border: 1px solid #888;
  border-radius: 8px;
  width: 90%;
  max-width: 400px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.modal-close {
  color: #aaa;
  float: right;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
}

.modal-close:hover {
  color: black;
}

.modal textarea {
  width: 100%;
  padding: 10px;
  margin: 10px 0;
  border-radius: 5px;
  border: 1px solid #ddd;
  font-family: inherit;
  min-height: 100px;
}

.modal .btn {
  width: 100%;
  justify-content: center;
  margin-top: 10px;
}

@media (max-width: 768px) {
  .navbar {
    flex-direction: column;
    gap: 15px;
    padding: 15px;
  }

  .navbar-links {
    flex-direction: column;
    gap: 10px;
    width: 100%;
  }

  .navbar-links a {
    width: 100%;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .page-header h1 {
    font-size: 24px;
  }

  table {
    font-size: 12px;
  }

  table th, table td {
    padding: 10px;
  }

  .action-group {
    flex-direction: column;
  }

  .action-group .btn {
    width: 100%;
    justify-content: center;
  }

  .section-card {
    padding: 20px;
  }

  .stat-card {
    padding: 20px;
  }

  .table-wrapper {
    overflow-x: scroll;
  }
}

</style>
</head>
<body>

<nav class="navbar">
  <div class="navbar-brand">
    <i class="fas fa-graduation-cap"></i>
    Student Fee Manager
  </div>
  <div class="navbar-links">
    <a href="add.php"><i class="fas fa-user-plus"></i> Add Student</a>
    <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>
  <div class="navbar-user">
    <i class="fas fa-user-circle"></i> Admin
  </div>
</nav>

<div class="container">
  <div class="page-header">
    <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
    <p>Welcome back, Admin! Here's your system overview.</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-header">
        <div>
          <div class="stat-label">Total Students</div>
          <div class="stat-number"><?php echo $total_students; ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-users"></i></div>
      </div>
      <div class="stat-subtitle">Active enrolled students</div>
    </div>

    <div class="stat-card">
      <div class="stat-header">
        <div>
          <div class="stat-label">Total Collected</div>
          <div class="stat-number">₹<?php echo number_format($total_collected); ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
      </div>
      <div class="stat-subtitle">Approved payments only</div>
    </div>

    <div class="stat-card">
      <div class="stat-header">
        <div>
          <div class="stat-label">Pending Approvals</div>
          <div class="stat-number"><?php echo $pending_approvals; ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
      </div>
      <div class="stat-subtitle">Awaiting your review</div>
    </div>
  </div>

  <!-- Send Comment to All Section -->
  <div class="section-card">
    <h2 class="section-title"><i class="fas fa-comment"></i> Send Message to All Students</h2>
    <div class="comment-section">
      <form method="POST">
        <label>Message Content:</label>
        <textarea name="comment_all" required placeholder="Write message for all students..."></textarea>
        <button type="submit" name="send_all_comment" class="btn btn-primary">
          <i class="fas fa-paper-plane"></i> Send to All
        </button>
      </form>
    </div>
  </div>

  <!-- Student List Section -->
  <div class="section-card">
    <h2 class="section-title"><i class="fas fa-list"></i> Student List</h2>
    
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Reg No</th>
            <th>Username</th>
            <th>Faculty</th>
            <th>Batch</th>
            <th>Semester</th>
            <th>Total Fee</th>
            <th>Paid</th>
            <th>Remaining</th>
            <th>Proof</th>
            <th>Status</th>
            <th>Action</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
<?php
$result = $conn->query("
SELECT 
    u.reg_no,
    u.username,
    s.faculty,
    s.batch,
    s.semester,
    s.total_fee,

    COALESCE(paid.total_paid, 0) AS total_paid,

    lp.payment_id,
    lp.payment_proof,
    lp.status

FROM users u

LEFT JOIN student s 
    ON u.reg_no = s.reg_no

/* total paid subquery */
LEFT JOIN (
    SELECT reg_no, SUM(paid_installments) AS total_paid
    FROM payment
    WHERE status = 'approved'
    GROUP BY reg_no
) paid 
    ON paid.reg_no = u.reg_no

/* latest payment */
LEFT JOIN payment lp 
    ON lp.payment_id = (
        SELECT payment_id 
        FROM payment 
        WHERE reg_no = u.reg_no 
        ORDER BY payment_id DESC 
        LIMIT 1
    )

WHERE u.username != 'admin'

ORDER BY u.reg_no DESC
");

if($result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $reg = $row['reg_no'];
        $total = $row['total_fee'] ?? 0;
        $paid = $row['total_paid'] ?? 0;
        $remaining = $total - $paid;
        $status = $row['status'] ?? 'pending';

        $bg_color = match($status){
            'approved' => '#d4edda',
            'rejected' => '#f8d7da',
            'pending' => '#fff3cd',
            default => '#fff3cd',
        };
?>
<tr>
  <td><strong><?= htmlspecialchars($reg) ?></strong></td>
  <td>
    <a href="history record.php?reg_no=<?= htmlspecialchars($row['reg_no']) ?>" style="color:#667eea; font-weight:600; text-decoration:none;">
      <?= htmlspecialchars($row['username']) ?>
    </a>
  </td>
  <td><?= htmlspecialchars($row['faculty'] ?? '-') ?></td>
  <td><?= htmlspecialchars($row['batch'] ?? '-') ?></td>
  <td><?= htmlspecialchars($row['semester'] ?? '-') ?></td>
  <td><span class="amount amount-total">₹<?= number_format($total) ?></span></td>
  <td><span class="amount amount-paid">₹<?= number_format($paid) ?></span></td>
  <td><span class="amount amount-due">₹<?= number_format($remaining) ?></span></td>
  <td>
    <?php if(!empty($row['payment_proof'])){ ?>
      <a href="uploads/<?= htmlspecialchars($row['payment_proof']) ?>" target="_blank" title="View payment proof">
        <img src="uploads/<?= htmlspecialchars($row['payment_proof']) ?>" width="50" height="50" style="border-radius: 5px; object-fit: cover;">
      </a>
    <?php } else { ?>
      <span style="color: #999;">No Proof</span>
    <?php } ?>
  </td>
  <td>
    <form method="POST" style="display: inline;">
      <input type="hidden" name="payment_id" value="<?= htmlspecialchars($row['payment_id']) ?>">
      <select name="update_status" onchange="this.form.submit()" class="status-select" style="background-color:<?= $bg_color ?>;">
        <option value="approved" <?= $status=='approved'?'selected':'' ?>>Approved</option>
        <option value="pending" <?= $status=='pending'?'selected':'' ?>>Pending</option>
        <option value="rejected" <?= $status=='rejected'?'selected':'' ?>>Rejected</option>
      </select>
    </form>
  </td>
  <td>
    <button class="btn btn-comment" onclick="openCommentModal('<?= htmlspecialchars($reg) ?>', '<?= htmlspecialchars($row['reg_no']) ?>')">
      <i class="fas fa-comment-dots"></i> Comment
    </button>
  </td>
  <td>
    <a href="?delete=<?= htmlspecialchars($reg) ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.');">
      <i class="fas fa-trash"></i> Delete
    </a>
  </td>
</tr>

<!-- Comment Modal -->
<div id="modal_<?= htmlspecialchars($reg) ?>" class="modal">
  <div class="modal-content">
    <span class="modal-close" onclick="closeCommentModal('<?= htmlspecialchars($reg) ?>')">&times;</span>
    <h3 style="color: #2c3e50; margin-bottom: 15px;">Add Comment for <?= htmlspecialchars($row['username']) ?></h3>
    <form method="POST" action="">
      <input type="hidden" name="reg_no" value="<?= htmlspecialchars($row['reg_no']) ?>">
      <textarea name="comment" required placeholder="Write your comment here..." style="width:100%; padding:10px; border-radius:6px; border:1px solid #ddd; font-family: inherit; min-height: 100px;"></textarea>
      <button type="submit" name="send_comment" class="btn btn-primary" style="width:100%; margin-top:10px; justify-content:center;">
        <i class="fas fa-check"></i> Send Comment
      </button>
    </form>
  </div>
</div>

<?php
    }
}else{
    echo "<tr><td colspan='12' style='text-align:center; color:#999; padding: 30px;'><i class='fas fa-inbox'></i> No students found</td></tr>";
}
?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function openCommentModal(reg, regNo) {
  document.getElementById('modal_' + reg).style.display = 'block';
}

function closeCommentModal(reg) {
  document.getElementById('modal_' + reg).style.display = 'none';
}

window.onclick = function(event) {
  if (event.target.classList.contains('modal')) {
    event.target.style.display = 'none';
  }
}
</script>

</body>
</html>