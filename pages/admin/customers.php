<?php
require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/../../includes/pricing.php';

$customers = $pdo->query(
    "SELECT c.customer_id, c.full_name, c.email, c.phone,
            COUNT(b.booking_id) AS total_bookings,
            COALESCE(SUM(CASE WHEN b.status != 'cancelled' THEN b.grand_total ELSE 0 END), 0) AS lifetime_value
     FROM customers c
     LEFT JOIN bookings b ON b.customer_id = c.customer_id
     GROUP BY c.customer_id, c.full_name, c.email, c.phone
     ORDER BY total_bookings DESC, c.full_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

function peso($n) {
    return '₱' . number_format((float) $n, 2);
}

$totalCustomers = count($customers);
$totalBookings = 0;
$totalRevenue = 0;
$activeCustomers = 0;
foreach ($customers as $customer) {
    $bookings = (int) $customer['total_bookings'];
    $totalBookings += $bookings;
    $totalRevenue += (float) $customer['lifetime_value'];
    if ($bookings > 1) $activeCustomers++;
}
$averageBookings = $totalCustomers > 0 ? $totalBookings / $totalCustomers : 0;

function customerInitials($name) {
    $parts = preg_split('/\s+/', trim((string) $name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $initials ?: 'CU';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Customer Management - SnapVenture Admin</title>
    <link rel="stylesheet" href="../../css/admin-sidebar.css" />
    <style>
        :root {
            --customer-brown: #705344;
            --customer-brown-dark: #5f4639;
            --customer-muted: #a18d82;
            --customer-border: #e9ddd5;
            --customer-bg: #faf8f5;
            --customer-white: #fff;
        }
        html, body { font-family: Arial, Helvetica, sans-serif !important; }
        body { background: var(--customer-bg); }
        .customers-page, .customers-page * { font-family: Arial, Helvetica, sans-serif !important; }
        .customers-page { min-width: 0; color: var(--customer-brown-dark); background: var(--customer-bg); min-height: 100vh; }
        /* Keep the page content beside the fixed admin sidebar. */
        /* The sidebar becomes an off-canvas menu on smaller screens. */
        .admin-shell > main.admin-content.customers-page {
    box-sizing: border-box;
    position: relative;
    flex: 0 0 calc(100% - 252px);
    width: calc(100% - 252px);
    margin-left: 252px;
    padding: 32px;
    min-width: 0;
}

@media (max-width: 900px) {
    .admin-shell > main.admin-content.customers-page {
        flex: 0 0 100%;
        width: 100%;
        margin-left: 0;
        padding: 20px;
    }
}
        .customers-page .page-heading { margin-bottom: 25px; }
        .customers-page .page-heading h1 { margin: 0 0 5px; color: var(--customer-brown-dark); font-size: clamp(25px, 2.3vw, 32px); font-weight: 700; letter-spacing: -0.5px; }
        .customers-page .page-heading p { margin: 0; color: var(--customer-muted); font-size: 15px; }
        .customer-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
        .customer-stat { min-width: 0; padding: 24px; background: var(--customer-white); border: 1px solid var(--customer-border); border-radius: 13px; }
        .customer-stat .stat-label { display: block; margin-bottom: 7px; color: var(--customer-muted); font-size: 14px; }
        .customer-stat strong { display: block; color: var(--customer-brown); font-size: 25px; line-height: 1.2; }
        .customer-stat small { display: block; margin-top: 7px; color: var(--customer-muted); font-size: 12px; }
        .customer-search-panel { margin-bottom: 24px; padding: 24px; background: var(--customer-white); border: 1px solid var(--customer-border); border-radius: 13px; }
        .customer-search-wrap { position: relative; }
        .customer-search-wrap svg { position: absolute; top: 50%; left: 14px; width: 18px; height: 18px; color: #b7a69d; transform: translateY(-50%); pointer-events: none; }
        #customerSearch { width: 100%; min-height: 43px; box-sizing: border-box; padding: 11px 14px 11px 41px; color: var(--customer-brown-dark); background: #fff; border: 1px solid var(--customer-border); border-radius: 9px; outline: none; font: inherit; font-size: 14px; }
        #customerSearch:focus { border-color: #a98b78; box-shadow: 0 0 0 3px rgba(112, 83, 68, .09); }
        #customerSearch::placeholder { color: #a18d82; }
        .customer-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; align-items: stretch; }
        .customer-card { min-width: 0; padding: 24px; background: var(--customer-white); border: 1px solid var(--customer-border); border-radius: 13px; transition: box-shadow .18s ease, transform .18s ease; display: flex; flex-direction: column; }
        .customer-card:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(75, 52, 39, .07); }
        .customer-card-top { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 17px; }
        .customer-avatar { display: flex; flex: 0 0 48px; width: 48px; height: 48px; align-items: center; justify-content: center; color: #fff; background: var(--customer-brown); border-radius: 50%; font-size: 15px; font-weight: 600; }
        .customer-identity { min-width: 0; flex: 1; padding-top: 3px; }
        .customer-name { overflow: hidden; margin: 0 0 3px; color: var(--customer-brown-dark); font-size: 16px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
        .customer-id { color: var(--customer-muted); font-size: 13px; }
        .customer-status { flex: 0 0 auto; padding: 4px 10px; color: #167447; background: #dcfce7; border-radius: 20px; font-size: 11px; line-height: 1; text-transform: lowercase; }
        .customer-status.new { color: #805b16; background: #fff2d6; }
        .customer-contact { display: grid; gap: 9px; margin-bottom: 17px; }
        .customer-contact-row { display: flex; min-width: 0; align-items: center; gap: 9px; color: var(--customer-brown); font-size: 13px; }
        .customer-contact-row svg { flex: 0 0 16px; width: 16px; height: 16px; color: #a58d80; }
        .customer-contact-row span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .customer-divider { height: 1px; margin: 0 0 13px; background: var(--customer-border); }
        .customer-metrics { display: grid; gap: 9px; margin-bottom: 17px; }
        .customer-metric { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; }
        .customer-metric span { color: var(--customer-muted); }
        .customer-metric strong { color: var(--customer-brown-dark); font-weight: 500; text-align: right; }
        .customer-details { display: block; width: 100%; margin-top: auto; padding: 9px 12px; color: var(--customer-brown-dark); background: #fff; border: 1px solid var(--customer-border); border-radius: 8px; font: inherit; font-size: 13px; text-align: center; text-decoration: none; transition: background .18s, border-color .18s; }
        .customer-details:hover { background: #faf6f3; border-color: #cbb7aa; }
        .customer-empty { grid-column: 1 / -1; padding: 38px 20px; color: var(--customer-muted); background: #fff; border: 1px solid var(--customer-border); border-radius: 13px; text-align: center; }
        @media (max-width: 1100px) { .customer-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .customer-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 650px) {
            .admin-shell > .customers-page { padding: 16px; }
            .customer-grid, .customer-stats { grid-template-columns: 1fr; }
            .customer-stat { padding: 19px; }
            .customer-card { padding: 19px; }
            .customer-search-panel { padding: 15px; }
        }

        /* Visual alignment with the reference Customer Management screen */
        .admin-shell { min-height: 100vh; background: #faf8f5; }
        .admin-shell > main.admin-content.customers-page {
            box-sizing: border-box;
            flex: 0 0 calc(100% - 252px);
            width: calc(100% - 252px);
            margin-left: 252px;
            padding: 26px 32px 36px;
            min-width: 0;
        }
        .customers-page .page-heading { margin-bottom: 24px; }
        .customers-page .page-heading h1 {
            font-size: clamp(27px, 2.2vw, 32px);
            line-height: 1.2;
            letter-spacing: -0.4px;
        }
        .customers-page .page-heading p { font-size: 16px; }
        .customer-stats { gap: 16px; margin: 24px 0; }
        .customer-stat {
            min-height: 0;
            padding: 20px 24px;
            border-radius: 12px;
        }
        .customer-stat .stat-label { margin-bottom: 5px; font-size: 14px; }
        .customer-stat strong { font-size: 26px; margin: 0; }
        .customer-stat small { margin-top: 6px; font-size: 12px; }
        .customer-search-panel {
            margin-bottom: 24px;
            padding: 24px;
            border-radius: 12px;
        }
        #customerSearch { min-height: 42px; font-size: 14px; }
        .customer-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            align-items: stretch;
        }
        .customer-card {
            min-height: 0;
            padding: 24px;
            border-radius: 12px;
        }
        .customer-card-top { gap: 12px; margin-bottom: 17px; align-items: flex-start; }
        .customer-avatar { flex-basis: 48px; width: 48px; height: 48px; font-size: 15px; }
        .customer-identity { padding-top: 2px; }
        .customer-name { font-size: 17px; line-height: 1.35; }
        .customer-id { font-size: 13px; }
        .customer-status { padding: 5px 10px; font-size: 11px; }
        .customer-contact { gap: 9px; margin-bottom: 18px; }
        .customer-contact-row { font-size: 14px; gap: 9px; }
        .customer-divider { margin-bottom: 13px; }
        .customer-metrics { gap: 9px; margin-bottom: 18px; }
        .customer-details { margin-top: 0; }
        .customer-metric { font-size: 14px; }
        .customer-details { padding: 9px 12px; font-size: 14px; }
        .customer-card[hidden] { display: none !important; }

        @media (max-width: 1100px) {
            .customer-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .customer-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 900px) {
            .admin-shell > main.admin-content.customers-page {
                flex: 0 0 100%;
                width: 100%;
                margin-left: 0;
                padding: 20px;
            }
        }
        @media (max-width: 650px) {
            .admin-shell > main.admin-content.customers-page { padding: 16px; }
            .customer-grid, .customer-stats { grid-template-columns: 1fr; }
            .customer-stat { min-height: 0; padding: 19px; }
            .customer-card { min-height: 0; padding: 19px; }
            .customer-search-panel { padding: 15px; }
        }


        /* FINAL REFERENCE MATCH */
        .admin-shell > main.admin-content.customers-page {
            width: calc(100% - 252px) !important;
            flex: 0 0 calc(100% - 252px) !important;
            margin-left: 252px !important;
            padding: 26px 32px 36px !important;
        }

        .customers-page .page-heading {
            margin-bottom: 24px !important;
        }

        .customer-stats {
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 16px !important;
            margin: 24px 0 !important;
        }

        .customer-stat {
            min-height: 126px !important;
            padding: 20px 24px !important;
        }

        .customer-search-panel {
            min-height: 92px !important;
            margin-bottom: 24px !important;
            padding: 24px !important;
        }

        .customer-search-wrap {
            width: 100%;
        }

        #customerSearch {
            min-height: 42px !important;
        }

        .customer-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 16px !important;
        }

        .customer-card {
            min-height: 350px !important;
            padding: 24px !important;
        }

        .customer-details {
            margin-top: auto !important;
        }

        @media (max-width: 1100px) {
            .customer-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
            .customer-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 900px) {
            .admin-shell > main.admin-content.customers-page {
                width: 100% !important;
                flex: 0 0 100% !important;
                margin-left: 0 !important;
                padding: 20px !important;
            }
        }

        @media (max-width: 650px) {
            .customer-grid,
            .customer-stats {
                grid-template-columns: 1fr !important;
            }
            .customer-stat {
                min-height: 0 !important;
            }
            .customer-card {
                min-height: 0 !important;
            }
            .customer-search-panel {
                min-height: 0 !important;
            }
        }


        /* Exact sizing patch: match the reference screenshot */
        .admin-shell > main.admin-content.customers-page {
            margin-left: 252px !important;
            width: calc(100% - 252px) !important;
            flex: 0 0 calc(100% - 252px) !important;
            padding: 26px 32px 36px !important;
        }
        .customer-stat {
            height: 126px !important;
            min-height: 126px !important;
            padding: 20px 24px !important;
        }
        .customer-search-panel {
            height: 92px !important;
            min-height: 92px !important;
            padding: 16px 24px !important;
            display: flex !important;
            align-items: center !important;
        }
        .customer-search-wrap { width: 100% !important; }
        .customer-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 16px !important;
        }
        .customer-card {
            height: 350px !important;
            min-height: 350px !important;
            padding: 24px !important;
        }
        .customer-details { margin-top: auto !important; }
        @media (max-width: 1100px) {
            .customer-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        }
        @media (max-width: 900px) {
            .admin-shell > main.admin-content.customers-page {
                margin-left: 0 !important;
                width: 100% !important;
                flex: 0 0 100% !important;
                padding: 20px !important;
            }
        }
        @media (max-width: 650px) {
            .customer-stats, .customer-grid { grid-template-columns: 1fr !important; }
            .customer-stat { height: auto !important; min-height: 0 !important; }
            .customer-search-panel { height: auto !important; min-height: 0 !important; }
            .customer-card { height: auto !important; min-height: 0 !important; }
        }


        /* Box sizing fix: declared heights include padding and borders */
        .customer-stat,
        .customer-search-panel,
        .customer-card {
            box-sizing: border-box !important;
        }
        .customer-stat {
            height: 126px !important;
            min-height: 126px !important;
        }
        .customer-search-panel {
            height: 92px !important;
            min-height: 92px !important;
        }
        .customer-card {
            height: 350px !important;
            min-height: 350px !important;
        }
        @media (max-width: 650px) {
            .customer-stat,
            .customer-search-panel,
            .customer-card {
                height: auto !important;
                min-height: 0 !important;
            }
        }


        /* Balanced spacing pass: consistent rhythm without oversized empty card areas */
        .admin-shell > main.admin-content.customers-page {
            box-sizing: border-box;
            padding: 28px 32px 40px !important;
        }
        .customers-page .page-heading {
            margin-bottom: 24px !important;
        }
        .customers-page .page-heading h1 {
            margin-bottom: 6px !important;
            letter-spacing: -0.35px !important;
        }
        .customers-page .page-heading p {
            line-height: 1.5 !important;
        }
        .customer-stats {
            gap: 16px !important;
            margin: 24px 0 !important;
        }
        .customer-stat {
            box-sizing: border-box !important;
            min-height: 126px !important;
            height: 126px !important;
            padding: 22px 24px !important;
            border-radius: 12px !important;
        }
        .customer-stat .stat-label {
            margin-bottom: 7px !important;
            line-height: 1.4 !important;
        }
        .customer-stat strong {
            line-height: 1.25 !important;
        }
        .customer-stat small {
            margin-top: 7px !important;
            line-height: 1.4 !important;
        }
        .customer-search-panel {
            box-sizing: border-box !important;
            min-height: 92px !important;
            height: 92px !important;
            margin-bottom: 24px !important;
            padding: 18px 24px !important;
            display: flex !important;
            align-items: center !important;
            border-radius: 12px !important;
        }
        .customer-search-wrap {
            width: 100% !important;
        }
        #customerSearch {
            box-sizing: border-box !important;
            height: 42px !important;
            min-height: 42px !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        .customer-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 16px !important;
            align-items: stretch !important;
        }
        .customer-card {
            box-sizing: border-box !important;
            height: auto !important;
            min-height: 0 !important;
            padding: 24px !important;
            border-radius: 12px !important;
            display: flex !important;
            flex-direction: column !important;
        }
        .customer-card-top {
            gap: 12px !important;
            margin-bottom: 18px !important;
        }
        .customer-avatar {
            flex: 0 0 48px !important;
            width: 48px !important;
            height: 48px !important;
        }
        .customer-contact {
            gap: 10px !important;
            margin-bottom: 18px !important;
        }
        .customer-contact-row {
            line-height: 1.45 !important;
        }
        .customer-divider {
            margin-bottom: 14px !important;
        }
        .customer-metrics {
            gap: 10px !important;
            margin-bottom: 18px !important;
        }
        .customer-metric {
            line-height: 1.45 !important;
        }
        .customer-details {
            margin-top: 0 !important;
            min-height: 38px !important;
            padding: 9px 12px !important;
        }
        @media (max-width: 1100px) {
            .customer-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }
        @media (max-width: 900px) {
            .admin-shell > main.admin-content.customers-page {
                padding: 22px 20px 32px !important;
            }
        }
        @media (max-width: 650px) {
            .admin-shell > main.admin-content.customers-page {
                padding: 18px 16px 28px !important;
            }
            .customer-stats,
            .customer-grid {
                grid-template-columns: 1fr !important;
            }
            .customer-stat {
                height: auto !important;
                min-height: 0 !important;
            }
            .customer-search-panel {
                height: auto !important;
                min-height: 0 !important;
                padding: 16px !important;
            }
            .customer-card {
                height: auto !important;
                min-height: 0 !important;
                padding: 20px !important;
            }
        }


        /* Fix View Details button: compact height and centered label */
        a.customer-details,
        .customer-card a.customer-details {
            box-sizing: border-box !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100% !important;
            height: 38px !important;
            min-height: 38px !important;
            padding: 0 12px !important;
            margin-top: auto !important;
            line-height: 1 !important;
            text-align: center !important;
            white-space: nowrap !important;
        }

        @media (max-width: 650px) {
            a.customer-details,
            .customer-card a.customer-details {
                height: 38px !important;
                min-height: 38px !important;
                margin-top: 18px !important;
            }
        }



        button.customer-details {
            box-sizing: border-box !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100% !important;
            height: 38px !important;
            min-height: 38px !important;
            margin-top: auto !important;
            padding: 0 12px !important;
            line-height: 1 !important;
            text-align: center !important;
            cursor: pointer !important;
        }

        /* Customer details modal */
        .customer-details {
            cursor: pointer;
            font-family: inherit;
        }
        .customer-modal[hidden] { display: none !important; }
        .customer-modal {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .customer-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(28, 24, 22, .52);
            backdrop-filter: blur(2px);
        }
        .customer-modal-dialog {
            position: relative;
            z-index: 1;
            width: min(100%, 672px);
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            padding: 24px;
            color: var(--customer-brown-dark);
            background: #fff;
            border-radius: 13px;
            box-shadow: 0 20px 60px rgba(35, 25, 20, .2);
        }
        .customer-modal-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 26px;
        }
        .customer-modal-heading h2 {
            margin: 0;
            color: var(--customer-brown);
            font-size: 20px;
            font-weight: 700;
        }
        .customer-modal-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            padding: 0;
            border: 0;
            border-radius: 7px;
            color: var(--customer-brown);
            background: transparent;
            font-size: 25px;
            line-height: 1;
            cursor: pointer;
        }
        .customer-modal-close:hover { background: #f7f1ed; }
        .customer-modal-profile {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
        }
        .customer-modal-avatar {
            display: flex;
            flex: 0 0 64px;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            color: #fff;
            background: var(--customer-brown);
            font-size: 22px;
            font-weight: 700;
        }
        .customer-modal-person { min-width: 0; }
        .customer-modal-person h3 {
            margin: 0 0 4px;
            overflow-wrap: anywhere;
            font-size: 20px;
            font-weight: 700;
        }
        .customer-modal-id {
            margin-bottom: 8px;
            color: var(--customer-muted);
            font-size: 14px;
        }
        .customer-modal-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 99px;
            color: #087443;
            background: #dcfce7;
            font-size: 12px;
        }
        .customer-modal-status.is-new {
            color: #925b00;
            background: #fff1cf;
        }
        .customer-modal-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px 28px;
            margin-bottom: 28px;
        }
        .customer-modal-field { min-width: 0; }
        .customer-modal-field span {
            display: block;
            margin-bottom: 7px;
            color: var(--customer-muted);
            font-size: 14px;
        }
        .customer-modal-field strong {
            display: block;
            overflow-wrap: anywhere;
            color: var(--customer-brown);
            font-size: 16px;
            font-weight: 500;
            line-height: 1.4;
        }
        .customer-modal-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        .customer-modal-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 43px;
            padding: 10px 14px;
            border: 1px solid var(--customer-border);
            border-radius: 8px;
            color: var(--customer-brown);
            background: #fff;
            font: inherit;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
        }
        .customer-modal-action:hover { background: #faf6f3; border-color: #cbb7aa; }
        .customer-modal-action:focus-visible,
        .customer-modal-close:focus-visible {
            outline: 3px solid rgba(112, 83, 68, .25);
            outline-offset: 2px;
        }
        .customer-modal-action[aria-disabled="true"] {
            opacity: .45;
            pointer-events: none;
        }
        body.customer-modal-open { overflow: hidden; }
        @media (max-width: 560px) {
            .customer-modal { padding: 12px; }
            .customer-modal-dialog { padding: 20px; max-height: calc(100vh - 24px); }
            .customer-modal-fields { gap: 18px 14px; }
            .customer-modal-field strong { font-size: 14px; }
            .customer-modal-profile { gap: 12px; margin-bottom: 22px; }
            .customer-modal-avatar { flex-basis: 54px; width: 54px; height: 54px; font-size: 19px; }
            .customer-modal-person h3 { font-size: 18px; }
        }
        @media (max-width: 390px) {
            .customer-modal-fields { grid-template-columns: 1fr; }
            .customer-modal-actions { grid-template-columns: 1fr; }
        }

    
        /* Compact action buttons for a cleaner, more balanced layout */
        button.customer-details,
        .customer-card button.customer-details {
            box-sizing: border-box !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100% !important;
            height: 34px !important;
            min-height: 34px !important;
            padding: 0 10px !important;
            margin-top: auto !important;
            line-height: 1.2 !important;
            font-size: 13px !important;
            border-radius: 7px !important;
        }

        .customer-modal-actions {
            gap: 10px !important;
            margin-top: 2px !important;
        }

        .customer-modal-action {
            box-sizing: border-box !important;
            min-height: 36px !important;
            height: 36px !important;
            padding: 6px 12px !important;
            gap: 7px !important;
            border-radius: 7px !important;
            font-size: 14px !important;
            line-height: 1.2 !important;
        }

        .customer-modal-action svg {
            width: 15px !important;
            height: 15px !important;
            flex: 0 0 15px !important;
        }

        @media (max-width: 560px) {
            button.customer-details,
            .customer-card button.customer-details {
                height: 34px !important;
                min-height: 34px !important;
            }

            .customer-modal-action {
                min-height: 36px !important;
                height: 36px !important;
                padding: 6px 8px !important;
                font-size: 13px !important;
            }
        }

    </style>
</head>
<body>
<div class="admin-shell">
    <div class="admin-mobile">
        <b style="color: var(--brown)">SnapVenture Admin</b>
        <button class="btn btn-outline" data-admin-menu aria-label="Open navigation">☰</button>
    </div>
    <?php $activePage = 'customers'; include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="admin-content customers-page">
        <header class="page-heading">
            <h1>Customer Management</h1>
            <p>Manage your customer relationships and track engagement</p>
        </header>

        <section class="customer-stats" aria-label="Customer overview">
            <article class="customer-stat">
                <span class="stat-label">Total Customers</span>
                <strong><?= number_format($totalCustomers) ?></strong>
                <small>Registered customers</small>
            </article>
            <article class="customer-stat">
                <span class="stat-label">Active Customers</span>
                <strong><?= number_format($activeCustomers) ?></strong>
                <small>More than one booking</small>
            </article>
            <article class="customer-stat">
                <span class="stat-label">Total Bookings</span>
                <strong><?= number_format($totalBookings) ?></strong>
                <small>Across all customers</small>
            </article>
            <article class="customer-stat">
                <span class="stat-label">Avg. Bookings</span>
                <strong><?= number_format($averageBookings, 1) ?></strong>
                <small>Per customer</small>
            </article>
        </section>

        <section class="customer-search-panel" aria-label="Search customers">
            <div class="customer-search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 4.2 4.2"></path></svg>
                <input id="customerSearch" type="search" placeholder="Search by name, email, or phone..." autocomplete="off" />
            </div>
        </section>

        <section class="customer-grid" id="customerGrid" aria-label="Customer list">
            <?php if (!$customers): ?>
                <div class="customer-empty">No customers yet.</div>
            <?php else: ?>
                <?php foreach ($customers as $c): ?>
                    <?php
                        $name = (string) $c['full_name'];
                        $email = (string) ($c['email'] ?? '');
                        $phone = (string) ($c['phone'] ?? '');
                        $bookingCount = (int) $c['total_bookings'];
                        $status = $bookingCount > 1 ? 'active' : 'new';
                        $customerId = (int) $c['customer_id'];
                        $searchText = mb_strtolower($name . ' ' . $email . ' ' . $phone);
                    ?>
                    <article class="customer-card" data-customer-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="customer-card-top">
                            <div class="customer-avatar" aria-hidden="true"><?= htmlspecialchars(customerInitials($name), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="customer-identity">
                                <h2 class="customer-name" title="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h2>
                                <div class="customer-id">CUST-<?= str_pad((string) $customerId, 3, '0', STR_PAD_LEFT) ?></div>
                            </div>
                            <span class="customer-status <?= $status === 'new' ? 'new' : 'active' ?>"><?= $status ?></span>
                        </div>

                        <div class="customer-contact">
                            <div class="customer-contact-row">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path></svg>
                                <span title="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($email !== '' ? $email : 'No email provided', ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div class="customer-contact-row">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M7 3h3l2 5-2 2a15 15 0 0 0 4 4l2-2 5 2v3c0 1.1-.9 2-2 2C10 19 5 14 5 5c0-1.1.9-2 2-2Z"></path></svg>
                                <span><?= htmlspecialchars($phone !== '' ? $phone : 'No phone provided', ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>

                        <div class="customer-divider"></div>
                        <div class="customer-metrics">
                            <div class="customer-metric"><span>Total Bookings:</span><strong><?= number_format($bookingCount) ?></strong></div>
                            <div class="customer-metric"><span>Total Spent:</span><strong><?= peso($c['lifetime_value']) ?></strong></div>
                        </div>
                        <button
                            class="customer-details"
                            type="button"
                            data-customer-modal-open
                            data-id="CUST-<?= str_pad((string) $customerId, 3, '0', STR_PAD_LEFT) ?>"
                            data-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                            data-initials="<?= htmlspecialchars(customerInitials($name), ENT_QUOTES, 'UTF-8') ?>"
                            data-email="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                            data-phone="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
                            data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                            data-bookings="<?= number_format($bookingCount) ?>"
                            data-spent="<?= htmlspecialchars(peso($c['lifetime_value']), ENT_QUOTES, 'UTF-8') ?>"
                        >View Details</button>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="customer-empty" id="customerNoResults" hidden>No customers match your search.</div>
        </section>
    </main>
</div>

<div class="customer-modal" id="customerDetailsModal" hidden>
    <div class="customer-modal-backdrop" data-customer-modal-close></div>
    <section
        class="customer-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="customerModalTitle"
        aria-describedby="customerModalDescription"
        tabindex="-1"
    >
        <div class="customer-modal-heading">
            <h2 id="customerModalTitle">Customer Details</h2>
            <button class="customer-modal-close" type="button" data-customer-modal-close aria-label="Close customer details">&times;</button>
        </div>

        <div class="customer-modal-profile">
            <div class="customer-modal-avatar" id="customerModalAvatar">CU</div>
            <div class="customer-modal-person">
                <h3 id="customerModalName">Customer name</h3>
                <div class="customer-modal-id" id="customerModalId">CUST-000</div>
                <span class="customer-modal-status" id="customerModalStatus">new</span>
            </div>
        </div>

        <p id="customerModalDescription" hidden>Contact information and booking summary for this customer.</p>

        <div class="customer-modal-fields">
            <div class="customer-modal-field">
                <span>Email</span>
                <strong id="customerModalEmail">—</strong>
            </div>
            <div class="customer-modal-field">
                <span>Phone</span>
                <strong id="customerModalPhone">—</strong>
            </div>
            <div class="customer-modal-field">
                <span>Messenger</span>
                <strong id="customerModalMessenger">Not provided</strong>
            </div>
            <div class="customer-modal-field">
                <span>Member Since</span>
                <strong id="customerModalMemberSince">Not available</strong>
            </div>
            <div class="customer-modal-field">
                <span>Total Bookings</span>
                <strong id="customerModalBookings">0</strong>
            </div>
            <div class="customer-modal-field">
                <span>Total Spent</span>
                <strong id="customerModalSpent">₱0.00</strong>
            </div>
            <div class="customer-modal-field">
                <span>Last Booking</span>
                <strong id="customerModalLastBooking">Not available</strong>
            </div>
            <div class="customer-modal-field">
                <span>Favorite Service</span>
                <strong id="customerModalFavoriteService">Not available</strong>
            </div>
        </div>

        <div class="customer-modal-actions">
            <a class="customer-modal-action" id="customerModalEmailAction" href="#" aria-disabled="true">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path></svg>
                Send Email
            </a>
            <a class="customer-modal-action" id="customerModalCallAction" href="#" aria-disabled="true">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 3h3l2 5-2 2a15 15 0 0 0 4 4l2-2 5 2v3c0 1.1-.9 2-2 2C10 19 5 14 5 5c0-1.1.9-2 2-2Z"></path></svg>
                Call
            </a>
        </div>
    </section>
</div>

<script src="../../script.js"></script>
<script>
    (() => {
        const searchInput = document.getElementById('customerSearch');
        const cards = Array.from(document.querySelectorAll('[data-customer-search]'));
        const noResults = document.getElementById('customerNoResults');
        if (!searchInput) return;
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLocaleLowerCase();
            let visibleCount = 0;
            cards.forEach(card => {
                const matches = card.dataset.customerSearch.includes(query);
                card.hidden = !matches;
                if (matches) visibleCount++;
            });
            if (noResults) noResults.hidden = visibleCount !== 0 || cards.length === 0;
        });
    })();

    (() => {
        const modal = document.getElementById('customerDetailsModal');
        if (!modal) return;
        const dialog = modal.querySelector('.customer-modal-dialog');
        const closeButtons = modal.querySelectorAll('[data-customer-modal-close]');
        const openButtons = document.querySelectorAll('[data-customer-modal-open]');
        const emailAction = document.getElementById('customerModalEmailAction');
        const callAction = document.getElementById('customerModalCallAction');
        let lastTrigger = null;

        const setText = (id, value, fallback = '—') => {
            const element = document.getElementById(id);
            if (element) element.textContent = value && String(value).trim() ? value : fallback;
        };

        const setAction = (element, scheme, value) => {
            if (!element) return;
            if (value && String(value).trim()) {
                element.href = scheme + value.trim();
                element.removeAttribute('aria-disabled');
                element.removeAttribute('tabindex');
            } else {
                element.href = '#';
                element.setAttribute('aria-disabled', 'true');
                element.setAttribute('tabindex', '-1');
            }
        };

        const openModal = (trigger) => {
            lastTrigger = trigger;
            const data = trigger.dataset;
            setText('customerModalAvatar', data.initials, 'CU');
            setText('customerModalName', data.name, 'Customer');
            setText('customerModalId', data.id, 'CUST-000');
            setText('customerModalEmail', data.email, 'No email provided');
            setText('customerModalPhone', data.phone, 'No phone provided');
            setText('customerModalBookings', data.bookings, '0');
            setText('customerModalSpent', data.spent, '₱0.00');

            const status = document.getElementById('customerModalStatus');
            if (status) {
                const statusText = data.status || 'new';
                status.textContent = statusText;
                status.classList.toggle('is-new', statusText === 'new');
            }

            // These fields are shown in the requested design, but the current page query
            // does not retrieve their source values. Do not invent customer information.
            setText('customerModalMessenger', '', 'Not provided');
            setText('customerModalMemberSince', '', 'Not available');
            setText('customerModalLastBooking', '', 'Not available');
            setText('customerModalFavoriteService', '', 'Not available');

            setAction(emailAction, 'mailto:', data.email || '');
            setAction(callAction, 'tel:', data.phone || '');

            modal.hidden = false;
            document.body.classList.add('customer-modal-open');
            dialog.focus();
        };

        const closeModal = () => {
            if (modal.hidden) return;
            modal.hidden = true;
            document.body.classList.remove('customer-modal-open');
            if (lastTrigger) lastTrigger.focus();
        };

        openButtons.forEach(button => button.addEventListener('click', () => openModal(button)));
        closeButtons.forEach(button => button.addEventListener('click', closeModal));
        document.addEventListener('keydown', event => {
            if (modal.hidden) return;
            if (event.key === 'Escape') {
                closeModal();
                return;
            }
            if (event.key === 'Tab') {
                const focusable = Array.from(modal.querySelectorAll('button:not([disabled]), a[href]:not([aria-disabled="true"])'));
                if (!focusable.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
    })();

</script>
</body>
</html>
