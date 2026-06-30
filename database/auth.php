<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit;
}
