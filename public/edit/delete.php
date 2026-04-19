<?php
require_once('../../src/db.php');

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
        // ADDED LOGIC FOR SITUATIONS:
        // This clears the reference in the lobbies table so the situation is free to be deleted.
        if ($type === 'situations') {
            $stmt = $pdo->prepare("UPDATE lobbies SET current_situation_id = NULL WHERE current_situation_id = ?");
            $stmt->execute([$id]);
        }

        $stmt = $pdo->prepare("DELETE FROM $type WHERE id = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        // Temporary: uncomment this to see the exact error if it still fails
        // die($e->getMessage()); 
    }
}

// Redirect back to the editor for that specific category
header("Location: manage.php?type=$type");
exit;