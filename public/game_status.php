<?php
// public/game_status.php
session_start();
require_once('../src/db.php');

$lid = $_SESSION['lobby_id'] ?? null;
$pid = $_SESSION['player_id'] ?? null;

if (!$lid || !$pid) exit(json_encode(['should_reload' => false]));

$game = $pdo->query("SELECT status FROM lobbies WHERE id = $lid")->fetch();
$players = $pdo->query("SELECT COUNT(*) FROM players WHERE lobby_id = $lid")->fetchColumn();

// If status changes or a new player joins, tell the browser to refresh
$should_reload = false;
if ($game['status'] !== ($_SESSION['last_status'] ?? '')) {
    $should_reload = true;
    $_SESSION['last_status'] = $game['status'];
}

// Special case: if we are in 'waiting' but a second player joined
if ($game['status'] === 'waiting' && $players > ($_SESSION['last_player_count'] ?? 1)) {
    $should_reload = true;
}
$_SESSION['last_player_count'] = $players;

echo json_encode(['should_reload' => $should_reload]);