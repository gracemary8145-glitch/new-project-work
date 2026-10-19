

   <?php
   require_once "session_config.php";
   require_once "db_connect.php";
   

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare(
        "SELECT id, full_name, username, password, role
         FROM users WHERE username = ? LIMIT 1"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user["password"])) {
        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["full_name"] = $user["full_name"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];

        header("Location: dashboard.php");
        exit;
    }

    $error = "Invalid username or password.";
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Student Attendance System</title>
    <style>
        
body {
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #eaf2ee, #f3f6fa);
    color: #173b2b;
    margin: 0;
    min-height: 100vh;
    padding: 30px 16px;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-box {
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
    background: #ffffff;
    padding: 35px;
    border-radius: 16px;
    box-shadow: 0 12px 35px rgba(23, 59, 43, 0.12);
    box-sizing: border-box;
    border-top: 5px solid #198754;
}

.login-box h1,
.login-box h2 {
    color: #173b2b;
    text-align: center;
    margin-top: 0;
    margin-bottom: 10px;
}

.login-box p {
    color: #64748b;
    text-align: center;
    line-height: 1.6;
    margin-bottom: 25px;
}

.login-box label {
    display: block;
    margin: 14px 0 7px;
    font-weight: 600;
    color: #334155;
}

input {
    display: block;
    width: 100%;
    padding: 13px;
    margin: 0 0 16px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-sizing: border-box;
    font-size: 15px;
    outline: none;
}

input:focus {
    border-color: #198754;
    box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.12);
}

button {
    width: 100%;
    padding: 13px;
    margin-top: 8px;
    background: #173b2b;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.2s ease;
}

button:hover {
    background: #198754;
}

.error {
    color: #b91c1c;
    background: #fef2f2;
    border: 1px solid #fecaca;
    padding: 10px;
    border-radius: 7px;
    margin-bottom: 15px;
}

@media (max-width: 480px) {
    body {
        padding: 20px 12px;
    }

    .login-box {
        padding: 25px 20px;
    }
}

.login-footer {
    position: fixed;
    bottom: 15px;
    left: 0;
    width: 100%;
    text-align: center;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
    padding: 0 12px;
    box-sizing: border-box;
}

.login-footer p {
    margin: 4px 0 0;
}

@media (max-height: 500px) {
    .login-footer {
        position: static;
        margin-top: 25px;
    }
}
.login-icon {
    display: flex;
    justify-content: center;
    align-items: center;
    color: #198754;
    margin-bottom: 12px;
}

    </style>
</head>
<body>
    <div class="login-box">
    <div class="login-icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg"
             width="42" height="42" viewBox="0 0 24 24"
             fill="none" stroke="currentColor"
             stroke-width="1.8" stroke-linecap="round"
             stroke-linejoin="round">
            <rect x="3" y="5" width="18" height="16" rx="2"/>
            <path d="M16 3v4M8 3v4M3 10h18"/>
            <path d="m8 15 2.5 2.5L16 12"/>
        </svg>
    </div>

    
        <p>Sign in to manage student attendance.</p>

        <?php if ($error !== ""): ?>
            <p class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </p>
        <?php endif; ?>

        <form method="post" action="login.php">
            <label for="username">Username</label>
            <input id="username" name="username"
                   autocomplete="username" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password"
                   autocomplete="current-password" required>

            <button type="submit">Log In</button>
        </form>
    </div>
    
<footer class="login-footer">
    Student Attendance Management System
    <p>Secure access for administrators and lecturers.</p>
</footer>

</body>
</html>
