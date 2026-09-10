<?php
require_once __DIR__ . '/_cli_only.php';
require_once __DIR__ . '/../config/database.php';
$rs = mysqli_query($conn, "SELECT id, role, fullname FROM admin WHERE role = 'Secretary'");
while ($r = mysqli_fetch_assoc($rs)) {
    echo $r['id'] . ' | ' . $r['role'] . ' | ' . $r['fullname'] . PHP_EOL;
}
$rs2 = mysqli_query($conn, 'SELECT admin_id, COUNT(*) AS c, SUM(is_read = 0) AS unread FROM notifications GROUP BY admin_id');
while ($r = mysqli_fetch_assoc($rs2)) {
    echo 'admin_id=' . $r['admin_id'] . ' total=' . $r['c'] . ' unread=' . $r['unread'] . PHP_EOL;
}
