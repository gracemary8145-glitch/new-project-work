
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
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f1f5f9;
        color: #173b2b;
    }

    header {
        background: #173b2b;
        color: white;
        padding: 22px 5%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        box-shadow: 0 3px 12px #00000012;
    }

    header h1 {
        margin: 0 0 10px;
        font-size: 26px;
    }

    .back {
        display: inline-block;
        color: white;
        text-decoration: none;
        border: 1px solid #ffffff80;
        border-radius: 6px;
        padding: 10px 14px;
    }

    .back:hover {
        background: #ffffff18;
    }

    main {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
    }

    .card {
        background: white;
        padding: 25px;
        margin-bottom: 24px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 4px 14px #00000008;
    }

    .card h2 {
        margin: 0 0 22px;
        font-size: 21px;
        color: #173b2b;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #334155;
    }

    input {
        width: 100%;
        min-height: 43px;
        padding: 11px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 7px;
        background: #fff;
        color: #173b2b;
        font: inherit;
    }

    input:focus {
        outline: none;
        border-color: #168653;
        box-shadow: 0 0 0 3px #16865320;
    }

    button,
    .button {
        display: inline-block;
        padding: 11px 17px;
        border: 0;
        border-radius: 7px;
        background: #173b2b;
        color: white;
        font: inherit;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    button:hover,
    .button:hover {
        background: #246344;
    }

    .success,
    .error {
        padding: 12px 14px;
        border-radius: 7px;
        margin-bottom: 18px;
        font-size: 14px;
    }

    .success {
        color: #166534;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
    }

    .error {
        color: #991b1b;
        background: #fef2f2;
        border: 1px solid #fecaca;
    }

    .search {
        display: flex;
        gap: 10px;
        margin-bottom: 18px;
        align-items: center;
    }

    .search input {
        flex: 1;
        min-width: 0;
    }

    .clear-button {
        background: #64748b;
    }

    .clear-button:hover {
        background: #475569;
    }

    .record-count {
        color: #64748b;
        font-size: 14px;
        margin: 0 0 15px;
    }

    .table-wrap {
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }

    th,
    td {
        text-align: left;
        padding: 14px 12px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 14px;
    }

    th {
        background: #f0f7f3;
        color: #173b2b;
        font-weight: 700;
        white-space: nowrap;
    }

    tbody tr:hover {
        background: #f8fbf9;
    }

    tbody tr:last-child td {
        border-bottom: none;
    }

    .actions {
        white-space: nowrap;
    }

    .actions a {
        display: inline-block;
        margin-right: 8px;
        color: #166534;
        font-weight: 600;
        text-decoration: none;
    }

    .actions a:hover {
        text-decoration: underline;
    }

    @media (max-width: 650px) {
        header {
            padding: 18px 20px;
        }

        header h1 {
            font-size: 22px;
        }

        main {
            margin: 20px auto;
            padding: 0 12px;
        }

        .card {
            padding: 18px;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .search {
            flex-wrap: wrap;
        }

        .search input {
            flex-basis: 100%;
        }
    }
</style>

</head>
<body>
<header>
    <div>
        <h1>Student Attendance Management System</h1>
        <p>Student Management Portal</p>
    </div>
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
            <a class="button clear-button" href="students.php">Clear</a>
        </form>

<p class="record-count">
    Total matching records: <?= $students->num_rows ?>
</p>
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
