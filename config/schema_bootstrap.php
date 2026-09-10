<?php

/**
 * Idempotent schema updates for requirements foundation (MariaDB / MySQL).
 */
if (!function_exists('bpis_column_exists')) {
    function bpis_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $rs = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $rs && mysqli_num_rows($rs) > 0;
    }
}

if (!function_exists('bpis_table_exists')) {
    function bpis_table_exists(mysqli $conn, string $table): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $rs = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
        return $rs && mysqli_num_rows($rs) > 0;
    }
}

if (!function_exists('bpis_add_column_if_missing')) {
    function bpis_add_column_if_missing(mysqli $conn, string $table, string $column, string $definition): void
    {
        if (!bpis_column_exists($conn, $table, $column)) {
            mysqli_query($conn, "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}

if (!function_exists('bpis_ensure_requirements_schema')) {
    function bpis_ensure_requirements_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        bpis_add_column_if_missing($conn, 'admin', 'google_id', "VARCHAR(128) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'admin', 'google_email', "VARCHAR(255) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'admin', 'is_super_admin', "TINYINT(1) NOT NULL DEFAULT 0");
        bpis_add_column_if_missing($conn, 'admin', 'email', "VARCHAR(255) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'admin', 'email_verified', "TINYINT(1) NOT NULL DEFAULT 0");
        bpis_add_column_if_missing($conn, 'admin', 'email_verify_token', "VARCHAR(64) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'admin', 'email_verify_expires_at', "DATETIME NULL DEFAULT NULL");
        mysqli_query($conn, "UPDATE admin SET is_super_admin = 1 WHERE role = 'Barangay Captain' AND is_super_admin = 0");
        mysqli_query($conn, "UPDATE admin SET email_verified = 1 WHERE email_verified = 0 AND (email IS NULL OR email = '')");
        mysqli_query($conn, "UPDATE admin SET email = username WHERE (email IS NULL OR email = '') AND LOWER(username) LIKE '%@gmail.com'");
        mysqli_query($conn, "UPDATE admin SET username = email WHERE email IS NOT NULL AND email <> '' AND (username IS NULL OR username = '' OR username NOT LIKE '%@%')");

        bpis_add_column_if_missing($conn, 'asset', 'photo_path', 'VARCHAR(500) NULL DEFAULT NULL');
        bpis_add_column_if_missing($conn, 'asset', 'asset_cluster', "ENUM('Fixed Assets','Movable Assets') NULL DEFAULT 'Movable Assets'");
        bpis_add_column_if_missing($conn, 'asset', 'location', "VARCHAR(255) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'asset', 'brand', "VARCHAR(120) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'asset', 'model', "VARCHAR(120) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'asset', 'audit_remarks', "TEXT NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'asset', 'acquisition_mode', "ENUM('Purchased','Donation','Transfer','Other') NULL DEFAULT 'Purchased'");
        bpis_add_column_if_missing($conn, 'asset', 'cheque_number', "VARCHAR(80) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'asset', 'voucher_number', "VARCHAR(80) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'asset', 'cash_amount', "DECIMAL(15,2) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'asset', 'purchase_proof_path', "VARCHAR(500) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'asset', 'service_invoice_path', "VARCHAR(500) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'asset', 'shop_name', "VARCHAR(255) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'asset', 'purchaser_name', "VARCHAR(255) NULL DEFAULT NULL");

        if (!bpis_table_exists($conn, 'asset_units')) {
            mysqli_query($conn, "CREATE TABLE asset_units (
                id INT(11) NOT NULL AUTO_INCREMENT,
                asset_id INT(11) NOT NULL,
                unit_tag VARCHAR(120) NOT NULL,
                brand VARCHAR(120) NULL,
                model VARCHAR(120) NULL,
                photo_path VARCHAR(500) NULL,
                location VARCHAR(255) NULL,
                unit_condition VARCHAR(80) DEFAULT 'SERVICEABLE',
                audit_remarks TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY asset_id (asset_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!bpis_table_exists($conn, 'asset_depreciation_log')) {
            mysqli_query($conn, "CREATE TABLE asset_depreciation_log (
                id INT(11) NOT NULL AUTO_INCREMENT,
                asset_id INT(11) NOT NULL,
                period_label VARCHAR(40) NOT NULL,
                depreciation_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                accumulated_depreciation DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                net_book_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                recorded_by INT(11) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY asset_id (asset_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!bpis_table_exists($conn, 'inventory_periods')) {
            mysqli_query($conn, "CREATE TABLE inventory_periods (
                id INT(11) NOT NULL AUTO_INCREMENT,
                period_type ENUM('Quarterly','Annual') NOT NULL DEFAULT 'Annual',
                period_label VARCHAR(60) NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                beginning_qty INT(11) NOT NULL DEFAULT 0,
                ending_qty INT(11) NOT NULL DEFAULT 0,
                beginning_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                ending_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                remarks TEXT NULL,
                created_by INT(11) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!bpis_table_exists($conn, 'inventory_period_lines')) {
            mysqli_query($conn, "CREATE TABLE inventory_period_lines (
                id INT(11) NOT NULL AUTO_INCREMENT,
                period_id INT(11) NOT NULL,
                asset_id INT(11) NOT NULL,
                beginning_qty INT(11) NOT NULL DEFAULT 0,
                ending_qty INT(11) NOT NULL DEFAULT 0,
                beginning_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                ending_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                condition_note VARCHAR(255) NULL,
                audit_remarks TEXT NULL,
                PRIMARY KEY (id),
                KEY period_id (period_id),
                KEY asset_id (asset_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!bpis_table_exists($conn, 'asset_history_snapshots')) {
            mysqli_query($conn, "CREATE TABLE asset_history_snapshots (
                id INT(11) NOT NULL AUTO_INCREMENT,
                asset_id INT(11) NOT NULL,
                period_type ENUM('Monthly','Quarterly','Annual') NOT NULL,
                period_label VARCHAR(60) NOT NULL,
                quantity INT(11) NOT NULL DEFAULT 0,
                condition_note VARCHAR(255) NULL,
                photo_path VARCHAR(500) NULL,
                remarks TEXT NULL,
                snapshot_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY asset_id (asset_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            // Ensure 'Monthly' is included in the period_type ENUM (fix for older schema)
            $rs = mysqli_query($conn, "SHOW COLUMNS FROM asset_history_snapshots LIKE 'period_type'");
            if ($rs && ($row = mysqli_fetch_assoc($rs))) {
                if (stripos($row['Type'], 'Monthly') === false) {
                    mysqli_query($conn, "ALTER TABLE asset_history_snapshots MODIFY COLUMN period_type ENUM('Monthly','Quarterly','Annual') NOT NULL");
                }
            }
        }

        if (!bpis_table_exists($conn, 'borrower_profiles')) {
            mysqli_query($conn, "CREATE TABLE borrower_profiles (
                id INT(11) NOT NULL AUTO_INCREMENT,
                full_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                contact_number VARCHAR(30) NULL,
                purok VARCHAR(120) NULL,
                photo_path VARCHAR(500) NULL,
                id_document_path VARCHAR(500) NULL,
                vetting_status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
                vetting_notes TEXT NULL,
                verified_by INT(11) NULL,
                verified_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!bpis_table_exists($conn, 'borrower_blocklist')) {
            mysqli_query($conn, "CREATE TABLE borrower_blocklist (
                id INT(11) NOT NULL AUTO_INCREMENT,
                full_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NULL,
                contact_number VARCHAR(30) NULL,
                reason TEXT NOT NULL,
                blocked_by INT(11) NOT NULL,
                blocked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                KEY email (email),
                KEY full_name (full_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        bpis_add_column_if_missing($conn, 'borrowing_requests', 'batch_id', 'VARCHAR(64) NULL DEFAULT NULL');
        bpis_add_column_if_missing($conn, 'borrowing_requests', 'vetting_status', "ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending'");
        bpis_add_column_if_missing($conn, 'borrowing_requests', 'borrower_profile_id', "INT(11) NULL DEFAULT NULL");
        bpis_add_column_if_missing($conn, 'borrower_profiles', 'id_document_path', 'VARCHAR(500) NULL DEFAULT NULL');
        bpis_add_column_if_missing($conn, 'borrower_profiles', 'id_document_back_path', 'VARCHAR(500) NULL DEFAULT NULL');
    }
}
