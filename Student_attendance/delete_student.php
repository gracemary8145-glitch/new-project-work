
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

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header("Location: students.php");
    exit;
}

$stmt = $conn->prepare(
    "SELECT matric_number, full_name FROM students WHERE id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    $conn->close();
    exit("Student record not found.");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        $error = "Invalid request. Please try again.";
    } else {
        $delete = $conn->prepare("DELETE FROM students WHERE id = ?");
        $delete->bind_param("i", $id);

        if ($delete->execute()) {
            $delete->close();
            $conn->close();
            header("Location: students.php?msg=deleted");
            exit;
        }

        // Attendance records may reference this student.
        if ($delete->errno === 1451) {
            $error = "This student has attendance records. You cannot delete the student until those records are handled.";
        } else {
            $error = "Could not delete the student record.";
            error_log("Student deletion failed: " . $delete->error);
        }
        $delete->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm Student Deletion</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; padding: 20px; }
        main { max-width: 550px; margin: 50px auto; background: white; padding: 25px; border-radius: 10px; }
        .danger { background: #b91c1c; color: white; border: 0; padding: 11px 16px; cursor: pointer; }
        .cancel { margin-left: 10px; }
        .error { color: #b91c1c; }
    </style>
</head>
<body>
<main>
    <h1>Confirm Student Deletion</h1>

    <?php if ($error !== ""): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <p>Are you sure you want to delete this student?</p>
    <p><strong>Name:</strong> <?= h($student["full_name"]) ?></p>
    <p><strong>Matric Number:</strong> <?= h($student["matric_number"]) ?></p>

    <form method="post">
        <input type="hidden" name="csrf_token"
               value="<?= h($_SESSION["csrf_token"]) ?>">
        <button class="danger" type="submit">Confirm Delete</button>
        <a class="cancel" href="students.php">Cancel</a>
    </form>
</main>
</body>
</html>
<?php $conn->close(); ?>
