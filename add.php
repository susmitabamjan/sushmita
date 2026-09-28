<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// DB Connection
$conn = new mysqli("localhost", "root", "", "payment_fee");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$messageType = "";

// normalize function
function normalizeFaculty($faculty) {
    $faculty = strtolower(trim($faculty));
    $faculty = str_replace([' ', '-', '_'], '', $faculty);
    return $faculty;
}

// installment function
function getInstallments($faculty) {
    return match ($faculty) {
        'diploma' => 12,
        'bteched' => 16,
        default => 1
    };
}

// ========================== EXCEL MODE ONLY ==========================
if (isset($_POST['submit'])) {

    if (!empty($_FILES['excel_file']['name'])) {

        try {
            $file = $_FILES['excel_file'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("File upload failed");
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls'])) {
                throw new Exception("Only Excel files allowed");
            }

            if (!is_uploaded_file($file['tmp_name'])) {
                throw new Exception("Invalid upload");
            }

            $spreadsheet = IOFactory::load($file['tmp_name']);
            $rows = $spreadsheet->getActiveSheet()->toArray();

            $success = 0;
            $fail = 0;

            foreach ($rows as $i => $row) {
                if ($i === 0) continue;

                $reg_no = trim($row[0] ?? '');
                $username = trim($row[1] ?? '');
                $password = trim($row[2] ?? '');
                $facultyRaw = $row[3] ?? '';
                $batch = trim($row[4] ?? '');
                $semester = trim($row[5] ?? '');
                $total_fee = floatval(str_replace(',', '', $row[6] ?? 0));

                if (!$reg_no || !$username || !$password) {
                    $fail++;
                    continue;
                }

                $faculty = normalizeFaculty($facultyRaw);
                $installments = getInstallments($faculty);
                $installment = ($installments > 0 && $total_fee > 0) ? $total_fee / $installments : 0;

                // duplicate check
                $chk = $conn->prepare("SELECT reg_no FROM users WHERE reg_no=?");
                $chk->bind_param("s", $reg_no);
                $chk->execute();
                $chk->store_result();

                if ($chk->num_rows > 0) {
                    $fail++;
                    $chk->close();
                    continue;
                }
                $chk->close();

                

                // Insert into users (NO HASH)
$u = $conn->prepare("INSERT INTO users (reg_no, username, password) VALUES (?, ?, ?)");
$u->bind_param("sss", $reg_no, $username, $password);

if (!$u->execute()) {
    $fail++;
    $u->close();
    continue;
}
$u->close();

// Insert into student
$s = $conn->prepare("INSERT INTO student (reg_no, faculty, batch, total_fee, installment, semester) VALUES (?, ?, ?, ?, ?, ?)");
$s->bind_param("sssdii", $reg_no, $faculty, $batch, $total_fee, $installment, $semester);

if ($s->execute()) $success++;
else $fail++;

$s->close();
            }

            $message = "Excel Import Completed: $success success, $fail failed";
            $messageType = "success";

        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = "error";
        }

    } else {
        $message = "Please upload an Excel file";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Students</title>
    <style>
      body {
    font-family: Arial, sans-serif;

    background: url('bgg.png') no-repeat center center fixed;
    background-size: cover;

    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}

        .container {
            max-width: 500px;
            margin: auto;
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.08);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #4e73df;
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #2e59d9;
        }

        .msg {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>

<div class="container">

    <h2>Upload Students</h2>

    <?php if ($message): ?>
        <div class="msg <?= $messageType ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <input type="file" name="excel_file" required>
        <small>Upload .xlsx or .xls file</small>

        <br><br>

        <button type="submit" name="submit">Upload</button>

    </form>
    <div style="background:#f1f1f1; padding:10px; border-radius:5px; margin-bottom:15px;">
    <strong>Excel File Format:</strong><br><br>
    <table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse; text-align:center;">
        <tr style="background:#4e73df; color:white;">
            <th>Reg. No.</th>
            <th>Username</th>
            <th>Password</th>
            <th>Faculty</th>
            <th>Batch</th>
            <th>Semester</th>
            <th>Total Fee</th>
        </tr>
        
    </table>
</div>

</div>

</body>
</html>