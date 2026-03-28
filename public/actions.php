<?php
// public/actions.php
session_start();
require_once('../src/db.php');

$do = $_GET['do'] ?? '';
$pid = $_SESSION['player_id'] ?? null;
$lid = $_SESSION['lobby_id'] ?? null;

if (!$pid || !$lid) { header("Location: play.php"); exit; }

if ($do === 'start') {
    // Reset round-specific data but keep persistent 'score'
    $pdo->query("UPDATE players SET has_submitted = false, char_id = null, strength_id = null, weakness_id = null, voted_for_id = null WHERE lobby_id = $lid");
    $pdo->query("UPDATE lobbies SET status = 'picking', current_situation_id = null WHERE id = $lid");
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
        $sit = $pdo->query("SELECT id FROM situations ORDER BY RANDOM() LIMIT 1")->fetchColumn();
        
        // Assign random Perils (Weaknesses)
        $players = $pdo->query("SELECT id FROM players WHERE lobby_id = $lid")->fetchAll();
        foreach($players as $p) {
            $wk = $pdo->query("SELECT id FROM weaknesses ORDER BY RANDOM() LIMIT 1")->fetchColumn();
            $pdo->query("UPDATE players SET weakness_id = $wk WHERE id = {$p['id']}");
        }

        $stmt = $pdo->prepare("UPDATE lobbies SET status = 'voting', current_situation_id = ? WHERE id = ?");
        $stmt->execute([$sit, $lid]);
    }
    header("Location: play.php");
}

if ($do === 'vote') {
    $target_id = (int)$_GET['target'];
    $stmt = $pdo->prepare("UPDATE players SET voted_for_id = ? WHERE id = ?");
    $stmt->execute([$target_id, $pid]);

    // Check if both have voted
    $voteCount = $pdo->query("SELECT COUNT(*) FROM players WHERE lobby_id = $lid AND voted_for_id IS NOT NULL")->fetchColumn();
    
    if ($voteCount >= 2) {
        $votes = $pdo->query("SELECT voted_for_id, COUNT(*) as qty FROM players WHERE lobby_id = $lid GROUP BY voted_for_id ORDER BY qty DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        // Award points if there is a clear winner (not a tie)
        if (count($votes) === 1 || (count($votes) > 1 && $votes[0]['qty'] > $votes[1]['qty'])) {
            $winner_id = $votes[0]['voted_for_id'];
            $stmt = $pdo->prepare("SELECT s.points FROM players p JOIN strengths s ON p.strength_id = s.id WHERE p.id = ?");
            $stmt->execute([$winner_id]);
            $perk_pts = (int)$stmt->fetchColumn();
            
            $total_gain = 10 + $perk_pts; // 10 base + perk
            $stmt = $pdo->prepare("UPDATE players SET score = score + ? WHERE id = ?");
            $stmt->execute([$total_gain, $winner_id]);
        }
        
        $pdo->query("UPDATE lobbies SET status = 'result' WHERE id = $lid");
    }
    header("Location: play.php");
}