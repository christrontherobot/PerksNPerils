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

$stmt = $pdo->prepare("SELECT status, current_situation_id FROM lobbies WHERE id = ?");
$stmt->execute([$lid]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COUNT(*) as total, COUNT(voted_for_id) as voted, COUNT(CASE WHEN has_submitted THEN 1 END) as submitted FROM players WHERE lobby_id = ?");
$stmt->execute([$lid]);
$p_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Detects status change, situation change, OR a change in player count/readiness
$current_state_string = $game['status'] . "_" . $game['current_situation_id'] . "_" . $p_stats['total'] . "_" . $p_stats['submitted'] . "_" . $p_stats['voted'];

$should_reload = false;
if (isset($_SESSION['last_state_string']) && $_SESSION['last_state_string'] !== $current_state_string) {
    $should_reload = true;
}

$_SESSION['last_state_string'] = $current_state_string;

echo json_encode([
    'should_reload' => $should_reload,
    'status' => $game['status']
]);