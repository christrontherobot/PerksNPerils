<?php
// public/actions.php
session_start();
require_once('../src/db.php');

$do = $_GET['do'] ?? '';
$pid = $_SESSION['player_id'];
$lid = $_SESSION['lobby_id'];

if ($do === 'start') {
    $pdo->query("UPDATE players SET has_submitted = false, char_id = null, strength_id = null WHERE lobby_id = $lid");
    $pdo->query("UPDATE lobbies SET status = 'picking' WHERE id = $lid");
    header("Location: play.php");
}

if ($do === 'submit') {
    $char = $_POST['char'];
    $str = $_POST['str'];
    $stmt = $pdo->prepare("UPDATE players SET char_id = ?, strength_id = ?, has_submitted = true WHERE id = ?");
    $stmt->execute([$char, $str, $pid]);

    // Check if both players are ready
    $readyCount = $pdo->query("SELECT COUNT(*) FROM players WHERE lobby_id = $lid AND has_submitted = true")->fetchColumn();
    if ($readyCount >= 2) {
        // Pick a situation
        $sit = $pdo->query("SELECT id FROM situations ORDER BY RANDOM() LIMIT 1")->fetchColumn();
        $pdo->query("UPDATE lobbies SET status = 'voting', current_situation_id = $sit WHERE id = $lid");
        
        // Assign random weaknesses (Perils)
        $players = $pdo->query("SELECT id FROM players WHERE lobby_id = $lid")->fetchAll();
        foreach($players as $p) {
            $wk = $pdo->query("SELECT id FROM weaknesses ORDER BY RANDOM() LIMIT 1")->fetchColumn();
            $pdo->query("UPDATE players SET weakness_id = $wk WHERE id = {$p['id']}");
        }
    }
    header("Location: play.php");
}