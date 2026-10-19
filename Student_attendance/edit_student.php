
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

$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
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
            $error = "One or more fields are too long.";
        } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            $update = $conn->prepare(
                "UPDATE students
                 SET matric_number = ?, full_name = ?,
                     email = NULLIF(?, ''),
                     department = NULLIF(?, ''),
                     level = NULLIF(?, '')
                 WHERE id = ?"
            );
            $update->bind_param(
                "sssssi", $matric, $name, $email,
                $department, $level, $id
            );

            if ($update->execute()) {
                $update->close();
                $conn->close();
                header("Location: students.php?msg=updated");
                exit;
            } elseif ($update->errno === 1062) {
                $error = "That matriculation number is already in use.";
            } else {
                $error = "Could not update the student record.";
                error_log("Student update failed: " . $update->error);
            }
            $update->close();

            // Preserve submitted values when validation or update fails.
            $student["matric_number"] = $matric;
            $student["full_name"] = $name;
            $student["email"] = $email;
            $student["department"] = $department;
            $student["level"] = $level;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Student</title>
    
<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 30px 18px;
        font-family: Arial, Helvetica, sans-serif;
        background: #f3f7f4;
        color: #24332b;
        min-height: 100vh;
    }

    main {
        width: 100%;
        max-width: 650px;
        margin: 25px auto;
        padding: 35px;
        background: #ffffff;
        border: 1px solid #e1eae3;
        border-radius: 14px;
        box-shadow: 0 8px 25px rgba(20, 65, 40, 0.08);
    }

    h1 {
        margin: 0 0 8px;
        color: #14532d;
        font-size: 28px;
        font-weight: 700;
    }

    .subtitle {
        margin: 0 0 28px;
        color: #64746a;
        font-size: 14px;
        line-height: 1.6;
    }

    label {
        display: block;
        margin-top: 18px;
        color: #344b3b;
        font-size: 14px;
        font-weight: 600;
    }

    input {
        display: block;
        width: 100%;
        margin-top: 8px;
        padding: 12px 14px;
        border: 1px solid #d3dfd6;
        border-radius: 7px;
        background: #fcfdfc;
        color: #24332b;
        font-family: inherit;
        font-size: 14px;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    input:focus {
        outline: none;
        border-color: #198754;
        box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.12);
        background: #ffffff;
    }

    .error {
        padding: 12px 14px;
        border: 1px solid #fecaca;
        border-radius: 7px;
        background: #fef2f2;
        color: #b91c1c;
        font-size: 14px;
        line-height: 1.5;
    }

    .form-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 28px;
    }

    button {
        padding: 12px 20px;
        border: none;
        border-radius: 7px;
        background: #166534;
        color: #ffffff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s, transform 0.2s;
    }

    button:hover {
        background: #14532d;
        transform: translateY(-1px);
    }

    .cancel-link {
        display: inline-block;
        padding: 11px 16px;
        border: 1px solid #d3dfd6;
        border-radius: 7px;
        color: #365443;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.2s;
    }

    .cancel-link:hover {
        background: #f0f7f1;
    }

    .required-note {
        margin-top: 22px;
        color: #718078;
        font-size: 12px;
    }

    @media (max-width: 520px) {
        body {
            padding: 12px;
        }

        main {
            margin: 12px auto;
            padding: 23px 18px;
        }

        h1 {
            font-size: 24px;
        }

        .form-actions {
            align-items: stretch;
            flex-direction: column;
        }

        button,
        .cancel-link {
            width: 100%;
            text-align: center;
        }
    }
</style>
</head>
<body>
<main>
<h1>Edit Student</h1>
<p class="subtitle">Update the student's information using the form below.</p>
    <?php if ($error !== ""): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token"
               value="<?= h($_SESSION["csrf_token"]) ?>">

        <label for="matric_number">Matriculation Number *</label>
        <input id="matric_number" name="matric_number"
               maxlength="30" required
               value="<?= h($student["matric_number"]) ?>">

        <label for="full_name">Full Name *</label>
        <input id="full_name" name="full_name"
               maxlength="100" required
               value="<?= h($student["full_name"]) ?>">

        <label for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="100"
               value="<?= h($student["email"] ?? "") ?>">

        <label for="department">Department</label>
        <input id="department" name="department" maxlength="100"
               value="<?= h($student["department"] ?? "") ?>">

        <label for="level">Level</label>
        <input id="level" name="level" maxlength="20"
               value="<?= h($student["level"] ?? "") ?>">

        <div class="form-actions">
    <button type="submit">Save Changes</button>
    <a class="cancel-link" href="students.php">Cancel</a>
</div>

<p class="required-note">* Required fields</p>
    </form>
</main>
</body>
</html>
<?php $conn->close(); ?>
