<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli("localhost", "root", "", "payment_fee");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

if (isset($_POST['login'])) {

    $reg_no = trim($_POST['reg_no']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // 🔹 Admin Login (hardcoded)
    if ($username === "Admin" && $password === "admin123" && $reg_no === "admin123") {

        $_SESSION['reg_no'] = $reg_no;
        $_SESSION['username'] = $username;

        header("Location: admin.php");
        exit();
    }

    // 🔹 Student Login (no role)
    $stmt = $conn->prepare("SELECT * FROM users WHERE reg_no=? AND username=?");
    $stmt->bind_param("ss", $reg_no, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $row = $result->fetch_assoc();

        if ($password === $row['password']) {

            $_SESSION['reg_no'] = $row['reg_no'];
            $_SESSION['username'] = $row['username'];

            header("Location: student.php");
            exit();

        } else {
            $error = "Wrong Password!";
        }

    } else {
        $error = "User not found!";
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        body{
            display:flex;
            justify-content:center;
            align-items:center;
            width:100%;
            height:100vh;
            margin:0;
            background: url('bg.jpeg') no-repeat center center;
            background-size: cover;
            font-family: Arial;
        }
        .login-box{
            background: rgba(50, 25, 237, 0.5);
            text-align: center;
            padding:30px;
            border-radius:15px;
            width:300px;
            color:white;
            box-shadow:0 5px 15px rgba(132, 26, 26, 0.3);
        }
        .login-box input,
        .login-box select,
        .login-box button{
            width:100%;
            padding:10px;
            margin:10px 0;
            border-radius:5px;
            border:1px solid #ccc;
            outline:none;
        }
        .login-box button{
            background:#4e73df;
            color:white;
            border:none;
            cursor:pointer;
        }
        .login-box button:hover{
            background:#2e59d9;
        }
        .error{
            color:red;
            font-size:14px;
            text-align:center;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h2>Login</h2>
    <form method="POST">
        <input type="text" name="reg_no" placeholder="Registration No" required>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>

        
        <button type="submit" name="login">Login</button>
    </form>

    <?php if(!empty($error)) echo "<div class='error'>$error</div>"; ?>
</div>

</body>
</html>