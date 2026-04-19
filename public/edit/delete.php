<?php
require_once('../../src/db.php');
<link rel="icon" type="image/png" href="/img/favicon.png">

session_start();

// Security check: must be logged in to delete
if (!isset($_SESSION['authenticated'])) {
    header("Location: index.php");
    exit;
}

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

// Array of allowed tables to prevent SQL injection via the 'type' parameter
$allowed_types = ['characters', 'strengths', 'weaknesses', 'situations'];

if (in_array($type, $allowed_types) && is_numeric($id)) {
    try {
        $stmt = $pdo->prepare("DELETE FROM $type WHERE id = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        // Optional: Log error
    }
}

// Redirect back to the editor for that specific category
header("Location: manage.php?type=$type");
exit;