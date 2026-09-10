<?php
session_start();

include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';

bpis_require_login(['Secretary', 'Barangay Captain']);

$user_fullname = 'User';
$user_role = (string) ($_SESSION['role'] ?? '');

$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Received' AND return_date < CURRENT_DATE THEN 1 ELSE 0 END) as overdue,
    COUNT(DISTINCT CASE WHEN status = 'Received' THEN full_name END) as borrower
    FROM borrowing_requests";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$total_requests = $stats['total'] ?? 0;
$total_pending = $stats['pending'] ?? 0;
$total_overdue = $stats['overdue'] ?? 0;
$total_borrowers_count = $stats['borrowers'] ?? 0;


$borrowers_count_query = "SELECT COUNT(DISTINCT full_name) as count FROM borrower";
$borrowers_count_result = mysqli_query($conn, $borrowers_count_query);
$borrowers_count_data = mysqli_fetch_assoc($borrowers_count_result);
$total_borrowers_count = $borrowers_count_data['count'] ?? 0;


$registered_borrowers = [];
$borrow_query = "SELECT id, full_name, purok, contact_number, search_item, quantity, date_needed, purpose, return_date, created_at, otp, status 
                 FROM borrowing_requests 
                 WHERE status = 'Approved' 
                 ORDER BY id DESC";
$borrow_result = mysqli_query($conn, $borrow_query);

if ($borrow_result) {
    while ($row = mysqli_fetch_assoc($borrow_result)) {
        $registered_borrowers[] = $row;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="<?= bpis_app_base_path() ?>/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/bpis_app_meta.php'; ?>
    <title>Borrowers Confirmation</title>
    <style>
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: { 600: '#2563eb', 700: '#1d4ed8' } } } } };
    </script>
    <script src="../tailwind_blue_theme.js"></script>
</head>
<body>
    <table>
        <thead>
            
