<?php
// public/actions.php
<link rel="icon" type="image/png" href="/img/favicon.png">

session_start();
require_once('../src/db.php');

$do = $_GET['do'] ?? '';
$pid = $_SESSION['player_id'] ?? null;
$lid = $_SESSION['lobby_id'] ?? null;

if (!$pid || !$lid) {
    if ($do !== '') { header("Location: play.php"); exit; }
}

if ($do === 'start') {
    $new_sit = $pdo->query("SELECT id FROM situations ORDER BY RANDOM() LIMIT 1")->fetchColumn();
    $stmt = $pdo->prepare("UPDATE lobbies SET status = 'picking', current_situation_id = ? WHERE id = ?");
    $stmt->execute([$new_sit, $lid]);
    
    $players = $pdo->prepare("SELECT id FROM players WHERE lobby_id = ?");
    $players->execute([$lid]);
    $all_p = $players->fetchAll();

    foreach($all_p as $p) {
        $hero_ids = $pdo->query("SELECT id FROM characters ORDER BY RANDOM() LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
        $perk_ids = $pdo->query("SELECT id FROM strengths ORDER BY RANDOM() LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->prepare("UPDATE players SET 
            has_submitted = false, 
            char_id = null, 
            strength_id = null, 
            weakness_id = null, 
            voted_for_id = null,
            draft_heroes = ?,
            draft_perks = ?
            WHERE id = ?");
        $stmt->execute([implode(',', $hero_ids), implode(',', $perk_ids), $p['id']]);
    }
        
    header("Location: play.php");
    exit;
}

if ($do === 'submit') {
    $char = $_POST['char'] ?? null;
    $str = $_POST['str'] ?? null;
    if ($char && $str) {
        $stmt = $pdo->prepare("UPDATE players SET char_id = ?, strength_id = ?, has_submitted = true WHERE id = ?");
        $stmt->execute([$char, $str, $pid]);
    }

    $totalInLobby = $pdo->query("SELECT COUNT(*) FROM players WHERE lobby_id = $lid")->fetchColumn();
    $readyCount = $pdo->query("SELECT COUNT(*) FROM players WHERE lobby_id = $lid AND has_submitted = true")->fetchColumn();
    
    if ($readyCount >= $totalInLobby && $totalInLobby >= 2) {
        $players = $pdo->query("SELECT id FROM players WHERE lobby_id = $lid")->fetchAll();
        foreach($players as $p) {
            $wk = $pdo->query("SELECT id FROM weaknesses ORDER BY RANDOM() LIMIT 1")->fetchColumn();
            $pdo->query("UPDATE players SET weakness_id = $wk WHERE id = {$p['id']}");
        }
        $pdo->query("UPDATE lobbies SET status = 'voting' WHERE id = $lid");
    }
    header("Location: play.php");
    exit;
}

if ($do === 'vote') {
    $target_id = (int)$_GET['target'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE lobby_id = ?");
    $stmt->execute([$lid]);
    $playerCount = $stmt->fetchColumn();

    // Only allow self-voting if exactly 2 players are in the lobby
    if ($playerCount > 2 && $target_id == $pid) {
        header("Location: play.php?error=noselfvote");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE players SET voted_for_id = ? WHERE id = ? AND voted_for_id IS NULL");
    $stmt->execute([$target_id, $pid]);

    $totalInLobby = $playerCount;
    $voteCount = $pdo->query("SELECT COUNT(*) FROM players WHERE lobby_id = $lid AND voted_for_id IS NOT NULL")->fetchColumn();
    
    if ($voteCount >= $totalInLobby && $totalInLobby >= 2) {
        $votes = $pdo->query("SELECT voted_for_id, COUNT(*) as qty FROM players WHERE lobby_id = $lid GROUP BY voted_for_id ORDER BY qty DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($votes) > 0) {
            $maxVotes = $votes[0]['qty'];
            $winners = array_filter($votes, function($v) use ($maxVotes) { return $v['qty'] == $maxVotes; });

            if (count($winners) === 1) {
                $winner_id = $winners[0]['voted_for_id'];
                $stmt = $pdo->prepare("SELECT s.points FROM players p JOIN strengths s ON p.strength_id = s.id WHERE p.id = ?");
                $stmt->execute([$winner_id]);
                $perk_pts = (int)$stmt->fetchColumn();
                $stmt = $pdo->prepare("UPDATE players SET score = score + ? WHERE id = ?");
                $stmt->execute([10 + $perk_pts, $winner_id]);
            }
        }
        $pdo->query("UPDATE lobbies SET status = 'result' WHERE id = $lid");
    }
    header("Location: play.php");
    exit;
}

if ($do === 'leave') {
    $pdo->prepare("DELETE FROM players WHERE id = ?")->execute([$pid]);
    session_destroy();
    header("Location: play.php");
    exit;
}