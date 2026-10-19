
<?php
require_once "session_config.php";
require_once "db_connect.php";

// Only administrators may manage student records.
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: login.php");
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Create a CSRF token for form protection.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$error = "";

// Register a new student.
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && ($_POST["action"] ?? "") === "add") {

    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        $error = "Invalid form submission. Please try again.";
    } else {
        $matric = trim($_POST["matric_number"] ?? "");
        $name = trim($_POST["full_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $department = trim($_POST["department"] ?? "");
        $level = trim($_POST["level"] ?? "");

        if ($matric === "" || $name === "") {
            $error = "Matric number and full name are required.";
        } elseif (strlen($matric) > 30 || strlen($name) > 100
            || strlen($email) > 100 || strlen($department) > 100
            || strlen($level) > 20) {
            $error = "One or more fields exceed the allowed length.";
        } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO students
                (matric_number, full_name, email, department, level)
                VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''))"
            );

            $stmt->bind_param(
                "sssss", $matric, $name, $email, $department, $level
            );

            if ($stmt->execute()) {
                header("Location: students.php?msg=added");
                exit;
            } else {
                if ($stmt->errno === 1062) {
                    $error = "That matriculation number already exists.";
                } else {
                    $error = "Could not register the student. Please try again.";
                    error_log("Student registration failed: " . $stmt->error);
                }
            }

            $stmt->close();
        }
    }
}

if (($_GET["msg"] ?? "") === "added") {
    $message = "Student registered successfully.";
} elseif (($_GET["msg"] ?? "") === "updated") {
    $message = "Student details updated successfully.";
} elseif (($_GET["msg"] ?? "") === "deleted") {
    $message = "Student deleted successfully.";
}

// Search students by name or matriculation number.
$search = trim($_GET["search"] ?? "");

if ($search !== "") {
    $term = "%" . $search . "%";
    $stmt = $conn->prepare(
        "SELECT id, matric_number, full_name, email, department, level
         FROM students
         WHERE matric_number LIKE ? OR full_name LIKE ?
         ORDER BY full_name"
    );
    $stmt->bind_param("ss", $term, $term);
    $stmt->execute();
    $students = $stmt->get_result();
} else {
    $students = $conn->query(
        "SELECT id, matric_number, full_name, email, department, level
         FROM students ORDER BY full_name"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Management</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }
        header {
            background: #123b66;
            color: white;
            padding: 20px;
        }
        main { max-width: 1150px; margin: 25px auto; padding: 16px; }
        .card {
            background: white;
            padding: 22px;
            margin-bottom: 22px;
            border-radius: 10px;
            box-shadow: 0 3px 12px #0000000c;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        label { display: block; margin-bottom: 6px; }
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
        }
        button, .button {
            display: inline-block;
            padding: 10px 15px;
            border: 0;
            border-radius: 5px;
            background: #123b66;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        .success { color: #166534; }
        .error { color: #b91c1c; }
        .search { display: flex; gap: 8px; margin-bottom: 16px; }
        .search input { flex: 1; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            text-align: left;
            padding: 11px;
            border-bottom: 1px solid #e2e8f0;
        }
        th { background: #f8fafc; }
        .actions { white-space: nowrap; }
        .actions a { margin-right: 8px; }
        .back { color: white; }
        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header>
    <h1>Student Management</h1>
    <a class="back" href="dashboard.php">Back to Dashboard</a>
</header>

<main>
    <section class="card">
        <h2>Register New Student</h2>

        <?php if ($message !== ""): ?>
            <p class="success"><?= h($message) ?></p>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <p class="error"><?= h($error) ?></p>
        <?php endif; ?>

        <form method="post" action="students.php">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token"
                   value="<?= h($_SESSION["csrf_token"]) ?>">

            <div class="form-grid">
                <div>
                    <label for="matric_number">Matriculation Number *</label>
                    <input id="matric_number" name="matric_number"
                           maxlength="30" required>
                </div>
                <div>
                    <label for="full_name">Full Name *</label>
                    <input id="full_name" name="full_name"
                           maxlength="100" required>
                </div>
                <div>
                    <label for="email">Email Address</label>
                    <input id="email" name="email" type="email"
                           maxlength="100">
                </div>
                <div>
                    <label for="department">Department</label>
                    <input id="department" name="department"
                           maxlength="100">
                </div>
                <div>
                    <label for="level">Level</label>
                    <input id="level" name="level"
                           placeholder="e.g. 100 Level" maxlength="20">
                </div>
            </div>
            <p><button type="submit">Register Student</button></p>
        </form>
    </section>

    <section class="card">
        <h2>Registered Students</h2>

        <form class="search" method="get" action="students.php">
            <input name="search" value="<?= h($search) ?>"
                   placeholder="Search by name or matric number">
            <button type="submit">Search</button>
            <a class="button" href="students.php">Clear</a>
        </form>

        <p>Total matching records: <?= $students->num_rows ?></p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Matric No.</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Level</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($students->num_rows > 0): ?>
                    <?php while ($student = $students->fetch_assoc()): ?>
                    <tr>
                        <td><?= h($student["matric_number"]) ?></td>
                        <td><?= h($student["full_name"]) ?></td>
                        <td><?= h($student["email"] ?? "") ?></td>
                        <td><?= h($student["department"] ?? "") ?></td>
                        <td><?= h($student["level"] ?? "") ?></td>
                        <td class="actions">
                            <a href="edit_student.php?id=<?= (int)$student["id"] ?>">Edit</a>
                            <a href="delete_student.php?id=<?= (int)$student["id"] ?>">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No student records found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
<?php
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
$conn->close();
?>
