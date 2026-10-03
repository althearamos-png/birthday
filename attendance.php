<?php

session_start();
require_once "config/database.php";

/* =========================================================
   PROTECT PAGE
   ========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$error = "";


/* =========================================================
   SELECT DATE
   ========================================================= */

$selectedDate =
    $_POST["attendance_date"]
    ?? $_GET["date"]
    ?? date("Y-m-d");


/* =========================================================
   CHECK IF DATE IS SUNDAY
   ========================================================= */

$dayOfWeek = date("w", strtotime($selectedDate));


/* =========================================================
   SAVE ATTENDANCE
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["save_attendance"])
) {

    if ($dayOfWeek != 0) {

        $error = "Please select a Sunday.";

    } else {

        try {

            $conn->beginTransaction();


            /* Get all active members */

            $stmt = $conn->query("
                SELECT id
                FROM members
                WHERE status = 'Active'
                ORDER BY last_name ASC
            ");

            $activeMembers =
                $stmt->fetchAll(PDO::FETCH_COLUMN);


            /* Save attendance for every active member */

            foreach ($activeMembers as $memberId) {

                /* Get selected status */

                $status =
                    $_POST["status"][$memberId]
                    ?? "Absent";


                /* Validate status */

                if (
                    $status !== "Present"
                    && $status !== "Absent"
                ) {

                    $status = "Absent";

                }


                /* Check if attendance already exists */

                $stmt = $conn->prepare("
                    SELECT id
                    FROM attendance
                    WHERE member_id = ?
                    AND attendance_date = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $memberId,
                    $selectedDate
                ]);

                $existingId =
                    $stmt->fetchColumn();


                if ($existingId) {

                    /* UPDATE existing attendance */

                    $stmt = $conn->prepare("
                        UPDATE attendance
                        SET status = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $status,
                        $existingId
                    ]);

                } else {

                    /* INSERT new attendance */

                    $stmt = $conn->prepare("
                        INSERT INTO attendance
                        (
                            member_id,
                            attendance_date,
                            status
                        )
                        VALUES (?, ?, ?)
                    ");

                    $stmt->execute([
                        $memberId,
                        $selectedDate,
                        $status
                    ]);

                }

            }


            $conn->commit();

            $message =
                "Attendance saved successfully!";


        } catch (PDOException $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error =
                "Database Error: "
                . $e->getMessage();

        }

    }

}


/* =========================================================
   GET ACTIVE MEMBERS
   ========================================================= */

$stmt = $conn->query("
    SELECT *
    FROM members
    WHERE status = 'Active'
    ORDER BY last_name ASC, first_name ASC
");

$members =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   GET EXISTING ATTENDANCE
   ========================================================= */

$attendance = [];

$stmt = $conn->prepare("
    SELECT
        member_id,
        status
    FROM attendance
    WHERE attendance_date = ?
");

$stmt->execute([
    $selectedDate
]);

$records =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


foreach ($records as $record) {

    $attendance[
        $record["member_id"]
    ] =
        $record["status"];

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"


<title>
    Sunday Attendance | Church Attendance
</title>


<style>

/* =========================================================
   GENERAL
   ========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

body {
    background: #f4f6f8;
    color: #17212b;
}


/* =========================================================
   SIDEBAR
   ========================================================= */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;

    width: 240px;
    height: 100vh;

    background: #17212b;
    color: white;

    padding: 25px 15px;
}

.brand {
    text-align: center;
    margin-bottom: 35px;
}

.brand-icon {
    font-size: 35px;
}

.brand h2 {
    font-size: 18px;
    margin-top: 8px;
}

.brand p {
    font-size: 11px;
    color: #aeb8c2;
    margin-top: 5px;
}

.menu {
    list-style: none;
}

.menu li {
    margin-bottom: 8px;
}

.menu a {
    display: block;
    padding: 13px 15px;

    border-radius: 8px;

    color: #dbe3ea;

    text-decoration: none;

    transition: 0.2s;
}

.menu a:hover,
.menu a.active {
    background: #2d3e4d;
    color: white;
}

.logout {
    position: absolute;

    bottom: 25px;

    left: 15px;
    right: 15px;
}

.logout a {
    display: block;

    padding: 12px;

    text-align: center;

    border-radius: 8px;

    background: #263746;

    color: white;

    text-decoration: none;
}

.logout a:hover {
    background: #34495e;
}


/* =========================================================
   MAIN
   ========================================================= */

.main {
    margin-left: 240px;
    padding: 30px;
}


/* =========================================================
   HEADER
   ========================================================= */

.header {
    margin-bottom: 25px;
}

.header h1 {
    font-size: 28px;
}

.header p {
    color: #6b7280;
    margin-top: 6px;
}


/* =========================================================
   DATE CARD
   ========================================================= */

.date-card {
    background: white;

    padding: 20px;

    border-radius: 12px;

    margin-bottom: 20px;

    box-shadow:
        0 3px 12px rgba(0,0,0,0.05);
}

.date-form {
    display: flex;

    align-items: end;

    gap: 12px;

    flex-wrap: wrap;
}

.date-form label {
    display: block;

    font-size: 13px;

    font-weight: bold;

    margin-bottom: 7px;
}

.date-form input {
    padding: 11px;

    border: 1px solid #ddd;

    border-radius: 8px;
}

.date-form button {
    padding: 11px 18px;

    border: none;

    border-radius: 8px;

    background: #34495e;

    color: white;

    cursor: pointer;

    font-weight: bold;
}


/* =========================================================
   ALERTS
   ========================================================= */

.alert {
    padding: 13px;

    border-radius: 8px;

    margin-bottom: 20px;

    background: #dcfce7;

    color: #166534;
}

.error {
    padding: 13px;

    border-radius: 8px;

    margin-bottom: 20px;

    background: #fee2e2;

    color: #991b1b;

    word-break: break-word;
}


/* =========================================================
   ATTENDANCE CARD
   ========================================================= */

.attendance-card {
    background: white;

    border-radius: 14px;

    padding: 20px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.06);
}

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 700px;
}

th {
    text-align: left;

    padding: 14px;

    background: #fafbfc;

    color: #6b7280;

    font-size: 12px;

    border-bottom:
        1px solid #e5e7eb;
}

td {
    padding: 14px;

    border-bottom:
        1px solid #f0f1f2;

    font-size: 14px;
}

tr:hover {
    background: #f8fafc;
}

.member-code {
    font-weight: bold;

    color: #34495e;
}


/* =========================================================
   STATUS BUTTONS
   ========================================================= */

.status-options {
    display: flex;

    gap: 8px;
}

.status-options label {
    cursor: pointer;
}

.status-options input {
    display: none;
}

.present-label,
.absent-label {
    display: inline-block;

    padding: 8px 12px;

    border-radius: 7px;

    font-size: 12px;

    font-weight: bold;

    cursor: pointer;
}

.present-label {
    background: #ecfdf5;

    color: #166534;

    border: 1px solid #bbf7d0;
}

.absent-label {
    background: #fef2f2;

    color: #991b1b;

    border: 1px solid #fecaca;
}


/* SELECTED PRESENT */

.status-options
input[value="Present"]:checked
+ .present-label {

    background: #16a34a;

    color: white;
}


/* SELECTED ABSENT */

.status-options
input[value="Absent"]:checked
+ .absent-label {

    background: #dc2626;

    color: white;
}


/* =========================================================
   SAVE BUTTON
   ========================================================= */

.save-container {
    margin-top: 20px;

    display: flex;

    justify-content: flex-end;
}

.save-btn {
    padding: 13px 25px;

    border: none;

    border-radius: 9px;

    background: #17212b;

    color: white;

    font-weight: bold;

    cursor: pointer;
}

.save-btn:hover {
    background: #34495e;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media(max-width: 800px) {

    .sidebar {
        position: relative;

        width: 100%;

        height: auto;
    }

    .logout {
        position: relative;

        left: auto;
        right: auto;
        bottom: auto;

        margin-top: 20px;
    }

    .main {
        margin-left: 0;

        padding: 20px;
    }

    .status-options {
        flex-direction: column;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
     ========================================================= -->

<aside class="sidebar">

    <div class="brand">

        <div class="brand-icon">
            ⛪
        </div>

        <h2>
            Church Attendance
        </h2>

        <p>
            Monitoring System
        </p>

    </div>


    <ul class="menu">

        <li>
            <a href="dashboard.php">
                🏠 Dashboard
            </a>
        </li>

        <li>
            <a href="members.php">
                👥 Members
            </a>
        </li>

        <li>
            <a
                href="attendance.php"
                class="active"
            >
                📅 Sunday Attendance
            </a>
        </li>

        <li>
            <a href="monthly_report.php">
                📊 Monthly Report
            </a>
        </li>

    </ul>


    <div class="logout">

        <a href="logout.php">
            🚪 Logout
        </a>

    </div>

</aside>


<!-- =========================================================
     MAIN CONTENT
     ========================================================= -->

<main class="main">


    <div class="header">

        <h1>
            Sunday Attendance
        </h1>

        <p>
            Record attendance for every Sunday.
        </p>

    </div>


    <!-- SUCCESS MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="alert">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- ERROR MESSAGE -->

    <?php if ($error !== ""): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- DATE SELECTOR -->

    <div class="date-card">

        <form
            method="GET"
            class="date-form"
        >

            <div>

                <label for="attendance_date">
                    Select Sunday
                </label>

                <input
                    type="date"
                    id="attendance_date"
                    name="date"
                    value="<?= htmlspecialchars($selectedDate) ?>"
                    required
                >

            </div>


            <button type="submit">
                Load Attendance
            </button>

        </form>

    </div>


    <!-- ATTENDANCE FORM -->

    <?php if ($dayOfWeek == 0): ?>

        <form
            method="POST"
            class="attendance-card"
        >

            <input
                type="hidden"
                name="attendance_date"
                value="<?= htmlspecialchars($selectedDate) ?>"
            >


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>Member ID</th>

                            <th>Name</th>

                            <th>Ministry</th>

                            <th>Attendance</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (count($members) > 0): ?>


                        <?php foreach ($members as $member): ?>


                            <?php

                            $memberId =
                                $member["id"];

                            $memberCode =
                                $member["member_code"];

                            $currentStatus =
                                $attendance[$memberId]
                                ?? "";

                            ?>


                            <tr>


                                <!-- MEMBER CODE -->

                                <td class="member-code">

                                    <?= htmlspecialchars(
                                        $memberCode
                                    ) ?>

                                </td>


                                <!-- NAME -->

                                <td>

                                    <?= htmlspecialchars(

                                        $member["first_name"]
                                        . " "
                                        .
                                        (
                                            $member["middle_name"]
                                            ? $member["middle_name"]
                                              . " "
                                            : ""
                                        )
                                        . $member["last_name"]

                                    ) ?>

                                </td>


                                <!-- MINISTRY -->

                                <td>

                                    <?= htmlspecialchars(
                                        $member["ministry"]
                                        ?: "—"
                                    ) ?>

                                </td>


                                <!-- ATTENDANCE -->

                                <td>

                                    <div class="status-options">


                                        <!-- PRESENT -->

                                        <label>

                                            <input
                                                type="radio"

                                                name="status[<?= (int)$memberId ?>]"

                                                value="Present"

                                                <?= $currentStatus === "Present"
                                                    ? "checked"
                                                    : "" ?>
                                            >

                                            <span
                                                class="present-label"
                                            >

                                                ✓ Present

                                            </span>

                                        </label>


                                        <!-- ABSENT -->

                                        <label>

                                            <input
                                                type="radio"

                                                name="status[<?= (int)$memberId ?>]"

                                                value="Absent"

                                                <?= $currentStatus === "Absent"
                                                    ? "checked"
                                                    : "" ?>
                                            >

                                            <span
                                                class="absent-label"
                                            >

                                                ✕ Absent

                                            </span>

                                        </label>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="4"
                                style="
                                    text-align:center;
                                    padding:30px;
                                "
                            >

                                No active members found.

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>


            <!-- SAVE BUTTON -->

            <?php if (count($members) > 0): ?>

                <div class="save-container">

                    <button
                        type="submit"
                        name="save_attendance"
                        class="save-btn"
                    >

                        💾 Save Attendance

                    </button>

                </div>

            <?php endif; ?>


        </form>

    <?php else: ?>

        <div class="error">

            Please select a Sunday.
            The selected date is not a Sunday.

        </div>

    <?php endif; ?>


</main>


</body>

</html>