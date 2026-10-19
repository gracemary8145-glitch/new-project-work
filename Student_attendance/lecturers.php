
<?php
require_once "session_config.php";
require_once "db_connect.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: login.php");
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $error = "Invalid request. Refresh the page and try again.";
    } else {
        $name = trim($_POST["full_name"] ?? "");
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($name === "" || $username === "" || $password === "") {
            $error = "Please fill in all fields.";
        } elseif (strlen($name) > 100 || strlen($username) > 50) {
            $error = "Name or username is too long.";
        } elseif (!preg_match('/\A[A-Za-z0-9_.-]{3,50}\z/', $username)) {
            $error = "Username must be 3–50 characters using letters, numbers, dots, underscores or hyphens.";
        } elseif (strlen($password) < 12) {
            $error = "Use a password of at least 12 characters.";
        } else {
            $check = $conn->prepare(
                "SELECT id FROM users WHERE username = ? LIMIT 1"
            );
            $check->bind_param("s", $username);
            $check->execute();
            $existing = $check->get_result();

            if ($existing->num_rows > 0) {
                $error = "That username is already in use.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $role = "lecturer";

                $stmt = $conn->prepare(
                    "INSERT INTO users (full_name, username, password, role)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param("ssss", $name, $username, $hash, $role);

                if ($stmt->execute()) {
                    header("Location: lecturers.php?msg=created");
                    exit;
                } else {
                    $error = "Could not create the lecturer account.";
                    error_log("Lecturer creation failed: " . $stmt->error);
                }

                $stmt->close();
            }

            $check->close();
        }
    }
}

if (($_GET["msg"] ?? "") === "created") {
    $message = "Lecturer account created successfully.";
}

$lecturers = $conn->query(
    "SELECT id, full_name, username
     FROM users
     WHERE role = 'lecturer'
     ORDER BY full_name"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lecturer Management</title>
    
<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f3f7f4;
        color: #1f2937;
        line-height: 1.6;
    }

    header {
        background: #14532d;
        color: white;
        padding: 24px 5%;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.12);
    }

    header h1 {
        margin: 0 0 8px;
        font-size: 26px;
    }

    .back {
        display: inline-block;
        color: white;
        text-decoration: none;
        font-size: 14px;
        opacity: 0.95;
    }

    .back:hover {
        text-decoration: underline;
    }

    main {
        width: 92%;
        max-width: 1100px;
        margin: 30px auto;
    }

    .card {
        background: #ffffff;
        padding: 26px;
        margin-bottom: 24px;
        border-radius: 12px;
        border: 1px solid #e2e8e4;
        box-shadow: 0 4px 14px rgba(20, 83, 45, 0.06);
    }

    .card h2 {
        margin: 0 0 20px;
        color: #14532d;
        font-size: 21px;
    }

    label {
        display: block;
        margin: 16px 0 7px;
        font-weight: 600;
        font-size: 14px;
    }

    input {
        display: block;
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #cbd5ce;
        border-radius: 7px;
        background: #ffffff;
        color: #1f2937;
        font-size: 15px;
        outline: none;
    }

    input:focus {
        border-color: #15803d;
        box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.12);
    }

    button {
        background: #166534;
        color: white;
        border: none;
        border-radius: 7px;
        padding: 12px 20px;
        margin-top: 20px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    button:hover {
        background: #14532d;
    }

    .success {
        padding: 12px 14px;
        color: #166534;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 7px;
    }

    .error {
        padding: 12px 14px;
        color: #b91c1c;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 7px;
    }

    .card p {
        font-size: 14px;
        color: #64748b;
    }

    .table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 450px;
    }

    th {
        background: #f0fdf4;
        color: #14532d;
        font-weight: 700;
    }

    th,
    td {
        text-align: left;
        padding: 14px 12px;
        border-bottom: 1px solid #e5e7eb;
    }

    tbody tr:hover {
        background: #f8faf9;
    }

    tbody tr:last-child td {
        border-bottom: none;
    }

    @media (max-width: 600px) {
        header {
            padding: 20px;
        }

        header h1 {
            font-size: 22px;
        }

        main {
            width: 94%;
            margin: 20px auto;
        }

        .card {
            padding: 18px;
        }
    }
</style>
</head>
<body>
<header>
    <h1>Lecturer Management</h1>
    <a class="back" href="dashboard.php">Back to Dashboard</a>
</header>

<main>
    <section class="card">
        <h2>Create Lecturer Account</h2>

        <?php if ($message !== ""): ?>
            <p class="success"><?= h($message) ?></p>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <p class="error"><?= h($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token"
                   value="<?= h($_SESSION["csrf_token"]) ?>">

            <label for="full_name">Lecturer's Full Name</label>
            <input id="full_name" name="full_name"
                   maxlength="100" required>

            <label for="username">Username</label>
            <input id="username" name="username"
                   minlength="3" maxlength="50"
                   autocomplete="off" required>

            <label for="password">Temporary Password (minimum 12 characters)</label>
            <input id="password" name="password" type="password"
                   minlength="12" autocomplete="new-password" required>

            <button type="submit">Create Lecturer</button>
        </form>
        <p>Give the lecturer their login details privately. Never share passwords publicly.</p>
    </section>

    <section class="card">
        <h2>Registered Lecturers</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($lecturers->num_rows > 0): ?>
                    <?php while ($lecturer = $lecturers->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$lecturer["id"] ?></td>
                            <td><?= h($lecturer["full_name"]) ?></td>
                            <td><?= h($lecturer["username"]) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3">No lecturer accounts yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
<?php $conn->close(); ?>
