<?php
// public/actions.php
session_start();
require_once('../src/db.php');

$do = $_GET['do'] ?? '';
$pid = $_SESSION['player_id'] ?? null;
$lid = $_SESSION['lobby_id'] ?? null;

// Only redirect if we are trying to do an action without being logged in
if (!$pid || !$lid) {
    if ($do !== '') {
        header("Location: play.php");
        exit;
    }
}

if ($do === 'start') {
    $pdo->query("UPDATE players SET has_submitted = false, char_id = null, strength_id = null, weakness_id = null, voted_for_id = null WHERE lobby_id = $lid");
    $pdo->query("UPDATE lobbies SET status = 'picking', current_situation_id = null WHERE id = $lid");
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

    $readyCount = $pdo->query("SELECT COUNT(*) FROM players WHERE lobby_id = $lid AND has_submitted = true")->fetchColumn();
    if ($readyCount >= 2) {
        $sit = $pdo->query("SELECT id FROM situations ORDER BY RANDOM() LIMIT 1")->fetchColumn();
        $players = $pdo->query("SELECT id FROM players WHERE lobby_id = $lid")->fetchAll();
        foreach($players as $p) {
            $wk = $pdo->query("SELECT id FROM weaknesses ORDER BY RANDOM() LIMIT 1")->fetchColumn();
            $pdo->query("UPDATE players SET weakness_id = $wk WHERE id = {$p['id']}");
        }
        $stmt = $pdo->prepare("UPDATE lobbies SET status = 'voting', current_situation_id = ? WHERE id = ?");
        $stmt->execute([$sit, $lid]);
    }
    header("Location: play.php");
    exit;
}

if ($do === 'vote') {
    $target_id = (int)$_GET['target'];
    $stmt = $pdo->prepare("UPDATE players SET voted_for_id = ? WHERE id = ?");
    $stmt->execute([$target_id, $pid]);

    $voteCount = $pdo->query("SELECT COUNT(*) FROM players WHERE lobby_id = $lid AND voted_for_id IS NOT NULL")->fetchColumn();
    if ($voteCount >= 2) {
        $votes = $pdo->query("SELECT voted_for_id, COUNT(*) as qty FROM players WHERE lobby_id = $lid GROUP BY voted_for_id ORDER BY qty DESC")->fetchAll(PDO::FETCH_ASSOC);
        if (count($votes) === 1 || (count($votes) > 1 && $votes[0]['qty'] > $votes[1]['qty'])) {
            $winner_id = $votes[0]['voted_for_id'];
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
    exit;
}

if ($do === 'leave') {
    $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
    $stmt->execute([$pid]);
    $remaining = $pdo->prepare("SELECT COUNT(*) FROM players WHERE lobby_id = ?");
    $remaining->execute([$lid]);
    if ($remaining->fetchColumn() == 0) {
        $stmt = $pdo->prepare("DELETE FROM lobbies WHERE id = ?");
        $stmt->execute([$lid]);
    }
    session_destroy();
    header("Location: play.php");
    exit;
}