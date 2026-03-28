<?php
// public/actions.php
session_start();
require_once('../src/db.php');

$do = $_GET['do'] ?? '';
$pid = $_SESSION['player_id'];
$lid = $_SESSION['lobby_id'];

if (!$pid || !$lid) { header("Location: play.php"); exit; }

if ($do === 'start') {
    // Reset round data but keep persistent 'score'
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
        // 1. Pick a random Situation
        $sit = $pdo->query("SELECT id FROM situations ORDER BY RANDOM() LIMIT 1")->fetchColumn();
        
        // 2. Assign random Weaknesses (Perils) to both players
        $players = $pdo->query("SELECT id FROM players WHERE lobby_id = $lid")->fetchAll();
        foreach($players as $p) {
            $wk = $pdo->query("SELECT id FROM weaknesses ORDER BY RANDOM() LIMIT 1")->fetchColumn();
            $pdo->query("UPDATE players SET weakness_id = $wk WHERE id = {$p['id']}");
        }

        // 3. Set Lobby to Voting
        $stmt = $pdo->prepare("UPDATE lobbies SET status = 'voting', current_situation_id = ? WHERE id = ?");
        $stmt->execute([$sit, $lid]);
    }
    header("Location: play.php");
}

if ($do === 'vote') {
    $target_id = (int)$_GET['target'];
    
    // Mark that this player has voted
    $stmt = $pdo->prepare("UPDATE players SET voted_for_id = ? WHERE id = ?");
    $stmt->execute([$target_id, $pid]);

    // Check if both have voted
    $voteCount = $pdo->query("SELECT COUNT(*) FROM players WHERE lobby_id = $lid AND voted_for_id IS NOT NULL")->fetchColumn();
    
    if ($voteCount >= 2) {
        // Determine Winner
        $votes = $pdo->query("SELECT voted_for_id, COUNT(*) as count FROM players WHERE lobby_id = $lid GROUP BY voted_for_id")->fetchAll(PDO::FETCH_ASSOC);
        
        $winner_id = null;
        if (count($votes) == 1) {
            // Both voted for the same person (Unlikely but possible)
            $winner_id = $votes[0]['voted_for_id'];
        } elseif (count($votes) == 2) {
            if ($votes[0]['count'] > $votes[1]['count']) {
                $winner_id = $votes[0]['voted_for_id'];
            } elseif ($votes[1]['count'] > $votes[0]['count']) {
                $winner_id = $votes[1]['voted_for_id'];
            } else {
                // TIE: No winner_id assigned
                $winner_id = null;
            }
        }

        if ($winner_id) {
            // Get Perk Points for the winner
            $stmt = $pdo->prepare("SELECT s.points FROM players p JOIN strengths s ON p.strength_id = s.id WHERE p.id = ?");
            $stmt->execute([$winner_id]);
            $perk_pts = (int)$stmt->fetchColumn();
            
            $total_gain = 10 + $perk_pts;
            $stmt = $pdo->prepare("UPDATE players SET score = score + ? WHERE id = ?");
            $stmt->execute([$total_gain, $winner_id]);
        }
        
        $pdo->query("UPDATE lobbies SET status = 'result' WHERE id = $lid");
    }
    header("Location: play.php");
}