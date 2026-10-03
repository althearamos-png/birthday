<?php
require_once "config/database.php";

$month = date('Y-m');
$startDate = $month . '-01';
$endDate = date('Y-m-d', strtotime($startDate . ' +1 month'));


// Active members
$stmt = $conn->query("
    SELECT COUNT(*)
    FROM members
    WHERE status = 'Active'
");

$totalMembers = (int) $stmt->fetchColumn();


// Present records this month
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM attendance
    WHERE status = 'Present'
    AND attendance_date >= ?
    AND attendance_date < ?
");

$stmt->execute([
    $startDate,
    $endDate
]);

$totalPresent = (int) $stmt->fetchColumn();


// Absent records this month
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM attendance
    WHERE status = 'Absent'
    AND attendance_date >= ?
    AND attendance_date < ?
");

$stmt->execute([
    $startDate,
    $endDate
]);

$totalAbsent = (int) $stmt->fetchColumn();


// Attendance rate
$totalRecords = $totalPresent + $totalAbsent;

$attendanceRate = $totalRecords > 0
    ? ($totalPresent / $totalRecords) * 100
    : 0;


// Number of Sundays recorded
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT attendance_date)
    FROM attendance
    WHERE attendance_date >= ?
    AND attendance_date < ?
");

$stmt->execute([
    $startDate,
    $endDate
]);

$sundaysRecorded = (int) $stmt->fetchColumn();


// Recent attendance
$stmt = $conn->prepare("
    SELECT
        a.attendance_date,
        a.status,
        m.member_code,
        m.first_name,
        m.middle_name,
        m.last_name
    FROM attendance a
    INNER JOIN members m
        ON a.member_id = m.id
    ORDER BY a.attendance_date DESC, a.id DESC
    LIMIT 10
");

$stmt->execute();

$recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
Church Attendance Dashboard
</title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


body {

    font-family: Arial, sans-serif;

    background: #f4f6f9;

    color: #333;

}


.container {

    width: 92%;

    max-width: 1200px;

    margin: 30px auto;

}


/* HEADER */

.header {

    background:
        linear-gradient(
            135deg,
            #173b63,
            #315f8d
        );

    color: white;

    padding: 30px;

    border-radius: 14px;

    margin-bottom: 25px;

}


.header h1 {

    font-size: 30px;

    margin-bottom: 7px;

}


.header p {

    opacity: .9;

}


/* STATISTICS */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;

}


.card {

    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,.08);

}


.card-title {

    color: #777;

    font-size: 14px;

    margin-bottom: 10px;

}


.card-number {

    color: #173b63;

    font-size: 30px;

    font-weight: bold;

}


/* QUICK ACTIONS */

.section {

    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 25px;

}


.section h2 {

    color: #173b63;

    margin-bottom: 20px;

}


.actions {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

}


.action {

    display: block;

    text-decoration: none;

    padding: 20px;

    border-radius: 10px;

    background: #f4f6f9;

    color: #173b63;

    border: 1px solid #e1e5ea;

    transition: .2s;

}


.action:hover {

    background: #173b63;

    color: white;

    transform: translateY(-2px);

}


.action-title {

    font-weight: bold;

    font-size: 16px;

    margin-bottom: 6px;

}


.action-description {

    font-size: 13px;

    opacity: .75;

}


/* TABLE */

table {

    width: 100%;

    border-collapse: collapse;

}


th {

    background: #173b63;

    color: white;

    padding: 13px;

    text-align: left;

}


td {

    padding: 13px;

    border-bottom:
        1px solid #eee;

}


.present {

    color: #198754;

    font-weight: bold;

}


.absent {

    color: #dc3545;

    font-weight: bold;

}


.empty {

    text-align: center;

    padding: 30px;

    color: #777;

}


/* MOBILE */

@media (max-width: 850px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .actions {

        grid-template-columns: 1fr;

    }

}


@media (max-width: 550px) {

    .stats {

        grid-template-columns: 1fr;

    }

    .container {

        width: 95%;

    }

    .header h1 {

        font-size: 23px;

    }

}

</style>

</head>


<body>


<div class="container">


<!-- HEADER -->

<div class="header">

    <h1>
        Church Attendance System
    </h1>

    <p>
        Dashboard • <?= date('F Y') ?>
    </p>

</div>



<!-- STATISTICS -->

<div class="stats">


    <div class="card">

        <div class="card-title">
            Active Members
        </div>

        <div class="card-number">
            <?= $totalMembers ?>
        </div>

    </div>



    <div class="card">

        <div class="card-title">
            Present This Month
        </div>

        <div class="card-number">
            <?= $totalPresent ?>
        </div>

    </div>



    <div class="card">

        <div class="card-title">
            Attendance Rate
        </div>

        <div class="card-number">
            <?= number_format(
                $attendanceRate,
                1
            ) ?>%
        </div>

    </div>



    <div class="card">

        <div class="card-title">
            Sundays Recorded
        </div>

        <div class="card-number">
            <?= $sundaysRecorded ?>
        </div>

    </div>


</div>



<!-- QUICK ACTIONS -->

<div class="section">

    <h2>
        Quick Actions
    </h2>


    <div class="actions">


        <a
            href="attendance.php"
            class="action"
        >

            <div class="action-title">
                📋 Sunday Attendance
            </div>

            <div class="action-description">
                Record this Sunday's attendance
            </div>

        </a>



        <a
            href="members.php"
            class="action"
        >

            <div class="action-title">
                👥 Manage Members
            </div>

            <div class="action-description">
                Add and manage church members
            </div>

        </a>



        <a
            href="monthly_report.php"
            class="action"
        >

            <div class="action-title">
                📊 Monthly Report
            </div>

            <div class="action-description">
                View monthly attendance records
            </div>

        </a>


    </div>

</div>



<!-- RECENT ATTENDANCE -->

<div class="section">

    <h2>
        Recent Attendance
    </h2>


    <table>

        <thead>

            <tr>

                <th>
                    Date
                </th>

                <th>
                    Member
                </th>

                <th>
                    Status
                </th>

            </tr>

        </thead>


        <tbody>


        <?php if (count($recentAttendance) > 0): ?>


            <?php foreach (
                $recentAttendance
                as $record
            ): ?>


                <?php

                $fullName =
                    $record['first_name'];

                if (!empty(
                    $record['middle_name']
                )) {

                    $fullName .=
                        ' ' .
                        $record['middle_name'];

                }

                $fullName .=
                    ' ' .
                    $record['last_name'];

                ?>


                <tr>

                    <td>

                        <?= htmlspecialchars(
                            $record['attendance_date']
                        ) ?>

                    </td>


                    <td>

                        <?= htmlspecialchars(
                            $fullName
                        ) ?>

                    </td>


                    <td class="<?=
                        $record['status']
                        === 'Present'
                        ? 'present'
                        : 'absent'
                    ?>">

                        <?= htmlspecialchars(
                            $record['status']
                        ) ?>

                    </td>

                </tr>


            <?php endforeach; ?>


        <?php else: ?>


            <tr>

                <td
                    colspan="3"
                    class="empty"
                >

                    No attendance records yet.

                </td>

            </tr>


        <?php endif; ?>


        </tbody>

    </table>


</div>


</div>


</body>

</html>