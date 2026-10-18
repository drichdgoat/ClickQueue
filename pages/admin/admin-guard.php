<?php
/**
 * SnapVenture Admin - auth guard.
 * require_once this at the very top of every protected admin page
 * (before any HTML output).
 */
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: auth/login.php');
    exit;
}