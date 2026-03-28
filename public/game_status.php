<?php
// public/game_status.php
session_start();
require_once('../src/db.php');

$lid = $_SESSION['lobby_id'] ?? null;
$pid = $_SESSION['player_id'] ?? null;

if (!$lid || !$pid) {
    echo json_encode(['should_reload' => false]);
    exit;
}

// Fetch the current lobby status and a "hash" of player submission status
$stmt = $pdo->prepare("SELECT status, current_situation_id FROM lobbies WHERE id = ?");
$stmt->execute([$lid]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE lobby_id = ?");
$stmt->execute([$lid]);
$player_count = $stmt->fetchColumn();

// Create a unique string representing the current state
$current_state_string = $game['status'] . "_" . $game['current_situation_id'] . "_" . $player_count;

$should_reload = false;
if (isset($_SESSION['last_state_string']) && $_SESSION['last_state_string'] !== $current_state_string) {
    $should_reload = true;
}

$_SESSION['last_state_string'] = $current_state_string;

echo json_encode([
    'should_reload' => $should_reload,
    'status' => $game['status']
]);