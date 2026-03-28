<?php
require_once('../src/db.php');
session_start();

$lobby_id = $_SESSION['lobby_id'] ?? null;
if (!$lobby_id) exit;

// Get Lobby Status
$stmt = $pdo->prepare("SELECT status, join_code FROM lobbies WHERE id = ?");
$stmt->execute([$lobby_id]);
$lobby = $stmt->fetch(PDO::FETCH_ASSOC);

// Get Players
$stmt = $pdo->prepare("SELECT username, has_submitted, score FROM players WHERE lobby_id = ?");
$stmt->execute([$lobby_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['lobby' => $lobby, 'players' => $players]);