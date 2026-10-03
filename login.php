<?php
session_start();
require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    if (empty($username) || empty($password)) {
        $error = "Please enter your username and password.";
    } else {

        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && md5($password) === $user["password"]) {

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["role"] = $user["role"];

            header("Location: dashboard.php");
            exit();

        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Church Attendance System - Login</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background:
                linear-gradient(135deg, #101820, #1c2833, #34495e);
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
        }

        .logo {
            width: 75px;
            height: 75px;
            margin: 0 auto 20px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #1c2833;
            color: white;

            font-size: 32px;
        }

        .title {
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            color: #1c2833;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #777;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 14px 15px;

            border: 1px solid #ddd;
            border-radius: 10px;

            font-size: 15px;
            outline: none;

            transition: 0.3s;
        }

        input:focus {
            border-color: #34495e;
            box-shadow: 0 0 0 3px rgba(52, 73, 94, 0.12);
        }

        .login-btn {
            width: 100%;
            padding: 14px;

            border: none;
            border-radius: 10px;

            background: #1c2833;
            color: white;

            font-size: 16px;
            font-weight: bold;

            cursor: pointer;
            transition: 0.3s;
        }

        .login-btn:hover {
            background: #34495e;
            transform: translateY(-1px);
        }

        .error {
            background: #ffe5e5;
            color: #b42318;

            padding: 12px;
            border-radius: 8px;

            margin-bottom: 20px;

            text-align: center;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            color: #888;
            font-size: 12px;
        }

    </style>
</head>

<body>

<div class="login-container">

    <div class="login-card">

        <div class="logo">
            ⛪
        </div>

        <h1 class="title">
            Church Attendance
        </h1>

        <p class="subtitle">
            Attendance & Member Monitoring System
        </p>

        <?php if (!empty($error)): ?>

            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    required
                    autocomplete="username"
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >

            </div>

            <button type="submit" class="login-btn">
                Sign In
            </button>

        </form>

        <div class="footer">
            Church Attendance Monitoring System
        </div>

    </div>

</div>

</body>
</html>