<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Get selected date
|--------------------------------------------------------------------------
*/

$selected_date = $_GET["date"] ?? date("Y-m-d");


/*
|--------------------------------------------------------------------------
| Check if selected date is Sunday
|--------------------------------------------------------------------------
*/

$dateObject = new DateTime($selected_date);
$isSunday = ($dateObject->format("w") == 0);


/*
|--------------------------------------------------------------------------
| Save Attendance
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_attendance"])) {

    $attendance_date = $_POST["attendance_date"];
    $attendance = $_POST["attendance"] ?? [];

    $checkDate = new DateTime($attendance_date);

    if ($checkDate->format("w") != 0) {

        $error = "Please select a Sunday.";

    } elseif (empty($attendance)) {

        $error = "No attendance records were submitted.";

    } else {

        try {

            $conn->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Insert or update each member's attendance
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                INSERT INTO attendance
                (member_id, attendance_date, status)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                status = VALUES(status)
            ");

            foreach ($attendance as $member_id => $status) {

                if ($status !== "Present" && $status !== "Absent") {
                    continue;
                }

                $stmt->execute([
                    $member_id,
                    $attendance_date,
                    $status
                ]);
            }

            $conn->commit();

            $message = "Sunday attendance saved successfully!";

            $selected_date = $attendance_date;

        } catch (PDOException $e) {

            $conn->rollBack();

            $error = "Unable to save attendance. Please check your database.";

        }
    }
}


/*
|--------------------------------------------------------------------------
| Get active members
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT
        id,
        member_code,
        first_name,
        middle_name,
        last_name,
        ministry,
        status
    FROM members
    WHERE status = 'Active'
    ORDER BY last_name ASC, first_name ASC
");

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Get existing attendance for selected date
|--------------------------------------------------------------------------
*/

$attendanceRecords = [];

$stmt = $conn->prepare("
    SELECT member_id, status
    FROM attendance
    WHERE attendance_date = ?
");

$stmt->execute([$selected_date]);

$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($records as $record) {

    $attendanceRecords[$record["member_id"]] = $record["status"];

}


/*
|--------------------------------------------------------------------------
| Calculate current totals
|--------------------------------------------------------------------------
*/

$presentCount = 0;
$absentCount = 0;

foreach ($attendanceRecords as $status) {

    if ($status === "Present") {
        $presentCount++;
    }

    if ($status === "Absent") {
        $absentCount++;
    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Sunday Attendance | Church Attendance</title>

<style>

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


/* SIDEBAR */

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
.menu .active {
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


/* MAIN */

.main {
    margin-left: 240px;
    padding: 30px;
}


/* HEADER */

.header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
}

.header h1 {
    font-size: 28px;
}

.header p {
    color: #6b7280;
    margin-top: 5px;
}


/* DATE SELECTOR */

.date-card {
    background: white;

    padding: 20px;

    border-radius: 12px;

    margin-bottom: 20px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.date-form {
    display: flex;

    align-items: end;

    gap: 15px;

    flex-wrap: wrap;
}

.date-group label {
    display: block;

    font-size: 12px;

    font-weight: bold;

    margin-bottom: 7px;
}

.date-group input {
    padding: 11px;

    border: 1px solid #ddd;

    border-radius: 8px;
}

.date-btn {
    padding: 11px 18px;

    background: #34495e;

    color: white;

    border: none;

    border-radius: 8px;

    cursor: pointer;
}

.sunday-warning {
    margin-top: 15px;

    padding: 12px;

    background: #fee2e2;

    color: #991b1b;

    border-radius: 8px;
}

.sunday-success {
    margin-top: 15px;

    padding: 12px;

    background: #dcfce7;

    color: #166534;

    border-radius: 8px;
}


/* ALERT */

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
}


/* STATS */

.stats {
    display: grid;

    grid-template-columns: repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 20px;
}

.stat-card {
    background: white;

    padding: 20px;

    border-radius: 12px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.stat-card span {
    font-size: 12px;

    color: #6b7280;
}

.stat-card strong {
    display: block;

    font-size: 28px;

    margin-top: 8px;
}


/* TABLE */

.table-container {
    background: white;

    border-radius: 14px;

    overflow-x: auto;

    box-shadow: 0 4px 15px rgba(0,0,0,0.06);
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 800px;
}

th {
    text-align: left;

    padding: 15px;

    font-size: 12px;

    color: #6b7280;

    background: #fafbfc;

    border-bottom: 1px solid #e5e7eb;
}

td {
    padding: 15px;

    border-bottom: 1px solid #f0f1f2;

    font-size: 13px;
}

.member-code {
    font-weight: bold;

    color: #34495e;
}


/* ATTENDANCE OPTIONS */

.attendance-options {
    display: flex;

    gap: 8px;
}

.attendance-options input {
    display: none;
}

.attendance-options label {
    padding: 8px 15px;

    border-radius: 20px;

    border: 1px solid #ddd;

    cursor: pointer;

    font-size: 12px;

    font-weight: bold;
}

.present-label:hover {
    background: #dcfce7;
}

.absent-label:hover {
    background: #fee2e2;
}

.attendance-options input:checked + .present-label {
    background: #166534;

    color: white;

    border-color: #166534;
}

.attendance-options input:checked + .absent-label {
    background: #991b1b;

    color: white;

    border-color: #991b1b;
}


/* SAVE BUTTON */

.save-container {
    margin-top: 20px;

    display: flex;

    justify-content: flex-end;
}

.save-btn {
    padding: 14px 25px;

    background: #17212b;

    color: white;

    border: none;

    border-radius: 9px;

    font-weight: bold;

    cursor: pointer;
}

.save-btn:hover {
    background: #34495e;
}


/* EMPTY */

.empty {
    text-align: center;

    padding: 40px;

    color: #6b7280;
}


/* MOBILE */

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

    .header {
        display: block;
    }

    .stats {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="brand">

        <div class="brand-icon">
            ⛪
        </div>

        <h2>Church Attendance</h2>

        <p>Monitoring System</p>

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
            <a href="attendance.php" class="active">
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


<!-- MAIN -->

<main class="main">


    <div class="header">

        <div>

            <h1>Sunday Attendance</h1>

            <p>
                Record and monitor weekly church attendance.
            </p>

        </div>

    </div>


    <?php if ($message): ?>

        <div class="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <!-- DATE -->

    <div class="date-card">

        <form method="GET" class="date-form">

            <div class="date-group">

                <label>
                    Select Sunday
                </label>

                <input
                    type="date"
                    name="date"
                    value="<?php echo htmlspecialchars($selected_date); ?>"
                    required
                >

            </div>


            <button
                type="submit"
                class="date-btn"
            >
                View Attendance
            </button>

        </form>


        <?php if ($isSunday): ?>

            <div class="sunday-success">

                ✓
                <?php echo $dateObject->format("F d, Y"); ?>
                is a Sunday.

            </div>

        <?php else: ?>

            <div class="sunday-warning">

                ⚠ Please select a Sunday.
                The selected date is
                <?php echo $dateObject->format("l"); ?>.

            </div>

        <?php endif; ?>

    </div>


    <!-- STATS -->

    <div class="stats">

        <div class="stat-card">

            <span>
                Active Members
            </span>

            <strong>
                <?php echo count($members); ?>
            </strong>

        </div>


        <div class="stat-card">

            <span>
                Present
            </span>

            <strong>
                <?php echo $presentCount; ?>
            </strong>

        </div>


        <div class="stat-card">

            <span>
                Absent
            </span>

            <strong>
                <?php echo $absentCount; ?>
            </strong>

        </div>

    </div>


    <!-- ATTENDANCE TABLE -->

    <form method="POST">

        <input
            type="hidden"
            name="attendance_date"
            value="<?php echo htmlspecialchars($selected_date); ?>"
        >


        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>Member ID</th>

                        <th>Member Name</th>

                        <th>Ministry</th>

                        <th>Attendance</th>

                    </tr>

                </thead>


                <tbody>

                <?php if (count($members) > 0): ?>

                    <?php foreach ($members as $member): ?>

                        <?php

                        $currentStatus =
                            $attendanceRecords[$member["id"]]
                            ?? "Absent";

                        ?>


                        <tr>


                            <td class="member-code">

                                <?php
                                echo htmlspecialchars(
                                    $member["member_code"]
                                );
                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $member["first_name"]
                                    . " "
                                    .
                                    (
                                        $member["middle_name"]
                                        ?
                                        $member["middle_name"]
                                        . " "
                                        :
                                        ""
                                    )
                                    .
                                    $member["last_name"]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $member["ministry"]
                                    ?: "—"
                                );

                                ?>

                            </td>


                            <td>

                                <div class="attendance-options">


                                    <input
                                        type="radio"
                                        id="present_<?php echo $member["id"]; ?>"
                                        name="attendance[<?php echo $member["id"]; ?>]"
                                        value="Present"
                                        <?php
                                        echo $currentStatus === "Present"
                                            ? "checked"
                                            : "";
                                        ?>
                                    >

                                    <label
                                        for="present_<?php echo $member["id"]; ?>"
                                        class="present-label"
                                    >
                                        ✓ Present
                                    </label>


                                    <input
                                        type="radio"
                                        id="absent_<?php echo $member["id"]; ?>"
                                        name="attendance[<?php echo $member["id"]; ?>]"
                                        value="Absent"
                                        <?php
                                        echo $currentStatus === "Absent"
                                            ? "checked"
                                            : "";
                                        ?>
                                    >

                                    <label
                                        for="absent_<?php echo $member["id"]; ?>"
                                        class="absent-label"
                                    >
                                        ✕ Absent
                                    </label>


                                </div>

                            </td>


                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="4"
                            class="empty"
                        >

                            No active members found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <?php if (count($members) > 0): ?>

            <div class="save-container">

                <button
                    type="submit"
                    name="save_attendance"
                    class="save-btn"
                    <?php
                    echo !$isSunday ? "disabled" : "";
                    ?>
                >
                    💾 Save Sunday Attendance
                </button>

            </div>

        <?php endif; ?>


    </form>


</main>

</body>

</html>