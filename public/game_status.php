<?php
// public/game_status.php
session_start();
require_once('../src/db.php');

$lid = $_SESSION['lobby_id'] ?? null;
$pid = $_SESSION['player_id'] ?? null;

if (!$lid) exit(json_encode(['error' => 'No lobby']));

$game = $pdo->query("SELECT status, join_code FROM lobbies WHERE id = $lid")->fetch();
$me = $pdo->query("SELECT has_submitted FROM players WHERE id = $pid")->fetch();

// Logic to force a reload if the game state has moved forward
$should_reload = false;
if ($game['status'] === 'voting' && $_SESSION['last_status'] !== 'voting') {
    $should_reload = true;
}
$_SESSION['last_status'] = $game['status'];

echo json_encode([
    'status_text' => "Lobby: {$game['join_code']} | Status: " . ucfirst($game['status']),
    'should_reload' => $should_reload
]);