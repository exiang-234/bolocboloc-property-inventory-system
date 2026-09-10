# BPIS Requirements Implementation Guide

This document maps your specification to what was added in the codebase and what you still need to configure.

## Login

| Requirement | Status | Location |
|-------------|--------|----------|
| Google Identity Federation | **Ready (configure)** | Copy `config/oauth_config.example.php` → `config/oauth_config.php`, set Client ID/Secret, set `enabled => true`. Flow: `auth/google_login.php`, `auth/google_callback.php` |
| Formal usernames (`name.role@domain`) | **Done** | `config/auth_helpers.php` → `bpis_username_is_formal_format()`; enforced on `login.php` and `register.php` |

## Treasurer / COA

| Requirement | Status | Location |
|-------------|--------|----------|
| Fixed vs Movable clustering | **Done** | `asset.asset_cluster`; registration form; filter via inventory (column when present) |
| Assigned location | **Done** | `asset.location` |
| Depreciation module | **Done** | `treasurer/depreciation.php`, table `asset_depreciation_log` |
| QR scan for annual count | **Done** | `treasurer/report_scan.php` (camera scanner) |
| Rename Audit → Report | **Done** | Treasurer sidebar labels; `audit.php` title |
| Beginning / ending inventory | **Done** | `treasurer/inventory_period.php`, tables `inventory_periods`, `inventory_period_lines` |
| Unit Cost/Value label | **Done** | `config/coa_labels.php`; updated inventory, registration, audit |
| Editable audit remarks | **Schema + registration** | `asset.audit_remarks`; full inline edit in inventory can be extended |
| QR includes brand/model | **Partial** | Registration stores brand/model; extend QR JS in `asset_registration.php` / `inventory.php` to append to payload |
| Brand, model, photos per unit | **Schema + form** | `asset_units` table; per-unit photo upload UI can be added on registration submit loop |
| Acquisition fields + uploads | **Done** | Registration POST + `config/upload_helpers.php` → `uploads/acquisition/` |
| Required field (*) | **Done** | Key fields on registration form |
| History dashboard | **Done** | `treasurer/inventory_history.php`, `asset_history_snapshots` |
| Real-time asset summary | **Done** | `treasurer/assets_summary_feed.php` (poll from dashboard) |

## Borrower / Secretary

| Requirement | Status | Location |
|-------------|--------|----------|
| Vetting after submit | **Done** | `borrower_profiles`, `borrowing_requests.vetting_status`; `secretary/borrower_profiles.php` |
| Blocklist | **Done** | `borrower_blocklist`, `secretary/blocklist.php`; checked in `borrower/step2.php` |

## Super admin

| Requirement | Status | Location |
|-------------|--------|----------|
| Barangay Captain = super admin | **Done** | `admin.is_super_admin`; set on Captain register/login; `bpis_is_super_admin()`; **User management UI** at `captain/users.php` |

## Database

Schema is applied automatically on each request via `config/schema_bootstrap.php` (included from `config/database.php`).

Manual SQL: `database/migrations/001_requirements_foundation.sql`

## Phase 2 (completed in code)

- Per-unit `asset_units` rows on registration with optional photos (`config/asset_units_helpers.php`).
- QR payload includes `tag|brand|model` on registration preview and inventory QR modal.
- Inventory: cluster filter, location/cluster columns, edit modal for audit remarks / location / cluster.
- Full treasurer sidebar on dashboard, inventory, registration, audit pages; horizontal subnav on utility pages.
- Treasurer dashboard polls `assets_summary_feed.php` every 30 seconds.
- Secretary sidebar on all secretary pages (dashboard, requests, borrowers, overdue, vetting, blocklist).
- One-time `asset_units` backfill: `tools/backfill_asset_units.php` or `php tools/backfill_asset_units.php [--dry-run]`.

## Next steps (optional)

1. Enable Google OAuth in `config/oauth_config.php`.
2. Run asset units backfill after deploying schema (dry run first).
